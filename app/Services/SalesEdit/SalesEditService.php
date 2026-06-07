<?php

namespace App\Services\SalesEdit;

use App\Repositories\SalesEdit\SalesEditRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Customer Record (sales-edit) backend service.
 *
 * Ports SalesDashboard::edit / salesEditLockPing / salesEditLockRelease from marluapp
 * with a couple of cleanups:
 *  - the legacy view stitched together ~8 collections; this service returns just the job
 *    + same-phone related list + lock state. Estimates/jobs/payments/etc are fetched by
 *    their own per-tab endpoints when those tabs ship.
 *  - the lock is keyed exactly the same way ("sales_edit_lock:{jobId}") so old and new
 *    clients cooperate during the migration window.
 */
class SalesEditService
{
    public const LOCK_TTL_SECONDS = 180;

    private const LOCK_KEY_PREFIX = 'sales_edit_lock:';

    /** Fields the FE may patch through PATCH /sales/edit/{id}/profile. Stored on the job document. */
    public const PROFILE_FIELDS = [
        'JobCollection_Customer_Full_Name',
        'JobCollection_Customer_Phone',
        'JobCollection_Customer_Email',
        'Customer_Address',
        'Customer_City',
        'Customer_Province',
        'Customer_Postal_Code',
        'Customer_Unit_Number',
        'Customer_Address_Coordinates',
        'JobCollection_HCP_Customer_ID',
        'JobCollection_HCP_Address_ID',
        'JobCollection_HCP_Customer_URL',
    ];

    /**
     * Fields the FE may patch through PATCH /sales/edit/{id}/sales-marketing.
     * Ports the editable inputs of the legacy sales-marketing-tab.blade.php. Audio capture,
     * support-file upload and Google-Calendar event creation are separate sub-features and
     * are NOT in this whitelist.
     */
    public const SALES_MARKETING_FIELDS = [
        // Source / marketing
        'JobCollection_Brand',
        'JobCollection_Platform',
        'JobCollection_Customer_Type',
        'JobCollection_Campaign_Name',
        'JobCollection_Form',
        'JobCollection_Customer_Record_Addition_Type',
        'JobCollection_Reception_Date',
        'JobCollection_Customer_Message',

        // Comments
        'JobCollection_Setter_Comments',
        'JobCollection_Closer_Comments',
        'JobCollection_Office_Comments',

        // Assignments & pricing
        'JobCollection_Job_Setter_Full_Name',
        'JobCollection_Job_Closer_Full_Name',
        'JobCollection_Job_Admin_Full_Name',
        'JobCollection_Job_Admin_Assigned_Date',
        'JobCollection_Estimate_Price',
        'JobCollection_Sell_Price',
        'JobCollection_Sell_Date',
        'JobCollection_Jobs_Date',
        'JobCollection_Sale_Type',
        'JobCollection_Payment_Deal_Offered',

        // Follow-up
        'JobCollection_Follow_up_Boolean',
        'JobCollection_Assigned_Follow_Up',
        'JobCollection_Follow_up_Date',

        // Estimate scheduling
        'JobCollection_Estimate_Schedule_Calendar',
        'JobCollection_Estimate_Type',
        'JobCollection_Estimate_Condition',
        'JobCollection_Estimate_Schedule_Duration',
        'JobCollection_Estimate_Scheduling_Start_TimeZulu',
        'JobCollection_Estimate_Scheduling_End_TimeZulu',
        'JobCollection_Estimate_Scheduling_Notes',
        'JobCollection_Estimate_Scheduling_CalendarID',
        'JobCollection_Estimate_Status',
        'JobCollection_Estimate_Scheduling_Creation_Date',
        'JobCollection_Estimate_Reschedule_Setter',
        'JobCollection_Estimate_Reschedule_Creation_Date',

        // Deposit collection
        'JobCollection_Deposit_Collection_Boolean',
        'JobCollection_Deposit_Collected_User',
        'JobCollection_Deposit_Collection_Date',
        'JobCollection_Deposit_Payment_Method',
        'JobCollection_Deposit_Amount',
    ];

    public function __construct(private SalesEditRepository $repo)
    {
    }

    /** Open a Customer Record. Returns ['job' => array, 'related' => array, 'lock' => array]. */
    public function open(string $jobId, int $userId, string $userName): array
    {
        $job = $this->repo->findById($jobId);
        if ($job === null) {
            throw new \DomainException('Customer record not found', 404);
        }

        $lock = $this->acquireLock($jobId, $userId, $userName);
        if (!($lock['ok'] ?? false)) {
            throw new \DomainException(
                ($lock['holder_name'] ?? 'Another user').' is currently editing this customer.',
                423
            );
        }

        $related = $this->repo->relatedByPhone(
            (string) ($job['JobCollection_Customer_Phone'] ?? ''),
            $jobId,
        );

        return [
            'job' => $job,
            'related' => $related,
            'lock' => [
                'token' => $lock['token'],
                'ttl_seconds' => self::LOCK_TTL_SECONDS,
                'holder_id' => $userId,
            ],
        ];
    }

    /**
     * Read-only event log for the Timeline tab. No edit lock required (it's a read).
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(string $jobId): array
    {
        $job = $this->repo->findById($jobId);
        if ($job === null) {
            throw new \DomainException('Customer record not found', 404);
        }
        return $this->repo->eventsFor($jobId, (string) ($job['JobCollection_Customer_Phone'] ?? ''));
    }

    /**
     * Read-only estimate data for the Estimates tab. No edit lock required (it's a read).
     *
     * @return array<string, mixed>
     */
    public function estimates(string $jobId): array
    {
        $job = $this->repo->findById($jobId);
        if ($job === null) {
            throw new \DomainException('Customer record not found', 404);
        }
        return $this->repo->estimateData($jobId);
    }

    /**
     * Read-only Job-tab data: segments + payments + invoices for the job. No edit lock (read).
     *
     * @return array{segments: array, payments: array, invoices: array}
     */
    public function job(string $jobId): array
    {
        $job = $this->repo->findById($jobId);
        if ($job === null) {
            throw new \DomainException('Customer record not found', 404);
        }
        return $this->repo->jobDetails($jobId);
    }

    public function updateProfile(string $jobId, array $fields, int $userId, string $userName, string $token): array
    {
        return $this->updateWhitelisted($jobId, $fields, self::PROFILE_FIELDS, $userId, $userName, $token);
    }

    public function updateSalesMarketing(string $jobId, array $fields, int $userId, string $userName, string $token): array
    {
        return $this->updateWhitelisted($jobId, $fields, self::SALES_MARKETING_FIELDS, $userId, $userName, $token);
    }

    /**
     * Replace the whole `JobCollection_Estimate` sub-document (estimate versions, packages,
     * services, per-estimate customer/deposit, notes). The FE owns the structure + totals
     * (computed client-side, mirroring the legacy tab); the BE persists it atomically after
     * verifying the edit lock.
     *
     * @param array<string, mixed> $estimate
     * @return array{updated:int}
     */
    public function updateEstimates(string $jobId, array $estimate, int $userId, string $userName, string $token): array
    {
        $this->assertHolds($jobId, $userId, $token);
        $modified = $this->repo->updateFields($jobId, ['JobCollection_Estimate' => $estimate], $userName);
        return ['updated' => $modified];
    }

    /**
     * Patch only the keys present in $allowed (and supplied in $fields), after verifying the
     * caller still holds the edit lock. Values are coerced to strings to match the legacy
     * document shape; the FE only ever sends scalar form values for these fields.
     *
     * @param array<int, string> $allowed
     */
    private function updateWhitelisted(string $jobId, array $fields, array $allowed, int $userId, string $userName, string $token): array
    {
        $this->assertHolds($jobId, $userId, $token);

        $patch = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $patch[$key] = $fields[$key] === null ? '' : (string) $fields[$key];
            }
        }

        $modified = $this->repo->updateFields($jobId, $patch, $userName);
        return [
            'updated' => $modified,
            'fields' => $patch,
        ];
    }

    public function pingLock(string $jobId, int $userId, string $token): bool
    {
        $key = self::LOCK_KEY_PREFIX.$jobId;
        $existing = $this->readLock($key);
        if (!$existing) {
            return false;
        }
        if ((int) ($existing['user_id'] ?? 0) !== $userId) {
            return false;
        }
        if ((string) ($existing['token'] ?? '') !== $token) {
            return false;
        }
        Cache::put($key, $this->encode($existing), self::LOCK_TTL_SECONDS);
        return true;
    }

    public function releaseLock(string $jobId, int $userId, string $token): void
    {
        $key = self::LOCK_KEY_PREFIX.$jobId;
        $existing = $this->readLock($key);
        if (!$existing) {
            return;
        }
        if ((int) ($existing['user_id'] ?? 0) !== $userId) {
            return;
        }
        if ($token !== '' && (string) ($existing['token'] ?? '') !== $token) {
            return;
        }
        Cache::forget($key);
    }

    /**
     * Try to acquire the per-job lock. If the current user already holds it (e.g. they reloaded
     * the page) we re-issue a fresh token rather than locking them out of their own record.
     */
    private function acquireLock(string $jobId, int $userId, string $userName): array
    {
        $key = self::LOCK_KEY_PREFIX.$jobId;
        $token = Str::random(48);
        $payload = $this->encode([
            'user_id' => $userId,
            'user_name' => $userName,
            'token' => $token,
        ]);

        if (Cache::add($key, $payload, self::LOCK_TTL_SECONDS)) {
            return ['ok' => true, 'token' => $token];
        }

        $existing = $this->readLock($key);
        if (!$existing) {
            if (Cache::add($key, $payload, self::LOCK_TTL_SECONDS)) {
                return ['ok' => true, 'token' => $token];
            }
            $existing = $this->readLock($key);
        }

        if ($existing && (int) ($existing['user_id'] ?? 0) === $userId) {
            Cache::put($key, $payload, self::LOCK_TTL_SECONDS);
            return ['ok' => true, 'token' => $token];
        }

        return [
            'ok' => false,
            'holder_name' => $existing['user_name'] ?? 'Another user',
        ];
    }

    private function assertHolds(string $jobId, int $userId, string $token): void
    {
        if (!$this->pingLock($jobId, $userId, $token)) {
            throw new \DomainException('Edit lock lost. Reload the page to continue.', 423);
        }
    }

    private function readLock(string $key): ?array
    {
        $raw = Cache::get($key);
        if (!$raw) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function encode(array $payload): string
    {
        return (string) json_encode($payload);
    }
}

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

    public function updateProfile(string $jobId, array $fields, int $userId, string $userName, string $token): array
    {
        $this->assertHolds($jobId, $userId, $token);

        $patch = [];
        foreach (self::PROFILE_FIELDS as $key) {
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

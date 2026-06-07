<?php

namespace App\Services\Sales;

use App\Repositories\Sales\SalesRepository;
use Illuminate\Http\Request;
use Jenssegers\Mongodb\Eloquent\Builder;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Translate sales-dashboard filter query parameters into a Mongo query.
 * Extracted from the legacy SalesDashboardController so the controller can stay thin.
 */
class SalesQueryBuilder
{
    private const SEARCH_FIELDS = [
        'JobCollection_Customer_Full_Name',
        'JobCollection_Customer_Message',
        'JobCollection_Customer_Email',
        'JobCollection_Brand',
        'JobCollection_Platform',
        'JobCollection_Customer_Phone',
        'JobCollection_Setter_Comments',
        'JobCollection_Closer_Comments',
        'JobCollection_Office_Comments',
        'JobCollection_Job_Substatus',
        'JobCollection_Job_Status',
        'JobCollection_Job_Stage',
        'JobCollection_Job_Setter_Full_Name',
        'JobCollection_Job_Closer_Full_Name',
        'JobCollection_Job_Admin_Full_Name',
        'JobCollection_Assigned_Follow_Up',
        'JobCollection_Campaign_Name',
        'JobCollection_Form',
    ];

    public function __construct(private SalesRepository $repo)
    {
    }

    public function build(Request $request): Builder
    {
        $search = trim((string) $request->query('search', ''));
        // Accept both the new (`search_flg`) and the legacy marluapp (`searchflg`) names
        // so the dashboard works regardless of the FE version emitting the params.
        $searchFlg = filter_var(
            $request->query('search_flg', $request->query('searchflg', false)),
            FILTER_VALIDATE_BOOLEAN
        );

        $query = $this->repo->newQuery();

        // Role-based row scoping (legacy SalesDashboard@index): a Setter/Closer/Office
        // admin only ever sees their own jobs; a General admin sees everything. This must
        // be enforced server-side regardless of the filter params the client sends.
        $this->applyRoleScope($query);

        if ($search !== '' && !$searchFlg) {
            return $this->applySearchOrGroup($query, $search);
        }

        $this->applyRegularFilters($query, $request);
        $this->applyStatusTriples($query, (string) $request->query('status_values', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $this->applySearchOrGroup($q, $search));
        }

        return $query;
    }

    /**
     * Restrict the result set to the rows the authenticated employee is allowed to see,
     * mirroring the legacy role branches:
     *   - Admin / General  → no restriction (sees all)
     *   - Admin / Office   → only jobs where they are the assigned Admin
     *   - Seller / Setter  → only jobs where they are the assigned Setter
     *   - Seller / Closer  → only jobs where they are the assigned Closer
     *
     * Note: unlike the legacy `?default=1` shortcut, this scope is NOT bypassable by a
     * client-supplied param — that would let an Office/Setter/Closer user read every
     * record. The "Default view" UX is a column/preset concern, not a visibility one.
     */
    private function applyRoleScope(Builder $query): void
    {
        foreach ($this->roleScopeFilter() as $field => $value) {
            $query->where($field, $value);
        }
    }

    /**
     * The role restriction as a plain `[field => value]` map (empty = no restriction).
     * Shared by the read query (applyRoleScope) and the bulk-write paths so a scoped
     * user can never update jobs outside their scope, even with hand-crafted IDs.
     *
     * @return array<string, string>
     */
    public function roleScopeFilter(): array
    {
        $user = JWTAuth::user();
        if ($user === null) {
            return [];
        }

        $type = (string) ($user->Employee_User_Type ?? '');
        $sub = (string) ($user->Employee_User_SubType ?? '');
        $name = trim((string) ($user->Employee_Full_Name ?? ''));

        if ($name === '') {
            return [];
        }

        if ($type === 'Admin' && $sub === 'General') {
            return [];
        }
        if ($type === 'Admin' && $sub === 'Office') {
            return ['JobCollection_Job_Admin_Full_Name' => $name];
        }
        if ($type === 'Seller' && $sub === 'Setter') {
            return ['JobCollection_Job_Setter_Full_Name' => $name];
        }
        if ($type === 'Seller' && $sub === 'Closer') {
            return ['JobCollection_Job_Closer_Full_Name' => $name];
        }

        return [];
    }

    private function applySearchOrGroup($query, string $search): Builder
    {
        $query->where(function ($q) use ($search) {
            foreach (self::SEARCH_FIELDS as $field) {
                $q->orWhere($field, 'like', '%' . $search . '%');
            }
        });
        return $query;
    }

    private function applyRegularFilters(Builder $query, Request $request): void
    {
        $items = $this->collectRegularFilterItems($request);
        if ($items === []) {
            return;
        }

        $query->where(function ($q) use ($items) {
            foreach ($items as $item) {
                if (count($item) > 2) {
                    [$field, $op, $val] = $item;
                    if (
                        ($field === 'JobCollection_Reception_Date' || $field === 'JobCollection_Estimate_Scheduling_Start_TimeZulu')
                        && $op === '<='
                    ) {
                        $next = date('Y-m-d', strtotime((string) $val) + 86400);
                        $q->where($field, $op, $next);
                    } else {
                        $q->where($field, $op, $val);
                    }
                } else {
                    $q->whereIn($item[0], $item[1]);
                }
            }
        });
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function collectRegularFilterItems(Request $request): array
    {
        $filter = [];
        $comma = static fn (?string $v): array => $v === null || $v === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $v))));

        $multi = [
            'brand' => 'JobCollection_Brand',
            'platform' => 'JobCollection_Platform',
            'setter' => 'JobCollection_Job_Setter_Full_Name',
            'closer' => 'JobCollection_Job_Closer_Full_Name',
            'admin' => 'JobCollection_Job_Admin_Full_Name',
            'assigned' => 'JobCollection_Assigned_Follow_Up',
            'follow' => 'JobCollection_Follow_up_Boolean',
            'lsuser' => 'JobCollection_Job_Last_Update_User',
            'estatus' => 'JobCollection_Estimate_Status',
            'etype' => 'JobCollection_Estimate_Type',
            'customertype' => 'JobCollection_Customer_Type',
            'condition' => 'JobCollection_Estimate_Condition',
            'rsetter' => 'JobCollection_Estimate_Reschedule_Setter',
            'caddition' => 'JobCollection_Customer_Record_Addition_Type',
            // Legacy alias (`reschedulesetter` / `recordadditiontype`) — kept for
            // back-compat with marluapp links that still target the API directly.
            'reschedulesetter' => 'JobCollection_Estimate_Reschedule_Setter',
            'recordadditiontype' => 'JobCollection_Customer_Record_Addition_Type',
            'paymentdealoffered' => 'JobCollection_Payment_Deal_Offered',
            'depositcollectedby' => 'JobCollection_Deposit_Collected_User',
            'depositpaymentmethod' => 'JobCollection_Deposit_Payment_Method',
            'schedule' => 'JobCollection_Estimate_Schedule_Calendar',
        ];
        foreach ($multi as $param => $field) {
            if ($request->filled($param)) {
                $vals = $comma((string) $request->query($param));
                if ($vals !== []) {
                    $filter[] = [$field, $vals];
                }
            }
        }

        $dateMap = [
            ['sdate', 'JobCollection_Reception_Date', '>='],
            ['edate', 'JobCollection_Reception_Date', '<='],
            ['lsdate', 'JobCollection_Job_Last_Update', '>='],
            ['ledate', 'JobCollection_Job_Last_Update', '<='],
            ['esdate', 'JobCollection_Estimate_Scheduling_Start_TimeZulu', '>='],
            ['eedate', 'JobCollection_Estimate_Scheduling_Start_TimeZulu', '<='],
            ['csdate', 'JobCollection_Estimate_Scheduling_Creation_Date', '>='],
            ['cedate', 'JobCollection_Estimate_Scheduling_Creation_Date', '<='],
            ['rcsdate', 'JobCollection_Estimate_Reschedule_Creation_Date', '>='],
            ['rcedate', 'JobCollection_Estimate_Reschedule_Creation_Date', '<='],
            ['fsdate', 'JobCollection_Follow_up_Date', '>='],
            ['fedate', 'JobCollection_Follow_up_Date', '<='],
            ['asdate', 'JobCollection_Job_Admin_Assigned_Date', '>='],
            ['aedate', 'JobCollection_Job_Admin_Assigned_Date', '<='],
            ['ssdate', 'JobCollection_Sell_Date', '>='],
            ['sedate', 'JobCollection_Sell_Date', '<='],
            ['jsdate', 'JobCollection_Jobs_Date', '>='],
            ['jedate', 'JobCollection_Jobs_Date', '<='],
            ['depositdatefrom', 'JobCollection_Deposit_Collection_Date', '>='],
            ['depositdateto', 'JobCollection_Deposit_Collection_Date', '<='],
        ];
        foreach ($dateMap as [$param, $field, $op]) {
            if ($request->filled($param)) {
                $filter[] = [$field, $op, $request->query($param)];
            }
        }

        $likeMap = [
            ['message', 'JobCollection_Customer_Message'],
            ['scomment', 'JobCollection_Setter_Comments'],
            ['ccomment', 'JobCollection_Closer_Comments'],
            ['ocomment', 'JobCollection_Office_Comments'],
            ['job_tags', 'JobCollection_Job_Tags'],
        ];
        foreach ($likeMap as [$param, $field]) {
            if ($request->filled($param)) {
                $filter[] = [$field, 'like', '%' . (string) $request->query($param) . '%'];
            }
        }

        $exactMap = [
            ['city', 'Customer_City'],
            ['pcode', 'Customer_Postal_Code'],
        ];
        foreach ($exactMap as [$param, $field]) {
            if ($request->filled($param)) {
                $filter[] = [$field, '=', $request->query($param)];
            }
        }

        return $filter;
    }

    /**
     * Apply S-S-S restriction. Triples are `Stage|Status|Substatus` joined with `;`.
     * Empty input means "no restriction" — the dashboard server-side enforces visibility
     * via JWT-derived role rules elsewhere; this method intentionally does not coerce.
     */
    private function applyStatusTriples(Builder $query, string $statusValues): void
    {
        $statusValues = trim($statusValues);
        if ($statusValues === '') {
            return;
        }

        $triples = [];
        foreach (explode(';', $statusValues) as $item) {
            $parts = explode('|', trim($item));
            if (count($parts) !== 3) {
                continue;
            }
            [$stage, $status, $sub] = array_map('trim', $parts);
            if ($stage !== '' && $status !== '' && $sub !== '') {
                $triples[] = [$stage, $status, $sub];
            }
        }
        if ($triples === []) {
            return;
        }

        $query->where(function ($q) use ($triples) {
            foreach ($triples as $t) {
                $q->orWhere(function ($sub) use ($t) {
                    $sub->where('JobCollection_Job_Stage', $t[0])
                        ->where('JobCollection_Job_Status', $t[1])
                        ->where('JobCollection_Job_Substatus', $t[2]);
                });
            }
        });
    }
}

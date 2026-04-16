<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\JobsDatabaseCollection;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

/**
 * API for the React Sales Dashboard (legacy: marluapp `/sales/sales-view` + SalesDashboard@index).
 */
class SalesDashboardController extends Controller
{
    /**
     * Same field coverage as marluapp `sales-view` grid (nested docs loaded by key).
     *
     * @var string[]
     */
    private const DASHBOARD_LIST_FIELDS = [
        '_id',
        'Phone_Flag',
        'JobCollection_Customer_Full_Name',
        'JobCollection_Customer_Phone',
        'JobCollection_Brand',
        'JobCollection_Reception_Date',
        'JobCollection_Customer_Message',
        'JobCollection_Customer_Image_Link',
        'JobCollection_Job_Stage',
        'JobCollection_Job_Status',
        'JobCollection_Job_Substatus',
        'JobCollection_Job_Last_Update',
        'JobCollection_Job_Last_Update_User',
        'JobCollection_Audio_Data',
        'JobCollection_Setter_Comments',
        'JobCollection_Closer_Comments',
        'JobCollection_Office_Comments',
        'JobCollection_Estimate_Scheduling_Start_TimeZulu',
        'JobCollection_Follow_up_Boolean',
        'JobCollection_Assigned_Follow_Up',
        'JobCollection_Follow_up_Date',
        'JobCollection_Job_Setter_Full_Name',
        'JobCollection_Estimate_Schedule_Calendar',
        'JobCollection_Job_Closer_Full_Name',
        'JobCollection_Job_Admin_Full_Name',
        'JobCollection_Estimate',
        'JobCollection_Estimate_Price',
        'JobCollection_Job',
        'JobCollection_Job_Admin_Assigned_Date',
        'JobCollection_Sell_Date',
        'JobCollection_Jobs_Date',
        'JobCollection_Estimate_Type',
        'JobCollection_Estimate_Condition',
        'JobCollection_Estimate_Scheduling_Creation_Date',
        'JobCollection_Estimate_Status',
        'JobCollection_Customer_Type',
        'JobCollection_Deposit_Collection_Boolean',
        'JobCollection_Deposit_Collected_User',
        'JobCollection_Deposit_Collection_Date',
        'JobCollection_Deposit_Payment_Method',
        'JobCollection_Deposit_Amount',
        'JobCollection_Customer_Email',
        'Customer_Postal_Code',
        'Customer_City',
        'JobCollection_Platform',
        'JobCollection_Estimate_Reschedule_Setter',
        'JobCollection_Estimate_Reschedule_Creation_Date',
        'JobCollection_Customer_Record_Addition_Type',
        'JobCollection_Campaign_Name',
        'JobCollection_Form',
    ];

    /**
     * Same projection as the dashboard list plus `Customer_Address` for CSV export.
     *
     * @var string[]
     */
    private const EXPORT_SELECT_FIELDS = [
        '_id',
        'Phone_Flag',
        'JobCollection_Customer_Full_Name',
        'JobCollection_Customer_Phone',
        'JobCollection_Brand',
        'JobCollection_Reception_Date',
        'JobCollection_Customer_Message',
        'JobCollection_Customer_Image_Link',
        'JobCollection_Job_Stage',
        'JobCollection_Job_Status',
        'JobCollection_Job_Substatus',
        'JobCollection_Job_Last_Update',
        'JobCollection_Job_Last_Update_User',
        'JobCollection_Audio_Data',
        'JobCollection_Setter_Comments',
        'JobCollection_Closer_Comments',
        'JobCollection_Office_Comments',
        'JobCollection_Estimate_Scheduling_Start_TimeZulu',
        'JobCollection_Follow_up_Boolean',
        'JobCollection_Assigned_Follow_Up',
        'JobCollection_Follow_up_Date',
        'JobCollection_Job_Setter_Full_Name',
        'JobCollection_Estimate_Schedule_Calendar',
        'JobCollection_Job_Closer_Full_Name',
        'JobCollection_Job_Admin_Full_Name',
        'JobCollection_Estimate',
        'JobCollection_Estimate_Price',
        'JobCollection_Job',
        'JobCollection_Job_Admin_Assigned_Date',
        'JobCollection_Sell_Date',
        'JobCollection_Jobs_Date',
        'JobCollection_Estimate_Type',
        'JobCollection_Estimate_Condition',
        'JobCollection_Estimate_Scheduling_Creation_Date',
        'JobCollection_Estimate_Status',
        'JobCollection_Customer_Type',
        'JobCollection_Deposit_Collection_Boolean',
        'JobCollection_Deposit_Collected_User',
        'JobCollection_Deposit_Collection_Date',
        'JobCollection_Deposit_Payment_Method',
        'JobCollection_Deposit_Amount',
        'JobCollection_Customer_Email',
        'Customer_Postal_Code',
        'Customer_City',
        'Customer_Address',
        'JobCollection_Platform',
        'JobCollection_Estimate_Reschedule_Setter',
        'JobCollection_Estimate_Reschedule_Creation_Date',
        'JobCollection_Customer_Record_Addition_Type',
        'JobCollection_Campaign_Name',
        'JobCollection_Form',
    ];

    /**
     * Column order and labels match marluapp `ExportsSalesDashboard.php`.
     *
     * @var string[]
     */
    private const EXPORT_CSV_HEADERS = [
        'Customer Name',
        'Phone',
        'Brand',
        'Reception Date',
        'Customer Message',
        'Sell Stage',
        'Sell Status',
        'Sell Substatus',
        'Last S-S-S Update',
        'Last S-S-S Update By',
        'Setter Comments',
        'Closer Comments',
        'Office Comments',
        'Estimate Date',
        'Follow-up',
        'Assigned To Follow-up',
        'Follow-up Date',
        'Setter',
        'Scheduled in Calendar Of',
        'Closer',
        'Admin',
        'Estimate Versions',
        'Estimate',
        'Booked',
        'Deposits',
        'Discounts',
        'Upsells',
        'Invoiced',
        'Total Paid',
        'Due',
        'Admin Assignation',
        'Sell Date',
        'Job\'s Date',
        'Estimate Type',
        'Scheduling Condition',
        'Estimate Creation Date',
        'Estimates Scheduling Status',
        'Customer Type',
        'Deposit Collection',
        'Email',
        'Postal Code',
        'City',
        'Customer Address',
        'Platform',
        'Reschedule - Setter',
        'Reschedule - Creation Date',
        'Customer Record Addition Type',
        'Campaign',
        'Form',
        'Customer Record URL',
    ];

    /** @var string[] */
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

    public function getDashboard(Request $request)
    {
        $auth = $this->requireAuth($request);
        if ($auth) {
            return $auth;
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $query = $this->buildFilteredQuery($request);

        /*
         * Sort must be index-backed or MongoDB can exceed the ~32MB in-memory sort limit
         * (often when jumping to the last page: large skip + sort). Run once:
         * `php artisan sales:create-dashboard-indexes`
         */
        $paginator = $query
            ->select(self::DASHBOARD_LIST_FIELDS)
            ->orderBy('JobCollection_Reception_Date', 'desc')
            ->orderBy('JobCollection_Customer_Full_Name', 'desc')
            ->paginate($perPage);

        foreach ($paginator->items() as $item) {
            $flg = JobsDatabaseCollection::where('JobCollection_Customer_Phone', $item->JobCollection_Customer_Phone)->count() > 1;
            $item->Phone_Flag = $flg;
        }

        return response()->json($paginator);
    }

    public function exportDashboard(Request $request)
    {
        $auth = $this->requireAuth($request);
        if ($auth) {
            return $auth;
        }

        $query = $this->buildFilteredQuery($request);
        $rows = $query
            ->select(self::EXPORT_SELECT_FIELDS)
            ->orderBy('JobCollection_Reception_Date', 'desc')
            ->orderBy('JobCollection_Customer_Full_Name', 'desc')
            ->limit(5000)
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::EXPORT_CSV_HEADERS);
            foreach ($rows as $row) {
                fputcsv($out, $this->mapRowToSalesExport($row));
            }
            fclose($out);
        }, 'sales-dashboard.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Row values aligned with marluapp `ExportsSalesDashboard::array()` data rows.
     *
     * @param  mixed  $row
     * @return array<int, string|int|float|null>
     */
    private function mapRowToSalesExport($row): array
    {
        $estimateDate = '';
        if (isset($row->JobCollection_Estimate_Scheduling_Start_TimeZulu)) {
            $estimateDate = substr(str_replace('T', ' ', (string) $row->JobCollection_Estimate_Scheduling_Start_TimeZulu), 0, 16);
        }

        $depositCollection = '';
        if (isset($row->JobCollection_Deposit_Collection_Boolean) && $row->JobCollection_Deposit_Collection_Boolean === 'Yes') {
            $depositCollection = ($row->JobCollection_Deposit_Collected_User ?? '') . ' | '
                . ($row->JobCollection_Deposit_Collection_Date ?? '') . ' | '
                . ($row->JobCollection_Deposit_Payment_Method ?? '') . ' | '
                . ($row->JobCollection_Deposit_Amount ?? '');
        }

        $estimateVersions = '';
        if (isset($row->JobCollection_Estimate)) {
            $est = $row->JobCollection_Estimate;
            $estArr = is_array($est) ? $est : (array) $est;
            $info = $estArr['JobCollection_Estimate_Information'] ?? null;
            if (is_array($info)) {
                $estimateVersions = (string) count($info);
            }
        }

        $job = $row->JobCollection_Job ?? null;
        $jobArr = [];
        if (is_array($job)) {
            $jobArr = $job;
        } elseif (is_object($job)) {
            $jobArr = (array) $job;
        }

        $jobMoney = static function (string $key) use ($jobArr): string {
            if (!array_key_exists($key, $jobArr) || $jobArr[$key] === null || $jobArr[$key] === '') {
                return '';
            }

            return number_format((float) $jobArr[$key], 2);
        };

        $estimatePrice = '';
        if (isset($row->JobCollection_Estimate_Price)) {
            $estimatePrice = number_format((float) $row->JobCollection_Estimate_Price, 2);
        }

        $id = $row->getKey();
        $idStr = $id !== null ? (string) $id : '';
        $base = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $recordUrl = $base !== '' && $idStr !== ''
            ? $base . '/sales/edit?id=' . rawurlencode($idStr)
            : '';

        return [
            $row->JobCollection_Customer_Full_Name ?? '',
            $row->JobCollection_Customer_Phone ?? '',
            $row->JobCollection_Brand ?? '',
            $row->JobCollection_Reception_Date ?? '',
            $row->JobCollection_Customer_Message ?? '',
            $row->JobCollection_Job_Stage ?? '',
            $row->JobCollection_Job_Status ?? '',
            isset($row->JobCollection_Job_Substatus) ? $row->JobCollection_Job_Substatus : '',
            $row->JobCollection_Job_Last_Update ?? '',
            $row->JobCollection_Job_Last_Update_User ?? 'Unknown User',
            $row->JobCollection_Setter_Comments ?? '',
            $row->JobCollection_Closer_Comments ?? '',
            $row->JobCollection_Office_Comments ?? '',
            $estimateDate,
            $row->JobCollection_Follow_up_Boolean ?? '',
            isset($row->JobCollection_Assigned_Follow_Up) ? $row->JobCollection_Assigned_Follow_Up : '',
            isset($row->JobCollection_Follow_up_Date) ? $row->JobCollection_Follow_up_Date : '',
            isset($row->JobCollection_Job_Setter_Full_Name) ? $row->JobCollection_Job_Setter_Full_Name : 'Not Assigned',
            isset($row->JobCollection_Estimate_Schedule_Calendar) ? $row->JobCollection_Estimate_Schedule_Calendar : '',
            isset($row->JobCollection_Job_Closer_Full_Name) ? $row->JobCollection_Job_Closer_Full_Name : 'Not Assigned',
            isset($row->JobCollection_Job_Admin_Full_Name) ? $row->JobCollection_Job_Admin_Full_Name : 'Not Assigned',
            $estimateVersions,
            $estimatePrice,
            $jobMoney('Job_Booked'),
            $jobMoney('Job_Deposits_Subtotal'),
            $jobMoney('Job_Discounts'),
            $jobMoney('Job_Upsells'),
            $jobMoney('Job_Subtotal_Less_Discounts'),
            $jobMoney('Job_Overall_Subtotal_Payments'),
            $jobMoney('Job_Pending_Subtotal_Balance'),
            isset($row->JobCollection_Job_Admin_Assigned_Date) ? $row->JobCollection_Job_Admin_Assigned_Date : '',
            isset($row->JobCollection_Sell_Date) ? $row->JobCollection_Sell_Date : '',
            isset($row->JobCollection_Jobs_Date) ? $row->JobCollection_Jobs_Date : '',
            isset($row->JobCollection_Estimate_Type) ? $row->JobCollection_Estimate_Type : '',
            isset($row->JobCollection_Estimate_Condition) ? $row->JobCollection_Estimate_Condition : '',
            isset($row->JobCollection_Estimate_Scheduling_Creation_Date) ? $row->JobCollection_Estimate_Scheduling_Creation_Date : '',
            isset($row->JobCollection_Estimate_Status) ? $row->JobCollection_Estimate_Status : 'Not Done',
            isset($row->JobCollection_Customer_Type) ? $row->JobCollection_Customer_Type : '',
            $depositCollection,
            isset($row->JobCollection_Customer_Email) ? $row->JobCollection_Customer_Email : '',
            isset($row->Customer_Postal_Code) ? $row->Customer_Postal_Code : '',
            isset($row->Customer_City) ? $row->Customer_City : '',
            isset($row->Customer_Address) ? $row->Customer_Address : '',
            isset($row->JobCollection_Platform) ? $row->JobCollection_Platform : '',
            isset($row->JobCollection_Estimate_Reschedule_Setter) ? $row->JobCollection_Estimate_Reschedule_Setter : '',
            isset($row->JobCollection_Estimate_Reschedule_Creation_Date) ? $row->JobCollection_Estimate_Reschedule_Creation_Date : '',
            isset($row->JobCollection_Customer_Record_Addition_Type) ? $row->JobCollection_Customer_Record_Addition_Type : 'Not Defined',
            isset($row->JobCollection_Campaign_Name) ? $row->JobCollection_Campaign_Name : '',
            isset($row->JobCollection_Form) ? $row->JobCollection_Form : '',
            $recordUrl,
        ];
    }

    public function assignSetter(Request $request)
    {
        $auth = $this->requireAuth($request);
        if ($auth) {
            return $auth;
        }

        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string',
            'setter_name' => 'required|string|max:500',
        ]);

        $updated = 0;
        foreach ($data['ids'] as $id) {
            try {
                $oid = new ObjectId($id);
            } catch (\Throwable $e) {
                continue;
            }
            $n = JobsDatabaseCollection::where('_id', $oid)->update([
                'JobCollection_Job_Setter_Full_Name' => $data['setter_name'],
            ]);
            $updated += (int) $n;
        }

        return response()->json(['updated' => $updated]);
    }

    /**
     * Bulk update jobs (legacy: POST /api/bulk_action). Updates by explicit ids, or by dashboard filter when ids is empty.
     */
    public function bulkAction(Request $request)
    {
        $auth = $this->requireAuth($request);
        if ($auth) {
            return $auth;
        }

        $data = $request->validate([
            'ids' => 'nullable|array',
            'ids.*' => 'string',
            'fields' => 'required|array|min:1',
            'fields.*' => 'nullable|string|max:2000',
            'filter_query' => 'nullable|array',
        ]);

        $allowed = [
            'JobCollection_Job_Setter_Full_Name',
            'JobCollection_Job_Closer_Full_Name',
            'JobCollection_Job_Admin_Full_Name',
            'JobCollection_Job_Stage',
            'JobCollection_Job_Status',
            'JobCollection_Job_Substatus',
        ];

        $fields = [];
        foreach ($data['fields'] as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $fields[$key] = $value;
        }

        if ($fields === []) {
            return response()->json(['message' => 'No valid fields to update'], 422);
        }

        $ids = $data['ids'] ?? [];
        if (is_array($ids) && count($ids) > 0) {
            $updated = 0;
            foreach ($ids as $id) {
                try {
                    $oid = new ObjectId($id);
                } catch (\Throwable $e) {
                    continue;
                }
                $updated += (int) JobsDatabaseCollection::where('_id', $oid)->update($fields);
            }

            return response()->json(['updated' => $updated]);
        }

        $filterQuery = $data['filter_query'] ?? [];
        if (!is_array($filterQuery) || $filterQuery === []) {
            return response()->json(['message' => 'filter_query is required when ids is empty'], 422);
        }

        $sub = Request::create('', 'GET', $filterQuery);
        $query = $this->buildFilteredQuery($sub);
        $updated = $query->update($fields);

        return response()->json(['updated' => (int) $updated]);
    }

    private function requireAuth(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $token = $request->header('Authorization');
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $validToken = $this->validateToken($token);
        if ($validToken->getStatusCode() != 200) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return null;
    }

    /**
     * @return \Jenssegers\Mongodb\Eloquent\Builder
     */
    private function buildFilteredQuery(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $searchFlg = filter_var($request->query('search_flg', false), FILTER_VALIDATE_BOOLEAN);

        $query = JobsDatabaseCollection::query()->where('Customer_Country', 'Canada');

        if ($search !== '' && !$searchFlg) {
            $this->applySearchOrGroup($query, $search);

            return $query;
        }

        $this->applyRegularFilters($query, $request);
        $this->applyStatusTriples($query, (string) $request->query('status_values', ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $this->applySearchOrGroup($q, $search);
            });
        }

        return $query;
    }

    /**
     * @param \Jenssegers\Mongodb\Eloquent\Builder $query
     */
    private function applySearchOrGroup($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            foreach (self::SEARCH_FIELDS as $field) {
                $q->orWhere($field, 'like', '%' . $search . '%');
            }
        });
    }

    /**
     * @param \Jenssegers\Mongodb\Eloquent\Builder $query
     */
    private function applyRegularFilters($query, Request $request): void
    {
        $items = $this->collectRegularFilterItems($request);
        if ($items === []) {
            return;
        }

        $query->where(function ($q) use ($items) {
            foreach ($items as $item) {
                if (count($item) > 2) {
                    $field = $item[0];
                    $op = $item[1];
                    $val = $item[2];
                    if (
                        ($field === 'JobCollection_Reception_Date' || $field === 'JobCollection_Estimate_Scheduling_Start_TimeZulu')
                        && $op === '<='
                    ) {
                        $date = strtotime((string) $val);
                        $nextDate = date('Y-m-d', $date + 86400);
                        $q->where($field, $op, $nextDate);
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

        if ($request->filled('brand')) {
            $tmp = $comma($request->query('brand'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Brand', $tmp];
            }
        }
        if ($request->filled('platform')) {
            $tmp = $comma($request->query('platform'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Platform', $tmp];
            }
        }
        if ($request->filled('sdate')) {
            $filter[] = ['JobCollection_Reception_Date', '>=', $request->query('sdate')];
        }
        if ($request->filled('edate')) {
            $filter[] = ['JobCollection_Reception_Date', '<=', $request->query('edate')];
        }
        if ($request->filled('lsdate')) {
            $filter[] = ['JobCollection_Job_Last_Update', '>=', $request->query('lsdate')];
        }
        if ($request->filled('ledate')) {
            $filter[] = ['JobCollection_Job_Last_Update', '<=', $request->query('ledate')];
        }
        if ($request->filled('esdate')) {
            $filter[] = ['JobCollection_Estimate_Scheduling_Start_TimeZulu', '>=', $request->query('esdate')];
        }
        if ($request->filled('eedate')) {
            $filter[] = ['JobCollection_Estimate_Scheduling_Start_TimeZulu', '<=', $request->query('eedate')];
        }
        if ($request->filled('csdate')) {
            $filter[] = ['JobCollection_Estimate_Scheduling_Creation_Date', '>=', $request->query('csdate')];
        }
        if ($request->filled('cedate')) {
            $filter[] = ['JobCollection_Estimate_Scheduling_Creation_Date', '<=', $request->query('cedate')];
        }
        if ($request->filled('rcsdate')) {
            $filter[] = ['JobCollection_Estimate_Reschedule_Creation_Date', '>=', $request->query('rcsdate')];
        }
        if ($request->filled('rcedate')) {
            $filter[] = ['JobCollection_Estimate_Reschedule_Creation_Date', '<=', $request->query('rcedate')];
        }
        if ($request->filled('fsdate')) {
            $filter[] = ['JobCollection_Follow_up_Date', '>=', $request->query('fsdate')];
        }
        if ($request->filled('fedate')) {
            $filter[] = ['JobCollection_Follow_up_Date', '<=', $request->query('fedate')];
        }
        if ($request->filled('asdate')) {
            $filter[] = ['JobCollection_Job_Admin_Assigned_Date', '>=', $request->query('asdate')];
        }
        if ($request->filled('aedate')) {
            $filter[] = ['JobCollection_Job_Admin_Assigned_Date', '<=', $request->query('aedate')];
        }
        if ($request->filled('ssdate')) {
            $filter[] = ['JobCollection_Sell_Date', '>=', $request->query('ssdate')];
        }
        if ($request->filled('sedate')) {
            $filter[] = ['JobCollection_Sell_Date', '<=', $request->query('sedate')];
        }
        if ($request->filled('jsdate')) {
            $filter[] = ['JobCollection_Jobs_Date', '>=', $request->query('jsdate')];
        }
        if ($request->filled('jedate')) {
            $filter[] = ['JobCollection_Jobs_Date', '<=', $request->query('jedate')];
        }
        if ($request->filled('message')) {
            $filter[] = ['JobCollection_Customer_Message', 'like', '%' . $request->query('message') . '%'];
        }
        if ($request->filled('city')) {
            $filter[] = ['Customer_City', '=', $request->query('city')];
        }
        if ($request->filled('pcode')) {
            $filter[] = ['Customer_Postal_Code', '=', $request->query('pcode')];
        }
        if ($request->filled('setter')) {
            $tmp = $comma($request->query('setter'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Job_Setter_Full_Name', $tmp];
            }
        }
        if ($request->filled('closer')) {
            $tmp = $comma($request->query('closer'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Job_Closer_Full_Name', $tmp];
            }
        }
        if ($request->filled('admin')) {
            $tmp = $comma($request->query('admin'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Job_Admin_Full_Name', $tmp];
            }
        }
        if ($request->filled('assigned')) {
            $tmp = $comma($request->query('assigned'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Assigned_Follow_Up', $tmp];
            }
        }
        if ($request->filled('follow')) {
            $tmp = $comma($request->query('follow'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Follow_up_Boolean', $tmp];
            }
        }
        if ($request->filled('lsuser')) {
            $tmp = $comma($request->query('lsuser'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Job_Last_Update_User', $tmp];
            }
        }
        if ($request->filled('estatus')) {
            $tmp = $comma($request->query('estatus'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Estimate_Status', $tmp];
            }
        }
        if ($request->filled('etype')) {
            $tmp = $comma($request->query('etype'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Estimate_Type', $tmp];
            }
        }
        if ($request->filled('customertype')) {
            $tmp = $comma($request->query('customertype'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Customer_Type', $tmp];
            }
        }
        if ($request->filled('condition')) {
            $tmp = $comma($request->query('condition'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Estimate_Condition', $tmp];
            }
        }
        if ($request->filled('rsetter')) {
            $tmp = $comma($request->query('rsetter'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Estimate_Reschedule_Setter', $tmp];
            }
        }
        if ($request->filled('caddition')) {
            $tmp = $comma($request->query('caddition'));
            if ($tmp !== []) {
                $filter[] = ['JobCollection_Customer_Record_Addition_Type', $tmp];
            }
        }
        if ($request->filled('scomment')) {
            $filter[] = ['JobCollection_Setter_Comments', 'like', '%' . (string) $request->query('scomment') . '%'];
        }
        if ($request->filled('ccomment')) {
            $filter[] = ['JobCollection_Closer_Comments', 'like', '%' . (string) $request->query('ccomment') . '%'];
        }
        if ($request->filled('ocomment')) {
            $filter[] = ['JobCollection_Office_Comments', 'like', '%' . (string) $request->query('ocomment') . '%'];
        }
        if ($request->filled('job_tags')) {
            $filter[] = ['JobCollection_Job_Tags', 'like', '%' . (string) $request->query('job_tags') . '%'];
        }

        return $filter;
    }

    /**
     * @param \Jenssegers\Mongodb\Eloquent\Builder $query
     */
    private function applyStatusTriples($query, string $statusValues): void
    {
        $statusValues = trim($statusValues);
        if ($statusValues === '') {
            return;
        }

        $triples = [];
        foreach (explode(';', $statusValues) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $parts = explode('|', $item);
            if (count($parts) !== 3) {
                continue;
            }
            [$stageName, $statusName, $subName] = array_map('trim', $parts);
            if ($stageName === '' || $statusName === '' || $subName === '') {
                continue;
            }
            $triples[] = [$stageName, $statusName, $subName];
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

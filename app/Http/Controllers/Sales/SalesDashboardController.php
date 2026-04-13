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

        $paginator = $query
            ->orderBy('JobCollection_Reception_Date', 'desc')
            ->orderBy('JobCollection_Customer_Full_Name', 'desc')
            ->paginate($perPage);

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
            ->orderBy('JobCollection_Reception_Date', 'desc')
            ->limit(5000)
            ->get();

        $headers = [
            'Customer Name',
            'Phone',
            'Brand',
            'Reception Date',
            'Customer Message',
            'Sell Stage',
            'Sell Status',
            'Sell Substatus',
            'Last S-S-S Update',
            'Last Update By',
        ];

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->JobCollection_Customer_Full_Name ?? '',
                    $row->JobCollection_Customer_Phone ?? '',
                    $row->JobCollection_Brand ?? '',
                    $row->JobCollection_Reception_Date ?? '',
                    $row->JobCollection_Customer_Message ?? '',
                    $row->JobCollection_Job_Stage ?? '',
                    $row->JobCollection_Job_Status ?? '',
                    $row->JobCollection_Job_Substatus ?? '',
                    $row->JobCollection_Job_Last_Update ?? '',
                    $row->JobCollection_Job_Last_Update_User ?? '',
                ]);
            }
            fclose($out);
        }, 'sales-dashboard.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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

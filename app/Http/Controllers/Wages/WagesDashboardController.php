<?php

namespace App\Http\Controllers\Wages;

use App\Http\Controllers\Controller;
use App\Models\EmployeesDatabaseCollection;
use App\Models\WagesDatabaseCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Wages dashboard API (legacy: marluapp WagesDashboard@index + WagesController API routes).
 */
class WagesDashboardController extends Controller
{
    private const DROPDOWNS = [
        'WagesCollection_Vehicle' => [
            'BK52270 - Truck 1',
            'BK71774 - Truck 2',
            'AN76491 - Truck 3',
            'BN498818 - Truck 4',
            'CTDB054 - Versa 1',
            'CXFP368 - Fit 1',
            'CYVP857 - Rondo 1',
            'GVJY915 - MX-30 1',
        ],
        'WagesCollection_Job_ShiftType' => [
            'Regular Shift',
            'Night Shift',
        ],
    ];

    /**
     * Auth is enforced by the `jwt` route middleware. This helper just
     * exposes the JWT custom claims (name, type) for legacy callers.
     *
     * @return array{name: string, type: string}|null
     */
    private function jwtUser(Request $request): ?array
    {
        $claims = $this->authClaims();
        if ($claims === []) {
            return null;
        }
        return [
            'name' => (string) ($claims['name'] ?? ''),
            'type' => (string) ($claims['type'] ?? ''),
        ];
    }

    /**
     * @deprecated use route `jwt` middleware
     */
    private function requireAuth(Request $request): ?\Illuminate\Http\JsonResponse
    {
        return null;
    }

    /**
     * Initial page payload (list, job picker list, technicians, dropdowns, filter option values).
     */
    public function dashboard(Request $request)
    {
        if ($r = $this->requireAuth($request)) {
            return $r;
        }
        $user = $this->jwtUser($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hasFilter = $request->query->has('start_date')
            || $request->query->has('end_date')
            || $request->query->has('text');
        $search = (string) $request->query('text', '');
        if ($hasFilter) {
            $endDate = $request->query->has('end_date')
                ? (string) $request->query('end_date')
                : date('Y-m-d');
            $startDate = $request->query->has('start_date')
                ? (string) $request->query('start_date')
                : '';
        } else {
            $endDate = '';
            $startDate = '';
        }

        $listData = $this->queryListData($search, $startDate, $endDate, $user, $hasFilter);

        $jobList = WagesDatabaseCollection::where('WagesCollection_Job_Type', '=', 'Job')
            ->whereRaw(['$where' => 'this.WagesCollection_UserCreator == this.WagesCollection_TechnicianViewer'])
            ->get();

        $technicians = EmployeesDatabaseCollection::where('Employee_User_Type', 'Technician')->get();

        $totalWage = 0.0;
        foreach ($listData as $row) {
            $totalWage += (float) ($row->WagesCollection_TechnicianViewer_Wage ?? 0);
        }

        return response()->json([
            'listData' => $listData,
            'jobList' => $jobList,
            'technicians' => $technicians,
            'dropdowns' => self::DROPDOWNS,
            'filterOptions' => $this->buildFilterOptions($listData),
            'totalWage' => $totalWage,
            'user' => $user,
        ]);
    }

    /**
     * Same rules as marluapp WagesDashboard@index.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, WagesDatabaseCollection>
     */
    private function queryListData(string $search, string $startDate, string $endDate, array $user, bool $hasFilter)
    {
        $ListData = WagesDatabaseCollection::where(1);
        if ($search !== '') {
            $ListData = $ListData->where(function ($q) use ($search) {
                $q->where('WagesCollection_TechnicianViewer', 'like', '%' . $search . '%')
                    ->orWhere('WagesCollection_Job_ID', 'like', '%' . $search . '%')
                    ->orWhere('WagesCollection_Job_Type', 'like', '%' . $search . '%')
                    ->orWhere('WagesCollection_Wage_Status', 'like', '%' . $search . '%')
                    ->orWhere('WagesCollection_Job_Comments', 'like', '%' . $search . '%');
            });
        }
        if ($hasFilter && $endDate !== '') {
            $ListData = $ListData->where('WagesCollection_Job_Execution_Date', '<=', $endDate);
        }
        if ($hasFilter && $startDate !== '') {
            $ListData = $ListData->where('WagesCollection_Job_Execution_Date', '>=', $startDate);
        }

        if ($hasFilter) {
            if (($user['type'] ?? '') === 'Admin') {
                return $ListData->orderBy('WagesCollection_Job_Execution_Date', 'DESC')->get();
            }

            return $ListData->where('WagesCollection_TechnicianViewer', $user['name'])
                ->orderBy('WagesCollection_Job_Execution_Date', 'DESC')->get();
        }

        if (($user['type'] ?? '') === 'Admin') {
            return WagesDatabaseCollection::orderBy('WagesCollection_Job_Execution_Date', 'DESC')->get();
        }

        return WagesDatabaseCollection::where('WagesCollection_TechnicianViewer', $user['name'])
            ->orderBy('WagesCollection_Job_Execution_Date', 'DESC')->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, mixed>|Collection<int, mixed>  $listData
     * @return array<string, array<int, mixed>>
     */
    private function buildFilterOptions($listData): array
    {
        $keys = [
            'WagesCollection_Job_Execution_Date',
            'WagesCollection_Job_ID',
            'WagesCollection_Job_Type',
            'WagesCollection_TechnicianViewer',
            'WagesCollection_Job_Duration',
            'WagesCollection_Job_Price',
            'WagesCollection_Job_Percentage',
            'WagesCollection_TechnicianViewer_Wage',
            'WagesCollection_Wage_Status',
            'WagesCollection_Job_Comments',
            'WagesCollection_Vehicle',
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = [];
        }
        foreach ($listData as $row) {
            foreach ($keys as $k) {
                $v = $row->{$k} ?? null;
                if ($v !== null && $v !== '') {
                    $out[$k][] = $v;
                }
            }
        }
        foreach ($keys as $k) {
            $out[$k] = array_values(array_unique($out[$k]));
        }

        return $out;
    }

    public function getFilter(Request $request)
    {
        if ($r = $this->requireAuth($request)) {
            return $r;
        }
        $user = $this->jwtUser($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $ListData = WagesDatabaseCollection::where(1);

        $fields = [
            'WagesCollection_Job_Execution_Date',
            'WagesCollection_Job_ID',
            'WagesCollection_Job_Type',
            'WagesCollection_TechnicianViewer',
            'WagesCollection_Job_Price',
            'WagesCollection_Job_Percentage',
            'WagesCollection_TechnicianViewer_Wage',
            'WagesCollection_Wage_Status',
            'WagesCollection_Job_Comments',
            'WagesCollection_Job_Duration',
            'WagesCollection_Vehicle',
        ];
        foreach ($fields as $f) {
            if ($request->has($f) && $request->input($f) !== null && $request->input($f) !== '') {
                $ListData = $ListData->where($f, $request->input($f));
            }
        }

        if ($request->has('search') && $request->input('search') !== null && $request->input('search') !== '') {
            $s = (string) $request->input('search');
            $ListData = $ListData->where(function ($q) use ($s) {
                $q->where('WagesCollection_TechnicianViewer', 'like', '%' . $s . '%')
                    ->orWhere('WagesCollection_Job_ID', 'like', '%' . $s . '%')
                    ->orWhere('WagesCollection_Job_Type', 'like', '%' . $s . '%')
                    ->orWhere('WagesCollection_Wage_Status', 'like', '%' . $s . '%')
                    ->orWhere('WagesCollection_Job_Comments', 'like', '%' . $s . '%');
            });
        }
        if ($request->has('end_date') && $request->input('end_date') !== null && $request->input('end_date') !== '') {
            $ListData = $ListData->where('WagesCollection_Job_Execution_Date', '<=', $request->input('end_date'));
        }
        if ($request->has('start_date') && $request->input('start_date') !== null && $request->input('start_date') !== '') {
            $ListData = $ListData->where('WagesCollection_Job_Execution_Date', '>=', $request->input('start_date'));
        }

        if (($user['type'] ?? '') === 'Admin') {
            $rows = $ListData->orderBy('WagesCollection_Job_Execution_Date', 'DESC')->get();
        } else {
            $rows = $ListData->where('WagesCollection_TechnicianViewer', $user['name'])
                ->orderBy('WagesCollection_Job_Execution_Date', 'DESC')->get();
        }

        return response()->json($rows);
    }

    public function getJob(Request $request)
    {
        if ($r = $this->requireAuth($request)) {
            return $r;
        }
        $jobId = $request->input('WagesCollection_Job_ID');
        $result = WagesDatabaseCollection::where('WagesCollection_Job_ID', $jobId)
            ->where('WagesCollection_Job_Type', 'not LIKE', '%Adjustment%')
            ->get();
        for ($i = 0; $i < count($result); $i++) {
            $factor = EmployeesDatabaseCollection::where('Employee_Full_Name', $result[$i]->WagesCollection_UserCreator)->first();
            $result[$i]->wage_factor = $factor ? $factor->Empolyee_Factor_Job : null;
        }

        return response()->json($result);
    }

    public function getExistingJob(Request $request)
    {
        if ($r = $this->requireAuth($request)) {
            return $r;
        }
        $jobId = $request->input('WagesCollection_Job_ID');
        $result = WagesDatabaseCollection::where('WagesCollection_Job_ID', $jobId)
            ->whereRaw(['$where' => 'this.WagesCollection_UserCreator == this.WagesCollection_TechnicianViewer'])
            ->get();
        for ($i = 0; $i < count($result); $i++) {
            $factor = EmployeesDatabaseCollection::where('Employee_Full_Name', $result[$i]->WagesCollection_UserCreator)->first();
            $result[$i]->wage_factor = $factor ? $factor->Empolyee_Factor_Job : null;
        }

        return response()->json($result);
    }

    public function createJob(Request $request)
    {
        if ($r = $this->requireAuth($request)) {
            return $r;
        }
        $data = $request->input('data', []);
        if (!is_array($data)) {
            return response('fail', 422);
        }
        if ($request->input('flag') === 'newJob') {
            if (WagesDatabaseCollection::where('WagesCollection_Job_ID', $data[0]['WagesCollection_Job_ID'] ?? '')->exists()) {
                return response('duplicate', 200);
            }
        }
        $newIndex = (int) $request->input('newIndex', 0);
        for ($i = 0; $i < $newIndex; $i++) {
            if (!isset($data[$i])) {
                continue;
            }
            WagesDatabaseCollection::insert([
                'WagesCollection_Job_Comments' => $data[$i]['WagesCollection_Job_Comments'] ?? '',
                'WagesCollection_Vehicle' => $data[$i]['WagesCollection_Vehicle'] ?? '',
                'WagesCollection_Wage_Status' => $data[$i]['WagesCollection_Wage_Status'] ?? '',
                'WagesCollection_Wage_budget' => $data[$i]['WagesCollection_Wage_budget'] ?? '',
                'WagesCollection_Job_Percentage' => $data[$i]['WagesCollection_Job_Percentage'] ?? '',
                'WagesCollection_Job_Price' => $data[$i]['WagesCollection_Job_Price'] ?? '',
                'WagesCollection_Job_ShiftType' => $data[$i]['WagesCollection_Job_ShiftType'] ?? '',
                'WagesCollection_Job_Duration' => $data[$i]['WagesCollection_Job_Duration'] ?? '',
                'WagesCollection_Job_Execution_Date' => $data[$i]['WagesCollection_Job_Execution_Date'] ?? '',
                'WagesCollection_Technicians' => $data[$i]['WagesCollection_Technicians'] ?? '',
                'WagesCollection_Job_ID' => $data[$i]['WagesCollection_Job_ID'] ?? '',
                'WagesCollection_TechnicianViewer_Wage' => $data[$i]['WagesCollection_TechnicianViewer_Wage'] ?? '',
                'WagesCollection_Job_Type' => $data[$i]['WagesCollection_Job_Type'] ?? '',
                'WagesCollection_TechnicianViewer' => $data[$i]['WagesCollection_TechnicianViewer'] ?? '',
                'WagesCollection_UserCreator' => $data[$i]['WagesCollection_UserCreator'] ?? '',
            ]);
        }
        for ($i = $newIndex; $i < count($data); $i++) {
            if (!isset($data[$i])) {
                continue;
            }
            if (($data[$i]['WagesCollection_Wage_Status'] ?? '') !== 'Paid') {
                WagesDatabaseCollection::where('_id', $data[$i]['_id'] ?? null)->update([
                    'WagesCollection_Job_Comments' => $data[$i]['WagesCollection_Job_Comments'] ?? '',
                    'WagesCollection_Vehicle' => $data[$i]['WagesCollection_Vehicle'] ?? '',
                    'WagesCollection_Wage_Status' => $data[$i]['WagesCollection_Wage_Status'] ?? '',
                    'WagesCollection_Wage_budget' => $data[$i]['WagesCollection_Wage_budget'] ?? '',
                    'WagesCollection_Job_Percentage' => $data[$i]['WagesCollection_Job_Percentage'] ?? '',
                    'WagesCollection_Job_Price' => $data[$i]['WagesCollection_Job_Price'] ?? '',
                    'WagesCollection_Job_ShiftType' => $data[$i]['WagesCollection_Job_ShiftType'] ?? '',
                    'WagesCollection_Job_Duration' => $data[$i]['WagesCollection_Job_Duration'] ?? '',
                    'WagesCollection_Job_Execution_Date' => $data[$i]['WagesCollection_Job_Execution_Date'] ?? '',
                    'WagesCollection_Technicians' => $data[$i]['WagesCollection_Technicians'] ?? '',
                    'WagesCollection_Job_ID' => $data[$i]['WagesCollection_Job_ID'] ?? '',
                    'WagesCollection_TechnicianViewer_Wage' => $data[$i]['WagesCollection_TechnicianViewer_Wage'] ?? '',
                    'WagesCollection_Job_Type' => $data[$i]['WagesCollection_Job_Type'] ?? '',
                    'WagesCollection_TechnicianViewer' => $data[$i]['WagesCollection_TechnicianViewer'] ?? '',
                    'WagesCollection_UserCreator' => $data[$i]['WagesCollection_UserCreator'] ?? '',
                ]);
            } else {
                WagesDatabaseCollection::where('_id', $data[$i]['_id'] ?? null)->update([
                    'WagesCollection_Job_Comments' => $data[$i]['WagesCollection_Job_Comments'] ?? '',
                    'WagesCollection_Vehicle' => $data[$i]['WagesCollection_Vehicle'] ?? '',
                    'WagesCollection_Wage_Status' => $data[$i]['WagesCollection_Wage_Status'] ?? '',
                    'WagesCollection_Wage_budget' => $data[$i]['WagesCollection_Wage_budget'] ?? '',
                    'WagesCollection_Job_Percentage' => $data[$i]['WagesCollection_Job_Percentage'] ?? '',
                    'WagesCollection_Job_Price' => $data[$i]['WagesCollection_Job_Price'] ?? '',
                    'WagesCollection_Job_ShiftType' => $data[$i]['WagesCollection_Job_ShiftType'] ?? '',
                    'WagesCollection_Job_Duration' => $data[$i]['WagesCollection_Job_Duration'] ?? '',
                    'WagesCollection_Job_Execution_Date' => $data[$i]['WagesCollection_Job_Execution_Date'] ?? '',
                    'WagesCollection_Technicians' => $data[$i]['WagesCollection_Technicians'] ?? '',
                    'WagesCollection_Job_ID' => $data[$i]['WagesCollection_Job_ID'] ?? '',
                    'WagesCollection_Job_Type' => $data[$i]['WagesCollection_Job_Type'] ?? '',
                    'WagesCollection_TechnicianViewer' => $data[$i]['WagesCollection_TechnicianViewer'] ?? '',
                    'WagesCollection_UserCreator' => $data[$i]['WagesCollection_UserCreator'] ?? '',
                ]);
            }
        }
        $result = WagesDatabaseCollection::whereRaw(['$where' => 'this.WagesCollection_UserCreator == this.WagesCollection_TechnicianViewer'])
            ->where('WagesCollection_Job_ID', $data[0]['WagesCollection_Job_ID'] ?? '')
            ->where('WagesCollection_Job_Type', 'LIKE', '%Adjustment%')->get();

        $adjust = 'Adjustment ' . strval(count($result) + 1);
        for ($i = $newIndex; $i < count($data); $i++) {
            if (!isset($data[$i])) {
                continue;
            }
            if (($data[$i]['WagesCollection_Wage_Status'] ?? '') == 'Paid') {
                $result = WagesDatabaseCollection::where('WagesCollection_Job_ID', $data[$i]['WagesCollection_Job_ID'])
                    ->where('WagesCollection_Technicians', $data[$i]['WagesCollection_Technicians'])
                    ->where('WagesCollection_TechnicianViewer', $data[$i]['WagesCollection_TechnicianViewer'])
                    ->where('WagesCollection_UserCreator', $data[$i]['WagesCollection_UserCreator'])
                    ->where('WagesCollection_Job_Execution_Date', $data[$i]['WagesCollection_Job_Execution_Date'])
                    ->get();
                $wage = floatval($data[$i]['WagesCollection_TechnicianViewer_Wage']);
                for ($j = 0; $j < count($result); $j++) {
                    $wage = $wage - floatval($result[$j]->WagesCollection_TechnicianViewer_Wage);
                }
                $wage = round($wage, 2);
                WagesDatabaseCollection::insert([
                    'WagesCollection_Job_Comments' => $data[$i]['WagesCollection_Job_Comments'] ?? '',
                    'WagesCollection_Vehicle' => $data[$i]['WagesCollection_Vehicle'] ?? '',
                    'WagesCollection_Wage_Status' => '',
                    'WagesCollection_Wage_budget' => $data[$i]['WagesCollection_Wage_budget'] ?? '',
                    'WagesCollection_Job_Percentage' => $data[$i]['WagesCollection_Job_Percentage'] ?? '',
                    'WagesCollection_Job_Price' => $data[$i]['WagesCollection_Job_Price'] ?? '',
                    'WagesCollection_Job_ShiftType' => $data[$i]['WagesCollection_Job_ShiftType'] ?? '',
                    'WagesCollection_Job_Duration' => $data[$i]['WagesCollection_Job_Duration'] ?? '',
                    'WagesCollection_Job_Execution_Date' => $data[$i]['WagesCollection_Job_Execution_Date'] ?? '',
                    'WagesCollection_Technicians' => $data[$i]['WagesCollection_Technicians'] ?? '',
                    'WagesCollection_Job_ID' => $data[$i]['WagesCollection_Job_ID'] ?? '',
                    'WagesCollection_TechnicianViewer_Wage' => $wage,
                    'WagesCollection_Job_Type' => $adjust,
                    'WagesCollection_TechnicianViewer' => $data[$i]['WagesCollection_TechnicianViewer'] ?? '',
                    'WagesCollection_UserCreator' => $data[$i]['WagesCollection_UserCreator'] ?? '',
                ]);
            }
        }

        return response('ok', 200);
    }

    public function updateJob(Request $request)
    {
        if ($r = $this->requireAuth($request)) {
            return $r;
        }
        $data = $request->input('data', []);
        if (!is_array($data) || count($data) === 0) {
            return response('fail', 422);
        }

        $result = WagesDatabaseCollection::where('WagesCollection_Job_ID', $data[0]['WagesCollection_Job_ID'] ?? '')
            ->where('WagesCollection_Job_Type', 'not LIKE', '%Adjustment%')->get();
        $flag = [];
        $flag1 = [];
        for ($j = 0; $j < count($result); $j++) {
            $flag[$j] = 0;
            for ($i = 0; $i < count($data); $i++) {
                if ($j == 0) {
                    $flag1[$i] = 0;
                }
                if (
                    $result[$j]->WagesCollection_Job_ID == ($data[$i]['WagesCollection_Job_ID'] ?? null) &&
                    $result[$j]->WagesCollection_UserCreator == ($data[$i]['WagesCollection_UserCreator'] ?? null) &&
                    $result[$j]->WagesCollection_TechnicianViewer == ($data[$i]['WagesCollection_TechnicianViewer'] ?? null) &&
                    $result[$j]->WagesCollection_Job_Type == ($data[$i]['WagesCollection_Job_Type'] ?? null)
                ) {
                    $flag[$j] = 1;
                    $flag1[$i] = 1;
                    if (($data[$i]['WagesCollection_Wage_Status'] ?? '') !== 'Paid') {
                        WagesDatabaseCollection::where('WagesCollection_Job_ID', $data[$i]['WagesCollection_Job_ID'])
                            ->where('WagesCollection_UserCreator', $data[$i]['WagesCollection_UserCreator'])
                            ->where('WagesCollection_TechnicianViewer', $data[$i]['WagesCollection_TechnicianViewer'])
                            ->where('WagesCollection_Job_Type', $data[$i]['WagesCollection_Job_Type'])
                            ->update([
                                'WagesCollection_Job_Comments' => $data[$i]['WagesCollection_Job_Comments'] ?? '',
                                'WagesCollection_Vehicle' => $data[$i]['WagesCollection_Vehicle'] ?? '',
                                'WagesCollection_Wage_Status' => $data[$i]['WagesCollection_Wage_Status'] ?? '',
                                'WagesCollection_Wage_budget' => $data[$i]['WagesCollection_Wage_budget'] ?? '',
                                'WagesCollection_Job_Percentage' => $data[$i]['WagesCollection_Job_Percentage'] ?? '',
                                'WagesCollection_Job_Price' => $data[$i]['WagesCollection_Job_Price'] ?? '',
                                'WagesCollection_Job_ShiftType' => $data[$i]['WagesCollection_Job_ShiftType'] ?? '',
                                'WagesCollection_Job_Duration' => $data[$i]['WagesCollection_Job_Duration'] ?? '',
                                'WagesCollection_Job_Execution_Date' => $data[$i]['WagesCollection_Job_Execution_Date'] ?? '',
                                'WagesCollection_Technicians' => $data[$i]['WagesCollection_Technicians'] ?? '',
                                'WagesCollection_Job_ID' => $data[$i]['WagesCollection_Job_ID'] ?? '',
                                'WagesCollection_TechnicianViewer_Wage' => $data[$i]['WagesCollection_TechnicianViewer_Wage'] ?? '',
                                'WagesCollection_Job_Type' => $data[$i]['WagesCollection_Job_Type'] ?? '',
                                'WagesCollection_TechnicianViewer' => $data[$i]['WagesCollection_TechnicianViewer'] ?? '',
                                'WagesCollection_UserCreator' => $data[$i]['WagesCollection_UserCreator'] ?? '',
                            ]);
                    } else {
                        WagesDatabaseCollection::where('WagesCollection_Job_ID', $data[$i]['WagesCollection_Job_ID'])
                            ->where('WagesCollection_UserCreator', $data[$i]['WagesCollection_UserCreator'])
                            ->where('WagesCollection_TechnicianViewer', $data[$i]['WagesCollection_TechnicianViewer'])
                            ->where('WagesCollection_Job_Type', $data[$i]['WagesCollection_Job_Type'])
                            ->update([
                                'WagesCollection_Job_Comments' => $data[$i]['WagesCollection_Job_Comments'] ?? '',
                                'WagesCollection_Vehicle' => $data[$i]['WagesCollection_Vehicle'] ?? '',
                                'WagesCollection_Wage_Status' => $data[$i]['WagesCollection_Wage_Status'] ?? '',
                                'WagesCollection_Wage_budget' => $data[$i]['WagesCollection_Wage_budget'] ?? '',
                                'WagesCollection_Job_Percentage' => $data[$i]['WagesCollection_Job_Percentage'] ?? '',
                                'WagesCollection_Job_Price' => $data[$i]['WagesCollection_Job_Price'] ?? '',
                                'WagesCollection_Job_ShiftType' => $data[$i]['WagesCollection_Job_ShiftType'] ?? '',
                                'WagesCollection_Job_Duration' => $data[$i]['WagesCollection_Job_Duration'] ?? '',
                                'WagesCollection_Job_Execution_Date' => $data[$i]['WagesCollection_Job_Execution_Date'] ?? '',
                                'WagesCollection_Technicians' => $data[$i]['WagesCollection_Technicians'] ?? '',
                                'WagesCollection_Job_ID' => $data[$i]['WagesCollection_Job_ID'] ?? '',
                                'WagesCollection_Job_Type' => $data[$i]['WagesCollection_Job_Type'] ?? '',
                                'WagesCollection_TechnicianViewer' => $data[$i]['WagesCollection_TechnicianViewer'] ?? '',
                                'WagesCollection_UserCreator' => $data[$i]['WagesCollection_UserCreator'] ?? '',
                            ]);
                    }
                }
            }
        }
        for ($j = 0; $j < count($result); $j++) {
            if ($flag[$j] !== 1) {
                WagesDatabaseCollection::where('_id', $result[$j]->_id)->delete();
            }
        }
        for ($i = 0; $i < count($data); $i++) {
            if ($flag1[$i] !== 1) {
                WagesDatabaseCollection::insert([
                    'WagesCollection_Job_Comments' => $data[$i]['WagesCollection_Job_Comments'] ?? '',
                    'WagesCollection_Vehicle' => $data[$i]['WagesCollection_Vehicle'] ?? '',
                    'WagesCollection_Wage_Status' => $data[$i]['WagesCollection_Wage_Status'] ?? '',
                    'WagesCollection_Wage_budget' => $data[$i]['WagesCollection_Wage_budget'] ?? '',
                    'WagesCollection_Job_Percentage' => $data[$i]['WagesCollection_Job_Percentage'] ?? '',
                    'WagesCollection_Job_Price' => $data[$i]['WagesCollection_Job_Price'] ?? '',
                    'WagesCollection_Job_ShiftType' => $data[$i]['WagesCollection_Job_ShiftType'] ?? '',
                    'WagesCollection_Job_Duration' => $data[$i]['WagesCollection_Job_Duration'] ?? '',
                    'WagesCollection_Job_Execution_Date' => $data[$i]['WagesCollection_Job_Execution_Date'] ?? '',
                    'WagesCollection_Technicians' => $data[$i]['WagesCollection_Technicians'] ?? '',
                    'WagesCollection_Job_ID' => $data[$i]['WagesCollection_Job_ID'] ?? '',
                    'WagesCollection_TechnicianViewer_Wage' => $data[$i]['WagesCollection_TechnicianViewer_Wage'] ?? '',
                    'WagesCollection_Job_Type' => $data[$i]['WagesCollection_Job_Type'] ?? '',
                    'WagesCollection_TechnicianViewer' => $data[$i]['WagesCollection_TechnicianViewer'] ?? '',
                    'WagesCollection_UserCreator' => $data[$i]['WagesCollection_UserCreator'] ?? '',
                ]);
            }
        }
        $result = WagesDatabaseCollection::whereRaw(['$where' => 'this.WagesCollection_UserCreator == this.WagesCollection_TechnicianViewer'])
            ->where('WagesCollection_Job_ID', $data[0]['WagesCollection_Job_ID'] ?? '')
            ->where('WagesCollection_Job_Type', 'LIKE', '%Adjustment%')->get();

        $adjust = 'Adjustment ' . strval(count($result) + 1);
        for ($i = 0; $i < count($data); $i++) {
            if (($data[$i]['WagesCollection_Wage_Status'] ?? '') == 'Paid') {
                $result = WagesDatabaseCollection::where('WagesCollection_Job_ID', $data[$i]['WagesCollection_Job_ID'])
                    ->where('WagesCollection_Technicians', $data[$i]['WagesCollection_Technicians'])
                    ->where('WagesCollection_TechnicianViewer', $data[$i]['WagesCollection_TechnicianViewer'])
                    ->where('WagesCollection_UserCreator', $data[$i]['WagesCollection_UserCreator'])
                    ->where('WagesCollection_Job_Execution_Date', $data[$i]['WagesCollection_Job_Execution_Date'])
                    ->get();
                $wage = floatval($data[$i]['WagesCollection_TechnicianViewer_Wage']);
                for ($j = 0; $j < count($result); $j++) {
                    $wage = $wage - floatval($result[$j]->WagesCollection_TechnicianViewer_Wage);
                }
                $wage = round($wage, 2);
                WagesDatabaseCollection::insert([
                    'WagesCollection_Job_Comments' => $data[$i]['WagesCollection_Job_Comments'] ?? '',
                    'WagesCollection_Vehicle' => $data[$i]['WagesCollection_Vehicle'] ?? '',
                    'WagesCollection_Wage_Status' => '',
                    'WagesCollection_Wage_budget' => $data[$i]['WagesCollection_Wage_budget'] ?? '',
                    'WagesCollection_Job_Percentage' => $data[$i]['WagesCollection_Job_Percentage'] ?? '',
                    'WagesCollection_Job_Price' => $data[$i]['WagesCollection_Job_Price'] ?? '',
                    'WagesCollection_Job_ShiftType' => $data[$i]['WagesCollection_Job_ShiftType'] ?? '',
                    'WagesCollection_Job_Duration' => $data[$i]['WagesCollection_Job_Duration'] ?? '',
                    'WagesCollection_Job_Execution_Date' => $data[$i]['WagesCollection_Job_Execution_Date'] ?? '',
                    'WagesCollection_Technicians' => $data[$i]['WagesCollection_Technicians'] ?? '',
                    'WagesCollection_Job_ID' => $data[$i]['WagesCollection_Job_ID'] ?? '',
                    'WagesCollection_TechnicianViewer_Wage' => $wage,
                    'WagesCollection_Job_Type' => $adjust,
                    'WagesCollection_TechnicianViewer' => $data[$i]['WagesCollection_TechnicianViewer'] ?? '',
                    'WagesCollection_UserCreator' => $data[$i]['WagesCollection_UserCreator'] ?? '',
                ]);
            }
        }

        return response('ok', 200);
    }

    public function updateRecord(Request $request)
    {
        if ($r = $this->requireAuth($request)) {
            return $r;
        }
        $index = $request->input('index');
        if (!WagesDatabaseCollection::where('_id', $index)->exists()) {
            return response('fail', 200);
        }
        $record = explode('!!!', (string) $request->input('record', ''));
        $value = explode('!!!', (string) $request->input('value', ''));
        for ($i = 0; $i < count($record); $i++) {
            $key = $record[$i];
            if ($key === '') {
                continue;
            }
            WagesDatabaseCollection::where('_id', $index)->update([$key => strval($value[$i] ?? '')]);
        }

        return response('ok', 200);
    }

    public function deleteJob(Request $request, string $id)
    {
        if ($r = $this->requireAuth($request)) {
            return $r;
        }
        if (WagesDatabaseCollection::where('_id', $id)->exists()) {
            WagesDatabaseCollection::where('_id', $id)->delete();

            return response('ok', 200);
        }

        return response('fail', 200);
    }

    public function export(Request $request)
    {
        if ($r = $this->requireAuth($request)) {
            return $r;
        }
        $user = $this->jwtUser($request);
        if (!$user || ($user['type'] ?? '') !== 'Admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = WagesDatabaseCollection::select([
            'WagesCollection_Job_ID',
            'WagesCollection_Job_Type',
            'WagesCollection_Job_Execution_Date',
            'WagesCollection_Vehicle',
            'WagesCollection_Job_Duration',
            'WagesCollection_Job_ShiftType',
            'WagesCollection_UserCreator',
            'WagesCollection_TechnicianViewer',
            'WagesCollection_Technicians',
            'WagesCollection_Job_Price',
            'WagesCollection_Job_Percentage',
            'WagesCollection_Wage_budget',
            'WagesCollection_TechnicianViewer_Wage',
            'WagesCollection_Wage_Status',
            'WagesCollection_Job_Comments',
        ])->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Execution Date',
                'Job Number',
                'Type',
                'Tech Name',
                'Execution Time(Min)',
                'Job Price',
                'Rate',
                'Wage',
                'Status',
                'Comments',
                'Vehicle',
            ]);
            foreach ($rows as $result) {
                fputcsv($out, [
                    $result->WagesCollection_Job_Execution_Date ?? '',
                    $result->WagesCollection_Job_ID ?? '',
                    $result->WagesCollection_Job_Type ?? '',
                    $result->WagesCollection_TechnicianViewer ?? '',
                    $result->WagesCollection_Job_Duration ?? '',
                    $result->WagesCollection_Job_Price ?? '',
                    $result->WagesCollection_Job_Percentage ?? '',
                    $result->WagesCollection_TechnicianViewer_Wage ?? '',
                    $result->WagesCollection_Wage_Status ?? '',
                    $result->WagesCollection_Job_Comments ?? '',
                    $result->WagesCollection_Vehicle ?? '',
                ]);
            }
            fclose($out);
        }, 'WagesDatabaseCollection.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

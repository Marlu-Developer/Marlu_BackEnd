<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

use App\Models\EmployeesDatabaseCollection;
use App\Models\EmployeeSchedulesDatabaseCollection;

class EmployeesController extends Controller
{
    // Get employees
    public function getEmployees(Request $request) {
        $token = $request->header('Authorization');
        if (!$token) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $validToken = $this->validateToken($token);
        if (!$token || $validToken->getStatusCode() != 200) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $searchCondition = [
            'Employee_ID',
            'Employee_Full_Name',
            'Employee_User_Type',
            'Employee_User_SubType',
            'Employee_Position',
            'Employee_User_Login',
            'Empolyee_Tags',
        ];
        $search = (isset($request['search'])) ? $request['search'] : '';

        if ($search !== '') {
            $activeListData = EmployeesDatabaseCollection::where('Employee_User_Status', 'active')
                    ->where(function ($query) use ($searchCondition, $search) {
                        foreach ($searchCondition as $condition) {
                            $query->orWhere($condition, 'like', '%' . $search . '%');
                        }
                    })
                    ->orderBy("Employee_ID", "ASC")
                    ->get();
            $inactiveListData = EmployeesDatabaseCollection::where('Employee_User_Status', 'inactive')
                    ->where(function ($query) use ($searchCondition, $search) {
                        foreach ($searchCondition as $condition) {
                            $query->orWhere($condition, 'like', '%' . $search . '%');
                        }
                    })
                    ->orderBy("Employee_ID", "ASC")
                    ->get();
        } else {
            $activeListData = EmployeesDatabaseCollection::where('Employee_User_Status', 'active')
                    ->orderBy("Employee_ID", "ASC")
                    ->get();
            $inactiveListData = EmployeesDatabaseCollection::where('Employee_User_Status', 'inactive')
                    ->orderBy("Employee_ID", "ASC")
                    ->get();
        }

        return response()->json([
            'activeListData' => $activeListData,
            'inactiveListData' => $inactiveListData,
        ]);
    }

    /**
     * PDF upload for employee documents (legacy: POST /api/uploadPdf).
     */
    public function uploadPdf(Request $request)
    {
        $token = $request->header('Authorization');
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validToken = $this->validateToken($token);
        if ($validToken->getStatusCode() != 200) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $name = $request->input('name');
        $content = $request->input('content');
        if (!$name || $content === null) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        $dir = public_path('upload/pdf');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . basename($name);
        File::put($path, base64_decode($content, true) ?: '');

        return response()->json(['ok' => true, 'name' => basename($name)]);
    }

    /**
     * Create employee (legacy: POST /api/create_employees).
     */
    public function createEmployee(Request $request)
    {
        $token = $request->header('Authorization');
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validToken = $this->validateToken($token);
        if ($validToken->getStatusCode() != 200) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->all();

        if (!isset($data['id'])) {
            return response()->json(['message' => 'Missing employee id'], 422);
        }

        $employeeId = (int) $data['id'];
        if (EmployeesDatabaseCollection::where('Employee_ID', $employeeId)->exists()) {
            return response()->json(['message' => 'Employee ID already in use'], 409);
        }

        $pic = $data['pic'] ?? '';
        $picFilename = '';
        if (is_string($pic) && $pic !== '' && str_contains($pic, 'base64')) {
            $parts = explode(',', $pic, 2);
            if (count($parts) === 2) {
                $binary = base64_decode($parts[1], true);
                if ($binary !== false) {
                    $imgDir = public_path('upload/img');
                    if (!File::isDirectory($imgDir)) {
                        File::makeDirectory($imgDir, 0755, true);
                    }
                    $picFilename = time() . '.png';
                    File::put($imgDir . DIRECTORY_SEPARATOR . $picFilename, $binary);
                }
            }
        }

        EmployeesDatabaseCollection::insert([
            'Employee_ID' => $employeeId,
            'Employee_Photo' => $picFilename,
            'Employee_Gender' => $data['gender'] ?? '',
            'Employee_Full_Name' => $data['alias'] ?? '',
            'Employee_Alias' => $data['full_name'] ?? '',
            'Employee_Phone_Number' => $data['phone'] ?? '',
            'Employee_Email' => $data['email'] ?? '',
            'Employee_CalendarID' => $data['calendarId'] ?? '',
            'Employee_Birthdate' => $data['birthdate'] ?? '',
            'Employee_Address' => $data['address'] ?? '',
            'Employee_Country' => $data['country'] ?? '',
            'Employee_Province' => $data['province'] ?? '',
            'Employee_City' => $data['city'] ?? '',
            'Employee_PostalCode' => $data['postalCode'] ?? '',
            'Employee_Driving_license' => $data['driving_license'] ?? '',
            'Employee_English' => $data['english'] ?? '',
            'Employee_Marble_Floors' => $data['marble_floors'] ?? '',
            'Employee_Marble_Tables' => $data['marble_tables'] ?? '',
            'Employee_Showers' => $data['showers'] ?? '',
            'Employee_Grout_Floors' => $data['grout_floors'] ?? '',
            'Empolyee_Comments_Section' => $data['comments'] ?? '',
            'Employee_Call_Agent_ID' => $data['agent_id'] ?? '',
            'Employee_Call_Agent_Phone' => $data['agent_phone'] ?? '',
            'Employee_User_Login' => $data['user_login'] ?? '',
            'Employee_User_Type' => $data['user_type'] ?? '',
            'Employee_User_SubType' => $data['user_subtype'] ?? '',
            'Employee_User_Status' => $data['user_status'] ?? 'active',
            'Employee_Password' => md5($data['password'] ?? ''),
            'Employee_Position' => $data['position'] ?? '',
            'Employee_Start_Date' => $data['starting_date'] ?? '',
            'Employee_Ending_Date' => $data['ending_date'] ?? '',
            'Empolyee_Wage_Type' => $data['wage_type'] ?? '',
            'Empolyee_Hourly_Wage' => $data['per_hour'] ?? '',
            'Empolyee_Monthly_Wage' => $data['per_month'] ?? '',
            'Empolyee_Vacation_Days' => $data['annual_vacation_days'] ?? '',
            'Empolyee_Vacation_Days_Used' => $data['used_vacation_days'] ?? '',
            'Empolyee_Documents' => $data['files-names'] ?? '',
            'Empolyee_Tags' => $data['tags'] ?? '',
            'Empolyee_Factor_Job' => $data['factor_per_job'] ?? '',
        ]);

        $schedule = $data['schedule'] ?? [];
        if (is_array($schedule)) {
            EmployeeSchedulesDatabaseCollection::updateOrCreate(
                ['Employee_ID' => $employeeId],
                [
                    'Employee_ID' => $employeeId,
                    'monday_enabled' => !empty($schedule['monday_enabled']),
                    'monday_start' => $schedule['monday_start'] ?? null,
                    'monday_end' => $schedule['monday_end'] ?? null,
                    'tuesday_enabled' => !empty($schedule['tuesday_enabled']),
                    'tuesday_start' => $schedule['tuesday_start'] ?? null,
                    'tuesday_end' => $schedule['tuesday_end'] ?? null,
                    'wednesday_enabled' => !empty($schedule['wednesday_enabled']),
                    'wednesday_start' => $schedule['wednesday_start'] ?? null,
                    'wednesday_end' => $schedule['wednesday_end'] ?? null,
                    'thursday_enabled' => !empty($schedule['thursday_enabled']),
                    'thursday_start' => $schedule['thursday_start'] ?? null,
                    'thursday_end' => $schedule['thursday_end'] ?? null,
                    'friday_enabled' => !empty($schedule['friday_enabled']),
                    'friday_start' => $schedule['friday_start'] ?? null,
                    'friday_end' => $schedule['friday_end'] ?? null,
                    'saturday_enabled' => !empty($schedule['saturday_enabled']),
                    'saturday_start' => $schedule['saturday_start'] ?? null,
                    'saturday_end' => $schedule['saturday_end'] ?? null,
                    'sunday_enabled' => !empty($schedule['sunday_enabled']),
                    'sunday_start' => $schedule['sunday_start'] ?? null,
                    'sunday_end' => $schedule['sunday_end'] ?? null,
                ]
            );
        }

        return response('ok', 200)->header('Content-Type', 'text/plain');
    }
}

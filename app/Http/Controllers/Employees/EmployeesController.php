<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\EmployeesDatabaseCollection;

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
}

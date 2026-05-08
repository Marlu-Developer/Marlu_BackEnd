<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeesDatabaseCollection;
use App\Models\UsersDatabaseCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Admin section (parity with marluapp/admin/*): users CRUD + analytics login.
 * Restricted to roles: Admin (Office/Owner) by route middleware.
 */
class AdminController extends Controller
{
    public function listUsers(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 50), 200));
        $q = EmployeesDatabaseCollection::query();
        if ($request->filled('search')) {
            $term = (string) $request->query('search');
            $q->where(function ($qq) use ($term) {
                $qq->orWhere('Employee_Full_Name', 'like', "%{$term}%")
                    ->orWhere('Employee_User_Login', 'like', "%{$term}%")
                    ->orWhere('Employee_Email', 'like', "%{$term}%");
            });
        }
        return response()->json($q->orderBy('Employee_Full_Name')->paginate($perPage));
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'employee_id' => ['required', 'integer'],
            'new_password' => ['required', 'string', 'min:8', 'max:120'],
        ]);
        $emp = EmployeesDatabaseCollection::where('Employee_ID', (int) $payload['employee_id'])->first();
        if (!$emp) {
            return response()->json(['message' => 'Employee not found', 'code' => 'not_found'], 404);
        }
        $emp->Employee_Password_Hash = Hash::make($payload['new_password']);
        $emp->Employee_Password = null;
        $emp->save();
        return response()->json(['data' => ['ok' => true]]);
    }

    public function loginAnalytics(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 100), 500));
        return response()->json(
            UsersDatabaseCollection::orderBy('Login_At', 'desc')->paginate($perPage)
        );
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeesDatabaseCollection;
use Session;

class LoginController extends Controller
{
    public function Me(Request $request) {
        
    }

    /**
     * Login the user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function SignIn(Request $request) {
        $username = $request->username;
        $password = $request->password;
        $user = EmployeesDatabaseCollection::where('Employee_User_Login', $username)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        if ($user->Employee_Password !== md5($password)) {
            return response()->json(['success' => false, 'message' => 'Invalid username or password'], 401);
        }
        
        return response()->json([
            'success' => true,
            // add more fields if your frontend expects them
            'id' => $user->Employee_ID,
            '_id' => $user->_id,
            'name' => $user->Employee_Full_Name,
            'email' => $user->Employee_Email,
            'phone' => $user->Employee_Phone_Number,
            'image' => $user->Employee_Photo,
            'type' => $user->Employee_User_Type,
            'subType' => $user->Employee_User_SubType,
        ]);
    }
}

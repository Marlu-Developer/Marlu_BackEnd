<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Authorize the current JWT user against a list of accepted roles.
 * Usage: Route::middleware(['jwt', 'role:Admin,Office']).
 */
class RoleAuthorize
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = JWTAuth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized', 'code' => 'unauthorized'], 401);
        }

        $userRole = method_exists($user, 'getRoleName') ? $user->getRoleName() : (string) ($user->Employee_User_Type ?? '');
        if (!empty($roles) && !in_array($userRole, $roles, true)) {
            return response()->json([
                'message' => 'Forbidden',
                'code' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}

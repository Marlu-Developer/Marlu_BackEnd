<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Verify a JWT bearer token and resolve the auth user.
 * Returns 401 with a typed JSON error on any failure.
 */
class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = JWTAuth::parseToken();
            $user = $token->authenticate();
            if (!$user) {
                return $this->fail('user_not_found', 'User not found', 401);
            }
        } catch (TokenExpiredException $e) {
            return $this->fail('token_expired', 'Token expired', 401);
        } catch (TokenInvalidException $e) {
            return $this->fail('token_invalid', 'Token invalid', 401);
        } catch (JWTException $e) {
            return $this->fail('token_missing', 'Authorization token missing', 401);
        }

        return $next($request);
    }

    private function fail(string $code, string $message, int $status)
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
        ], $status);
    }
}

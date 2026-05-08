<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignInRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(private AuthService $auth)
    {
    }

    public function signIn(SignInRequest $request): JsonResponse
    {
        $payload = $this->auth->login(
            (string) $request->validated('username'),
            (string) $request->validated('password')
        );

        if (!$payload) {
            return response()->json([
                'message' => 'Invalid username or password',
                'code' => 'invalid_credentials',
            ], 401);
        }

        return response()->json(['data' => $payload]);
    }

    public function me(): JsonResponse
    {
        $user = $this->auth->me();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated', 'code' => 'unauthenticated'], 401);
        }
        return response()->json(['data' => $user]);
    }

    public function refresh(): JsonResponse
    {
        $payload = $this->auth->refresh();
        return response()->json(['data' => $payload]);
    }

    public function logout(): JsonResponse
    {
        $this->auth->logout();
        return response()->json(['data' => ['ok' => true]]);
    }
}

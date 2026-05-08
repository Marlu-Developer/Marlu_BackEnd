<?php

namespace App\Http\Controllers;

use App\Models\AuthUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Resolve the currently authenticated AuthUser (Mongo employee record).
     * Returns null if no valid token is present (rare under jwt middleware).
     */
    protected function authUser(): ?AuthUser
    {
        $user = JWTAuth::user();
        return $user instanceof AuthUser ? $user : null;
    }

    /**
     * Resolve the JWT custom claims for the current request.
     */
    protected function authClaims(): array
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            return $payload->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}

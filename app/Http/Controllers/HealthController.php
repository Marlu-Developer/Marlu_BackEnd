<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $db = 'unknown';
        try {
            DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
            $db = 'ok';
        } catch (\Throwable $e) {
            $db = 'down';
        }

        return response()->json([
            'data' => [
                'app' => config('app.name'),
                'env' => config('app.env'),
                'time' => now()->toIso8601String(),
                'db' => $db,
                'version' => 'v1',
            ],
        ]);
    }
}

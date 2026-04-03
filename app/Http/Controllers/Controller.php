<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    // Validate token
    public function validateToken($token) {
        $tmp = explode('.', $token);

        if (count($tmp) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token!',
            ], 401);
        }

        $header = $tmp[0];
        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token!',
            ], 401);
        }

        $signature = $tmp[2];
        if (!$signature || $signature !== 'dummy-signature') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token!',
            ], 401);
        }

        $base64Url = $tmp[1];
        $base64 = str_replace('-', '+', str_replace('_', '/', $base64Url));
        $decoded = json_decode(base64_decode($base64), true);

        if (!$decoded || !isset($decoded['exp'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token!',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Valid token!',
        ], 200);
    }
}

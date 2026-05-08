<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight per-request structured log entry. Includes request id,
 * method, path, status, duration, user (if authenticated), and a
 * conservative subset of headers. Hooks into the existing log channel
 * (override per env: stack -> json driver in production).
 */
class ObservabilityLogger
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        try {
            $user = $request->user();
            Log::info('http.request', [
                'request_id' => $request->attributes->get('request_id'),
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => method_exists($response, 'status') ? $response->status() : null,
                'duration_ms' => $durationMs,
                'user_id' => $user?->getAuthIdentifier(),
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // never let logging break a request
        }

        return $response;
    }
}

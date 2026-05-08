<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Attach a stable request id to every request and propagate it to the
 * response so logs and frontend traces can be correlated.
 */
class RequestId
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->header('X-Request-Id');
        if (!$id || strlen($id) > 64) {
            $id = (string) Str::uuid();
        }
        $request->headers->set('X-Request-Id', $id);
        $request->attributes->set('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);
        return $response;
    }
}

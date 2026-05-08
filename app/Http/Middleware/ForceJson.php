<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ensure all API requests are treated as JSON so error responses
 * (validation, auth, exceptions) are always returned as JSON, not HTML.
 */
class ForceJson
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}

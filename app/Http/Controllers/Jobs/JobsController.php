<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Services\Jobs\JobsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jobs dashboard + layout config (parity with marluapp/job/JobDashboard).
 */
class JobsController extends Controller
{
    public function __construct(private JobsService $jobs)
    {
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->jobs->dashboard($request));
    }

    public function layout(): JsonResponse
    {
        return response()->json(['data' => $this->jobs->layout()]);
    }

    public function updateLayout(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'columns' => ['required', 'array'],
        ]);
        return response()->json(['data' => $this->jobs->saveLayout($payload['columns'])]);
    }
}

<?php

namespace App\Http\Controllers\Others;

use App\Http\Controllers\Controller;
use App\Services\Others\OthersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Others" section (parity with marluapp/others/*):
 *   - webhooks status
 *   - apis status
 *   - company profile
 *   - terms & conditions
 *   - cron jobs status
 *   - database details
 *   - JustCall proxy (calls + recording metadata)
 */
class OthersController extends Controller
{
    public function __construct(private OthersService $others)
    {
    }

    public function webhooks(): JsonResponse
    {
        return response()->json(['data' => $this->others->webhooks()]);
    }

    public function apis(): JsonResponse
    {
        return response()->json(['data' => $this->others->apis()]);
    }

    public function companyProfile(): JsonResponse
    {
        return response()->json(['data' => $this->others->companyProfile()]);
    }

    public function updateCompanyProfile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->others->updateCompanyProfile($request->all())]);
    }

    public function cronJobs(): JsonResponse
    {
        return response()->json(['data' => $this->others->cronJobs()]);
    }

    public function databaseDetails(): JsonResponse
    {
        return response()->json(['data' => $this->others->databaseDetails()]);
    }

    /**
     * JustCall proxy. Secret is read from env (never sent from FE).
     */
    public function justCallProxy(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'agent_id' => ['nullable', 'string', 'max:60'],
        ]);
        return response()->json(['data' => $this->others->justCall($payload)]);
    }
}

<?php

namespace App\Http\Controllers\Kpis;

use App\Http\Controllers\Controller;
use App\Services\Kpis\KpisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KPI dashboards (parity with marluapp:
 *  - kpis/setters       (legacy SettersDashboard)
 *  - kpis/closers       (legacy ClosersDashboard)
 *  - kpis/users-activity (legacy UsersActivityDashboard, uses Vue 3 CDN)
 * ).
 *
 * The legacy Vue 3 CDN approach is replaced by MUI grids on the FE.
 * Aggregations move from blade-injected PHP to `KpisService` so they
 * can be cached in Redis and indexed via `IndexSeeder`.
 */
class KpisController extends Controller
{
    public function __construct(private KpisService $kpis)
    {
    }

    public function setters(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->kpis->setters($request)]);
    }

    public function closers(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->kpis->closers($request)]);
    }

    public function usersActivity(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->kpis->usersActivity($request)]);
    }
}

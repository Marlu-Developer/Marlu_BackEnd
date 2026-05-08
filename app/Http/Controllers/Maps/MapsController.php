<?php

namespace App\Http\Controllers\Maps;

use App\Http\Controllers\Controller;
use App\Services\Maps\MapsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Maps endpoints (parity with marluapp/maps/MapsDashboard).
 *  - GET /maps/os-estimates  -> JSON of estimates by week / by flexibility
 */
class MapsController extends Controller
{
    public function __construct(private MapsService $maps)
    {
    }

    public function osEstimates(Request $request): JsonResponse
    {
        $mode = (string) $request->query('mode', 'by_week');
        return response()->json(['data' => $this->maps->osEstimates($mode, $request)]);
    }
}

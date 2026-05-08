<?php

namespace App\Http\Controllers\Schedules;

use App\Http\Controllers\Controller;
use App\Services\Schedules\SchedulesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Schedules dashboards (parity with marluapp:
 *  - schedules/by-technician
 *  - schedules/by-closer
 *  - schedules/all-technicians
 *  - schedules/all-closers
 *  - schedules/modifications
 * ).
 */
class SchedulesController extends Controller
{
    public function __construct(private SchedulesService $schedules)
    {
    }

    public function byTechnician(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->schedules->byTechnician($request)]);
    }

    public function byCloser(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->schedules->byCloser($request)]);
    }

    public function allTechnicians(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->schedules->allTechnicians($request)]);
    }

    public function allClosers(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->schedules->allClosers($request)]);
    }

    public function modifications(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->schedules->modifications($request)]);
    }
}

<?php

namespace App\Services\Schedules;

use App\Repositories\Schedules\SchedulesRepository;
use Illuminate\Http\Request;

/**
 * Aggregation logic for the 5 legacy schedule dashboards.
 * Parity with marluapp/app/Http/Controllers/schedules/*.
 *
 * NOTE: each method returns a normalized array shape so the FE hook
 * can consume one type per dashboard. Implementations will mirror
 * marluapp business rules, replacing per-row find()->save() loops with
 * `$this->repo->newQuery()` + `bulkWrite` for any modifications.
 */
class SchedulesService
{
    public function __construct(private SchedulesRepository $repo)
    {
    }

    public function byTechnician(Request $request): array
    {
        $date = (string) $request->query('date', date('Y-m-d'));
        $technicianId = $request->query('technician_id');
        return [
            'date' => $date,
            'technicianId' => $technicianId,
            'segments' => $this->repo->segmentsForTechnician($date, $technicianId),
        ];
    }

    public function byCloser(Request $request): array
    {
        $date = (string) $request->query('date', date('Y-m-d'));
        $closerId = $request->query('closer_id');
        return [
            'date' => $date,
            'closerId' => $closerId,
            'estimates' => $this->repo->estimatesForCloser($date, $closerId),
        ];
    }

    public function allTechnicians(Request $request): array
    {
        $date = (string) $request->query('date', date('Y-m-d'));
        return [
            'date' => $date,
            'technicians' => $this->repo->allTechnicians($date),
        ];
    }

    public function allClosers(Request $request): array
    {
        $date = (string) $request->query('date', date('Y-m-d'));
        return [
            'date' => $date,
            'closers' => $this->repo->allClosers($date),
        ];
    }

    public function modifications(Request $request): array
    {
        return [
            'modifications' => $this->repo->modifications(
                (string) $request->query('start_date', date('Y-m-d', strtotime('-7 days'))),
                (string) $request->query('end_date', date('Y-m-d')),
            ),
        ];
    }
}

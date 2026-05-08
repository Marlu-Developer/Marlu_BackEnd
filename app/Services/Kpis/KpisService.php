<?php

namespace App\Services\Kpis;

use App\Repositories\Kpis\KpisRepository;
use Illuminate\Http\Request;

class KpisService
{
    public function __construct(private KpisRepository $repo)
    {
    }

    public function setters(Request $request): array
    {
        return [
            'startDate' => (string) $request->query('start_date', date('Y-m-d', strtotime('-30 days'))),
            'endDate' => (string) $request->query('end_date', date('Y-m-d')),
            'metrics' => $this->repo->setterMetrics(
                (string) $request->query('start_date', date('Y-m-d', strtotime('-30 days'))),
                (string) $request->query('end_date', date('Y-m-d'))
            ),
        ];
    }

    public function closers(Request $request): array
    {
        return [
            'startDate' => (string) $request->query('start_date', date('Y-m-d', strtotime('-30 days'))),
            'endDate' => (string) $request->query('end_date', date('Y-m-d')),
            'metrics' => $this->repo->closerMetrics(
                (string) $request->query('start_date', date('Y-m-d', strtotime('-30 days'))),
                (string) $request->query('end_date', date('Y-m-d'))
            ),
        ];
    }

    public function usersActivity(Request $request): array
    {
        return [
            'sessions' => $this->repo->userSessions(
                (string) $request->query('start_date', date('Y-m-d', strtotime('-7 days'))),
                (string) $request->query('end_date', date('Y-m-d'))
            ),
        ];
    }
}

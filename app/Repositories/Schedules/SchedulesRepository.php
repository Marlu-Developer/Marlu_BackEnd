<?php

namespace App\Repositories\Schedules;

use App\Models\DailyScheduleDatabaseCollection;
use App\Models\EmployeeSchedulesDatabaseCollection;
use App\Models\EventsDatabaseCollection;
use App\Models\JobsDatabaseCollection;
use App\Repositories\BaseMongoRepository;
use Illuminate\Support\Collection;

/**
 * Data access for schedules dashboards. Replace stubbed return values
 * with field-projected queries indexed via `IndexSeeder` (idx_jobs_date,
 * idx_estimate_zulu, etc.) when porting each legacy controller.
 */
class SchedulesRepository extends BaseMongoRepository
{
    protected function modelClass(): string
    {
        return DailyScheduleDatabaseCollection::class;
    }

    public function segmentsForTechnician(string $date, mixed $technicianId): Collection
    {
        $q = DailyScheduleDatabaseCollection::query()->where('Schedule_Date', $date);
        if ($technicianId !== null && $technicianId !== '') {
            $q->where('Technician_ID', (int) $technicianId);
        }
        return $q->get();
    }

    public function estimatesForCloser(string $date, mixed $closerId): Collection
    {
        $q = JobsDatabaseCollection::query()
            ->whereDate('JobCollection_Estimate_Scheduling_Start_TimeZulu', $date);
        if ($closerId !== null && $closerId !== '') {
            $q->where('JobCollection_Job_Closer_Full_Name', (string) $closerId);
        }
        return $q->select([
            '_id',
            'JobCollection_Customer_Full_Name',
            'JobCollection_Estimate_Scheduling_Start_TimeZulu',
            'JobCollection_Job_Closer_Full_Name',
            'Customer_City',
            'Customer_Postal_Code',
        ])->get();
    }

    public function allTechnicians(string $date): Collection
    {
        return DailyScheduleDatabaseCollection::query()
            ->where('Schedule_Date', $date)
            ->orderBy('Technician_Full_Name')
            ->get();
    }

    public function allClosers(string $date): Collection
    {
        return JobsDatabaseCollection::query()
            ->whereDate('JobCollection_Estimate_Scheduling_Start_TimeZulu', $date)
            ->select([
                '_id',
                'JobCollection_Customer_Full_Name',
                'JobCollection_Estimate_Scheduling_Start_TimeZulu',
                'JobCollection_Job_Closer_Full_Name',
                'Customer_City',
            ])
            ->orderBy('JobCollection_Estimate_Scheduling_Start_TimeZulu')
            ->get();
    }

    public function modifications(string $startDate, string $endDate): Collection
    {
        return EventsDatabaseCollection::query()
            ->whereBetween('Event_Date', [$startDate, $endDate])
            ->orderBy('Event_Date', 'desc')
            ->get();
    }
}

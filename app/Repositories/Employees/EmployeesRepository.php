<?php

namespace App\Repositories\Employees;

use App\Models\EmployeesDatabaseCollection;
use App\Models\EmployeeSchedulesDatabaseCollection;
use Illuminate\Database\Eloquent\Collection;
use MongoDB\BSON\ObjectId;

class EmployeesRepository
{
    private const SEARCH_FIELDS = [
        'Employee_ID',
        'Employee_Full_Name',
        'Employee_User_Type',
        'Employee_User_SubType',
        'Employee_Position',
        'Employee_User_Login',
        'Empolyee_Tags',
    ];

    private const SCHEDULE_DAY_FIELDS = [
        'monday_enabled',
        'tuesday_enabled',
        'wednesday_enabled',
        'thursday_enabled',
        'friday_enabled',
        'saturday_enabled',
        'sunday_enabled',
    ];

    /**
     * Listing rows include the legacy-compatible `Employee_Schedule_Active` flag,
     * resolved in a single batched query to avoid the legacy N+1 lookup.
     */
    public function listByStatus(string $status, ?string $search = null): Collection
    {
        $query = EmployeesDatabaseCollection::where('Employee_User_Status', $status);

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                foreach (self::SEARCH_FIELDS as $field) {
                    $q->orWhere($field, 'like', '%' . $search . '%');
                }
            });
        }

        $rows = $query->orderBy('Employee_ID', 'ASC')->get();

        $this->attachScheduleActive($rows);

        return $rows;
    }

    /**
     * Hydrate each listing row with `Employee_Schedule_Active` (true if any day is enabled).
     * One Mongo round-trip with `whereIn`, no per-row queries.
     */
    private function attachScheduleActive(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $ids = $rows->pluck('Employee_ID')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            $rows->each(function ($row) {
                $row->Employee_Schedule_Active = false;
            });
            return;
        }

        $schedules = EmployeeSchedulesDatabaseCollection::whereIn('Employee_ID', $ids)
            ->select(array_merge(['Employee_ID'], self::SCHEDULE_DAY_FIELDS))
            ->get()
            ->keyBy(fn ($s) => (string) $s->Employee_ID);

        foreach ($rows as $row) {
            $key = (string) ($row->Employee_ID ?? '');
            $schedule = $schedules->get($key);
            $row->Employee_Schedule_Active = $schedule
                ? (bool) (
                    $schedule->monday_enabled
                    || $schedule->tuesday_enabled
                    || $schedule->wednesday_enabled
                    || $schedule->thursday_enabled
                    || $schedule->friday_enabled
                    || $schedule->saturday_enabled
                    || $schedule->sunday_enabled
                )
                : false;
        }
    }

    public function findByEmployeeId(int $employeeId): ?EmployeesDatabaseCollection
    {
        return EmployeesDatabaseCollection::where('Employee_ID', $employeeId)->first();
    }

    public function existsByEmployeeId(int $employeeId): bool
    {
        return EmployeesDatabaseCollection::where('Employee_ID', $employeeId)->exists();
    }

    public function activeOrdered(): Collection
    {
        return EmployeesDatabaseCollection::where('Employee_User_Status', 'active')
            ->orderBy('Employee_User_Type')
            ->orderBy('Employee_User_SubType')
            ->orderBy('Employee_Full_Name')
            ->get();
    }

    public function insertOne(array $attributes): void
    {
        EmployeesDatabaseCollection::insert($attributes);
    }

    public function updateField(int $employeeId, string $key, mixed $value): bool
    {
        return EmployeesDatabaseCollection::where('Employee_ID', $employeeId)
            ->update([$key => $value]) > 0;
    }

    /**
     * Persist a partial update for one employee using the underlying query builder
     * so values such as `null` and `false` are written reliably (Mongo Eloquent's
     * dirty tracking does not always fire for dynamic attributes set via array
     * access, hence the direct query update).
     */
    public function updateMany(int $employeeId, array $patch): bool
    {
        if ($patch === []) {
            return false;
        }
        return EmployeesDatabaseCollection::where('Employee_ID', $employeeId)
            ->update($patch) > 0;
    }
}

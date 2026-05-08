<?php

namespace App\Repositories\Kpis;

use App\Models\JobsDatabaseCollection;
use App\Repositories\BaseMongoRepository;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;

class KpisRepository extends BaseMongoRepository
{
    protected function modelClass(): string
    {
        return JobsDatabaseCollection::class;
    }

    public function setterMetrics(string $startDate, string $endDate): array
    {
        $pipeline = [
            ['$match' => [
                'JobCollection_Reception_Date' => ['$gte' => $startDate, '$lte' => $endDate],
                'JobCollection_Job_Setter_Full_Name' => ['$exists' => true, '$ne' => null],
            ]],
            ['$group' => [
                '_id' => '$JobCollection_Job_Setter_Full_Name',
                'totalRegisters' => ['$sum' => 1],
                'totalEstimates' => ['$sum' => ['$cond' => [['$ifNull' => ['$JobCollection_Estimate_Scheduling_Start_TimeZulu', false]], 1, 0]]],
                'totalSold' => ['$sum' => ['$cond' => [['$ifNull' => ['$JobCollection_Sell_Date', false]], 1, 0]]],
            ]],
            ['$sort' => ['totalRegisters' => -1]],
            ['$limit' => 200],
        ];
        $cursor = $this->rawCollection()->aggregate($pipeline);
        return iterator_to_array($cursor, false);
    }

    public function closerMetrics(string $startDate, string $endDate): array
    {
        $pipeline = [
            ['$match' => [
                'JobCollection_Sell_Date' => ['$gte' => $startDate, '$lte' => $endDate],
            ]],
            ['$group' => [
                '_id' => '$JobCollection_Job_Closer_Full_Name',
                'totalSold' => ['$sum' => 1],
                'revenue' => ['$sum' => ['$ifNull' => ['$JobCollection_Estimate_Price', 0]]],
            ]],
            ['$sort' => ['revenue' => -1]],
            ['$limit' => 200],
        ];
        $cursor = $this->rawCollection()->aggregate($pipeline);
        return iterator_to_array($cursor, false);
    }

    public function userSessions(string $startDate, string $endDate): Collection
    {
        // Place-holder: read user activity from the legacy collection if present.
        // Implement when porting marluapp/.../UsersActivityDashboard.php.
        return collect([]);
    }
}

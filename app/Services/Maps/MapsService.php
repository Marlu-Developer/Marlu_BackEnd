<?php

namespace App\Services\Maps;

use App\Models\JobsDatabaseCollection;
use Illuminate\Http\Request;

class MapsService
{
    public function osEstimates(string $mode, Request $request): array
    {
        $startDate = (string) $request->query('start_date', date('Y-m-d', strtotime('-7 days')));
        $endDate = (string) $request->query('end_date', date('Y-m-d', strtotime('+30 days')));

        $items = JobsDatabaseCollection::query()
            ->whereBetween('JobCollection_Estimate_Scheduling_Start_TimeZulu', [$startDate, $endDate])
            ->select([
                '_id',
                'JobCollection_Customer_Full_Name',
                'JobCollection_Estimate_Scheduling_Start_TimeZulu',
                'JobCollection_Job_Closer_Full_Name',
                'Customer_City',
                'Customer_Postal_Code',
                'Customer_Address',
                'Customer_Latitude',
                'Customer_Longitude',
            ])
            ->limit(2000)
            ->get();

        return [
            'mode' => $mode,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'estimates' => $items,
        ];
    }
}

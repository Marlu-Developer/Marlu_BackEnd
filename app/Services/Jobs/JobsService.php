<?php

namespace App\Services\Jobs;

use App\Models\JobDashboardLayoutDatabaseCollection;
use App\Models\JobsDatabaseCollection;
use Illuminate\Http\Request;

class JobsService
{
    public function dashboard(Request $request): mixed
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        return JobsDatabaseCollection::query()
            ->whereNotNull('JobCollection_Sell_Date')
            ->orderBy('JobCollection_Jobs_Date', 'desc')
            ->paginate($perPage);
    }

    public function layout(): mixed
    {
        return JobDashboardLayoutDatabaseCollection::first();
    }

    public function saveLayout(array $columns): mixed
    {
        $layout = JobDashboardLayoutDatabaseCollection::first();
        if (!$layout) {
            return JobDashboardLayoutDatabaseCollection::create(['columns' => $columns]);
        }
        $layout->columns = $columns;
        $layout->save();
        return $layout;
    }
}

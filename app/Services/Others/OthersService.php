<?php

namespace App\Services\Others;

use App\Models\ApisStatusDatabaseCollection;
use App\Models\CompanyProfileDatabaseCollection;
use App\Models\CronJobsStatusDatabaseCollection;
use App\Models\WebhooksStatusDatabaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OthersService
{
    public function webhooks()
    {
        return WebhooksStatusDatabaseCollection::orderBy('Webhook_Name')->get();
    }

    public function apis()
    {
        return ApisStatusDatabaseCollection::orderBy('Api_Name')->get();
    }

    public function companyProfile()
    {
        return CompanyProfileDatabaseCollection::first();
    }

    public function updateCompanyProfile(array $payload): mixed
    {
        $doc = CompanyProfileDatabaseCollection::first();
        if (!$doc) {
            return CompanyProfileDatabaseCollection::create($payload);
        }
        foreach ($payload as $k => $v) {
            $doc[$k] = $v;
        }
        $doc->save();
        return $doc;
    }

    public function cronJobs()
    {
        return CronJobsStatusDatabaseCollection::orderBy('CronJob_Name')->get();
    }

    public function databaseDetails(): array
    {
        $db = DB::connection('mongodb')->getMongoDB();
        $stats = $db->command(['dbStats' => 1])->toArray();
        return $stats[0] ?? [];
    }

    public function justCall(array $payload): array
    {
        $key = (string) env('JUSTCALL_API_KEY', '');
        $secret = (string) env('JUSTCALL_API_SECRET', '');
        if ($key === '' || $secret === '') {
            return ['ok' => false, 'message' => 'JustCall credentials not configured'];
        }
        // Real call removed for the migration scaffold; route through HTTP client.
        $response = Http::withBasicAuth($key, $secret)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('https://api.justcall.io/v1/calls/list_only', $payload);
        return ['ok' => $response->successful(), 'status' => $response->status()];
    }
}

<?php

namespace App\Repositories\Estimates;

use App\Models\EstimatesDatabaseCollection;
use App\Repositories\BaseMongoRepository;

class EstimatesRepository extends BaseMongoRepository
{
    protected function modelClass(): string
    {
        return EstimatesDatabaseCollection::class;
    }

    public function insert(array $payload): mixed
    {
        return EstimatesDatabaseCollection::create($payload);
    }

    public function updateById(string $id, array $payload): mixed
    {
        $doc = EstimatesDatabaseCollection::where('_id', $id)->first();
        if (!$doc) {
            return null;
        }
        foreach ($payload as $k => $v) {
            $doc[$k] = $v;
        }
        $doc->save();
        return $doc;
    }

    public function deleteById(string $id): bool
    {
        return EstimatesDatabaseCollection::where('_id', $id)->delete() > 0;
    }

    public function logEmail(string $id, array $payload): void
    {
        $doc = EstimatesDatabaseCollection::where('_id', $id)->first();
        if (!$doc) {
            return;
        }
        $log = (array) ($doc->Estimate_Email_Log ?? []);
        $log[] = array_merge($payload, ['sentAt' => now()->toIso8601String()]);
        $doc->Estimate_Email_Log = $log;
        $doc->save();
    }
}

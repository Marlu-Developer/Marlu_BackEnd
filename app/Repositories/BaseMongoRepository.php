<?php

namespace App\Repositories;

use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Eloquent\Model;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

/**
 * Common helpers shared by Mongo repositories.
 * Keeps controllers/services free of raw driver details.
 */
abstract class BaseMongoRepository
{
    abstract protected function modelClass(): string;

    public function newQuery(): Builder
    {
        return ($this->modelClass())::query();
    }

    public function rawCollection(): Collection
    {
        $modelClass = $this->modelClass();
        /** @var Model $instance */
        $instance = new $modelClass();
        return $instance->getConnection()
            ->getMongoDB()
            ->selectCollection($instance->getTable());
    }

    /**
     * Bulk update by ObjectId in a single round-trip.
     */
    public function bulkUpdateByIds(array $ids, array $set): int
    {
        if ($ids === [] || $set === []) {
            return 0;
        }
        $oids = [];
        foreach ($ids as $id) {
            try {
                $oids[] = new ObjectId((string) $id);
            } catch (\Throwable) {
                continue;
            }
        }
        if ($oids === []) {
            return 0;
        }
        $result = $this->rawCollection()->updateMany(
            ['_id' => ['$in' => $oids]],
            ['$set' => $set]
        );
        return (int) $result->getModifiedCount();
    }
}

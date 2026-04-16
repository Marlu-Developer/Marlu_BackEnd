<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates indexes so Sales Dashboard list/export sorts do not exceed MongoDB's in-memory sort limit
 * ("Sort operation used more than the maximum 33554432 bytes of RAM"), especially on high page numbers.
 *
 * Run after deploy: php artisan sales:create-dashboard-indexes
 */
class CreateSalesDashboardMongoIndexes extends Command
{
    protected $signature = 'sales:create-dashboard-indexes';

    protected $description = 'Create MongoDB compound index for Sales Dashboard sort (Customer_Country + reception date + name)';

    public const INDEX_NAME = 'sales_dashboard_list_sort';

    public const COLLECTION = 'jobs_database_collection';

    public function handle(): int
    {
        $connection = DB::connection('mongodb');
        $collection = $connection->getCollection(self::COLLECTION);

        foreach ($collection->listIndexes() as $indexInfo) {
            if ($indexInfo->getName() === self::INDEX_NAME) {
                $this->info('Index "'.self::INDEX_NAME.'" already exists on "'.self::COLLECTION.'".');

                return self::SUCCESS;
            }
        }

        $collection->createIndex(
            [
                'Customer_Country' => 1,
                'JobCollection_Reception_Date' => -1,
                'JobCollection_Customer_Full_Name' => -1,
            ],
            [
                'name' => self::INDEX_NAME,
                'background' => true,
            ]
        );

        $this->info('Created index "'.self::INDEX_NAME.'" on "'.self::COLLECTION.'".');

        return self::SUCCESS;
    }
}

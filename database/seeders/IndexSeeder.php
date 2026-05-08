<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\Exception as MongoException;

/**
 * Create MongoDB indexes used by the API. Run once with:
 *   php artisan db:seed --class=IndexSeeder
 *
 * Indexes are built in the background to avoid locking writes on large
 * collections. Re-running is safe (createIndex is idempotent on identical specs).
 */
class IndexSeeder extends Seeder
{
    public function run(): void
    {
        $this->indexes('employees_database_collection', [
            ['key' => ['Employee_ID' => 1], 'name' => 'idx_employee_id', 'unique' => true],
            ['key' => ['Employee_User_Login' => 1], 'name' => 'idx_employee_login', 'unique' => true, 'sparse' => true],
            ['key' => ['Employee_User_Type' => 1, 'Employee_User_SubType' => 1], 'name' => 'idx_employee_type_subtype'],
            ['key' => ['Employee_User_Status' => 1], 'name' => 'idx_employee_status'],
        ]);

        $this->indexes('user_permits_by_type_database_collection', [
            ['key' => ['Employee_User_Type' => 1, 'Employee_User_SubType' => 1], 'name' => 'idx_permit_type_subtype', 'unique' => true],
        ]);

        $this->indexes('jobs_database_collection', [
            ['key' => ['Customer_Country' => 1, 'JobCollection_Reception_Date' => -1], 'name' => 'idx_country_reception'],
            ['key' => ['JobCollection_Job_Stage' => 1, 'JobCollection_Job_Status' => 1, 'JobCollection_Job_Substatus' => 1], 'name' => 'idx_sss'],
            ['key' => ['JobCollection_Customer_Phone' => 1], 'name' => 'idx_customer_phone'],
            ['key' => ['JobCollection_Job_Setter_Full_Name' => 1], 'name' => 'idx_setter'],
            ['key' => ['JobCollection_Job_Closer_Full_Name' => 1], 'name' => 'idx_closer'],
            ['key' => ['JobCollection_Job_Admin_Full_Name' => 1], 'name' => 'idx_admin'],
            ['key' => ['JobCollection_Sell_Date' => -1], 'name' => 'idx_sell_date'],
            ['key' => ['JobCollection_Jobs_Date' => -1], 'name' => 'idx_jobs_date'],
            ['key' => ['JobCollection_Estimate_Scheduling_Start_TimeZulu' => -1], 'name' => 'idx_estimate_zulu'],
        ]);

        $this->indexes('wages_database_collection', [
            ['key' => ['WagesCollection_Date' => -1], 'name' => 'idx_wages_date'],
            ['key' => ['WagesCollection_Technician_Full_Name' => 1, 'WagesCollection_Date' => -1], 'name' => 'idx_wages_tech_date'],
        ]);

        $this->indexes('estimates_database_collection', [
            ['key' => ['Estimate_Customer_Job_id' => 1], 'name' => 'idx_estimate_jobid'],
        ]);

        $this->indexes('mentions_database_collection', [
            ['key' => ['Mention_Created_At' => -1], 'name' => 'idx_mention_created'],
        ]);
    }

    /**
     * @param array<int, array{key: array<string,int>, name: string, unique?: bool, sparse?: bool}> $defs
     */
    private function indexes(string $collection, array $defs): void
    {
        $db = DB::connection('mongodb')->getMongoDB();
        $coll = $db->selectCollection($collection);

        foreach ($defs as $def) {
            $options = ['name' => $def['name'], 'background' => true];
            if (!empty($def['unique'])) {
                $options['unique'] = true;
            }
            if (!empty($def['sparse'])) {
                $options['sparse'] = true;
            }
            try {
                $coll->createIndex($def['key'], $options);
                $this->command?->info("[idx] {$collection}.{$def['name']}");
            } catch (MongoException $e) {
                $this->command?->warn("[idx-skip] {$collection}.{$def['name']}: " . $e->getMessage());
            }
        }
    }
}

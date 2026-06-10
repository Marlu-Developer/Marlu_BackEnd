<?php

namespace App\Repositories\Sales;

use App\Models\JobsDatabaseCollection;
use Illuminate\Http\Request;
use Jenssegers\Mongodb\Eloquent\Builder;
use MongoDB\BSON\ObjectId;

class SalesRepository
{
    public const DASHBOARD_LIST_FIELDS = [
        '_id',
        'Phone_Flag',
        'JobCollection_Customer_Full_Name',
        'JobCollection_Customer_Phone',
        'JobCollection_Brand',
        'JobCollection_Reception_Date',
        'JobCollection_Customer_Message',
        'JobCollection_Customer_Image_Link',
        'JobCollection_Job_Stage',
        'JobCollection_Job_Status',
        'JobCollection_Job_Substatus',
        'JobCollection_Job_Last_Update',
        'JobCollection_Job_Last_Update_User',
        'JobCollection_Audio_Data',
        'JobCollection_Setter_Comments',
        'JobCollection_Closer_Comments',
        'JobCollection_Office_Comments',
        'JobCollection_Estimate_Scheduling_Start_TimeZulu',
        'JobCollection_Follow_up_Boolean',
        'JobCollection_Assigned_Follow_Up',
        'JobCollection_Follow_up_Date',
        'JobCollection_Job_Setter_Full_Name',
        'JobCollection_Estimate_Schedule_Calendar',
        'JobCollection_Job_Closer_Full_Name',
        'JobCollection_Job_Admin_Full_Name',
        'JobCollection_Estimate',
        'JobCollection_Estimate_Price',
        'JobCollection_Job',
        'JobCollection_Job_Admin_Assigned_Date',
        'JobCollection_Sell_Date',
        'JobCollection_Jobs_Date',
        'JobCollection_Estimate_Type',
        'JobCollection_Estimate_Condition',
        'JobCollection_Estimate_Scheduling_Creation_Date',
        'JobCollection_Estimate_Status',
        'JobCollection_Customer_Type',
        'JobCollection_Deposit_Collection_Boolean',
        'JobCollection_Deposit_Collected_User',
        'JobCollection_Deposit_Collection_Date',
        'JobCollection_Deposit_Payment_Method',
        'JobCollection_Deposit_Amount',
        'JobCollection_Customer_Email',
        'Customer_Postal_Code',
        'Customer_City',
        'JobCollection_Platform',
        'JobCollection_Estimate_Reschedule_Setter',
        'JobCollection_Estimate_Reschedule_Creation_Date',
        'JobCollection_Customer_Record_Addition_Type',
        'JobCollection_Campaign_Name',
        'JobCollection_Form',
    ];

    public const EXPORT_SELECT_FIELDS = [
        ...self::DASHBOARD_LIST_FIELDS,
        'Customer_Address',
    ];

    public function newQuery(): Builder
    {
        return JobsDatabaseCollection::query()->where('Customer_Country', 'Canada');
    }

    /**
     * Update many jobs by ObjectId in a single Mongo bulkWrite (single round-trip).
     *
     * @param array<string, string> $scope Optional extra match conditions (role scope) that
     *                                      are AND-ed with the id filter, so a scoped user can
     *                                      only ever update jobs they are allowed to see.
     */
    public function bulkUpdateByIds(array $ids, array $fields, array $scope = []): int
    {
        if ($ids === [] || $fields === []) {
            return 0;
        }

        $oids = [];
        foreach ($ids as $id) {
            try {
                $oids[] = new ObjectId((string) $id);
            } catch (\Throwable $e) {
                continue;
            }
        }
        if ($oids === []) {
            return 0;
        }

        $filter = ['_id' => ['$in' => $oids]];
        foreach ($scope as $field => $value) {
            $filter[$field] = $value;
        }

        $collection = JobsDatabaseCollection::raw();
        $result = $collection->updateMany(
            $filter,
            ['$set' => $fields]
        );

        return (int) $result->getModifiedCount();
    }

    /**
     * Replace a job's voice-note metadata array (legacy DashboardsController::updateVoiceNotes).
     * Returns 1 if the job existed (matched), else 0.
     */
    public function setAudioData(string $id, array $notes): int
    {
        try {
            $oid = new ObjectId($id);
        } catch (\Throwable $e) {
            return 0;
        }

        $result = JobsDatabaseCollection::raw()->updateOne(
            ['_id' => $oid],
            ['$set' => ['JobCollection_Audio_Data' => array_values($notes)]]
        );

        return (int) $result->getMatchedCount();
    }

    public function countSamePhone(?string $phone): int
    {
        if (!$phone) {
            return 0;
        }
        return JobsDatabaseCollection::where('JobCollection_Customer_Phone', $phone)->count();
    }
}

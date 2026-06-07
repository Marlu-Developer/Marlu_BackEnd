<?php

namespace App\Repositories\SalesEdit;

use App\Models\EventsDatabaseCollection;
use App\Models\InvoicesDatabaseCollection;
use App\Models\JobsDatabaseCollection;
use App\Models\PaymentsDatabaseCollection;
use App\Models\SegmentsDatabaseCollection;
use Illuminate\Support\Collection;
use MongoDB\BSON\ObjectId;

class SalesEditRepository
{
    /**
     * Fields surfaced by /sales/edit/{id}.
     * Kept lean: the Profile tab needs ~15 fields, but we send the whole header set so the FE
     * can render the title bar (brand/stage/status) without an extra round-trip.
     */
    public const RECORD_FIELDS = [
        '_id',
        'Customer_ID',
        'Job_ID',

        // Customer Profile
        'JobCollection_Customer_Full_Name',
        'JobCollection_Customer_Phone',
        'JobCollection_Customer_Email',
        'Customer_Address',
        'Customer_City',
        'Customer_Province',
        'Customer_Postal_Code',
        'Customer_Unit_Number',
        'Customer_Address_Coordinates',
        'Customer_Country',

        // HCP integration
        'JobCollection_HCP_Customer_ID',
        'JobCollection_HCP_Address_ID',
        'JobCollection_HCP_Customer_URL',

        // Header / context (read-only on Profile tab, used elsewhere)
        'JobCollection_Brand',
        'JobCollection_Reception_Date',
        'JobCollection_Job_Stage',
        'JobCollection_Job_Status',
        'JobCollection_Job_Substatus',
        'JobCollection_Job_Last_Update',
        'JobCollection_Job_Last_Update_User',
        'JobCollection_Job_Setter_Full_Name',
        'JobCollection_Job_Closer_Full_Name',
        'JobCollection_Job_Admin_Full_Name',
        'JobCollection_Estimate_Price',
        'JobCollection_Sell_Price',
        'JobCollection_Tags',

        // Sales & Marketing tab
        'JobCollection_Platform',
        'JobCollection_Customer_Type',
        'JobCollection_Campaign_Name',
        'JobCollection_Form',
        'JobCollection_Customer_Record_Addition_Type',
        'JobCollection_Customer_Message',
        'JobCollection_Setter_Comments',
        'JobCollection_Closer_Comments',
        'JobCollection_Office_Comments',
        'JobCollection_Job_Admin_Assigned_Date',
        'JobCollection_Sell_Date',
        'JobCollection_Jobs_Date',
        'JobCollection_Sale_Type',
        'JobCollection_Payment_Deal_Offered',
        'JobCollection_Follow_up_Boolean',
        'JobCollection_Assigned_Follow_Up',
        'JobCollection_Follow_up_Date',
        'JobCollection_Estimate_Schedule_Calendar',
        'JobCollection_Estimate_Type',
        'JobCollection_Estimate_Condition',
        'JobCollection_Estimate_Schedule_Duration',
        'JobCollection_Estimate_Scheduling_Start_TimeZulu',
        'JobCollection_Estimate_Scheduling_End_TimeZulu',
        'JobCollection_Estimate_Scheduling_Notes',
        'JobCollection_Estimate_Scheduling_CalendarID',
        'JobCollection_Estimate_Scheduling_EventID',
        'JobCollection_Estimate_Status',
        'JobCollection_Estimate_Scheduling_Creation_Date',
        'JobCollection_Estimate_Reschedule_Setter',
        'JobCollection_Estimate_Reschedule_Creation_Date',
        'JobCollection_Deposit_Collection_Boolean',
        'JobCollection_Deposit_Collected_User',
        'JobCollection_Deposit_Collection_Date',
        'JobCollection_Deposit_Payment_Method',
        'JobCollection_Deposit_Amount',
        'JobCollection_Support_Files',
        'JobCollection_Audio_Data',
    ];

    /**
     * Fields for the "Related Registers" table (same phone number).
     */
    public const RELATED_FIELDS = [
        '_id',
        'JobCollection_Customer_Full_Name',
        'JobCollection_Brand',
        'JobCollection_Reception_Date',
        'JobCollection_Job_Stage',
        'JobCollection_Job_Status',
        'JobCollection_Job_Substatus',
        'JobCollection_Estimate_Price',
        'JobCollection_Sell_Price',
    ];

    public function findById(string $jobId): ?array
    {
        try {
            $oid = new ObjectId($jobId);
        } catch (\Throwable $e) {
            return null;
        }

        $row = JobsDatabaseCollection::raw()
            ->findOne(['_id' => $oid], ['projection' => array_fill_keys(self::RECORD_FIELDS, 1)]);
        if (!$row) {
            return null;
        }
        return $this->toAssoc($row);
    }

    /**
     * The full estimate sub-document for the Estimates tab: `JobCollection_Estimate`
     * (which holds the estimate number, per-estimate customer/deposit info, and the
     * `JobCollection_Estimate_Information[]` versions with their packages & services).
     * Returns an empty array when the job has no estimate yet.
     *
     * @return array<string, mixed>
     */
    public function estimateData(string $jobId): array
    {
        try {
            $oid = new ObjectId($jobId);
        } catch (\Throwable $e) {
            return [];
        }

        $row = JobsDatabaseCollection::raw()->findOne(
            ['_id' => $oid],
            ['projection' => ['JobCollection_Estimate' => 1]]
        );
        if (!$row) {
            return [];
        }
        $assoc = $this->toAssoc($row);
        $estimate = $assoc['JobCollection_Estimate'] ?? [];
        return is_array($estimate) ? $estimate : [];
    }

    public function relatedByPhone(string $phone, string $excludeJobId): array
    {
        if ($phone === '') {
            return [];
        }
        try {
            $excludeOid = new ObjectId($excludeJobId);
        } catch (\Throwable $e) {
            return [];
        }

        $cursor = JobsDatabaseCollection::raw()
            ->find(
                [
                    'JobCollection_Customer_Phone' => $phone,
                    '_id' => ['$ne' => $excludeOid],
                ],
                ['projection' => array_fill_keys(self::RELATED_FIELDS, 1)]
            );
        return array_values(array_map(fn ($d) => $this->toAssoc($d), iterator_to_array($cursor)));
    }

    /**
     * Fields surfaced by the Timeline tab (the legacy events table columns).
     */
    public const EVENT_FIELDS = [
        '_id',
        'Date',
        'User',
        'User_Photo',
        'Section',
        'Action',
        'Info',
    ];

    /**
     * The event log for a job (legacy SalesDashboard::edit `$Events`): events where
     * Job_ID matches and the Customer_Phone matches, newest first. Job_ID is stored as an
     * ObjectId by the dominant write path, but we also match the string form defensively.
     *
     * @return array<int, array<string, mixed>>
     */
    public function eventsFor(string $jobId, string $phone): array
    {
        $jobIdValues = [$jobId];
        try {
            $jobIdValues[] = new ObjectId($jobId);
        } catch (\Throwable $e) {
            // not a valid ObjectId — string match only
        }

        $filter = ['Job_ID' => ['$in' => $jobIdValues]];
        if ($phone !== '') {
            $filter['Customer_Phone'] = $phone;
        }

        $cursor = EventsDatabaseCollection::raw()->find(
            $filter,
            [
                'projection' => array_fill_keys(self::EVENT_FIELDS, 1),
                'sort' => ['Date' => -1],
            ]
        );

        return array_values(array_map(fn ($d) => $this->toAssoc($d), iterator_to_array($cursor)));
    }

    /**
     * The Job tab's three related lists, all keyed by the job _id (legacy
     * SalesDashboard::edit :1084-1094). Job_ID is stored as the string _id by the dominant
     * write path; we also match the ObjectId form defensively.
     *
     * @return array{segments: array, payments: array, invoices: array}
     */
    public function jobDetails(string $jobId): array
    {
        return [
            'segments' => $this->relatedDocs(SegmentsDatabaseCollection::raw(), 'Segment_Customer_ID', $jobId, ['Segment_Number' => 1]),
            'payments' => $this->relatedDocs(PaymentsDatabaseCollection::raw(), 'Payment_Customer_ID', $jobId, ['Payment_Creation_Date' => 1]),
            'invoices' => $this->relatedDocs(InvoicesDatabaseCollection::raw(), 'Invoice_Customer_ID', $jobId, ['created_at' => 1]),
        ];
    }

    /**
     * Find all docs in a Mongo collection whose $field matches the job id (string or ObjectId),
     * sorted as given, serialized to plain PHP. Shared by the Job tab and the Jobs dashboard.
     *
     * @param array<string, int> $sort
     * @return array<int, array<string, mixed>>
     */
    public function relatedDocs(\MongoDB\Collection $collection, string $field, string $jobId, array $sort): array
    {
        $values = [$jobId];
        try {
            $values[] = new ObjectId($jobId);
        } catch (\Throwable $e) {
            // not an ObjectId — string match only
        }
        $cursor = $collection->find([$field => ['$in' => $values]], ['sort' => $sort]);
        return array_values(array_map(fn ($d) => $this->toAssoc($d), iterator_to_array($cursor)));
    }

    /**
     * Update only the supplied scalar fields. Always stamps an updated_at field.
     */
    public function updateFields(string $jobId, array $fields, string $updaterName): int
    {
        if ($fields === []) {
            return 0;
        }

        try {
            $oid = new ObjectId($jobId);
        } catch (\Throwable $e) {
            return 0;
        }

        $fields['JobCollection_Job_Last_Update'] = now()->toDateTimeString();
        $fields['JobCollection_Job_Last_Update_User'] = $updaterName;

        $result = JobsDatabaseCollection::raw()->updateOne(
            ['_id' => $oid],
            ['$set' => $fields]
        );
        return (int) $result->getModifiedCount();
    }

    /**
     * Recursively cast MongoDB BSON documents/arrays/ObjectIds to plain PHP scalars/arrays
     * so they serialize cleanly through Laravel's JSON pipeline.
     */
    private function toAssoc(mixed $value): mixed
    {
        if ($value instanceof \MongoDB\Model\BSONDocument || $value instanceof \MongoDB\Model\BSONArray) {
            $arr = (array) $value;
            return array_map(fn ($v) => $this->toAssoc($v), $arr);
        }
        if ($value instanceof ObjectId) {
            return (string) $value;
        }
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format(DATE_ATOM);
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->toAssoc($v), $value);
        }
        return $value;
    }
}

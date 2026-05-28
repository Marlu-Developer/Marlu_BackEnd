<?php

namespace App\Repositories\SalesEdit;

use App\Models\JobsDatabaseCollection;
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

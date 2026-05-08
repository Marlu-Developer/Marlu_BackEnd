<?php

namespace App\Support\Exports;

class SalesDashboardCsv
{
    public const HEADERS = [
        'Customer Name', 'Phone', 'Brand', 'Reception Date', 'Customer Message',
        'Sell Stage', 'Sell Status', 'Sell Substatus', 'Last S-S-S Update', 'Last S-S-S Update By',
        'Setter Comments', 'Closer Comments', 'Office Comments', 'Estimate Date',
        'Follow-up', 'Assigned To Follow-up', 'Follow-up Date', 'Setter', 'Scheduled in Calendar Of',
        'Closer', 'Admin', 'Estimate Versions', 'Estimate', 'Booked', 'Deposits', 'Discounts',
        'Upsells', 'Invoiced', 'Total Paid', 'Due', 'Admin Assignation', 'Sell Date', "Job's Date",
        'Estimate Type', 'Scheduling Condition', 'Estimate Creation Date', 'Estimates Scheduling Status',
        'Customer Type', 'Deposit Collection', 'Email', 'Postal Code', 'City', 'Customer Address',
        'Platform', 'Reschedule - Setter', 'Reschedule - Creation Date',
        'Customer Record Addition Type', 'Campaign', 'Form', 'Customer Record URL',
    ];

    public static function row($row): array
    {
        $estimateDate = '';
        if (isset($row->JobCollection_Estimate_Scheduling_Start_TimeZulu)) {
            $estimateDate = substr(str_replace('T', ' ', (string) $row->JobCollection_Estimate_Scheduling_Start_TimeZulu), 0, 16);
        }

        $depositCollection = '';
        if (($row->JobCollection_Deposit_Collection_Boolean ?? '') === 'Yes') {
            $depositCollection = ($row->JobCollection_Deposit_Collected_User ?? '') . ' | '
                . ($row->JobCollection_Deposit_Collection_Date ?? '') . ' | '
                . ($row->JobCollection_Deposit_Payment_Method ?? '') . ' | '
                . ($row->JobCollection_Deposit_Amount ?? '');
        }

        $estimateVersions = '';
        $est = $row->JobCollection_Estimate ?? null;
        if ($est) {
            $estArr = is_array($est) ? $est : (array) $est;
            $info = $estArr['JobCollection_Estimate_Information'] ?? null;
            if (is_array($info)) {
                $estimateVersions = (string) count($info);
            }
        }

        $job = $row->JobCollection_Job ?? null;
        $jobArr = is_array($job) ? $job : (is_object($job) ? (array) $job : []);
        $jobMoney = static fn (string $key): string =>
            isset($jobArr[$key]) && $jobArr[$key] !== '' ? number_format((float) $jobArr[$key], 2) : '';

        $estimatePrice = isset($row->JobCollection_Estimate_Price)
            ? number_format((float) $row->JobCollection_Estimate_Price, 2)
            : '';

        $idStr = $row->getKey() !== null ? (string) $row->getKey() : '';
        $base = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $recordUrl = $base !== '' && $idStr !== ''
            ? $base . '/sales/edit?id=' . rawurlencode($idStr)
            : '';

        return [
            $row->JobCollection_Customer_Full_Name ?? '',
            $row->JobCollection_Customer_Phone ?? '',
            $row->JobCollection_Brand ?? '',
            $row->JobCollection_Reception_Date ?? '',
            $row->JobCollection_Customer_Message ?? '',
            $row->JobCollection_Job_Stage ?? '',
            $row->JobCollection_Job_Status ?? '',
            $row->JobCollection_Job_Substatus ?? '',
            $row->JobCollection_Job_Last_Update ?? '',
            $row->JobCollection_Job_Last_Update_User ?? 'Unknown User',
            $row->JobCollection_Setter_Comments ?? '',
            $row->JobCollection_Closer_Comments ?? '',
            $row->JobCollection_Office_Comments ?? '',
            $estimateDate,
            $row->JobCollection_Follow_up_Boolean ?? '',
            $row->JobCollection_Assigned_Follow_Up ?? '',
            $row->JobCollection_Follow_up_Date ?? '',
            $row->JobCollection_Job_Setter_Full_Name ?? 'Not Assigned',
            $row->JobCollection_Estimate_Schedule_Calendar ?? '',
            $row->JobCollection_Job_Closer_Full_Name ?? 'Not Assigned',
            $row->JobCollection_Job_Admin_Full_Name ?? 'Not Assigned',
            $estimateVersions,
            $estimatePrice,
            $jobMoney('Job_Booked'),
            $jobMoney('Job_Deposits_Subtotal'),
            $jobMoney('Job_Discounts'),
            $jobMoney('Job_Upsells'),
            $jobMoney('Job_Subtotal_Less_Discounts'),
            $jobMoney('Job_Overall_Subtotal_Payments'),
            $jobMoney('Job_Pending_Subtotal_Balance'),
            $row->JobCollection_Job_Admin_Assigned_Date ?? '',
            $row->JobCollection_Sell_Date ?? '',
            $row->JobCollection_Jobs_Date ?? '',
            $row->JobCollection_Estimate_Type ?? '',
            $row->JobCollection_Estimate_Condition ?? '',
            $row->JobCollection_Estimate_Scheduling_Creation_Date ?? '',
            $row->JobCollection_Estimate_Status ?? 'Not Done',
            $row->JobCollection_Customer_Type ?? '',
            $depositCollection,
            $row->JobCollection_Customer_Email ?? '',
            $row->Customer_Postal_Code ?? '',
            $row->Customer_City ?? '',
            $row->Customer_Address ?? '',
            $row->JobCollection_Platform ?? '',
            $row->JobCollection_Estimate_Reschedule_Setter ?? '',
            $row->JobCollection_Estimate_Reschedule_Creation_Date ?? '',
            $row->JobCollection_Customer_Record_Addition_Type ?? 'Not Defined',
            $row->JobCollection_Campaign_Name ?? '',
            $row->JobCollection_Form ?? '',
            $recordUrl,
        ];
    }
}

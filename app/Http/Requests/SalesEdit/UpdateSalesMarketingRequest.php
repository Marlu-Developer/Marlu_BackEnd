<?php

namespace App\Http\Requests\SalesEdit;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sales & Marketing tab fields (the editable inputs of the legacy
 * sales-marketing-tab.blade.php). Everything is nullable so partial saves work.
 */
class UpdateSalesMarketingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lock_token' => ['required', 'string', 'max:80'],

            // Source / marketing
            'JobCollection_Brand' => ['nullable', 'string', 'max:128'],
            'JobCollection_Platform' => ['nullable', 'string', 'max:128'],
            'JobCollection_Customer_Type' => ['nullable', 'string', 'max:128'],
            'JobCollection_Campaign_Name' => ['nullable', 'string', 'max:255'],
            'JobCollection_Form' => ['nullable', 'string', 'max:255'],
            'JobCollection_Customer_Record_Addition_Type' => ['nullable', 'string', 'max:64'],
            'JobCollection_Reception_Date' => ['nullable', 'string', 'max:64'],
            'JobCollection_Customer_Message' => ['nullable', 'string', 'max:5000'],

            // Comments
            'JobCollection_Setter_Comments' => ['nullable', 'string', 'max:5000'],
            'JobCollection_Closer_Comments' => ['nullable', 'string', 'max:5000'],
            'JobCollection_Office_Comments' => ['nullable', 'string', 'max:5000'],

            // Assignments & pricing
            'JobCollection_Job_Setter_Full_Name' => ['nullable', 'string', 'max:255'],
            'JobCollection_Job_Closer_Full_Name' => ['nullable', 'string', 'max:255'],
            'JobCollection_Job_Admin_Full_Name' => ['nullable', 'string', 'max:255'],
            'JobCollection_Job_Admin_Assigned_Date' => ['nullable', 'string', 'max:64'],
            'JobCollection_Estimate_Price' => ['nullable', 'string', 'max:32'],
            'JobCollection_Sell_Price' => ['nullable', 'string', 'max:32'],
            'JobCollection_Sell_Date' => ['nullable', 'string', 'max:64'],
            'JobCollection_Jobs_Date' => ['nullable', 'string', 'max:64'],
            'JobCollection_Sale_Type' => ['nullable', 'string', 'max:64'],
            'JobCollection_Payment_Deal_Offered' => ['nullable', 'string', 'max:64'],

            // Follow-up
            'JobCollection_Follow_up_Boolean' => ['nullable', 'string', 'max:16'],
            'JobCollection_Assigned_Follow_Up' => ['nullable', 'string', 'max:255'],
            'JobCollection_Follow_up_Date' => ['nullable', 'string', 'max:64'],

            // Estimate scheduling
            'JobCollection_Estimate_Schedule_Calendar' => ['nullable', 'string', 'max:128'],
            'JobCollection_Estimate_Type' => ['nullable', 'string', 'max:64'],
            'JobCollection_Estimate_Condition' => ['nullable', 'string', 'max:64'],
            'JobCollection_Estimate_Schedule_Duration' => ['nullable', 'string', 'max:32'],
            'JobCollection_Estimate_Scheduling_Start_TimeZulu' => ['nullable', 'string', 'max:64'],
            'JobCollection_Estimate_Scheduling_End_TimeZulu' => ['nullable', 'string', 'max:64'],
            'JobCollection_Estimate_Scheduling_Notes' => ['nullable', 'string', 'max:5000'],
            'JobCollection_Estimate_Scheduling_CalendarID' => ['nullable', 'string', 'max:255'],
            'JobCollection_Estimate_Status' => ['nullable', 'string', 'max:64'],
            'JobCollection_Estimate_Scheduling_Creation_Date' => ['nullable', 'string', 'max:64'],
            'JobCollection_Estimate_Reschedule_Setter' => ['nullable', 'string', 'max:255'],
            'JobCollection_Estimate_Reschedule_Creation_Date' => ['nullable', 'string', 'max:64'],

            // Deposit collection
            'JobCollection_Deposit_Collection_Boolean' => ['nullable', 'string', 'max:16'],
            'JobCollection_Deposit_Collected_User' => ['nullable', 'string', 'max:255'],
            'JobCollection_Deposit_Collection_Date' => ['nullable', 'string', 'max:64'],
            'JobCollection_Deposit_Payment_Method' => ['nullable', 'string', 'max:64'],
            'JobCollection_Deposit_Amount' => ['nullable', 'string', 'max:32'],
        ];
    }
}

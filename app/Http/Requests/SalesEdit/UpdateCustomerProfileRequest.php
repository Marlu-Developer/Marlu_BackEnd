<?php

namespace App\Http\Requests\SalesEdit;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Profile-tab fields: only those the legacy customer-profile-tab.blade.php writes.
 * Everything is optional/nullable so partial saves are supported.
 */
class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lock_token' => ['required', 'string', 'max:80'],

            'JobCollection_Customer_Full_Name' => ['nullable', 'string', 'max:255'],
            'JobCollection_Customer_Phone' => ['nullable', 'string', 'max:64'],
            'JobCollection_Customer_Email' => ['nullable', 'string', 'max:255'],

            'Customer_Address' => ['nullable', 'string', 'max:1000'],
            'Customer_City' => ['nullable', 'string', 'max:255'],
            'Customer_Province' => ['nullable', 'string', 'max:255'],
            'Customer_Postal_Code' => ['nullable', 'string', 'max:64'],
            'Customer_Unit_Number' => ['nullable', 'string', 'max:64'],
            'Customer_Address_Coordinates' => ['nullable', 'string', 'max:128'],

            'JobCollection_HCP_Customer_ID' => ['nullable', 'string', 'max:128'],
            'JobCollection_HCP_Address_ID' => ['nullable', 'string', 'max:128'],
            'JobCollection_HCP_Customer_URL' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

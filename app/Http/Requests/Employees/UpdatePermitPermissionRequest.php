<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermitPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permit_user_type' => ['required', 'string', 'max:60'],
            'permit_user_subtype' => ['required', 'string', 'max:60'],
            'permission_key' => ['required', 'string', 'max:120'],
            'permission_value' => ['present'],
        ];
    }
}

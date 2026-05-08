<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;

class ResetEmployeeToTypeDefaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.employeeKey' => ['required', 'string', 'max:120'],
            'mappings.*.permitKey' => ['required', 'string', 'max:120'],
        ];
    }
}

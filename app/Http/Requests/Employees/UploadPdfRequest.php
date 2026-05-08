<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;

class UploadPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9 _.\\-]+$/'],
            'content' => ['required', 'string'],
        ];
    }
}

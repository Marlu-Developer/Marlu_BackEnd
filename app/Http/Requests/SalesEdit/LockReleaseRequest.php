<?php

namespace App\Http\Requests\SalesEdit;

use Illuminate\Foundation\Http\FormRequest;

class LockReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string', 'max:80'],
        ];
    }
}

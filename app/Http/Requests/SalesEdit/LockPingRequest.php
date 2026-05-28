<?php

namespace App\Http\Requests\SalesEdit;

use Illuminate\Foundation\Http\FormRequest;

class LockPingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:80'],
        ];
    }
}

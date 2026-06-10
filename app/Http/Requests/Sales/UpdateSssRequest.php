<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSssRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'size:24'],
            'stage' => ['required', 'string', 'max:200'],
            'status' => ['required', 'string', 'max:200'],
            'substatus' => ['required', 'string', 'max:200'],
        ];
    }
}

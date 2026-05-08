<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class BulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['nullable', 'array', 'max:1000'],
            'ids.*' => ['string', 'size:24'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['nullable', 'string', 'max:2000'],
            'filter_query' => ['nullable', 'array'],
        ];
    }
}

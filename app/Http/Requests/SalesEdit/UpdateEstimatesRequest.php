<?php

namespace App\Http\Requests\SalesEdit;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The Estimates tab persists the whole nested `JobCollection_Estimate` object. The structure
 * is deep and FE-owned (versions → packages → services), so we validate the envelope only:
 * a lock token plus an `estimate` object.
 */
class UpdateEstimatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lock_token' => ['required', 'string', 'max:80'],
            'estimate' => ['present', 'array'],
        ];
    }
}

<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;

class CreateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'alias' => ['required', 'string', 'max:120'],
            'full_name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:30'],
            'user_login' => ['required', 'string', 'max:120'],
            'user_type' => ['required', 'string', 'max:30'],
            'user_subtype' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:4', 'max:120'],
            'starting_date' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc'],
            'user_status' => ['nullable', 'in:active,inactive'],
            'schedule' => ['nullable', 'array'],
            'pic' => ['nullable', 'string'],
            'files-names' => ['nullable', 'string'],
        ];
    }

    /**
     * Pre-trim string-like fields (mirrors the legacy client-side behaviour and
     * prevents whitespace-only values from passing the `required` rule).
     */
    protected function prepareForValidation(): void
    {
        $trimKeys = [
            'alias', 'full_name', 'phone', 'user_login', 'user_type',
            'user_subtype', 'starting_date', 'email',
        ];
        $patch = [];
        foreach ($trimKeys as $k) {
            if ($this->has($k) && is_string($this->input($k))) {
                $patch[$k] = trim((string) $this->input($k));
            }
        }
        if ($patch !== []) {
            $this->merge($patch);
        }
    }
}

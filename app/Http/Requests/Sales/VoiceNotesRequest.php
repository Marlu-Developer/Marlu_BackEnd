<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class VoiceNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'size:24'],
            'notes' => ['present', 'array', 'max:200'],
            'notes.*.Audio_Recorded_User' => ['nullable', 'string', 'max:200'],
            'notes.*.Audio_Recorded_Date' => ['required', 'string', 'max:30'],
        ];
    }
}

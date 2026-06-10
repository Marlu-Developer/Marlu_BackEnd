<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UploadAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'max:51200'],
            'name' => ['required', 'string', 'regex:/^\d{1,20}\.wav$/'],
        ];
    }
}

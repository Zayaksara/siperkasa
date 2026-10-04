<?php

namespace App\Http\Requests\Pic;

use Illuminate\Foundation\Http\FormRequest;

class HoldReportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'hold_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'hold_reason.required' => 'Alasan penundaan wajib diisi.',
            'hold_reason.min' => 'Alasan penundaan minimal 10 karakter.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $report = $this->route('report');

        return [
            'assigned_pic_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Pic->value)->where('is_active', true)->whereNull('deleted_at'),
                Rule::notIn([$report instanceof Report ? $report->assigned_pic_id : null]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_pic_id.exists' => 'PIC harus pengguna aktif dengan peran PIC.',
            'assigned_pic_id.not_in' => 'PIC baru harus berbeda dari PIC saat ini.',
        ];
    }
}

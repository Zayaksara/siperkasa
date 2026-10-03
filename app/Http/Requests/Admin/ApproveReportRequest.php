<?php

namespace App\Http\Requests\Admin;

use App\Enums\Priority;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'admin_priority' => ['required', Rule::enum(Priority::class)],
            'sla_date' => ['required', 'date', 'after:now'],
            'assigned_pic_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Pic->value)->where('is_active', true)->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'sla_date.after' => 'SLA harus di masa depan.',
            'assigned_pic_id.exists' => 'PIC harus pengguna aktif dengan peran PIC.',
        ];
    }
}

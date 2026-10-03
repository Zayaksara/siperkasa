<?php

namespace Database\Factories;

use App\Enums\AttachmentType;
use App\Models\Report;
use App\Models\ReportAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReportAttachment> */
class ReportAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'type' => AttachmentType::Damage,
            'path' => 'reports/test/damage/'.Str::uuid().'.jpg',
            'position' => 1,
        ];
    }

    public function completion(): static
    {
        return $this->state(fn () => ['type' => AttachmentType::Completion]);
    }
}

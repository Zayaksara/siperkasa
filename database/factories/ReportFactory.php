<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Report> */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Valid SPK-YYMM-NNNN shape in the unused "99xx" month range, so it never collides
            // with real tickets from TicketNumber::next(), even for ->count(n)->create().
            'ticket_number' => fake()->unique()->numerify('SPK-99##-####'),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'category_id' => Category::factory(),
            'room_id' => Room::factory(),
            'asset_id' => null,
            'description' => fake()->paragraph(),
            'user_priority' => fake()->randomElement(Priority::cases()),
            'status' => ReportStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ReportStatus::Pending]);
    }

    public function assigned(?User $pic = null): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Assigned,
            'admin_id' => User::factory()->admin(),
            'admin_priority' => Priority::Medium,
            'sla_date' => now()->addDays(7),
            'assigned_pic_id' => $pic?->id ?? User::factory()->pic(),
            'validated_at' => now(),
        ]);
    }

    public function inProgress(?User $pic = null): static
    {
        return $this->assigned($pic)->state(fn () => [
            'status' => ReportStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function onHold(?User $pic = null): static
    {
        return $this->inProgress($pic)->state(fn () => [
            'status' => ReportStatus::OnHold,
            'hold_reason' => 'Menunggu sparepart dari vendor.',
        ]);
    }

    public function closed(?User $pic = null): static
    {
        return $this->inProgress($pic)->state(fn () => [
            'status' => ReportStatus::Closed,
            'repair_action' => 'Ganti lampu proyektor.',
            'completed_on' => now()->toDateString(),
            'repair_cost' => 350000,
            'vendor_name' => 'CV Teknik Jaya',
            'closed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Rejected,
            'admin_id' => User::factory()->admin(),
            'rejection_reason' => 'Laporan duplikat dengan tiket lain.',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}

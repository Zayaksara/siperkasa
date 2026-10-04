<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\Room;
use App\Models\User;
use App\Support\TicketNumber;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/** Local demo data so dashboards, lists and exports have something to show. */
class DemoReportSeeder extends Seeder
{
    /** @var array<string, int> state => how many reports */
    private const PLAN = [
        'pending' => 6, 'assigned' => 5, 'inProgress' => 5, 'onHold' => 3, 'closed' => 8, 'rejected' => 2, 'cancelled' => 1,
    ];

    private const TITLES = [
        'Proyektor tidak menyala', 'AC tidak dingin', 'Kursi kuliah patah', 'Komputer lab restart sendiri',
        'Lampu ruangan mati', 'Whiteboard rusak', 'Mic wireless tidak berfungsi', 'Kabel LAN putus',
        'Pintu ruang kelas macet', 'Stop kontak korsleting',
    ];

    public function run(): void
    {
        $admin = User::where('role', UserRole::Admin)->first();
        $pics = User::where('role', UserRole::Pic)->where('is_active', true)->get();
        $reporters = User::where('role', UserRole::User)->where('is_active', true)->get();
        $categories = Category::all();
        $rooms = Room::all();

        if (! $admin || $pics->isEmpty() || $reporters->isEmpty() || $categories->isEmpty() || $rooms->isEmpty()) {
            return;
        }

        // Idempotency: return early if demo data already seeded
        if (Report::where('description', 'like', '[Demo]%')->exists()) {
            return;
        }

        foreach (self::PLAN as $state => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->createReport($state, $admin, $pics->random(), $reporters->random(), $categories->random(), $rooms->random());
            }
        }
    }

    private function createReport(string $state, User $admin, User $pic, User $reporter, Category $category, Room $room): void
    {
        $createdAt = now()->subDays(random_int(1, 40))->subMinutes(random_int(0, 600));

        $overrides = [
            'ticket_number' => TicketNumber::next($createdAt),
            'user_id' => $reporter->id,
            'category_id' => $category->id,
            'room_id' => $room->id,
            'title' => self::TITLES[array_rand(self::TITLES)],
            'description' => '[Demo] Demo report for testing',
            'user_priority' => Priority::cases()[array_rand(Priority::cases())],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        // Passing admin_id stops the factory states from creating an extra admin user per report.
        if (! in_array($state, ['pending', 'cancelled'], true)) {
            $overrides['admin_id'] = $admin->id;
        }

        $factory = Report::factory();
        $factory = in_array($state, ['pending', 'rejected', 'cancelled'], true) ? $factory->{$state}() : $factory->{$state}($pic);

        $report = $factory->create($overrides);

        // Ensure all timestamps ≤ now() and maintain order: created_at ≤ validated_at ≤ started_at ≤ closed_at
        $validatedAt = min($createdAt->copy()->addHours(random_int(1, 12)), now());
        $startedAt = $state === 'assigned' ? null : min($validatedAt->copy()->addHours(2), now());
        $closedAtCalc = ($startedAt ?? $validatedAt)->copy()->addDays(random_int(1, 5));
        $closedAt = $state === 'closed' ? min($closedAtCalc, now()) : null;

        $attributes = match ($state) {
            'rejected', 'pending' => [],
            'cancelled' => ['cancelled_at' => min($createdAt->copy()->addHour(), now())],
            default => [
                'admin_priority' => $report->user_priority,
                'validated_at' => $validatedAt,
                'sla_date' => $validatedAt->copy()->addDays($report->user_priority->slaDays()),
                'started_at' => $startedAt,
                'closed_at' => $closedAt,
                'completed_on' => $closedAt ? $closedAt->toDateString() : null,
            ],
        };

        $report->forceFill($attributes)->save();

        $this->log($report, null, ReportStatus::Pending->value, $reporter, null, $createdAt);

        if ($state !== 'pending') {
            $this->log($report, ReportStatus::Pending->value, $report->status->value, $state === 'cancelled' ? $reporter : $admin, null, $validatedAt);
        }
    }

    private function log(Report $report, ?string $from, string $to, User $actor, ?string $note, CarbonInterface $at): void
    {
        $log = ReportStatusLog::create(['report_id' => $report->id, 'from_status' => $from, 'to_status' => $to, 'actor_id' => $actor->id, 'note' => $note]);
        $log->forceFill(['created_at' => $at])->save();
    }
}

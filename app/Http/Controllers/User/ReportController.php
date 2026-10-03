<?php

namespace App\Http\Controllers\User;

use App\Actions\Reports\CancelReport;
use App\Actions\Reports\SubmitReport;
use App\Actions\Reports\UpdateReport;
use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Support\ReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('create', Report::class); // reporters only

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $reports = Report::query()
            ->visibleTo($request->user())
            ->withSummary()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('ticket_number', 'like', $like)->orWhere('title', 'like', $like));
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Report $report) => ReportPresenter::summary($report));

        return Inertia::render('reports/Index', [
            'reports' => $reports,
            'filters' => $filters,
            'statuses' => collect(ReportStatus::cases())->map(fn (ReportStatus $status) => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Report::class);

        return Inertia::render('reports/Create', $this->formOptions());
    }

    public function store(StoreReportRequest $request, SubmitReport $submit): RedirectResponse
    {
        Gate::authorize('create', Report::class);

        $report = $submit->handle(
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Laporan {$report->ticket_number} berhasil dikirim."]);

        return to_route('reports.show', $report);
    }

    public function show(Report $report): Response
    {
        Gate::authorize('view', $report);

        return Inertia::render('reports/Show', [
            'report' => ReportPresenter::detail($report),
            'can' => [
                'update' => Gate::allows('update', $report),
                'cancel' => Gate::allows('cancel', $report),
            ],
        ]);
    }

    public function edit(Report $report): Response
    {
        Gate::authorize('update', $report);

        return Inertia::render('reports/Edit', [
            ...$this->formOptions(),
            'report' => ReportPresenter::detail($report),
        ]);
    }

    public function update(UpdateReportRequest $request, Report $report, UpdateReport $update): RedirectResponse
    {
        Gate::authorize('update', $report);

        $update->handle(
            $report,
            $request->user(),
            $request->safe()->except(['photos', 'remove_photo_ids']),
            $request->file('photos', []),
            $request->input('remove_photo_ids', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan diperbarui.']);

        return to_route('reports.show', $report);
    }

    public function cancel(Request $request, Report $report, CancelReport $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $report);

        $cancel->handle($report, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan dibatalkan.']);

        return to_route('reports.show', $report);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'buildings' => Building::query()->with(['rooms' => fn ($query) => $query->orderBy('code')])->orderBy('code')->get()
                ->map(fn (Building $building) => [
                    'id' => $building->id,
                    'code' => $building->code,
                    'name' => $building->name,
                    'rooms' => $building->rooms->map(fn ($room) => ['id' => $room->id, 'code' => $room->code, 'name' => $room->name])->values(),
                ]),
            'priorities' => collect(Priority::cases())->map(fn (Priority $priority) => ['value' => $priority->value, 'label' => $priority->label()]),
        ];
    }
}

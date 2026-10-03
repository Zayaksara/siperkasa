<?php

namespace App\Http\Controllers\User;

use App\Actions\Reports\SubmitReport;
use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Support\ReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
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

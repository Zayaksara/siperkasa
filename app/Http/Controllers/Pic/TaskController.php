<?php

namespace App\Http\Controllers\Pic;

use App\Actions\Reports\CompleteReport;
use App\Actions\Reports\HoldReport;
use App\Actions\Reports\ResumeReport;
use App\Actions\Reports\StartReport;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pic\CompleteReportRequest;
use App\Http\Requests\Pic\HoldReportRequest;
use App\Models\Report;
use App\Support\ReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    private const ACTIVE = [ReportStatus::Assigned, ReportStatus::InProgress, ReportStatus::OnHold];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Report::class);

        $tab = $request->query('tab') === 'done' ? 'done' : 'active';
        $mine = fn () => Report::query()->visibleTo($request->user());

        $query = $mine()->withSummary();

        $tab === 'done'
            ? $query->where('status', ReportStatus::Closed)->orderByDesc('closed_at')->orderBy('id')
            : $query->whereIn('status', self::ACTIVE)->orderBy('sla_date')->orderBy('id');

        return Inertia::render('pic/tasks/Index', [
            'tasks' => $query->paginate(10)->withQueryString()->through(fn (Report $report) => ReportPresenter::summary($report)),
            'tab' => $tab,
            'counts' => [
                'active' => $mine()->whereIn('status', self::ACTIVE)->count(),
                'done' => $mine()->where('status', ReportStatus::Closed)->count(),
            ],
        ]);
    }

    public function show(Report $report): Response
    {
        Gate::authorize('view', $report);

        return Inertia::render('pic/tasks/Show', [
            'report' => ReportPresenter::detail($report),
            'can' => [
                'start' => Gate::allows('start', $report),
                'hold' => Gate::allows('hold', $report),
                'resume' => Gate::allows('resume', $report),
                'complete' => Gate::allows('complete', $report),
            ],
        ]);
    }

    public function start(Request $request, Report $report, StartReport $start): RedirectResponse
    {
        Gate::authorize('start', $report);

        $start->handle($report, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengerjaan dimulai.']);

        return to_route('pic.tasks.show', $report);
    }

    public function hold(HoldReportRequest $request, Report $report, HoldReport $hold): RedirectResponse
    {
        Gate::authorize('hold', $report);

        $hold->handle($report, $request->user(), $request->validated('hold_reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas ditunda.']);

        return to_route('pic.tasks.show', $report);
    }

    public function resume(Request $request, Report $report, ResumeReport $resume): RedirectResponse
    {
        Gate::authorize('resume', $report);

        $resume->handle($report, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengerjaan dilanjutkan.']);

        return to_route('pic.tasks.show', $report);
    }

    public function complete(CompleteReportRequest $request, Report $report, CompleteReport $complete): RedirectResponse
    {
        Gate::authorize('complete', $report);

        $complete->handle(
            $report,
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Laporan {$report->ticket_number} selesai."]);

        return to_route('pic.tasks.index');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ValidateReport;
use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveReportRequest;
use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use App\Support\ReportFilter;
use App\Support\ReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Report::class);

        $filter = ReportFilter::fromRequest($request);

        $reports = $filter->apply(Report::query())
            ->with(['category:id,name', 'room.building:id,name', 'user:id,name', 'assignedPic:id,name'])
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'Pending' THEN created_at END ASC")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Report $report) => ReportPresenter::summary($report));

        return Inertia::render('admin/reports/Index', [
            'reports' => $reports,
            'filters' => $filter->values,
            'options' => [
                'categories' => Category::orderBy('name')->get(['id', 'name']),
                'buildings' => Building::orderBy('code')->get(['id', 'name']),
                'pics' => User::where('role', UserRole::Pic)->orderBy('name')->get(['id', 'name']),
                'statuses' => collect(ReportStatus::cases())->map(fn (ReportStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
                'priorities' => collect(Priority::cases())->map(fn (Priority $p) => ['value' => $p->value, 'label' => $p->label()]),
            ],
        ]);
    }

    public function show(Report $report): Response
    {
        Gate::authorize('view', $report);

        $report->loadMissing('category');

        $activePics = User::query()
            ->where('role', UserRole::Pic)
            ->where('is_active', true)
            ->withCount(['assignedReports as active_tasks' => fn ($query) => $query->whereNotIn('status', ReportStatus::finalValues())])
            ->orderBy('name')
            ->get(['id', 'name']);

        $suggested = $report->category?->default_pic_id;

        return Inertia::render('admin/reports/Show', [
            'report' => ReportPresenter::detail($report),
            'can' => [
                'approve' => Gate::allows('validate', $report),
                'reject' => Gate::allows('reject', $report),
                'reassign' => Gate::allows('reassign', $report),
            ],
            'pics' => $activePics->map(fn (User $pic) => ['id' => $pic->id, 'name' => $pic->name, 'active_tasks' => $pic->active_tasks]),
            'current_pic_id' => $report->assigned_pic_id,
            'suggested_pic_id' => $activePics->contains('id', $suggested) ? $suggested : null,
            'priorities' => collect(Priority::cases())->map(fn (Priority $p) => ['value' => $p->value, 'label' => $p->label()]),
            'sla_defaults' => collect(Priority::cases())->mapWithKeys(
                fn (Priority $p) => [$p->value => now()->addDays($p->slaDays())->format('Y-m-d\TH:i')],
            ),
        ]);
    }

    public function approve(ApproveReportRequest $request, Report $report, ValidateReport $validate): RedirectResponse
    {
        Gate::authorize('validate', $report);

        $validate->handle(
            $report,
            $request->user(),
            Priority::from($request->validated('admin_priority')),
            Date::parse($request->validated('sla_date')),
            User::findOrFail($request->validated('assigned_pic_id')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Laporan {$report->ticket_number} divalidasi dan ditugaskan."]);

        return to_route('admin.reports.show', $report);
    }
}

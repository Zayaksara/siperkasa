<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\ReportExport;
use App\Support\ReportFilter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function excel(Request $request): StreamedResponse
    {
        Gate::authorize('export', Report::class);

        $filter = ReportFilter::fromRequest($request);

        return response()->streamDownload(function () use ($filter) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(ReportExport::headerRow());

            $count = 0;
            $reports = $filter->apply(Report::query())
                ->with(ReportExport::RELATIONS)
                ->lazyById(500);

            foreach ($reports as $report) {
                $writer->addRow(ReportExport::row($report));
                $count++;
            }

            if ($count === 0) {
                $writer->addRow(ReportExport::emptyRow());
            }

            $writer->close();
        }, $this->fileName('xlsx'), ['Content-Type' => self::XLSX_MIME]);
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        Gate::authorize('export', Report::class);

        $filter = ReportFilter::fromRequest($request);
        $query = $filter->apply(Report::query());

        $total = (clone $query)->count();

        if ($total > (int) config('siperkasa.pdf_max_rows')) {
            return back()->withErrors([
                'export' => "Terlalu banyak data ({$total} baris) untuk PDF. Gunakan Excel atau persempit filter.",
            ]);
        }

        set_time_limit(120);

        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $summary = [];
        foreach (ReportStatus::cases() as $status) {
            if (($counts[$status->value] ?? 0) > 0) {
                $summary[$status->label()] = (int) $counts[$status->value];
            }
        }

        $rows = $query->with(['room.building:id,name', 'assignedPic:id,name,deleted_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Report $report) => [
                'ticket' => $report->ticket_number,
                'date' => $report->created_at?->format('d M Y'),
                'title' => $report->title,
                'location' => ReportExport::location($report),
                'status' => $report->status->label(),
                'pic' => $report->assignedPic?->name ?? '-',
                'sla' => $report->sla_date?->format('d M Y H:i') ?? '-',
            ])->all();

        $logoPath = public_path('images/logo-uinssc-pdf.png');

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.reports', [
            'logo' => is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : '',
            'printedAt' => now()->format('d M Y H:i'),
            'filterText' => ReportExport::filterText($filter->values),
            'summary' => $summary,
            'rows' => $rows,
        ])->render());
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        // Page numbers: stamp every page after the single render.
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 120,
            $canvas->get_height() - 24,
            'Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal'),
            8,
            [0.34, 0.36, 0.35],
        );

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->fileName('pdf').'"',
        ]);
    }

    private function fileName(string $extension): string
    {
        return 'laporan-siperkasa-'.now()->format('Ymd-Hi').'.'.$extension;
    }
}

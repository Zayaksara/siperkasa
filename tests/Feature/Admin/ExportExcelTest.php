<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

/** @return array{rows: list<list<mixed>>, xml: string} */
function workbook($response): array
{
    $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $rows = [];
    $reader = new Reader;
    $reader->open($path);
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
        break;
    }
    $reader->close();

    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($path);

    return ['rows' => $rows, 'xml' => $xml];
}

test('the workbook has the agreed columns and one row per matching report', function () {
    $category = Category::factory()->create(['name' => 'Elektronik']);
    $pic = User::factory()->pic()->create(['name' => 'Pak Teknisi']);
    $asset = Asset::factory()->create();
    $report = Report::factory()->closed($pic)->create([
        'category_id' => $category->id,
        'asset_id' => $asset->id,
        'title' => 'Proyektor mati',
        'sla_date' => now()->subDay(),
    ]);
    Report::factory()->count(2)->create();

    $response = $this->actingAs($this->admin)
        ->get(route('admin.reports.export.excel', ['status' => 'Closed', 'category_id' => $category->id]))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet')
        ->and($response->headers->get('content-disposition'))->toMatch('/laporan-siperkasa-\d{8}-\d{4}\.xlsx/');

    $rows = workbook($response)['rows'];

    expect($rows[0])->toHaveCount(19)
        ->and($rows[0][0])->toBe('Tiket')
        ->and($rows[0][14])->toBe('Terlambat');
    expect($rows)->toHaveCount(2);
    expect($rows[1][0])->toBe($report->ticket_number)
        ->and($rows[1][2])->toBe($report->user->name)
        ->and($rows[1][3])->toBe($report->user->nim_nip)
        ->and($rows[1][4])->toBe('Proyektor mati')
        ->and($rows[1][5])->toBe('Elektronik')
        ->and($rows[1][8])->toBe($asset->asset_code)
        ->and($rows[1][11])->toBe('Selesai')
        ->and($rows[1][12])->toBe('Pak Teknisi')
        ->and($rows[1][14])->toBe('Tidak')
        ->and((float) $rows[1][17])->toBe(350000.0)
        ->and($rows[1][18])->toBe('CV Teknik Jaya');
});

test('the export applies the same filters as the list', function () {
    Report::factory()->count(3)->create();
    Report::factory()->assigned()->create(['sla_date' => now()->subDay()]);

    $rows = workbook($this->actingAs($this->admin)->get(route('admin.reports.export.excel', ['overdue' => 1])))['rows'];

    expect($rows)->toHaveCount(2)->and($rows[1][14])->toBe('Ya');
});

test('the date filter includes the whole last day', function () {
    Report::factory()->create(['created_at' => '2026-10-05 23:59:00']);
    Report::factory()->create(['created_at' => '2026-10-06 00:00:00']);
    Report::factory()->create(['created_at' => '2026-10-04 23:59:59']);

    $rows = workbook($this->actingAs($this->admin)->get(route('admin.reports.export.excel', ['from' => '2026-10-05', 'to' => '2026-10-05'])))['rows'];

    expect($rows)->toHaveCount(2);
});

test('every matching report is exported, not just one page', function () {
    Report::factory()->count(20)->create();

    expect(workbook($this->actingAs($this->admin)->get(route('admin.reports.export.excel')))['rows'])->toHaveCount(21);
});

test('a filter that matches nothing still yields a workbook with a no-data row', function () {
    Report::factory()->create();

    $rows = workbook($this->actingAs($this->admin)->get(route('admin.reports.export.excel', ['status' => 'Cancelled'])))['rows'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('Tiket')
        ->and($rows[1][0])->toBe('Tidak ada data');
});

test('values that look like formulas are exported as exact text, never a formula', function () {
    Report::factory()->create(['title' => '=HYPERLINK("http://evil.example","klik")']);
    Report::factory()->create(['title' => '+1 AC rusak']);
    Report::factory()->create(['title' => '@SUM(A1)']);
    Report::factory()->create(['title' => '- ganti kabel']);

    $book = workbook($this->actingAs($this->admin)->get(route('admin.reports.export.excel')));
    $titles = collect($book['rows'])->skip(1)->pluck(4)->all();

    // exact text, no visible apostrophe (the reader itself maps a leading "=" to a formula, so the XML is the proof)
    expect($titles)->toHaveCount(4)
        ->toContain('=HYPERLINK("http://evil.example","klik")', '+1 AC rusak', '@SUM(A1)', '- ganti kabel');
    expect($book['xml'])->not->toContain('<f>');
});

test('a trashed reporter and PIC do not break the export', function () {
    $pic = User::factory()->pic()->create(['name' => 'PIC Lama']);
    $report = Report::factory()->assigned($pic)->create();
    $reporter = $report->user;
    $reporter->delete();
    $pic->delete();

    $rows = workbook($this->actingAs($this->admin)->get(route('admin.reports.export.excel')))['rows'];

    expect($rows[1][2])->toBe("{$reporter->name} (terhapus)")
        ->and($rows[1][12])->toBe('PIC Lama (terhapus)');
});

test('a report without PIC, asset or admin priority exports empty cells', function () {
    Report::factory()->create();

    $rows = workbook($this->actingAs($this->admin)->get(route('admin.reports.export.excel')))['rows'];

    expect($rows[1][8])->toBe('')
        ->and($rows[1][12])->toBe('')
        ->and($rows[1][14])->toBe('Tidak');
});

test('invalid filters are validation errors', function () {
    $this->actingAs($this->admin)->get(route('admin.reports.export.excel', ['status' => 'Nope']))->assertSessionHasErrors('status');
});

test('only admins can export', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.reports.export.excel'))->assertForbidden();
})->with(['reporter', 'pic']);

test('guests are redirected to login', function () {
    $this->get(route('admin.reports.export.excel'))->assertRedirect(route('login'));
});

test('exports are throttled to 10 per minute', function () {
    $this->actingAs($this->admin);

    foreach (range(1, 10) as $i) {
        $this->get(route('admin.reports.export.excel'))->streamedContent();
    }

    $this->get(route('admin.reports.export.excel'))->assertStatus(429);
    $this->get(route('admin.reports.export.pdf'))->assertStatus(429);
});

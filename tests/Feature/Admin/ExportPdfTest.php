<?php

use App\Enums\Priority;
use App\Models\Report;
use App\Models\User;
use App\Support\ReportExport;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('the pdf is a real PDF download', function () {
    Report::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'))->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toMatch('/laporan-siperkasa-\d{8}-\d{4}\.pdf/')
        ->and(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('the pdf view carries the header, filter summary, status counts and rows', function () {
    $html = view('exports.reports', [
        'logo' => 'data:image/png;base64,AAAA',
        'printedAt' => '15 Okt 2026 10:00',
        'filterText' => 'Status: Selesai',
        'summary' => ['Menunggu' => 1, 'Selesai' => 1],
        'rows' => [['ticket' => 'SPK-2610-0001', 'date' => '15 Okt 2026', 'title' => 'AC bocor', 'location' => 'Gedung A, 101', 'status' => 'Selesai', 'pic' => 'Pak Teknisi', 'sla' => '-']],
    ])->render();

    expect($html)->toContain('Laporan Kerusakan Aset')
        ->toContain('UNIVERSITAS ISLAM NEGERI SIBER SYEKH NURJATI CIREBON')
        ->toContain('Status: Selesai')
        ->toContain('Menunggu')
        ->toContain('SPK-2610-0001')
        ->toContain('AC bocor');
});

test('titles are escaped in the pdf view', function () {
    $html = view('exports.reports', [
        'logo' => '', 'printedAt' => 'x', 'filterText' => 'x', 'summary' => [],
        'rows' => [['ticket' => 'T', 'date' => 'd', 'title' => '<script>alert(1)</script>', 'location' => 'l', 'status' => 's', 'pic' => 'p', 'sla' => '-']],
    ])->render();

    expect($html)->not->toContain('<script>alert(1)</script>');
});

test('an empty result still produces a pdf', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'))->assertOk();

    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('the pdf applies the list filters', function () {
    Report::factory()->count(3)->create();
    config(['siperkasa.pdf_max_rows' => 2]);

    // 3 pending reports exceed the limit, a filter that matches none does not
    $this->actingAs($this->admin)->get(route('admin.reports.export.pdf', ['status' => 'Cancelled']))->assertOk();
});

test('more rows than the limit are refused with a clear message', function () {
    config(['siperkasa.pdf_max_rows' => 2]);
    Report::factory()->count(3)->create();

    $this->actingAs($this->admin)->from(route('admin.reports.index'))->get(route('admin.reports.export.pdf'))
        ->assertRedirect(route('admin.reports.index'))
        ->assertSessionHasErrors(['export' => 'Terlalu banyak data (3 baris) untuk PDF. Gunakan Excel atau persempit filter.']);
});

test('exactly the limit is allowed', function () {
    config(['siperkasa.pdf_max_rows' => 3]);
    Report::factory()->count(3)->create();

    $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'))->assertOk();
});

test('the filter text names the active filters', function () {
    expect(ReportExport::filterText([]))->toBe('Semua laporan')
        ->and(ReportExport::filterText(['status' => 'Closed', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->toBe('Status: Selesai · Dari: 2026-10-01 · Sampai: 2026-10-31');
});

test('the filter text shows readable priority and a trashed PIC name', function () {
    $pic = User::factory()->pic()->create(['name' => 'PIC Lama']);
    $pic->delete();

    expect(ReportExport::filterText(['priority' => 'High', 'pic_id' => $pic->id]))
        ->toBe('PIC: PIC Lama · Prioritas: '.Priority::High->label());
});

test('only admins can export a pdf', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.reports.export.pdf'))->assertForbidden();
})->with(['reporter', 'pic']);

test('the over-limit refusal still shows the message when there is no referer', function () {
    config(['siperkasa.pdf_max_rows' => 2]);
    Report::factory()->count(3)->create();

    $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'))
        ->assertRedirect(route('admin.reports.index'))
        ->assertSessionHasErrors(['export' => 'Terlalu banyak data (3 baris) untuk PDF. Gunakan Excel atau persempit filter.']);
});

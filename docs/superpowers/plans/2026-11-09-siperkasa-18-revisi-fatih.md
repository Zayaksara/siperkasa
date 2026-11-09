# SiPerkasa 18 — Revisi Fatih Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cari gedung, cari aset per merek, statistik 5 teratas, filter "Hanya yang menunggu", dan halaman Kinerja PIC (ketepatan vs keterlambatan SLA).

**Architecture:** Pencarian = parameter `q` server-side di controller yang sudah ada. Kinerja PIC = kelas agregasi baru `App\Support\PicPerformance` (satu query `GROUP BY assigned_pic_id`), controller invokable `Admin\PicPerformanceController`, halaman Inertia `admin/pic-performance/Index.vue`.

**Tech Stack:** Laravel, Inertia + Vue 3 + TS, Pest, MySQL, ApexCharts (`@/components/charts/ApexChart.vue`), Wayfinder routes, GSAP.

**Spec:** `docs/superpowers/specs/2026-11-09-siperkasa-18-revisi-fatih-design.md`

## Global Constraints

- Semua agent wajib invoke skill `/caveman` sebelum kerja; laporan terse.
- UI task wajib skill `/frontend-design`, ikut desain awal (palet admin 5 hijau `#149612..#E1FFE0`), komponen shadcn yang ada, target sentuh `min-h-11`, mobile 360px tanpa overflow.
- Animasi hanya GSAP di `onMounted` + `gsap.context`/revert saat unmount, hormati `prefers-reduced-motion`.
- Teks UI bahasa Indonesia. Route helper via Wayfinder (`php artisan wayfinder:generate` bila route baru).
- Tes: `php artisan test --compact <file>`; format: `vendor/bin/pint --dirty`; frontend: `npm run lint` + `npx vue-tsc --noEmit` (atau `npm run types` bila ada).
- Commit konvensional, TANPA trailer Co-Authored-By / atribusi Claude. `git add` hanya file task; JANGAN add `config/siperkasa.php`, `database/seeders/ShowcaseSeeder.php`, `tests/Feature/ShowcaseSeederTest.php`, `tests/Feature/DatabaseSeederTest.php`, `resources/js/components/landing/LandingFooter.vue`, `resources/js/assets/errors/*`, plan 17. Tidak push (controller yang push).
- DB kerja `siperkasa` tidak di-seed/di-migrate fresh.

## Review Focus

- Kata kunci dengan `%`/`_` di cari gedung/merek tidak jadi wildcard (escape seperti `AssetSearchController`). → Task 1.
- Aset tanpa merek (`brand` null) tetap muncul saat cari nama/kode. → Task 1.
- PIC tanpa tugas ber-SLA di rentang: `on_time_rate` = `null` ("—"), bukan 0/100, dan tidak crash bagi-nol. → Task 3.
- Laporan Cancelled/Rejected/Pending/tanpa SLA tidak masuk hitungan; laporan PIC terhapus (soft delete) tetap terhitung, PIC ditandai. → Task 3.
- `overdue=1` di URL laporan admin tetap terlihat (chip) dan bisa dihapus setelah checkbox lama hilang. → Task 2.

---

### Task 1: Cari gedung + cari aset per merek — Pak Budi Haiku

**Files:**
- Modify: `app/Http/Controllers/Admin/BuildingController.php` (`index`)
- Modify: `app/Http/Controllers/Admin/AssetController.php:28-31`
- Modify: `app/Http/Controllers/AssetSearchController.php` (where clause)
- Modify: `resources/js/pages/admin/buildings/Index.vue`, `resources/js/pages/admin/assets/Index.vue` (placeholder)
- Test: `tests/Feature/Admin/BuildingRoomTest.php`, `tests/Feature/Admin/AssetBrowserTest.php`, `tests/Feature/Assets/AssetSearchTest.php`

**Interfaces:**
- Produces: `admin.buildings.index` menerima `?q=` (string, max 100) dan mengirim prop `filters: { q?: string }` di samping `buildings`.

- [ ] **Step 1: Tes gagal**
  - `BuildingRoomTest`: `it('filters buildings by code or name')` — buat gedung `GKB`/"Gedung Kuliah Bersama" dan `REK`/"Rektorat"; `get(route('admin.buildings.index', ['q' => 'kuliah']))` → `->has('buildings', 1)->where('buildings.0.code', 'GKB')->where('filters.q', 'kuliah')`; `q=rek` → hanya `REK` (cocok kode prefix atau nama mengandung); `q='%'` → `has('buildings', 0)`.
  - `AssetBrowserTest`: `it('searches assets by brand')` — aset brand `Epson`, aset lain brand `Canon`, satu brand null; `q=eps` → 1 baris Epson; `q` = nama aset ber-brand null → tetap muncul.
  - `AssetSearchTest`: `it('matches brand')` — `getJson(route(<route pencarian aset yang ada>, ['q' => 'eps']))` → `data` berisi aset Epson.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Admin/BuildingRoomTest.php tests/Feature/Admin/AssetBrowserTest.php tests/Feature/Assets/AssetSearchTest.php` → FAIL di tes baru.
- [ ] **Step 3: Implement.** Gedung: validasi `q` `nullable|string|max:100`, escape `addcslashes($q, '%_\\')`, `where(code like "{$like}%" or name like "%{$like}%")`. Aset (2 controller): tambah `->orWhere('brand', 'like', "%{$like}%")` di dalam closure yang sama. UI gedung: form cari di atas tabel, pola sama dengan `admin/assets/Index.vue` (`Input` h-11, `router.get(index.url(), { q: q || undefined }, { preserveState: true, replace: true })` saat submit), placeholder "Cari kode atau nama gedung", `aria-label="Cari gedung"`; EmptyState khusus bila hasil cari kosong ("Gedung tidak ditemukan"). Placeholder aset → "Cari kode, nama, atau merek aset". Skill `/frontend-design` untuk form cari.
- [ ] **Step 4:** tes Step 2 → PASS; `vendor/bin/pint --dirty`; `npm run lint`.
- [ ] **Step 5: Commit** `feat: search buildings and assets by brand`

### Task 2: Statistik 5 teratas + checkbox "Hanya yang menunggu" — Pak Budi Haiku

**Files:**
- Modify: `app/Support/DashboardStats.php` (`by_category`, `by_building` `limit(10)` → `limit(5)`)
- Modify: `app/Http/Controllers/Admin/DashboardController.php` (cache key `dashboard.admin.v2.` → `dashboard.admin.v3.`)
- Modify: `resources/js/pages/admin/Dashboard.vue` (judul kartu kategori/gedung + "5 teratas")
- Modify: `resources/js/pages/admin/reports/Index.vue:360-367` (checkbox)
- Test: `tests/Feature/Dashboard/AdminDashboardTest.php`, `tests/Feature/Admin/AdminReportListTest.php`

- [ ] **Step 1: Tes gagal** — `it('limits category and building stats to top five')`: 6 kategori & 6 gedung dengan laporan berbeda jumlah → `->has('stats.by_category', 5)->has('stats.by_building', 5)` dan item ke-0 adalah yang terbanyak. Laporan admin: `it('pending filter returns only pending')` dengan `?status=Pending` → semua baris `status === 'Pending'` (backend sudah ada; tes penjaga).
- [ ] **Step 2:** jalankan dua file tes → FAIL di tes top five.
- [ ] **Step 3: Implement.** Backend limit + cache key. Checkbox: `id="f-pending"`, label "Hanya yang menunggu", `:model-value="f.status === 'Pending'"`, `@update:model-value="f.status = $event === true ? 'Pending' : ''"`. Hapus `overdue` dari checkbox; `f.overdue` tetap dikirim apa adanya. Bila `f.overdue` true, tampilkan chip kecil "Hanya terlambat" + tombol hapus (`aria-label="Hapus filter terlambat"`, min-h-11) yang men-set `f.overdue = false` lalu submit. Skill `/frontend-design`.
- [ ] **Step 4:** tes → PASS; pint; lint; `npx vue-tsc --noEmit`.
- [ ] **Step 5: Commit** `feat: show top five stats and pending filter shortcut`

### Task 3: Agregasi Kinerja PIC (backend) — Pak Joko Sonnet

**Files:**
- Create: `app/Support/PicPerformance.php`
- Create: `app/Http/Controllers/Admin/PicPerformanceController.php`
- Modify: `routes/web.php` (grup admin: `Route::get('pic-performance', PicPerformanceController::class)->name('pic-performance');`)
- Test: `tests/Feature/Admin/PicPerformanceTest.php`

**Interfaces:**
- Produces: `PicPerformance::forRange(CarbonInterface $from, CarbonInterface $to): array` → `['summary' => {total:int, on_time:int, late_closed:int, late_open:int, on_track:int, on_time_rate:?float}, 'pics' => list<{id:int, name:string, trashed:bool, active:bool, total:int, on_time:int, late_closed:int, late_open:int, on_track:int, on_time_rate:?float, avg_late_hours:?float}>]`.
- Inertia `admin/pic-performance/Index` props: `performance` (bentuk di atas), `range: {from: 'Y-m-d', to: 'Y-m-d'}`.

Aturan (dari spec, verbatim):
- Hanya laporan `assigned_pic_id` & `sla_date` not null, status bukan `Cancelled`/`Rejected`/`Pending`, `validated_at` dalam `[from 00:00, to 23:59:59]`.
- on_time: `Closed` & `closed_at <= sla_date`; late_closed: `Closed` & `closed_at > sla_date`; late_open: belum final & `sla_date < now()` (On_Hold termasuk); on_track: belum final & `sla_date >= now()`.
- `on_time_rate` = round(on_time / (on_time + late_closed + late_open) * 100, 1), `null` bila penyebut 0. `avg_late_hours` = rata `TIMESTAMPDIFF(SECOND, sla_date, closed_at)/3600` untuk late_closed, 1 desimal, null bila tidak ada.
- Laporan dihitung ke `assigned_pic_id` sekarang (PIC terakhir). PIC termasuk `withTrashed()`; `pics` hanya PIC yang punya ≥1 laporan di rentang; urut `on_time_rate` naik (null terakhir), lalu nama.
- Satu query agregasi `SUM(CASE …)` `GROUP BY assigned_pic_id`; tanpa N+1.
- Controller: validasi `from`/`to` sama seperti `Admin\DashboardController` (default 30 hari terakhir, maks 366 hari, pesan "Rentang tanggal maksimal 1 tahun."), `Gate`/middleware `role:admin` lewat grup route.

- [ ] **Step 1: Tes gagal** (pakai `Date::setTestNow`/`travelTo`):
  - `it('classifies tasks per pic')`: PIC A: 1 on_time, 1 late_closed (telat 5 jam), 1 late_open (On_Hold lewat SLA), 1 on_track → `performance.pics.0` A: `total 4, on_time 1, late_closed 1, late_open 1, on_track 1, on_time_rate 33.3, avg_late_hours 5.0`.
  - `it('excludes cancelled rejected pending and reports without sla')` → total tidak bertambah.
  - `it('returns null rate when pic has only on track tasks')` → `on_time_rate null`.
  - `it('filters by validated_at range')` → laporan validated di luar rentang tidak dihitung.
  - `it('includes trashed pic and marks it')` → `trashed true`.
  - `it('sorts worst rate first')`.
  - `it('rejects ranges over a year')` → `assertSessionHasErrors('to')`.
  - `it('forbids non admin')` → PIC & user dapat 403.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Admin/PicPerformanceTest.php` → FAIL.
- [ ] **Step 3:** Implement kelas + controller + route; `php artisan wayfinder:generate` (bila proyek memakainya lewat vite plugin, cukup pastikan `@/routes/admin` punya `picPerformance`). Halaman Vue placeholder minimal `admin/pic-performance/Index.vue` (Head + PageHeader) supaya Inertia component ada; UI penuh di Task 4.
- [ ] **Step 4:** tes → PASS; `php artisan test --compact tests/Feature/Dashboard tests/Feature/Admin` tetap hijau; pint.
- [ ] **Step 5: Commit** `feat: compute pic on-time performance`

### Task 4: Halaman Kinerja PIC (UI) — Pak Joko Sonnet

**Files:**
- Modify: `resources/js/pages/admin/pic-performance/Index.vue`
- Modify: `resources/js/composables/useRoleNav.ts` (adminNav, grup Operasional setelah "Laporan": `{ title: 'Kinerja PIC', href: picPerformance(), icon: Gauge }`)
- Modify: `resources/js/pages/admin/Dashboard.vue` (kartu "Beban PIC": link "Lihat kinerja PIC")
- Test: `tests/Feature/Admin/PicPerformanceTest.php` (render component `admin/pic-performance/Index` + props `range`)

**Interfaces:**
- Consumes: props dari Task 3; drill-down `admin.reports.index` dengan `{ pic_id, overdue: 1 }`.

- [ ] **Step 1:** Wajib invoke `/frontend-design` + `gsap-core`/`gsap-frameworks`. Baca `admin/Dashboard.vue` untuk pola kartu, `ApexChart`, `ChartLegendList`, form rentang tanggal.
- [ ] **Step 2: Implement** isi halaman:
  - PageHeader "Kinerja PIC", deskripsi "Ketepatan PIC menyelesaikan tugas sesuai batas SLA."
  - Form rentang tanggal (`from`/`to`, sama seperti dashboard) + catatan kecil: "Rentang memakai tanggal validasi. Status terlambat dinilai per hari ini. Laporan dibatalkan/ditolak tidak dihitung."
  - 4 kartu ringkasan: Total tugas, % tepat waktu (atau "—"), Terlambat selesai, Masih terlambat.
  - Stacked bar horizontal per PIC (Tepat waktu / Terlambat selesai / Masih terlambat / Berjalan), warna dari palet hijau + satu warna peringatan yang sudah dipakai untuk terlambat di app; legenda `ChartLegendList`.
  - Tabel: PIC (badge "nonaktif"/"terhapus"), Total, Tepat, Terlambat selesai, Masih terlambat (link drill-down bila > 0), Berjalan, % tepat waktu, Rata telat (jam). Sortir per kolom di client (`button` header, `aria-sort`). Default urutan dari server. Mobile: tabel bisa scroll horizontal di wadahnya sendiri, halaman tidak overflow; atau kartu per PIC.
  - EmptyState bila `pics` kosong.
  - GSAP: kartu & baris fade/rise stagger ringan saat mount; dimatikan bila reduced-motion; `ctx.revert()` saat unmount.
- [ ] **Step 3:** Tes render: `->component('admin/pic-performance/Index')->has('performance.pics')->where('range.from', …)` → PASS.
- [ ] **Step 4:** `npm run lint`, `npx vue-tsc --noEmit`, `npm run build` sukses (hapus `public/hot` basi bila mengganggu? JANGAN hapus — laporkan saja).
- [ ] **Step 5: Commit** `feat: add pic performance page`

### Task 5 (controller): ukur latensi notifikasi + review akhir

- [ ] Ukur waktu respons `GET /notifications/unread-count` di server lokal (login admin, 20 request, catat median/p95) — catat di 06-Progress.
- [ ] Review akhir seluruh cabang oleh Pak Hendra Opus.
- [ ] Push `feat/siperkasa-v1` (instruksi Fatih 2026-11-09).

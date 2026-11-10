# SiPerkasa 19 — Integrasi API SiberMap (Gedung & Ruangan) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Master gedung & ruangan SiPerkasa diambil dari API publik SiberMap V2 (UIN Siber Syekh Nurjati), lengkap dengan hierarki Kampus → Gedung → Lantai → Ruangan.

**Sumber:** `docs/Dokumentasi API SiberMap V2.pdf`. Base URL `http://103.140.22.112/api`, tanpa auth, GET, respons `{"success": true, "data": ...}`. Data live 2026-11-10: 5 kampus, ~50 gedung, 456 ruangan (234 ruangan `name` = null → pakai `room_number`).

**Keputusan Fatih (2026-11-10):**
1. Sync ke DB lokal (bukan live fetch). `reports.room_id` tetap FK lokal; aplikasi jalan walau API mati.
2. Data lokal lama: yang tidak dipakai laporan dihapus; yang dipakai laporan tetap dipakai.
3. Baris hasil sync **read-only** di admin (edit/hapus dikunci, badge "SiberMap").
4. Tampilkan kampus: kolom kampus di gedung; form laporan jadi Kampus → Gedung → Ruangan; filter kampus di admin.

**Architecture:**
- Migration alter baru (DB kerja sudah dipakai, JANGAN ubah migration lama): `buildings` + `campus_name` (string null), `sibermap_id` (unsignedBigInteger null unique), `synced_at` (timestamp null). `rooms` + `sibermap_id` (unsignedBigInteger null unique), `floor_name` (string null), `synced_at` (timestamp null).
- Config baru `config/sibermap.php` (bukan `config/siperkasa.php`, file itu dirty milik Fatih): `base_url` = `env('SIBERMAP_URL', 'http://103.140.22.112/api')`, `timeout` = `(int) env('SIBERMAP_TIMEOUT', 15)`. Tambah `SIBERMAP_URL=` ke `.env.example`.
- `App\Support\SiberMap\SiberMapClient`: `buildings(): array`, `rooms(): array`. `Http::baseUrl(config)->timeout()->retry(2, 500)->acceptJson()`, lempar `App\Support\SiberMap\SiberMapException` bila HTTP gagal, `success !== true`, atau `data` bukan array.
- `App\Support\SiberMap\SiberMapSync::run(bool $pruneManual = false): SyncResult` (readonly DTO: `buildingsUpserted`, `roomsUpserted`, `removed`, `keptInUse`, `prunedManual`). Ambil kedua endpoint DULU (gagal → exception, DB tidak disentuh), lalu semua tulis dalam `DB::transaction`.
- Artisan `sibermap:sync {--prune-manual}`; jadwal harian 04:00 tanpa `--prune-manual`.
- Admin: `POST admin/sibermap/sync` (throttle 3/menit) → jalankan sync, toast hasil. Gedung/ruang hasil sync dikunci lewat policy.
- UI: halaman admin Gedung (kolom/filter kampus, badge, tombol sinkron, waktu sinkron terakhir), detail Gedung (kolom lantai, badge, aksi dikunci), form laporan cascade Kampus → Gedung → Ruangan dengan pencarian.

**Aturan pemetaan (WAJIB persis):**
- Gedung API `{id, location_id, name, location:{id,name}}` → `buildings`: `sibermap_id = id`, `code = "SM-{id}"`, `name = trim(name)`, `campus_name = trim(location.name)` (null bila location null), `synced_at = now()`. Upsert by `sibermap_id`.
- Ruangan API `{id, floor_id, room_number, name, floor:{id, building_id, name, building:{...location}}}` → `rooms`: `sibermap_id = id`, `code = "SM-{id}"`, `name = trim(name)` bila tidak kosong, selain itu `trim(room_number)`, selain itu `"Ruang {id}"`; `floor_name = trim(floor.name)` atau null; `floor` = angka pertama dari regex `/(?:lt|lantai)\.?\s*(\d+)/i` pada `floor.name`, selain itu null; `building_id` = id lokal gedung ber-`sibermap_id = floor.building_id`. Bila gedung itu tidak ada di daftar `/gedung`, buat dari `floor.building` (aturan gedung di atas). Ruangan tanpa `floor`/`floor.building_id` dilewati (hitung `skipped`).
- Baris lokal ber-`sibermap_id` yang tidak ada lagi di API: hapus bila tidak dipakai (`isInUse()` false); bila dipakai laporan → biarkan (hitung `keptInUse`). Ruangan dulu, lalu gedung.
- `--prune-manual` / sekali jalan: baris `sibermap_id IS NULL` — hapus ruangan tanpa laporan, lalu hapus gedung manual yang tidak punya ruangan lagi. Yang dipakai laporan tetap.
- Sync tidak pernah menyentuh baris manual tanpa `--prune-manual`.

**Tech Stack:** Laravel 12, Inertia + Vue 3 + TS, Pest, MySQL, Wayfinder, shadcn-vue, GSAP.

## Global Constraints

- Semua agent wajib invoke skill `/caveman` sebelum kerja; laporan terse.
- UI task wajib skill `/frontend-design`, ikut desain awal (palet admin 5 hijau `#149612..#E1FFE0`), komponen shadcn yang ada, target sentuh `min-h-11`, mobile 360px tanpa overflow.
- Animasi hanya GSAP di `onMounted` + `gsap.context`/revert saat unmount, hormati `prefers-reduced-motion`.
- Teks UI bahasa Indonesia. Route helper via Wayfinder (`php artisan wayfinder:generate` bila route baru).
- Tes: `php artisan test --compact <file>`; semua tes HTTP SiberMap pakai `Http::fake()` + `Http::preventStrayRequests()` (tes TIDAK boleh memanggil IP asli). Format: `vendor/bin/pint --dirty`; frontend: `npx vp check` + `npx vue-tsc --noEmit`.
- Commit konvensional, TANPA trailer Co-Authored-By / atribusi Claude. `git add` hanya file task; JANGAN add `config/siperkasa.php`, `database/seeders/ShowcaseSeeder.php`, `tests/Feature/ShowcaseSeederTest.php`, `resources/js/components/landing/LandingFooter.vue`, `resources/js/assets/errors/*`, `docs/Dokumentasi API SiberMap V2.pdf`, plan 17. Tidak push (Fatih yang push).
- DB kerja `siperkasa` TIDAK di-seed/migrate fresh dan TIDAK di-sync oleh agent (controller yang menjalankan `migrate` + sync setelah review akhir).

## Review Focus

- API gagal / `success:false` / timeout → tidak ada baris terhapus atau berubah. → Task 1.
- Ruangan/gedung SiberMap yang hilang dari API tapi dipakai laporan TIDAK terhapus (FK `restrictOnDelete`). → Task 1.
- Sync ulang idempoten (jalan 2× = jumlah baris sama, tanpa duplikat `code`). → Task 1.
- Baris manual tak tersentuh tanpa `--prune-manual`. → Task 1.
- Policy: update/delete gedung & ruang ber-`sibermap_id` ditolak 403 walau request langsung. → Task 2.
- Form laporan: ruangan lama (manual, dipakai laporan) tetap bisa dipilih saat edit laporan. → Task 4.

---

### Task 1: Skema + klien + sync + command — Pak Joko Sonnet

**Files:**
- Create: `database/migrations/2026_11_10_090000_add_sibermap_columns_to_buildings_and_rooms.php`
- Create: `config/sibermap.php`; Modify: `.env.example`
- Create: `app/Support/SiberMap/SiberMapClient.php`, `SiberMapException.php`, `SiberMapSync.php`, `SyncResult.php`
- Create: `app/Console/Commands/SyncSiberMap.php` (signature `sibermap:sync {--prune-manual}`)
- Modify: `routes/console.php` (`Schedule::command('sibermap:sync')->dailyAt('04:00')->withoutOverlapping();`)
- Modify: `app/Models/Building.php`, `app/Models/Room.php` (fillable, casts `synced_at` datetime, `isSynced(): bool` = `sibermap_id !== null`, docblock props)
- Modify: `database/factories/BuildingFactory.php`, `RoomFactory.php` bila ada: state `synced()`
- Test: `tests/Feature/SiberMap/SiberMapSyncTest.php`

- [ ] Tulis tes dulu (Pest, `RefreshDatabase`, `Http::preventStrayRequests()`, `Http::fake([config base_url.'/gedung' => ..., '/ruangan' => ...])`), minimal kasus:
  1. Sync awal: 2 gedung (beda kampus) + 3 ruangan → `code` `SM-{id}`, `campus_name`, `floor_name`, `floor` hasil regex ("Lt 1 Pascasarjana" → 1, "Lantai 3" → 3, "Basement" → null), nama ruangan fallback `room_number` saat `name` null, fallback `"Ruang {id}"` saat keduanya kosong.
  2. Idempoten: jalan 2× → jumlah sama, nama terbaru dipakai (ubah nama di fake ke-2 → terupdate).
  3. Gedung hanya muncul di nested `floor.building` → dibuat.
  4. Ruangan hilang dari API: tak dipakai → terhapus; dipakai laporan → tetap, `keptInUse` = 1.
  5. API gagal (HTTP 500 / `success:false`) → `SiberMapException`, DB tak berubah.
  6. Baris manual tetap tanpa flag; dengan `pruneManual: true` → ruangan manual tanpa laporan & gedung manual kosong terhapus, yang dipakai laporan tetap.
  7. Command `sibermap:sync` exit 0 + cetak ringkasan; saat API gagal exit 1 + pesan error.
- [ ] Jalankan tes → gagal. Implementasi sesuai "Aturan pemetaan". Jalankan tes → lulus. Jalankan juga `tests/Feature/Admin/BuildingRoomTest.php` (tak boleh regresi).
- [ ] `vendor/bin/pint --dirty`. Commit `feat: sync buildings and rooms from sibermap api`.

### Task 2: Kunci policy + endpoint sinkron admin + props — Pak Budi Haiku

**Files:**
- Modify: `app/Policies/BuildingPolicy.php`, `app/Policies/RoomPolicy.php`: `update`/`delete` return false bila `isSynced()`.
- Create: `app/Http/Controllers/Admin/SiberMapSyncController.php` (invokable; `Gate::authorize('create', Building::class)`; panggil `SiberMapSync::run()`; sukses → toast success "Sinkron SiberMap selesai: {b} gedung, {r} ruang."; `SiberMapException` → toast error "Gagal menghubungi SiberMap. Coba lagi nanti."; `back()`).
- Modify: route admin (file route admin yang dipakai `admin.buildings.*`): `Route::post('sibermap/sync', SiberMapSyncController::class)->middleware('throttle:3,1')->name('sibermap.sync')` di grup admin → nama `admin.sibermap.sync`. Lalu `php artisan wayfinder:generate`.
- Modify: `app/Http/Controllers/Admin/BuildingController.php`: `index` validasi `campus` (nullable string max 100), filter `campus_name = campus`; select tambah `campus_name`, `sibermap_id`; props tambah `campuses` (distinct `campus_name` non-null, urut), `lastSyncedAt` (`max(synced_at)` ISO atau null), filters ikut `campus`. `q` juga cocokkan `campus_name` (LIKE escaped seperti yang ada). `show`: building + `campus_name`, `sibermap_id`; rooms + `floor_name`, `sibermap_id`.
- Modify: `app/Http/Controllers/User/ReportController.php::formOptions()`: tiap building + `campus` (`campus_name` ?? "Lainnya"), tiap room + `floor_name`; urutan gedung `campus_name` lalu `name`, ruangan `floor` lalu `name`.
- Modify: `app/Http/Controllers/Admin/ReportController.php:51`: buildings tambah `campus_name`, urut `campus_name, name`.
- Test: `tests/Feature/Admin/BuildingRoomTest.php` (tambah: update/delete gedung & ruang synced → 403; manual tetap bisa; filter `campus`; props `campuses`, `lastSyncedAt`), `tests/Feature/SiberMap/SiberMapSyncEndpointTest.php` (admin POST sukses + toast; API gagal → toast error, DB tetap; non-admin 403; `Http::fake` + `preventStrayRequests`).
- [ ] TDD seperti Task 1. Jalankan tes report form yang ada (`tests/Feature/Reports/*` yang menyentuh `buildings`) → lulus. Pint. Commit `feat: lock synced locations and add sibermap sync action`.

### Task 3: UI admin Gedung & detail Gedung — Pak Joko Sonnet

**Files:** `resources/js/pages/admin/buildings/Index.vue`, `resources/js/pages/admin/buildings/Show.vue` (+ komponen kecil baru bila perlu di `resources/js/components/admin/`).

- [ ] Invoke `/frontend-design` + `/gsap-frameworks`. Ikut gaya halaman admin yang ada.
- [ ] Index: kolom "Kampus"; select filter kampus (opsi dari `campuses`, "Semua kampus") bersama pencarian `q` yang ada (router GET, preserveState); badge "SiberMap" (hijau palet admin) pada baris synced; tombol "Sinkron SiberMap" (POST `admin.sibermap.sync`, state loading, disabled saat proses) + teks "Terakhir sinkron: {tanggal id-ID}" atau "Belum pernah sinkron"; aksi edit/hapus disembunyikan untuk baris synced (ganti ikon gembok + tooltip "Dikelola SiberMap"). Mobile 360px: tabel jadi kartu/scroll sesuai pola yang ada, tanpa overflow halaman.
- [ ] Show: kampus di header; kolom "Lantai" tampil `floor_name ?? floor`; badge SiberMap; aksi ruang synced dikunci sama seperti di atas; form tambah ruang manual tetap ada.
- [ ] GSAP: stagger masuk baris/kartu ringan (fade+y), `gsap.context` revert, reduced-motion → tanpa animasi.
- [ ] `npx vp check`, `npx vue-tsc --noEmit`, `npm run build`. Commit `feat: show campus and sibermap sync on building pages`.

### Task 4: Form laporan Kampus → Gedung → Ruangan — Pak Joko Sonnet

**Files:** `resources/js/components/ReportForm.vue` (+ `resources/js/pages/reports/Create.vue`, `Edit.vue` type prop), komponen picker baru bila perlu di `resources/js/components/`.

- [ ] Invoke `/frontend-design` + `/gsap-frameworks`.
- [ ] Tiga pilihan berurutan: Kampus (dari `buildings[].campus` unik) → Gedung (terfilter kampus) → Ruangan (terfilter gedung, bisa dicari ketik, label `name` + `floor_name` sebagai keterangan, dikelompokkan per lantai bila ada). Ganti kampus mengosongkan gedung & ruang; ganti gedung mengosongkan ruang. Field yang dikirim tetap hanya `room_id` (dan field lama yang sudah ada) — kontrak backend tidak berubah.
- [ ] Edit laporan: nilai awal `room_id` → kampus & gedung terisi otomatis, termasuk ruangan manual lama.
- [ ] 456 ruangan: pencarian client-side tanpa lag; target sentuh `min-h-11`; 360px tanpa overflow.
- [ ] Tes: jalankan tes feature report create/edit yang ada → lulus. `npx vp check`, `npx vue-tsc --noEmit`, `npm run build`. Commit `feat: pick report location by campus building and room`.

### Akhir

- Review akhir cabang — Pak Hendra Opus.
- Controller: `php artisan migrate` di DB kerja, lalu `php artisan sibermap:sync --prune-manual` (keputusan Fatih #2), catat hasil di 06-Progress.
- Cek manual Fatih (URL + apa yang dicek) dicatat di 06-Progress.

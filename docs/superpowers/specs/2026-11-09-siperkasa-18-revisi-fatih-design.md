# SiPerkasa 18 — Revisi Fatih (cari gedung, merek aset, top 5, kinerja PIC, filter menunggu)

Tanggal: 2026-11-09. Sumber: permintaan Fatih (item 1, 2, 4, 5, 8). Item 3 batal, item 6 sudah ada (donut status di dashboard admin), item 7 dan 9 hanya jawaban (lihat bagian akhir).

## Intent

Admin (pengelola sarpras/BMN) butuh menemukan data lebih cepat dan menilai PIC secara objektif. Di data Fatih banyak tugas PIC terlambat, tapi tidak ada tempat yang menyaring atau mengukur keterlambatan per PIC.

## Goals

1. **Cari gedung** — halaman `admin/buildings` dapat kotak cari (kode atau nama gedung), perilaku sama seperti cari aset di `admin/assets` (query `q`, server-side, `withQueryString`, debounce seperti halaman aset).
2. **Cari aset berdasarkan merek** — kolom `brand` ikut dicari (`like %q%`) di:
   - `Admin\AssetController@index` (daftar aset admin), placeholder jadi "Cari kode, nama, atau merek aset".
   - `AssetSearchController` (pilih aset saat membuat laporan).
3. **Statistik 5 teratas** — `by_category` dan `by_building` di `DashboardStats::forAdmin` jadi `limit(5)`; judul kartu di dashboard admin menyebut "5 teratas". Cache key dashboard dinaikkan versinya (`v3`) supaya cache lama tidak terpakai.
4. **Kinerja PIC** — halaman baru khusus admin `GET /admin/pic-performance` (`admin.pic-performance`), menu di grup Operasional sidebar admin ("Kinerja PIC").
   - Filter rentang tanggal `from`/`to` (default 30 hari terakhir, maks 1 tahun, sama seperti dashboard). Rentang diterapkan ke `validated_at` (saat tugas diberikan ke PIC).
   - Definisi per laporan ber-PIC dan ber-SLA:
     - **Tepat waktu**: `status = Closed` dan `closed_at <= sla_date`.
     - **Terlambat selesai**: `status = Closed` dan `closed_at > sla_date`.
     - **Terlambat berjalan**: belum final dan `sla_date < now()` (sama dengan scope `overdue`).
     - **Berjalan (on track)**: belum final dan `sla_date >= now()`.
   - Keputusan Fatih: laporan reassign dihitung ke **PIC terakhir** (`assigned_pic_id` saat ini). Laporan Ditunda (On_Hold) lewat SLA **tetap terlambat berjalan**.
   - Keputusan controller: laporan `Cancelled`/`Rejected` dikeluarkan dari semua hitungan (catatan kecil di UI). Hanya laporan dengan `assigned_pic_id` dan `sla_date` tidak null. Status "terlambat berjalan/berjalan" dinilai per **hari ini** (`now()`), bukan per akhir rentang; tulis keterangan ini di UI. Total tugas = jumlah empat kelompok.
   - Per PIC (termasuk PIC nonaktif/terhapus yang punya data di rentang, ditandai): total tugas, tepat waktu, terlambat selesai, terlambat berjalan, berjalan, **% tepat waktu** = tepat / (tepat + terlambat selesai + terlambat berjalan), rata-rata keterlambatan (jam) untuk yang terlambat selesai.
   - Ringkasan atas: total tugas, % tepat waktu keseluruhan, jumlah terlambat (selesai + berjalan).
   - Grafik: stacked bar horizontal per PIC (tepat / terlambat selesai / terlambat berjalan / berjalan) memakai `ApexChart` + `ChartLegendList` yang sudah ada.
   - Tabel diurutkan % tepat waktu terendah dulu (yang paling butuh perhatian di atas); bisa disortir per kolom di client.
   - Angka "terlambat berjalan" per PIC menjadi link ke `admin/reports?pic_id=X&overdue=1` (drill-down, filter `overdue` tetap ada di backend).
   - Kartu "Beban PIC" di dashboard admin dapat link "Lihat kinerja PIC".
5. **Filter "menunggu"** — checkbox `f-overdue` "Hanya yang terlambat" di `admin/reports/Index.vue` diganti checkbox "Hanya yang menunggu" yang menyaring `status = Pending`.
   - Implementasi: checkbox men-set/menghapus `status=Pending` pada filter (tanpa parameter baru); tercentang bila `f.status === 'Pending'`, tetap sinkron dengan dropdown status (pilih status lain di dropdown = checkbox otomatis tidak tercentang). Kombinasi `overdue=1` + `status=Pending` tetap valid walau hasilnya kosong.
   - Keputusan controller untuk item 3 (top 5): cukup ubah `limit` + judul kartu; tes dashboard yang mengandalkan 10 baris disesuaikan.
   - Parameter `overdue` di `ReportFilter` tidak dihapus (dipakai drill-down dashboard, link kinerja PIC, dan ekspor). Bila URL membawa `overdue=1`, tampilkan chip/label kecil "Hanya terlambat" yang bisa dihapus, supaya filter tidak tersembunyi.

## Non-goals

- Websocket/real-time push (tetap polling 60 detik).
- Admin per fakultas / pembatasan data per gedung.
- Pengingat laporan Pending, perubahan SLA setelah validasi.
- Ekspor Excel/PDF untuk kinerja PIC (bisa task terpisah).
- Mengubah DB kerja `siperkasa` atau file F22 yang belum di-commit (`config/siperkasa.php`, `ShowcaseSeeder`, `ShowcaseSeederTest`, `DatabaseSeederTest.php` terhapus) — jangan ikut di-commit.

## Anti-goals

- Persentase menyesatkan: PIC tanpa tugas ber-SLA tampil "—", bukan 0% atau 100%.
- Query N+1 per PIC: agregasi satu query `GROUP BY assigned_pic_id` dengan `SUM(CASE …)`.

## Constraints

- Laravel + Inertia Vue, MySQL (pakai `TIMESTAMPDIFF` seperti `DashboardStats`). Tes Pest feature untuk tiap goal (TDD).
- UI: wajib skill `/frontend-design`, ikut kesepakatan desain awal (`@design`, palet admin 5 hijau `#149612..#E1FFE0`), komponen shadcn yang sudah ada, target sentuh `min-h-11`, mobile 360px tanpa overflow.
- Animasi (bila ada) lewat GSAP di `onMounted` + context, hormati `prefers-reduced-motion`.
- Teks UI bahasa Indonesia. Route helper via Wayfinder (`@/routes/...`).
- Commit pesan konvensional, tanpa atribusi Claude. Tidak push.

## Dibiarkan ke builder

- Tata letak persis halaman kinerja PIC (kartu ringkasan + grafik + tabel) dalam batas desain yang ada.
- Nama class/method (mis. `App\Support\PicPerformance`).

## Jawaban non-kode (untuk catatan)

- **Item 7**: admin yang disarankan = unit pengelola BMN/sarpras pusat; admin fakultas butuh fitur pembatasan data (task baru bila mau).
- **Item 9**: notifikasi lewat polling `POLL_MS = 60_000` (0–60 dtk, rata ~30 dtk), berhenti setelah 30 menit idle. Latensi endpoint `notifications/unread-count` diukur di lokal saat pengerjaan dan dicatat di 06-Progress.

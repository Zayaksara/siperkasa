# Panduan Deploy SiPerkasa di cPanel

Panduan ini untuk orang non-developer. Ikuti urutan nomor. Ganti `<user>` dengan nama akun cPanel Anda dan `siperkasa.example.ac.id` dengan domain asli.

Catatan: dokumen ini hanya persiapan. Belum ada yang di-deploy.

## 1. Kebutuhan

- PHP 8.4 atau lebih baru (cPanel: MultiPHP Manager). Ekstensi aktif: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `dom`, `fileinfo`, `openssl`.
- Fungsi `proc_open` harus aktif. Fungsi ini dipakai oleh `schedule:run`, pekerjaan latar belakang, dan `mysqldump`. Cek di terminal:

  ```bash
  php -r "var_dump(function_exists('proc_open'));"
  ```

  Hasil harus `bool(true)`. Jika `false`, minta hosting mengaktifkan `proc_open` (hapus dari `disable_functions`).
- Cron dengan interval 1 menit. Beberapa hosting hanya mengizinkan 5 atau 15 menit. Jika begitu, pakai interval terkecil yang diizinkan. Akibatnya impor aset dan ekspor berjalan lebih lambat (tertunda sampai 5 atau 15 menit). Tidak ada data yang hilang.
- MySQL 8.
- Akses SSH (disarankan) atau Terminal di cPanel.
- Composer terpasang di server. Jika tidak ada, minta hosting atau pakai `composer.phar`.

## 2. Tata letak folder

- Aplikasi ada di `/home/<user>/siperkasa`, di luar `public_html`.
- Arahkan Document Root domain ke `/home/<user>/siperkasa/public` (cPanel: Domains, Manage, Document Root). Jika tidak bisa, hapus folder `public_html` lama (setelah dicadangkan) dan buat symlink:

  ```bash
  ln -s /home/<user>/siperkasa/public /home/<user>/public_html
  ```
- Jangan jalankan `php artisan storage:link`. Foto laporan bersifat privat dan hanya ditampilkan lewat pengecekan hak akses aplikasi.

## 3. Deploy pertama

1. Ambil kode. Pilih salah satu:
   - cPanel, Git Version Control: buat repository ke `/home/<user>/siperkasa`, lalu Deploy.
   - Atau upload zip kode ke `/home/<user>/siperkasa` dan ekstrak.
2. Masuk ke folder aplikasi dan pasang library PHP:

   ```bash
   cd /home/<user>/siperkasa
   composer install --no-dev -o
   ```
3. Build aset di komputer sendiri (Node biasanya tidak ada di cPanel):

   ```bash
   npm ci && npm run build
   ```

   Upload folder `public/build` hasilnya ke `/home/<user>/siperkasa/public/build`.
4. Buat file `.env`:

   ```bash
   cp .env.production.example .env
   ```

   Edit `.env`: isi `APP_URL`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, pengaturan `MAIL_*`, `BACKUP_NOTIFY_MAIL` (WAJIB, email admin yang membaca kabar backup gagal), dan `SUPER_ADMIN_PASSWORD` (password panjang, minimal 12 karakter, huruf besar, kecil, angka, simbol). Lalu:

   ```bash
   php artisan key:generate --force
   ```
5. Buat tabel:

   ```bash
   php artisan migrate --force
   ```
6. Isi data awal (admin, kategori, gedung). Jalankan hanya seeder yang aman dengan `--class`:

   ```bash
   php artisan db:seed --class=DatabaseSeeder --force
   ```

   Seeder demo (`DemoReportSeeder`) tidak boleh dijalankan di production. `DatabaseSeeder` hanya membuat data demo jika `APP_ENV=local`. Jangan pernah mengubah `APP_ENV` ke `local` di server. Jangan menjalankan `--class=DemoReportSeeder`.
7. Impor daftar aset BMN satu kali (upload file `.xlsx` dulu, misalnya ke `/home/<user>/bmn.xlsx`):

   ```bash
   php artisan assets:import /home/<user>/bmn.xlsx
   ```
8. Cache konfigurasi:

   ```bash
   php artisan optimize
   ```
9. Pasang cron. cPanel, Cron Jobs, interval "Once Per Minute" (`* * * * *`), perintah:

   ```
   * * * * * cd /home/<user>/siperkasa && php artisan schedule:run >> /dev/null 2>&1
   ```

   Ini satu-satunya cron yang dibutuhkan. Jika `php` tidak ketemu, pakai path lengkap, misalnya `/usr/local/bin/ea-php84`.
10. Buka `https://siperkasa.example.ac.id` dan login sebagai admin (NIP dari `SUPER_ADMIN_NIP`, password dari `SUPER_ADMIN_PASSWORD`).

## 4. php.ini (cPanel, MultiPHP INI Editor)

Atur nilai berikut untuk domain aplikasi:

| Pengaturan | Nilai |
|---|---|
| `upload_max_filesize` | `4M` |
| `post_max_size` | `16M` |
| `memory_limit` | `256M` atau lebih |
| `max_execution_time` | `120` atau lebih |

Ekspor PDF sampai 500 baris memakai sekitar 192 MB memori dan sekitar 6 detik. Dengan nilai bawaan hosting (128 MB, 30 detik) ekspor PDF akan gagal.

## 5. HTTPS

- Aktifkan AutoSSL (cPanel, SSL/TLS Status) untuk domain.
- Di `.env`: `APP_URL=https://...` (passkey hanya jalan di HTTPS) dan `SESSION_SECURE_COOKIE=true`.
- `TRUSTED_PROXIES` (daftar IP "perantara" yang boleh dipercaya, misalnya layanan CDN): biarkan kosong jika situs langsung diakses tanpa perantara. Isi hanya dengan IP perantara yang sebenarnya, dipisah koma.
- Peringatan: jangan isi `*` kecuali server hanya bisa dijangkau lewat perantara. Jika tidak, siapa pun bisa memalsukan alamat IP dan skema (http/https) pengunjung.
- Setelah mengubah `.env`, jalankan `php artisan optimize` lagi.

## 6. Pekerjaan latar belakang (antrean)

- Cron di langkah 3.9 menjalankan `schedule:run` tiap menit. Perintah itu menjalankan `queue:work --stop-when-empty` selama maksimal 14 menit, dan juga tugas harian (backup, bersih-bersih).
- Yang memakai antrean: impor aset BMN dari layar admin, dan notifikasi.
- Cek antrean sehat:
  - Tabel `jobs` kosong (phpMyAdmin) atau hanya berisi pekerjaan yang baru masuk.
  - Tidak ada pekerjaan gagal: `php artisan queue:failed`
- Jalankan manual satu pekerjaan yang macet:

  ```bash
  php artisan queue:work --once
  ```
- Ulangi pekerjaan gagal: `php artisan queue:retry all`.
- Pekerjaan gagal dihapus otomatis setelah 30 hari. Notifikasi yang sudah dibaca dihapus otomatis setelah 180 hari. Notifikasi yang belum dibaca tidak pernah dihapus.

## 7. Backup dan restore

Backup otomatis jalan lewat cron yang sama:

| Jam | Tugas |
|---|---|
| 01:00 | `backup:clean` hapus backup lama |
| 01:30 | `backup:run` buat backup baru (database + foto) |
| 03:00 | `backup:monitor` cek backup masih segar, kirim email jika tidak sehat |

Isi backup: dump database (`db-dumps/`) dan folder `storage/app/private` (foto laporan). Kode tidak ikut karena bisa diambil dari git. Hasil disimpan di `storage/app/backups` (privat, tidak bisa diakses dari web).

Retensi: semua backup 3 hari terakhir, satu per hari untuk 14 hari, satu per minggu untuk 8 minggu. Jika total melebihi 5000 MB, backup tertua dihapus.

### Lokasi kedua (off-site)

Backup yang hanya ada di server yang sama tidak aman jika server rusak. Siapkan server lain yang bisa diakses lewat SFTP (transfer file lewat SSH).

Lokasi kedua menyala HANYA jika tiga isian ini semuanya terisi: `BACKUP_OFFSITE_HOST`, `BACKUP_OFFSITE_HOST_FINGERPRINT`, dan `BACKUP_ARCHIVE_PASSWORD`. Jika salah satu kosong, backup hanya disimpan di server ini. Alasannya: tanpa sidik jari, koneksi bisa dibelokkan ke server palsu. Tanpa password, backup (berisi data pribadi, hash password, dan foto) keluar dari server dalam keadaan terbuka.

Cara mendapat sidik jari (fingerprint) server tujuan. Jalankan di komputer yang bisa menjangkau server tujuan, lalu bandingkan hasilnya dengan yang diberikan pengelola server tujuan:

```bash
ssh-keyscan -t ed25519 server-backup.example.org | ssh-keygen -lf -
```

Hasilnya mirip `256 SHA256:AbCdEf... server-backup.example.org (ED25519)`. Salin bagian `SHA256:AbCdEf...` apa adanya ke `BACKUP_OFFSITE_HOST_FINGERPRINT`. Aplikasi menerima format ini.

Password zip: buat password panjang di `BACKUP_ARCHIVE_PASSWORD`, lalu simpan salinannya di tempat terpisah di luar server (misalnya brankas password milik pengelola). Tanpa password ini backup tidak bisa dibuka dan restore tidak mungkin dilakukan. Jika password diganti, catat password lama: backup lama tetap memakai password lama.

```
BACKUP_OFFSITE_HOST=server-backup.example.org
BACKUP_OFFSITE_HOST_FINGERPRINT=SHA256:AbCdEf...
BACKUP_OFFSITE_PORT=22
BACKUP_OFFSITE_USERNAME=...
BACKUP_OFFSITE_PASSWORD=...
BACKUP_OFFSITE_ROOT=/backups/siperkasa
```

Alternatif: pakai kunci SSH lewat `BACKUP_OFFSITE_PRIVATE_KEY` (isi kunci) dan `BACKUP_OFFSITE_PASSPHRASE`. Kosongkan `BACKUP_OFFSITE_HOST` jika tidak memakai lokasi kedua. Jalankan `php artisan optimize` lagi setelah mengubah `.env`.

Pengaturan lain: `MYSQLDUMP_PATH=/usr/bin` (folder `mysqldump`, cek dengan `which mysqldump`), `BACKUP_NOTIFY_MAIL` (WAJIB: email admin penerima kabar backup gagal; jangan pakai alamat noreply karena kabar tidak akan terbaca).

### Tes alarm backup (wajib sebelum go-live)

Pastikan kabar gagal benar-benar sampai. Isi `BACKUP_NOTIFY_MAIL`, jalankan `php artisan optimize`, lalu ubah `MYSQLDUMP_PATH` sementara ke folder yang salah (misalnya `/tmp`). Jalankan `php artisan backup:run` dan pastikan email kabar gagal masuk ke kotak masuk admin. Setelah itu kembalikan `MYSQLDUMP_PATH` yang benar dan jalankan `php artisan optimize` lagi. Jika email tidak masuk, jangan go-live sebelum diperbaiki.

### Jalankan manual

```bash
php artisan backup:run
php artisan backup:list
```

`backup:list` harus menampilkan backup baru dengan status sehat.

### Latihan restore (wajib sebelum go-live)

1. Unduh file zip backup terbaru ke komputer. Lokasinya `storage/app/backups/<APP_NAME>/` (nama folder mengikuti `APP_NAME`) atau lokasi kedua. Ekstrak dengan password dari `BACKUP_ARCHIVE_PASSWORD`:

   ```bash
   unzip -P 'password-zip-anda' nama-file-backup.zip -d hasil-backup
   ```

   Hapus riwayat terminal setelahnya karena password tertulis di perintah. Tanpa password, zip tidak bisa dibuka.
2. Buat database kosong baru untuk uji coba (jangan timpa database produksi), lalu impor:

   ```bash
   mysql siperkasa_uji < hasil-backup/db-dumps/mysql-siperkasa.sql
   ```

   Nama file dump mengikuti nama database. Cek isi folder `db-dumps/`.
3. Folder foto tersimpan di dalam zip dengan path lengkap server asal, yaitu path yang berakhir dengan `storage/app/private` (misalnya `hasil-backup/home/<user>/siperkasa/storage/app/private`). Cari dengan `find hasil-backup -path '*storage/app/private' -type d`, lalu salin isinya ke `storage/app/private` pada aplikasi uji.
4. Arahkan `.env` aplikasi uji ke database uji, login, dan buka satu foto laporan. Foto harus tampil.
5. Catat tanggal latihan di sini:

   - Tanggal latihan restore terakhir: ____________________
   - Dilakukan oleh: ____________________

Untuk restore produksi yang sebenarnya, urutan sama, tetapi aktifkan mode perawatan dulu (`php artisan down`) dan impor ke database produksi setelah memastikan backup yang dipakai benar.

## 8. Update aplikasi

```bash
cd /home/<user>/siperkasa
php artisan down --secret=kata-rahasia-anda
```

Selama mode perawatan, buka `https://siperkasa.example.ac.id/kata-rahasia-anda` sekali agar Anda tetap bisa mengakses situs. Lalu:

```bash
git pull                              # atau upload kode baru
composer install --no-dev -o
# upload ulang public/build dari komputer (npm ci && npm run build)
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

Rollback jika ada masalah:

- Kembalikan kode ke rilis sebelumnya (`git checkout <tag-lama>`), lalu `composer install --no-dev -o`, upload `public/build` lama.
- Batalkan migrasi hanya milik rilis yang gagal: `php artisan migrate:rollback --step=N` (N = jumlah migrasi di rilis itu). Jika ragu, restore dari backup.
- Jangan pernah menjalankan `migrate:fresh`, `migrate:refresh`, atau `db:wipe`. Perintah itu menghapus semua data. Aplikasi memblokirnya di production, tetapi jangan mencoba.

## 9. SSR (tampilan dirender di server)

SSR mati secara bawaan (`INERTIA_SSR_ENABLED=false`) dan situs tetap berjalan normal. Nyalakan hanya di hosting yang bisa menjaga `php artisan inertia:start-ssr` berjalan terus (proses Node). Jika dinyalakan, build aset dengan `npm run build:ssr`.

## 10. Pemantauan

- Pasang monitor (misalnya UptimeRobot) ke `https://siperkasa.example.ac.id/up`. Halaman ini mengecek database dan sisa disk. Jika sisa disk kurang dari `HEALTH_MIN_FREE_MB` (bawaan 1024 MB) atau database tidak terjangkau, halaman mengembalikan 500.
- Log ada di `storage/logs/laravel-YYYY-MM-DD.log`, disimpan 14 hari. Hanya level `warning` ke atas yang dicatat.
- Foto bertambah sekitar 3 sampai 4 GB per tahun. Pantau kuota disk hosting dan ruang backup.

## 11. Pemecahan masalah

**Impor aset macet di status `queued`.** Cron tidak jalan. Cek halaman Cron Jobs di cPanel, pastikan baris di langkah 3.9 ada dan path `php` benar. Cek juga `proc_open` (bagian 1). Jalankan `php artisan queue:work --once` untuk memproses manual.

**Gejala lain yang mungkin muncul.**

- Error 500 setelah suatu aksi, tetapi status data sudah berubah (misalnya laporan sudah pindah status). Penyebab: penyimpanan notifikasi (pesan pemberitahuan di dalam aplikasi) gagal setelah data tersimpan. Solusi: muat ulang halaman. Data aman. Cek `storage/logs` untuk penyebab gagalnya notifikasi.
- Tampilan tidak cocok antara server dan browser (peringatan hydration mismatch di konsol browser): hanya terjadi bila SSR menyala. Dengan SSR mati (bawaan), masalah ini tidak ada.

**419 (Page Expired) setelah deploy.** Bersihkan cache dan cookie browser, atau login ulang. Pastikan `APP_URL` dan `SESSION_SECURE_COOKIE` sesuai (HTTPS).

**Ekspor PDF timeout atau kehabisan memori.** Persempit filter (rentang tanggal lebih pendek) atau pakai ekspor Excel. Pastikan `memory_limit` 256M dan `max_execution_time` 120 (bagian 4).

**Halaman putih atau 500 tanpa pesan.** Lihat `storage/logs/laravel-<tanggal>.log`. Pastikan folder `storage` dan `bootstrap/cache` bisa ditulis (`chmod -R 775 storage bootstrap/cache`). Jalankan `php artisan optimize:clear` lalu `php artisan optimize`.

**Backup gagal.** Cek email `BACKUP_NOTIFY_MAIL`, jalankan `php artisan backup:run -v` dan baca pesannya. Penyebab umum: `MYSQLDUMP_PATH` salah, `proc_open` nonaktif, disk penuh, atau kredensial lokasi kedua salah.

**Cron tidak jalan sama sekali.** Cek `* * * * *` di Cron Jobs, path `php`, dan path `cd /home/<user>/siperkasa`. Untuk uji manual: `cd /home/<user>/siperkasa && php artisan schedule:list` harus menampilkan 7 tugas.

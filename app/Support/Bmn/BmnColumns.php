<?php

namespace App\Support\Bmn;

final class BmnColumns
{
    public const JENIS_BMN = 'Jenis BMN';

    public const KODE_BARANG = 'Kode Barang';

    public const NUP = 'NUP';

    public const NAMA_BARANG = 'Nama Barang';

    public const MERK = 'Merk';

    public const TIPE = 'Tipe';

    public const KONDISI = 'Kondisi';

    public const TGL_PEROLEHAN = 'Tanggal Perolehan';

    public const NILAI_PEROLEHAN = 'Nilai Perolehan';

    public const KODE_REGISTER = 'Kode Register';

    public const GOLONGAN = 'Golongan Aset';

    public const KELOMPOK = 'Kelompok Aset';

    /** All 80 header names, in the order they appear in the export. */
    public const ALL = [
        'No', 'Jenis BMN', 'Kode Satker', 'Nama Satker', 'Kode Barang', 'NUP',
        'Nama Barang', 'Status BMN', 'Merk', 'Tipe', 'Kondisi', 'Umur Aset',
        'Intra / Extra', 'Henti Guna', 'Status SBSN', 'Status BMN Idle',
        'Status Kemitraan', 'BPYBDS', 'Usulan Barang Hilang', 'Usulan Barang RB',
        'Usul Hapus', 'Hibah DKTP', 'Konsensi Jasa', 'Properti Investasi',
        'Jenis Dokumen', 'No Dokumen', 'No BPKP', 'No Polisi',
        'Status Sertifikasi', 'Jenis Sertipikat', 'No Sertifikat', 'Nama',
        'Tanggal Buku Pertama', 'Tanggal Perolehan', 'Tanggal Pengapusan',
        'Nilai Perolehan Pertama', 'Nilai Mutasi', 'Nilai Perolehan',
        'Nilai Penyusutan', 'Nilai Buku', 'Luas Tanah Seluruhnya',
        'Luas Tanah Untuk Bangunan', 'Luas Tanah Untuk Sarana Lingkungan',
        'Luas Lahan Kosong', 'Luas Bangunan', 'Luas Tapak Bangunan',
        'Luas Pemanfataan', 'Jumlah Lantai', 'Jumlah Foto', 'Status Penggunaan',
        'No PSP', 'Tanggal PSP', 'Alamat', 'RT/RW', 'Kelurahan/Desa',
        'Kecamatan', 'Kab/Kota', 'Kode Kab/Kota', 'Provinsi', 'Kode Provinsi',
        'Kode Pos', 'SBSK', 'Optimalisasi', 'Penghuni', 'Pengguna',
        'Kode KPKNL', 'Uraian KPKNL', 'Uraian Kanwil DJKN', 'Nama K/L',
        'Nama E1', 'Nama Korwil', 'Kode Register', 'Lokasi Ruang',
        'Jenis Identitas', 'No Identitas', 'No STNK', 'Nama Pengguna',
        'Status PMK', 'Golongan Aset', 'Kelompok Aset',
    ];
}

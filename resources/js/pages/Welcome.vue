<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useIntersectionObserver } from '@vueuse/core';
import { dashboard, login } from '@/routes';
import { Activity, QrCode, FileText, CheckCircle, PenTool, LayoutDashboard } from '@lucide/vue';

interface Stats {
    total_reports: number;
    total_completed: number;
    total_in_progress: number;
    total_assets: number;
}

defineProps<{
    stats: Stats;
}>();

// Simple intersection observer logic for fade-in animations
const fadeElements = ref<HTMLElement[]>([]);
const setFadeElement = (el: any) => {
    if (el && !fadeElements.value.includes(el)) {
        fadeElements.value.push(el);
        useIntersectionObserver(el, ([{ isIntersecting }]) => {
            if (isIntersecting) {
                el.classList.remove('opacity-0', 'translate-y-10');
                el.classList.add('opacity-100', 'translate-y-0');
            }
        }, { threshold: 0.15 });
    }
};
</script>

<template>
    <Head title="Selamat Datang" />
    <div class="min-h-screen bg-[#F8FAFC] font-roboto text-[#212529]">
        <!-- Navbar -->
        <header class="fixed top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-gray-100 transition-all shadow-sm">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
                <div class="flex items-center gap-2">
                    <Activity class="h-6 w-6 text-[#0d6efd]" />
                    <span class="text-xl font-bold tracking-tight text-[#0d6efd]">siPerkasa</span>
                </div>
                <nav>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-md bg-[#0d6efd] px-5 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-blue-700 focus:ring-2 focus:ring-[#0d6efd] focus:ring-offset-2"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="rounded-md bg-[#0d6efd] px-5 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-blue-700 focus:ring-2 focus:ring-[#0d6efd] focus:ring-offset-2"
                    >
                        Login / Masuk
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="relative pt-24 pb-16 lg:pt-32 lg:pb-24 overflow-hidden">
            <div :ref="setFadeElement" class="mx-auto max-w-7xl px-6 lg:px-8 text-center opacity-0 translate-y-10 transition-all duration-1000 ease-out">
                <h1 class="mx-auto max-w-4xl text-4xl font-extrabold tracking-tight sm:text-6xl text-gray-900 leading-tight">
                    Laporkan Kerusakan Fasilitas dengan <span class="text-[#0d6efd]">Mudah & Cepat</span>
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-gray-600">
                    siPerkasa adalah platform pelaporan inventaris dan aset fakultas. Scan QR, laporkan, dan pantau status perbaikannya secara transparan.
                </p>
                <div class="mt-10 flex items-center justify-center gap-x-6">
                    <Link
                        :href="login()"
                        class="rounded-md bg-[#0d6efd] px-6 py-3.5 text-base font-semibold text-white shadow-md transition-all hover:bg-blue-700 hover:-translate-y-1 focus:ring-2 focus:ring-[#0d6efd] focus:ring-offset-2"
                    >
                        Buat Laporan Sekarang
                    </Link>
                    <a href="#cara-melapor" class="text-base font-semibold leading-6 text-gray-900 transition-colors hover:text-[#0d6efd]">
                        Pelajari Caranya <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </main>

        <!-- Informasi Section -->
        <section class="bg-white py-16 sm:py-24" id="tentang">
            <div :ref="setFadeElement" class="mx-auto max-w-7xl px-6 lg:px-8 opacity-0 translate-y-10 transition-all duration-1000 ease-out">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-base font-semibold leading-7 text-[#0d6efd]">Tentang Sistem</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Apa itu siPerkasa?</p>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        siPerkasa (Sistem Pelaporan Kerusakan Aset dan Sarana) dirancang untuk memutus rantai pelaporan manual yang rumit. Kami menjembatani Anda dengan tim Sarpras dan Teknisi untuk memastikan setiap fasilitas kampus selalu dalam kondisi prima.
                    </p>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-100 ease-out flex flex-col items-center justify-center rounded-2xl bg-white p-8 shadow-sm border border-gray-100">
                        <dt class="text-sm font-medium leading-6 text-gray-500">Total Laporan Masuk</dt>
                        <dd class="mt-2 text-4xl font-extrabold tracking-tight text-[#0d6efd]">{{ stats.total_reports }}</dd>
                    </div>
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-200 ease-out flex flex-col items-center justify-center rounded-2xl bg-white p-8 shadow-sm border border-gray-100">
                        <dt class="text-sm font-medium leading-6 text-gray-500">Laporan Selesai</dt>
                        <dd class="mt-2 text-4xl font-extrabold tracking-tight text-green-600">{{ stats.total_completed }}</dd>
                    </div>
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-300 ease-out flex flex-col items-center justify-center rounded-2xl bg-white p-8 shadow-sm border border-gray-100">
                        <dt class="text-sm font-medium leading-6 text-gray-500">Sedang Dikerjakan</dt>
                        <dd class="mt-2 text-4xl font-extrabold tracking-tight text-yellow-500">{{ stats.total_in_progress }}</dd>
                    </div>
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-400 ease-out flex flex-col items-center justify-center rounded-2xl bg-white p-8 shadow-sm border border-gray-100">
                        <dt class="text-sm font-medium leading-6 text-gray-500">Total Aset Terdaftar</dt>
                        <dd class="mt-2 text-4xl font-extrabold tracking-tight text-gray-900">{{ stats.total_assets }}</dd>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cara Melapor Section -->
        <section class="bg-white py-16 sm:py-24" id="cara-melapor">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div :ref="setFadeElement" class="mx-auto max-w-2xl text-center mb-16 opacity-0 translate-y-10 transition-all duration-1000 ease-out">
                    <h2 class="text-base font-semibold leading-7 text-[#0d6efd]">Panduan</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Cara Melapor dalam 3 Langkah</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-10 relative">
                    <!-- Connecting Line for Desktop -->
                    <div class="hidden md:block absolute top-12 left-[16%] right-[16%] h-0.5 bg-gray-100 -z-10"></div>

                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-100 ease-out relative text-center">
                        <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-blue-50 border-4 border-white shadow-sm mb-6">
                            <QrCode class="h-10 w-10 text-[#0d6efd]" />
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">1. Scan QR Code</h3>
                        <p class="text-gray-600">Temukan stiker QR Code pada aset yang rusak dan scan menggunakan kamera HP Anda, atau login dan cari aset secara manual.</p>
                    </div>
                    
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-300 ease-out relative text-center">
                        <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-blue-50 border-4 border-white shadow-sm mb-6">
                            <FileText class="h-10 w-10 text-[#0d6efd]" />
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">2. Isi Detail & Foto</h3>
                        <p class="text-gray-600">Jelaskan kerusakan yang terjadi dan unggah foto kondisi aset saat ini sebagai bukti validasi tim kami.</p>
                    </div>

                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-500 ease-out relative text-center">
                        <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-blue-50 border-4 border-white shadow-sm mb-6">
                            <CheckCircle class="h-10 w-10 text-[#0d6efd]" />
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">3. Pantau Status</h3>
                        <p class="text-gray-600">Laporan akan divalidasi dan dikerjakan oleh teknisi (PIC). Pantau terus status penyelesaiannya secara real-time.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Fitur Section -->
        <section class="py-16 sm:py-24 bg-[#F8FAFC]">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div :ref="setFadeElement" class="mx-auto max-w-2xl text-center mb-16 opacity-0 translate-y-10 transition-all duration-1000 ease-out">
                    <h2 class="text-base font-semibold leading-7 text-[#0d6efd]">Fitur Utama</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Semua yang Anda butuhkan</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-100 ease-out bg-white p-8 rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-blue-50/50 hover:shadow-lg transition-shadow">
                        <div class="h-12 w-12 rounded-lg bg-blue-50 flex items-center justify-center mb-6">
                            <QrCode class="h-6 w-6 text-[#0d6efd]" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Identifikasi Pintar</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Sistem mengenali gedung dan ruangan secara otomatis hanya dengan scan QR Code aset, mencegah salah input lokasi.</p>
                    </div>

                    <!-- Feature 2 -->
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-200 ease-out bg-white p-8 rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-blue-50/50 hover:shadow-lg transition-shadow">
                        <div class="h-12 w-12 rounded-lg bg-blue-50 flex items-center justify-center mb-6">
                            <Activity class="h-6 w-6 text-[#0d6efd]" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Transparansi Proses</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Setiap perubahan status tiket (Pending, In Progress, Closed) tercatat di log aktivitas yang bisa Anda lihat langsung.</p>
                    </div>

                    <!-- Feature 3 -->
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-300 ease-out bg-white p-8 rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-blue-50/50 hover:shadow-lg transition-shadow">
                        <div class="h-12 w-12 rounded-lg bg-blue-50 flex items-center justify-center mb-6">
                            <CheckCircle class="h-6 w-6 text-[#0d6efd]" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Target Waktu (SLA)</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Admin Sarpras dapat memberikan batas waktu penyelesaian untuk setiap laporan agar teknisi bekerja secara terukur.</p>
                    </div>

                    <!-- Feature 4 -->
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-400 ease-out bg-white p-8 rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-blue-50/50 hover:shadow-lg transition-shadow">
                        <div class="h-12 w-12 rounded-lg bg-blue-50 flex items-center justify-center mb-6">
                            <PenTool class="h-6 w-6 text-[#0d6efd]" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Pencegahan Duplikasi</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Sistem memblokir pelaporan ganda pada aset yang sama jika masih ada tiket perbaikan yang aktif berjalan.</p>
                    </div>

                    <!-- Feature 5 -->
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-500 ease-out bg-white p-8 rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-blue-50/50 hover:shadow-lg transition-shadow">
                        <div class="h-12 w-12 rounded-lg bg-blue-50 flex items-center justify-center mb-6">
                            <LayoutDashboard class="h-6 w-6 text-[#0d6efd]" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Dashboard Spesifik</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Tampilan layar disesuaikan dengan peran Anda (Pelapor, Teknisi/PIC, atau Admin) untuk fokus yang lebih baik.</p>
                    </div>

                    <!-- Feature 6 -->
                    <div :ref="setFadeElement" class="opacity-0 translate-y-10 transition-all duration-700 delay-600 ease-out bg-white p-8 rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-blue-50/50 hover:shadow-lg transition-shadow">
                        <div class="h-12 w-12 rounded-lg bg-blue-50 flex items-center justify-center mb-6">
                            <FileText class="h-6 w-6 text-[#0d6efd]" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Ekspor Pelaporan</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Laporan penyelesaian dan rekap biaya perbaikan dapat diekspor dengan mudah oleh Admin untuk keperluan akuntabilitas.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Footer Section -->
        <section class="bg-white border-t border-gray-100">
            <div :ref="setFadeElement" class="mx-auto max-w-7xl px-6 py-16 sm:py-24 lg:flex lg:items-center lg:justify-between lg:px-8 opacity-0 translate-y-10 transition-all duration-1000 ease-out">
                <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    <span class="block">Ada kerusakan?</span>
                    <span class="block text-[#0d6efd]">Jangan diam saja, laporkan sekarang.</span>
                </h2>
                <div class="mt-8 flex items-center gap-x-6 lg:mt-0 lg:flex-shrink-0">
                    <Link
                        :href="login()"
                        class="rounded-md bg-[#0d6efd] px-8 py-4 text-lg font-semibold text-white shadow-md transition-all hover:bg-blue-700 hover:-translate-y-1 focus:ring-2 focus:ring-[#0d6efd] focus:ring-offset-2"
                    >
                        Login untuk Melapor
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-50 border-t border-gray-100 py-8">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 text-center text-sm text-gray-500">
                &copy; {{ new Date().getFullYear() }} siPerkasa (Sistem Pelaporan Kerusakan Aset dan Sarana). All rights reserved.
            </div>
        </footer>
    </div>
</template>

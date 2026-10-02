<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();    // Otomatis, Misal: REP-2609-001

            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->cascadeOnDelete();

            // FASE 1: Input Pelapor
            $table->text('description')->nullable();
            $table->string('damage_photo')->nullable();   // URL/Path foto kerusakan
            $table->enum('user_priority', ['Low', 'Medium', 'High', 'Critical'])->nullable();
            $table->enum('status', [
                'Pending',
                'Assigned',
                'In_Progress',
                'On_Hold',
                'Closed',
                'Rejected',
            ])->default('Pending');

            // FASE 2: Validasi Admin
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('admin_priority', ['Low', 'Medium', 'High', 'Critical'])->nullable();
            $table->dateTime('sla_date')->nullable();     // Target waktu penyelesaian
            $table->foreignId('assigned_pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable(); // Alasan jika ditolak

            // FASE 3: Eksekusi PIC
            $table->text('hold_reason')->nullable();            // Alasan jika tertunda (On Hold)
            $table->string('vendor_name')->nullable();          // Nama teknisi luar
            $table->text('repair_action')->nullable();          // Tindakan perbaikan
            $table->decimal('repair_cost', 15, 2)->nullable();  // Biaya ke vendor
            $table->string('completion_photo')->nullable();     // URL/Path foto selesai

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

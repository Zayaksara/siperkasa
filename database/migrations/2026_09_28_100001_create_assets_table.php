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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();      // Kode Inventaris (Misal: FT-IT-001)
            $table->string('name');                       // Nama Alat (Misal: Proyektor, AC)
            $table->string('brand_type')->nullable();     // Merk/Tipe (Opsional)
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('building')->nullable();       // Gedung tempat alat berada
            $table->string('room')->nullable();           // Ruangan tempat alat berada
            $table->foreignId('default_pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('purchase_year')->nullable();  // Tahun Pengadaan (Opsional)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

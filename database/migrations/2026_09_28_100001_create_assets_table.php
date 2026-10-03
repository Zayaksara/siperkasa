<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();          // {Kode Barang}-{NUP}
            $table->string('kode_barang')->index();
            $table->unsignedInteger('nup');
            $table->string('name')->index();
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('jenis_bmn');
            $table->enum('golongan', ['IT', 'NON-IT'])->index();
            $table->string('condition')->nullable();
            $table->date('acquired_at')->nullable();
            $table->decimal('acquisition_value', 18, 2)->nullable();
            $table->string('kode_register')->nullable()->index();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('bmn_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

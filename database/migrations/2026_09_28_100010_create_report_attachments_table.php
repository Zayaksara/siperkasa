<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->enum('type', ['damage', 'completion']);
            $table->string('path');
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamps();

            $table->index(['report_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
    }
};

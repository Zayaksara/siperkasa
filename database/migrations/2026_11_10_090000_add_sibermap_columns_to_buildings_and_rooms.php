<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->string('campus_name')->nullable();
            $table->unsignedBigInteger('sibermap_id')->nullable()->unique();
            $table->timestamp('synced_at')->nullable();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('sibermap_id')->nullable()->unique();
            $table->string('floor_name')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique(['sibermap_id']);
            $table->dropColumn(['sibermap_id', 'floor_name', 'synced_at']);
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropUnique(['sibermap_id']);
            $table->dropColumn(['campus_name', 'sibermap_id', 'synced_at']);
        });
    }
};

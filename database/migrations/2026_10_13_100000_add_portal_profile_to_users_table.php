<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('portal_role', 50)->nullable()->after('phone');
            $table->string('faculty')->nullable()->after('portal_role');
            $table->string('study_program_code', 20)->nullable()->after('faculty');
            $table->string('study_program')->nullable()->after('study_program_code');
            $table->string('degree_level', 20)->nullable()->after('study_program');
            $table->unsignedSmallInteger('entry_year')->nullable()->after('degree_level');
            $table->timestamp('portal_synced_at')->nullable()->after('entry_year');

            $table->index('faculty');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['faculty']);
            $table->dropColumn([
                'phone',
                'portal_role',
                'faculty',
                'study_program_code',
                'study_program',
                'degree_level',
                'entry_year',
                'portal_synced_at',
            ]);
        });
    }
};

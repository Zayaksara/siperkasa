<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();               // SPK-YYMM-NNNN

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 120);
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->text('description');
            $table->enum('user_priority', ['Low', 'Medium', 'High', 'Critical']);
            $table->enum('status', ['Pending', 'Assigned', 'In_Progress', 'On_Hold', 'Closed', 'Rejected', 'Cancelled'])
                ->default('Pending')->index();

            // Admin validation
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('admin_priority', ['Low', 'Medium', 'High', 'Critical'])->nullable();
            $table->dateTime('sla_date')->nullable()->index();
            $table->foreignId('assigned_pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // PIC execution
            $table->text('hold_reason')->nullable();                 // latest reason; history lives in the status log
            $table->text('repair_action')->nullable();
            $table->date('completed_on')->nullable();
            $table->decimal('repair_cost', 15, 2)->nullable();
            $table->string('vendor_name')->nullable();

            $table->timestamp('validated_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_pic_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

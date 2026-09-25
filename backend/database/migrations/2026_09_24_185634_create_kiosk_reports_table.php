<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_reports', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('kiosk_id')->constrained()->cascadeOnDelete();

            // a farmer, not a raw user - matches how the rest of the app names this relation
            $table->foreignId('farmer_profile_id')->constrained()->cascadeOnDelete();

            $table->string('reason');
            $table->text('details')->nullable();

            $table->string('status', 20)->default('open');

            $table->timestamp('supplier_due_at');
            $table->timestamp('admin_due_at')->nullable();

            $table->text('supplier_answer')->nullable();
            $table->timestamp('supplier_answered_at')->nullable();

            // stamped when the admin-window-ended alert fires, so the scheduled job never
            // re-sends it every day while the report just sits with_admin waiting on a human
            $table->timestamp('admin_window_alerted_at')->nullable();

            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // a farmer can report a given kiosk only once
            $table->unique(['kiosk_id', 'farmer_profile_id']);

            $table->index('status');
            $table->index('supplier_due_at');
            $table->index('admin_due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_reports');
    }
};

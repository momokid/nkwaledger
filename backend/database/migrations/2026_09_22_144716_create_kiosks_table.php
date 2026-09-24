<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosks', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();

            // assigned from the row's own id right after insert, so it is never null once a kiosk exists
            $table->string('kiosk_number')->nullable()->unique();

            $table->string('name', 60);
            $table->string('thumbnail_path')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();

            $table->string('contact_phone');

            $table->string('status', 20)->default('pending_confirmation');
            $table->timestamp('confirmed_at')->nullable();

            // a 2nd (or later) kiosk beyond the free cap, held back for admin sign-off on top of the normal confirmation
            $table->boolean('requires_admin_approval')->default(false);
            $table->timestamp('admin_approved_at')->nullable();
            $table->foreignId('admin_approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('supplier_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosks');
    }
};

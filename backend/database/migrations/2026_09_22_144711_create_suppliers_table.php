<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            // what the browser sees, so a supplier cannot be found by counting upward
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('business_name')->nullable();
            $table->string('business_registration_number')->nullable();

            // ID checks are postponed, but the fields are stored now so the check can switch on later
            $table->string('id_number_hash', 64)->nullable();
            $table->string('id_photo_path')->nullable();
            $table->timestamp('id_verified_at')->nullable();
            $table->foreignId('id_verified_by')->nullable()->constrained('users')->nullOnDelete();

            // the marketplace's own required email, independent of whatever email (if any) the staff invite used
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            $table->string('account_status', 20)->default('active');
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspension_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produce_listings', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // the batch this listing is drawn from - capacity, product identity (via its
            // farm unit's farm type) and unit of measure all come from here, never duplicated
            $table->foreignId('farm_unit_stock_id')->constrained()->restrictOnDelete();

            $table->foreignId('farmer_profile_id')->constrained()->restrictOnDelete();

            // a farmer or their agent - draft listings posted by an agent wait on
            // farmer_agreed_at before anyone else can see them
            $table->foreignId('posted_by_user_id')->constrained('users')->restrictOnDelete();

            $table->string('status', 20)->default('draft');

            $table->decimal('quantity_listed', 10, 2);
            $table->decimal('quantity_remaining', 10, 2);

            $table->string('photo')->nullable();

            // crops only - an animal listing leaves both null and never expires
            $table->unsignedInteger('crop_expiry_days')->nullable();
            $table->timestamp('expires_at')->nullable();

            // sent once, guards the reminder and the still-available prompt from repeating
            $table->timestamp('expiry_reminder_sent_at')->nullable();
            $table->timestamp('still_available_prompted_at')->nullable();

            // set the moment a farmer approves an agent-posted draft; already set at
            // creation time when the farmer posts their own listing
            $table->timestamp('farmer_agreed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('farm_unit_stock_id');
            $table->index('farmer_profile_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produce_listings');
    }
};

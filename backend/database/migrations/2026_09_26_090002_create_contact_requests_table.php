<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // polymorphic on purpose - a produce listing is the first contactable,
            // never the only one this is meant to serve
            $table->string('contactable_type');
            $table->unsignedBigInteger('contactable_id');

            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();

            $table->text('message')->nullable();

            $table->timestamps();

            $table->index(['contactable_type', 'contactable_id']);
            $table->index('recipient_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};

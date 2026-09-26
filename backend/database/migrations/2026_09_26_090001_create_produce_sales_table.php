<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produce_sales', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('produce_listing_id')->constrained()->restrictOnDelete();

            // any authenticated account holder, not only a farmer
            $table->foreignId('buyer_user_id')->constrained('users')->restrictOnDelete();

            // denormalized from the listing, same as orders.farmer_profile_id from kiosks
            $table->foreignId('farmer_profile_id')->constrained()->restrictOnDelete();

            $table->decimal('quantity', 10, 2);
            $table->string('payment_method', 10);

            // the buyer's own offer - typed once, at request time, never price times
            // quantity; the farmer either accepts it (confirms) or the sale never settles
            $table->unsignedBigInteger('amount_minor');

            // a friendly rollup of confirmed_at/received_at/closed_at below, same
            // convention as orders.status - never the source of truth
            $table->string('status', 20)->default('requested');

            $table->timestamp('requested_at');

            // the farmer's tap - they are the seller here, the direction Step 5 had the
            // kiosk supplier's confirm in
            $table->timestamp('confirmed_at')->nullable();

            // the buyer's tap - confirming they received the produce
            $table->timestamp('received_at')->nullable();

            $table->timestamp('closed_at')->nullable();

            $table->foreignId('ledger_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            // present only when an agent facilitated this sale
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();

            // set only by the agent's own co-confirm action - this is what
            // countsTowardCredit() gates on, alongside isFullyConfirmed()
            $table->timestamp('agent_co_confirmed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('produce_listing_id');
            $table->index('farmer_profile_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produce_sales');
    }
};

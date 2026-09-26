<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // assigned from the row's own id right after insert, distinct from a kiosk's
            // own NKL-#### numbering
            $table->string('order_number')->nullable()->unique();

            $table->foreignId('kiosk_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_profile_id')->constrained()->restrictOnDelete();

            // the farm unit the input purchase applies to, chosen at cart checkout since
            // the input_purchase template requires one - see the Step 5 audit
            $table->foreignId('farm_unit_id')->constrained()->restrictOnDelete();

            // present only when an agent facilitated this order
            $table->foreignId('facilitating_agent_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('payment_method', 10);

            // a friendly rollup of confirmed_at/received_at/closed_at below - never the
            // source of truth for whether an order is complete, see Order::isFullyConfirmed()
            $table->string('status', 20)->default('requested');

            // pesewas, the sum of every order item's line total at order time
            $table->unsignedBigInteger('amount_minor')->default(0);

            $table->timestamp('requested_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // set once both taps have landed and the ledger post actually happens -
            // doubles as the guard against posting the same order twice
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('kiosk_id');
            $table->index('farmer_profile_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

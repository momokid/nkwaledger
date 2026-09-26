<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();

            // one commission per order - an order has at most one facilitating agent
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();

            $table->string('status', 20)->default('pending_admin');

            // whether this agent is also the one who verifies this farmer's identity -
            // recorded here since it can change after the fact, this row must not
            $table->boolean('verifies_farmer')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('agent_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};

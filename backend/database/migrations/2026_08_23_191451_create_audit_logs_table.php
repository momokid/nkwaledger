<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // the person stays on the row even if their account goes, so history keeps its owner
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action');

            // empty for events that touch no record, like a failed login
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            // only the keys that changed, never the whole record
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // no updated_at, because a row that can change is not a record of what happened
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            // this table outgrows every other one, so reading a date range has to stay cheap
            $table->index('created_at');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

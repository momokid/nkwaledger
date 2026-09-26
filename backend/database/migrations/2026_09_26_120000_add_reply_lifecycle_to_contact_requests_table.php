<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            // the number the sender wants revealed - typed at send time, may differ
            // from their account phone, never exposed until the recipient replies
            $table->string('sender_phone')->after('message');

            $table->string('status', 10)->default('sent')->after('sender_phone');

            $table->timestamp('replied_at')->nullable()->after('status');
            $table->text('reply_message')->nullable()->after('replied_at');

            // an unanswered request never reveals a number, past this date - see
            // ContactRequestService::reply()'s own live check, which never trusts the
            // scheduled sweep alone to have already flipped status
            $table->timestamp('expires_at')->nullable()->after('reply_message');

            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropColumn(['sender_phone', 'status', 'replied_at', 'reply_message', 'expires_at']);
        });
    }
};

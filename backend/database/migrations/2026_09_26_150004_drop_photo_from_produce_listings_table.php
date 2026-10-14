<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// the single nullable `photo` column is replaced by the new produce_listing_images
// relationship (see create_produce_listing_images_table). Pre-production/demo data
// only exists on this branch, so this is a clean drop, not a backfill - any seeded
// listing with just the old column gets fresh images the next time the demo seeder runs
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produce_listings', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }

    public function down(): void
    {
        Schema::table('produce_listings', function (Blueprint $table) {
            $table->string('photo')->nullable();
        });
    }
};

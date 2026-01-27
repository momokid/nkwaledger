<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OneTimePasswordTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_time_password_can_be_stored(): void
    {
        DB::table('one_time_passwords')->insert([
            'phone_number' => '233501234567',
            'otp_hash'     => bcrypt('123456'),
            'expires_at'   => now()->addMinutes(5),
            'channel'      => 'web',
        ]);

        $this->assertDatabaseHas('one_time_passwords', [
            'phone_number' => '233501234567',
        ]);
    }
}

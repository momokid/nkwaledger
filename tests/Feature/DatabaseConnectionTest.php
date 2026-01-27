<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_postgres_database_connection_works(): void
    {
        $result = DB::select('SELECT 1 AS connected');

        $this->assertEquals(1, $result[0]->connected);
    }
}

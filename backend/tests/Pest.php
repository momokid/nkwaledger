<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vet', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'adviser', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supplier', 'guard_name' => 'web']);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

// a minimal-but-real M4A container (ftyp + moov/mvhd declaring the given
// duration + an empty mdat) - no actual audio frames, but getID3 reads
// duration straight from mvhd, so this is enough to test duration limits
// without needing ffmpeg or any real encoded audio in this environment
function fakeAudioUpload(int $seconds, string $filename = 'note.m4a'): \Illuminate\Http\UploadedFile
{
    $box = fn(string $type, string $body) => pack('N', strlen($body) + 8) . $type . $body;

    $mvhdBody =
        pack('N', 0) // version(1) + flags(3)
        . pack('N', 0) // creation_time
        . pack('N', 0) // modification_time
        . pack('N', 1000) // timescale
        . pack('N', $seconds * 1000) // duration
        . pack('N', 0x00010000) // rate
        . pack('n', 0x0100) // volume
        . pack('n', 0) // reserved
        . pack('N', 0) . pack('N', 0) // reserved x2
        . pack('N9', 0x00010000, 0, 0, 0, 0x00010000, 0, 0, 0, 0x40000000) // matrix
        . str_repeat(pack('N', 0), 6) // pre_defined
        . pack('N', 1); // next_track_ID

    $bytes = $box('ftyp', 'M4A ' . pack('N', 0) . 'M4A ' . 'mp42' . 'isom')
        . $box('moov', $box('mvhd', $mvhdBody))
        . $box('mdat', '');

    $path = sys_get_temp_dir() . '/' . \Illuminate\Support\Str::random(12) . '.m4a';
    file_put_contents($path, $bytes);

    return new \Illuminate\Http\UploadedFile($path, $filename, 'audio/mp4', null, true);
}

<?php

use App\Support\AudioUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('stores the audio file under the given directory, keeping its extension', function () {
    $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');

    $path = AudioUpload::store($audio, 'disease-reports');

    expect($path)->toStartWith('disease-reports/');
    expect($path)->toEndWith('.webm');
    Storage::disk('public')->assertExists($path);
});

it('stores the audio unchanged, since MediaRecorder output is already compressed', function () {
    $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');
    $originalContent = file_get_contents($audio->getRealPath());

    $path = AudioUpload::store($audio, 'disease-reports');

    expect(Storage::disk('public')->get($path))->toBe($originalContent);
});

it('gives each stored audio file its own unique filename', function () {
    $first = AudioUpload::store(UploadedFile::fake()->create('a.webm', 10, 'audio/webm'), 'disease-reports');
    $second = AudioUpload::store(UploadedFile::fake()->create('b.webm', 10, 'audio/webm'), 'disease-reports');

    expect($first)->not->toBe($second);
});

it('reports the duration of an m4a recording', function () {
    $audio = fakeAudioUpload(12);

    expect(AudioUpload::durationInSeconds($audio))->toEqualWithDelta(12.0, 0.5);
});

it('reports null when a file has no readable duration', function () {
    $audio = UploadedFile::fake()->create('note.webm', 10, 'audio/webm');

    expect(AudioUpload::durationInSeconds($audio))->toBeNull();
});

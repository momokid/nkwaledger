<?php

use App\Support\PhotoUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('stores the photo under the given directory as a webp', function () {
    $photo = UploadedFile::fake()->image('problem.png', 800, 600);

    $path = PhotoUpload::store($photo, 'disease-reports');

    expect($path)->toStartWith('disease-reports/');
    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

it('shrinks a photo larger than the maximum dimension', function () {
    $photo = UploadedFile::fake()->image('big.jpg', 3000, 2000);

    $path = PhotoUpload::store($photo, 'disease-reports');

    $info = getimagesize(Storage::disk('public')->path($path));

    expect(max($info[0], $info[1]))->toBeLessThanOrEqual(1600);
    // aspect ratio preserved, so a landscape photo stays landscape
    expect($info[0])->toBeGreaterThan($info[1]);
});

it('does not enlarge a photo already smaller than the maximum dimension', function () {
    $photo = UploadedFile::fake()->image('small.jpg', 400, 300);

    $path = PhotoUpload::store($photo, 'disease-reports');

    $info = getimagesize(Storage::disk('public')->path($path));

    expect([$info[0], $info[1]])->toBe([400, 300]);
});

it('compresses the file down from a typical phone-photo size', function () {
    $photo = UploadedFile::fake()->image('phone.jpg', 3000, 2000);
    $originalSize = filesize($photo->getRealPath());

    $path = PhotoUpload::store($photo, 'disease-reports');

    $storedSize = Storage::disk('public')->size($path);

    expect($storedSize)->toBeLessThan($originalSize);
});

it('gives each stored photo its own unique filename', function () {
    $first = PhotoUpload::store(UploadedFile::fake()->image('a.jpg'), 'disease-reports');
    $second = PhotoUpload::store(UploadedFile::fake()->image('b.jpg'), 'disease-reports');

    expect($first)->not->toBe($second);
});

it('can store on another disk, and then not on the public one', function () {
    Storage::fake('local');

    $path = PhotoUpload::store(UploadedFile::fake()->image('id.jpg'), 'kyc', 'local');

    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

// a hand-built JPEG whose EXIF block carries a camera make, standing in for GPS or owner data
function jpegWithExif(): string
{
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($image);
    $jpeg = ob_get_clean();

    $tiff = "II\x2A\x00\x08\x00\x00\x00" . "\x01\x00" . "\x0F\x01\x02\x00\x08\x00\x00\x00\x1A\x00\x00\x00" . "\x00\x00\x00\x00" . "PRIVATE\x00";
    $payload = "Exif\x00\x00" . $tiff;

    return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);
}

it('strips EXIF metadata from the stored photo', function () {
    $content = jpegWithExif();
    expect($content)->toContain('PRIVATE');

    $path = PhotoUpload::store(UploadedFile::fake()->createWithContent('gps.jpg', $content), 'disease-reports');

    $stored = Storage::disk('public')->get($path);

    expect($stored)->not->toContain('PRIVATE')
        ->and($stored)->not->toContain('EXIF')
        ->and($stored)->not->toContain('Exif');
});

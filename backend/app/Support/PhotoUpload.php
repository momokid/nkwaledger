<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class PhotoUpload
{
    // a phone photo can be several megabytes; nothing this app shows it in
    // ever needs more than this on the longest side
    private const MAX_DIMENSION = 1600;

    // visibly fine at phone-screen size, a fraction of the original's bytes
    private const QUALITY = 75;

    // corrected for orientation and shrunk to fit, then re-encoded as jpeg —
    // so a farmer's data plan never pays for the original file's full size
    public static function store(UploadedFile $file, string $directory): string
    {
        $manager = new ImageManager(new Driver());

        $image = $manager->decodePath($file->getRealPath());
        $image->orient();
        $image->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION);

        $path = trim($directory, '/') . '/' . (string) Str::uuid7() . '.jpg';

        Storage::disk('public')->put(
            $path,
            (string) $image->encode(new JpegEncoder(quality: self::QUALITY)),
        );

        return $path;
    }
}

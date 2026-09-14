<?php

namespace App\Support;

use getID3;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AudioUpload
{
    // Symfony's own guessExtension() maps audio/webm to the unfamiliar ".weba" -
    // pin the extensions we actually want for the mime types this feature accepts
    private const EXTENSIONS = [
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/aac' => 'aac',
        'audio/mpeg' => 'mp3',
    ];

    // MediaRecorder's own output (opus/aac) is already compressed for voice,
    // so this stores the bytes exactly as received - nothing to re-encode
    public static function store(UploadedFile $file, string $directory): string
    {
        $extension = self::EXTENSIONS[$file->getMimeType()] ?? ($file->guessExtension() ?: 'webm');
        $directory = trim($directory, '/');
        $filename = (string) Str::uuid7() . '.' . $extension;

        return Storage::disk('public')->putFileAs($directory, $file, $filename);
    }

    // null means the container's duration could not be read, not that it has none
    public static function durationInSeconds(UploadedFile $file): ?float
    {
        $info = (new getID3())->analyze($file->getRealPath());

        return $info['playtime_seconds'] ?? null;
    }
}

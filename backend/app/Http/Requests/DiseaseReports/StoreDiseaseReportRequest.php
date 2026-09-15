<?php

namespace App\Http\Requests\DiseaseReports;

use App\Support\AudioUpload;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreDiseaseReportRequest extends FormRequest
{
    // the safety margin above the client's own 30-second hard stop
    private const MAX_AUDIO_SECONDS = 35;

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:1000'],
            // compressed on the way in, so the size ceiling here is about the
            // original phone photo, not what ends up stored
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            // whatever MediaRecorder actually produces: webm/opus (Chrome, Firefox),
            // ogg/opus (older Firefox), or mp4/aac (Safari) - already compressed for
            // ~30s of voice, so a few MB is a generous ceiling, not a real ceiling
            'audio' => [
                'nullable',
                'file',
                'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/x-m4a,audio/aac,audio/mpeg',
                'max:5120',
                $this->durationWithinLimit(...),
            ],
        ];
    }

    // the client already hard-stops at 30s; this only catches a client that didn't.
    // a duration getID3 cannot read is let through rather than blocking a real report
    // over a parsing gap - the client-side cutoff stays the primary defense
    private function durationWithinLimit(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $seconds = AudioUpload::durationInSeconds($value);

        if ($seconds !== null && $seconds > self::MAX_AUDIO_SECONDS) {
            $fail('That recording is too long. Please keep it to 30 seconds or less.');
        }
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Please describe what you are seeing.',
            'photo.required' => 'Please add one photo.',
            'photo.image' => 'That file does not look like a photo.',
            'photo.max' => 'That photo is too large. Please choose a smaller one.',
            'audio.mimetypes' => 'That does not look like a voice recording.',
            'audio.max' => 'That recording is too large.',
        ];
    }
}

<?php

namespace App\Services\DiseaseReports;

use App\Exceptions\HealthUploadRefused;
use App\Models\DiseaseReport;
use App\Models\HealthReportUpload;
use App\Models\SyncSubmission;
use App\Models\User;
use App\Support\AudioUpload;
use App\Support\PhotoUpload;
use finfo;
use getID3;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

// a report's photo and voice note, sent in fixed chunks; the server's own file size is the one true offset
class HealthReportUploadService
{
    public const PHOTO = 'photo';
    public const AUDIO = 'audio';

    // what the content says it is, whatever the phone called it
    private const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const AUDIO_TYPES = ['audio/webm', 'video/webm', 'audio/ogg', 'application/ogg', 'audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/aac', 'audio/mpeg'];

    public function __construct(private readonly ReportReleaseService $release) {}

    // only the farmer's own, found through the record uuid their phone sent; anyone else finds nothing
    public function reportFor(User $user, string $clientUuid): ?DiseaseReport
    {
        $report = SyncSubmission::where('client_uuid', $clientUuid)
            ->where('user_id', $user->id)
            ->where('type', SyncSubmission::TYPE_HEALTH_REPORT)
            ->first()?->diseaseReport;

        return $report !== null && $report->farmerProfile?->user_id === $user->id ? $report : null;
    }

    public function state(DiseaseReport $report, string $kind): array
    {
        if ($this->stored($report, $kind)) {
            return $this->answer('complete', 0);
        }

        $session = $this->session($report, $kind);

        return $this->answer($session ? 'open' : 'none', $session ? $this->offset($session) : 0);
    }

    public function open(User $user, DiseaseReport $report, string $kind, int $size, string $sha256): array
    {
        if ($this->stored($report, $kind)) {
            return $this->answer('complete', 0);
        }

        $max = config($kind === self::PHOTO ? 'health_reports.photo_max_bytes' : 'health_reports.audio_max_bytes');

        if ($size < 1 || $size > $max) {
            throw new HealthUploadRefused(422, 'bad_size');
        }

        $session = $this->session($report, $kind);

        if ($session !== null && $session->total_bytes === $size && $session->sha256 === $sha256) {
            return $this->answer('open', $this->offset($session));
        }

        $open = HealthReportUpload::where('user_id', $user->id)->where('expires_at', '>', now());

        // a session being replaced does not count against the limit
        if ($open->count() - ($session ? 1 : 0) >= config('health_reports.max_open_sessions')) {
            throw new HealthUploadRefused(429, 'too_many_open');
        }

        if ($session !== null) {
            $this->discard($session);
        }

        $session = HealthReportUpload::create([
            'uuid' => (string) Str::uuid7(),
            'disease_report_id' => $report->id,
            'user_id' => $user->id,
            'kind' => $kind,
            'total_bytes' => $size,
            'sha256' => $sha256,
            'part_path' => 'health-report-uploads/' . Str::uuid7() . '.part',
            'expires_at' => now()->addHours(config('health_reports.session_hours')),
        ]);

        Storage::disk('local')->put($session->part_path, '');

        return $this->answer('open', 0);
    }

    public function chunk(DiseaseReport $report, string $kind, int $offset, string $bytes): array
    {
        $result = DB::transaction(function () use ($report, $kind, $offset, $bytes) {
            // a repeat of the last chunk, or any call after completion, changes nothing
            if ($this->stored($report, $kind)) {
                return $this->answer('complete', 0);
            }

            $session = HealthReportUpload::where('disease_report_id', $report->id)->where('kind', $kind)
                ->where('expires_at', '>', now())->lockForUpdate()->first();

            if ($session === null) {
                throw new HealthUploadRefused(404, 'no_session');
            }

            $length = strlen($bytes);

            if ($length < 1 || $length > config('health_reports.chunk_size')) {
                throw new HealthUploadRefused(413, 'bad_chunk');
            }

            $have = $this->offset($session);

            if ($offset !== $have) {
                throw new HealthUploadRefused(409, 'wrong_offset', ['offset' => $have]);
            }

            if ($have + $length > $session->total_bytes) {
                throw new HealthUploadRefused(422, 'too_long');
            }

            file_put_contents(Storage::disk('local')->path($session->part_path), $bytes, FILE_APPEND | LOCK_EX);

            if ($have + $length < $session->total_bytes) {
                return $this->answer('open', $have + $length);
            }

            return $this->finish($report, $session, $kind);
        });

        // a refusal of the finished file is thrown only now, so dropping its partial file and session is kept
        if ($result instanceof HealthUploadRefused) {
            throw $result;
        }

        return $result;
    }

    // the last chunk is in: the whole file is checked before anyone can see any of it
    private function finish(DiseaseReport $report, HealthReportUpload $session, string $kind): array|HealthUploadRefused
    {
        $path = Storage::disk('local')->path($session->part_path);

        if (! hash_equals($session->sha256, hash_file('sha256', $path))) {
            $this->discard($session);

            return new HealthUploadRefused(422, 'bad_checksum');
        }

        $disk = config('filesystems.photo_disk');
        $file = new UploadedFile($path, 'upload', null, null, true);

        try {
            $stored = $kind === self::PHOTO ? $this->storePhoto($file, $path, $disk) : $this->storeAudio($file, $path, $disk);
        } catch (HealthUploadRefused $refused) {
            $this->discard($session);

            return $refused;
        }

        $this->discard($session);

        if ($kind === self::PHOTO) {
            $this->release->release($report->id, $stored, $disk);
        } else {
            DiseaseReport::withoutGlobalScopes()->whereKey($report->id)->update(['audio_path' => $stored, 'media_disk' => $disk]);
        }

        return $this->answer('complete', 0);
    }

    private function storePhoto(UploadedFile $file, string $path, string $disk): string
    {
        if (! in_array((new finfo(FILEINFO_MIME_TYPE))->file($path), self::PHOTO_TYPES, true)) {
            throw new HealthUploadRefused(422, 'bad_type');
        }

        try {
            return PhotoUpload::store($file, 'disease-reports', $disk);
        } catch (Throwable) {
            throw new HealthUploadRefused(422, 'bad_type');
        }
    }

    private function storeAudio(UploadedFile $file, string $path, string $disk): string
    {
        if (! in_array((new finfo(FILEINFO_MIME_TYPE))->file($path), self::AUDIO_TYPES, true)) {
            throw new HealthUploadRefused(422, 'bad_type');
        }

        $info = (new getID3())->analyze($path);
        $seconds = $info['playtime_seconds'] ?? null;

        if (isset($info['video']) || ($seconds !== null && $seconds > config('health_reports.audio_max_seconds'))) {
            throw new HealthUploadRefused(422, 'bad_type');
        }

        return AudioUpload::store($file, 'disease-reports', $disk);
    }

    // a photo is stored once; a voice note counts as stored when the web form already sent the report whole
    private function stored(DiseaseReport $report, string $kind): bool
    {
        return $kind === self::PHOTO
            ? $report->photo_path !== null
            : $report->audio_path !== null || $report->client_uuid !== null;
    }

    private function session(DiseaseReport $report, string $kind): ?HealthReportUpload
    {
        return HealthReportUpload::where('disease_report_id', $report->id)->where('kind', $kind)
            ->where('expires_at', '>', now())->first();
    }

    private function offset(HealthReportUpload $session): int
    {
        return Storage::disk('local')->exists($session->part_path) ? Storage::disk('local')->size($session->part_path) : 0;
    }

    public function discard(HealthReportUpload $session): void
    {
        Storage::disk('local')->delete($session->part_path);
        $session->delete();
    }

    private function answer(string $state, int $offset): array
    {
        return ['state' => $state, 'offset' => $offset, 'chunk_size' => config('health_reports.chunk_size')];
    }
}

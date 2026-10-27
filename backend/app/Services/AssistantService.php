<?php

namespace App\Services;

use App\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// answers how-to questions from the markdown files in resources/assistant; only the typed question leaves the app
class AssistantService
{
    public const DAILY_LIMIT = 50;

    private const INSTRUCTIONS = 'You are the help assistant inside NkwaLedger, a farm record app for farmers in Ghana. '
        . 'Answer in plain, short English that a farmer with little schooling can follow. '
        . 'Use only the knowledge below. If the answer is not there, say you are not sure and ask the person to call the support number. '
        . 'When a page helps, give its link exactly as written in the page list. '
        . 'Never give advice on loans, money, medicines or doses. Never ask for a PIN or password.';

    public function limitReached(User $user): bool
    {
        return AssistantMessage::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count() >= self::DAILY_LIMIT;
    }

    public function ask(User $user, string $question): ?string
    {
        $key = config('services.openrouter.key');

        if (blank($key)) {
            return null;
        }

        try {
            $response = Http::withToken($key)->timeout(30)->retry(1, 500, throw: false)
                ->post(rtrim(config('services.openrouter.url'), '/') . '/chat/completions', [
                    'model' => config('services.assistant.model'),
                    'provider' => ['data_collection' => 'deny'],
                    'messages' => [
                        ['role' => 'system', 'content' => self::INSTRUCTIONS . "\n\n" . $this->knowledge()],
                        ['role' => 'user', 'content' => $question],
                    ],
                ]);

            $reply = $response->successful() ? trim((string) $response->json('choices.0.message.content')) : '';
        } catch (Throwable $e) {
            Log::warning('Assistant request failed', ['error' => $e::class]);

            return null;
        }

        if ($reply === '') {
            return null;
        }

        AssistantMessage::create(['user_id' => $user->id, 'message' => $question, 'reply' => $reply]);

        return $reply;
    }

    private function knowledge(): string
    {
        return collect(File::glob(resource_path('assistant/*.md')))
            ->sort()
            ->map(fn (string $path) => trim(File::get($path)))
            ->implode("\n\n");
    }
}

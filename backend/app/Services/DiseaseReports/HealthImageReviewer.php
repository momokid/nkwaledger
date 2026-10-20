<?php

namespace App\Services\DiseaseReports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// asks the vision model for a draft only; any failure gives null so a report never depends on the AI
class HealthImageReviewer
{
    private const INSTRUCTIONS = 'You help an extension officer in Ghana review a farmer\'s report. '
        . 'Look at the photo and the description. Pick the one category that fits best from the allowed list. '
        . 'Say whether the photo is usable (clear and showing the plant, animal or fish). '
        . 'Set urgent to true for signs that could spread or kill quickly, such as several animals sick or dying at once. '
        . 'Give a confidence between 0 and 1. Write a short, plain note about what you can see. '
        . 'Never name a product, medicine or dose.';

    public function review(string $imageBytes, string $mime, string $description, string $farmCategory, array $categories): ?HealthReview
    {
        $key = config('services.openrouter.key');

        if (blank($key)) {
            return null;
        }

        $model = config('services.openrouter.model');

        try {
            $response = Http::withToken($key)->timeout(45)->retry(1, 500, throw: false)
                ->post(rtrim(config('services.openrouter.url'), '/') . '/chat/completions', [
                    'model' => $model,
                    // photos are not kept or used for training by the provider
                    'provider' => ['data_collection' => 'deny'],
                    'messages' => [
                        ['role' => 'system', 'content' => self::INSTRUCTIONS],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => "Farm type: {$farmCategory}\nDescription: {$description}\nAllowed categories: " . implode(', ', $categories)],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64," . base64_encode($imageBytes)]],
                        ]],
                    ],
                    'response_format' => ['type' => 'json_schema', 'json_schema' => [
                        'name' => 'health_review',
                        'strict' => true,
                        'schema' => $this->schema($categories),
                    ]],
                ]);

            if (! $response->successful()) {
                Log::warning('Health review request failed', ['status' => $response->status()]);

                return null;
            }

            return $this->parse((string) $response->json('choices.0.message.content'), $categories, $model);
        } catch (Throwable $e) {
            Log::warning('Health review failed', ['error' => $e::class]);

            return null;
        }
    }

    private function parse(string $content, array $categories, string $model): ?HealthReview
    {
        $data = json_decode($content, true);

        if (! is_array($data) || ! is_bool($data['photo_usable'] ?? null) || ! is_bool($data['urgent'] ?? null)) {
            return null;
        }

        if ($data['photo_usable']) {
            $confidence = $data['confidence'] ?? null;

            if (! in_array($data['category'] ?? null, $categories, true) || ! is_numeric($confidence) || $confidence < 0 || $confidence > 1) {
                return null;
            }
        }

        return new HealthReview(
            $data['photo_usable'],
            (string) ($data['category'] ?? ''),
            (float) ($data['confidence'] ?? 0),
            $data['urgent'],
            (string) ($data['note'] ?? ''),
            $model,
        );
    }

    private function schema(array $categories): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['photo_usable', 'category', 'confidence', 'urgent', 'note'],
            'properties' => [
                'photo_usable' => ['type' => 'boolean'],
                'category' => ['type' => 'string', 'enum' => array_values($categories)],
                'confidence' => ['type' => 'number'],
                'urgent' => ['type' => 'boolean'],
                'note' => ['type' => 'string'],
            ],
        ];
    }
}

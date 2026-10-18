<?php

namespace App\Services\Ussd;

// the screens a farmer reads over USSD, from resources/ussd.{language}.json. A screen missing in a language reads in English.
// One shared instance per request, so choosing a language once changes every screen after it
class UssdText
{
    public const LANGUAGES = ['en', 'tw'];

    private array $maps = [];
    private string $language = 'en';

    public function use(?string $language): void
    {
        $this->language = in_array($language, self::LANGUAGES, true) ? $language : 'en';
    }

    public function in(string $language): static
    {
        $copy = clone $this;
        $copy->use($language);

        return $copy;
    }

    public function get(string $key, array $with = []): string
    {
        $text = $this->map($this->language)[$key] ?? $this->map('en')[$key];

        return $with === [] ? $text : strtr($text, array_combine(array_map(fn($name) => '{' . $name . '}', array_keys($with)), array_map('strval', $with)));
    }

    private function map(string $language): array
    {
        return $this->maps[$language] ??= json_decode(file_get_contents(resource_path("ussd.{$language}.json")), true, flags: JSON_THROW_ON_ERROR);
    }
}

<?php

namespace App\Services\Ussd;

// the screens a farmer reads over USSD, from resources/ussd.en.json so a Twi file can sit beside it later
class UssdText
{
    private ?array $map = null;

    public function get(string $key, array $with = []): string
    {
        $this->map ??= json_decode(file_get_contents(resource_path('ussd.en.json')), true, flags: JSON_THROW_ON_ERROR);

        return strtr($this->map[$key], array_combine(array_map(fn($name) => '{' . $name . '}', array_keys($with)), array_map('strval', $with)));
    }
}

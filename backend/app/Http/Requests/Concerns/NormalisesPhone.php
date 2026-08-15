<?php

namespace App\Http\Requests\Concerns;

use App\Support\Phone;

trait NormalisesPhone
{
    // rewrites the field before any rule, lookup or unique check sees it
    protected function normalisePhoneField(string $field = 'phone'): void
    {
        if (! $this->has($field)) {
            return;
        }

        $raw = $this->input($field);

        if (! is_string($raw)) {
            return;
        }

        // an unusable number is left alone so validation can reject it with a clear message
        $this->merge([$field => Phone::normalise($raw) ?? $raw]);
    }

    // one definition of what a usable Ghanaian mobile number is
    protected function phoneRules(array $extra = []): array
    {
        return array_merge(['required', 'string', 'regex:/^\+233[0-9]{9}$/'], $extra);
    }

    protected function phoneMessages(string $field = 'phone'): array
    {
        return [
            "{$field}.regex" => 'That doesn\'t look like a Ghanaian mobile number. Try it the way you\'d dial it, like 0244445566.',
        ];
    }
}

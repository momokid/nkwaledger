<?php

namespace App\Http\Requests\Concerns;

use App\Support\Phone;
use Closure;

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

    // defers to the normaliser rather than restating it, so a landline or an unissued prefix cannot slip through
    protected function phoneRules(array $extra = []): array
    {
        $check = function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || Phone::normalise($value) === null) {
                $fail('That doesn\'t look like a Ghanaian mobile number. Try it the way you\'d dial it, like 0244445566.');
            }
        };

        return array_merge(['required', 'string', $check], $extra);
    }

    protected function phoneMessages(string $field = 'phone'): array
    {
        return [];
    }
}

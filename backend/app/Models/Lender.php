<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// who a loan was borrowed from: an institution in one shared list that only an admin adds to. Shared across
// farmers, so nothing here may ever show one farmer another's loans
class Lender extends Model
{
    public const TYPES = ['bank', 'rural_bank', 'credit_financial_institution', 'microfinance', 'other'];

    protected $fillable = ['uuid', 'type', 'name', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $lender) {
            $lender->uuid ??= (string) Str::uuid7();
        });

        static::saving(function (self $lender) {
            if (! $lender->exists || $lender->isDirty('name')) {
                $lender->forceFill(self::derived($lender->name));
            }
        });
    }

    /**
     * What a name decides: its tidy spelling, the key two spellings share, and the key it is searched and sorted by.
     * A plain method, not only a model event, so the seeder (which runs with events off) can set them too.
     *
     * @return array{name: string, name_key: string, sort_key: string}
     */
    public static function derived(string $name): array
    {
        $name = self::squish($name);

        return ['name' => $name, 'name_key' => self::keyOf($name), 'sort_key' => self::sortKeyOf($name)];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function squish(string $name): string
    {
        return (string) Str::of($name)->squish();
    }

    // the key a name is searched and sorted by: lower case, one space between words, accents folded to plain letters.
    // A character with no plain letter is kept as it is (the folding library alone would drop it)
    public static function sortKeyOf(string $name): string
    {
        $key = self::keyOf($name);

        if (! preg_match('/[^[:ascii:]]/', $key)) {
            return $key;
        }

        $folded = '';

        foreach (mb_str_split($key) as $char) {
            $plain = Str::ascii($char);
            $folded .= $plain === '' ? $char : $plain;
        }

        return self::squish($folded);
    }

    // true when a character is left that has no plain-letter form: such a name is not accepted from an admin
    public static function hasUnfoldable(string $name): bool
    {
        return (bool) preg_match('/[^[:ascii:]]/', self::sortKeyOf($name));
    }

    // one spelling for comparing names: case and extra spaces do not matter
    public static function keyOf(string $name): string
    {
        return mb_strtolower(self::squish($name));
    }
}

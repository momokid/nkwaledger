<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// who a loan was borrowed from: an institution in one shared list that only an admin adds to. Shared across
// farmers, so nothing here may ever show one farmer another's loans
class Lender extends Model
{
    public const TYPES = ['bank', 'microfinance', 'credit_union', 'other'];

    protected $fillable = ['uuid', 'type', 'name', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $lender) {
            $lender->uuid ??= (string) Str::uuid7();
        });

        static::saving(function (self $lender) {
            $lender->forceFill(self::derived($lender->name));
        });
    }

    /**
     * What a name decides: its tidy spelling and the key two spellings share. A plain method, not only a model
     * event, so the seeder (which runs with events off) can set them too.
     *
     * @return array{name: string, name_key: string}
     */
    public static function derived(string $name): array
    {
        $name = self::squish($name);

        return ['name' => $name, 'name_key' => self::keyOf($name)];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type === null ? $query : $query->where('type', $type);
    }

    public static function squish(string $name): string
    {
        return (string) Str::of($name)->squish();
    }

    // one spelling for comparing names: case and extra spaces do not matter
    public static function keyOf(string $name): string
    {
        return mb_strtolower(self::squish($name));
    }
}

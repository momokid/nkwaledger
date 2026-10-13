<?php

namespace App\Services\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// who a loan was borrowed from. A lender is an institution picked from the list an admin keeps: a farmer or an agent
// can search it and pick from it, and can never add to it
class LenderService
{
    public const SEARCH_LIMIT = 10;
    public const SEARCH_MIN = 2;

    // placeholders: the wording for these is not approved yet
    public const NOT_IN_LIST = 'lender.not_in_list';
    public const NAME_CHARACTERS = 'lender.name_characters';
    public const NAME_TAKEN = 'lender.institution_exists';

    public function __construct(private readonly AuditService $audit) {}

    /**
     * Found and ordered by sort_key, compared by bytes, so the order is the same on every database: names that start
     * with the typed text first, then the rest A to Z, then name_key so no two rows ever tie.
     *
     * @return Collection<int, array{uuid: string, name: string, type: string}>
     */
    public function search(string $term): Collection
    {
        $key = Lender::sortKeyOf($term);

        if (mb_strlen($key) < self::SEARCH_MIN) {
            return collect();
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key);
        // the byte-order clause is one of two fixed strings chosen by the driver, never anything typed
        $bytes = DB::getDriverName() === 'pgsql' ? 'collate "C"' : 'collate binary';

        return Lender::whereRaw("sort_key like ? escape '\\'", ['%' . $escaped . '%'])
            ->orderByRaw("case when sort_key like ? escape '\\' then 0 else 1 end", [$escaped . '%'])
            ->orderByRaw("sort_key {$bytes}")
            ->orderByRaw("name_key {$bytes}")
            ->limit(self::SEARCH_LIMIT)
            ->get(['uuid', 'name', 'type'])
            ->map(fn(Lender $lender) => ['uuid' => $lender->uuid, 'name' => $lender->name, 'type' => $lender->type]);
    }

    /**
     * Every lender rule, with nothing written. The same call runs when an agent records a loan and again when it is
     * accepted, so what was fine at entry can still be refused later. Only the picked id counts: a typed name or type
     * is never read.
     *
     * @return array{lender: string}
     */
    public function check(array $input): array
    {
        return ['lender' => $this->picked($input['lender'] ?? null)->uuid];
    }

    // the lender record a checked loan links to
    public function resolve(array $checked): Lender
    {
        return $this->picked($checked['lender'] ?? null);
    }

    public function addInstitution(string $type, string $name, User $by): Lender
    {
        $name = Lender::squish($name);

        if (! in_array($type, Lender::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'lender.type_invalid']);
        }

        // two admins adding twins at once both pass the check; the unique index on sort_key lets only one in
        try {
            return DB::transaction(function () use ($type, $name, $by) {
                $this->mustBeNew($name, null);

                $lender = Lender::create(['type' => $type, 'name' => $name, 'created_by' => $by->id]);

                $this->audit->recordOn('lender.created', $lender, null, ['uuid' => $lender->uuid, 'type' => $lender->type, 'name' => $lender->name], $by);

                return $lender;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => self::NAME_TAKEN]);
        }
    }

    /**
     * A new name, a new type, or both. The row is locked and read again first, so two admins at once cannot log a
     * wrong "before". A type change never touches the name or any loan.
     */
    public function update(Lender $lender, ?string $name, ?string $type, User $by): Lender
    {
        if ($type !== null && ! in_array($type, Lender::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'lender.type_invalid']);
        }

        try {
            return $this->updateLocked($lender, $name, $type, $by);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => self::NAME_TAKEN]);
        }
    }

    private function updateLocked(Lender $lender, ?string $name, ?string $type, User $by): Lender
    {
        return DB::transaction(function () use ($lender, $name, $type, $by) {
            $locked = Lender::whereKey($lender->id)->lockForUpdate()->firstOrFail();
            $before = [];
            $after = [];

            if ($name !== null && Lender::squish($name) !== $locked->name) {
                $name = Lender::squish($name);
                $this->mustBeNew($name, $locked);
                $before['name'] = $locked->name;
                $after['name'] = $name;
            }

            if ($type !== null && $type !== $locked->type) {
                $before['type'] = $locked->type;
                $after['type'] = $type;
            }

            if ($after !== []) {
                $locked->forceFill($after)->save();
                $this->audit->recordOn('lender.updated', $locked, $before, $after, $by);
            }

            return $locked;
        });
    }

    // a lender is only ever found by its public id; its type is whatever the list says
    private function picked(mixed $uuid): Lender
    {
        $lender = is_string($uuid) && Str::isUuid($uuid) ? Lender::where('uuid', $uuid)->first() : null;

        if ($lender === null) {
            throw PostingFailed::because(self::NOT_IN_LIST);
        }

        return $lender;
    }

    private function mustBeNew(string $name, ?Lender $except): void
    {
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'lender.name_required']);
        }

        // placeholder: a name with a letter that has no plain form cannot be matched or sorted
        if (Lender::hasUnfoldable($name)) {
            throw ValidationException::withMessages(['name' => self::NAME_CHARACTERS]);
        }

        $clash = Lender::where(fn($q) => $q->where('name_key', Lender::keyOf($name))->orWhere('sort_key', Lender::sortKeyOf($name)))
            ->when($except, fn($q) => $q->whereKeyNot($except->id))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages(['name' => self::NAME_TAKEN]);
        }
    }
}

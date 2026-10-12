<?php

namespace App\Services\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

// who a loan was borrowed from. A lender is an institution picked from the list an admin keeps: a farmer or an agent
// can search it and pick from it, and can never add to it
class LenderService
{
    public const SEARCH_LIMIT = 10;
    public const SEARCH_MIN = 2;

    // placeholders: the wording for these is not approved yet
    public const NOT_IN_LIST = 'lender.not_in_list';

    public function __construct(private readonly AuditService $audit) {}

    /** @return Collection<int, array{uuid: string, name: string, type: string}> */
    public function search(string $term, ?string $type = null): Collection
    {
        $key = Lender::keyOf($term);

        if (mb_strlen($key) < self::SEARCH_MIN) {
            return collect();
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key) . '%';

        return Lender::ofType($type)
            ->whereRaw("name_key like ? escape '\\'", [$like])
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get(['uuid', 'name', 'type'])
            ->map(fn(Lender $lender) => ['uuid' => $lender->uuid, 'name' => $lender->name, 'type' => $lender->type]);
    }

    /**
     * Every lender rule, with nothing written. The same call runs when an agent records a loan and again when it is
     * accepted, so what was fine at entry can still be refused later.
     *
     * @return array{lender_type: string, lender: string, lender_name: string} the name is the list's, not the typed one
     */
    public function check(array $input): array
    {
        $type = $input['lender_type'] ?? null;

        if (! in_array($type, Loan::LENDER_TYPES, true)) {
            throw new InvalidArgumentException('A loan needs a lender name and a lender type.');
        }

        if (Lender::squish((string) ($input['lender_name'] ?? '')) === '') {
            throw new InvalidArgumentException('A loan needs a lender name and a lender type.');
        }

        $lender = $this->picked($input['lender'] ?? null, $type);

        return ['lender_type' => $lender->type, 'lender' => $lender->uuid, 'lender_name' => $lender->name];
    }

    // the lender record a checked loan links to
    public function resolve(array $checked): Lender
    {
        return $this->picked($checked['lender'] ?? null, $checked['lender_type']);
    }

    public function addInstitution(string $type, string $name, User $by): Lender
    {
        $name = Lender::squish($name);

        if (! in_array($type, Lender::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'lender.type_invalid']);
        }

        $this->mustBeNew($name, null);

        $lender = Lender::create(['type' => $type, 'name' => $name, 'created_by' => $by->id]);

        $this->audit->recordOn('lender.created', $lender, null, ['uuid' => $lender->uuid, 'type' => $lender->type, 'name' => $lender->name], $by);

        return $lender;
    }

    public function rename(Lender $lender, string $name, User $by): Lender
    {
        $name = Lender::squish($name);

        $this->mustBeNew($name, $lender);

        $before = $lender->name;
        $lender->forceFill(['name' => $name])->save();

        $this->audit->recordOn('lender.updated', $lender, ['name' => $before], ['name' => $lender->name], $by);

        return $lender;
    }

    // a lender is only ever found by its public id, and only if it is of the type the farmer chose
    private function picked(mixed $uuid, string $type): Lender
    {
        $lender = is_string($uuid) && Str::isUuid($uuid) ? Lender::ofType($type)->where('uuid', $uuid)->first() : null;

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

        $clash = Lender::where('name_key', Lender::keyOf($name))->when($except, fn($q) => $q->whereKeyNot($except->id))->exists();

        if ($clash) {
            throw ValidationException::withMessages(['name' => 'lender.institution_exists']);
        }
    }
}

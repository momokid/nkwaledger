<?php

namespace App\Services\Loans;

use App\Models\FarmerProfile;
use App\Models\Lender;
use App\Models\LenderRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Support\Wording;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// a request for a lender that is not in the list. A farmer, or an agent for a farmer, sends it; the admin adds the lender,
// points it at one already in the list, or rejects it with a reason. The person who asked is told, and every step is audit-logged
class LenderRequestService
{
    public const WAITING = 'lender.request_waiting';

    public function __construct(
        private readonly LenderService $lenders,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function submit(User $by, FarmerProfile $farmer, string $name, string $type, ?string $note): LenderRequest
    {
        $name = Lender::squish($name);

        if (! in_array($type, Lender::TYPES, true)) {
            throw ValidationException::withMessages(['type' => Wording::get('lender.type_invalid')]);
        }

        $this->lenders->mustBeNew($name, null);

        if (LenderRequest::where('status', LenderRequest::PENDING)->where('name_key', Lender::keyOf($name))->exists()) {
            throw ValidationException::withMessages(['name' => Wording::get(self::WAITING)]);
        }

        // two people sending the same name at once both pass the check; the unique index lets only one in
        try {
            return DB::transaction(function () use ($by, $farmer, $name, $type, $note) {
                $request = LenderRequest::create([
                    'farmer_profile_id' => $farmer->id,
                    'requested_by' => $by->id,
                    'name' => $name,
                    'name_key' => Lender::keyOf($name),
                    'type' => $type,
                    'note' => $note === null || trim($note) === '' ? null : trim($note),
                ]);

                $this->audit->recordOn('lender_request.created', $request, null, ['uuid' => $request->uuid, 'name' => $request->name, 'type' => $request->type], $by);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => Wording::get(self::WAITING)]);
        }
    }

    public function add(LenderRequest $request, User $admin): LenderRequest
    {
        return DB::transaction(function () use ($request, $admin) {
            $locked = $this->lock($request);
            // a name that has been added another way meanwhile is refused here, and the request stays waiting
            $lender = $this->lenders->addInstitution($locked->type, $locked->name, $admin);

            return $this->close($locked, LenderRequest::ADDED, $admin, $lender, null, Wording::get('lender.request_added', ['lender' => $locked->name]));
        });
    }

    public function useExisting(LenderRequest $request, Lender $lender, User $admin): LenderRequest
    {
        return DB::transaction(function () use ($request, $lender, $admin) {
            $locked = $this->lock($request);

            return $this->close($locked, LenderRequest::MERGED, $admin, $lender, null, Wording::get('lender.request_merged', ['lender' => $locked->name, 'existing' => $lender->name]));
        });
    }

    public function reject(LenderRequest $request, string $reason, User $admin): LenderRequest
    {
        return DB::transaction(function () use ($request, $reason, $admin) {
            $locked = $this->lock($request);

            return $this->close($locked, LenderRequest::REJECTED, $admin, null, $reason, Wording::get('lender.request_rejected', ['lender' => $locked->name, 'reason' => $reason]));
        });
    }

    // the row is locked and read again, so two admins answering at once cannot both win
    private function lock(LenderRequest $request): LenderRequest
    {
        $locked = LenderRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isPending()) {
            throw ValidationException::withMessages(['request' => Wording::FALLBACK]);
        }

        return $locked;
    }

    private function close(LenderRequest $request, string $status, User $admin, ?Lender $lender, ?string $reason, string $message): LenderRequest
    {
        $request->forceFill([
            'status' => $status,
            'lender_id' => $lender?->id,
            'decision_note' => $reason,
            'decided_by' => $admin->id,
            'decided_at' => now(),
        ])->save();

        $this->audit->recordOn("lender_request.{$status}", $request, ['status' => LenderRequest::PENDING], array_filter([
            'status' => $status, 'lender' => $lender?->uuid, 'reason' => $reason,
        ]), $admin);

        $asker = $request->requestedBy;

        if ($asker !== null) {
            // an agent goes back to their own page for the farmer; a farmer to their loans
            $link = $asker->hasRole('agent') ? "/agent/farmers/{$request->farmerProfile->uuid}" : '/loans';
            $this->notifications->send($asker, 'loan.lender_request', $message, $link);
        }

        return $request;
    }
}

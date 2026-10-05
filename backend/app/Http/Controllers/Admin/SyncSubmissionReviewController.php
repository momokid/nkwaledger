<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectionRequest;
use App\Models\SyncSubmission;
use App\Models\TransactionTemplate;
use App\Services\AccessControlService;
use App\Services\SyncSubmissionService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SyncSubmissionReviewController extends Controller
{
    public function __construct(
        private readonly SyncSubmissionService $sync,
        private readonly AccessControlService $access,
    ) {}

    // read-only: what is waiting for an admin, with only the public uuid of each row
    public function index(Request $request): Response
    {
        $held = SyncSubmission::query()
            ->where('status', SyncSubmission::HELD)
            ->with(['farmerProfile.user:id,surname,first_name', 'user:id,surname,first_name'])
            ->latest('received_at')
            ->latest('id')
            ->paginate(20);

        // one lookup for every template on the page, not one per row
        $templates = TransactionTemplate::withTrashed()
            ->whereIn('id', $held->getCollection()->map(fn(SyncSubmission $row) => (int) ($row->payload['template'] ?? 0)))
            ->pluck('name', 'id');

        return Inertia::render('Admin/SyncSubmissions/Index', [
            // which buttons to show; the routes still check the permission themselves
            'permissions' => [
                'approve' => $this->access->can($request->user(), 'sync-submissions.approve'),
                'reject' => $this->access->can($request->user(), 'sync-submissions.reject'),
            ],
            'submissions' => $held->through(fn(SyncSubmission $row) => [
                'uuid' => $row->uuid,
                'farmer' => $row->farmerProfile === null
                    ? 'Unknown'
                    : trim("{$row->farmerProfile->user?->surname} {$row->farmerProfile->user?->first_name}"),
                'submitted_by' => trim("{$row->user?->surname} {$row->user?->first_name}"),
                'record' => $templates[(int) ($row->payload['template'] ?? 0)] ?? 'Unknown',
                'amount' => $this->amount($row->payload['amount'] ?? null),
                'event_date' => $row->device_date->toDateString(),
                'received_at' => $row->received_at->toIso8601String(),
                'reason' => $row->reason,
            ]),
        ]);
    }

    // the device sent text, so an unreadable amount shows as nothing rather than breaking the page
    private function amount(mixed $value): ?string
    {
        try {
            return Money::format(Money::toMinor((string) $value));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function approve(Request $request, SyncSubmission $submission): JsonResponse
    {
        // only a system failure gets here; the row is left held so approving can be tried again
        try {
            $approved = $this->sync->approve($submission, $request->user());
        } catch (PostingFailed $failure) {
            return response()->json(['message' => $failure->getMessage()], 503);
        }

        return response()->json($this->sync->result($approved->load('transaction:id,reference')));
    }

    public function reject(RejectionRequest $request, SyncSubmission $submission): JsonResponse
    {
        return response()->json($this->sync->result($this->sync->reject($submission, $request->user(), $request->validated('reason'))));
    }
}

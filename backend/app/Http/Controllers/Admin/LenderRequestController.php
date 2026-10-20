<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Models\Lender;
use App\Models\LenderRequest;
use App\Services\Loans\LenderRequestService;
use App\Services\Loans\LenderService;
use App\Support\Wording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

// the admin's queue of lender requests: the waiting ones, answered in place. Admin only
class LenderRequestController extends Controller
{
    public function __construct(
        private readonly LenderRequestService $requests,
        private readonly LenderService $lenders,
    ) {}

    public function index(Request $request): JsonResponse|Response
    {
        // ponytail: every waiting request is listed; page it if the queue ever runs to hundreds
        $rows = LenderRequest::query()->with('requestedBy')->where('status', LenderRequest::PENDING)->orderBy('id')->get()
            ->map(fn(LenderRequest $row) => [
                'uuid' => $row->uuid,
                'requested_by' => trim("{$row->requestedBy?->surname} {$row->requestedBy?->first_name}"),
                'name' => $row->name,
                'type' => $row->type,
                'note' => $row->note,
                'date' => $row->created_at->toDateString(),
            ])->values();

        if ($request->expectsJson()) {
            return response()->json(['rows' => $rows]);
        }

        return Inertia::render('Admin/LenderRequests/Index', ['rows' => $rows, 'types' => Lender::TYPES]);
    }

    public function add(Request $request, LenderRequest $lenderRequest): JsonResponse
    {
        $this->requests->add($lenderRequest, $request->user());

        return response()->json(['ok' => true]);
    }

    public function useExisting(Request $request, LenderRequest $lenderRequest): JsonResponse
    {
        $notListed = Wording::get('lender.not_in_list');
        $data = $request->validate(['lender' => ['required', 'uuid']], ['lender.required' => $notListed, 'lender.uuid' => $notListed]);

        try {
            $lender = $this->lenders->resolve(['lender' => $data['lender']]);
        } catch (PostingFailed $failure) {
            throw ValidationException::withMessages(['lender' => $failure->getMessage()]);
        }

        $this->requests->useExisting($lenderRequest, $lender, $request->user());

        return response()->json(['ok' => true]);
    }

    public function reject(Request $request, LenderRequest $lenderRequest): JsonResponse
    {
        $words = Wording::FALLBACK;
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => $words, 'reason.string' => $words, 'reason.max' => $words]);

        $this->requests->reject($lenderRequest, $data['reason'], $request->user());

        return response()->json(['ok' => true]);
    }
}

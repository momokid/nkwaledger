<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectionRequest;
use App\Models\SyncSubmission;
use App\Services\SyncSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncSubmissionReviewController extends Controller
{
    public function __construct(private readonly SyncSubmissionService $sync) {}

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

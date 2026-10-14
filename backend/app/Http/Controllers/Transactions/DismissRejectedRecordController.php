<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\SyncSubmission;
use App\Services\SyncSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DismissRejectedRecordController extends Controller
{
    public function __construct(private readonly SyncSubmissionService $sync) {}

    public function __invoke(Request $request, SyncSubmission $submission): JsonResponse
    {
        $this->sync->dismiss($request->user(), $submission);

        return response()->json(['dismissed' => true]);
    }
}

<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\SyncBatchRequest;
use App\Models\FarmerProfile;
use App\Models\SyncSubmission;
use App\Services\SyncSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SyncSubmissionController extends Controller
{
    public function __construct(private readonly SyncSubmissionService $sync) {}

    // in the order sent, each record on its own
    public function store(SyncBatchRequest $request): JsonResponse
    {
        $results = collect($request->validated('records'))
            ->map(fn(array $record) => $this->sync->submit($request->user(), $record))
            ->all();

        return response()->json(['results' => $results]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $this->sync->visibleTo($user);

        if ($request->filled('farmer')) {
            $uuid = (string) $request->query('farmer');
            $farmer = Str::isUuid($uuid) ? FarmerProfile::where('uuid', $uuid)->first() : null;

            // a farmer out of reach is simply not there
            abort_if($farmer === null || ! $this->sync->reachableFarmerIds($user)->whereKey($farmer->id)->exists(), 404);

            $query->where('farmer_profile_id', $farmer->id);
        }

        $rows = $query->with('transaction:id,reference')->latest('received_at')->latest('id')->paginate(50)
            ->through(fn(SyncSubmission $submission) => $this->sync->result($submission) + [
                'device_date' => $submission->device_date->toDateString(),
                'received_at' => $submission->received_at->toIso8601String(),
            ]);

        return response()->json($rows);
    }
}

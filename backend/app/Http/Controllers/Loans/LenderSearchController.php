<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\Lender;
use App\Services\Loans\LenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

// the search box on the loan form. A POST, so what is typed never lands in an address or a log. Institutions only
class LenderSearchController extends Controller
{
    public function __construct(private readonly LenderService $lenders) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['farmer', 'agent', 'admin']), 404);

        $check = Validator::make($request->only('q', 'type'), [
            'q' => ['required', 'string', 'min:' . LenderService::SEARCH_MIN, 'max:100'],
            'type' => ['nullable', 'string', Rule::in(Lender::TYPES)],
        ]);

        if ($check->fails()) {
            return response()->json(['errors' => $check->errors()], 422);
        }

        return response()->json($this->lenders->search($check->validated()['q'], $check->validated()['type'] ?? null)->values());
    }
}

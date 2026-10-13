<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Services\Loans\LenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// the search box on the loan form. A POST, so what is typed never lands in an address or a log. Institutions only
class LenderSearchController extends Controller
{
    public function __construct(private readonly LenderService $lenders) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['farmer', 'agent', 'admin']), 404);

        $check = Validator::make($request->only('q'), [
            'q' => ['required', 'string', 'min:' . LenderService::SEARCH_MIN, 'max:100'],
        ]);

        if ($check->fails()) {
            return response()->json(['errors' => $check->errors()], 422);
        }

        return response()->json($this->lenders->search($check->validated()['q'])->values());
    }
}

<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\Lender;
use App\Services\Loans\LenderRequestService;
use App\Support\Wording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// a farmer, or an agent for one of their own farmers, asks for a lender that is not in the list
class LenderRequestController extends Controller
{
    public function __construct(private readonly LenderRequestService $requests) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->hasAnyRole(['farmer', 'agent']), 404);

        $words = Wording::FALLBACK;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', Lender::TYPES)],
            'note' => ['nullable', 'string', 'max:500'],
            'farmer' => [$user->hasRole('agent') ? 'required' : 'nullable', 'uuid'],
        ], [
            'name.required' => Wording::get('lender.name_required'), 'name.string' => $words, 'name.max' => $words,
            'type.required' => Wording::get('lender.type_invalid'), 'type.in' => Wording::get('lender.type_invalid'), 'type.string' => $words,
            'note.string' => $words, 'note.max' => $words, 'farmer.required' => $words, 'farmer.uuid' => $words,
        ]);

        // a farmer asks for themselves whatever is sent; an agent only for a farmer assigned to them (anyone else's does not exist)
        $farmer = $user->hasRole('agent')
            ? FarmerProfile::where('uuid', $data['farmer'])->where('assigned_agent_id', $user->id)->firstOrFail()
            : FarmerProfile::where('user_id', $user->id)->firstOrFail();

        $this->requests->submit($user, $farmer, $data['name'], $data['type'], $data['note'] ?? null);

        return response()->json(['ok' => true], 201);
    }
}

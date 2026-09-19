<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\Region;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RegionDetailController extends Controller
{
    public function show(Request $request, Region $region): JsonResponse
    {
        $from = $request->query('from', Carbon::now()->subDays(29)->toDateString());
        $to = $request->query('to', Carbon::now()->toDateString());

        $farmers = FarmerProfile::query()
            ->whereHas('community.district', fn($query) => $query->where('region_id', $region->id))
            ->with(['user:id,surname,first_name', 'community:id,name'])
            ->get();

        $incomeByFarmer = Transaction::query()
            ->whereIn('farmer_profile_id', $farmers->pluck('id'))
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->where('transaction_type', 'INCOME')
            ->selectRaw('farmer_profile_id, SUM(amount_minor) as total')
            ->groupBy('farmer_profile_id')
            ->pluck('total', 'farmer_profile_id');

        $expenseByFarmer = Transaction::query()
            ->whereIn('farmer_profile_id', $farmers->pluck('id'))
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->where('transaction_type', 'EXPENSE')
            ->selectRaw('farmer_profile_id, SUM(amount_minor) as total')
            ->groupBy('farmer_profile_id')
            ->pluck('total', 'farmer_profile_id');

        return response()->json([
            'region_name' => $region->name,
            'farmers' => $farmers->map(fn($farmer) => [
                'id' => $farmer->uuid,
                'name' => trim("{$farmer->user?->surname} {$farmer->user?->first_name}"),
                'community' => $farmer->community?->name,
                'income' => (int) ($incomeByFarmer[$farmer->id] ?? 0),
                'expense' => (int) ($expenseByFarmer[$farmer->id] ?? 0),
            ])->values()->all(),
        ]);
    }
}

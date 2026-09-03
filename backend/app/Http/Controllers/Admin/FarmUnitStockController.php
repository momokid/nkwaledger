<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MovementReason;
use App\Enums\StockSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectionRequest;
use App\Http\Requests\Admin\StoreFarmUnitStockRequest;
use App\Http\Requests\Admin\StoreStockMovementRequest;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;


class FarmUnitStockController extends Controller
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request, FarmerProfile $farmer, FarmUnit $farmUnit): Response
    {
        $this->guardFarmer($request->user(), $farmer);
        $this->guardBelongsTo($farmer, $farmUnit);

        $farmer->load('user:id,surname,first_name');
        $farmUnit->load('farmType.category:id,name');

        $actorId = $request->user()->id;

        return Inertia::render('Admin/FarmUnits/Stocks', [
            'farmer' => [
                'id' => $farmer->uuid,
                'name' => "{$farmer->user?->surname} {$farmer->user?->first_name}",
            ],
            'unit' => [
                'id' => $farmUnit->id,
                'name' => $farmUnit->name,
                'farm_type' => $farmUnit->farmType?->name,
                'farm_type_category' => $farmUnit->farmType?->category?->name,
                'is_approved' => $farmUnit->isApproved(),
            ],
            'stocks' => $farmUnit->stocks()
                ->with(['recordedBy:id,surname', 'confirmedBy:id,surname', 'movements.recordedBy:id,surname'])
                ->orderByDesc('started_on')
                ->get()
                ->map(fn(FarmUnitStock $stock) => [
                    'id' => $stock->id,
                    'source' => $stock->source->label(),
                    'opening_quantity' => $stock->opening_quantity,
                    'current_quantity' => $stock->current_quantity,
                    'unit_of_measure' => $stock->unit_of_measure,
                    'acquisition_cost' => $stock->acquisition_cost,
                    'cost_per_unit' => $stock->costPerUnit(),
                    'started_on' => $stock->started_on?->toDateString(),
                    'expected_ready_on' => $stock->expected_ready_on?->toDateString(),
                    'ended_on' => $stock->ended_on?->toDateString(),
                    'is_confirmed' => $stock->isConfirmed(),
                    'confirmed_by' => $stock->confirmedBy?->surname,
                    'is_rejected' => $stock->isRejected(),
                    'rejection_reason' => $stock->rejection_reason,
                    'counts_toward_credit' => $stock->countsTowardCredit(),
                    'can_confirm' => $stock->conflictedUserId() !== $actorId,
                    'movements' => $stock->movements
                        ->sortByDesc('occurred_on')
                        ->values()
                        ->map(fn(FarmUnitStockMovement $movement) => [
                            'id' => $movement->id,
                            'reason' => $movement->reason->label(),
                            'quantity' => $movement->quantity,
                            'is_increase' => $movement->is_increase,
                            'occurred_on' => $movement->occurred_on?->toDateString(),
                            'note' => $movement->note,
                            'recorded_by' => $movement->recordedBy?->surname,
                            'is_confirmed' => $movement->isConfirmed(),
                            'is_rejected' => $movement->isRejected(),
                            'rejection_reason' => $movement->rejection_reason,
                            'can_confirm' => $movement->conflictedUserId() !== $actorId,
                        ]),
                ]),
            ...$this->frame($request),
            'permissions' => [
                'create' => $this->access->can($request->user(), 'farm-units.create'),
                'confirm' => $this->access->can($request->user(), 'farm-units.confirm'),
            ],
            'sources' => StockSource::options(),
            'reasons' => MovementReason::options(),
        ]);
    }

    public function storeStock(StoreFarmUnitStockRequest $request, FarmerProfile $farmer, FarmUnit $farmUnit): RedirectResponse
    {
        $this->guardFarmer($request->user(), $farmer);
        $this->guardBelongsTo($farmer, $farmUnit);

        $farmUnit->stocks()->create([
            ...$request->validated(),
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'The count is saved. Someone else needs to check it.');
    }

    public function confirmStock(Request $request, FarmerProfile $farmer, FarmUnit $farmUnit, FarmUnitStock $stock): RedirectResponse
    {
        $this->guardFarmer($request->user(), $farmer);
        $this->guardBelongsTo($farmer, $farmUnit);
        $this->guardStock($farmUnit, $stock);

        if ($stock->isConfirmed()) {
            throw ValidationException::withMessages([
                'stock' => 'This count has already been checked.',
            ]);
        }

        // whoever wrote the number down is not the one who checks it
        if ($stock->conflictedUserId() === $request->user()->id) {
            throw ValidationException::withMessages([
                'stock' => 'Someone else needs to check this count.',
            ]);
        }

        $stock->forceFill([
            'confirmed_at' => now(),
            'confirmed_by' => $request->user()->id,
        ])->save();

        $this->audit->recordOn('farm_unit_stock.confirmed', $stock);

        return back()->with('success', 'The count is checked.');
    }

    public function storeMovement(StoreStockMovementRequest $request, FarmerProfile $farmer, FarmUnit $farmUnit, FarmUnitStock $stock): RedirectResponse
    {
        $this->guardFarmer($request->user(), $farmer);
        $this->guardBelongsTo($farmer, $farmUnit);
        $this->guardStock($farmUnit, $stock);

        $data = $request->validated();

        $stock->movements()->create([
            ...$data,
            'is_increase' => $data['is_increase'] ?? true,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'The change is saved. Someone else needs to check it.');
    }

    public function confirmMovement(Request $request, FarmerProfile $farmer, FarmUnit $farmUnit, FarmUnitStock $stock, FarmUnitStockMovement $movement): RedirectResponse
    {
        $this->guardFarmer($request->user(), $farmer);
        $this->guardBelongsTo($farmer, $farmUnit);
        $this->guardStock($farmUnit, $stock);
        $this->guardMovement($stock, $movement);

        if ($movement->isConfirmed()) {
            throw ValidationException::withMessages([
                'movement' => 'This change has already been checked.',
            ]);
        }

        if ($movement->conflictedUserId() === $request->user()->id) {
            throw ValidationException::withMessages([
                'movement' => 'Someone else needs to check this change.',
            ]);
        }

        $movement->forceFill([
            'confirmed_at' => now(),
            'confirmed_by' => $request->user()->id,
        ])->save();

        $this->audit->recordOn('farm_unit_stock_movement.confirmed', $movement);

        return back()->with('success', 'The change is checked.');
    }

    public function rejectStock(RejectionRequest $request, FarmerProfile $farmer, FarmUnit $farmUnit, FarmUnitStock $stock): RedirectResponse
    {
        $this->guardFarmer($request->user(), $farmer);
        $this->guardBelongsTo($farmer, $farmUnit);
        $this->guardStock($farmUnit, $stock);

        if ($stock->conflictedUserId() === $request->user()->id) {
            throw ValidationException::withMessages([
                'stock' => 'Someone else needs to check this count.',
            ]);
        }

        try {
            $stock->reject($request->user()->id, $request->validated('reason'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'stock' => $exception->getMessage(),
            ]);
        }

        $this->audit->recordOn('farm_unit_stock.rejected', $stock);

        if ($stock->recordedBy) {
            $this->notifications->send(
                $stock->recordedBy,
                'farm_unit_stock.rejected',
                "Your count of {$stock->opening_quantity} {$stock->unit_of_measure} in {$stock->farmUnit?->name} was sent back: {$request->validated('reason')}",
            );
        }

        return back()->with('success', 'The count is sent back.');
    }

    public function rejectMovement(RejectionRequest $request, FarmerProfile $farmer, FarmUnit $farmUnit, FarmUnitStock $stock, FarmUnitStockMovement $movement): RedirectResponse
    {
        $this->guardFarmer($request->user(), $farmer);
        $this->guardBelongsTo($farmer, $farmUnit);
        $this->guardStock($farmUnit, $stock);
        $this->guardMovement($stock, $movement);

        if ($movement->conflictedUserId() === $request->user()->id) {
            throw ValidationException::withMessages([
                'movement' => 'Someone else needs to check this change.',
            ]);
        }

        try {
            $movement->reject($request->user()->id, $request->validated('reason'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'movement' => $exception->getMessage(),
            ]);
        }

        $this->audit->recordOn('farm_unit_stock_movement.rejected', $movement);

        if ($movement->recordedBy) {
            $this->notifications->send(
                $movement->recordedBy,
                'farm_unit_stock_movement.rejected',
                "Your change of {$movement->quantity} in {$movement->stock?->farmUnit?->name} was sent back: {$request->validated('reason')}",
            );
        }

        return back()->with('success', 'The change is sent back.');
    }

    // the frame and the address the current route group belongs to
    private function frame(Request $request): array
    {
        $name = $request->route()?->getName() ?? '';
        $group = str_starts_with($name, 'agent.') ? 'agent' : 'admin';

        return [
            'layout' => $group,
            'basePath' => "/{$group}/farmers",
        ];
    }

    // a farmer they do not hold simply is not there, so nothing is learned by guessing
    private function guardFarmer(User $user, FarmerProfile $farmer): void
    {
        abort_if(! $user->hasRole('admin') && $farmer->assigned_agent_id !== $user->id, 404);
    }

    private function guardBelongsTo(FarmerProfile $farmer, FarmUnit $farmUnit): void
    {
        abort_if($farmUnit->farmer_profile_id !== $farmer->id, 404);
    }

    private function guardStock(FarmUnit $farmUnit, FarmUnitStock $stock): void
    {
        abort_if($stock->farm_unit_id !== $farmUnit->id, 404);
    }

    private function guardMovement(FarmUnitStock $stock, FarmUnitStockMovement $movement): void
    {
        abort_if($movement->farm_unit_stock_id !== $stock->id, 404);
    }
}

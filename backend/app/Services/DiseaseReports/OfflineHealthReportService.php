<?php

namespace App\Services\DiseaseReports;

use App\Enums\DiseaseReportStatus;
use App\Http\Requests\DiseaseReports\StoreDiseaseReportRequest;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

// the text of a health report saved on a phone; the photo comes later, so nothing here routes or tells an officer
class OfflineHealthReportService
{
    public const NOT_ALLOWED = 'This account cannot send health reports right now.';
    public const NOT_YOUR_FARMER = 'This farmer is not one you report for.';
    public const NOT_YOUR_UNIT = 'This farm unit is not one you can report for.';

    public function __construct(
        private readonly ReportRoutingService $routing,
        private readonly AccessControlService $access,
    ) {}

    // the report the web form already posted under this uuid, for this user's own farmer record
    public function webTwin(User $user, string $uuid): ?DiseaseReport
    {
        return DiseaseReport::withoutGlobalScopes()
            ->where('client_uuid', $uuid)
            ->whereHas('farmerProfile', fn($query) => $query->where('user_id', $user->id))
            ->first();
    }

    // why this report cannot be made, or null; the same text rules as the online form
    public function refusalFor(User $user, ?FarmerProfile $farmer, ?FarmUnit $unit, array $record): ?string
    {
        if (! $user->is_active || ! $this->access->can($user, 'disease-reports.create')) {
            return self::NOT_ALLOWED;
        }

        // an unknown farmer reads exactly like one this user may not report for
        if ($farmer === null || ($farmer->user_id !== $user->id && $farmer->assigned_agent_id !== $user->id)) {
            return self::NOT_YOUR_FARMER;
        }

        if ($unit === null || $unit->farmer_profile_id !== $farmer->id) {
            return self::NOT_YOUR_UNIT;
        }

        $form = new StoreDiseaseReportRequest();
        $text = Validator::make($record, ['description' => $form->rules()['description']], $form->messages());

        if ($text->fails()) {
            return $text->errors()->first();
        }

        try {
            $this->routing->routeFor($unit);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function createWaiting(User $user, FarmerProfile $farmer, FarmUnit $unit, array $record): DiseaseReport
    {
        [$category, $role] = $this->routing->routeFor($unit);

        return DiseaseReport::create([
            'farm_unit_id' => $unit->id,
            'farmer_profile_id' => $farmer->id,
            // the agent who sent it for the farmer; null when the farmer sent their own
            'reported_by' => $user->id === $farmer->user_id ? null : $user->id,
            'category' => $category,
            'routed_role' => $role,
            'assigned_officer_id' => null,
            'status' => DiseaseReportStatus::WaitingForPhoto,
            'description' => $record['description'],
        ]);
    }
}

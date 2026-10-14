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
    // the existing notice wording; a refusal with no reason of its own says nothing more
    public const REFUSED = 'A record could not be saved.';

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
            return self::REFUSED;
        }

        // only the farmer's own login, as online; an unknown farmer reads the same
        if ($farmer === null || $farmer->user_id !== $user->id) {
            return self::REFUSED;
        }

        if ($unit === null || $unit->farmer_profile_id !== $farmer->id) {
            return self::REFUSED;
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

    public function createWaiting(FarmerProfile $farmer, FarmUnit $unit, array $record): DiseaseReport
    {
        [$category, $role] = $this->routing->routeFor($unit);

        return DiseaseReport::create([
            'farm_unit_id' => $unit->id,
            'farmer_profile_id' => $farmer->id,
            'reported_by' => null,
            'category' => $category,
            'routed_role' => $role,
            'assigned_officer_id' => null,
            'status' => DiseaseReportStatus::WaitingForPhoto,
            'description' => $record['description'],
        ]);
    }
}

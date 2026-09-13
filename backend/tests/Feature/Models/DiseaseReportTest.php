<?php

use App\Enums\ContactMethod;
use App\Enums\DiseaseReportStatus;
use App\Enums\OfficerRole;
use App\Models\DiseaseReport;
use App\Models\FarmUnit;
use App\Models\User;

test('a report casts its routed role, status and contact method to their enums', function () {
    $report = DiseaseReport::factory()->create([
        'routed_role' => OfficerRole::Adviser,
        'status' => DiseaseReportStatus::New,
    ]);

    expect($report->routed_role)->toBe(OfficerRole::Adviser);
    expect($report->status)->toBe(DiseaseReportStatus::New);
    expect($report->contact_method)->toBeNull();
});

test('a report is assigned once it has an officer', function () {
    $officer = User::factory()->create();
    $report = DiseaseReport::factory()->create(['assigned_officer_id' => null]);

    expect($report->isAssigned())->toBeFalse();

    $report->update(['assigned_officer_id' => $officer->id]);

    expect($report->fresh()->isAssigned())->toBeTrue();
});

test('a report defaults to new status on creation', function () {
    $report = DiseaseReport::factory()->create();

    expect($report->status)->toBe(DiseaseReportStatus::New);
});

test('a new uuid is stamped on creation', function () {
    $report = DiseaseReport::factory()->create();

    expect($report->uuid)->not->toBeNull();
});

test('responding sets status, contact method and note together', function () {
    $report = DiseaseReport::factory()->create();

    $report->respond(DiseaseReportStatus::Resolved, ContactMethod::FarmVisit, 'Treated and recovering.');

    $fresh = $report->fresh();
    expect($fresh->status)->toBe(DiseaseReportStatus::Resolved);
    expect($fresh->contact_method)->toBe(ContactMethod::FarmVisit);
    expect($fresh->response_note)->toBe('Treated and recovering.');
});

test('scopeAwaitingOfficer only returns reports with no officer yet', function () {
    $waiting = DiseaseReport::factory()->create(['assigned_officer_id' => null]);
    $assigned = DiseaseReport::factory()->create(['assigned_officer_id' => User::factory()->create()->id]);

    $result = DiseaseReport::query()->awaitingOfficer()->pluck('id');

    expect($result)->toContain($waiting->id);
    expect($result)->not->toContain($assigned->id);
});

test('scopeHistoryFor returns a farm unit\'s other reports, newest first, excluding the current one', function () {
    $unit = FarmUnit::factory()->create();

    $oldest = DiseaseReport::factory()->create(['farm_unit_id' => $unit->id, 'created_at' => now()->subDays(2)]);
    $middle = DiseaseReport::factory()->create(['farm_unit_id' => $unit->id, 'created_at' => now()->subDay()]);
    $current = DiseaseReport::factory()->create(['farm_unit_id' => $unit->id]);

    $otherUnitReport = DiseaseReport::factory()->create();

    $history = DiseaseReport::query()->historyFor($unit->id, $current->id)->pluck('id');

    expect($history->all())->toBe([$middle->id, $oldest->id]);
    expect($history)->not->toContain($otherUnitReport->id);
});

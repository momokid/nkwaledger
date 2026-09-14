<?php

use App\Enums\OfficerRole;
use App\Models\AgentOfficerAssignment;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Storage::fake('public');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create([
        'user_id' => $this->farmerUser->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $livestockCategory = FarmTypeCategory::create(['name' => 'Livestock']);
    $farmType = FarmType::factory()->withCategory($livestockCategory)->create();
    $this->unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $farmType->id,
    ]);
});

describe('on submission', function () {
    it('notifies the farmer and their agent', function () {
        $this->actingAs($this->farmerUser)->post("/my-farm/{$this->unit->id}/report-problem", [
            'description' => 'Some birds look weak.',
            'photo' => UploadedFile::fake()->image('sick.jpg'),
        ]);

        expect(Notification::where('user_id', $this->farmerUser->id)
            ->where('kind', 'disease_report.submitted')
            ->exists())->toBeTrue();

        expect(Notification::where('user_id', $this->agent->id)
            ->where('kind', 'disease_report.submitted')
            ->exists())->toBeTrue();
    });

    it('notifies the assigned officer when one is linked', function () {
        $vet = User::factory()->create();
        $vet->assignRole('vet');

        AgentOfficerAssignment::create([
            'agent_id' => $this->agent->id,
            'officer_id' => $vet->id,
            'role' => OfficerRole::Vet,
        ]);

        $this->actingAs($this->farmerUser)->post("/my-farm/{$this->unit->id}/report-problem", [
            'description' => 'Some birds look weak.',
            'photo' => UploadedFile::fake()->image('sick.jpg'),
        ]);

        $notification = Notification::where('user_id', $vet->id)
            ->where('kind', 'disease_report.submitted')
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->link)->toBe('/vet/reports/' . DiseaseReport::first()->uuid);
    });

    it('does not try to notify an officer when none is linked yet', function () {
        $this->actingAs($this->farmerUser)->post("/my-farm/{$this->unit->id}/report-problem", [
            'description' => 'Some birds look weak.',
            'photo' => UploadedFile::fake()->image('sick.jpg'),
        ]);

        expect(Notification::where('kind', 'disease_report.submitted')->count())->toBe(2);
    });
});

describe('on response', function () {
    it('notifies the farmer and their agent', function () {
        $vet = User::factory()->create();
        $vet->assignRole('vet');

        $report = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'routed_role' => OfficerRole::Vet,
            'assigned_officer_id' => $vet->id,
        ]);

        $this->actingAs($vet)->post("/vet/reports/{$report->uuid}/respond", [
            'status' => 'resolved',
            'contact_method' => 'farm_visit',
            'note' => 'Treated, recovering.',
        ]);

        $farmerNotification = Notification::where('user_id', $this->farmerUser->id)
            ->where('kind', 'disease_report.responded')
            ->first();
        $agentNotification = Notification::where('user_id', $this->agent->id)
            ->where('kind', 'disease_report.responded')
            ->first();

        expect($farmerNotification)->not->toBeNull();
        expect($farmerNotification->message)->toContain('resolved');
        expect($agentNotification)->not->toBeNull();
    });
});

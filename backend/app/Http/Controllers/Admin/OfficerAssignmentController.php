<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOfficerAssignmentRequest;
use App\Models\AgentOfficerAssignment;
use App\Models\User;
use App\Services\DiseaseReports\ReportRoutingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OfficerAssignmentController extends Controller
{
    public function __construct(private readonly ReportRoutingService $routing) {}

    public function index(): Response
    {
        $assignments = AgentOfficerAssignment::query()
            ->with(['agent', 'officer'])
            ->orderBy('created_at')
            ->get()
            ->map(fn(AgentOfficerAssignment $assignment) => [
                'id' => $assignment->id,
                'agent_name' => trim("{$assignment->agent->surname} {$assignment->agent->first_name}"),
                'officer_name' => trim("{$assignment->officer->surname} {$assignment->officer->first_name}"),
                'role' => $assignment->role->value,
            ]);

        return Inertia::render('Admin/OfficerAssignments/Index', [
            'assignments' => $assignments,
            'agents' => $this->usersWithRole('agent'),
            'vets' => $this->usersWithRole('vet'),
            'advisers' => $this->usersWithRole('adviser'),
        ]);
    }

    public function store(StoreOfficerAssignmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $assignment = AgentOfficerAssignment::create($data);

        // the moment this link exists, anything already waiting for this
        // agent's farmers under this role should stop waiting
        $this->routing->autoAssignWaitingReports(
            (int) $data['agent_id'],
            $assignment->role,
            User::findOrFail($data['officer_id']),
        );

        return back()->with('success', 'Officer linked. Any waiting reports have been sent to them.');
    }

    public function destroy(AgentOfficerAssignment $assignment): RedirectResponse
    {
        $assignment->delete();

        return back()->with('success', 'Assignment removed.');
    }

    private function usersWithRole(string $role): array
    {
        return User::role($role)
            ->orderBy('surname')
            ->get(['id', 'surname', 'first_name'])
            ->map(fn(User $user) => [
                'id' => $user->id,
                'name' => trim("{$user->surname} {$user->first_name}"),
            ])
            ->all();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AccessControlService;
use App\Services\ApprovalQueueService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalQueueService $queue,
        private readonly AccessControlService $access,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $all = $this->queue->pending($user);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        // paged in php, since the list is three tables stitched together
        $items = new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url()],
        );

        return Inertia::render('Approvals/Index', [
            'items' => $items,
            ...$this->frame($request),
            'permissions' => [
                'approve' => $this->access->can($user, 'farm-units.approve'),
                'confirm' => $this->access->can($user, 'farm-units.confirm'),
            ],
        ]);
    }

    private function frame(Request $request): array
    {
        $name = $request->route()?->getName() ?? '';
        $group = str_starts_with($name, 'agent.') ? 'agent' : 'admin';

        return [
            'layout' => $group,
            'basePath' => "/{$group}",
        ];
    }
}

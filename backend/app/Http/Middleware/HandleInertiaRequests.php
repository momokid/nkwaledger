<?php

namespace App\Http\Middleware;

use App\Services\NavigationAccessService;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Services\ApprovalQueueService;
use App\Services\NotificationService;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private NavigationAccessService $navigation,
        private ApprovalQueueService $approvals,
        private NotificationService $notifications,
    ) {}

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $this->userProps($request),
                // route names this user may open, so the sidebar can hide the rest
                'nav'  => fn() => $this->navigation->allowedRouteNames($request->user()),
                // only what this person can actually sign off, so the badge never lies
                'pendingApprovals' => fn() => $request->user()
                    ? $this->approvals->countFor($request->user())
                    : 0,
                'unreadNotifications' => fn() => $this->notifications->unreadCountFor($request->user()),
            ],
            // one-shot messages any controller can set with ->with(), read once by the layout
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
                'status'  => fn() => $request->session()->get('status'),
            ],
            // what the person typed, sent back when a save fails on the server
            'old' => fn() => $request->session()->getOldInput(),
            'ziggy' => fn() => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    // only these fields go to the browser, nothing else about the account
    private function userProps(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return [
            'id'                => $user->id,
            'surname'           => $user->surname,
            'first_name'        => $user->first_name,
            'other_name'        => $user->other_name,
            'phone'             => $user->phone,
            'email'             => $user->email,
            'is_active'         => $user->is_active,
            'is_phone_verified' => $user->phone_verified_at !== null,
            'roles'             => $user->getRoleNames(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = AuditLog::query()
            ->with('user:id,surname,first_name')
            ->when($request->input('action'), fn($query, $action) => $query->where('action', $action))
            ->when($request->input('user_id'), fn($query, $id) => $query->where('user_id', $id))
            ->when($request->input('from'), fn($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->input('to'), fn($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->paginate(25)
            ->withQueryString()
            ->through(fn(AuditLog $entry) => [
                'id'         => $entry->id,
                'action'     => $entry->action,
                'user_name'  => $entry->user ? "{$entry->user->surname} {$entry->user->first_name}" : null,
                // the class name means nothing to an auditor, so it arrives as words
                'record'     => $this->recordLabel($entry->auditable_type),
                'record_id'  => $entry->auditable_id,
                'old_values' => $entry->old_values,
                'new_values' => $entry->new_values,
                'ip_address' => $entry->ip_address,
                'created_at' => $entry->created_at,
            ]);

        return Inertia::render('Admin/Audit/Index', [
            'entries' => $entries,
            'filters' => $request->only(['action', 'user_id', 'from', 'to']),
            // the choices come from what is actually in the table, not a fixed list
            'actions' => AuditLog::query()
                ->withoutGlobalScopes()
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'people' => User::query()
                ->whereIn('id', AuditLog::query()->withoutGlobalScopes()->whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('surname')
                ->get(['id', 'surname', 'first_name'])
                ->map(fn(User $user) => [
                    'id'   => $user->id,
                    'name' => "{$user->surname} {$user->first_name}",
                ]),
        ]);
    }

    // turns App\Models\FarmType into Farm Type
    private function recordLabel(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }

        return Str::headline(class_basename($class));
    }
}

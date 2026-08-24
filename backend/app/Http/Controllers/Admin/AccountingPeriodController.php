<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountingPeriodRequest;
use App\Models\AccountingPeriod;
use App\Services\AccessControlService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AccountingPeriodController extends Controller
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/AccountingPeriods/Index', [
            'periods' => AccountingPeriod::query()
                ->with(['closedBy:id,surname,first_name', 'reopenedBy:id,surname,first_name'])
                ->orderByDesc('starts_on')
                ->paginate(15)
                ->through(fn(AccountingPeriod $period) => [
                    'id'          => $period->id,
                    'name'        => $period->name,
                    'starts_on'   => $period->starts_on,
                    'ends_on'     => $period->ends_on,
                    'status'      => $period->status,
                    'closed_at'   => $period->closed_at,
                    'closed_by'   => $period->closedBy?->surname . ' ' . $period->closedBy?->first_name,
                    'reopened_at' => $period->reopened_at,
                    'reopened_by' => $period->reopenedBy?->surname . ' ' . $period->reopenedBy?->first_name,
                ]),
            'permissions' => [
                'create' => $this->access->can($user, 'accounting-periods.create'),
                'close'  => $this->access->can($user, 'accounting-periods.close'),
                'reopen' => $this->access->can($user, 'accounting-periods.reopen'),
            ],
        ]);
    }

    public function store(StoreAccountingPeriodRequest $request): RedirectResponse
    {
        $period = AccountingPeriod::create($request->validated());

        return back()->with('success', "{$period->name} is open and ready for transactions.");
    }

    public function close(Request $request, AccountingPeriod $accountingPeriod): RedirectResponse
    {
        // the model owns the rule; the controller only turns a refusal into something readable
        try {
            $accountingPeriod->close($request->user());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        $this->audit->recordOn('period.closed', $accountingPeriod);

        return back()->with('success', "{$accountingPeriod->name} is closed. Corrections now go in as adjustments.");
    }

    public function reopen(Request $request, AccountingPeriod $accountingPeriod): RedirectResponse
    {
        try {
            $accountingPeriod->reopen($request->user());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        $this->audit->recordOn('period.reopened', $accountingPeriod);

        return back()->with('success', "{$accountingPeriod->name} is open again. Reports built from it may now change.");
    }
}

<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\Loans\LoanStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

// the loan statement for one loan: on screen, as a print page and as a PDF. The farmer, the farmer's agent and the admin
// may open it; the policy answers 404 to everyone else
class LoanStatementController extends Controller
{
    public function __construct(private readonly LoanStatementService $statements) {}

    public function show(Loan $loan): Response
    {
        Gate::authorize('statement', $loan);

        return Inertia::render('Loans/Statement', [
            'statement' => $this->statements->for($loan),
            'backUrl' => $this->loanPage($loan),
            'printUrl' => "/loans/{$loan->uuid}/statement/print",
            'pdfUrl' => "/loans/{$loan->uuid}/statement/pdf",
        ]);
    }

    public function print(Loan $loan): View
    {
        Gate::authorize('statement', $loan);

        return view('loans.statement-print', ['s' => $this->statements->for($loan), 'pdf' => false]);
    }

    public function pdf(Loan $loan): HttpResponse
    {
        Gate::authorize('statement', $loan);

        $statement = $this->statements->for($loan);
        $name = 'loan-statement-' . Str::slug($statement['loan']['lender']) . '-' . now()->format('Ymd') . '.pdf';

        return response(Pdf::loadView('loans.statement-print', ['s' => $statement, 'pdf' => true])->output(['compress' => 0]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    // back to the loan page this person normally reads
    private function loanPage(Loan $loan): string
    {
        $user = request()->user();

        return match (true) {
            $user->hasRole('admin') => "/admin/loans/{$loan->uuid}",
            $user->hasRole('agent') => "/agent/loans/{$loan->uuid}",
            default => "/loans/{$loan->uuid}",
        };
    }
}

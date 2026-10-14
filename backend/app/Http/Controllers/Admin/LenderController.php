<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lender;
use App\Services\Loans\LenderService;
use App\Support\Wording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// the only place an institution is added or a lender's name or type corrected. Admin only; every change is audit-logged
class LenderController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly LenderService $lenders) {}

    public function index(): Response
    {
        $page = Lender::orderBy('name')->paginate(self::PER_PAGE);

        return Inertia::render('Admin/Lenders/Index', [
            'lenders' => $page->getCollection()->map(fn(Lender $lender) => $this->row($lender))->values(),
            'pagination' => ['page' => $page->currentPage(), 'last' => $page->lastPage()],
            'types' => Lender::TYPES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', Lender::TYPES)],
            'name' => ['required', 'string', 'max:255'],
        ], ['type.in' => Wording::get('lender.type_invalid'), 'name.required' => Wording::get('lender.name_required')]);

        return response()->json($this->row($this->lenders->addInstitution($data['type'], $data['name'], $request->user())), 201);
    }

    public function update(Request $request, Lender $lender): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255', 'required_without:type'],
            'type' => ['nullable', 'string', 'in:' . implode(',', Lender::TYPES), 'required_without:name'],
        ], ['name.required_without' => Wording::get('lender.name_required'), 'type.required_without' => Wording::get('lender.type_invalid'), 'type.in' => Wording::get('lender.type_invalid')]);

        return response()->json($this->row($this->lenders->update($lender, $data['name'] ?? null, $data['type'] ?? null, $request->user())));
    }

    /** @return array<string, mixed> */
    private function row(Lender $lender): array
    {
        return ['uuid' => $lender->uuid, 'type' => $lender->type, 'name' => $lender->name];
    }
}

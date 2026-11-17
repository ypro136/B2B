<?php

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentController extends BaseController
{
    public function index(Request $request): View
    {
        $type = (string) $request->query('type', '');
        $state = (string) $request->query('state', '');
        $companyId = $request->query('company_id');

        return view('admin::documents.index', [
            'type' => $type,
            'state' => $state,
            'companyId' => $companyId,
            'documents' => Document::query()
                ->with('company')
                ->when($type !== '', fn ($query) => $query->where('type', $type))
                ->when($state !== '', fn ($query) => $query->where('state', $state))
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function show(Document $document): View
    {
        $document->load(['company', 'lines.trip', 'booking.trip', 'payment', 'approver']);

        return view('admin::documents.show', [
            'document' => $document,
        ]);
    }

    public function approve(Request $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $documents->approve($document, $request->user());

        return back()->with('status', 'Document approved and snapshotted.');
    }

    public function void(Request $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $documents->void($document, $request->user());

        return back()->with('status', 'Document voided. Its history is preserved.');
    }

    public function monthlyForm(): View
    {
        return view('admin::documents.monthly', [
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function monthly(Request $request, DocumentService $documents): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        $document = $documents->generateMonthlyInvoice($company, $data['period'], $request->user());

        return redirect()
            ->route('admin.payments.documents.show', $document)
            ->with('status', 'Monthly invoice drafted. Review and approve it.');
    }
}

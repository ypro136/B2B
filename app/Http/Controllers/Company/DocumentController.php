<?php

namespace App\Http\Controllers\Company;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DocumentController extends BaseController
{
    public function index(Request $request): View
    {
        $company = $request->user()->company;

        return view('company::documents.index', [
            'company' => $company,
            'documents' => $company->documents()
                ->approved()
                ->latest()
                ->paginate(15),
        ]);
    }

    public function show(Request $request, Document $document): View
    {
        abort_unless((int) $document->company_id === (int) $request->user()->company_id, 403);
        abort_unless($document->isApproved(), 404);

        $document->load(['company', 'lines.trip', 'booking.trip', 'payment']);

        return view('company::documents.show', [
            'document' => $document,
        ]);
    }
}

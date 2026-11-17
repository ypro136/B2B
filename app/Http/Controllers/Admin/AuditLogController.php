<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends BaseController
{
    /**
     * List audited platform activity with an optional action filter.
     */
    public function index(Request $request): View
    {
        $action = (string) $request->query('action', '');

        return view('admin::audit-logs.index', [
            'action' => $action,
            'actions' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'logs' => AuditLog::query()
                ->with('actor')
                ->when($action !== '', fn ($query) => $query->where('action', $action))
                ->latest('created_at')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }
}

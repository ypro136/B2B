<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\TripTemplate;
use App\Services\RecurrenceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TripTemplateController extends BaseController
{
    public function index(): View
    {
        return view('admin::templates.index', [
            'templates' => TripTemplate::query()
                ->with('creator')
                ->withCount('trips')
                ->orderBy('route_name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin::templates.form', [
            'template' => new TripTemplate(['weekdays' => '1,2,3,4,5', 'total_seats' => 40, 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $template = TripTemplate::query()->create([
            ...$data,
            'weekdays' => implode(',', $data['weekdays']),
            'created_by' => $request->user()->getKey(),
        ]);

        AuditLog::record($template, 'template.created', $request->user(), null, $template->toArray());

        return redirect()
            ->route('admin.templates.index')
            ->with('status', 'Recurring template created. Use Generate trips to publish departures.');
    }

    public function edit(TripTemplate $template): View
    {
        return view('admin::templates.form', [
            'template' => $template,
        ]);
    }

    public function update(Request $request, TripTemplate $template): RedirectResponse
    {
        $data = $this->validated($request);
        $old = $template->toArray();

        $template->update([
            ...$data,
            'weekdays' => implode(',', $data['weekdays']),
        ]);

        AuditLog::record($template, 'template.updated', $request->user(), $old, $template->fresh()->toArray());

        return redirect()
            ->route('admin.templates.index')
            ->with('status', 'Template updated.');
    }

    public function generate(Request $request, TripTemplate $template, RecurrenceService $recurrence): RedirectResponse
    {
        $created = $recurrence->generate($template, $request->user());

        return redirect()
            ->route('admin.templates.index')
            ->with('status', $created.' trip(s) generated for the next 14 days.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:160'],
            'origin' => ['required', 'string', 'max:160'],
            'destination' => ['required', 'string', 'max:160'],
            'departure_time' => ['required', 'date_format:H:i'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'standard_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'per_seat_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['standard_price'] = (float) ($data['standard_price'] ?? 0);
        $data['per_seat_cost'] = (float) ($data['per_seat_cost'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}

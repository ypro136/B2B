<?php

namespace App\Http\Controllers\Company;

use App\Services\SeatRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeatRequestController extends BaseController
{
    /**
     * List the company's seat requests and the submission form.
     */
    public function index(): View
    {
        $company = auth()->user()?->company;

        return view('company::seat-requests.index', [
            'company' => $company,
            'seatRequests' => $company?->seatRequests()
                ->with('reviewer')
                ->latest()
                ->paginate(15) ?? collect(),
        ]);
    }

    /**
     * Submit a request for additional seats.
     */
    public function store(Request $request, SeatRequestService $seatRequests): RedirectResponse
    {
        $data = $request->validate([
            'requested_seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $company = $request->user()->company;

        if ($company === null) {
            throw ValidationException::withMessages([
                'requested_seats' => 'This account is not linked to a company.',
            ]);
        }

        $seatRequests->request(
            $company,
            $request->user(),
            (int) $data['requested_seats'],
            $data['reason'] ?? null,
        );

        return back()->with('status', 'Seat request submitted for review.');
    }
}

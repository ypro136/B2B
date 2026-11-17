<?php

namespace App\Http\Controllers\Admin;

use App\Models\SeatRequest;
use App\Services\SeatRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SeatRequestController extends BaseController
{
    /**
     * List seat requests, optionally filtered by status.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        return view('admin::seat-requests.index', [
            'status' => $status,
            'seatRequests' => SeatRequest::query()
                ->with(['company', 'requester', 'reviewer'])
                ->when(
                    in_array($status, [
                        SeatRequest::STATUS_PENDING,
                        SeatRequest::STATUS_APPROVED,
                        SeatRequest::STATUS_REJECTED,
                    ], true),
                    fn ($query) => $query->where('status', $status),
                )
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    /**
     * Approve a pending seat request.
     */
    public function approve(
        Request $request,
        SeatRequest $seatRequest,
        SeatRequestService $seatRequests,
    ): RedirectResponse {
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $seatRequests->approve($seatRequest, $request->user(), $data['review_note'] ?? null);

        return back()->with('status', 'Seat request approved. One-time bonus seats were granted.');
    }

    /**
     * Reject a pending seat request.
     */
    public function reject(
        Request $request,
        SeatRequest $seatRequest,
        SeatRequestService $seatRequests,
    ): RedirectResponse {
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $seatRequests->reject($seatRequest, $request->user(), $data['review_note'] ?? null);

        return back()->with('status', 'Seat request rejected.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeatRequest;
use App\Services\SeatRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatRequestController extends Controller
{
    /**
     * List seat requests submitted by the authenticated user's company.
     */
    public function index(Request $request): JsonResponse
    {
        $seatRequests = SeatRequest::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['requester', 'reviewer'])
            ->latest()
            ->get()
            ->map(fn (SeatRequest $seatRequest) => $this->seatRequestPayload($seatRequest));

        return response()->json(['data' => $seatRequests]);
    }

    /**
     * Submit a seat request for the authenticated user's company.
     */
    public function store(Request $request, SeatRequestService $seatRequests): JsonResponse
    {
        $data = $request->validate([
            'requested_seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $seatRequest = $seatRequests->request(
            $request->user()->company,
            $request->user(),
            (int) $data['requested_seats'],
            $data['reason'] ?? null,
        );

        return response()->json([
            'message' => 'Seat request submitted.',
            'seat_request' => $this->seatRequestPayload($seatRequest),
        ], 201);
    }

    /**
     * Shape a seat request for API responses.
     *
     * @return array<string, mixed>
     */
    private function seatRequestPayload(SeatRequest $seatRequest): array
    {
        return [
            'id' => $seatRequest->getKey(),
            'requested_seats' => $seatRequest->requested_seats,
            'reason' => $seatRequest->reason,
            'status' => $seatRequest->status,
            'review_note' => $seatRequest->review_note,
            'created_at' => $seatRequest->created_at?->toIso8601String(),
            'reviewed_at' => $seatRequest->reviewed_at?->toIso8601String(),
        ];
    }
}

@extends($layout)

@section('title', 'Seat requests')
@section('eyebrow', 'Company Portal')
@section('page-title', 'Seat requests')

@section('content')
    @php($seatsHeld = $company?->confirmedSeats() ?? 0)
    @php($hasPending = $company?->seatRequests()->pending()->exists() ?? false)

    <div class="page-head">
        <div>
            <h2>Request more capacity</h2>
            <p class="muted">
                Current allowance: {{ $company?->effectiveSeatLimit() ?? 0 }} seats ({{ $company?->bonus_seats ?? 0 }} bonus), {{ $seatsHeld }} in use.
            </p>
        </div>
    </div>

    <div class="form-card">
        @if ($hasPending)
            <p class="muted" style="margin: 0;">A seat request is already awaiting review. You can submit another once it is reviewed.</p>
        @else
            <form method="POST" action="{{ route('company.seat-requests.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="requested_seats">Additional seats</label>
                        <input id="requested_seats" name="requested_seats" type="number" min="1" max="1000" value="{{ old('requested_seats') }}" required>
                    </div>
                    <div class="field is-wide">
                        <label for="reason">Reason (optional)</label>
                        <textarea id="reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Submit request</button>
                </div>
            </form>
        @endif
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Requested</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($seatRequests as $seatRequest)
                    @php($badge = match ($seatRequest->status) {
                        'PENDING' => 'badge-warning',
                        'APPROVED' => 'badge-success',
                        'REJECTED' => 'badge-danger',
                        default => 'badge-muted',
                    })
                    <tr>
                        <td>{{ $seatRequest->requested_seats }} seats</td>
                        <td>{{ $seatRequest->reason ?: '-' }}</td>
                        <td><span class="badge {{ $badge }}">{{ $seatRequest->status }}</span></td>
                        <td class="nowrap">{{ $seatRequest->created_at?->format('M j, Y H:i') }}</td>
                        <td>
                            <span class="muted">
                                {{ $seatRequest->reviewer?->name ?? 'Awaiting review' }}
                                @if ($seatRequest->review_note)
                                    <br>{{ $seatRequest->review_note }}
                                @endif
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><div class="empty">No seat requests submitted yet.</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $seatRequests->links('vendor.pagination.portal') }}
@endsection

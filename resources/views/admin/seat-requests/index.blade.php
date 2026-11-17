@extends($layout)

@section('title', 'Seat requests')
@section('eyebrow', 'Administration')
@section('page-title', 'Seat requests')

@section('content')
    @php($canReview = auth()->user()?->hasFeature(\App\Models\AdminPermission::APPROVE_REQUESTS))

    <div class="page-head">
        <div>
            <h2>Seat requests</h2>
            <p class="muted">Approving a request grants one-time bonus seats for the current quota window.</p>
        </div>
        <div class="actions">
            <a class="btn btn-small {{ $status === '' ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.seat-requests.index') }}">All</a>
            <a class="btn btn-small {{ $status === 'PENDING' ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.seat-requests.index', ['status' => 'PENDING']) }}">Pending</a>
            <a class="btn btn-small {{ $status === 'APPROVED' ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.seat-requests.index', ['status' => 'APPROVED']) }}">Approved</a>
            <a class="btn btn-small {{ $status === 'REJECTED' ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.seat-requests.index', ['status' => 'REJECTED']) }}">Rejected</a>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Company</th>
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
                        <td>
                            <span class="strong">{{ $seatRequest->company?->name }}</span><br>
                            <span class="muted">{{ $seatRequest->requester?->name }}</span>
                        </td>
                        <td>{{ $seatRequest->requested_seats }} seats</td>
                        <td>{{ $seatRequest->reason ?: '-' }}</td>
                        <td><span class="badge {{ $badge }}">{{ $seatRequest->status }}</span></td>
                        <td class="nowrap">{{ $seatRequest->created_at?->format('M j, Y H:i') }}</td>
                        <td>
                            @if ($seatRequest->isPending() && $canReview)
                                <div class="actions">
                                    <form method="POST" action="{{ route('admin.seat-requests.approve', $seatRequest) }}" class="actions">
                                        @csrf
                                        <input type="text" name="review_note" placeholder="Optional note" class="table-input">
                                        <button class="btn btn-small btn-primary" type="submit">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.seat-requests.reject', $seatRequest) }}">
                                        @csrf
                                        <button class="btn btn-small btn-danger" type="submit">Reject</button>
                                    </form>
                                </div>
                            @else
                                <span class="muted">
                                    {{ $seatRequest->reviewer?->name ?? '-' }}
                                    @if ($seatRequest->review_note)
                                        <br>{{ $seatRequest->review_note }}
                                    @endif
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"><div class="empty">No seat requests found.</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $seatRequests->links('vendor.pagination.portal') }}
@endsection

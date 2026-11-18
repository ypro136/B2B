@extends($layout)

@section('title', 'Dashboard')
@section('eyebrow', 'Administration')
@section('page-title', 'Operations dashboard')

@section('content')
    <section class="hero">
        <div>
            <h2>Run the network from one place</h2>
            <p>Manage quotas, numbered seats, pricing, payments and documents across the platform.</p>
        </div>
        <span class="hero-badge">Live overview</span>
    </section>

    <section class="cards">
        @foreach ($stats as $stat)
            <article class="card">
                <p class="card-label">{{ $stat['label'] }}</p>
                <p class="card-value">{{ $stat['value'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid">
        <article class="panel">
            <h3>Company management</h3>
            <p>Review company accounts and adjust seat limits within current usage.</p>
            <p style="margin-top: 12px;"><a class="btn btn-small" href="{{ route('admin.companies.index') }}">Open companies</a></p>
        </article>
        <article class="panel">
            <h3>Trip operations</h3>
            <p>Schedule journeys and cancel trips when plans change.</p>
            <p style="margin-top: 12px;"><a class="btn btn-small" href="{{ route('admin.trips.index') }}">Open trips</a></p>
        </article>
        <article class="panel">
            <h3>Seat requests</h3>
            <p>Approve or reject requests for additional company capacity.</p>
            <p style="margin-top: 12px;"><a class="btn btn-small" href="{{ route('admin.seat-requests.index') }}">Open requests</a></p>
        </article>
        <article class="panel">
            <h3>Finance</h3>
            <p>Record payments, allocate them to bookings and approve documents.</p>
            <p style="margin-top: 12px;"><a class="btn btn-small" href="{{ route('admin.payments.index') }}">Open payments</a></p>
        </article>
    </section>

    <section>
        <div class="page-head">
            <div>
                <h2>Bookings done today</h2>
                <p class="muted">All bookings created today, marked whether they carry a confirmed payment or not.</p>
            </div>
        </div>

        <div class="table-wrap" style="margin-top: 12px;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Company</th>
                        <th>Trip</th>
                        <th>Seats</th>
                        <th>Amount</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Booked</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($todaysBookings as $booking)
                        <tr>
                            <td>#{{ $booking->id }}</td>
                            <td class="strong">{{ $booking->company?->name }}</td>
                            <td>{{ $booking->trip?->route_name }}</td>
                            <td>{{ $booking->seats_count }}</td>
                            <td>{{ number_format((float) $booking->total_amount, 2) }} {{ $booking->currency }}</td>
                            <td>{{ number_format($booking->remainingDue(), 2) }} {{ $booking->currency }}</td>
                            <td><span class="badge {{ $booking->status === 'CONFIRMED' ? 'badge-success' : 'badge-muted' }}">{{ $booking->status }}</span></td>
                            <td>
                                @if ($booking->confirmed_payment_allocations_count > 0)
                                    <span class="badge badge-success">Confirmed payment</span>
                                @else
                                    <span class="badge badge-warning">No payment</span>
                                @endif
                            </td>
                            <td class="nowrap">{{ $booking->created_at?->format('H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="muted" style="text-align: center; padding: 16px;">No bookings done today yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($recentPayments->isNotEmpty())
        <section>
            <div class="page-head">
                <div>
                    <h2>Recent payments</h2>
                    <p class="muted">Latest cash and bank records waiting for confirmation or allocation.</p>
                </div>
            </div>

            <div class="table-wrap" style="margin-top: 12px;">
                <table>
                    <thead><tr><th>Company</th><th>Amount</th><th>Status</th><th>Received</th></tr></thead>
                    <tbody>
                        @foreach ($recentPayments as $payment)
                            <tr>
                                <td class="strong">{{ $payment->company?->name }}</td>
                                <td>{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
                                <td><span class="badge {{ $payment->status === 'CONFIRMED' ? 'badge-success' : 'badge-warning' }}">{{ $payment->status }}</span></td>
                                <td>{{ $payment->received_date?->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($recentRequests->isNotEmpty())
        <section>
            <div class="page-head">
                <div>
                    <h2>Recent seat requests</h2>
                    <p class="muted">The latest requests submitted by client companies.</p>
                </div>
            </div>

            <div class="table-wrap" style="margin-top: 12px;">
                <table>
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentRequests as $seatRequest)
                            @php($badge = match ($seatRequest->status) {
                                'PENDING' => 'badge-warning',
                                'APPROVED' => 'badge-success',
                                'REJECTED' => 'badge-danger',
                                default => 'badge-muted',
                            })
                            <tr>
                                <td class="strong">{{ $seatRequest->company?->name }}</td>
                                <td>{{ $seatRequest->requested_seats }} seats</td>
                                <td><span class="badge {{ $badge }}">{{ $seatRequest->status }}</span></td>
                                <td>{{ $seatRequest->created_at?->format('M j, Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection

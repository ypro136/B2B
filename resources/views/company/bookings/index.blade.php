@extends($layout)

@section('title', 'Bookings')
@section('eyebrow', 'Company Portal')
@section('page-title', 'My bookings')

@section('content')
    <div class="page-head">
        <div>
            <h2>Seat reservations</h2>
            <p class="muted">Cancelling a booking releases its seats immediately.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('company.trips.index') }}">Book another trip</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Trip</th>
                    <th>Departure</th>
                    <th>Seats</th>
                    <th>Status</th>
                    <th>Booked by</th>
                    <th>Booked at</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bookings as $booking)
                    <tr>
                        <td>
                            <span class="strong">{{ $booking->trip?->route_name }}</span><br>
                            <span class="muted">{{ $booking->trip?->origin }} to {{ $booking->trip?->destination }}</span>
                        </td>
                        <td class="nowrap">{{ $booking->trip?->departure_datetime?->format('M j, Y H:i') }}</td>
                        <td>{{ $booking->seats_count }}</td>
                        <td>
                            <span class="badge {{ $booking->status === 'CONFIRMED' ? 'badge-success' : 'badge-muted' }}">
                                {{ $booking->status }}
                            </span>
                        </td>
                        <td>{{ $booking->bookedBy?->name ?? '-' }}</td>
                        <td class="nowrap">{{ $booking->created_at?->format('M j, Y H:i') }}</td>
                        <td class="actions">
                            <a class="btn btn-small" href="{{ route('company.bookings.show', $booking) }}">Open</a>
                            @if ($booking->status === 'CONFIRMED')
                                <form method="POST" action="{{ route('company.bookings.cancel', $booking) }}">
                                    @csrf
                                    <button class="btn btn-small btn-danger" type="submit">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7"><div class="empty">No bookings yet. Pick an upcoming trip to reserve seats.</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $bookings->links('vendor.pagination.portal') }}
@endsection

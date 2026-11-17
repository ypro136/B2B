@extends($layout)

@section('title', 'Trips')
@section('eyebrow', 'Company Portal')
@section('page-title', 'Upcoming trips')

@section('content')
    @php($available = $company?->availableSeats() ?? 0)

    <div class="page-head">
        <div>
            <h2>Book seats</h2>
            <p class="muted">You can book up to {{ $available }} more seat(s) within your current limit.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('company.bookings.index') }}">My bookings</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Departure</th>
                    <th>Seats left</th>
                    <th>Book</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($trips as $trip)
                    @php($remaining = max(0, $trip->total_seats - ($trip->confirmed_seats_count ?? 0)))
                    <tr>
                        <td>
                            <span class="strong">{{ $trip->route_name }}</span><br>
                            <span class="muted">{{ $trip->origin }} to {{ $trip->destination }}</span>
                        </td>
                        <td class="nowrap">{{ $trip->departure_datetime?->format('M j, Y H:i') }}</td>
                        <td>{{ $remaining }}</td>
                        <td>
                            @if ($remaining > 0 && $available > 0)
                                <a class="btn btn-small btn-ghost" href="{{ route('company.trips.show', $trip) }}">Select seats</a>
                                <form method="POST" action="{{ route('company.bookings.store') }}" class="inline-form">
                                    @csrf
                                    <input type="hidden" name="trip_id" value="{{ $trip->id }}">
                                    <div class="field">
                                        <label>Seats</label>
                                        <input type="number" name="seats_count" min="1" max="{{ min($remaining, $available) }}" value="1" required>
                                    </div>
                                    <button class="btn btn-small btn-primary" type="submit">Book</button>
                                </form>
                            @else
                                <span class="badge badge-muted">Unavailable</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><div class="empty">No upcoming trips are available right now.</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $trips->links('vendor.pagination.portal') }}
@endsection

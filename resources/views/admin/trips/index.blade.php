@extends($layout)

@section('title', 'Trips')
@section('eyebrow', 'Administration')
@section('page-title', 'Trips')

@section('content')
    <div class="page-head">
        <div>
            <h2>Scheduled journeys</h2>
            <p class="muted">Capacity is enforced across all confirmed bookings.</p>
        </div>
        @if (auth()->user()?->hasFeature(\App\Models\AdminPermission::MANAGE_TRIPS))
            <a class="btn btn-primary" href="{{ route('admin.trips.create') }}">Schedule trip</a>
        @endif
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Departure</th>
                    <th>Seats</th>
                    <th>Status</th>
                    <th>Created by</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($trips as $trip)
                    <tr>
                        <td>
                            <span class="strong">{{ $trip->route_name }}</span><br>
                            <span class="muted">{{ $trip->origin }} to {{ $trip->destination }}</span>
                        </td>
                        <td class="nowrap">{{ $trip->departure_datetime?->format('M j, Y H:i') }}</td>
                        <td>{{ $trip->confirmed_seats_count ?? 0 }} / {{ $trip->total_seats }}</td>
                        <td>
                            <span class="badge {{ $trip->status === 'SCHEDULED' ? 'badge-success' : ($trip->status === 'CANCELLED' ? 'badge-danger' : 'badge-muted') }}">
                                {{ $trip->status }}
                            </span>
                        </td>
                        <td>{{ $trip->creator?->name ?? '-' }}</td>
                        <td class="actions">
                            <a class="btn btn-small" href="{{ route('admin.trips.show', $trip) }}">Open</a>
                            @if ($trip->status === 'SCHEDULED' && auth()->user()?->hasFeature(\App\Models\AdminPermission::MANAGE_TRIPS))
                                @if ($trip->departure_datetime?->isPast())
                                    <form method="POST" action="{{ route('admin.trips.complete', $trip) }}">
                                        @csrf
                                        <button class="btn btn-small" type="submit">Mark complete</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.trips.cancel', $trip) }}">
                                    @csrf
                                    <button class="btn btn-small btn-danger" type="submit">Cancel trip</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"><div class="empty">No trips scheduled yet.</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $trips->links('vendor.pagination.portal') }}
@endsection

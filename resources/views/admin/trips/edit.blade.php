@extends($layout)

@section('title', 'Edit trip')
@section('eyebrow', 'Administration')
@section('page-title', 'Edit trip')

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ $trip->route_name }}</h2>
            <p class="muted">Resize seats carefully: seats held by confirmed bookings cannot be removed.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.trips.show', $trip) }}">Back to trip</a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.trips.update', $trip) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field is-wide">
                    <label for="route_name">Route name</label>
                    <input id="route_name" name="route_name" type="text" maxlength="160" value="{{ old('route_name', $trip->route_name) }}" required>
                </div>
                <div class="field">
                    <label for="origin">Origin</label>
                    <input id="origin" name="origin" type="text" maxlength="160" value="{{ old('origin', $trip->origin) }}" required>
                </div>
                <div class="field">
                    <label for="destination">Destination</label>
                    <input id="destination" name="destination" type="text" maxlength="160" value="{{ old('destination', $trip->destination) }}" required>
                </div>
                <div class="field">
                    <label for="departure_datetime">Departure</label>
                    <input id="departure_datetime" name="departure_datetime" type="datetime-local"
                           value="{{ old('departure_datetime', $trip->departure_datetime?->format('Y-m-d\TH:i')) }}" required>
                </div>
                <div class="field">
                    <label for="total_seats">Total seats ({{ $trip->confirmedSeats() }} booked)</label>
                    <input id="total_seats" name="total_seats" type="number" min="{{ max(1, $trip->confirmedSeats()) }}" max="1000" value="{{ old('total_seats', $trip->total_seats) }}" required>
                </div>
                <div class="field">
                    <label for="standard_price">Standard price</label>
                    <input id="standard_price" name="standard_price" type="number" step="0.01" min="0" value="{{ old('standard_price', $trip->standard_price) }}" required>
                </div>
                <div class="field">
                    <label for="per_seat_cost">Per-seat cost</label>
                    <input id="per_seat_cost" name="per_seat_cost" type="number" step="0.01" min="0" value="{{ old('per_seat_cost', $trip->per_seat_cost) }}" required>
                </div>
                <div class="field">
                    <label for="cutoff_hours">Cutoff (hours, blank = global)</label>
                    <input id="cutoff_hours" name="cutoff_hours" type="number" min="0" max="168" value="{{ old('cutoff_hours', $trip->cutoff_hours) }}">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save trip</button>
                <a class="btn btn-ghost" href="{{ route('admin.trips.show', $trip) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

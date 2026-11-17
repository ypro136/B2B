@extends($layout)

@section('title', 'Schedule trip')
@section('eyebrow', 'Administration')
@section('page-title', 'Schedule trip')

@section('content')
    <div class="page-head">
        <div>
            <h2>New journey</h2>
            <p class="muted">Trips are published to the company portal as soon as they are scheduled.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.trips.index') }}">Back to trips</a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.trips.store') }}">
            @csrf

            <div class="form-grid">
                <div class="field is-wide">
                    <label for="route_name">Route name</label>
                    <input id="route_name" name="route_name" type="text" maxlength="160" value="{{ old('route_name') }}" required>
                </div>
                <div class="field">
                    <label for="origin">Origin</label>
                    <input id="origin" name="origin" type="text" maxlength="160" value="{{ old('origin') }}" required>
                </div>
                <div class="field">
                    <label for="destination">Destination</label>
                    <input id="destination" name="destination" type="text" maxlength="160" value="{{ old('destination') }}" required>
                </div>
                <div class="field">
                    <label for="departure_datetime">Departure</label>
                    <input id="departure_datetime" name="departure_datetime" type="datetime-local" value="{{ old('departure_datetime') }}" required>
                </div>
                <div class="field">
                    <label for="total_seats">Total seats</label>
                    <input id="total_seats" name="total_seats" type="number" min="1" max="1000" value="{{ old('total_seats', 40) }}" required>
                </div>
                <div class="field">
                    <label for="standard_price">Standard seat price</label>
                    <input id="standard_price" name="standard_price" type="number" step="0.01" min="0" value="{{ old('standard_price', 0) }}">
                </div>
                <div class="field">
                    <label for="per_seat_cost">Per-seat cost</label>
                    <input id="per_seat_cost" name="per_seat_cost" type="number" step="0.01" min="0" value="{{ old('per_seat_cost', 0) }}">
                </div>
                <div class="field">
                    <label for="cutoff_hours">Booking cutoff (hours)</label>
                    <input id="cutoff_hours" name="cutoff_hours" type="number" min="0" max="168" value="{{ old('cutoff_hours') }}" placeholder="Use platform default">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Schedule trip</button>
                <a class="btn btn-ghost" href="{{ route('admin.trips.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

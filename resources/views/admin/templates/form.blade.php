@extends($layout)

@section('title', $template->exists ? 'Edit template' : 'New template')
@section('eyebrow', 'Administration')
@section('page-title', $template->exists ? 'Edit template' : 'New template')

@php($selectedWeekdays = old('weekdays', $template->weekdayNumbers()))

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ $template->exists ? $template->route_name : 'Recurring departure' }}</h2>
            <p class="muted">Generation skips departures that already exist for the same route and time.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.templates.index') }}">Back to templates</a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ $template->exists ? route('admin.templates.update', $template) : route('admin.templates.store') }}">
            @csrf
            @if ($template->exists)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="field is-wide">
                    <label for="route_name">Route name</label>
                    <input id="route_name" name="route_name" type="text" maxlength="160" value="{{ old('route_name', $template->route_name) }}" required>
                </div>
                <div class="field">
                    <label for="origin">Origin</label>
                    <input id="origin" name="origin" type="text" maxlength="160" value="{{ old('origin', $template->origin) }}" required>
                </div>
                <div class="field">
                    <label for="destination">Destination</label>
                    <input id="destination" name="destination" type="text" maxlength="160" value="{{ old('destination', $template->destination) }}" required>
                </div>
                <div class="field">
                    <label for="departure_time">Departure time</label>
                    <input id="departure_time" name="departure_time" type="time" value="{{ old('departure_time', $template->departure_time) }}" required>
                </div>
                <div class="field">
                    <label for="total_seats">Seats per departure</label>
                    <input id="total_seats" name="total_seats" type="number" min="1" max="1000" value="{{ old('total_seats', $template->total_seats) }}" required>
                </div>
                <div class="field">
                    <label for="standard_price">Standard price</label>
                    <input id="standard_price" name="standard_price" type="number" step="0.01" min="0" value="{{ old('standard_price', $template->standard_price) }}">
                </div>
                <div class="field">
                    <label for="per_seat_cost">Per-seat cost</label>
                    <input id="per_seat_cost" name="per_seat_cost" type="number" step="0.01" min="0" value="{{ old('per_seat_cost', $template->per_seat_cost) }}">
                </div>
                <div class="field">
                    <label for="valid_from">Valid from</label>
                    <input id="valid_from" name="valid_from" type="date" value="{{ old('valid_from', $template->valid_from?->format('Y-m-d')) }}">
                </div>
                <div class="field">
                    <label for="valid_until">Valid until</label>
                    <input id="valid_until" name="valid_until" type="date" value="{{ old('valid_until', $template->valid_until?->format('Y-m-d')) }}">
                </div>
            </div>

            <div class="field section-gap">
                <label>Weekdays</label>
                <div class="actions">
                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday => $label)
                        <label class="checkbox">
                            <input type="checkbox" name="weekdays[]" value="{{ $weekday }}" @checked(in_array($weekday, array_map('intval', (array) $selectedWeekdays), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="field section-gap">
                <label>Status</label>
                <label class="checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ? '1' : '0') == '1')>
                    Active
                </label>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">{{ $template->exists ? 'Save template' : 'Create template' }}</button>
                <a class="btn btn-ghost" href="{{ route('admin.templates.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

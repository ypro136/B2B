@extends($layout)

@section('title', 'Select seats')
@section('eyebrow', 'Company Portal')
@section('page-title', 'Select seats')

@php
    $remainingOnTrip = $trip->remainingSeats();
    $bookable = $trip->isBookable();
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ $trip->route_name }}</h2>
            <p class="muted">{{ $trip->origin }} to {{ $trip->destination }} &middot; {{ $trip->departure_datetime?->format('M j, Y H:i') }}</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('company.trips.index') }}">Back to trips</a>
    </div>

    <section class="cards">
        <article class="card">
            <p class="card-label">Your price per seat</p>
            <p class="card-value">{{ number_format($unitPrice, 2) }} {{ $trip->currency }}</p>
        </article>
        <article class="card">
            <p class="card-label">Seats left on trip</p>
            <p class="card-value">{{ $remainingOnTrip }}</p>
        </article>
        <article class="card">
            <p class="card-label">Your remaining quota</p>
            <p class="card-value">{{ $company->availableSeats() }}</p>
        </article>
        <article class="card">
            <p class="card-label">Booking closes</p>
            <p class="card-value" style="font-size: 18px;">{{ $trip->bookingCutoffAt()?->format('M j, H:i') ?? '-' }}</p>
        </article>
    </section>

    @if (! $bookable)
        <div class="alert alert-error">This trip is not open for booking. The departure may be too close or the trip is no longer scheduled.</div>
    @endif

    <div class="seat-legend">
        <span><i class="seat-dot seat-dot-free"></i> Available</span>
        <span><i class="seat-dot seat-dot-own"></i> Your seats</span>
        <span><i class="seat-dot seat-dot-taken"></i> Taken</span>
    </div>

    <form method="POST" action="{{ route('company.bookings.store') }}" id="seat-booking-form">
        @csrf
        <input type="hidden" name="trip_id" value="{{ $trip->id }}">

        <div class="seat-grid">
            @foreach ($trip->seats as $seat)
                @php($isOccupied = in_array($seat->id, $occupied, true))
                @php($isOwn = in_array($seat->id, $ownSeatIds, true))
                <label class="seat {{ $isOccupied ? ($isOwn ? 'is-own' : 'is-occupied') : 'is-free' }}">
                    @if (! $isOccupied)
                        <input type="checkbox" name="seats[]" value="{{ $seat->seat_number }}" class="seat-input" @disabled(! $bookable)>
                    @endif
                    <span class="seat-number">{{ $seat->seat_number }}</span>
                    @if ($isOwn)
                        <span class="seat-owner">Yours</span>
                    @endif
                </label>
            @endforeach
        </div>

        <div class="form-actions">
            <span class="muted" id="seat-summary">No seats selected.</span>
            <button class="btn btn-primary" type="submit" @disabled(! $bookable)>Book selected seats</button>
            <a class="btn btn-ghost" href="{{ route('company.bookings.index') }}">My bookings</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const inputs = document.querySelectorAll('.seat-input');
            const summary = document.getElementById('seat-summary');
            const max = {{ max(0, min($remainingOnTrip, $company->availableSeats())) }};

            const update = () => {
                const selected = [...inputs].filter((input) => input.checked);

                if (selected.length > max) {
                    selected.at(-1).checked = false;
                    return update();
                }

                summary.textContent = selected.length === 0
                    ? 'No seats selected.'
                    : `${selected.length} seat(s): ${selected.map((input) => input.value).join(', ')}`;
            };

            inputs.forEach((input) => input.addEventListener('change', update));
        })();
    </script>
@endpush

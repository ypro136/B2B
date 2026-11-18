@extends($layout)

@section('title', 'Trip details')
@section('eyebrow', 'Administration')
@section('page-title', 'Trip details')

@php
    $canManage = auth()->user()?->hasFeature(\App\Models\AdminPermission::MANAGE_TRIPS);
    $canPrice = auth()->user()?->hasFeature(\App\Models\AdminPermission::MANAGE_PRICING);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ $trip->route_name }}</h2>
            <p class="muted">{{ $trip->origin }} to {{ $trip->destination }} &middot; {{ $trip->departure_datetime?->format('M j, Y H:i') }}</p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('admin.trips.index') }}">Back to trips</a>
            @if ($canManage)
                <a class="btn" href="{{ route('admin.trips.edit', $trip) }}">Edit trip</a>
            @endif
            @if ($canManage && $trip->status === \App\Models\Trip::STATUS_SCHEDULED)
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
        </div>
    </div>

    <section class="cards">
        <article class="card">
            <p class="card-label">Status</p>
            <p class="card-value" style="font-size: 20px;">
                <span class="badge {{ $trip->status === 'SCHEDULED' ? 'badge-success' : ($trip->status === 'CANCELLED' ? 'badge-danger' : 'badge-muted') }}">{{ $trip->status }}</span>
            </p>
        </article>
        <article class="card">
            <p class="card-label">Seats sold</p>
            <p class="card-value">{{ $trip->confirmedSeats() }} / {{ $trip->total_seats }}</p>
        </article>
        <article class="card">
            <p class="card-label">Standard price</p>
            <p class="card-value">{{ number_format((float) $trip->standard_price, 2) }} {{ $trip->currency }}</p>
        </article>
        <article class="card">
            <p class="card-label">Per-seat cost</p>
            <p class="card-value">{{ number_format((float) $trip->per_seat_cost, 2) }} {{ $trip->currency }}</p>
        </article>
        <article class="card">
            <p class="card-label">Booking cutoff</p>
            <p class="card-value" style="font-size: 20px;">{{ $trip->cutoffHours() }}h before</p>
        </article>
    </section>

    @if ($canPrice)
        <section class="form-card">
            <h2>Pricing controls</h2>
            <div class="split section-gap">
                <form method="POST" action="{{ route('admin.trips.cost', $trip) }}" class="inline-form">
                    @csrf
                    <div class="field">
                        <label>Per-seat cost</label>
                        <input class="table-input" name="per_seat_cost" type="number" step="0.01" min="0" value="{{ $trip->per_seat_cost }}" required>
                    </div>
                    <button class="btn btn-small" type="submit">Update cost</button>
                </form>

                <form method="POST" action="{{ route('admin.trips.company-price', $trip) }}" class="inline-form">
                    @csrf
                    <div class="field">
                        <label>Company</label>
                        <select class="table-input" name="company_id" required>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Final price</label>
                        <input class="table-input" name="price" type="number" step="0.01" min="0" required>
                    </div>
                    <button class="btn btn-small btn-primary" type="submit">Save override</button>
                </form>
            </div>

            @if ($trip->companyPrices->isNotEmpty())
                <div class="table-wrap section-gap">
                    <table>
                        <thead><tr><th>Company</th><th>Override price</th></tr></thead>
                        <tbody>
                            @foreach ($trip->companyPrices as $override)
                                <tr>
                                    <td class="strong">{{ $override->company?->name ?? 'Company #'.$override->company_id }}</td>
                                    <td>{{ number_format((float) $override->price, 2) }} {{ $trip->currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- Numbered seat map disabled for the demo: booking is by number of seats only.
    <section class="form-card">
        <div class="page-head">
            <div>
                <h2>Seat map</h2>
                <p class="muted">Numbered seats update as companies book and cancel.</p>
            </div>
        </div>

        <div class="seat-legend section-gap">
            <span><i class="seat-dot seat-dot-free"></i> Available</span>
            <span><i class="seat-dot seat-dot-own"></i> Occupied</span>
        </div>

        <div class="seat-grid section-gap">
            @foreach ($trip->seats as $seat)
                @php($bookingSeat = $seat->bookingSeat)
                @php($booking = $bookingSeat?->booking)
                @php($isOccupied = in_array($seat->id, $occupied, true))
                <div class="seat {{ $isOccupied ? 'is-occupied' : 'is-free' }}"
                     title="{{ $isOccupied ? ($booking?->company?->name ?? 'Occupied') : 'Available' }}">
                    <span class="seat-number">{{ $seat->seat_number }}</span>
                    @if ($isOccupied)
                        <span class="seat-owner">{{ $booking?->company?->name ?? 'Booked' }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
    --}}

    <section>
        <div class="page-head">
            <div>
                <h2>Bookings</h2>
                <p class="muted">Confirmed and cancelled reservations for this departure.</p>
            </div>
        </div>

        <div class="table-wrap" style="margin-top: 12px;">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Seats</th>
                        <th>Seat numbers</th>
                        <th>Total</th>
                        <th>Settlement</th>
                        <th>Status</th>
                        <th>Booked by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trip->bookings->sortByDesc('created_at') as $booking)
                        <tr>
                            <td class="strong">{{ $booking->company?->name ?? '-' }}</td>
                            <td>{{ $booking->seats_count }}</td>
                            <td>{{ $booking->seats->pluck('seat_number')->implode(', ') ?: '-' }}</td>
                            <td>{{ number_format((float) $booking->total_amount, 2) }} {{ $booking->currency }}</td>
                            <td><span class="badge">{{ str_replace('_', ' ', $booking->settlement_status) }}</span></td>
                            <td>
                                <span class="badge {{ $booking->status === 'CONFIRMED' ? 'badge-success' : 'badge-muted' }}">{{ $booking->status }}</span>
                            </td>
                            <td>{{ $booking->bookedBy?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">No bookings on this trip yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

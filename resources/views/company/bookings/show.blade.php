@extends($layout)

@section('title', 'Booking details')
@section('eyebrow', 'Company Portal')
@section('page-title', 'Booking details')

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ $booking->trip?->route_name }}</h2>
            <p class="muted">
                {{ $booking->trip?->origin }} to {{ $booking->trip?->destination }} &middot;
                {{ $booking->trip?->departure_datetime?->format('M j, Y H:i') }}
            </p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('company.bookings.index') }}">Back to bookings</a>
            @if ($booking->status === \App\Models\Booking::STATUS_CONFIRMED)
                <form method="POST" action="{{ route('company.bookings.cancel', $booking) }}">
                    @csrf
                    <button class="btn btn-danger" type="submit">Cancel booking</button>
                </form>
            @endif
        </div>
    </div>

    <section class="cards">
        <article class="card">
            <p class="card-label">Status</p>
            <p class="card-value" style="font-size: 20px;">
                <span class="badge {{ $booking->status === 'CONFIRMED' ? 'badge-success' : 'badge-muted' }}">{{ $booking->status }}</span>
            </p>
        </article>
        <article class="card">
            <p class="card-label">Seats</p>
            <p class="card-value">{{ $booking->seats->pluck('seat_number')->implode(', ') ?: $booking->seats_count }}</p>
        </article>
        <article class="card">
            <p class="card-label">Total</p>
            <p class="card-value">{{ number_format((float) $booking->total_amount, 2) }} {{ $booking->currency }}</p>
        </article>
        <article class="card">
            <p class="card-label">Settled</p>
            <p class="card-value">{{ number_format((float) $booking->settled_amount, 2) }} {{ $booking->currency }}</p>
            <p class="card-note">{{ str_replace('_', ' ', $booking->settlement_status) }}</p>
        </article>
        <article class="card">
            <p class="card-label">Remaining due</p>
            <p class="card-value">{{ number_format($booking->remainingDue(), 2) }} {{ $booking->currency }}</p>
        </article>
    </section>

    <section class="form-card">
        <h2>Booking summary</h2>
        <div class="table-wrap section-gap">
            <table>
                <tbody>
                    <tr><th>Reference</th><td>#{{ $booking->id }}</td></tr>
                    <tr><th>Booked by</th><td>{{ $booking->bookedBy?->name ?? '-' }}</td></tr>
                    <tr><th>Booked at</th><td>{{ $booking->created_at?->format('M j, Y H:i') }}</td></tr>
                    <tr><th>Unit price</th><td>{{ number_format((float) $booking->unit_price, 2) }} {{ $booking->currency }}</td></tr>
                    <tr><th>VAT</th><td>{{ $booking->vat_enabled ? number_format((float) $booking->vat_rate, 2).'%' : 'Not applied' }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    @if ($booking->status === \App\Models\Booking::STATUS_CONFIRMED)
        <section class="form-card">
            <h2>Change a seat</h2>
            <p class="muted">Move one of your seats to any free seat on the same trip, while booking is still open.</p>

            <form method="POST" action="{{ route('company.bookings.change-seat', $booking) }}" class="inline-form section-gap">
                @csrf
                <div class="field">
                    <label>From seat</label>
                    <select class="table-input" name="from_seat" required>
                        @foreach ($booking->seats as $seat)
                            <option value="{{ $seat->seat_number }}">{{ $seat->seat_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>To seat</label>
                    <select class="table-input" name="to_seat" required>
                        @forelse ($freeSeats as $seatNumber)
                            <option value="{{ $seatNumber }}">{{ $seatNumber }}</option>
                        @empty
                            <option value="" disabled>No free seats</option>
                        @endforelse
                    </select>
                </div>
                <button class="btn btn-small btn-primary" type="submit" @disabled($freeSeats->isEmpty())>Move seat</button>
            </form>
        </section>
    @endif

    <section>
        <div class="page-head">
            <div>
                <h2>Payments and documents</h2>
                <p class="muted">Confirmed allocations and approved documents linked to this booking.</p>
            </div>
        </div>

        <div class="table-wrap" style="margin-top: 12px;">
            <table>
                <thead><tr><th>Payment date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
                <tbody>
                    @forelse ($booking->allocations as $allocation)
                        <tr>
                            <td>{{ $allocation->payment?->received_date?->format('M j, Y') }}</td>
                            <td>{{ number_format((float) $allocation->amount, 2) }} {{ $allocation->payment?->currency }}</td>
                            <td>{{ $allocation->payment?->method }}</td>
                            <td>{{ $allocation->payment?->reference ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty">No payment allocations yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-wrap section-gap">
            <table>
                <thead><tr><th>Document</th><th>Type</th><th>Total</th><th>State</th><th></th></tr></thead>
                <tbody>
                    @forelse ($booking->documents->where('state', \App\Models\Document::STATE_APPROVED) as $document)
                        <tr>
                            <td class="strong">{{ $document->number }}</td>
                            <td>{{ $document->typeLabel() }}</td>
                            <td>{{ number_format((float) $document->total, 2) }} {{ $document->currency }}</td>
                            <td><span class="badge badge-success">{{ $document->state }}</span></td>
                            <td class="actions">
                                <a class="btn btn-small" href="{{ route('company.documents.show', $document) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No approved documents yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

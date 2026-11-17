@extends($layout)

@section('title', 'Dashboard')
@section('eyebrow', 'Company Portal')
@section('page-title', $company?->name ?? 'Company dashboard')

@section('content')
    <section class="hero">
        <div>
            <h2>Book seats without leaving your desk</h2>
            <p>Pick numbered seats within your quota, track settlements and download approved invoices.</p>
        </div>
        <span class="hero-badge">Company overview</span>
    </section>

    <section class="cards">
        @foreach ($stats as $stat)
            <article class="card">
                <p class="card-label">{{ $stat['label'] }}</p>
                <p class="card-value">{{ $stat['value'] }}</p>
            </article>
        @endforeach
    </section>

    @if ($company)
        <section class="grid">
            <article class="panel">
                <h3>Upcoming trips</h3>
                <p>{{ $upcomingTrips }} trip(s) are currently open for booking.</p>
                <p style="margin-top: 12px;"><a class="btn btn-small" href="{{ route('company.trips.index') }}">Browse trips</a></p>
            </article>
            <article class="panel">
                <h3>Your bookings</h3>
                <p>{{ $company->confirmedSeats() }} seat(s) are currently held against your limit.</p>
                <p style="margin-top: 12px;"><a class="btn btn-small" href="{{ route('company.bookings.index') }}">View bookings</a></p>
            </article>
            <article class="panel">
                <h3>Need more seats?</h3>
                <p>Submit a seat request and the admin team will review it.</p>
                <p style="margin-top: 12px;"><a class="btn btn-small" href="{{ route('company.seat-requests.index') }}">Open requests</a></p>
            </article>
            <article class="panel">
                <h3>Quota</h3>
                @if ($quota)
                    <p>Base {{ $quota['base'] }}, bonus {{ $quota['bonus'] }}, used {{ $quota['used'] }}. {{ $quota['remaining'] }} seat(s) remain.</p>
                @endif
                <p style="margin-top: 12px;"><a class="btn btn-small" href="{{ route('company.trips.index') }}">Book seats</a></p>
            </article>
            <article class="panel">
                <h3>Payments and documents</h3>
                <p>Outstanding {{ number_format($outstanding, 2) }} EGP &middot; credit {{ number_format($creditBalance, 2) }} EGP.</p>
                <p style="margin-top: 12px;"><a class="btn btn-small" href="{{ route('company.payments.index') }}">Open finance</a></p>
            </article>
        </section>

        @if ($recentBookings->isNotEmpty())
            <section>
                <div class="page-head">
                    <div>
                        <h2>Recent bookings</h2>
                        <p class="muted">Your latest reservations.</p>
                    </div>
                </div>

                <div class="table-wrap" style="margin-top: 12px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Trip</th>
                                <th>Departure</th>
                                <th>Seats</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentBookings as $booking)
                                <tr>
                                    <td class="strong">{{ $booking->trip?->route_name }}</td>
                                    <td class="nowrap">{{ $booking->trip?->departure_datetime?->format('M j, Y H:i') }}</td>
                                    <td>{{ $booking->seats_count }}</td>
                                    <td>
                                        <span class="badge {{ $booking->status === 'CONFIRMED' ? 'badge-success' : 'badge-muted' }}">
                                            {{ $booking->status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @else
        <section class="panel">
            <h3>No company assigned</h3>
            <p>This account is not linked to a company yet. Contact the platform administrator.</p>
        </section>
    @endif
@endsection

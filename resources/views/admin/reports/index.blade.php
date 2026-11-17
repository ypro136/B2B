@extends($layout)

@section('title', 'Reports')
@section('eyebrow', 'Administration')
@section('page-title', 'Reports')

@section('content')
    <div class="page-head">
        <div>
            <h2>Platform performance</h2>
            <p class="muted">Seat utilization, trip demand, and recent administrative activity.</p>
        </div>
    </div>

    <section class="cards">
        @foreach ($summary as $stat)
            <article class="card">
                <p class="card-label">{{ $stat['label'] }}</p>
                <p class="card-value">{{ $stat['value'] }}</p>
                <p class="card-note">{{ $stat['note'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="section-gap">
        <div class="page-head">
            <div>
                <h2>Top companies by seats</h2>
                <p class="muted">Confirmed seats held on active trips, ranked against each company's limit.</p>
            </div>
        </div>

        <div class="table-wrap section-gap">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Seats used</th>
                        <th>Seat limit</th>
                        <th>Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topCompanies as $company)
                        <tr>
                            <td class="strong">{{ $company['name'] }}</td>
                            <td>{{ $company['seats_used'] }}</td>
                            <td>{{ $company['seat_limit'] }}</td>
                            <td><span class="badge {{ $company['utilization'] >= 80 ? 'badge-warning' : 'badge-success' }}">{{ $company['utilization'] }}%</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"><div class="empty">No confirmed bookings yet.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="section-gap">
        <div class="page-head">
            <div>
                <h2>Upcoming trips</h2>
                <p class="muted">The next departures and how full they are.</p>
            </div>
        </div>

        <div class="table-wrap section-gap">
            <table>
                <thead>
                    <tr>
                        <th>Route</th>
                        <th>Departs</th>
                        <th>Seats used</th>
                        <th>Capacity</th>
                        <th>Load</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($upcomingTrips as $trip)
                        <tr>
                            <td class="strong">{{ $trip['route_name'] }}</td>
                            <td>{{ $trip['departure_datetime']?->format('M j, Y H:i') }}</td>
                            <td>{{ $trip['seats_used'] }}</td>
                            <td>{{ $trip['total_seats'] }}</td>
                            <td><span class="badge {{ $trip['utilization'] >= 80 ? 'badge-warning' : 'badge-success' }}">{{ $trip['utilization'] }}%</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"><div class="empty">No upcoming trips scheduled.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="section-gap">
        <div class="page-head">
            <div>
                <h2>Recent activity</h2>
                <p class="muted">The latest audited administrative and booking events.</p>
            </div>
        </div>

        <div class="table-wrap section-gap">
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentActivity as $log)
                        <tr>
                            <td class="nowrap">{{ $log->created_at?->format('M j, Y H:i') }}</td>
                            <td>{{ $log->actor?->name ?? 'System' }}</td>
                            <td>{{ $log->action }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3"><div class="empty">No audit activity recorded yet.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

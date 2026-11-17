@extends($layout)

@section('title', 'Trip templates')
@section('eyebrow', 'Administration')
@section('page-title', 'Trip templates')

@section('content')
    <div class="page-head">
        <div>
            <h2>Recurring departures</h2>
            <p class="muted">Templates generate numbered-seat trips for the next 14 days on demand.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.templates.create') }}">New template</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Runs</th>
                    <th>Departure</th>
                    <th>Seats</th>
                    <th>Price</th>
                    <th>Validity</th>
                    <th>Generated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr>
                        <td>
                            <span class="strong">{{ $template->route_name }}</span><br>
                            <span class="muted">{{ $template->origin }} to {{ $template->destination }}</span>
                        </td>
                        <td>
                            @foreach ($template->weekdayNumbers() as $weekday)
                                <span class="badge badge-muted">{{ ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$weekday] }}</span>
                            @endforeach
                        </td>
                        <td class="nowrap">{{ $template->departure_time }}</td>
                        <td>{{ $template->total_seats }}</td>
                        <td>{{ number_format((float) $template->standard_price, 2) }}</td>
                        <td class="nowrap">
                            {{ $template->valid_from?->format('M j, Y') ?? 'Any' }}
                            &ndash;
                            {{ $template->valid_until?->format('M j, Y') ?? 'Open' }}
                        </td>
                        <td>{{ $template->trips_count }}</td>
                        <td class="actions">
                            <form method="POST" action="{{ route('admin.templates.generate', $template) }}">
                                @csrf
                                <button class="btn btn-small" type="submit">Generate trips</button>
                            </form>
                            <a class="btn btn-small btn-ghost" href="{{ route('admin.templates.edit', $template) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">No recurring templates yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $templates->links('vendor.pagination.portal') }}
@endsection

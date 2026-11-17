@extends($layout)

@section('title', 'Credits')
@section('eyebrow', 'Company Portal')
@section('page-title', 'Account credits')

@section('content')
    <div class="page-head">
        <div>
            <h2>Credit balance</h2>
            <p class="muted">Credits are issued by the operator and applied to bookings on your behalf.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('company.payments.index') }}">Payments</a>
    </div>

    <section class="cards">
        <article class="card">
            <p class="card-label">Available credit</p>
            <p class="card-value">{{ number_format($balance, 2) }} EGP</p>
        </article>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Booking</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td class="nowrap">{{ $movement->created_at?->format('M j, Y H:i') }}</td>
                        <td>
                            <span class="badge {{ $movement->type === 'ISSUED' ? 'badge-success' : 'badge-muted' }}">{{ $movement->type }}</span>
                        </td>
                        <td class="strong">{{ number_format((float) $movement->amount, 2) }} EGP</td>
                        <td>
                            @if ($movement->booking)
                                #{{ $movement->booking->id }} &middot; {{ $movement->booking->trip?->route_name }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $movement->notes ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty">No credit movements yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $movements->links('vendor.pagination.portal') }}
@endsection

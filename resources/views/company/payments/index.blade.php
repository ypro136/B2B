@extends($layout)

@section('title', 'Payments')
@section('eyebrow', 'Company Portal')
@section('page-title', 'Payments')

@section('content')
    <div class="page-head">
        <div>
            <h2>Payment history</h2>
            <p class="muted">Confirmed cash and bank payments received by the operator.</p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('company.documents.index') }}">Documents</a>
            <a class="btn btn-ghost" href="{{ route('company.credits.index') }}">Credits</a>
        </div>
    </div>

    <section class="cards">
        <article class="card">
            <p class="card-label">Outstanding balance</p>
            <p class="card-value">{{ number_format($outstanding, 2) }} EGP</p>
        </article>
        <article class="card">
            <p class="card-label">Credit balance</p>
            <p class="card-value">{{ number_format($creditBalance, 2) }} EGP</p>
        </article>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Allocated</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td class="nowrap">{{ $payment->received_date?->format('M j, Y') }}</td>
                        <td class="strong">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
                        <td>{{ $payment->method }}</td>
                        <td>{{ $payment->reference ?? '-' }}</td>
                        <td>{{ number_format($payment->allocatedAmount(), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty">No confirmed payments yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $payments->links('vendor.pagination.portal') }}
@endsection

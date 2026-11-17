@extends($layout)

@section('title', 'Finance report')
@section('eyebrow', 'Finance')
@section('page-title', 'Finance report')

@php
    $canClose = auth()->user()?->hasFeature(\App\Models\AdminPermission::CLOSE_PERIODS);
    $canReopen = auth()->user()?->hasFeature(\App\Models\AdminPermission::REOPEN_PERIODS);
@endphp

@section('content')
    <div class="actions" style="margin-bottom: 16px;">
        <a class="btn btn-small btn-primary" href="#finance-report">Finance report</a>
        <a class="btn btn-small btn-ghost" href="#periods">Periods</a>
    </div>

    <div class="page-head" id="finance-report">
        <div>
            <h2>Revenue, cost and profit</h2>
            <p class="muted">Confirmed payments are the revenue basis. VAT is shown as an internal component, not added on top.</p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('admin.reports.finance.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Export CSV</a>
            <a class="btn btn-ghost" href="#periods">Periods</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.reports.finance') }}" class="inline-form">
        <div class="field">
            <label>From</label>
            <input class="table-input" type="date" name="from" value="{{ $from->toDateString() }}">
        </div>
        <div class="field">
            <label>To</label>
            <input class="table-input" type="date" name="to" value="{{ $to->toDateString() }}">
        </div>
        <button class="btn btn-small" type="submit">Apply range</button>
        <a class="btn btn-small btn-ghost" href="{{ route('admin.reports.finance') }}">This month</a>
    </form>

    <section class="cards">
        <article class="card">
            <p class="card-label">Confirmed revenue</p>
            <p class="card-value">{{ number_format($confirmedRevenue, 2) }}</p>
        </article>
        <article class="card">
            <p class="card-label">Seat cost</p>
            <p class="card-value">{{ number_format($seatCost, 2) }}</p>
        </article>
        <article class="card">
            <p class="card-label">Net profit</p>
            <p class="card-value">{{ number_format($netProfit, 2) }}</p>
        </article>
        <article class="card">
            <p class="card-label">Internal VAT component</p>
            <p class="card-value">{{ number_format($vatComponent, 2) }}</p>
        </article>
        <article class="card">
            <p class="card-label">Pending payments</p>
            <p class="card-value">{{ $pendingPayments }}</p>
        </article>
    </section>

    <section class="split">
        <div class="form-card">
            <h2>Revenue by company</h2>
            <div class="table-wrap section-gap">
                <table>
                    <thead><tr><th>Company</th><th>Confirmed revenue</th></tr></thead>
                    <tbody>
                        @forelse ($revenueByCompany as $row)
                            <tr>
                                <td class="strong">{{ $row['company'] }}</td>
                                <td>{{ number_format($row['revenue'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2"><div class="empty">No confirmed payments in this range.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-card">
            <h2>Ledger activity</h2>
            <div class="table-wrap section-gap">
                <table>
                    <thead><tr><th>Type</th><th>Company</th><th>Amount</th></tr></thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td><span class="badge badge-muted">{{ $transaction->type }}</span></td>
                                <td>{{ $transaction->company?->name ?? '-' }}</td>
                                <td>{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><div class="empty">No ledger entries in this range.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <section id="periods" style="margin-top: 32px;">
        <div class="page-head">
            <div>
                <h2>Accounting periods</h2>
                <p class="muted">Closed periods reject new payments and ledger changes until reopened with a reason.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Confirmed payments</th>
                        <th>Status</th>
                        <th>Closed at</th>
                        <th>Reopened</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($periods as $period)
                        @php($isClosed = $period['status'] === \App\Models\MonthlyClosing::STATUS_CLOSED)
                        <tr>
                            <td class="strong">{{ $period['label'] }}</td>
                            <td>{{ number_format($period['confirmed_payments'], 2) }}</td>
                            <td>
                                <span class="badge {{ $isClosed ? 'badge-success' : 'badge-warning' }}">{{ $period['status'] }}</span>
                            </td>
                            <td class="nowrap">{{ $period['closed_at']?->format('M j, Y H:i') ?? '-' }}</td>
                            <td class="nowrap">{{ $period['reopened_at']?->format('M j, Y H:i') ?? '-' }}</td>
                            <td class="actions">
                                @if (! $isClosed && $canClose)
                                    <form method="POST" action="{{ route('admin.reports.finance.periods.close', $period['period']) }}">
                                        @csrf
                                        <button class="btn btn-small btn-primary" type="submit">Close period</button>
                                    </form>
                                @endif
                                @if ($isClosed && $canReopen)
                                    <form method="POST" action="{{ route('admin.reports.finance.periods.reopen', $period['period']) }}" class="inline-form">
                                        @csrf
                                        <input class="table-input" name="reason" type="text" placeholder="Reopen reason" required>
                                        <button class="btn btn-small btn-danger" type="submit">Reopen</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

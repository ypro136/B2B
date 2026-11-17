@extends($layout)

@section('title', 'Payments')
@section('eyebrow', 'Payments')
@section('page-title', 'Payments')

@section('content')
    @include('admin::partials.payments-subnav')
    <div class="page-head">
        <div>
            <h2>Offline payment register</h2>
            <p class="muted">Pending payments do not post to the ledger until they are confirmed.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.payments.create') }}">Record payment</a>
    </div>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="inline-form">
        <div class="field">
            <label>Status</label>
            <select class="table-input" name="status">
                <option value="">All statuses</option>
                @foreach (['PENDING', 'CONFIRMED', 'REJECTED'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Company</label>
            <select class="table-input" name="company_id">
                <option value="">All companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((string) $companyId === (string) $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-small" type="submit">Filter</button>
        <a class="btn btn-small btn-ghost" href="{{ route('admin.payments.index') }}">Clear</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Company</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Allocated</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td class="nowrap">{{ $payment->received_date?->format('M j, Y') }}</td>
                        <td class="strong">{{ $payment->company?->name ?? '-' }}</td>
                        <td>{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
                        <td>{{ $payment->method }}</td>
                        <td>{{ $payment->reference ?? '-' }}</td>
                        <td>{{ number_format((float) ($payment->allocated_total ?? 0), 2) }}</td>
                        <td>
                            <span class="badge {{ $payment->status === 'CONFIRMED' ? 'badge-success' : ($payment->status === 'REJECTED' ? 'badge-danger' : 'badge-warning') }}">
                                {{ $payment->status }}
                            </span>
                        </td>
                        <td class="actions">
                            <a class="btn btn-small" href="{{ route('admin.payments.show', $payment) }}">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">No payments recorded.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $payments->links('vendor.pagination.portal') }}
@endsection

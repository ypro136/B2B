@extends($layout)

@section('title', 'Documents')
@section('eyebrow', 'Payments')
@section('page-title', 'Documents')

@section('content')
    @include('admin::partials.payments-subnav')
    <div class="page-head">
        <div>
            <h2>Invoices and receipts</h2>
            <p class="muted">Drafts must be approved before companies can see them. Voiding preserves history.</p>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('admin.payments.documents.monthly-form') }}">Monthly invoice</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.payments.documents.index') }}" class="inline-form">
        <div class="field">
            <label>Type</label>
            <select class="table-input" name="type">
                <option value="">All types</option>
                @foreach (['booking_invoice' => 'Booking invoice', 'monthly_invoice' => 'Monthly invoice', 'payment_receipt' => 'Payment receipt'] as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>State</label>
            <select class="table-input" name="state">
                <option value="">All states</option>
                @foreach (['DRAFT', 'APPROVED', 'VOIDED'] as $value)
                    <option value="{{ $value }}" @selected($state === $value)>{{ $value }}</option>
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
        <a class="btn btn-small btn-ghost" href="{{ route('admin.payments.documents.index') }}">Clear</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Type</th>
                    <th>Company</th>
                    <th>Period</th>
                    <th>Total</th>
                    <th>State</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr>
                        <td class="strong">{{ $document->number }}</td>
                        <td>{{ $document->typeLabel() }}</td>
                        <td>{{ $document->company?->name ?? '-' }}</td>
                        <td class="nowrap">
                            @if ($document->period_start)
                                {{ $document->period_start->format('M Y') }}
                            @else
                                {{ $document->created_at?->format('M j, Y') }}
                            @endif
                        </td>
                        <td>{{ number_format((float) $document->total, 2) }} {{ $document->currency }}</td>
                        <td>
                            <span class="badge {{ $document->state === 'APPROVED' ? 'badge-success' : ($document->state === 'VOIDED' ? 'badge-danger' : 'badge-warning') }}">
                                {{ $document->state }}
                            </span>
                        </td>
                        <td class="actions">
                            <a class="btn btn-small" href="{{ route('admin.payments.documents.show', $document) }}">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty">No documents match the filters.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $documents->links('vendor.pagination.portal') }}
@endsection

@extends($layout)

@section('title', 'Documents')
@section('eyebrow', 'Company Portal')
@section('page-title', 'Documents')

@section('content')
    <div class="page-head">
        <div>
            <h2>Invoices and receipts</h2>
            <p class="muted">Approved documents issued to {{ $company?->name }}.</p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('company.payments.index') }}">Payments</a>
            <a class="btn btn-ghost" href="{{ route('company.bookings.index') }}">Bookings</a>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Type</th>
                    <th>Period</th>
                    <th>Total</th>
                    <th>Approved</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr>
                        <td class="strong">{{ $document->number }}</td>
                        <td>{{ $document->typeLabel() }}</td>
                        <td class="nowrap">{{ $document->period_start?->format('M Y') ?? $document->created_at?->format('M j, Y') }}</td>
                        <td>{{ number_format((float) $document->total, 2) }} {{ $document->currency }}</td>
                        <td class="nowrap">{{ $document->approved_at?->format('M j, Y') ?? '-' }}</td>
                        <td class="actions">
                            <a class="btn btn-small" href="{{ route('company.documents.show', $document) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty">No approved documents yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $documents->links('vendor.pagination.portal') }}
@endsection

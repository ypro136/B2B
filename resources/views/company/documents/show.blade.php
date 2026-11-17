@extends($layout)

@section('title', $document->number)
@section('eyebrow', 'Company Portal')
@section('page-title', $document->number)

@php
    $issuerName = \App\Models\Setting::get('issuer_name', config('app.name'));
    $issuerTax = \App\Models\Setting::get('issuer_tax_number');
    $issuerAddress = \App\Models\Setting::get('issuer_address');
@endphp

@section('content')
    <div class="page-head no-print">
        <div>
            <h2>{{ $document->typeLabel() }} &middot; {{ $document->number }}</h2>
            <p class="muted">Approved {{ $document->approved_at?->format('M j, Y H:i') }}</p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('company.documents.index') }}">Back to documents</a>
            <button class="btn" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <article class="document-sheet">
        <header class="document-head">
            <div>
                <h2>{{ $issuerName }}</h2>
                @if ($issuerAddress)<p>{{ $issuerAddress }}</p>@endif
                @if ($issuerTax)<p>Tax no. {{ $issuerTax }}</p>@endif
            </div>
            <div class="document-meta">
                <p><span class="strong">{{ $document->typeLabel() }}</span></p>
                <p>{{ $document->number }}</p>
                <p>{{ $document->created_at?->format('M j, Y') }}</p>
            </div>
        </header>

        <section class="document-parties">
            <div>
                <p class="document-label">Billed to</p>
                <p class="strong">{{ $document->company?->name }}</p>
                @if ($document->company?->legal_name)<p>{{ $document->company?->legal_name }}</p>@endif
                @if ($document->company?->address)<p>{{ $document->company?->address }}</p>@endif
                @if ($document->company?->tax_number)<p>Tax no. {{ $document->company?->tax_number }}</p>@endif
            </div>
            <div>
                <p class="document-label">Reference</p>
                @if ($document->booking)
                    <p>Booking #{{ $document->booking->id }}</p>
                    <p>{{ $document->booking->trip?->route_name }}</p>
                @endif
                @if ($document->payment)
                    <p>Payment #{{ $document->payment->id }}</p>
                @endif
                @if ($document->period_start)
                    <p>Period {{ $document->period_start->format('M Y') }}</p>
                @endif
            </div>
        </section>

        <table class="document-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="is-numeric">Seats</th>
                    <th class="is-numeric">Unit</th>
                    <th class="is-numeric">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->lines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td class="is-numeric">{{ $line->seats }}</td>
                        <td class="is-numeric">{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="is-numeric">{{ number_format((float) $line->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="is-numeric">Total</th>
                    <th class="is-numeric">{{ number_format((float) $document->total, 2) }} {{ $document->currency }}</th>
                </tr>
            </tfoot>
        </table>

        <footer class="document-footer">
            <span>{{ $issuerName }}</span>
            <span>{{ $document->number }}</span>
        </footer>
    </article>
@endsection

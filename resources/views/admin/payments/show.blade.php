@extends($layout)

@section('title', 'Payment details')
@section('eyebrow', 'Payments')
@section('page-title', 'Payment details')

@php
    $canConfirm = auth()->user()?->hasFeature(\App\Models\AdminPermission::CONFIRM_PAYMENTS);
    $canAllocate = auth()->user()?->hasFeature(\App\Models\AdminPermission::ALLOCATE_PAYMENTS);
    $canRefund = auth()->user()?->hasFeature(\App\Models\AdminPermission::ISSUE_REFUNDS);
@endphp

@section('content')
    @include('admin::partials.payments-subnav')
    <div class="page-head">
        <div>
            <h2>Payment #{{ $payment->id }} &middot; {{ $payment->company?->name }}</h2>
            <p class="muted">
                {{ $payment->received_date?->format('M j, Y') }} &middot; {{ $payment->method }} &middot;
                recorded by {{ $payment->recorder?->name ?? 'system' }}
            </p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('admin.payments.index') }}">Back to payments</a>
            @if ($payment->isPending() && $canConfirm)
                <form method="POST" action="{{ route('admin.payments.confirm', $payment) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">Confirm payment</button>
                </form>
                <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="inline-form">
                    @csrf
                    <input class="table-input" name="note" type="text" placeholder="Rejection note">
                    <button class="btn btn-danger" type="submit">Reject</button>
                </form>
            @endif
        </div>
    </div>

    <section class="cards">
        <article class="card">
            <p class="card-label">Amount</p>
            <p class="card-value">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</p>
        </article>
        <article class="card">
            <p class="card-label">Allocated</p>
            <p class="card-value">{{ number_format($payment->allocatedAmount(), 2) }}</p>
        </article>
        <article class="card">
            <p class="card-label">Refunded / credited</p>
            <p class="card-value">{{ number_format($payment->refundedAmount(), 2) }}</p>
        </article>
        <article class="card">
            <p class="card-label">Available to allocate</p>
            <p class="card-value">{{ number_format($available, 2) }}</p>
        </article>
        <article class="card">
            <p class="card-label">Status</p>
            <p class="card-value" style="font-size: 20px;">
                <span class="badge {{ $payment->status === 'CONFIRMED' ? 'badge-success' : ($payment->status === 'REJECTED' ? 'badge-danger' : 'badge-warning') }}">{{ $payment->status }}</span>
            </p>
        </article>
    </section>

    @if ($payment->reference || $payment->notes)
        <section class="form-card">
            <h2>Reference and notes</h2>
            <p class="section-gap">{{ $payment->reference ?? 'No reference' }}</p>
            <p class="muted">{{ $payment->notes ?? 'No notes.' }}</p>
        </section>
    @endif

    @if ($payment->isConfirmed() && $canAllocate)
        <section class="form-card">
            <h2>Allocate to bookings</h2>
            <p class="muted">Allocate up to {{ number_format($available, 2) }} {{ $payment->currency }} across open bookings.</p>

            @if ($bookings->isEmpty())
                <div class="empty">All bookings for this company are fully settled.</div>
            @else
                <form method="POST" action="{{ route('admin.payments.allocate', $payment) }}">
                    @csrf
                    <div class="table-wrap section-gap">
                        <table>
                            <thead><tr><th>Booking</th><th>Total</th><th>Settled</th><th>Due</th><th>Allocate</th></tr></thead>
                            <tbody>
                                @foreach ($bookings as $booking)
                                    <tr>
                                        <td>
                                            <span class="strong">{{ $booking->trip?->route_name }}</span><br>
                                            <span class="muted">#{{ $booking->id }} &middot; {{ $booking->seats_count }} seat(s)</span>
                                        </td>
                                        <td>{{ number_format((float) $booking->total_amount, 2) }}</td>
                                        <td>{{ number_format((float) $booking->settled_amount, 2) }}</td>
                                        <td>{{ number_format($booking->remainingDue(), 2) }}</td>
                                        <td>
                                            <input class="table-input" type="number" step="0.01" min="0"
                                                   max="{{ $booking->remainingDue() }}"
                                                   name="allocations[{{ $booking->id }}]" value="0">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Save allocations</button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    @if ($payment->isConfirmed() && $canRefund && $available > 0)
        <section class="form-card">
            <h2>Refund or issue credit</h2>
            <div class="split section-gap">
                <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="inline-form">
                    @csrf
                    <div class="field">
                        <label>Amount</label>
                        <input class="table-input" name="amount" type="number" step="0.01" min="0.01" max="{{ $available }}" required>
                    </div>
                    <div class="field">
                        <label>Kind</label>
                        <select class="table-input" name="kind">
                            <option value="cash">Cash refund</option>
                            <option value="credit">Account credit</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Note</label>
                        <input class="table-input" name="note" type="text">
                    </div>
                    <button class="btn btn-small btn-danger" type="submit">Record refund</button>
                </form>

                <form method="POST" action="{{ route('admin.credits.store') }}" class="inline-form">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $payment->company_id }}">
                    <input type="hidden" name="note" value="Manual credit from payment #{{ $payment->id }}">
                    <div class="field">
                        <label>Credit amount</label>
                        <input class="table-input" name="amount" type="number" step="0.01" min="0.01" required>
                    </div>
                    <button class="btn btn-small" type="submit">Issue company credit</button>
                </form>
            </div>
        </section>
    @endif

    <section>
        <div class="page-head">
            <div>
                <h2>Allocations</h2>
                <p class="muted">How this payment was applied to bookings.</p>
            </div>
        </div>

        <div class="table-wrap" style="margin-top: 12px;">
            <table>
                <thead><tr><th>Booking</th><th>Trip</th><th>Amount</th><th>Allocated at</th></tr></thead>
                <tbody>
                    @forelse ($payment->allocations as $allocation)
                        <tr>
                            <td class="strong">#{{ $allocation->booking_id }}</td>
                            <td>{{ $allocation->booking?->trip?->route_name ?? '-' }}</td>
                            <td>{{ number_format((float) $allocation->amount, 2) }} {{ $payment->currency }}</td>
                            <td class="nowrap">{{ $allocation->created_at?->format('M j, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty">No allocations yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <div class="page-head">
            <div>
                <h2>Documents</h2>
                <p class="muted">Receipt drafts created for this payment.</p>
            </div>
        </div>

        <div class="table-wrap" style="margin-top: 12px;">
            <table>
                <thead><tr><th>Number</th><th>Type</th><th>Total</th><th>State</th><th></th></tr></thead>
                <tbody>
                    @forelse ($payment->documents as $document)
                        <tr>
                            <td class="strong">{{ $document->number }}</td>
                            <td>{{ $document->typeLabel() }}</td>
                            <td>{{ number_format((float) $document->total, 2) }} {{ $document->currency }}</td>
                            <td><span class="badge {{ $document->state === 'APPROVED' ? 'badge-success' : 'badge-warning' }}">{{ $document->state }}</span></td>
                            <td class="actions">
                                <a class="btn btn-small" href="{{ route('admin.payments.documents.show', $document) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No documents linked to this payment.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

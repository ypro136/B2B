@extends($layout)

@section('title', 'Record payment')
@section('eyebrow', 'Payments')
@section('page-title', 'Record payment')

@section('content')
@include('admin::partials.payments-subnav')

    <div class="page-head">
        <div>
            <h2>New offline payment</h2>
            <p class="muted">Payments start pending. Confirm the payment to post it to the ledger and draft a receipt.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.payments.index') }}">Back to payments</a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.payments.store') }}">
            @csrf

            <div class="field" style="margin-bottom: 16px;">
                <label for="booking_shortcut">Fill from a booking pending payment</label>
                @if ($pendingBookings->isNotEmpty())
                    <select id="booking_shortcut">
                        <option value="">Choose a booking to pre-fill company, amount and currency…</option>
                        @foreach ($pendingBookings as $booking)
                            <option value="{{ $booking['id'] }}"
                                data-company-id="{{ $booking['company_id'] }}"
                                data-amount="{{ $booking['outstanding'] }}"
                                data-currency="{{ $booking['currency'] }}">
                                {{ $booking['company'] }} — {{ $booking['trip'] ?? 'Booking #'.$booking['id'] }} — outstanding {{ number_format($booking['outstanding'], 2) }} {{ $booking['currency'] }}
                            </option>
                        @endforeach
                    </select>
                    <p class="muted">Pre-fills company, amount and currency. You can still edit every field before recording.</p>
                @else
                    <p class="muted">No bookings are pending payment right now. You can still record a payment manually below.</p>
                @endif
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="company_id">Company</label>
                    <select id="company_id" name="company_id" required>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="amount">Amount</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required>
                </div>
                <div class="field">
                    <label for="currency">Currency</label>
                    <input id="currency" name="currency" type="text" maxlength="3" value="{{ old('currency', 'EGP') }}">
                </div>
                <div class="field">
                    <label for="received_date">Received date</label>
                    <input id="received_date" name="received_date" type="date" value="{{ old('received_date', now()->toDateString()) }}" required>
                </div>
                <div class="field">
                    <label for="method">Method</label>
                    <select id="method" name="method" required>
                        <option value="CASH" @selected(old('method') === 'CASH')>Cash</option>
                        <option value="BANK" @selected(old('method') === 'BANK')>Bank transfer</option>
                    </select>
                </div>
                <div class="field">
                    <label for="reference">Reference</label>
                    <input id="reference" name="reference" type="text" maxlength="120" value="{{ old('reference') }}">
                </div>
                <div class="field is-wide">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Record payment</button>
                <a class="btn btn-ghost" href="{{ route('admin.payments.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var shortcut = document.getElementById('booking_shortcut');
        if (!shortcut) { return; }
        shortcut.addEventListener('change', function () {
            var option = shortcut.options[shortcut.selectedIndex];
            if (!option || !option.dataset.companyId) { return; }
            document.getElementById('company_id').value = option.dataset.companyId;
            document.getElementById('amount').value = option.dataset.amount;
            document.getElementById('currency').value = option.dataset.currency;
        });
    })();
</script>
@endpush

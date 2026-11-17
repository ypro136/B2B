@extends($layout)

@section('title', 'Create company')
@section('eyebrow', 'Administration')
@section('page-title', 'Create company')

@section('content')
    <div class="page-head">
        <div>
            <h2>New client company</h2>
            <p class="muted">Create the company first, then add up to two active portal users.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.companies.index') }}">Back to companies</a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.companies.store') }}">
            @csrf

            <div class="form-grid">
                <div class="field is-wide">
                    <label for="name">Company name</label>
                    <input id="name" name="name" type="text" maxlength="160" value="{{ old('name') }}" required>
                </div>
                <div class="field">
                    <label for="legal_name">Legal name</label>
                    <input id="legal_name" name="legal_name" type="text" maxlength="160" value="{{ old('legal_name') }}">
                </div>
                <div class="field">
                    <label for="tax_number">Tax number</label>
                    <input id="tax_number" name="tax_number" type="text" maxlength="60" value="{{ old('tax_number') }}">
                </div>
                <div class="field is-wide">
                    <label for="address">Invoice address</label>
                    <input id="address" name="address" type="text" maxlength="255" value="{{ old('address') }}">
                </div>
                <div class="field">
                    <label for="contact">Contact</label>
                    <input id="contact" name="contact" type="text" maxlength="255" value="{{ old('contact') }}" placeholder="Email or phone">
                </div>
                <div class="field">
                    <label for="seat_limit">Base seat limit</label>
                    <input id="seat_limit" name="seat_limit" type="number" min="0" max="1000000" value="{{ old('seat_limit', 0) }}" required>
                </div>
                <div class="field">
                    <label>Partial payments</label>
                    <label class="checkbox">
                        <input type="hidden" name="partial_payment_allowed" value="0">
                        <input type="checkbox" name="partial_payment_allowed" value="1" @checked(old('partial_payment_allowed', '0') == '1')>
                        Allow partial settlement
                    </label>
                </div>
                <div class="field">
                    <label>Status</label>
                    <label class="checkbox">
                        <input type="hidden" name="is_active" value="0">
                        <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') == '1')>
                        Active
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Create company</button>
                <a class="btn btn-ghost" href="{{ route('admin.companies.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

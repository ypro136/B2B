@extends($layout)

@section('title', 'Monthly invoice')
@section('eyebrow', 'Payments')
@section('page-title', 'Monthly invoice')

@section('content')
    @include('admin::partials.payments-subnav')
    <div class="page-head">
        <div>
            <h2>Draft a monthly invoice</h2>
            <p class="muted">Combines bookings that became fully settled during the chosen month.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.payments.documents.index') }}">Back to documents</a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.payments.documents.monthly') }}">
            @csrf

            <div class="form-grid">
                <div class="field">
                    <label for="company_id">Company</label>
                    <select id="company_id" name="company_id" required>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="period">Month</label>
                    <input id="period" name="period" type="month" value="{{ old('period', now()->format('Y-m')) }}" required>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Generate draft</button>
                <a class="btn btn-ghost" href="{{ route('admin.payments.documents.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

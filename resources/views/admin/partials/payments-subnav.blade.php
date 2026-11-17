@php
    $canPayments = auth()->user()?->hasFeature(\App\Models\AdminPermission::RECORD_PAYMENTS);
    $canDocuments = auth()->user()?->hasFeature(\App\Models\AdminPermission::PREPARE_DOCUMENTS);
    $onDocuments = request()->routeIs('admin.payments.documents.*');
@endphp
<div class="actions" style="margin-bottom: 16px;">
    @if ($canPayments)
        <a class="btn btn-small {{ $onDocuments ? 'btn-ghost' : 'btn-primary' }}" href="{{ route('admin.payments.index') }}">Payments</a>
    @endif
    @if ($canDocuments)
        <a class="btn btn-small {{ $onDocuments ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.payments.documents.index') }}">Documents</a>
    @endif
</div>

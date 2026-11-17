@extends($layout)

@section('title', 'Audit log')
@section('eyebrow', 'Administration')
@section('page-title', 'Audit log')

@section('content')
    <div class="page-head">
        <div>
            <h2>Platform activity</h2>
            <p class="muted">Every seat-limit change, request decision, booking, and trip event is recorded.</p>
        </div>
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="actions">
            <select name="action" class="table-input">
                <option value="">All actions</option>
                @foreach ($actions as $option)
                    <option value="{{ $option }}" @selected($action === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <button class="btn btn-small" type="submit">Filter</button>
            @if ($action !== '')
                <a class="btn btn-small btn-ghost" href="{{ route('admin.audit-logs.index') }}">Clear</a>
            @endif
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Target</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="nowrap">{{ $log->created_at?->format('M j, Y H:i') }}</td>
                        <td>{{ $log->actor?->name ?? 'System' }}</td>
                        <td><span class="badge">{{ $log->action }}</span></td>
                        <td class="nowrap">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><div class="empty">No matching audit entries.</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links('vendor.pagination.portal') }}
@endsection

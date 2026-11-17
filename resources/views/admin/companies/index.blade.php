@extends($layout)

@section('title', 'Companies')
@section('eyebrow', 'Administration')
@section('page-title', 'Companies')

@section('content')
    <div class="page-head">
        <div>
            <h2>Client companies</h2>
            <p class="muted">Quota allowances, account policy and company workspaces.</p>
        </div>
        @if ($canManage)
            <a class="btn btn-primary" href="{{ route('admin.companies.create') }}">Add company</a>
        @endif
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Company</th>
                    <th>Contact</th>
                    <th>Allowance</th>
                    <th>Bonus</th>
                    <th>Used</th>
                    <th>Users</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    <tr>
                        <td class="strong">{{ $company->name }}</td>
                        <td>{{ $company->contact ?? '-' }}</td>
                        <td>{{ $company->seat_limit }}</td>
                        <td>{{ $company->bonus_seats }}</td>
                        <td>{{ $company->usedSeats() }}</td>
                        <td>{{ $company->users_count }}</td>
                        <td>
                            <span class="badge {{ $company->is_active ? 'badge-success' : 'badge-muted' }}">
                                {{ $company->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="actions">
                            <a class="btn btn-small" href="{{ route('admin.companies.edit', $company) }}">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8"><div class="empty">No companies yet.</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $companies->links('vendor.pagination.portal') }}
@endsection

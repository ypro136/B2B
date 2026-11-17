@extends($layout)

@section('title', 'Manage company')
@section('eyebrow', 'Administration')
@section('page-title', 'Manage company')

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ $company->name }}</h2>
            <p class="muted">Limits, quota window, users, CRM and commercial history.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.companies.index') }}">Back to companies</a>
    </div>

    <section class="cards">
        <article class="card">
            <p class="card-label">Base limit</p>
            <p class="card-value">{{ $quota['base'] }}</p>
        </article>
        <article class="card">
            <p class="card-label">Bonus seats</p>
            <p class="card-value">{{ $quota['bonus'] }}</p>
        </article>
        <article class="card">
            <p class="card-label">Used since reset</p>
            <p class="card-value">{{ $quota['used'] }}</p>
        </article>
        <article class="card">
            <p class="card-label">Remaining</p>
            <p class="card-value">{{ $quota['remaining'] }}</p>
        </article>
    </section>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.companies.update', $company) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label for="legal_name">Legal name</label>
                    <input id="legal_name" name="legal_name" type="text" maxlength="160" value="{{ old('legal_name', $company->legal_name) }}">
                </div>
                <div class="field">
                    <label for="tax_number">Tax number</label>
                    <input id="tax_number" name="tax_number" type="text" maxlength="60" value="{{ old('tax_number', $company->tax_number) }}">
                </div>
                <div class="field is-wide">
                    <label for="address">Invoice address</label>
                    <input id="address" name="address" type="text" maxlength="255" value="{{ old('address', $company->address) }}">
                </div>
                <div class="field">
                    <label for="contact">Contact</label>
                    <input id="contact" name="contact" type="text" maxlength="255" value="{{ old('contact', $company->contact) }}">
                </div>
                <div class="field">
                    <label for="seat_limit">Base seat limit ({{ $seatsInUse }} held)</label>
                    <input id="seat_limit" name="seat_limit" type="number" min="0" max="1000000" value="{{ old('seat_limit', $company->seat_limit) }}" required>
                </div>
                <div class="field">
                    <label>Partial payments</label>
                    <label class="checkbox">
                        <input type="hidden" name="partial_payment_allowed" value="0">
                        <input type="checkbox" name="partial_payment_allowed" value="1" @checked(old('partial_payment_allowed', $company->partial_payment_allowed ? '1' : '0') == '1')>
                        Allow partial settlement
                    </label>
                </div>
                <div class="field">
                    <label>Status</label>
                    <label class="checkbox">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $company->is_active ? '1' : '0') == '1')>
                        Active
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save company</button>
                @if ($canResetQuotas)
                    <button class="btn btn-ghost" type="submit" form="quota-reset-form">Reset quota window</button>
                @endif
            </div>
        </form>

        @if ($canResetQuotas)
            <form id="quota-reset-form" method="POST" action="{{ route('admin.companies.quota-reset', $company) }}" class="section-gap">
                @csrf
                <p class="muted" style="margin:0;">Resetting starts a new usage window and clears unused bonus seats. Active bookings are kept.</p>
            </form>
        @endif
    </div>

    <section class="form-card">
        <div class="page-head">
            <div>
                <h2>User accounts ({{ $users->count() }}/2)</h2>
                <p class="muted">Each company can have at most two active portal accounts.</p>
            </div>
        </div>

        <div class="table-wrap section-gap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $companyUser)
                        <tr>
                            <td class="strong">{{ $companyUser->name }}</td>
                            <td>{{ $companyUser->email }}</td>
                            <td>{{ $companyUser->roles->pluck('display_name')->filter()->join(', ') ?: '-' }}</td>
                            <td>
                                <span class="badge {{ $companyUser->is_active ? 'badge-success' : 'badge-muted' }}">
                                    {{ $companyUser->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="actions">
                                @if ($canManageUsers)
                                    <form method="POST" action="{{ route('admin.companies.users.toggle', [$company, $companyUser]) }}">
                                        @csrf
                                        <button class="btn btn-small {{ $companyUser->is_active ? 'btn-danger' : '' }}" type="submit">
                                            {{ $companyUser->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"><div class="empty">No user accounts yet.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($canManageUsers)
            @if ($canAddUser)
                <form class="section-gap" method="POST" action="{{ route('admin.companies.users.store', $company) }}">
                    @csrf

                    <div class="form-grid">
                        <div class="field">
                            <label for="name">Name</label>
                            <input id="name" name="name" type="text" maxlength="120" value="{{ old('name') }}" required>
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" maxlength="255" value="{{ old('email') }}" required>
                        </div>
                        <div class="field">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                @foreach ($roleOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" minlength="8" required>
                        </div>
                        <div class="field">
                            <label for="password_confirmation">Confirm password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Add user account</button>
                    </div>
                </form>
            @else
                <div class="empty">This company already has two active user accounts.</div>
            @endif
        @endif
    </section>

    @if ($canViewCrm)
        <section class="form-card">
            <div class="page-head">
                <div>
                    <h2>CRM workspace</h2>
                    <p class="muted">Contacts, internal notes and activity history.</p>
                </div>
            </div>

            <div class="split section-gap">
                <div>
                    <h3 class="mini-title">Contacts</h3>
                    <form method="POST" action="{{ route('admin.companies.contacts.store', $company) }}" class="inline-form">
                        @csrf
                        <div class="field"><label>Name</label><input name="name" type="text" placeholder="Name" required></div>
                        <div class="field"><label>Role</label><input name="role" type="text" placeholder="Role"></div>
                        <div class="field"><label>Email</label><input name="email" type="email" placeholder="Email"></div>
                        <div class="field"><label>Phone</label><input name="phone" type="text" placeholder="Phone"></div>
                        <button class="btn btn-small btn-primary" type="submit">Add contact</button>
                    </form>

                    <div class="table-wrap section-gap">
                        <table>
                            <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Phone</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($company->contacts as $contact)
                                    <tr>
                                        <td class="strong">{{ $contact->name }}</td>
                                        <td>{{ $contact->role ?? '-' }}</td>
                                        <td>{{ $contact->email ?? '-' }}</td>
                                        <td>{{ $contact->phone ?? '-' }}</td>
                                        <td class="actions">
                                            <form method="POST" action="{{ route('admin.companies.contacts.destroy', [$company, $contact]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-small btn-danger" type="submit">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><div class="empty">No contacts recorded.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h3 class="mini-title">Notes</h3>
                    <form method="POST" action="{{ route('admin.companies.notes.store', $company) }}">
                        @csrf
                        <div class="field">
                            <textarea name="body" placeholder="Add an internal note" required></textarea>
                        </div>
                        <div class="form-actions"><button class="btn btn-small btn-primary" type="submit">Save note</button></div>
                    </form>

                    <div class="timeline section-gap">
                        @forelse ($company->notes->sortByDesc('created_at') as $note)
                            <article class="timeline-item">
                                <p>{{ $note->body }}</p>
                                <span class="muted">{{ $note->author?->name ?? 'System' }} · {{ $note->created_at?->format('M j, Y H:i') }}</span>
                            </article>
                        @empty
                            <div class="empty">No notes yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="section-gap">
                <h3 class="mini-title">Activities</h3>
                <form method="POST" action="{{ route('admin.companies.activities.store', $company) }}" class="inline-form">
                    @csrf
                    <div class="field"><label>Kind</label><input name="kind" type="text" placeholder="Call / visit / email" required></div>
                    <div class="field is-wide"><label>Summary</label><input name="summary" type="text" placeholder="What happened?" required></div>
                    <button class="btn btn-small btn-primary" type="submit">Log activity</button>
                </form>
                <div class="timeline section-gap">
                    @forelse ($company->activities->sortByDesc('occurred_at') as $activity)
                        <article class="timeline-item">
                            <span class="badge badge-muted">{{ $activity->kind }}</span>
                            <p>{{ $activity->summary }}</p>
                            <span class="muted">{{ $activity->actor?->name ?? 'System' }} · {{ $activity->occurred_at?->format('M j, Y H:i') }}</span>
                        </article>
                    @empty
                        <div class="empty">No activities logged.</div>
                    @endforelse
                </div>
            </div>
        </section>
    @endif

    <section class="form-card">
        <h2>Recent commercial history</h2>
        <div class="split section-gap">
            <div>
                <h3 class="mini-title">Bookings</h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Trip</th><th>Seats</th><th>Settlement</th><th>Total</th></tr></thead>
                        <tbody>
                            @forelse ($recentBookings as $booking)
                                <tr>
                                    <td>{{ $booking->trip?->route_name }}</td>
                                    <td>{{ $booking->seats_count }}</td>
                                    <td><span class="badge">{{ str_replace('_', ' ', $booking->settlement_status) }}</span></td>
                                    <td>{{ number_format((float) $booking->total_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="empty">No bookings.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div>
                <h3 class="mini-title">Payments</h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($recentPayments as $payment)
                                <tr>
                                    <td>{{ $payment->received_date?->format('M j, Y') }}</td>
                                    <td>{{ number_format((float) $payment->amount, 2) }}</td>
                                    <td><span class="badge {{ $payment->status === 'CONFIRMED' ? 'badge-success' : 'badge-warning' }}">{{ $payment->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><div class="empty">No payments.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <h3 class="mini-title section-gap">Documents</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Number</th><th>Type</th><th>Total</th><th>State</th></tr></thead>
                <tbody>
                    @forelse ($recentDocuments as $document)
                        <tr>
                            <td class="strong">{{ $document->number }}</td>
                            <td>{{ $document->typeLabel() }}</td>
                            <td>{{ number_format((float) $document->total, 2) }}</td>
                            <td><span class="badge {{ $document->state === 'APPROVED' ? 'badge-success' : ($document->state === 'VOIDED' ? 'badge-danger' : 'badge-warning') }}">{{ $document->state }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty">No documents.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

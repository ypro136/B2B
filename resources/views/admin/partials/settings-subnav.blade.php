@php
    $isSuperAdmin = auth()->user()?->isSuperAdmin();
    $canManageAdmins = auth()->user()?->hasFeature(\App\Models\AdminPermission::MANAGE_ADMINS);
@endphp
<div class="actions" style="margin-bottom: 16px;">
    @if ($isSuperAdmin)
        <a class="btn btn-small btn-ghost" href="#settings">Settings</a>
        <a class="btn btn-small btn-ghost" href="#permissions">Permissions</a>
    @endif
    @if ($canManageAdmins)
        <a class="btn btn-small btn-ghost" href="#add-admin">Add admin</a>
    @endif
</div>

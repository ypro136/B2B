<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SeatRequestController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TripController;
use App\Http\Controllers\Admin\TripTemplateController;
use App\Models\AdminPermission;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['portal:admin'])
    ->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->name('login.store');

        Route::middleware(['auth', 'portal.user:admin'])->group(function () {
            Route::post('logout', [LoginController::class, 'logout'])->name('logout');

            Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

            // Companies, quotas and CRM
            Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
            Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
            Route::put('companies/{company}', [CompanyController::class, 'update'])
                ->middleware('feature:'.AdminPermission::MANAGE_SEAT_LIMITS)
                ->name('companies.update');

            Route::post('companies/{company}/quota-reset', [CompanyController::class, 'resetQuota'])
                ->middleware('feature:'.AdminPermission::RESET_QUOTAS)
                ->name('companies.quota-reset');

            Route::middleware('feature:'.AdminPermission::MANAGE_COMPANIES)->group(function () {
                Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
                Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
                Route::post('companies/{company}/users', [CompanyUserController::class, 'store'])
                    ->name('companies.users.store');
                Route::post('companies/{company}/users/{user}/toggle', [CompanyUserController::class, 'toggle'])
                    ->name('companies.users.toggle');
            });

            Route::middleware('feature:'.AdminPermission::VIEW_CRM)->group(function () {
                Route::post('companies/{company}/contacts', [CompanyController::class, 'storeContact'])
                    ->name('companies.contacts.store');
                Route::delete('companies/{company}/contacts/{contact}', [CompanyController::class, 'destroyContact'])
                    ->name('companies.contacts.destroy');
                Route::post('companies/{company}/notes', [CompanyController::class, 'storeNote'])
                    ->name('companies.notes.store');
                Route::post('companies/{company}/activities', [CompanyController::class, 'storeActivity'])
                    ->name('companies.activities.store');
            });

            // Trips and recurrence
            Route::get('trips', [TripController::class, 'index'])->name('trips.index');
            Route::get('trips/create', [TripController::class, 'create'])->name('trips.create');
            Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');
            Route::get('trips/{trip}/edit', [TripController::class, 'edit'])->name('trips.edit');

            Route::middleware('feature:'.AdminPermission::MANAGE_TRIPS)->group(function () {
                Route::post('trips', [TripController::class, 'store'])->name('trips.store');
                Route::put('trips/{trip}', [TripController::class, 'update'])->name('trips.update');
                Route::post('trips/{trip}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');
                Route::post('trips/{trip}/complete', [TripController::class, 'complete'])->name('trips.complete');
            });

            Route::middleware('feature:'.AdminPermission::MANAGE_PRICING)->group(function () {
                Route::post('trips/{trip}/company-price', [TripController::class, 'setCompanyPrice'])
                    ->name('trips.company-price');
                Route::post('trips/{trip}/cost', [TripController::class, 'setCost'])
                    ->name('trips.cost');
            });

            Route::middleware('feature:'.AdminPermission::MANAGE_TEMPLATES)->group(function () {
                Route::get('trip-templates', [TripTemplateController::class, 'index'])->name('templates.index');
                Route::get('trip-templates/create', [TripTemplateController::class, 'create'])->name('templates.create');
                Route::post('trip-templates', [TripTemplateController::class, 'store'])->name('templates.store');
                Route::get('trip-templates/{template}/edit', [TripTemplateController::class, 'edit'])->name('templates.edit');
                Route::put('trip-templates/{template}', [TripTemplateController::class, 'update'])->name('templates.update');
                Route::post('trip-templates/{template}/generate', [TripTemplateController::class, 'generate'])
                    ->name('templates.generate');
            });

            // Seat requests
            Route::get('seat-requests', [SeatRequestController::class, 'index'])->name('seat-requests.index');
            Route::post('seat-requests/{seatRequest}/approve', [SeatRequestController::class, 'approve'])
                ->middleware('feature:'.AdminPermission::APPROVE_REQUESTS)
                ->name('seat-requests.approve');
            Route::post('seat-requests/{seatRequest}/reject', [SeatRequestController::class, 'reject'])
                ->middleware('feature:'.AdminPermission::APPROVE_REQUESTS)
                ->name('seat-requests.reject');

            // Finance: payments, allocation, refunds, credits and documents (merged Payments tab)
            Route::middleware('feature:'.AdminPermission::RECORD_PAYMENTS)->group(function () {
                Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
                Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
                Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
                Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');
            });

            Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm'])
                ->middleware('feature:'.AdminPermission::CONFIRM_PAYMENTS)
                ->name('payments.confirm');
            Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])
                ->middleware('feature:'.AdminPermission::CONFIRM_PAYMENTS)
                ->name('payments.reject');
            Route::post('payments/{payment}/allocations', [PaymentController::class, 'allocate'])
                ->middleware('feature:'.AdminPermission::ALLOCATE_PAYMENTS)
                ->name('payments.allocate');
            Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])
                ->middleware('feature:'.AdminPermission::ISSUE_REFUNDS)
                ->name('payments.refund');
            Route::post('credits', [PaymentController::class, 'issueCredit'])
                ->middleware('feature:'.AdminPermission::ISSUE_REFUNDS)
                ->name('credits.store');
            Route::post('credits/apply', [PaymentController::class, 'applyCredit'])
                ->middleware('feature:'.AdminPermission::ISSUE_REFUNDS)
                ->name('credits.apply');
            Route::post('adjustments', [PaymentController::class, 'adjustment'])
                ->middleware('feature:'.AdminPermission::ISSUE_REFUNDS)
                ->name('adjustments.store');

            // Documents (merged into the Payments tab)
            Route::middleware('feature:'.AdminPermission::PREPARE_DOCUMENTS)->group(function () {
                Route::get('payments/documents', [DocumentController::class, 'index'])->name('payments.documents.index');
                Route::get('payments/documents/monthly', [DocumentController::class, 'monthlyForm'])->name('payments.documents.monthly-form');
                Route::post('payments/documents/monthly', [DocumentController::class, 'monthly'])->name('payments.documents.monthly');
                Route::get('payments/documents/{document}', [DocumentController::class, 'show'])->name('payments.documents.show');
            });

            Route::post('payments/documents/{document}/approve', [DocumentController::class, 'approve'])
                ->middleware('feature:'.AdminPermission::APPROVE_DOCUMENTS)
                ->name('payments.documents.approve');
            Route::post('payments/documents/{document}/void', [DocumentController::class, 'void'])
                ->middleware('feature:'.AdminPermission::APPROVE_DOCUMENTS)
                ->name('payments.documents.void');

            // Legacy Documents URLs redirect into the merged Payments tab
            Route::get('documents', fn () => redirect()->route('admin.payments.documents.index', [], 301));
            Route::get('documents/monthly', fn () => redirect()->route('admin.payments.documents.monthly-form', [], 301));
            Route::get('documents/{document}', fn (string $document) => redirect()->route('admin.payments.documents.show', ['document' => $document], 301))->whereNumber('document');

            // Reports and periods
            Route::get('reports', [ReportController::class, 'index'])
                ->middleware('feature:'.AdminPermission::VIEW_REPORTS)
                ->name('reports.index');
            Route::get('reports/finance', [ReportController::class, 'finance'])
                ->middleware('feature:'.AdminPermission::VIEW_FINANCE_REPORTS)
                ->name('reports.finance');
            Route::get('reports/finance/export', [ReportController::class, 'exportFinance'])
                ->middleware('feature:'.AdminPermission::VIEW_FINANCE_REPORTS)
                ->name('reports.finance.export');

            // Periods (merged into the Finance tab)
            Route::post('reports/finance/periods/{period}/close', [PeriodController::class, 'close'])
                ->middleware('feature:'.AdminPermission::CLOSE_PERIODS)
                ->name('reports.finance.periods.close');
            Route::post('reports/finance/periods/{period}/reopen', [PeriodController::class, 'reopen'])
                ->middleware('feature:'.AdminPermission::REOPEN_PERIODS)
                ->name('reports.finance.periods.reopen');

            // Legacy Periods URL redirects into the merged Finance tab
            Route::get('periods', fn () => redirect()->to(route('admin.reports.finance').'#periods', 301));

            Route::get('audit-logs', [AuditLogController::class, 'index'])
                ->middleware('feature:'.AdminPermission::VIEW_REPORTS)
                ->name('audit-logs.index');

            // Settings (merged tab: platform settings, admin permissions and add-admin)
            Route::get('settings', [SettingsController::class, 'index'])
                ->middleware('feature:'.AdminPermission::MANAGE_ADMINS)
                ->name('settings.index');
            Route::put('settings', [SettingsController::class, 'update'])
                ->middleware('super_admin')
                ->name('settings.update');
            Route::post('settings/admins', [AdminUserController::class, 'store'])
                ->middleware('feature:'.AdminPermission::MANAGE_ADMINS)
                ->name('settings.admins.store');
            Route::post('settings/permissions', [PermissionController::class, 'store'])
                ->middleware('super_admin')
                ->name('settings.permissions.store');
            Route::delete('settings/permissions/{user}/{feature}', [PermissionController::class, 'destroy'])
                ->middleware('super_admin')
                ->name('settings.permissions.destroy');

            // Legacy Permissions URL redirects into the merged Settings tab
            Route::get('permissions', fn () => redirect()->to(route('admin.settings.index').'#permissions', 301));
        });
    });

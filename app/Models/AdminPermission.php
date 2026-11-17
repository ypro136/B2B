<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPermission extends Model
{
    use HasFactory;

    public const MANAGE_TRIPS = 'MANAGE_TRIPS';

    public const MANAGE_COMPANIES = 'MANAGE_COMPANIES';

    public const MANAGE_SEAT_LIMITS = 'MANAGE_SEAT_LIMITS';

    public const APPROVE_REQUESTS = 'APPROVE_REQUESTS';

    public const VIEW_REPORTS = 'VIEW_REPORTS';

    public const RESET_QUOTAS = 'RESET_QUOTAS';

    public const MANAGE_TEMPLATES = 'MANAGE_TEMPLATES';

    public const MANAGE_PRICING = 'MANAGE_PRICING';

    public const VIEW_CRM = 'VIEW_CRM';

    public const RECORD_PAYMENTS = 'RECORD_PAYMENTS';

    public const CONFIRM_PAYMENTS = 'CONFIRM_PAYMENTS';

    public const ALLOCATE_PAYMENTS = 'ALLOCATE_PAYMENTS';

    public const ISSUE_REFUNDS = 'ISSUE_REFUNDS';

    public const PREPARE_DOCUMENTS = 'PREPARE_DOCUMENTS';

    public const APPROVE_DOCUMENTS = 'APPROVE_DOCUMENTS';

    public const VIEW_FINANCE_REPORTS = 'VIEW_FINANCE_REPORTS';

    public const CLOSE_PERIODS = 'CLOSE_PERIODS';

    public const REOPEN_PERIODS = 'REOPEN_PERIODS';

    public const MANAGE_SETTINGS = 'MANAGE_SETTINGS';

    public const MANAGE_ADMINS = 'MANAGE_ADMINS';

    /**
     * The model does not use default timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'admin_user_id',
        'feature',
        'granted_by',
        'granted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
        ];
    }

    /**
     * The administrator that owns the permission.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * The super admin that granted the permission.
     */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * All features that can be granted to a regular admin.
     *
     * @return list<string>
     */
    public static function features(): array
    {
        return [
            self::MANAGE_TRIPS,
            self::MANAGE_COMPANIES,
            self::MANAGE_SEAT_LIMITS,
            self::APPROVE_REQUESTS,
            self::VIEW_REPORTS,
            self::RESET_QUOTAS,
            self::MANAGE_TEMPLATES,
            self::MANAGE_PRICING,
            self::VIEW_CRM,
            self::RECORD_PAYMENTS,
            self::CONFIRM_PAYMENTS,
            self::ALLOCATE_PAYMENTS,
            self::ISSUE_REFUNDS,
            self::PREPARE_DOCUMENTS,
            self::APPROVE_DOCUMENTS,
            self::VIEW_FINANCE_REPORTS,
            self::CLOSE_PERIODS,
            self::REOPEN_PERIODS,
            self::MANAGE_SETTINGS,
            self::MANAGE_ADMINS,
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    public const SUPER_ADMIN = 'SUPER_ADMIN';

    public const ADMIN = 'ADMIN';

    public const COMPANY_ADMIN = 'COMPANY_ADMIN';

    public const DISPATCHER = 'DISPATCHER';

    public const DRIVER = 'DRIVER';

    public const CLIENT = 'CLIENT';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    /**
     * Users assigned to the role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * All roles understood by the application.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::COMPANY_ADMIN,
            self::DISPATCHER,
            self::DRIVER,
            self::CLIENT,
        ];
    }
}

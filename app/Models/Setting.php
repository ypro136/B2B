<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * @var array<string, string|null>
     */
    protected static array $cache = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $value = self::query()->where('key', $key)->value('value');

        return self::$cache[$key] = $value ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, (string) $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? '1' : '0');

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function float(string $key, float $default = 0): float
    {
        return (float) self::get($key, (string) $default);
    }

    public static function set(string $key, ?string $value, string $type = 'string'): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type],
        );

        self::$cache[$key] = $value;
    }

    /**
     * @return array<string, self>
     */
    public static function allSettings(): array
    {
        return self::query()->orderBy('key')->get()->keyBy('key')->all();
    }
}

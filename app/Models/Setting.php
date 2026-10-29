<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    /** @use HasFactory   SettingFactory> */
    use HasFactory;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'key';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Cache key prefix for setting items.
     */
    private const CACHE_PREFIX = 'app_setting_';

    /**
     * Cache TTL in seconds (24 hours).
     */
    private const CACHE_TTL = 86400;

    /**
     * Retrieve a setting value by key with caching support.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = static::find($key);

            if (! $setting) {
                return $default;
            }

            // محاولة تحويل البيانات إذا كانت مخزنة كـ JSON
            $decoded = json_decode($setting->value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
        });
    }

    /**
     * Set a setting value (create or update) and automatically purge cache.
     */
    public static function set(string $key, mixed $value): void
    {
        $storedValue = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : (string) $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue]
        );

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * Check if a setting exists by key.
     */
    public static function has(string $key): bool
    {
        return static::get($key) !== null;
    }

    /**
     * Delete a setting by key and clear cache.
     */
    public static function forget(string $key): bool
    {
        Cache::forget(self::CACHE_PREFIX.$key);

        return (bool) static::where('key', $key)->delete();
    }
}

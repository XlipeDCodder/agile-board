<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value store pra configurações globais do sistema (toggles de feature,
 * flags operacionais). Pensado pra poucos pares, lido com frequência.
 * Cache "remember" evita hit no DB em cada request — busted no set().
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public const CACHE_KEY_PREFIX = 'system_setting:';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::CACHE_KEY_PREFIX.$key, function () use ($key, $default) {
            $row = self::where('key', $key)->first();
            return $row ? $row->value : $default;
        });
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? '1' : '0');
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    public static function set(string $key, mixed $value): void
    {
        $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        self::updateOrCreate(['key' => $key], ['value' => $stringValue]);
        Cache::forget(self::CACHE_KEY_PREFIX.$key);
    }
}

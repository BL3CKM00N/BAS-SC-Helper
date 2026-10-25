<?php

namespace App\Services\Admin;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Settings that an admin changes in the panel and that apply to the whole app, kept in the database so they
 * survive deploys. All of them are read through one cached array, so reading is cheap.
 */
class AppSettings
{
    private const CACHE_KEY = 'app-settings';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        try {
            return $this->loaded = Cache::rememberForever(self::CACHE_KEY, fn () => AppSetting::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            // Before the migrations ran there is no table (or cache table) yet: act as if nothing was set.
            return [];
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        AppSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function forget(string $key): void
    {
        AppSetting::query()->whereKey($key)->delete();

        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }
}

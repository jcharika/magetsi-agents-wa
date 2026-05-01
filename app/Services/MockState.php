<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MockState
{
    private const CACHE_KEY = 'magetsi_mock_enabled';

    public static function isEnabled(): bool
    {
        return Cache::get(self::CACHE_KEY, false);
    }

    public static function enable(): void
    {
        Cache::forever(self::CACHE_KEY, true);
    }

    public static function disable(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function toggle(bool $enable): void
    {
        if ($enable) {
            self::enable();
        } else {
            self::disable();
        }
    }
}
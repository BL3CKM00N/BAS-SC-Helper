<?php

namespace App\Support;

/**
 * A readable description of a browser, taken from its user agent: "Chrome on macOS", "Safari on iOS".
 */
final class DeviceInfo
{
    /**
     * @return array{browser: string, os: string, device: string, label: string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        $browser = match (true) {
            str_contains($ua, 'Edg/'), str_contains($ua, 'EdgA/'), str_contains($ua, 'EdgiOS/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/'), str_contains($ua, 'FxiOS/') => 'Firefox',
            str_contains($ua, 'SamsungBrowser/') => 'Samsung Internet',
            str_contains($ua, 'CriOS/'), str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') && str_contains($ua, 'Version/') => 'Safari',
            default => 'Unknown browser',
        };

        $os = match (true) {
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad'), str_contains($ua, 'iPod') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh'), str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'an unknown system',
        };

        $device = match (true) {
            str_contains($ua, 'iPad'), str_contains($ua, 'Tablet') => 'tablet',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPod'), str_contains($ua, 'Mobile') => 'phone',
            default => 'computer',
        };

        return ['browser' => $browser, 'os' => $os, 'device' => $device, 'label' => $browser.' on '.$os];
    }
}

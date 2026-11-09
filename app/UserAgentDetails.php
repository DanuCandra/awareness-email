<?php

namespace App;

class UserAgentDetails
{
    private const UNKNOWN = 'Tidak diketahui';

    /** @return array{browser: string, os: string, device: string} */
    public function parse(?string $userAgent): array
    {
        $agent = strtolower(substr(trim($userAgent ?? ''), 0, 1000));
        $browser = self::UNKNOWN;
        foreach ([
            'Thunderbird' => '~\bthunderbird/~',
            'Edge' => '~\b(?:edg|edga|edgios|edge)/~',
            'Samsung Internet' => '~\bsamsungbrowser/~',
            'Opera' => '~\b(?:opr|opios|opera)[/ ]~',
            'Firefox' => '~\b(?:firefox|fxios)/~',
            'Android WebView' => '~; wv\)~',
            'Chromium' => '~\bchromium/~',
            'Chrome' => '~\b(?:chrome|crios)/~',
            'Internet Explorer' => '~\b(?:msie |trident/)~',
        ] as $name => $pattern) {
            if (preg_match($pattern, $agent) === 1) {
                $browser = $name;
                break;
            }
        }
        if ($browser === self::UNKNOWN && str_contains($agent, 'safari/') && str_contains($agent, 'version/')) {
            $browser = 'Safari';
        }

        $os = match (true) {
            str_contains($agent, 'windows phone') => 'Windows Phone',
            str_contains($agent, 'ipad') => 'iPadOS',
            str_contains($agent, 'iphone'), str_contains($agent, 'ipod') => 'iOS',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'cros ') => 'ChromeOS',
            str_contains($agent, 'windows nt') => 'Windows',
            str_contains($agent, 'macintosh'), str_contains($agent, 'mac os x') => 'macOS',
            str_contains($agent, 'linux') => 'Linux',
            default => self::UNKNOWN,
        };
        $device = match (true) {
            str_contains($agent, 'windows phone'), str_contains($agent, 'iphone'), str_contains($agent, 'ipod') => 'Ponsel',
            str_contains($agent, 'ipad'), str_contains($agent, 'tablet') => 'Tablet',
            $os === 'Android' && ! str_contains($agent, 'mobile') => 'Tablet',
            str_contains($agent, 'mobile') => 'Ponsel',
            str_contains($agent, 'windows nt'), str_contains($agent, 'macintosh'), str_contains($agent, 'x11'), str_contains($agent, 'cros ') => 'Desktop',
            default => self::UNKNOWN,
        };

        return ['browser' => $browser, 'os' => $os, 'device' => $device];
    }
}

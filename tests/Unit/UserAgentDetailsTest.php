<?php

namespace Tests\Unit;

use App\UserAgentDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserAgentDetailsTest extends TestCase
{
    /** @return array<string, array{?string, array{browser: string, os: string, device: string}}> */
    public static function agents(): array
    {
        $windows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
        $android = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko)';

        return [
            'chrome on windows' => [$windows, ['browser' => 'Chrome', 'os' => 'Windows', 'device' => 'Desktop']],
            'edge before chrome' => [$windows.' Edg/130.0.0.0', ['browser' => 'Edge', 'os' => 'Windows', 'device' => 'Desktop']],
            'opera before chrome' => [$windows.' OPR/100.0', ['browser' => 'Opera', 'os' => 'Windows', 'device' => 'Desktop']],
            'internet explorer' => ['Mozilla/5.0 (Windows NT 6.1; Trident/7.0; rv:11.0) like Gecko', ['browser' => 'Internet Explorer', 'os' => 'Windows', 'device' => 'Desktop']],
            'chromium before chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chromium/130.0 Chrome/130.0 Safari/537.36', ['browser' => 'Chromium', 'os' => 'Linux', 'device' => 'Desktop']],
            'firefox on linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:132.0) Gecko/20100101 Firefox/132.0', ['browser' => 'Firefox', 'os' => 'Linux', 'device' => 'Desktop']],
            'safari on mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15', ['browser' => 'Safari', 'os' => 'macOS', 'device' => 'Desktop']],
            'android phone before linux' => [$android, ['browser' => 'Chrome', 'os' => 'Android', 'device' => 'Ponsel']],
            'edge on android' => [$android.' EdgA/130.0', ['browser' => 'Edge', 'os' => 'Android', 'device' => 'Ponsel']],
            'samsung before chrome' => [$android.' SamsungBrowser/27.0', ['browser' => 'Samsung Internet', 'os' => 'Android', 'device' => 'Ponsel']],
            'android tablet' => [str_replace(' Mobile', '', $android), ['browser' => 'Chrome', 'os' => 'Android', 'device' => 'Tablet']],
            'android webview' => [str_replace(' K)', ' K; wv)', $android), ['browser' => 'Android WebView', 'os' => 'Android', 'device' => 'Ponsel']],
            'safari iphone before mac' => [$iphone.' Version/18.0 Mobile/15E148 Safari/604.1', ['browser' => 'Safari', 'os' => 'iOS', 'device' => 'Ponsel']],
            'chrome iphone before safari' => [$iphone.' CriOS/130.0.0.0 Mobile/15E148 Safari/604.1', ['browser' => 'Chrome', 'os' => 'iOS', 'device' => 'Ponsel']],
            'firefox iphone' => [$iphone.' FxiOS/132.0 Mobile/15E148 Safari/605.1.15', ['browser' => 'Firefox', 'os' => 'iOS', 'device' => 'Ponsel']],
            'edge iphone' => [$iphone.' EdgiOS/130.0 Mobile/15E148 Safari/605.1.15', ['browser' => 'Edge', 'os' => 'iOS', 'device' => 'Ponsel']],
            'ipad before mac' => ['Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', ['browser' => 'Safari', 'os' => 'iPadOS', 'device' => 'Tablet']],
            'chrome os before linux' => ['Mozilla/5.0 (X11; CrOS x86_64 16000.0.0) AppleWebKit/537.36 Chrome/130.0 Safari/537.36', ['browser' => 'Chrome', 'os' => 'ChromeOS', 'device' => 'Desktop']],
            'thunderbird when explicitly sent' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Thunderbird/128.0', ['browser' => 'Thunderbird', 'os' => 'Windows', 'device' => 'Desktop']],
            'unknown agent stays unknown' => ['<script>untrusted</script>', ['browser' => 'Tidak diketahui', 'os' => 'Tidak diketahui', 'device' => 'Tidak diketahui']],
            'empty agent stays unknown' => ['', ['browser' => 'Tidak diketahui', 'os' => 'Tidak diketahui', 'device' => 'Tidak diketahui']],
            'null agent stays unknown' => [null, ['browser' => 'Tidak diketahui', 'os' => 'Tidak diketahui', 'device' => 'Tidak diketahui']],
        ];
    }

    /** @param array{browser: string, os: string, device: string} $expected */
    #[DataProvider('agents')]
    public function test_parses_supported_user_agents_without_inventing_missing_information(?string $agent, array $expected): void
    {
        $this->assertSame($expected, (new UserAgentDetails)->parse($agent));
    }
}

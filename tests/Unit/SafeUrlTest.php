<?php

namespace Tests\Unit;

use App\Support\SafeUrl;
use PHPUnit\Framework\TestCase;

class SafeUrlTest extends TestCase
{
    public function test_safe_urls_pass(): void
    {
        $safe = ['', null, '/', '/products/1', 'https://example.com', 'https://example.com/a?b=1#c', 'http://example.com/x'];

        foreach ($safe as $url) {
            $this->assertTrue(SafeUrl::passes($url), var_export($url, true));
        }
    }

    public function test_dangerous_urls_are_rejected(): void
    {
        $bad = [
            'javascript:alert(1)',
            'JavaScript:alert(1)',
            ' javascript:alert(1)',
            "java\tscript:alert(1)",
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            '//evil.com',
            '/\\evil.com',
            'ftp://example.com',
            'mailto:a@b.com',
            'example.com/no-scheme',
            "https://example.com/\nx",
        ];

        foreach ($bad as $url) {
            $this->assertFalse(SafeUrl::passes($url), $url);
        }
    }

    public function test_sanitize_nulls_unsafe_and_keeps_safe(): void
    {
        $this->assertNull(SafeUrl::sanitize('javascript:alert(1)'));
        $this->assertSame('https://example.com', SafeUrl::sanitize('https://example.com'));
        $this->assertSame('/x', SafeUrl::sanitize('/x'));
    }
}

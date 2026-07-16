<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DockerNginxConfigTest extends TestCase
{
    public function test_storage_uploads_are_not_shadowed_by_the_generic_asset_regex(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/default.conf');

        $this->assertIsString($config);
        $this->assertStringContainsString('location /storage/', $config);
        $this->assertStringContainsString('alias /var/www/html/storage/app/public/;', $config);
        $this->assertStringContainsString(
            'location ~* ^/(?!storage/).*\.(jpg|jpeg|png|webp|gif|svg|ico|woff2?)$',
            $config,
        );
        $this->assertStringContainsString(
            'location ~* ^/storage/.*\.(php[0-9]?|pht|phtml|phps|phar|pl|py|cgi|sh|shtml)$',
            $config,
        );
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DockerfileTest extends TestCase
{
    public function test_vendor_stage_installs_an_archive_extractor_before_composer_install(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 2).'/Dockerfile');

        $this->assertIsString($dockerfile);

        $vendorStart = strpos($dockerfile, 'FROM php:8.5-cli AS vendor');
        $runtimeStart = strpos($dockerfile, 'FROM php:8.5-fpm AS runtime');

        $this->assertNotFalse($vendorStart);
        $this->assertNotFalse($runtimeStart);

        $vendorStage = substr($dockerfile, $vendorStart, $runtimeStart - $vendorStart);
        $unzipInstall = strpos($vendorStage, 'apt-get install -y --no-install-recommends unzip');
        $composerInstall = strpos($vendorStage, 'RUN composer install');

        $this->assertNotFalse($unzipInstall);
        $this->assertNotFalse($composerInstall);
        $this->assertLessThan($composerInstall, $unzipInstall);
    }

    public function test_php_85_runtime_uses_the_builtin_opcache_module(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 2).'/Dockerfile');

        $this->assertIsString($dockerfile);
        $this->assertStringContainsString(
            'docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath',
            $dockerfile,
        );
        $this->assertStringNotContainsString('bcmath opcache', $dockerfile);
        $this->assertStringContainsString('php -m | grep -qi opcache', $dockerfile);
    }
}

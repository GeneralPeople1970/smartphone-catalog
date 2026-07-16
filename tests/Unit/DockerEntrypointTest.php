<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DockerEntrypointTest extends TestCase
{
    public function test_one_off_commands_do_not_emit_php_fpm_startup_output(): void
    {
        $entrypoint = dirname(__DIR__, 2).'/docker/entrypoint.sh';
        $command = 'sh '.escapeshellarg($entrypoint).' printf '.escapeshellarg("base64:test-key\n");
        $output = [];

        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertSame(['base64:test-key'], $output);
    }
}

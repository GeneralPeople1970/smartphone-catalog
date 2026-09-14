<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DockerDeployComposeTest extends TestCase
{
    public function test_image_only_compose_requires_no_local_build(): void
    {
        $compose = file_get_contents(dirname(__DIR__, 2).'/compose.deploy.yml');

        $this->assertIsString($compose);
        $this->assertStringNotContainsString('build:', $compose);
        $this->assertStringContainsString(
            'image: ${DOCKER_APP_IMAGE:-generalpeople/smartphone-catalog:runtime}',
            $compose,
        );
        $this->assertStringContainsString(
            'image: ${DOCKER_WEB_IMAGE:-generalpeople/smartphone-catalog:web}',
            $compose,
        );
    }

    public function test_migration_must_complete_before_the_app_starts(): void
    {
        $compose = file_get_contents(dirname(__DIR__, 2).'/compose.deploy.yml');

        $this->assertIsString($compose);
        $this->assertStringContainsString('entrypoint: ["/usr/local/bin/release.sh"]', $compose);
        $this->assertStringContainsString('condition: service_completed_successfully', $compose);
        $this->assertStringNotContainsString('profiles:', $compose);
    }

    public function test_deployment_keeps_isolation_healthchecks_and_persistent_data(): void
    {
        $compose = file_get_contents(dirname(__DIR__, 2).'/compose.deploy.yml');

        $this->assertIsString($compose);
        $this->assertStringContainsString('http://127.0.0.1/up', $compose);
        $this->assertStringNotContainsString('http://localhost/up', $compose);
        $this->assertStringContainsString('db-data:/var/lib/mysql', $compose);
        $this->assertStringContainsString('uploads:/var/www/html/storage/app/public', $compose);
        $this->assertStringNotContainsString('network_mode: host', $compose);
        $this->assertStringNotContainsString('container_name:', $compose);

        $dockerignore = file_get_contents(dirname(__DIR__, 2).'/.dockerignore');

        $this->assertIsString($dockerignore);
        $this->assertStringContainsString('compose*.yml', $dockerignore);

        $developmentCompose = file_get_contents(dirname(__DIR__, 2).'/compose.yml');

        $this->assertIsString($developmentCompose);
        $this->assertStringContainsString('http://127.0.0.1/up', $developmentCompose);
        $this->assertStringNotContainsString('http://localhost/up', $developmentCompose);
    }

    public function test_publish_workflow_uses_repository_secrets_and_both_image_targets(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/publish-images.yml');

        $this->assertIsString($workflow);
        $this->assertStringContainsString('DOCKERHUB_TOKEN: ${{ secrets.DOCKERHUB_TOKEN }}', $workflow);
        $this->assertStringContainsString('target: runtime', $workflow);
        $this->assertStringContainsString('target: web', $workflow);
        $this->assertStringContainsString('platforms: linux/amd64,linux/arm64', $workflow);
        $this->assertMatchesRegularExpression('/push:\s+branches:\s+- main/s', $workflow);
    }

    #[DataProvider('deploymentGuides')]
    public function test_readme_links_to_a_complete_docker_deployment_guide(string $readmePath, string $guidePath): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root.'/'.$readmePath);
        $this->assertFileExists($root.'/'.$guidePath);

        $readme = file_get_contents($root.'/'.$readmePath);
        $guide = file_get_contents($root.'/'.$guidePath);
        $this->assertIsString($readme);
        $this->assertIsString($guide);
        $this->assertMatchesRegularExpression('/\]\('.preg_quote($guidePath, '/').'(?:#[^)]*)?\)/', $readme);

        foreach ([
            'git clone https://github.com/GeneralPeople1970/smartphone-catalog.git',
            'cp .env.docker.example .env',
            'docker compose -f compose.deploy.yml run --rm --no-deps app php artisan key:generate --show',
            'docker compose -f compose.deploy.yml up -d --pull always --wait',
            'docker compose -f compose.deploy.yml exec app php artisan user:promote',
            'http://127.0.0.1:8080/up',
        ] as $step) {
            $this->assertTrue(str_contains($guide, $step), $guidePath.' is missing deployment step: '.$step);
        }

        foreach (['APP_KEY', 'APP_URL', 'DB_PASSWORD', 'DB_ROOT_PASSWORD', 'SESSION_SECURE_COOKIE'] as $setting) {
            $this->assertTrue(str_contains($guide, '`'.$setting.'`'), $guidePath.' is missing configuration: '.$setting);
        }
    }

    public static function deploymentGuides(): array
    {
        return [
            'Chinese' => ['README.md', 'docs/DEPLOYMENT.md'],
            'English' => ['README.en.md', 'docs/DEPLOYMENT.en.md'],
        ];
    }
}

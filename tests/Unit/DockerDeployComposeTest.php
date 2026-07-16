<?php

namespace Tests\Unit;

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

    public function test_readme_documents_a_complete_recommended_docker_deployment(): void
    {
        $readme = file_get_contents(dirname(__DIR__, 2).'/README.md');

        $this->assertIsString($readme);
        $this->assertStringContainsString(
            'git clone https://github.com/GeneralPeople1970/smartphone-catalog.git',
            $readme,
        );
        $this->assertStringContainsString('APP_KEY="$(docker run --rm --entrypoint php', $readme);
        $this->assertStringContainsString('DB_ROOT_PASSWORD="$(docker run --rm --entrypoint php', $readme);
        $this->assertStringContainsString(
            'docker compose -f compose.deploy.yml up -d --pull always --wait',
            $readme,
        );
        $this->assertStringContainsString('curl --fail --show-error', $readme);

        $dockerPosition = strpos($readme, '### Docker 部署（推荐）');
        $manualPosition = strpos($readme, '### 手动部署');

        $this->assertIsInt($dockerPosition);
        $this->assertIsInt($manualPosition);
        $this->assertTrue($dockerPosition < $manualPosition);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceWorkerTest extends TestCase
{
    public function test_service_worker_is_versioned_and_not_cacheable(): void
    {
        $response = $this->get('/sw.js')->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString('__VERSION__', $body);
        $this->assertMatchesRegularExpression("/const VERSION = '[0-9a-f]{10}';/", $body);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('Service-Worker-Allowed', '/');
    }

    public function test_dev_flag_follows_the_environment(): void
    {
        $this->app['env'] = 'local';
        $this->assertStringContainsString('const DEV = true;', $this->get('/sw.js')->getContent());

        $this->app['env'] = 'production';
        $this->assertStringContainsString('const DEV = false;', $this->get('/sw.js')->getContent());
    }
}

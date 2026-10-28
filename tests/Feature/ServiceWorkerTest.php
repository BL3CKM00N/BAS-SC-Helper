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

    public function test_navigations_retry_once_and_do_not_wait_long_for_the_network(): void
    {
        $body = $this->get('/sw.js')->getContent();

        $this->assertStringContainsString('const NAVIGATION_TIMEOUT = 4000;', $body);
        $this->assertStringNotContainsString('navigator.onLine', $body);
        $this->assertStringContainsString('fetchWithRetry', $body);
    }

    public function test_the_start_url_is_answered_with_the_launch_screen(): void
    {
        $body = $this->get('/sw.js')->getContent();

        $this->assertStringContainsString("pathname === '/' && !searchParams.has('go')", $body);
        $this->assertStringContainsString("location.replace('/?go=1')", file_get_contents(public_path('offline.html')));
    }
}

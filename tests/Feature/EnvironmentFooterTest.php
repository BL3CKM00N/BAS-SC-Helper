<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentFooterTest extends TestCase
{
    public function test_local_shows_the_dev_footer_title_and_logo_label(): void
    {
        $this->app['env'] = 'local';

        $this->get('/')
            ->assertOk()
            ->assertSee('Development environment')
            ->assertSee('[DEV]</title>', false)
            ->assertSee('/logo-dev.svg');

        $this->get('/logo-dev.svg')
            ->assertOk()
            ->assertSee('>DEV</text>', false)
            ->assertSee('fill: #fff;', false)
            ->assertSee('fill: #b8bab8;', false);
    }

    public function test_production_has_no_dev_markers(): void
    {
        $this->app['env'] = 'production';

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Development environment')
            ->assertDontSee('[DEV]', false)
            ->assertDontSee('logo-dev.svg')
            ->assertSee('/logo.svg?v=', false);

        $this->get('/logo-dev.svg')->assertNotFound();
    }
}

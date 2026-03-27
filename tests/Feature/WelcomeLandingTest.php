<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomeLandingTest extends TestCase
{
    public function test_landing_page_loads_with_marketing_copy(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Gestão de combustível e frota', false);
        $response->assertSee('Diário de bordo unificado', false);
    }
}

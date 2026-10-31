<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_urls_use_https_when_the_proxy_forwards_https(): void
    {
        $response = $this->get('http://kilomo.com.br/', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'kilomo.com.br',
            'X-Forwarded-Port' => '443',
        ]);

        $response->assertOk();
        $response->assertSee('https://kilomo.com.br/build/', false);
        $response->assertDontSee('http://kilomo.com.br/build/', false);
    }

    public function test_urls_stay_http_without_forwarded_headers(): void
    {
        $response = $this->get('http://kilomo.com.br/');

        $response->assertOk();
        $response->assertDontSee('https://kilomo.com.br/build/', false);
    }
}

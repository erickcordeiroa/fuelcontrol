<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomeLandingTest extends TestCase
{
    public function test_landing_page_loads_with_marketing_copy(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Gestão de frota, combustível e rotas de entrega', false);
        $response->assertSee('Sua frota e suas entregas em um só lugar.', false);
        $response->assertSeeInOrder(['Tudo o que a operação precisa', 'Como funciona um dia de entregas', 'Planos', 'Até 2 veículos', 'R$ 79,00', 'Até 10 veículos', 'R$ 249,00', 'Mais de 10 veículos', 'Sob consulta'], false);
        $response->assertSee('Valores de exemplo, sujeitos a alteração.', false);
        $response->assertSeeInOrder(['Em breve', 'App do motorista'], false);
    }
}

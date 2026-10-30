<?php

/**
 * Planos por quantidade de veículos (spec 007).
 *
 * VALORES DE EXEMPLO: preços e cotas ainda não foram definidos pelo dono da plataforma.
 * Limite null = sem limite. Preço null = sob consulta.
 * public = aparece na home e pode ser escolhido pela própria empresa em Assinatura.
 */
return [
    'default' => 'inicial',

    'plans' => [
        'inicial' => [
            'name' => 'Até 2 veículos',
            'description' => 'Para começar a organizar a operação.',
            'public' => true,
            'price_cents' => 7900,
            'limits' => [
                'vehicles' => 2,
                'drivers' => 2,
                'routes_per_month' => 60,
                'geocoding_per_month' => 300,
            ],
            'features' => [
                'deliveries' => true,
                'report_export' => false,
            ],
        ],

        'profissional' => [
            'name' => 'Até 10 veículos',
            'description' => 'Para a frota que entrega todos os dias.',
            'public' => true,
            'price_cents' => 24900,
            'limits' => [
                'vehicles' => 10,
                'drivers' => 10,
                'routes_per_month' => null,
                'geocoding_per_month' => 2000,
            ],
            'features' => [
                'deliveries' => true,
                'report_export' => true,
            ],
        ],

        'empresa' => [
            'name' => 'Mais de 10 veículos',
            'description' => 'Para operações maiores, com limites combinados.',
            'public' => true,
            'price_cents' => null,
            'limits' => [
                'vehicles' => null,
                'drivers' => null,
                'routes_per_month' => null,
                'geocoding_per_month' => 10000,
            ],
            'features' => [
                'deliveries' => true,
                'report_export' => true,
            ],
        ],

        /** Empresas parceiras que testam o sistema. Não aparece na home nem pode ser escolhido pela tela. */
        'parceiros' => [
            'name' => 'Parceiros',
            'description' => 'Plano de teste para empresas parceiras.',
            'public' => false,
            'price_cents' => 0,
            'limits' => [
                'vehicles' => 20,
                'drivers' => 20,
                'routes_per_month' => null,
                'geocoding_per_month' => 5000,
            ],
            'features' => [
                'deliveries' => true,
                'report_export' => true,
            ],
        ],
    ],
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google_maps' => [
        /**
         * Cada empresa cadastra a própria chave em Integrações. Esta só é usada fora de produção,
         * quando a empresa ainda não tem chave (desenvolvimento local).
         */
        'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),

        /** IP público do servidor, exibido em Integrações para o cliente restringir a chave no Google Cloud. */
        'server_ip' => env('GOOGLE_MAPS_SERVER_IP'),
        'geocoding_per_minute' => (int) env('GOOGLE_GEOCODING_PER_MINUTE', 300),

        /**
         * Tabela do Geocoding (SKU Essentials), conferida em 29/10/2026 em
         * https://developers.google.com/maps/billing-and-pricing/pricing
         * Cada faixa: [limite superior de consultas no mês (null = sem limite), US$ a cada mil].
         */
        'geocoding_free_per_month' => (int) env('GOOGLE_GEOCODING_FREE_PER_MONTH', 10000),
        'geocoding_price_tiers' => [
            [100_000, 5.00],
            [500_000, 4.00],
            [1_000_000, 3.00],
            [5_000_000, 1.50],
            [null, 0.38],
        ],
    ],

    'map_tiles' => [
        /** Imagens do mapa (Leaflet). O servidor público do OpenStreetMap não é para alto volume: troque por um provedor em produção. */
        'url' => env('MAP_TILES_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_TILES_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'),
        'max_zoom' => (int) env('MAP_TILES_MAX_ZOOM', 19),
    ],

    'routing' => [
        /** Ponto de partida padrão das rotas (centro de distribuição). */
        'depot_latitude' => env('ROUTING_DEPOT_LATITUDE'),
        'depot_longitude' => env('ROUTING_DEPOT_LONGITUDE'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

<?php

namespace App\Services\Geocoding;

use App\Models\Customer;

interface Geocoder
{
    /**
     * Busca a coordenada de um endereço em texto. Retorna null quando o provedor não encontra nada.
     * O cliente, quando informado, é usado para registrar o consumo da consulta.
     *
     * @throws GeocodingException quando o provedor falha ou não está configurado
     */
    public function geocode(string $address, ?Customer $customer = null): ?GeocodeResult;

    /**
     * Renova a coordenada a partir de um identificador guardado (place_id).
     *
     * @throws GeocodingException quando o provedor falha ou não está configurado
     */
    public function geocodePlaceId(string $placeId, ?Customer $customer = null): ?GeocodeResult;
}

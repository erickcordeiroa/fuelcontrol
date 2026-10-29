<?php

namespace App\Services\Geocoding;

use RuntimeException;

class GeocodingException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(__('Chave do Google não cadastrada. Cadastre em Integrações.'));
    }
}

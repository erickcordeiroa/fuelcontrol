<?php

namespace App\Services\Deliveries;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Ação do motorista recusada pelas regras da rota (spec 004); a API responde 422 com a mensagem.
 */
class DriverRouteException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}

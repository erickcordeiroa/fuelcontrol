<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API do app do motorista (spec 004): só usuário motorista, vinculado a um cadastro de motorista, com token do app.
 */
class EnsureUserIsDriver
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== UserRole::Driver || ! $user->tokenCan('driver') || $user->driver === null) {
            abort(Response::HTTP_FORBIDDEN, __('Acesso exclusivo do motorista.'));
        }

        return $next($request);
    }
}

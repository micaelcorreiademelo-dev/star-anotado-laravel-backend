<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Para requisições de API, não redirecionar
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Para requisições web, redirecionar para login
        return route('login');
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        // Para requisições de API, retornar resposta JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            abort(response()->json([
                'message' => 'Não autenticado.',
                'error' => 'Unauthenticated'
            ], 401));
        }

        // Para requisições web, usar comportamento padrão
        parent::unauthenticated($request, $guards);
    }
}
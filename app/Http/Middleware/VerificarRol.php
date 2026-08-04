<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarRol
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json(['mensaje' => 'No autenticado.'], 401);
        }

        if (!in_array($request->user()->rol, $roles)) {
            return response()->json(['mensaje' => 'Acceso denegado. Permisos insuficientes.'], 403);
        }

        return $next($request);
    }
}

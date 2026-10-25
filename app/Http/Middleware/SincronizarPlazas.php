<?php

namespace App\Http\Middleware;

use App\Services\SincronizadorPlazas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * S14 - Después de cada operación que escribe (POST/PUT/DELETE), enlaza a su
 * plaza lo que se creó o reasignó y, con el modelo activo, hace el relevo.
 * Corre al terminar la respuesta para no frenar ni romper el flujo actual: si
 * falla, solo queda en el log y el cron nocturno lo vuelve a intentar.
 */
class SincronizarPlazas
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethodSafe() || $response->getStatusCode() >= 400) {
            return;
        }

        try {
            $sincronizador = app(SincronizadorPlazas::class);
            if ($sincronizador->disponible()) {
                $sincronizador->sincronizar();
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo sincronizar el modelo de plazas: ' . $e->getMessage());
        }
    }
}

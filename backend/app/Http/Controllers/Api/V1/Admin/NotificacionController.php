<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\NotificacionLeida;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\NotificacionResource;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Centro de notificaciones administrativas.
 *
 * Expone el listado (con filtros), el contador de no leidas y las acciones
 * de marcar como leida una o todas.
 */
class NotificacionController extends Controller
{
    public function __construct(private readonly NotificacionService $notificaciones)
    {
    }

    /**
     * Listado paginado de notificaciones.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Notificacion::query()
                ->with(['pedido', 'cliente', 'pago'])
                ->orderByDesc('created_at');

            if ($request->boolean('solo_no_leidas')) {
                $query->where('leida', false);
            }

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->string('tipo'));
            }

            $notificaciones = $query->paginate((int) $request->integer('por_pagina', 15));

            return response()->json([
                'success' => true,
                'message' => 'Listado de notificaciones.',
                'data' => NotificacionResource::collection($notificaciones),
                'meta' => [
                    'total' => (int) Notificacion::query()->count(),
                    'no_leidas' => $this->notificaciones->noLeidas(),
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error al listar notificaciones', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las notificaciones.',
                'errors' => null,
            ], 500);
        }
    }

    /**
     * Contador de notificaciones no leidas.
     */
    public function contador(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Contador de notificaciones.',
                'data' => [
                    'total' => (int) Notificacion::query()->count(),
                    'no_leidas' => $this->notificaciones->noLeidas(),
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error al contar notificaciones', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al contar las notificaciones.',
                'errors' => null,
            ], 500);
        }
    }

    /**
     * Marca una notificacion como leida.
     */
    public function marcarLeida(int $id): JsonResponse
    {
        try {
            $notificacion = Notificacion::query()->with(['pedido', 'cliente', 'pago'])->findOrFail($id);

            if (! $notificacion->leida) {
                $notificacion->forceFill(['leida' => true, 'leida_en' => now()])->save();
            }

            event(new NotificacionLeida($notificacion));

            return response()->json([
                'success' => true,
                'message' => 'Notificacion marcada como leida.',
                'data' => new NotificacionResource($notificacion),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'La notificacion no existe.',
                'errors' => null,
            ], 404);
        } catch (Throwable $e) {
            Log::error('Error al marcar notificacion', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la notificacion.',
                'errors' => null,
            ], 500);
        }
    }

    /**
     * Marca todas las notificaciones como leidas.
     */
    public function marcarTodasLeidas(): JsonResponse
    {
        try {
            $marcadas = $this->notificaciones->marcarTodasLeidas();

            return response()->json([
                'success' => true,
                'message' => 'Todas las notificaciones fueron marcadas como leidas.',
                'data' => ['marcadas' => $marcadas],
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error al marcar todas las notificaciones', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar las notificaciones.',
                'errors' => null,
            ], 500);
        }
    }
}
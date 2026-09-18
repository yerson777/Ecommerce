<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ComunicacionStoreRequest;
use App\Http\Requests\Api\V1\Admin\ComunicacionStoreRequest as StoreRequest;
use App\Http\Resources\V1\ComunicacionResource;
use App\Models\Comunicacion;
use App\Services\ComunicacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Historial de comunicaciones iniciadas hacia clientes (WhatsApp/email).
 *
 * Solo registra el intento de envio: la entrega real no se asume nunca,
 * de acuerdo con la politica del proyecto (no notificar "enviadas" sin
 * garantia).
 */
class ComunicacionController extends Controller
{
    public function __construct(private readonly ComunicacionService $comunicaciones)
    {
    }

    /**
     * Listado paginado de comunicaciones con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Comunicacion::query()
                ->with(['pedido', 'cliente', 'user'])
                ->orderByDesc('created_at');

            if ($request->filled('pedido_id')) {
                $query->where('pedido_id', (int) $request->integer('pedido_id'));
            }

            if ($request->filled('cliente_id')) {
                $query->where('cliente_id', (int) $request->integer('cliente_id'));
            }

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->string('tipo'));
            }

            $comunicaciones = $query->paginate((int) $request->integer('por_pagina', 20));

            return response()->json([
                'success' => true,
                'message' => 'Listado de comunicaciones.',
                'data' => ComunicacionResource::collection($comunicaciones),
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error al listar comunicaciones', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las comunicaciones.',
                'errors' => null,
            ], 500);
        }
    }

    /**
     * Registra una comunicacion iniciada.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $datos = $request->validated();

            $comunicacion = $this->comunicaciones->registrar(
                (int) $datos['pedido_id'],
                $datos['tipo'],
                $datos['mensaje'],
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Comunicacion registrada.',
                'data' => new ComunicacionResource($comunicacion),
            ], 201);
        } catch (Throwable $e) {
            Log::error('Error al registrar comunicacion', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la comunicacion.',
                'errors' => $e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : null,
            ], $e instanceof \Illuminate\Validation\ValidationException ? 422 : 500);
        }
    }
}
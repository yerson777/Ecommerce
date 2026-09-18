<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\PlantillaMensajeStoreRequest;
use App\Http\Requests\Api\V1\Admin\PlantillaMensajeUpdateRequest;
use App\Http\Resources\V1\PlantillaMensajeResource;
use App\Models\PlantillaMensaje;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plantillas de mensaje reutilizables para WhatsApp/email.
 *
 * Una plantilla guarda un mensaje base con variables simples {{...}} que
 * el selector del panel reemplaza con datos reales del pedido antes de
 * iniciar la conversacion.
 */
class PlantillaMensajeController extends Controller
{
    /**
     * Listado paginado de plantillas (filtro opcional por solo_activas).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PlantillaMensaje::query()->orderBy('nombre');

            if ($request->boolean('solo_activas')) {
                $query->where('activo', true);
            }

            $plantillas = $request->boolean('todas')
                ? $query->get()
                : $query->paginate((int) $request->integer('por_pagina', 20));

            return response()->json([
                'success' => true,
                'message' => 'Listado de plantillas de mensajes.',
                'data' => PlantillaMensajeResource::collection($plantillas),
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error al listar plantillas', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las plantillas.',
                'errors' => null,
            ], 500);
        }
    }

    /**
     * Crea una plantilla.
     */
    public function store(PlantillaMensajeStoreRequest $request): JsonResponse
    {
        try {
            $plantilla = PlantillaMensaje::query()->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Plantilla creada.',
                'data' => new PlantillaMensajeResource($plantilla),
            ], 201);
        } catch (Throwable $e) {
            Log::error('Error al crear plantilla', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la plantilla.',
                'errors' => $e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : null,
            ], $e instanceof \Illuminate\Validation\ValidationException ? 422 : 500);
        }
    }

    /**
     * Muestra una plantilla.
     */
    public function mostrar(int $id): JsonResponse
    {
        try {
            $plantilla = PlantillaMensaje::query()->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Plantilla.',
                'data' => new PlantillaMensajeResource($plantilla),
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'La plantilla no existe.',
                'errors' => null,
            ], 404);
        } catch (Throwable $e) {
            Log::error('Error al mostrar plantilla', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la plantilla.',
                'errors' => null,
            ], 500);
        }
    }

    /**
     * Actualiza una plantilla.
     */
    public function actualizar(PlantillaMensajeUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $plantilla = PlantillaMensaje::query()->findOrFail($id);
            $plantilla->fill($request->validated())->save();

            return response()->json([
                'success' => true,
                'message' => 'Plantilla actualizada.',
                'data' => new PlantillaMensajeResource($plantilla->fresh()),
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'La plantilla no existe.',
                'errors' => null,
            ], 404);
        } catch (Throwable $e) {
            Log::error('Error al actualizar plantilla', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la plantilla.',
                'errors' => null,
            ], 500);
        }
    }

    /**
     * Elimina una plantilla.
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $plantilla = PlantillaMensaje::query()->findOrFail($id);
            $plantilla->delete();

            return response()->json([
                'success' => true,
                'message' => 'Plantilla eliminada.',
                'data' => null,
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'La plantilla no existe.',
                'errors' => null,
            ], 404);
        } catch (Throwable $e) {
            Log::error('Error al eliminar plantilla', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la plantilla.',
                'errors' => null,
            ], 500);
        }
    }
}
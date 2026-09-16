<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Respuestas JSON consistentes para la API de Everly.
 *
 * Formato de éxito:  { success, message, data }
 * Formato de error:  { success, message, errors }
 */
class Api
{
    public static function success(mixed $data = null, string $message = 'Operación exitosa.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function created(mixed $data = null, string $message = 'Recurso creado.', int $status = 201): JsonResponse
    {
        return self::success($data, $message, $status);
    }

    public static function noContent(string $message = 'Recurso eliminado.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => null,
        ], 200);
    }

    public static function error(string $message = 'Error en la solicitud.', int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    public static function resource(
        JsonResource|AnonymousResourceCollection $resource,
        string $message = 'Operación exitosa.',
        int $status = 200
    ): JsonResponse {
        $resource->additional(['success' => true, 'message' => $message]);

        return $resource
            ->response()
            ->setStatusCode($status);
    }

    /**
     * Respuesta de colección con estructura estable:
     * data => items (siempre array), meta => paginación cuando aplica.
     *
     * @param  AnonymousResourceCollection  $collection  ResourceCollection (paginado o simple)
     */
    public static function collection(
        AnonymousResourceCollection $collection,
        string $message = 'Operación exitosa.',
        int $status = 200
    ): JsonResponse {
        $source = $collection->resource;

        if ($source instanceof LengthAwarePaginator) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $collection->resolve(),
                'meta' => [
                    'current_page' => $source->currentPage(),
                    'last_page' => $source->lastPage(),
                    'per_page' => $source->perPage(),
                    'total' => $source->total(),
                ],
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $collection->resolve(),
        ], $status);
    }
}
<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Almacenamiento de imágenes de catálogo (categorías y marcas).
 * Centraliza la carpeta y la url pública para reutilizar el mismo patrón
 * que los banners y las fotos de producto.
 */
class CatalogoImagenService
{
    public const TAMANO_MAXIMO_KB = 4096;

    public const MIMES = 'jpeg,jpg,png,webp,gif';

    public function url(?string $ruta): ?string
    {
        return $ruta ? $this->disco()->url($ruta) : null;
    }

    public function guardar(UploadedFile $archivo, string $carpeta): string
    {
        return $this->disco()->putFileAs($carpeta, $archivo, $this->nombreArchivo($archivo), 'public');
    }

    public function eliminar(?string $ruta): void
    {
        if ($ruta && $this->disco()->exists($ruta)) {
            $this->disco()->delete($ruta);
        }
    }

    private function disco(): Filesystem
    {
        return Storage::disk('public');
    }

    private function nombreArchivo(UploadedFile $archivo): string
    {
        $extension = strtolower($archivo->guessExtension() ?: ($archivo->getClientOriginalExtension() ?: 'jpg'));

        return Str::uuid()->toString().'.'.$extension;
    }
}

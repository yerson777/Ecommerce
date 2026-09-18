<?php

namespace App\Services;

use App\Models\Banner;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de imágenes de banner para el carrusel de la tienda.
 *
 * Los archivos se guardan en el disco "public" (storage/app/public/banners)
 * y en la base de datos solo se persiste la ruta relativa. El nombre de disco
 * está centralizado aquí para que, en el futuro, baste con apuntar a un disco
 * externo (p. ej. S3) sin modificar el resto del sistema.
 */
class BannerService
{
    protected const DISCO = 'public';

    protected function almacenamiento(): Filesystem
    {
        return Storage::disk(self::DISCO);
    }

    /**
     * Url pública de una ruta relativa almacenada en la base de datos.
     */
    public function urlDeRuta(string $ruta): string
    {
        return $this->almacenamiento()->url($ruta);
    }

    public function url(Banner $banner): string
    {
        return $this->urlDeRuta($banner->ruta);
    }

    /**
     * Crea un banner con su imagen y lo posiciona al final de la secuencia.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, UploadedFile $archivo): Banner
    {
        return DB::transaction(function () use ($datos, $archivo) {
            $ruta = $this->almacenamiento()->putFileAs(
                $this->carpeta(),
                $archivo,
                $this->nombreArchivo($archivo),
                'public'
            );

            $orden = (int) Banner::max('orden');

            return Banner::create([
                'ruta' => $ruta,
                ...$datos,
                'orden' => $datos['orden'] ?? ($orden + 1),
            ]);
        });
    }

    /**
     * Actualiza los datos de un banner. Si se envía una imagen, reemplaza
     * el archivo físico y elimina el anterior.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Banner $banner, array $datos, ?UploadedFile $archivo = null): Banner
    {
        return DB::transaction(function () use ($banner, $datos, $archivo) {
            $cambios = $datos;

            if ($archivo) {
                $nuevaRuta = $this->almacenamiento()->putFileAs(
                    $this->carpeta(),
                    $archivo,
                    $this->nombreArchivo($archivo),
                    'public'
                );

                $cambios['ruta'] = $nuevaRuta;
            }

            $rutaAnterior = $archivo ? $banner->ruta : null;
            $banner->update($cambios);

            if ($rutaAnterior) {
                $this->eliminarArchivoSiExiste($rutaAnterior);
            }

            return $banner->fresh();
        });
    }

    /**
     * Elimina un banner: primero el registro y luego el archivo físico.
     */
    public function eliminar(Banner $banner): void
    {
        DB::transaction(function () use ($banner) {
            $ruta = $banner->ruta;

            $banner->delete();
            $this->eliminarArchivoSiExiste($ruta);
        });
    }

    /**
     * Reordena los banners a partir de una secuencia de ids.
     * La secuencia recibida debe contener exactamente todos los banners.
     *
     * @param  array<int, int>  $ids
     *
     * @throws ValidationException si la secuencia no coincide con los banners.
     */
    public function reordenar(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $idsValidos = Banner::orderBy('orden')->orderBy('id')->pluck('id')->all();

            if (count($ids) !== count($idsValidos)
                || array_diff($ids, $idsValidos)
                || array_diff($idsValidos, $ids)) {
                throw ValidationException::withMessages([
                    'ordenes' => 'La secuencia debe contener exactamente todos los banners.',
                ]);
            }

            foreach ($ids as $posicion => $bannerId) {
                Banner::whereKey($bannerId)->update(['orden' => $posicion + 1]);
            }
        });
    }

    /**
     * Banners activos para la tienda, ordenados según la secuencia del admin.
     */
    public function activos(): Collection
    {
        return Banner::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    protected function carpeta(): string
    {
        return 'banners';
    }

    /**
     * Nombre de archivo único y descriptivo para el almacenamiento.
     * Usa la extensión derivada del MIME (validado por FormRequest).
     */
    protected function nombreArchivo(UploadedFile $archivo): string
    {
        $extension = strtolower($archivo->guessExtension() ?: ($archivo->getClientOriginalExtension() ?: 'jpg'));

        return Str::uuid()->toString() . '.' . $extension;
    }

    protected function eliminarArchivoSiExiste(string $ruta): void
    {
        if ($this->almacenamiento()->exists($ruta)) {
            $this->almacenamiento()->delete($ruta);
        }
    }
}
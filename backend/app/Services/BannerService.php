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
    public function crear(array $datos, UploadedFile $archivo, ?UploadedFile $archivoMovil = null): Banner
    {
        return DB::transaction(function () use ($datos, $archivo, $archivoMovil) {
            $ruta = $this->almacenamiento()->putFileAs(
                $this->carpeta(),
                $archivo,
                $this->nombreArchivo($archivo),
                'public'
            );

            $rutaMovil = $archivoMovil
                ? $this->almacenamiento()->putFileAs(
                    $this->carpeta(),
                    $archivoMovil,
                    $this->nombreArchivo($archivoMovil),
                    'public'
                )
                : null;

            $orden = (int) Banner::max('orden');

            return Banner::create([
                'ruta' => $ruta,
                ...$datos,
                'ruta_movil' => $rutaMovil,
                'orden' => $datos['orden'] ?? ($orden + 1),
            ]);
        });
    }

    /**
     * Actualiza los datos de un banner. Si se envía una imagen, reemplaza
     * el archivo físico y elimina el anterior. Con "sin_imagen_movil" se
     * quita la versión móvil.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Banner $banner, array $datos, ?UploadedFile $archivo = null, ?UploadedFile $archivoMovil = null): Banner
    {
        return DB::transaction(function () use ($banner, $datos, $archivo, $archivoMovil) {
            $quitarMovil = (bool) ($datos['sin_imagen_movil'] ?? false);
            unset($datos['sin_imagen_movil']);

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
            $rutaMovilAnterior = $banner->ruta_movil;

            if ($archivoMovil) {
                $cambios['ruta_movil'] = $this->almacenamiento()->putFileAs(
                    $this->carpeta(),
                    $archivoMovil,
                    $this->nombreArchivo($archivoMovil),
                    'public'
                );
            } elseif ($quitarMovil) {
                $cambios['ruta_movil'] = null;
            }

            $banner->update($cambios);

            if ($rutaAnterior) {
                $this->eliminarArchivoSiExiste($rutaAnterior);
            }

            if ($rutaMovilAnterior && array_key_exists('ruta_movil', $cambios) && $cambios['ruta_movil'] !== $rutaMovilAnterior) {
                $this->eliminarArchivoSiExiste($rutaMovilAnterior);
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
     * Banners activos y vigentes para la tienda, ordenados según la
     * secuencia del admin. Los rangos de fecha son opcionales: un null
     * significa sin límite de ese lado.
     */
    public function activos(): Collection
    {
        $ahora = now();

        return Banner::query()
            ->where('activo', true)
            ->where(function ($query) use ($ahora) {
                $query->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $ahora);
            })
            ->where(function ($query) use ($ahora) {
                $query->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $ahora);
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ruta que se persiste en "enlace" para cada tipo de destino: rutas
     * internas de la tienda, o la URL externa tal cual.
     *
     * @throws ValidationException si el destino no existe.
     */
    public function resolverEnlace(string $tipo, ?int $id, ?string $urlExterna = null): ?string
    {
        if ($tipo === Banner::ENLACE_NINGUNO) {
            return null;
        }

        if ($tipo === Banner::ENLACE_URL) {
            return $urlExterna;
        }

        $this->validarDestino($tipo, $id);

        return match ($tipo) {
            Banner::ENLACE_PRODUCTO => '/producto/'.$id,
            Banner::ENLACE_CATEGORIA => '/catalogo?categoria='.$id,
            Banner::ENLACE_OFERTA => '/catalogo?oferta='.$id,
            default => null,
        };
    }

    /**
     * Verifica que el destino referenciado exista para evitar enlaces rotos.
     *
     * @throws ValidationException
     */
    public function validarDestino(string $tipo, ?int $id): void
    {
        if ($tipo === Banner::ENLACE_NINGUNO || $tipo === Banner::ENLACE_URL) {
            return;
        }

        if (! $id) {
            throw ValidationException::withMessages([
                'enlace_id' => 'Seleccioná el destino del enlace.',
            ]);
        }

        $tabla = match ($tipo) {
            Banner::ENLACE_PRODUCTO => 'productos',
            Banner::ENLACE_CATEGORIA => 'categorias',
            Banner::ENLACE_OFERTA => 'ofertas',
            default => null,
        };

        if ($tabla === null) {
            throw ValidationException::withMessages([
                'enlace_tipo' => 'El tipo de enlace no es válido.',
            ]);
        }

        if (! DB::table($tabla)->where('id', $id)->exists()) {
            throw ValidationException::withMessages([
                'enlace_id' => match ($tipo) {
                    Banner::ENLACE_PRODUCTO => 'La prenda seleccionada ya no existe.',
                    Banner::ENLACE_CATEGORIA => 'La categoría seleccionada ya no existe.',
                    Banner::ENLACE_OFERTA => 'La oferta seleccionada ya no existe.',
                },
            ]);
        }
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
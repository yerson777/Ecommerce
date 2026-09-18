<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoImagen;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de imágenes de producto en almacenamiento.
 *
 * Los archivos se guardan en el disco "public" (storage/app/public/productos)
 * y en la base de datos solo se persiste la ruta relativa. El nombre de disco
 * está centralizado aquí para que, en el futuro, baste con apuntar a un disco
 * externo (p. ej. S3) sin modificar el resto del sistema.
 */
class ProductoImagenService
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

    public function url(ProductoImagen $imagen): string
    {
        return $this->urlDeRuta($imagen->ruta);
    }

    /**
     * Sube una o varias imágenes a un producto en una sola transacción.
     * La primera imagen subida queda como principal solo si el producto
     * aún no tiene una.
     *
     * @param  array<int, UploadedFile>  $archivos
     * @return Collection<int, ProductoImagen>
     */
    public function subirVarias(Producto $producto, array $archivos): Collection
    {
        return DB::transaction(function () use ($producto, $archivos) {
            $tienePrincipal = $producto->imagenes()->where('es_principal', true)->exists();
            $ordenActual = (int) $producto->imagenes()->max('orden');

            $creadas = collect();

            foreach ($archivos as $posicion => $archivo) {
                $ordenActual++;
                $ruta = $this->almacenamiento()->putFileAs(
                    $this->carpeta($producto),
                    $archivo,
                    $this->nombreArchivo($archivo),
                    'public'
                );

                $imagen = ProductoImagen::create([
                    'producto_id' => $producto->id,
                    'ruta' => $ruta,
                    'nombre_original' => $this->nombreOriginal($archivo),
                    'es_principal' => ! $tienePrincipal && $posicion === 0,
                    'orden' => $ordenActual,
                ]);

                $creadas->push($imagen);
            }

            return $creadas;
        });
    }

    /**
     * Establece una imagen como principal.
     *
     * Operación transaccional y segura: primero se desmarca cualquier imagen
     * principal del producto y luego se marca la solicitada. Así nunca pueden
     * existir dos principales para el mismo producto.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si la imagen no pertenece al producto.
     */
    public function establecerPrincipal(Producto $producto, int $imagenId): ProductoImagen
    {
        return DB::transaction(function () use ($producto, $imagenId) {
            $imagen = $this->imagenDelProducto($producto, $imagenId);

            $producto->imagenes()->update(['es_principal' => false]);
            $imagen->update(['es_principal' => true]);

            return $imagen->fresh();
        });
    }

    /**
     * Reordena las imágenes del producto a partir de una secuencia de ids.
     * La secuencia recibida debe contener exactamente las imágenes del producto
     * para evitar órdenes duplicados o huecos.
     *
     * @param  array<int, int>  $ids
     *
     * @throws ValidationException si la secuencia no coincide con las imágenes del producto.
     */
    public function reordenar(Producto $producto, array $ids): void
    {
        DB::transaction(function () use ($producto, $ids) {
            $idsValidos = $producto->imagenes()->pluck('id')->all();

            if (count($ids) !== count($idsValidos)
                || array_diff($ids, $idsValidos)
                || array_diff($idsValidos, $ids)) {
                throw ValidationException::withMessages([
                    'ordenes' => 'La secuencia debe contener exactamente las imágenes de este producto.',
                ]);
            }

            foreach ($ids as $posicion => $imagenId) {
                ProductoImagen::whereKey($imagenId)->update(['orden' => $posicion + 1]);
            }
        });
    }

    /**
     * Reemplaza el archivo físico de una imagen por otro.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si la imagen no pertenece al producto.
     */
    public function reemplazar(Producto $producto, int $imagenId, UploadedFile $archivo): ProductoImagen
    {
        return DB::transaction(function () use ($producto, $imagenId, $archivo) {
            $imagen = $this->imagenDelProducto($producto, $imagenId);

            $nuevaRuta = $this->almacenamiento()->putFileAs(
                $this->carpeta($producto),
                $archivo,
                $this->nombreArchivo($archivo),
                'public'
            );

            $rutaAnterior = $imagen->ruta;
            $imagen->update([
                'ruta' => $nuevaRuta,
                'nombre_original' => $this->nombreOriginal($archivo),
            ]);

            $this->eliminarArchivoSiExiste($rutaAnterior);

            return $imagen->fresh();
        });
    }

    /**
     * Elimina una imagen: primero el registro y luego el archivo físico.
     * Si la imagen eliminada era la principal y quedan otras, la siguiente
     * (menor orden) pasa a ser principal automáticamente.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si la imagen no pertenece al producto.
     */
    public function eliminar(Producto $producto, int $imagenId): void
    {
        DB::transaction(function () use ($producto, $imagenId) {
            $imagen = $this->imagenDelProducto($producto, $imagenId);

            $ruta = $imagen->ruta;
            $eraPrincipal = (bool) $imagen->es_principal;

            $imagen->delete();
            $this->eliminarArchivoSiExiste($ruta);

            if ($eraPrincipal) {
                $nuevaPrincipal = $producto->imagenes()->orderBy('orden')->orderBy('id')->first();
                if ($nuevaPrincipal) {
                    ProductoImagen::whereKey($nuevaPrincipal->id)->update(['es_principal' => true]);
                }
            }
        });
    }

    /**
     * Elimina los archivos físicos de todas las imágenes de un producto.
     * Se usa antes de borrar el producto (los registros se eliminan por
     * cascadeOnDelete para no dejar archivos huérfanos).
     */
    public function eliminarArchivosDeProducto(Producto $producto): void
    {
        $rutas = $producto->imagenes()->pluck('ruta');

        foreach ($rutas as $ruta) {
            $this->eliminarArchivoSiExiste($ruta);
        }
    }

    /**
     * Verifica que una imagen pertenezca al producto y la devuelve.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function imagenDelProducto(Producto $producto, int $imagenId): ProductoImagen
    {
        return ProductoImagen::query()
            ->where('producto_id', $producto->id)
            ->findOrFail($imagenId);
    }

    protected function carpeta(Producto $producto): string
    {
        return 'productos/' . $producto->id;
    }

    protected function nombreOriginal(UploadedFile $archivo): string
    {
        $nombre = $archivo->getClientOriginalName() ?: $archivo->getBasename();

        return mb_substr($nombre, 0, 230);
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
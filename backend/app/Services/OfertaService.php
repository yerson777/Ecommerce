<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Oferta;
use App\Models\Producto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Precios de oferta calculados en el servidor.
 *
 * La prenda nunca cambia su precio base: el descuento se aplica al vuelo y
 * se registra en el pedido/venta (precio_original, descuento, precio_unitario
 * cobrado). Si dos ofertas activas cubren la misma prenda, se aplica la que
 * dé mayor descuento (a favor del cliente); la regla de no acumulación con
 * cupones la resuelve PedidoService.
 */
class OfertaService
{
    /**
     * Membresía por prenda de las ofertas vigentes (ahora), usada para el
     * cálculo de precios en la tienda. Se invalida al crear/editar ofertas.
     *
     * @var array<int, Oferta[]>|null
     */
    private ?array $membresia = null;

    public function olvidar(): void
    {
        $this->membresia = null;
    }

    /**
     * Ofertas vigentes (activas según fechas y sin desactivar).
     *
     * @return Collection<int, Oferta>
     */
    public function vigentes(): Collection
    {
        return Oferta::query()
            ->where('activo', true)
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->with(['productos:id', 'categorias:id'])
            ->get();
    }

    /**
     * Precio final de una prenda con la mejor oferta vigente que la cubra.
     *
     * @return array{oferta: Oferta, precio_final: float, descuento: float, porcentaje: int}|null
     */
    public function preciosDe(Producto $producto): ?array
    {
        $ofertas = $this->membresia()[$producto->id] ?? [];

        if (empty($ofertas)) {
            return null;
        }

        $precio = (float) $producto->precio;
        $mejor = null;
        $mejorPrecio = $precio;

        foreach ($ofertas as $oferta) {
            $final = $oferta->precioFinal($precio);

            if ($final < $mejorPrecio - 0.005) {
                $mejor = $oferta;
                $mejorPrecio = $final;
            }
        }

        if (! $mejor) {
            return null;
        }

        return [
            'oferta' => $mejor,
            'precio_final' => $mejorPrecio,
            'descuento' => round($precio - $mejorPrecio, 2),
            'porcentaje' => $mejor->porcentajeDe($precio),
        ];
    }

    /**
     * @param  array<int, Producto>  $productos
     * @return array<int, array{oferta: Oferta, precio_final: float, descuento: float, porcentaje: int}>
     */
    public function preciosPara(array $productos): array
    {
        $resultado = [];

        foreach ($productos as $producto) {
            $precios = $this->preciosDe($producto);

            if ($precios !== null) {
                $resultado[$producto->id] = $precios;
            }
        }

        return $resultado;
    }

    /**
     * Membresía (prenda -> ofertas vigentes que la cubren). Se resuelve una
     * sola vez por petición y se invalida al modificar ofertas.
     *
     * @return array<int, Oferta[]>
     */
    private function membresia(): array
    {
        if ($this->membresia !== null) {
            return $this->membresia;
        }

        $mapa = [];
        $categoriasOferta = [];

        foreach ($this->vigentes() as $oferta) {
            foreach ($oferta->productos as $producto) {
                $mapa[$producto->id][] = $oferta;
            }

            foreach ($oferta->categorias as $categoria) {
                $categoriasOferta[$categoria->id][] = $oferta;
            }
        }

        if (! empty($categoriasOferta)) {
            $productos = Producto::query()
                ->whereIn('categoria_id', array_keys($categoriasOferta))
                ->select('id', 'categoria_id')
                ->get();

            foreach ($productos as $producto) {
                foreach ($categoriasOferta[$producto->categoria_id] ?? [] as $oferta) {
                    $mapa[$producto->id][] = $oferta;
                }
            }
        }

        return $this->membresia = $mapa;
    }

    /**
     * Prendas del alcance de una oferta (directas + las de sus categorías).
     *
     * @return \Illuminate\Database\Eloquent\Builder<Producto>
     */
    public function alcance(Oferta $oferta): \Illuminate\Database\Eloquent\Builder
    {
        $productosIds = $oferta->productos()->pluck('producto_id');
        $categoriasIds = $oferta->categorias()->pluck('categoria_id');

        return Producto::query()->where(function ($query) use ($productosIds, $categoriasIds) {
            $query->whereIn('id', $productosIds);

            if ($categoriasIds->isNotEmpty()) {
                $query->orWhereIn('categoria_id', $categoriasIds);
            }
        });
    }

    /**
     * Resumen del alcance para la lista administrativa.
     *
     * @return array{productos_count: int, categorias: array<int, array{id: int, nombre: string}>}
     */
    public function resumen(Oferta $oferta): array
    {
        return [
            'productos_count' => $this->alcance($oferta)->count(),
            'categorias' => $oferta->categorias
                ->map(fn (Categoria $categoria) => [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                ])
                ->all(),
        ];
    }

    /**
     * Crea una oferta con su alcance. El llamador debe haber validado
     * conflictos y disponibilidad con validarAlcance().
     *
     * @param  array{nombre: string, tipo: string, valor: float, fecha_inicio: string, fecha_fin: string, permitir_cupon?: bool, activo?: bool, productos?: int[], categorias?: int[]}  $datos
     */
    public function crear(array $datos): Oferta
    {
        return $this->transaccion(function () use ($datos) {
            $oferta = Oferta::create([
                'nombre' => trim($datos['nombre']),
                'tipo' => $datos['tipo'],
                'valor' => (float) $datos['valor'],
                'fecha_inicio' => Carbon::parse($datos['fecha_inicio']),
                'fecha_fin' => Carbon::parse($datos['fecha_fin']),
                'activo' => (bool) ($datos['activo'] ?? true),
                'permitir_cupon' => (bool) ($datos['permitir_cupon'] ?? false),
            ]);

            $oferta->productos()->sync($datos['productos'] ?? []);
            $oferta->categorias()->sync($datos['categorias'] ?? []);

            $this->olvidar();

            return $oferta->load('productos:id', 'categorias:id');
        });
    }

    /**
     * Actualiza una oferta y su alcance.
     *
     * @param  array{nombre: string, tipo: string, valor: float, fecha_inicio: string, fecha_fin: string, permitir_cupon?: bool, activo?: bool, productos?: int[], categorias?: int[]}  $datos
     */
    public function actualizar(Oferta $oferta, array $datos): Oferta
    {
        return $this->transaccion(function () use ($oferta, $datos) {
            $oferta->update([
                'nombre' => trim($datos['nombre']),
                'tipo' => $datos['tipo'],
                'valor' => (float) $datos['valor'],
                'fecha_inicio' => Carbon::parse($datos['fecha_inicio']),
                'fecha_fin' => Carbon::parse($datos['fecha_fin']),
                'activo' => (bool) ($datos['activo'] ?? $oferta->activo),
                'permitir_cupon' => (bool) ($datos['permitir_cupon'] ?? $oferta->permitir_cupon),
            ]);

            $oferta->productos()->sync($datos['productos'] ?? []);
            $oferta->categorias()->sync($datos['categorias'] ?? []);

            $this->olvidar();

            return $oferta->load('productos:id', 'categorias:id');
        });
    }

    /**
     * Valida el alcance de una oferta: que ninguna prenda quede en dos
     * ofertas con vigencias superpuestas (punto 3) y que las prendas que se
     * agregan nuevas estén disponibles (punto 9).
     *
     * @param  array{fecha_inicio: string, fecha_fin: string, productos?: int[], categorias?: int[]}  $datos
     *
     * @throws ValidationException con mensajes en "productos" sin guardar nada.
     */
    public function validarAlcance(array $datos, ?Oferta $excluir = null): void
    {
        $inicio = Carbon::parse($datos['fecha_inicio']);
        $fin = Carbon::parse($datos['fecha_fin']);

        $productosIds = array_values(array_unique(array_map('intval', $datos['productos'] ?? [])));
        $categoriasIds = array_values(array_unique(array_map('intval', $datos['categorias'] ?? [])));

        $this->validarPrendasDisponibles($productosIds, $excluir);

        $productosAlcance = $this->prendasDelAlcance($productosIds, $categoriasIds);

        if ($productosAlcance->isEmpty()) {
            return;
        }

        $conflicto = Oferta::query()
            ->where('activo', true)
            ->where('fecha_fin', '>=', now())
            ->when($excluir, fn ($query) => $query->whereKeyNot($excluir->id))
            ->with('categorias:id')
            ->get()
            ->filter(fn (Oferta $otra) => $inicio->lt($otra->fecha_fin) && $fin->gt($otra->fecha_inicio));

        $mensajes = [];

        foreach ($conflicto as $otra) {
            $idsOtra = $this->idsDelAlcance($otra);

            $repetidas = $productosAlcance
                ->filter(fn (Producto $producto) => in_array($producto->id, $idsOtra, true));

            foreach ($repetidas as $producto) {
                $mensajes[] = sprintf(
                    'La prenda %s (%s) ya está en la oferta "%s" (%s a %s).',
                    $producto->nombre,
                    $producto->codigo,
                    $otra->nombre,
                    $otra->fecha_inicio->format('d/m/Y H:i'),
                    $otra->fecha_fin->format('d/m/Y H:i'),
                );
            }
        }

        if (! empty($mensajes)) {
            $mensajes[] = 'No se guardó: una prenda no puede estar en dos ofertas a la vez.';

            throw ValidationException::withMessages(['productos' => array_values(array_unique($mensajes))]);
        }
    }

    /**
     * Solo se pueden agregar a una oferta prendas que estén disponibles;
     * las que ya estaban en la oferta no se revisan (pueden haberse
     * vendido mientras tanto sin romper nada).
     *
     * @param  int[]  $productosIds
     */
    private function validarPrendasDisponibles(array $productosIds, ?Oferta $excluir = null): void
    {
        if (empty($productosIds)) {
            return;
        }

        $yaEnOferta = $excluir
            ? $excluir->productos()->pluck('producto_id')->map(fn ($id) => (int) $id)->all()
            : [];

        $nuevas = array_values(array_diff($productosIds, $yaEnOferta));

        if (empty($nuevas)) {
            return;
        }

        $ocupadas = Producto::query()
            ->whereIn('id', $nuevas)
            ->where('estado', '!=', Producto::ESTADO_DISPONIBLE)
            ->get(['id', 'codigo', 'nombre', 'estado']);

        if ($ocupadas->isEmpty()) {
            return;
        }

        $etiquetas = [
            Producto::ESTADO_RESERVADA => 'reservada',
            Producto::ESTADO_VENDIDA => 'vendida',
        ];

        $mensajes = $ocupadas->map(fn (Producto $producto) => sprintf(
            'La prenda %s (%s) está %s; solo se agregan prendas disponibles.',
            $producto->nombre,
            $producto->codigo,
            $etiquetas[$producto->estado] ?? $producto->estado,
        ));

        throw ValidationException::withMessages(['productos' => $mensajes->all()]);
    }

    /**
     * @param  int[]  $productosIds
     * @param  int[]  $categoriasIds
     * @return Collection<int, Producto>
     */
    private function prendasDelAlcance(array $productosIds, array $categoriasIds): Collection
    {
        return Producto::query()
            ->where(function ($query) use ($productosIds, $categoriasIds) {
                $query->whereIn('id', $productosIds);

                if (! empty($categoriasIds)) {
                    $query->orWhereIn('categoria_id', $categoriasIds);
                }
            })
            ->get(['id', 'codigo', 'nombre', 'categoria_id']);
    }

    /**
     * @return int[]
     */
    private function idsDelAlcance(Oferta $oferta): array
    {
        $directos = $oferta->productos()->pluck('producto_id')->map(fn ($id) => (int) $id)->all();
        $categoriasIds = $oferta->categorias()->pluck('categoria_id');

        if ($categoriasIds->isEmpty()) {
            return $directos;
        }

        $porCategoria = Producto::query()
            ->whereIn('categoria_id', $categoriasIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($directos, $porCategoria)));
    }

    private function transaccion(callable $callback): mixed
    {
        return \Illuminate\Support\Facades\DB::transaction($callback);
    }
}

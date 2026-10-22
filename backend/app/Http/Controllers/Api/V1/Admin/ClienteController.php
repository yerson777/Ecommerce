<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Cliente\ClienteStoreRequest;
use App\Http\Requests\V1\Admin\Cliente\ClienteUpdateRequest;
use App\Http\Resources\V1\ClienteResource;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\Venta;
use App\Support\Api;
use App\Support\Contacto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $clientes = $this->consultaClientes($request)
            ->paginate($request->integer('per_page', 15));

        return Api::collection(ClienteResource::collection($clientes), 'Listado de clientes.');
    }

    /**
     * Consulta base del listado (reutilizada por el export a Excel/CSV).
     *
     * - Oculta los clientes marcados como "Prueba" (punto 6).
     * - Excluye pedidos cancelados y devueltos del total comprado (punto 2).
     * - Marca los clientes con teléfono/correo repetido (punto 1).
     */
    private function consultaClientes(Request $request): Builder
    {
        return Cliente::query()
            ->selectRaw('clientes.*')
            ->withCount('pedidos')
            ->withSum(['pedidos as total_comprado' => fn ($q) => $q->whereNotIn('estado', [
                Pedido::ESTADO_CANCELADO,
                Pedido::ESTADO_DEVUELTO,
            ])], 'total')
            ->leftJoinSub(
                Pedido::query()
                    ->selectRaw('MAX(id) as pedido_id, cliente_id')
                    ->groupBy('cliente_id'),
                'ultimo_pedidos',
                'ultimo_pedidos.cliente_id',
                '=',
                'clientes.id'
            )
            ->leftJoin('pedidos as ultimo_pedido', 'ultimo_pedido.id', '=', 'ultimo_pedidos.pedido_id')
            ->selectRaw(
                'ultimo_pedido.numero_pedido as ultimo_numero, '
                .'ultimo_pedido.estado as ultimo_estado, '
                .'ultimo_pedido.total as ultimo_total, '
                .'ultimo_pedido.fecha_pedido as ultimo_fecha'
            )
            ->selectRaw(
                '(EXISTS (SELECT 1 FROM clientes c2 WHERE c2.id <> clientes.id AND c2.es_prueba = 0 AND ('
                ."(clientes.telefono_normalizado IS NOT NULL AND clientes.telefono_normalizado <> '' "
                .'AND c2.telefono_normalizado = clientes.telefono_normalizado) '
                .'OR '
                ."(clientes.email IS NOT NULL AND clientes.email <> '' "
                .'AND LOWER(c2.email) = LOWER(clientes.email))'
                .'))) as es_duplicado'
            )
            ->where('clientes.es_prueba', false)
            ->when($request->filled('busqueda'), function ($query) use ($request) {
                $busqueda = $request->input('busqueda');
                $query->where(function ($w) use ($busqueda) {
                    $w->where('clientes.nombre', 'like', "%{$busqueda}%")
                        ->orWhere('clientes.email', 'like', "%{$busqueda}%")
                        ->orWhere('clientes.telefono', 'like', "%{$busqueda}%")
                        ->orWhereHas('pedidos', fn ($p) => $p->where('numero_pedido', 'like', "%{$busqueda}%"));
                });
            })
            ->when($request->filled('filtro'), function ($query) use ($request) {
                $treintaDias = now()->subDays(30)->toDateString();
                match ($request->input('filtro')) {
                    'nuevos' => $query->where(function ($w) use ($treintaDias) {
                        $w->where('clientes.created_at', '>=', $treintaDias)
                            ->orWhere('clientes.fecha_primer_pedido', '>=', $treintaDias);
                    }),
                    'recurrentes' => $query->whereHas('pedidos', fn () => true, '>=', 2),
                    'pendientes' => $query->whereHas('pedidos', fn ($p) => $p->whereIn('estado', [
                        Pedido::ESTADO_PENDIENTE,
                        Pedido::ESTADO_CONFIRMADO,
                    ])),
                    'bloqueados' => $query->where('clientes.bloqueado', true),
                    default => null,
                };
            })
            ->orderByRaw('clientes.fecha_ultimo_pedido IS NULL, clientes.fecha_ultimo_pedido DESC, clientes.id DESC');
    }

    public function opciones()
    {
        $opciones = Cliente::query()
            ->select('id', 'nombre', 'telefono')
            ->orderBy('nombre')
            ->get();

        return Api::success($opciones, 'Opciones de clientes.');
    }

    public function store(ClienteStoreRequest $request)
    {
        $datos = $request->validated();

        if (! ($datos['confirmar_duplicado'] ?? false)) {
            $coincidencias = $this->buscarCoincidencias(
                $datos['telefono'] ?? null,
                $datos['email'] ?? null
            );

            if ($coincidencias->isNotEmpty()) {
                return $this->respuestaDuplicado($coincidencias);
            }
        }

        $cliente = Cliente::create($datos);

        return Api::resource(new ClienteResource($cliente), 'Cliente creado correctamente.', 201);
    }

    public function show(int $id)
    {
        $cliente = Cliente::query()
            ->withCount([
                'pedidos',
                'ventas',
                'pedidos as compras_count' => fn ($q) => $q->whereNotIn('estado', [
                    Pedido::ESTADO_CANCELADO,
                    Pedido::ESTADO_DEVUELTO,
                ]),
            ])
            ->withSum(['pedidos as total_comprado' => fn ($q) => $q->whereNotIn('estado', [
                Pedido::ESTADO_CANCELADO,
                Pedido::ESTADO_DEVUELTO,
            ])], 'total')
            ->with([
                'pedidos' => fn ($q) => $q
                    ->with(['items.producto.talla', 'items.producto.imagenes', 'pagos', 'venta', 'devolucion'])
                    ->orderByDesc('fecha_pedido')
                    ->orderByDesc('id'),
                'ventas' => fn ($q) => $q
                    ->with(['items.producto', 'pagos.metodoPago'])
                    ->orderByDesc('fecha_venta')
                    ->orderByDesc('id'),
                'todasLasReservas' => fn ($q) => $q
                    ->with('producto')
                    ->orderByDesc('id'),
                'devoluciones' => fn ($q) => $q
                    ->with(['pedido.items.producto'])
                    ->orderByDesc('id'),
            ])
            ->findOrFail($id);

        $totalComprado = (float) ($cliente->total_comprado ?? 0);
        $compras = (int) ($cliente->compras_count ?? 0);

        $cliente->ticket_promedio = number_format($compras > 0 ? $totalComprado / $compras : 0, 2, '.', '');
        $cliente->saldo_pendiente = number_format(
            (float) $cliente->pedidos
                ->where('estado', '!=', Pedido::ESTADO_CANCELADO)
                ->sum(fn (Pedido $p) => (float) $p->saldoPendiente()),
            2,
            '.',
            ''
        );

        // Totales de devoluciones del cliente (sumatoria por pedido)
        $totalReembolsado = 0.0;
        if ($cliente->relationLoaded('pedidos') || $cliente->pedidos !== null) {
            $totalReembolsado = $cliente->pedidos
                ->filter(fn ($p) => $p->devolucion)
                ->sum(fn ($p) => (float) $p->devolucion->monto_reembolsado);
        } else {
            $totalReembolsado = (float) $cliente->pedidos()
                ->join('devoluciones', 'devoluciones.pedido_id', '=', 'pedidos.id')
                ->sum('devoluciones.monto_reembolsado');
        }

        $cliente->total_reembolsado = number_format($totalReembolsado, 2, '.', '');
        $cliente->total_comprado_neto = number_format(max(0.0, $totalComprado - $totalReembolsado), 2, '.', '');

        return Api::resource(new ClienteResource($cliente), 'Detalle del cliente.');
    }

    public function update(ClienteUpdateRequest $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $datos = $request->validated();

        if (! ($datos['confirmar_duplicado'] ?? false)) {
            $coincidencias = $this->buscarCoincidencias(
                $datos['telefono'] ?? $cliente->telefono,
                $datos['email'] ?? $cliente->email,
                $cliente->id
            );

            if ($coincidencias->isNotEmpty()) {
                return $this->respuestaDuplicado($coincidencias);
            }
        }

        $cliente->update($datos);

        return Api::resource(new ClienteResource($cliente), 'Cliente actualizado correctamente.');
    }

    public function destroy(int $id)
    {
        $cliente = Cliente::findOrFail($id);

        if ($cliente->pedidos()->exists()) {
            return Api::error('No se puede eliminar el cliente porque tiene pedidos asociados.', 409);
        }

        $cliente->delete();

        return Api::noContent('Cliente eliminado correctamente.');
    }

    /**
     * Grupos de clientes repetidos (mismo teléfono normalizado o mismo correo).
     * No une nada: solo devuelve las coincidencias para revisión manual.
     */
    public function duplicados()
    {
        $coleccion = Cliente::query()
            ->noPrueba()
            ->withCount('pedidos')
            ->get();

        $grupos = [];
        $indicePorClave = [];

        foreach ($coleccion as $cliente) {
            $claves = array_values(array_filter([
                $cliente->telefono_normalizado ? 'tel:'.$cliente->telefono_normalizado : null,
                $cliente->email ? 'mail:'.Contacto::normalizarEmail($cliente->email) : null,
            ]));

            $destino = null;
            foreach ($claves as $clave) {
                if (isset($indicePorClave[$clave])) {
                    $destino = $indicePorClave[$clave];
                    break;
                }
            }

            if ($destino === null) {
                $grupos[] = [];
                $destino = array_key_last($grupos);
            }

            $grupos[$destino][$cliente->id] = $cliente->id;

            foreach ($claves as $clave) {
                if (isset($indicePorClave[$clave]) && $indicePorClave[$clave] !== $destino) {
                    $otro = $indicePorClave[$clave];
                    foreach ($grupos[$otro] as $idGrupo) {
                        $grupos[$destino][$idGrupo] = $idGrupo;
                    }
                    $grupos[$otro] = [];
                    foreach ($indicePorClave as $k => $v) {
                        if ($v === $otro) {
                            $indicePorClave[$k] = $destino;
                        }
                    }
                }
                $indicePorClave[$clave] = $destino;
            }
        }

        $resultado = collect($grupos)
            ->filter(fn ($ids) => count($ids) > 1)
            ->map(fn ($ids) => $coleccion
                ->whereIn('id', array_values($ids))
                ->values()
                ->map(fn (Cliente $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'telefono' => $c->telefono,
                    'email' => $c->email,
                    'ciudad' => $c->ciudad,
                    'bloqueado' => (bool) $c->bloqueado,
                    'pedidos_count' => $c->pedidos_count,
                    'fecha_ultimo_pedido' => $c->fecha_ultimo_pedido?->toDateString(),
                ])
                ->all())
            ->values()
            ->all();

        return Api::success($resultado, 'Clientes duplicados.');
    }

    /**
     * Une uno o más clientes duplicados dentro de un cliente principal:
     * mueve pedidos, ventas y reservas y oculta (es_prueba) los absorbidos.
     * Nunca borra clientes.
     */
    public function unir(Request $request)
    {
        $datos = $request->validate([
            'principal_id' => ['required', 'integer', 'exists:clientes,id'],
            'duplicado_ids' => ['required', 'array', 'min:1'],
            'duplicado_ids.*' => ['required', 'integer', 'exists:clientes,id', 'different:principal_id'],
        ]);

        /** @var Cliente $principal */
        $principal = Cliente::findOrFail($datos['principal_id']);

        $duplicados = Cliente::query()
            ->whereIn('id', $datos['duplicado_ids'])
            ->where('id', '!=', $principal->id)
            ->get();

        if ($duplicados->isEmpty()) {
            return Api::error('No hay clientes válidos para unir.', 422);
        }

        DB::transaction(function () use ($principal, $duplicados) {
            $ids = $duplicados->pluck('id')->all();

            Pedido::query()->whereIn('cliente_id', $ids)->update(['cliente_id' => $principal->id]);
            Venta::query()->whereIn('cliente_id', $ids)->update(['cliente_id' => $principal->id]);
            Reserva::query()->whereIn('cliente_id', $ids)->update(['cliente_id' => $principal->id]);

            // Completa los datos de contacto que le falten al principal.
            foreach (['telefono', 'email', 'direccion', 'ciudad', 'notas'] as $campo) {
                if (blank($principal->{$campo})) {
                    $valor = $duplicados->first(fn (Cliente $d) => filled($d->{$campo}))?->{$campo};
                    if ($valor) {
                        $principal->{$campo} = $valor;
                    }
                }
            }

            $fechas = Pedido::query()
                ->where('cliente_id', $principal->id)
                ->selectRaw('MIN(fecha_pedido) as primera, MAX(fecha_pedido) as ultima')
                ->first();

            if ($fechas?->primera) {
                $principal->fecha_primer_pedido = $fechas->primera;
            }
            if ($fechas?->ultima) {
                $principal->fecha_ultimo_pedido = $fechas->ultima;
            }

            $principal->save();

            Cliente::query()->whereIn('id', $ids)->update(['es_prueba' => true]);
        });

        $principal = $principal->fresh()->loadCount('pedidos');

        return Api::resource(new ClienteResource($principal), 'Clientes unidos correctamente.');
    }

    /**
     * Exporta el listado filtrado a CSV (se abre directamente en Excel).
     */
    public function exportar(Request $request)
    {
        $clientes = $this->consultaClientes($request)->get();

        $headers = [
            'ID', 'Nombre', 'Teléfono', 'Correo', 'Ciudad', 'Dirección',
            'Pedidos', 'Total comprado (Bs)', 'Estado', 'Último pedido', 'Fecha último pedido',
        ];

        $rows = $clientes->map(fn (Cliente $c) => [
            $c->id,
            $c->nombre,
            $c->telefono,
            $c->email,
            $c->ciudad,
            $c->direccion,
            (int) ($c->pedidos_count ?? 0),
            number_format((float) ($c->total_comprado ?? 0), 2, '.', ''),
            $c->bloqueado ? 'Bloqueado' : 'Activo',
            $c->ultimo_numero,
            $c->ultimo_fecha,
        ])->all();

        $csv = $this->generarCsv($headers, $rows);
        $nombre = 'everly_clientes_'.now()->format('Y-m-d_His').'.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Busca clientes (no prueba) que coincidan por teléfono normalizado o correo.
     *
     * @return Collection<int, Cliente>
     */
    private function buscarCoincidencias(?string $telefono, ?string $email, ?int $ignorarId = null): Collection
    {
        $tel = Contacto::normalizarTelefono($telefono);
        $mail = Contacto::normalizarEmail($email);

        if (! $tel && ! $mail) {
            return collect();
        }

        return Cliente::query()
            ->where('es_prueba', false)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->where(function ($q) use ($tel, $mail) {
                if ($tel) {
                    $q->orWhere('telefono_normalizado', $tel);
                }
                if ($mail) {
                    $q->orWhereRaw('LOWER(email) = ?', [$mail]);
                }
            })
            ->limit(5)
            ->get();
    }

    private function respuestaDuplicado(Collection $coincidencias)
    {
        $mensaje = 'Ya existe un cliente con ese teléfono o correo: '
            .$coincidencias->pluck('nombre')->implode(', ')
            .'.';

        return Api::error($mensaje, 422, [
            'telefono' => [$mensaje],
            'email' => [$mensaje],
            'confirmar_duplicado' => [$mensaje],
        ]);
    }

    private function generarCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, ',', '"');

        foreach ($rows as $row) {
            fputcsv($output, $row, ',', '"');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}

<?php

namespace App\Services;

use App\Models\ConfiguracionHistorial;
use App\Models\MetodoPago;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MetodoPagoService
{
    protected const DISCO = 'public';

    protected const CARPETA_QR = 'metodos-pago';

    /**
     * Campo de la tabla => clave usada en el historial de actividad.
     *
     * @var array<string, string>
     */
    protected const CAMPOS_HISTORIAL = [
        'nombre' => 'metodo_pago_nombre',
        'instrucciones' => 'metodo_pago_instrucciones',
        'titular' => 'metodo_pago_titular',
        'banco' => 'metodo_pago_banco',
        'numero_cuenta' => 'metodo_pago_numero_cuenta',
        'activo' => 'metodo_pago_activo',
        'orden' => 'metodo_pago_orden',
    ];

    /**
     * @var array<string, string>
     */
    public const ETIQUETAS = [
        'metodo_pago_nombre' => 'Nombre visible',
        'metodo_pago_instrucciones' => 'Instrucciones para el cliente',
        'metodo_pago_titular' => 'Titular',
        'metodo_pago_banco' => 'Banco',
        'metodo_pago_numero_cuenta' => 'Número de cuenta',
        'metodo_pago_activo' => 'Estado',
        'metodo_pago_orden' => 'Orden',
        'metodo_pago_qr' => 'Imagen QR',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listar(): array
    {
        return MetodoPago::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (MetodoPago $metodo) => $this->datosAdmin($metodo))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publicos(): array
    {
        return MetodoPago::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (MetodoPago $metodo) => $this->datosPublicos($metodo))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function datosAdmin(MetodoPago $metodo): array
    {
        return [
            'id' => $metodo->id,
            'nombre' => $metodo->nombre,
            'codigo' => $metodo->codigo,
            'instrucciones' => $metodo->instrucciones,
            'titular' => $metodo->titular,
            'banco' => $metodo->banco,
            'numero_cuenta' => $metodo->numero_cuenta,
            'activo' => $metodo->activo,
            'orden' => $metodo->orden,
            'qr_url' => $metodo->qr_url,
            'qr_nombre' => $metodo->qr_nombre,
            'qr_mime' => $metodo->qr_mime,
            'qr_tamano' => $metodo->qr_tamano,
            'qr_subido_en' => $metodo->qr_subido_en?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosPublicos(MetodoPago $metodo): array
    {
        return [
            'id' => $metodo->id,
            'nombre' => $metodo->nombre,
            'codigo' => $metodo->codigo,
            'instrucciones' => $metodo->instrucciones,
            'titular' => $metodo->titular,
            'banco' => $metodo->banco,
            'numero_cuenta' => $metodo->numero_cuenta,
            'qr_url' => $metodo->qr_url,
            'orden' => $metodo->orden,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(
        MetodoPago $metodo,
        array $datos,
        ?UploadedFile $qr,
        bool $quitarQr,
        int $userId,
    ): MetodoPago {
        $activoNuevo = array_key_exists('activo', $datos)
            ? (bool) $datos['activo']
            : $metodo->activo;

        if ($metodo->activo && ! $activoNuevo) {
            $otrosActivos = MetodoPago::query()
                ->where('activo', true)
                ->whereKeyNot($metodo->id)
                ->exists();

            if (! $otrosActivos) {
                throw ValidationException::withMessages([
                    'activo' => 'Debe quedar al menos un método de pago activo.',
                ]);
            }
        }

        $qrAnterior = $metodo->qr_ruta;
        $qrAEliminar = null;

        DB::transaction(function () use ($metodo, $datos, $qr, $quitarQr, $userId, $qrAnterior, &$qrAEliminar) {
            $cambios = [];

            foreach (self::CAMPOS_HISTORIAL as $campo => $claveHistorial) {
                if (! array_key_exists($campo, $datos)) {
                    continue;
                }

                $anterior = $metodo->getAttribute($campo);
                $nuevo = $this->normalizar($campo, $datos[$campo]);

                if ($this->sonIguales($anterior, $nuevo)) {
                    continue;
                }

                $cambios[] = [$claveHistorial, $this->valorHistorial($anterior), $this->valorHistorial($nuevo)];
                $metodo->setAttribute($campo, $nuevo);
            }

            if ($qr) {
                $ruta = $this->almacenamiento()->putFileAs(
                    self::CARPETA_QR,
                    $qr,
                    $this->nombreArchivo($qr),
                    'public',
                );

                $metodo->fill([
                    'qr_ruta' => $ruta,
                    'qr_nombre' => $qr->getClientOriginalName(),
                    'qr_mime' => $qr->getClientMimeType(),
                    'qr_tamano' => $qr->getSize(),
                    'qr_subido_en' => now(),
                ]);

                $cambios[] = ['metodo_pago_qr', $qrAnterior, $ruta];
                $qrAEliminar = $qrAnterior;
            } elseif ($quitarQr && $qrAnterior) {
                $metodo->fill([
                    'qr_ruta' => null,
                    'qr_nombre' => null,
                    'qr_mime' => null,
                    'qr_tamano' => null,
                    'qr_subido_en' => null,
                ]);

                $cambios[] = ['metodo_pago_qr', $qrAnterior, null];
                $qrAEliminar = $qrAnterior;
            }

            $metodo->save();

            foreach ($cambios as [$campo, $anterior, $nuevo]) {
                ConfiguracionHistorial::create([
                    'campo' => $campo,
                    'metodo_pago_id' => $metodo->id,
                    'metodo_pago_nombre' => $metodo->nombre,
                    'valor_anterior' => $anterior,
                    'valor_nuevo' => $nuevo,
                    'user_id' => $userId,
                ]);
            }
        });

        if ($qrAEliminar) {
            $this->eliminarArchivoSiExiste($qrAEliminar);
        }

        return $metodo->fresh();
    }

    protected function normalizar(string $campo, mixed $valor): mixed
    {
        if ($campo === 'activo') {
            return (bool) $valor;
        }

        if ($campo === 'orden') {
            return (int) $valor;
        }

        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    protected function sonIguales(mixed $anterior, mixed $nuevo): bool
    {
        if (is_bool($anterior) || is_bool($nuevo)) {
            return (bool) $anterior === (bool) $nuevo;
        }

        return (string) ($anterior ?? '') === (string) ($nuevo ?? '');
    }

    protected function valorHistorial(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        return (string) $valor;
    }

    protected function almacenamiento(): Filesystem
    {
        return Storage::disk(self::DISCO);
    }

    protected function nombreArchivo(UploadedFile $archivo): string
    {
        $extension = strtolower($archivo->guessExtension() ?: ($archivo->getClientOriginalExtension() ?: 'png'));

        return Str::uuid()->toString().'.'.$extension;
    }

    protected function eliminarArchivoSiExiste(?string $ruta): void
    {
        if ($ruta && $this->almacenamiento()->exists($ruta)) {
            $this->almacenamiento()->delete($ruta);
        }
    }
}

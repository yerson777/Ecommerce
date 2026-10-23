<?php

namespace App\Services;

use App\Models\ConfiguracionHistorial;
use App\Models\Setting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Datos públicos y textos de la tienda administrados desde Configuración.
 *
 * Se guardan como claves de la tabla "settings". Los campos vacíos se
 * conservan como null para que la tienda use su valor de respaldo.
 */
class ConfiguracionTiendaService
{
    protected const DISCO = 'public';

    protected const CARPETA_LOGO = 'configuracion';

    /**
     * Clave de settings => nombre del campo recibido por la API.
     *
     * @var array<string, string>
     */
    protected const CAMPOS = [
        'tienda_nombre' => 'nombre',
        'tienda_whatsapp' => 'whatsapp',
        'tienda_correo' => 'correo',
        'tienda_direccion' => 'direccion',
        'tienda_horario' => 'horario',
        'tienda_instagram' => 'instagram',
        'tienda_facebook' => 'facebook',
        'tienda_tiktok' => 'tiktok',
        'tienda_cambios_devoluciones' => 'cambios_devoluciones',
        'tienda_envios' => 'envios',
        'tienda_terminos' => 'terminos',
        'moneda_simbolo' => 'moneda_simbolo',
        'moneda_decimales' => 'moneda_decimales',
        'moneda_separador_miles' => 'moneda_separador_miles',
        'moneda_separador_decimal' => 'moneda_separador_decimal',
    ];

    /**
     * Etiquetas legibles para el historial de cambios.
     *
     * @var array<string, string>
     */
    public const ETIQUETAS = [
        'tienda_nombre' => 'Nombre de la tienda',
        'tienda_logo' => 'Logo',
        'tienda_whatsapp' => 'WhatsApp',
        'tienda_correo' => 'Correo electrónico',
        'tienda_direccion' => 'Dirección',
        'tienda_horario' => 'Horario',
        'tienda_instagram' => 'Instagram',
        'tienda_facebook' => 'Facebook',
        'tienda_tiktok' => 'TikTok',
        'tienda_cambios_devoluciones' => 'Cambios y devoluciones',
        'tienda_envios' => 'Envíos',
        'tienda_terminos' => 'Términos y condiciones',
        'moneda_simbolo' => 'Símbolo de moneda',
        'moneda_decimales' => 'Decimales de precio',
        'moneda_separador_miles' => 'Separador de miles',
        'moneda_separador_decimal' => 'Separador decimal',
    ];

    protected function almacenamiento(): Filesystem
    {
        return Storage::disk(self::DISCO);
    }

    protected function urlDeRuta(?string $ruta): ?string
    {
        return $ruta ? $this->almacenamiento()->url($ruta) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $claves = array_merge(array_keys(self::CAMPOS), ['tienda_logo']);

        $valores = Setting::query()
            ->whereIn('nombre', $claves)
            ->pluck('valor', 'nombre');

        $valor = fn (string $clave): ?string => $valores->has($clave)
            ? ($valores[$clave] === '' ? null : $valores[$clave])
            : null;

        $decimales = $valor('moneda_decimales');
        $simbolo = $valor('moneda_simbolo');
        $separadorMiles = $valor('moneda_separador_miles');
        $separadorDecimal = $valor('moneda_separador_decimal');

        $logo = $valor('tienda_logo');

        return [
            'nombre' => $valor('tienda_nombre'),
            'logo' => $logo,
            'logo_url' => $this->urlDeRuta($logo),
            'whatsapp' => $valor('tienda_whatsapp'),
            'correo' => $valor('tienda_correo'),
            'direccion' => $valor('tienda_direccion'),
            'horario' => $valor('tienda_horario'),
            'instagram' => $valor('tienda_instagram'),
            'facebook' => $valor('tienda_facebook'),
            'tiktok' => $valor('tienda_tiktok'),
            'textos' => [
                'cambios_devoluciones' => $valor('tienda_cambios_devoluciones'),
                'envios' => $valor('tienda_envios'),
                'terminos' => $valor('tienda_terminos'),
            ],
            'moneda' => [
                'simbolo' => $simbolo ?? 'Bs',
                'decimales' => $decimales === null ? 2 : max(0, min(4, (int) $decimales)),
                'separador_miles' => $separadorMiles ?? '',
                'separador_decimal' => $separadorDecimal ?? '.',
            ],
        ];
    }

    /**
     * Actualiza los datos recibidos, registra cada cambio y devuelve el
     * estado final. Los valores vacíos se guardan como null.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function actualizar(array $datos, ?UploadedFile $logo, bool $quitarLogo, int $userId): array
    {
        $logoAnterior = Setting::query()->where('nombre', 'tienda_logo')->value('valor');
        $logoAEliminar = null;

        DB::transaction(function () use ($datos, $logo, $quitarLogo, $userId, $logoAnterior, &$logoAEliminar) {
            foreach (self::CAMPOS as $clave => $campo) {
                if (! array_key_exists($campo, $datos)) {
                    continue;
                }

                $nuevo = $this->normalizarValor($clave, $datos[$campo]);
                $anterior = Setting::query()->where('nombre', $clave)->value('valor');

                if ($this->sonIguales($anterior, $nuevo)) {
                    continue;
                }

                Setting::query()->updateOrCreate(
                    ['nombre' => $clave],
                    ['valor' => $nuevo],
                );

                $this->registrarCambio($clave, $anterior, $nuevo, $userId);
            }

            if ($logo) {
                $ruta = $this->almacenamiento()->putFileAs(
                    self::CARPETA_LOGO,
                    $logo,
                    $this->nombreArchivo($logo),
                    'public',
                );

                Setting::query()->updateOrCreate(
                    ['nombre' => 'tienda_logo'],
                    ['valor' => $ruta],
                );

                $this->registrarCambio('tienda_logo', $logoAnterior, $ruta, $userId);
                $logoAEliminar = $logoAnterior;
            } elseif ($quitarLogo && $logoAnterior) {
                Setting::query()->updateOrCreate(
                    ['nombre' => 'tienda_logo'],
                    ['valor' => null],
                );

                $this->registrarCambio('tienda_logo', $logoAnterior, null, $userId);
                $logoAEliminar = $logoAnterior;
            }
        });

        if ($logoAEliminar) {
            $this->eliminarArchivoSiExiste($logoAEliminar);
        }

        return $this->datos();
    }

    protected function normalizarValor(string $clave, mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if ($clave === 'moneda_decimales') {
            if ($valor === '') {
                return null;
            }

            return (string) max(0, min(4, (int) $valor));
        }

        $texto = is_string($valor) ? trim($valor) : (string) $valor;

        if ($texto === '') {
            return null;
        }

        return match ($clave) {
            'tienda_instagram' => $this->normalizarRed('instagram', $texto),
            'tienda_facebook' => $this->normalizarRed('facebook', $texto),
            'tienda_tiktok' => $this->normalizarRed('tiktok', $texto),
            default => $texto,
        };
    }

    /**
     * Acepta un usuario (@cuenta) o una URL y devuelve siempre una URL.
     */
    protected function normalizarRed(string $red, string $valor): string
    {
        if (preg_match('~^https?://~i', $valor)) {
            return $valor;
        }

        $usuario = ltrim($valor, '@');

        if (str_contains($usuario, '.') || str_contains($usuario, '/')) {
            return 'https://'.$usuario;
        }

        return match ($red) {
            'instagram' => 'https://instagram.com/'.$usuario,
            'facebook' => 'https://facebook.com/'.$usuario,
            'tiktok' => 'https://tiktok.com/@'.$usuario,
            default => $valor,
        };
    }

    protected function sonIguales(?string $anterior, ?string $nuevo): bool
    {
        return ($anterior ?? '') === ($nuevo ?? '');
    }

    protected function registrarCambio(string $clave, ?string $anterior, ?string $nuevo, int $userId): void
    {
        ConfiguracionHistorial::create([
            'campo' => $clave,
            'valor_anterior' => $anterior,
            'valor_nuevo' => $nuevo,
            'user_id' => $userId,
        ]);
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

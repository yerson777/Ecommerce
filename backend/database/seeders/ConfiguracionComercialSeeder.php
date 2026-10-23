<?php

namespace Database\Seeders;

use App\Models\CategoriaGasto;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

class ConfiguracionComercialSeeder extends Seeder
{
    public function run(): void
    {
        $metodosPago = [
            ['nombre' => 'Efectivo', 'codigo' => 'efectivo', 'orden' => 0],
            ['nombre' => 'QR', 'codigo' => 'qr', 'orden' => 1],
            ['nombre' => 'Transferencia', 'codigo' => 'transferencia', 'orden' => 2],
        ];
        foreach ($metodosPago as $metodo) {
            MetodoPago::query()->updateOrCreate(
                ['nombre' => $metodo['nombre']],
                ['codigo' => $metodo['codigo'], 'activo' => true, 'orden' => $metodo['orden']]
            );
        }

        $nombresPago = array_column($metodosPago, 'nombre');
        MetodoPago::query()
            ->whereNotIn('nombre', $nombresPago)
            ->update(['activo' => false]);

        $metodosEntrega = [
            ['nombre' => 'Retiro presencial (plaza Corazonistas)', 'costo' => 0, 'orden' => 0, 'modalidad' => 'recojo'],
            ['nombre' => 'Envío por Yango desde la plaza Corazonistas', 'costo' => 0, 'orden' => 1, 'modalidad' => 'ciudad'],
            ['nombre' => 'Paquetería Flash Store (CORREO)', 'costo' => 0, 'orden' => 2, 'modalidad' => 'paqueteria'],
            ['nombre' => 'Paquetería Flash Store (QUILLACOLLO)', 'costo' => 0, 'orden' => 3, 'modalidad' => 'paqueteria'],
            ['nombre' => 'Envío Provincial o Departamental', 'costo' => 0, 'orden' => 4, 'modalidad' => 'paqueteria'],
        ];
        foreach ($metodosEntrega as $datos) {
            MetodoEntrega::query()->updateOrCreate(
                ['nombre' => $datos['nombre']],
                $datos + ['activo' => true]
            );
        }

        $nombresEntrega = array_column($metodosEntrega, 'nombre');
        MetodoEntrega::query()
            ->whereNotIn('nombre', $nombresEntrega)
            ->update(['activo' => false]);

        $categoriasGasto = ['Transporte', 'Publicidad', 'Embalaje', 'Otros', 'Envío'];
        foreach ($categoriasGasto as $orden => $nombre) {
            CategoriaGasto::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true, 'orden' => $orden]
            );
        }
    }
}

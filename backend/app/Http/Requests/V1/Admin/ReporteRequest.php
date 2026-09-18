<?php

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periodo' => ['nullable', 'string', 'in:hoy,ayer,ultimos_7_dias,ultimos_30_dias,este_mes,mes_anterior,este_anio,personalizado'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'metodo_pago_id' => ['nullable', 'integer', 'exists:metodos_pago,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'talla_id' => ['nullable', 'integer', 'exists:tallas,id'],
            'estado' => ['nullable', 'string', 'in:disponible,reservada,vendida'],
            'estado_pedido' => ['nullable', 'string', 'in:pendiente,confirmado,cancelado,completado'],
            'estado_pago' => ['nullable', 'string', 'in:pendiente,parcial,pagado'],
            'cliente' => ['nullable', 'string', 'max:255'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

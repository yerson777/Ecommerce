<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos de la cuenta del cliente para la tienda. Nunca expone la contraseña
 * ni información administrativa.
 */
class ClienteCuentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'email_verificado' => $this->email_verified_at !== null,
            'bloqueado' => (bool) $this->bloqueado,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

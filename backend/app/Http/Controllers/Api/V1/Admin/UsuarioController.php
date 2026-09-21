<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Usuario\UsuarioStoreRequest;
use App\Http\Requests\V1\Admin\Usuario\UsuarioUpdateRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::query()
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $busqueda = trim($request->input('busqueda'));
                $q->where(function ($query) use ($busqueda) {
                    $query->where('name', 'like', "%{$busqueda}%")
                        ->orWhere('email', 'like', "%{$busqueda}%");
                });
            })
            ->when($request->filled('rol'), fn ($q) => $q->where('role', $request->input('rol')))
            ->orderBy('activo', 'desc')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(UserResource::collection($usuarios), 'Listado de usuarios.');
    }

    public function store(UsuarioStoreRequest $request)
    {
        $usuario = User::create($request->validated());
        $usuario->refresh();

        return Api::resource(new UserResource($usuario), 'Usuario creado correctamente.', 201);
    }

    public function show(int $id)
    {
        $usuario = User::findOrFail($id);

        return Api::resource(new UserResource($usuario), 'Detalle del usuario.');
    }

    public function update(UsuarioUpdateRequest $request, int $id)
    {
        $usuario = User::findOrFail($id);
        $datos = $request->validated();
        $actual = $request->user();

        if ((int) $usuario->id === (int) $actual->id) {
            if (array_key_exists('role', $datos) && $datos['role'] !== $usuario->role) {
                throw ValidationException::withMessages([
                    'role' => ['No podés cambiar el rol de tu propio usuario.'],
                ]);
            }
            if (array_key_exists('activo', $datos) && ! $datos['activo']) {
                throw ValidationException::withMessages([
                    'activo' => ['No podés desactivar tu propio usuario.'],
                ]);
            }
        }

        $pierdeSuperAdmin = $usuario->esSuperAdmin() && $usuario->activo && (
            (array_key_exists('role', $datos) && $datos['role'] !== User::ROL_SUPER_ADMIN)
            || (array_key_exists('activo', $datos) && ! $datos['activo'])
        );

        if ($pierdeSuperAdmin && $this->esUltimoSuperAdminActivo($usuario)) {
            throw ValidationException::withMessages([
                'role' => ['No se puede desactivar el único super administrador activo.'],
            ]);
        }

        $usuario->update($datos);

        return Api::resource(new UserResource($usuario), 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, int $id)
    {
        $usuario = User::findOrFail($id);

        if ((int) $usuario->id === (int) $request->user()->id) {
            return Api::error('No podés desactivar tu propio usuario.', 422);
        }

        if ($usuario->esSuperAdmin() && $usuario->activo && $this->esUltimoSuperAdminActivo($usuario)) {
            return Api::error('No se puede desactivar el único super administrador activo.', 422);
        }

        $usuario->update(['activo' => false]);

        return Api::success(new UserResource($usuario), 'Usuario desactivado correctamente.');
    }

    private function esUltimoSuperAdminActivo(User $usuario): bool
    {
        return User::query()
            ->where('role', User::ROL_SUPER_ADMIN)
            ->where('activo', true)
            ->whereKeyNot($usuario->id)
            ->count() === 0;
    }
}
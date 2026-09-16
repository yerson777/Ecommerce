# API Everly

Backend Laravel 12. Base URL: `http://127.0.0.1:8000/api` · Versión: **v1**.

Todo el tráfico es JSON (`Content-Type: application/json`). La API se divide en dos grupos:

| Grupo | Prefijo | Acceso |
|---|---|---|
| **Store** (tienda Angular) | `/api/v1/store/*` | Público, sin autenticación |
| **Admin** (panel admin) | `/api/v1/admin/*` | Token Bearer + rol `admin` / `super_admin` |

---

## Formato de respuesta

**Éxito**

```json
{ "success": true, "message": "Mensaje.", "data": { ... } }
```

- `data` es un objeto (dato único) o un array (listado).
- En listados paginados se incluye `meta`:

```json
{
  "success": true,
  "message": "Listado de productos.",
  "data": [ { "id": 1, ... } ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 6 }
}
```

**Error**

```json
{ "success": false, "message": "Mensaje.", "errors": { "campo": ["Motivo"] } }
```

`errors` solo aparece en errores de validación (422).

### Códigos de estado

| Código | Significado |
|---|---|
| 200 | Éxito (listado, detalle, login, saldo) |
| 201 | Recurso creado |
| 204 | Recurso eliminado |
| 401 | No autenticado (token ausente o inválido) |
| 403 | No autorizado (rol insuficiente) |
| 404 | Recurso no encontrado |
| 409 | Conflicto (p. ej. eliminar recurso en uso) |
| 422 | Error de validación |

---

## Autenticación (Admin)

Uso de **Laravel Sanctum**, tokens Bearer.

### `POST /api/v1/admin/auth/login`

```json
{ "email": "admin@everly.local", "password": "..." }
```

Respuesta `200`:

```json
{
  "success": true,
  "message": "Inicio de sesión exitoso.",
  "data": {
    "token": "...",
    "user": { "id": 1, "name": "...", "email": "...", "role": "super_admin" }
  }
}
```

Devolución: `422` si falta `email`/`password` o las credenciales son incorrectas.

### `POST /api/v1/admin/auth/logout` · `GET /api/v1/admin/auth/me`

Ambos requieren `Authorization: Bearer <token>`. `logout` revoca el token; `me` devuelve el usuario autenticado (`{ success, message, data: { user } }`).

> Contrato consumido por Angular (admin): `login` → `{ token, user }`; `ApiResponse<T>` → `{ success, message, data }`.

---

## Públicas · Store

Nunca exponen costo, margen, ganancias ni datos administrativos.

### `GET /api/v1/store/products`

Listado de productos **publicados y disponibles** (estado `disponible`). Nunca aparecen prendas reservadas, vendidas ni ocultas. Parámetros opcionales:

| Parámetro | Tipo | Descripción |
|---|---|---|
| `categoria` | int | Filtrar por id de categoría |
| `talla` | int | Filtrar por id de talla |
| `page` | int | Paginación |

Respuesta `200` paginada. Cada item:

```json
{
  "id": 1,
  "codigo": "EV-0001",
  "nombre": "Vestido floral",
  "color": "Rojo",
  "descripcion": "...",
  "precio": "199.00",
  "estado": "disponible",
  "categoria": { "id": 1, "nombre": "Vestidos", "slug": "vestidos" },
  "talla": { "id": 1, "nombre": "M" },
  "imagenes": [ { "id": 1, "ruta": "...", "portada": true } ]
}
```

### `GET /api/v1/store/products/{id}`

Detalle de un producto **publicado y disponible**. `404` si el producto no existe, no está publicado o no está disponible.

### `GET /api/v1/store/categories` · `GET /api/v1/store/sizes`

Listados públicos de categorías activas y tallas. Sin paginar.

---

## Administrativas · Admin

Requieren `Authorization: Bearer <token>` y rol `admin` o `super_admin`.

### Productos

Cada producto representa **una prenda física única** (una sola unidad: talla + color + código). No hay stock por cantidades.

**Estados de la prenda:** `disponible` → `reservada` → `vendida`. El estado **no se modifica al editar** el producto; solo cambia a través de las operaciones de inventario (`reservar`, `liberar`, `vender`). Un producto `vendida` es inmutable (no se puede editar ni volver a vender/reservar).

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/productos` | Listado paginado con filtros (ver abajo) |
| POST | `/api/v1/admin/productos` | Crear (201); estado inicial obligatorio `disponible` |
| GET | `/api/v1/admin/productos/{id}` | Detalle |
| PUT | `/api/v1/admin/productos/{id}` | Actualizar (no acepta `estado`) |
| DELETE | `/api/v1/admin/productos/{id}` | Eliminar (200; 409 si tiene movimientos o no está `disponible`) |
| GET | `/api/v1/admin/productos/{id}/historial` | Historial de cambios de la prenda |

**Filtros del listado:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `estado` | string | `disponible`, `reservada` o `vendida` |
| `publicado` | bool | `1`/`0` |
| `categoria` | int | Id de categoría |
| `talla` | int | Id de talla |
| `precio_min` / `precio_max` | number | Rango de precio |
| `busqueda` | string | Coincidencia en código o nombre |
| `per_page` | int | Paginación (default 15) |

Campos (store):

```json
{
  "codigo": "EV-0001",
  "nombre": "Vestido floral",
  "categoria_id": 1,
  "talla_id": 1,
  "color": "Rojo",
  "descripcion": "...",
  "costo": 60.00,
  "precio": 199.00,
  "estado": "disponible",
  "publicado": true,
  "fecha_ingreso": "2026-01-01",
  "imagenes": []
}
```

Reglas: `codigo`, `nombre`, `categoria_id`, `talla_id`, `costo`, `precio`, `estado`, `publicado` obligatorios; `costo`/`precio` decimales ≥ 0; `estado` ∈ `disponible, reservado, vendido`; `categoria_id`/`talla_id` deben existir.

### Inventario

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/inventario` | Resumen: `total`, `por_estado`, `publicados`, `no_publicados`, `por_categoria`, `por_talla`, `por_rango_precio` |

### Operaciones de inventario (reserva · liberación · venta)

Todas cambian el estado de la prenda y son la **única** vía legítima para hacerlo. Se ejecutan en una transacción con `SELECT ... FOR UPDATE` sobre el producto, re-validando el estado antes de escribir, con restricciones UNIQUE de BD como barrera final contra la doble venta/doble reserva.

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/v1/admin/inventario/reservar` | `disponible` → `reservada`. Crea la reserva (201). Vence en `vence_en` si se indica |
| POST | `/api/v1/admin/inventario/liberar` | `reservada` → `disponible`. Cierra la reserva activa como `liberada` |
| POST | `/api/v1/admin/inventario/vender` | `disponible` o `reservada` → `vendida`. Crea la venta formal, despublica la prenda y completa la reserva (201) |

Solicitudes:

```json
// reservar
{ "producto_id": 1, "vence_en": "2026-10-01 12:00:00" }
// liberar
{ "producto_id": 1 }
// vender
{ "producto_id": 1, "cliente_id": 2, "costo_envio": 0, "notas": "..." }
```

Errores de negocio responden `409` (`success: false`) y no alteran la BD: reservar una prenda no disponible, vender una prenda ya vendida, liberar una prenda no reservada, vender a cliente inexistente.

**Protección contra doble venta:** dos peticiones simultáneas sobre la misma prenda no pueden terminar ambas en venta:
1. `lockForUpdate()` serializa el acceso a la fila del producto dentro de la transacción.
2. Se valida el estado actual dentro de la transacción (`vendida` → rechazada).
3. `venta_items.producto_id` es UNIQUE a nivel global: la segunda inserción falla con `409`.

Análogamente, `reservas.guard_activo_id` (columna generada + UNIQUE) garantiza **una sola reserva activa por prenda**.

### Categorías

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/categorias` | Listado (con `productos_count`) |
| POST | `/api/v1/admin/categorias` | Crear (201) |
| GET | `/api/v1/admin/categorias/{id}` | Detalle |
| PUT | `/api/v1/admin/categorias/{id}` | Actualizar |
| DELETE | `/api/v1/admin/categorias/{id}` | Eliminar (204; 409 si tiene productos) |

Campos: `nombre` (obligatorio), `slug` (autogenerado si se omite), `activo`, `orden`.

### Tallas

Mismo CRUD que categorías en `/api/v1/admin/tallas`. Campo: `nombre` (obligatorio).

### Clientes

CRUD completo en `/api/v1/admin/clientes`. Campos típicos: `nombre`, `email`, `telefono`, `direccion`, `ciudad`, `activo`.

### Otras lecturas

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/pedidos` · `/pedidos/{id}` | Listado/detalle con items |
| GET | `/api/v1/admin/ventas` · `/ventas/{id}` | Listado/detalle de ventas |
| GET | `/api/v1/admin/pagos` · `/pagos/{id}` | Listado/detalle de pagos |
| GET | `/api/v1/admin/gastos` · `/gastos/{id}` | Listado/detalle de gastos |
| GET | `/api/v1/admin/caja/movimientos` | Movimientos de caja |
| GET | `/api/v1/admin/caja/saldo` | Saldo de caja |
| GET | `/api/v1/admin/reportes/resumen` | Resumen para reportes |

---

## Herramientas de verificación

```powershell
# Ping
Invoke-RestMethod http://127.0.0.1:8000/api/ping

# Login y token
$login = Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8000/api/v1/admin/auth/login `
  -ContentType 'application/json' -Body '{"email":"admin@everly.local","password":"Everly-Dev-2026!"}'
$h = @{ Authorization = "Bearer $($login.data.token)" }

# Listado público (no debe contener costo/margen)
Invoke-RestMethod http://127.0.0.1:8000/api/v1/store/products

# Listado admin (protegido)
Invoke-RestMethod -Headers $h http://127.0.0.1:8000/api/v1/admin/productos

# Reserva, liberación y venta
Invoke-RestMethod -Method Post -Headers $h -Uri http://127.0.0.1:8000/api/v1/admin/inventario/reservar `
  -ContentType 'application/json' -Body '{"producto_id":1}'
Invoke-RestMethod -Method Post -Headers $h -Uri http://127.0.0.1:8000/api/v1/admin/inventario/liberar `
  -ContentType 'application/json' -Body '{"producto_id":1}'
Invoke-RestMethod -Method Post -Headers $h -Uri http://127.0.0.1:8000/api/v1/admin/inventario/vender `
  -ContentType 'application/json' -Body '{"producto_id":1,"cliente_id":1}'

# Historial de una prenda
Invoke-RestMethod -Headers $h http://127.0.0.1:8000/api/v1/admin/productos/1/historial
```

---

## Ejecución de pruebas

```powershell
cd backend
php artisan test   # 45 tests (163 aserciones) contra MySQL everly_test
```
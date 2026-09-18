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
  "imagenes": [
    { "id": 1, "url": "http://localhost:8000/storage/productos/EV-0100-1.png", "es_principal": true, "orden": 1 }
  ]
}
```

> Las imágenes públicas **nunca** exponen `ruta`, `nombre_original` ni fechas; solo `id`, `url`, `es_principal` y `orden` (ordenadas: principal primero).

### `GET /api/v1/store/products/{id}`

Detalle de un producto **publicado y disponible**. `404` si el producto no existe, no está publicado o no está disponible.

### `GET /api/v1/store/categories` · `GET /api/v1/store/sizes`

Listados públicos de categorías activas y tallas. Sin paginar.

### `GET /api/v1/store/metodos-entrega` · `GET /api/v1/store/metodos-pago`

Métodos activos para el checkout. Entrega: `id`, `nombre`, `costo`. Pago: `id`, `nombre`.

### `POST /api/v1/store/pedidos` — Checkout

Crea el pedido desde el carrito del cliente. **Público** (sin token).

```json
{
  "productos": [3, 7],
  "nombre": "Lucía Fernández",
  "telefono": "70123456",
  "email": "lucia@example.com",
  "ciudad": "Santa Cruz",
  "direccion": "Calle 3 #45, Barrio Las Palmas",
  "notas": "Tocar el timbre dos veces",
  "metodo_pago_id": 1,
  "metodo_entrega_id": 2
}
```

Reglas:
- Precios y totales **siempre** se calculan en el servidor (el cliente nunca los envía — se ignoran).
- `productos` admite 1..20 ids, **sin repetidos** (`422` si se duplican).
- El pedido se crea en estado `pendiente` y cada prenda pasa a `reservada` (con reserva activa y vencimiento según `duracion_reserva_horas`, default 24 h).
- **Concurrencia:** cada pieza única se bloquea con `SELECT ... FOR UPDATE` dentro de la transacción; si ya no está `disponible` (vendida o reservada por otro pedido), responde `409` y no se crea nada.

Respuesta `201` (`PedidoPublicoResource`): `numero_pedido`, `estado`, `subtotal`, `costo_envio`, `total`, `fecha_pedido`, `metodo_pago`, `metodo_entrega`, `cliente` (nombre/telefono/email/ciudad/direccion) e `items` (código, nombre, talla, color, imagen, precio histórico). No expone costos ni datos administrativos.

**Registro automático del cliente:** al crear el pedido, el cliente se identifica/actualiza por `telefono` (un solo cliente por número). Se guardan la ciudad, dirección, observaciones y las fechas `fecha_primer_pedido` / `fecha_ultimo_pedido`.

### `GET /api/v1/store/pedidos/{numero_pedido}` — Seguimiento público

Consulta el estado de un pedido por su número (p. ej. `PED-20260916-A2LX`). **Público** (sin token). Devuelve el mismo `PedidoPublicoResource` que el checkout (estado, totales, métodos, items y datos básicos del cliente del pedido consultado).

- `404` + `{ "success": false }` si el número no existe.
- No expone datos administrativos (costos, `pedidos_count`, `total_comprado`, historial ni datos de otros pedidos/clientes).

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

### Imágenes de producto

Cada producto puede tener una o varias imágenes y **máximo una imagen principal**. Los archivos se guardan en el disco `public` de Laravel (`storage/app/public/productos/{id}/...`); en la base de datos solo se persiste la ruta relativa (listo para migrar a S3 cambiando un solo disco). Las imágenes se listan siempre con la principal primero y luego por `orden`.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/productos/{id}/imagenes` | Listar imágenes del producto (principal primero) |
| POST | `/api/v1/admin/productos/{id}/imagenes` | Subir imagen(es) (201); campo `imagen` (1) o `imagenes[]` (hasta 5 por petición) |
| PUT | `/api/v1/admin/productos/{id}/imagenes/{imagenId}/principal` | Establecer imagen principal (máximo una por producto) |
| PUT | `/api/v1/admin/productos/{id}/imagenes/orden` | Reordenar por secuencia de ids |
| PUT | `/api/v1/admin/productos/{id}/imagenes/{imagenId}` | Reemplazar el archivo físico (campo `imagen`) |
| DELETE | `/api/v1/admin/productos/{id}/imagenes/{imagenId}` | Eliminar imagen (registro + archivo físico) |

**Validación de archivos (Laravel, no solo Angular):**

| Regla | Valor |
|---|---|
| MIME | imagen válida (`image`) |
| Extensiones | `jpeg, jpg, png, webp, gif` |
| Tamaño máximo | 4 MB por archivo |
| Existencia del producto | verificada previamente (404 si no existe) |

**Reordenar:** `PUT .../imagenes/orden` con `{ "ordenes": [id4, id3, id2, id1] }`. La secuencia debe contener exactamente las imágenes del producto; el primero pasa a orden 1, el segundo a 2, etc.

**Principal:** al marcar una imagen como principal, la anterior deja de serlo (operación transaccional, nunca quedan dos principales). Al eliminar la imagen principal, **la siguiente por orden pasa a ser principal automáticamente**; si no quedan imágenes, el producto queda sin principal.

**Seguridad:** todas las operaciones exigen `Authorization: Bearer <token>` y rol `admin`/`super_admin`. Una imagen que no pertenezca al producto indicado devuelve `404`. Al eliminar, primero se borra el registro y luego el archivo físico (no se dejan archivos huérfanos). Al eliminar un producto (borrado seguro), sus archivos de imagen también se eliminan.

**Ejemplo de subida (PowerShell/curl):**

```powershell
curl.exe -X POST "http://127.0.0.1:8000/api/v1/admin/productos/1/imagenes" `
  -H "Authorization: Bearer $token" -F "imagen=@foto.png"

# Varias imágenes:
curl.exe -X POST "http://127.0.0.1:8000/api/v1/admin/productos/1/imagenes" `
  -H "Authorization: Bearer $token" -F "imagenes[]=@a.png" -F "imagenes[]=@b.png"
```

Respuesta de un item (admin):

```json
{
  "id": 5,
  "producto_id": 1,
  "ruta": "productos/1/uuid.png",
  "url": "http://localhost:8000/storage/productos/1/uuid.png",
  "nombre_original": "foto.png",
  "es_principal": false,
  "orden": 3,
  "created_at": "2026-09-16T06:24:04.000000Z",
  "updated_at": "2026-09-16T06:24:04.000000Z"
}
```

### Listado de productos (filtros)

**Filtros del listado de productos:**

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

Reglas: `codigo`, `nombre`, `categoria_id`, `talla_id`, `costo`, `precio`, `estado`, `publicado` obligatorios; `costo`/`precio` decimales ≥ 0; al crear `estado` ∈ `disponible` (única vía de cambio: operaciones de inventario); `categoria_id`/`talla_id` deben existir.

### Resumen de inventario

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

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/clientes` | Listado paginado con filtro `busqueda` y agregados por cliente (ver abajo) |
| POST | `/api/v1/admin/clientes` | Crear cliente (admite `{ nombre, telefono, email, direccion, ciudad, notas }`) |
| GET | `/api/v1/admin/clientes/{id}` | Detalle con historial de pedidos completo |
| PUT | `/api/v1/admin/clientes/{id}` | Actualizar cliente |
| DELETE | `/api/v1/admin/clientes/{id}` | Eliminar (409 si tiene pedidos asociados) |

**Listado:** cada item incluye `pedidos_count`, `total_comprado` (suma de pedidos no cancelados) y `ultimo_pedido` (`numero_pedido`, `estado`, `total`, `fecha_pedido`). Orden: por `fecha_ultimo_pedido` descendente (clientes sin pedidos al final).

**Filtro `busqueda`:** coincide con nombre, email, teléfono **o número de pedido** del cliente.

**Detalle (`GET .../{id}`):** añade `fecha_primer_pedido`, `fecha_ultimo_pedido`, `notas` (observaciones) y `pedidos` = historial completo ordenado descendente, cada uno con sus `items` (producto, talla, color, imagen y `precio_unitario` histórico pagado). La identidad se mantiene por `telefono` (creada automáticamente en el checkout).

### Ventas

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/ventas` | Listado paginado con filtros (ver abajo) |
| GET | `/api/v1/admin/ventas/{id}` | Detalle (cliente, items, pagos y pedido asociado) |

**Estado de la venta (derivado, no almacenado):** se calcula desde los pagos `completados` de la venta:

| Estado | Condición |
|---|---|
| `pendiente` | Sin pagos completados |
| `parcial` | Pago(s) completados por menos del total |
| `pagada` | Pagos completados ≥ total |

Los pagos anulados no cuentan. Cada item incluye además `total_pagado`.

**Filtros del listado:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `estado` | string | `pendiente`, `parcial` o `pagada` |
| `fecha_desde` / `fecha_hasta` | date (`YYYY-MM-DD`) | Rango de `fecha_venta` |
| `producto_id` | int | Filtrar por prenda vendida |
| `categoria_id` | int | Filtrar por categoría de la prenda |
| `busqueda` | string | Coincidencia en número de venta o nombre del cliente |
| `per_page` | int | Paginación (default 15) |

### Pedidos

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/pedidos` | Listado paginado con filtros (ver abajo) |
| GET | `/api/v1/admin/pedidos/{id}` | Detalle (cliente, items, métodos, venta asociada) |
| PUT | `/api/v1/admin/pedidos/{id}/estado` | Cambia el estado aplicando los efectos sobre inventario |

**Ciclo de vida del pedido** (máquina de estados centralizada en `Pedido::TRANSICIONES`):

| Estado | Transiciones válidas | Efecto sobre inventario |
|---|---|---|
| `pendiente` | → `confirmado`, → `cancelado` | Inicio: prendas `reservada` |
| `confirmado` | → `cancelado`, → `completado` | Sin cambios (prendas siguen reservadas) |
| `cancelado` | — (final) | Libera reservas activas; prendas vuelven a `disponible` |
| `completado` | — (final) | Crea la `Venta`, reservas `completada`, prendas `vendida` (se despublican) |

`PUT .../estado` recibe `{ "estado": "confirmado" | "cancelado" | "completado" }`. Transiciones inválidas o prendas no reservadas para el pedido → `409`. Al `completar` se usa el **precio histórico** guardado en `pedido_items`.

Filtros del listado:

| Parámetro | Tipo | Descripción |
|---|---|---|
| `estado` | string | `pendiente`, `confirmado`, `cancelado` o `completado` |
| `fecha_desde` / `fecha_hasta` | date (`YYYY-MM-DD`) | Rango de `fecha_pedido` |
| `busqueda` | string | Número de pedido, nombre o teléfono del cliente |
| `per_page` | int | Paginación (default 15) |

### Pagos

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/pagos` | Listado paginado con filtros (ver abajo) |
| GET | `/api/v1/admin/pagos/{id}` | Detalle con resumen financiero y historial del pedido |
| POST | `/api/v1/admin/pagos` | Registrar un pago de un pedido (con o sin comprobante) |
| POST | `/api/v1/admin/pagos/{id}/comprobante` | Adjuntar/reemplazar el comprobante de un pago |
| POST | `/api/v1/admin/pagos/{id}/confirmar` | Confirmar un pago `pendiente` (a verificar) |
| POST | `/api/v1/admin/pagos/{id}/anular` | Anular (rechazar) un pago; no cuenta para saldo ni caja |
| GET | `/api/v1/admin/metodos-pago` | Métodos de pago activos (para el formulario) |

**Estados del pago** (`pagos.estado`, independiente del estado del pedido):

| Estado | Significado |
|---|---|
| `pendiente` | Registrado a la espera de verificación (con comprobante y/o a revisar) |
| `completado` | Verificado; cuenta para el saldo del pedido y genera el ingreso en `caja` (fija `pagado_en`) |
| `anulado` | Rechazado/eliminado contablemente; no cuenta para saldo y retira cualquier ingreso de caja |

**Estado de pago del pedido (derivado**, expuesto como `estado_pago` en pedidos y pagos): `cancelado` (si el pedido fue cancelado), `pendiente` (sin pagos completados), `parcial` (parcialmente pagado) o `pagado` (pagos completados ≥ total).

**Registro (`POST /api/v1/admin/pagos`):**

| Campo | Tipo | Regla |
|---|---|---|
| `pedido_id` | int | Pedido existente; no puede estar `cancelado` |
| `monto` | decimal | `> 0`. No puede superar el **saldo pendiente** (total − pagos `completados`) salvo que se envíe `permitir_excedente: true` |
| `metodo_pago_id` | int | Método existente |
| `fecha` | datetime (`Y-m-d\TH:i`) | Opcional; si se omite se usa ahora. Solo aplica si `estado = completado` |
| `estado` | enum | `pendiente` (default) o `completado` (verificado directo: fija `pagado_en` y crea el ingreso de caja) |
| `referencia` | string | Opcional (nº de transferencia, voucher…) |
| `nota` | string | Opcional |
| `comprobante` | file | Opcional. `jpg, jpeg, png, webp, gif, pdf`, máx. 5 MB. Se guarda en disco y solo metadatos en BD |

- El **excedente** se marca con la columna `excedente`; se revalida al confirmar.
- Un pago con comprobante nace `pendiente` para verificación del admin (confirmar/anular).
- La numeración es automática: `PAG-YYMMDD-XXXXXX`.
- **Clave:** registrar pagos no modifica inventario ni el estado del pedido; el ciclo de prendas sigue gobernado por `PUT /pedidos/{id}/estado`.

**Historial:** el detalle `GET /pagos/{id}` expone `pedido.pagos` con la lista completa de pagos del pedido (monto, método, referencia, estado, comprobante) para auditar la trayectoria de cobro.

Filtros del listado:

| Parámetro | Tipo | Descripción |
|---|---|---|
| `estado` | string | `pendiente`, `completado` o `anulado` |
| `metodo_pago_id` | int | Filtra por método de pago |
| `fecha_desde` / `fecha_hasta` | date (`YYYY-MM-DD`) | Rango de `created_at` |
| `busqueda` | string | Nº de pago, referencia, nº de pedido, nombre/teléfono del cliente o nº de venta |
| `per_page` | int | Paginación (default 15) |

Cada item agrega `pedido.total`, `pedido.total_pagado`, `pedido.saldo_pendiente` y `pedido.estado_pago`.

### Dashboard

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/dashboard` | Resumen agregado para el panel: `productos` (totales, públicos y 8 recientes), `ventas` (total, monto y 8 recientes), `pedidos`, `clientes` (total, con_pedidos, nuevos 30 días, recurrentes ≥2 pedidos, pedidos_por_cliente), `pagos`, `caja` y `inventario.por_estado/por_categoria/por_talla` |

El bloque `pagos` del dashboard: `total_vendido` (suma de ventas), `total_cobrado` (suma de pagos `completados`), `total_pendiente` (max(0, vendido − cobrado)) y `pedidos.{pendientes_de_pago, parcialmente_pagados, pagados}`.

### Otras lecturas

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/admin/pedidos` · `/pedidos/{id}` · `PUT /pedidos/{id}/estado` | Gestión completa de pedidos (ver sección Pedidos) |
| GET | `/api/v1/admin/clientes` · `/clientes/{id}` | Clientes con historial de compras y agregados (ver sección Clientes) |
| GET | `/api/v1/store/pedidos/{numero_pedido}` | Seguimiento público de pedido (tienda) |
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

# Checkout de tienda (crea pedido pendiente y reserva las prendas)
$checkout = @{
  productos = @(2, 3)
  nombre = 'Prueba'
  telefono = '70123456'
  ciudad = 'Santa Cruz'
  direccion = 'Calle 1 #2'
  metodo_pago_id = 1
  metodo_entrega_id = 1
}
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8000/api/v1/store/pedidos `
  -ContentType 'application/json' -Body ($checkout | ConvertTo-Json)

# Cambiar estado de un pedido (admin)
Invoke-RestMethod -Method Put -Headers $h -Uri http://127.0.0.1:8000/api/v1/admin/pedidos/1/estado `
  -ContentType 'application/json' -Body '{"estado":"confirmado"}'

# Clientes con agregados y detalle con historial
Invoke-RestMethod -Headers $h 'http://127.0.0.1:8000/api/v1/admin/clientes?busqueda=70123456'
Invoke-RestMethod -Headers $h http://127.0.0.1:8000/api/v1/admin/clientes/1

# Seguimiento público de un pedido por número
Invoke-RestMethod http://127.0.0.1:8000/api/v1/store/pedidos/PED-20260916-A2LX

# Pagos: registrar, verificar y auditar
Invoke-RestMethod -Method Post -Headers $h -Uri http://127.0.0.1:8000/api/v1/admin/pagos `
  -ContentType 'application/json' -Body '{"pedido_id":1,"monto":115,"metodo_pago_id":1,"estado":"completado"}'
Invoke-RestMethod -Method Post -Headers $h 'http://127.0.0.1:8000/api/v1/admin/pagos/1/confirmar'
Invoke-RestMethod -Headers $h 'http://127.0.0.1:8000/api/v1/admin/pagos?estado=pendiente&busqueda=70123456'
Invoke-RestMethod -Headers $h http://127.0.0.1:8000/api/v1/admin/pagos/1
```

---

## Ejecución de pruebas

```powershell
cd backend
php artisan test   # 126 tests (626 aserciones) contra MySQL everly_test
```
# Auditoría CRUD — Panel Admin

Fecha: 2026-11-11 · Backend: http://127.0.0.1:8000 (env=audit, BD `everly_audit`, copia de prueba de `everly`) · Autenticación: Sanctum (probe@everly.local, super_admin, usuario de prueba).

Estado por módulo (C=Crear, L=Listar, E=Editar, D=Eliminar):

| Módulo | Crear | Listar | Editar | Eliminar | Error corregido (archivo y línea) |
|---|---|---|---|---|---|
| Usuarios | OK | OK | OK | OK (desactiva, por diseño) | — |
| Categorías | OK | OK | OK (POST, por diseño putFormData) | OK | — |
| Tallas | OK | OK | OK | OK | — |
| Marcas | OK | OK | OK (POST, por diseño putFormData) | OK | — |
| Condiciones | OK | OK | OK | OK | — |
| Productos | OK | OK | OK | OK | — (fix previo: admin\src\app\pages\productos\producto-form-modal.ts:151 envía estado:'disponible' al crear) |
| Clientes | OK | OK | OK | OK | — (bloqueo de teléfono/email duplicado es intencional) |
| Proveedores | OK | OK | OK | OK | — |
| Compras | OK | OK | OK | OK (más anular) | 1) `costo_asignado: null` rechazado por `numeric` sin `nullable` — corregido en backend\app\Http\Requests\V1\Admin\Compra\CompraStoreRequest.php:21 y CompraUpdateRequest.php:21. 2) Anular/eliminar compra dejaba egreso huérfano en caja — corregido en backend\app\Services\CompraService.php:265 (anular) y :296 (eliminar). |
| Cupones | OK | OK | OK | OK | — |
| Banners | OK | OK | OK (POST, por diseño putFormData) | OK (borra imagen del storage) | — |
| Ofertas | OK | OK | OK | OK | — |
| Ventas | OK (presencial) | OK | N/A (no editable por diseño) | OK (anular: devuelve prendas, reembolsa pagos y registra egreso) | — |
| Reservas | OK | OK | N/A (ops: convertir/liberar) | OK (liberar devuelve la prenda) | — |
| Devoluciones | OK (pendiente) | OK | N/A (aprobar/rechazar) | OK (aprobar devuelve la prenda al inventario) | — |
| Pagos | OK (store pendiente) | OK | N/A (confirmar/anular) | OK (confirmar + anular) | — |
| Caja | OK (movimientos ajuste) | OK | N/A | OK (borra ajuste) | — |
| Gastos | OK | OK | N/A | OK (borra gasto y su movimiento en caja) | — |
| Envíos | N/A (se crea con el pedido) | OK | OK (estado + datos) | N/A (no borrar) | — |
| Pedidos | N/A (se genera desde venta/reserva) | OK | OK (estado: cancelado probado; completado requiere prenda reservada, sin datos de prueba) | N/A (no borrar) | — |
| Configuración | N/A (config singleton) | OK | OK (tienda POST, métodos de pago/entrega PUT) | N/A | — |
| Inventario (solo lectura) | N/A | OK (resumen + listado) | N/A | N/A | — |
| Dashboard / Reportes (solo lectura) | N/A | OK (resumen, dashboard y demás reportes) | N/A | N/A | — |

# Everly

Sistema de comercio electrónico para una boutique de moda. Cada prenda física es una **unidad única** con su propio código de inventario (por ejemplo, `EV-0001`), lo que obliga a controlar reservas y ventas de forma individual para evitar dobles ventas.

## Aplicaciones

| Carpeta | Aplicación | Descripción |
|---|---|---|
| `backend/` | Laravel + REST API | Núcleo central: productos, inventario, pedidos, clientes, ventas, caja, gastos y reportes. Motor: PHP 8.2, Laravel 12, MySQL/MariaDB. |
| `tienda/` | Angular | Storefront público: catálogo, filtros, carrito y checkout. Punto de acceso: `http://localhost:4200`. |
| `admin/` | Angular | Sistema administrativo privado: gestión de productos, pedidos, caja y reportes. Punto de acceso: `http://localhost:4300`. |

## Arquitectura

```
                  ┌────────────────────┐
                  │      MySQL         │
                  │   (base everly)    │
                  └─────────┬──────────┘
                            │
                  ┌─────────▼──────────┐
                  │  backend (Laravel) │  REST API  /api/v1
                  └────┬──────────┬────┘
                       │          │
              ┌────────▼─┐    ┌───▼────────┐
              │  tienda  │    │   admin    │
              │ Angular  │    │  Angular   │
              └──────────┘    └────────────┘
```

- Laravel expone la API en `/api/v1/` y sirve como único punto de acceso a los datos.
- `tienda` y `admin` son SPA Angular independientes que consumen la misma API.
- La API pública nunca expone costos, ganancias ni datos administrativos; el panel admin usa autenticación por tokens (Laravel Sanctum).
- CORS permitido únicamente para los orígenes de `tienda` y `admin`.

## Tecnologías

- **Backend:** PHP 8.2, Laravel 12, Laravel Sanctum, MySQL/MariaDB.
- **Tienda y Admin:** Angular 22 (standalone, SCSS, sin SSR), RxJS.
- **Herramientas:** Git, VS Code, Composer, npm, Laragon/XAMPP, MySQL Workbench, Postman.

## Estructura de carpetas

```
everly/
├── backend/            # API Laravel
│   ├── app/
│   ├── bootstrap/      # configuración middleware y excepciones
│   ├── config/         # cors.php, sanctum.php, etc.
│   ├── database/
│   ├── routes/         # api.php (endpoints REST)
│   └── ...
├── tienda/             # Angular - tienda online
│   └── src/
│       ├── app/
│       │   ├── core/           # servicios, interceptores, modelos, guards
│       │   └── pages/          # home, 404
│       └── environments/       # environments.ts / development.ts
├── admin/              # Angular - panel administrativo
│   └── src/
│       ├── app/
│       │   ├── core/           # auth, token, interceptores, guards
│       │   └── pages/          # login, dashboard, 404
│       └── environments/
└── README.md
```

## Requisitos

- PHP >= 8.2 con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`.
- Composer >= 2.
- Node.js >= 20 y npm >= 10.
- MySQL o MariaDB (probado con MariaDB 10.4).
- Git.

## Instalación

### 1. Backend (Laravel)

```bash
cd backend
copy .env.example .env        # Windows
composer install
php artisan key:generate
```

Configurar la conexión en `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=everly
DB_USERNAME=root
DB_PASSWORD=
```

Crear la base de datos (si no existe) y ejecutar migraciones:

```sql
CREATE DATABASE everly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate
php artisan serve --port=8000
```

Verificación: `http://localhost:8000/api/ping` debe responder `{"success": true, "message": "pong", "version": "v1"}`.

### 2. Tienda (Angular)

```bash
cd tienda
npm install
npm run start
```

Acceso: `http://localhost:4200` (la ruta raíz muestra el estado de conexión con el backend).

### 3. Admin (Angular)

```bash
cd admin
npm install
npm run start
```

Acceso: `http://localhost:4300` (la ruta `/login` será el punto de entrada de la autenticación en fases posteriores).

## Puertos y URLs

| Servicio | URL | Puerto |
|---|---|---|
| Backend (Laravel) | http://localhost:8000 | 8000 |
| Tienda (Angular) | http://localhost:4200 | 4200 |
| Admin (Angular) | http://localhost:4300 | 4300 |
| MySQL (MariaDB) | localhost | 3306 |

## Enlaces rápidos de la API

- `GET /api/ping` — verificación de salud del backend.

Los módulos funcionales (categorías, tallas, productos, pedidos, ventas, caja, etc.) se implementarán en fases posteriores.
# AI Coding Guidelines for EPESPO Inventory Management System

## Architecture Overview
This is a Laravel 12 backend for managing institutional inventory (EPESPO). Core entities: Products (Producto), Departments (Departamento), Users (Usuario), Assignments (Asignacion), Movements (Movimiento), Acts (Acta), Receptions (Recepcion).

## Authentication & Authorization
- **JWT Auth**: Use `tymon/jwt-auth` with custom fields: `contrasena` (password), `correo` (email).
- **User Model**: Extends Authenticatable, implements JWTSubject. Override `getAuthPassword()`, `getEmailForPasswordReset()`.
- **Roles**: 'admin', 'lector'. Use `middleware('role:admin,lector')` in routes.
- **Password Reset**: Custom notification `ResetPasswordFrontendNotification`.

## Data Models & Relationships
- **Producto**: Belongs to Departamento (ubicacion). Code format: E-EC-YYYY-NNN (prefix based on category).
- **Asignacion**: Belongs to Responsable, Departamento (area), Acta. Many-to-many with Producto via pivot.
- **Movimiento**: Logging table, belongs to Usuario. No timestamps, manual `fecha`.
- **Acta**: Generated from assignments/receptions, stores PDF path.

## Key Patterns
- **Movement Logging**: Use global helper `registrarMovimiento($accion, $descripcion)` to log user actions.
- **Code Generation**: Products auto-generate codes with prefixes (e.g., 'Equipo de Computo' → 'E-EC').
- **Validation**: Categories restricted to ['Equipo de Computo', 'Equipo de Oficina', 'Muebles y Enseres', 'Instalaciones, Maquinarias y Herramientas'].
- **File Exports**: Use `mpdf` for PDFs, `phpword` for Word documents in ActaController.

## Workflows
- **Setup**: Run `composer run-script setup` for initial setup (install, migrate, build).
- **Testing**: Use PHPUnit, run `php artisan test`.
- **Migrations**: Custom table names (e.g., 'usuarios', 'asignaciones').
- **API Routes**: JWT-protected, role-based access. Public: /register, /login, /forgot-password, /reset-password.

## Conventions
- **Field Names**: Spanish (contrasena, correo, nombre) instead of English.
- **Helpers**: Autoloaded in composer.json, e.g., `RegistroMovimiento.php`.
- **Controllers**: Namespace `App\Http\Controllers\Api` for API endpoints.
- **Storage**: PDFs stored in `storage/app/actas/`.

Reference: `app/Models/`, `routes/api.php`, `app/Helpers/RegistroMovimiento.php`.
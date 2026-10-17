# Hospital Sanitas: Historial Médico Electrónico

PHP 8.2 (MVC propio, sin Composer) + MySQL/MariaDB + Apache (XAMPP).

## Instalación

1. Copiar el proyecto a `htdocs/historialmedico` y activar `mod_rewrite` (`AllowOverride All`).
2. Iniciar Apache y MySQL.
3. `copy .env.example .env` y completar los valores (base de datos, `APP_KEY`, contraseñas de demostración).
4. `php database/install.php` crea la base, las tablas, los datos de referencia y la demostración.
   - `--fresh` recrea la base; `--sin-demo` omite los datos de ejemplo.
5. Opcional: `php database/seed_historial_demo.php` agrega 12 meses de actividad ficticia.
6. Abrir `http://localhost/historialmedico/`.

Las migraciones nuevas se aplican con `php database/migrate.php`. Pruebas: `php tests/run.php` (reinstala la base).

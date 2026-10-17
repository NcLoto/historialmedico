# Hospital Sanitas: Historial Médico Electrónico

Sistema web para administrar el historial médico de los pacientes de un hospital: antecedentes, alergias, enfermedades, cirugías, vacunas, medicamentos, consultas con signos vitales y diagnósticos CIE-10, y documentos (exámenes e imágenes). Tiene tres tipos de usuario (paciente, médico y administrador), permisos validados en el servidor y auditoría de cada acción.

> Todos los datos de demostración son **ficticios**.

## Contenido

- [Qué incluye](#qué-incluye)
- [Tecnologías](#tecnologías)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Cuentas de demostración](#cuentas-de-demostración)
- [Configuración del `.env`](#configuración-del-env)
- [Correo y tarea programada](#correo-y-tarea-programada)
- [Roles y permisos](#roles-y-permisos)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Pruebas](#pruebas)
- [Problemas frecuentes](#problemas-frecuentes)

## Qué incluye

- **Historial clínico** del paciente, con alertas (alergias graves, condiciones y medicación vigente) y vista cronológica.
- **Consultas** con signos vitales, diagnósticos CIE-10, tratamientos, plan y notas internas (no visibles para el paciente).
- **Documentos** adjuntos protegidos: el tipo real del archivo se verifica siempre y se guarda su huella SHA-256.
- **Impresos en PDF** con el diseño de la institución: resumen clínico, informe de consulta, receta, constancia de atención, carné de vacunación (formato A6) y reportes. Cada impreso lleva un código de verificación que se comprueba en `/verificar` sin mostrar contenido clínico.
- **Dashboards** distintos por rol (administrador, médico y paciente).
- **Reportes** por periodo con vista previa y descarga en PDF o CSV.
- **Notificaciones** dentro del sistema y por correo electrónico, con preferencias por usuario.
- **Configuración** desde la propia interfaz: datos de la institución, apariencia (tema claro u oscuro, color de acento, estilo cristal, imagen de fondo, logo, favicon y color de la barra lateral), diseño de documentos, notificaciones, correo y seguridad.
- **Seguridad:** contraseñas con hash, bloqueo por intentos fallidos, contraseña temporal que obliga a definir una nueva al ingresar, CSRF en todos los formularios, política CSP estricta y auditoría que registra qué campos cambiaron, nunca sus valores.

## Tecnologías

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.2, MVC propio, sin framework ni Composer |
| Base de datos | MySQL o MariaDB, con PDO y sentencias preparadas |
| Frontend | HTML, CSS y JavaScript propios, sin librerías |
| Servidor | Apache con `mod_rewrite` (pensado para XAMPP) |
| PDF | Generador propio, sin dependencias |

Las tipografías (Poppins, Open Sans e IBM Plex Sans) se cargan desde Google Fonts, así que hace falta internet; sin conexión el sistema funciona con tipografías del sistema.

## Requisitos

- XAMPP (o equivalente) con **PHP 8.1 o superior** y las extensiones `pdo_mysql`, `mbstring`, `fileinfo` y `gd`.
- **MySQL 5.7+ o MariaDB 10.4+**.
- Apache con `mod_rewrite` y `AllowOverride All` en la carpeta del proyecto.
- Para las pruebas automáticas: extensión `curl` de PHP.

## Instalación

1. **Clonar** el repositorio dentro de `htdocs`, de modo que quede en `C:\xampp\htdocs\historialmedico`:
   ```
   git clone https://github.com/NcLoto/historialmedico.git
   ```
   Si lo coloca en otra carpeta, cambie `APP_BASE_PATH` y `APP_URL` en el paso 3.
2. **Iniciar** Apache y MySQL desde el panel de XAMPP.
3. **Crear el archivo de configuración:**
   ```
   copy .env.example .env
   ```
   Abra `.env` y revise al menos `DB_USERNAME` y `DB_PASSWORD` (en XAMPP, usuario `root` y contraseña vacía), y genere la clave `APP_KEY`:
   ```
   php -r "echo bin2hex(random_bytes(32));"
   ```
4. **Instalar la base de datos.** Con PHP de XAMPP (`C:\xampp\php\php.exe` si no está en el PATH):
   ```
   php database/install.php
   ```
   Crea la base, las tablas, los roles y permisos, los catálogos, la cuenta de administrador y los datos de demostración.
   - `--fresh` borra y recrea la base desde cero.
   - `--sin-demo` instala solo lo necesario: catálogos y cuenta de administrador.
   - Si `ADMIN_PASSWORD` o `DEMO_PASSWORD` están vacíos en el `.env`, el instalador genera contraseñas al azar y las muestra **una sola vez** al terminar. Anótelas.
5. **Opcional, para demostraciones:** agrega 12 meses de actividad ficticia (unos 120 pacientes, consultas, diagnósticos y documentos) para que dashboards y reportes se vean con datos:
   ```
   php database/seed_historial_demo.php
   ```
   Se ejecuta una sola vez (`--forzar` agrega otro lote, `--pacientes=N` cambia el tamaño).
6. **Abrir** `http://localhost/historialmedico/` e iniciar sesión.

El logo, el favicon y la imagen de fondo no vienen en el repositorio: se cargan desde **Configuración → Apariencia**. Mientras no haya logo se muestra una cruz por defecto.

### Actualizar una instalación existente

Después de `git pull`, aplique las migraciones nuevas:
```
php database/migrate.php
```

## Cuentas de demostración

Todas las cuentas de demostración comparten la contraseña `DEMO_PASSWORD`. La del administrador es `ADMIN_PASSWORD`.

| Rol | Correo |
|-----|--------|
| Administrador | `admin@sanitas.local` (o el valor de `ADMIN_EMAIL`) |
| Médicos | `andres.ovalle@sanitas.local`, `lucia.merida@sanitas.local`, `rodrigo.pineda@sanitas.local`, `marta.estrada@sanitas.local` |
| Pacientes | `carlos.ramirez@correo.test`, `mariajose.gomez@correo.test`, `pedro.tzul@correo.test`, `ana.barrios@correo.test`, `roberto.cifuentes@correo.test`, `gabriela.lopez@correo.test` |

## Configuración del `.env`

El archivo `.env` no se versiona. Cada compañero crea el suyo a partir de `.env.example`.

| Variable | Para qué sirve |
|----------|----------------|
| `APP_BASE_PATH` | Prefijo de la URL (`/historialmedico`). Vacío si el sistema está en la raíz del dominio. |
| `APP_DEBUG` | `true` solo en desarrollo: muestra los errores en pantalla. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión a MySQL o MariaDB. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Cuenta de administrador que crea el instalador. |
| `DEMO_PASSWORD` | Contraseña de las cuentas de demostración. |
| `APP_KEY` | Secreto para firmar los enlaces de baja de los correos. |
| `APP_URL` | URL pública del sistema, usada en los enlaces de los correos. |
| `MAIL_*` | Envío de correo (ver la sección siguiente). |

## Correo y tarea programada

Los avisos pueden salir también por correo (citas, recordatorios, documentos nuevos, alertas y recuperación de contraseña). Por privacidad, los correos **no incluyen información clínica**: avisan y enlazan al sistema.

- `MAIL_DRIVER=archivo` (valor por defecto): no envía nada, guarda cada correo como archivo en `storage/mail`. Es lo recomendado para desarrollo.
- `MAIL_DRIVER=smtp`: envío real; complete `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD` y `MAIL_FROM`. En Gmail o Microsoft 365 use una contraseña de aplicación.
- `MAIL_DRIVER=desactivado`: sin correo.

Los correos se guardan en una cola y los envía `bin/tareas.php`, que también genera los recordatorios de consultas. Para ejecutarla cada 5 minutos en Windows:
```
schtasks /Create /TN "Historial Medico - tareas" /SC MINUTE /MO 5 /TR "\"C:\xampp\htdocs\historialmedico\bin\tareas.bat\"" /F
```
En Linux: `*/5 * * * * php /ruta/al/proyecto/bin/tareas.php`. Sin la tarea, **Configuración → Correo** lo advierte y permite usar «Procesar ahora».

## Roles y permisos

| Rol | Acceso |
|-----|--------|
| Paciente | Solo su propia ficha, consultas y documentos, en lectura. No ve las notas internas. |
| Médico | Pacientes, historial clínico, consultas y documentos. |
| Administrador | Pacientes (datos personales), médicos, usuarios, roles y permisos, auditoría, configuración y reportes. **Sin acceso al contenido clínico.** |

Los permisos (`modulo.accion`) se asignan por rol en **Roles y permisos** y se verifican en cada ruta del servidor; ocultar un menú nunca reemplaza esa validación. Las rutas y el permiso que exigen están en `config/routes.php`.

## Estructura del proyecto

```
app/
  Core/           Router, Auth, Policy, Csrf, Validator, Upload, Audit, Mailer, Pdf, View…
  Controllers/    un controlador por módulo
  Repositories/   consultas SQL
  Domain/         reglas del negocio (historial, documentos, impresos, correo, notificaciones)
  Views/          vistas y parciales
bin/              tarea programada (tareas.php, tareas.bat)
config/           rutas y permisos (routes.php)
database/         schema.sql, seed*.sql, migraciones, install.php, migrate.php
public/           único directorio expuesto: index.php, css, js, imágenes
storage/          documentos, logos, fotos de perfil, correo simulado y registros (sin acceso web)
tests/            pruebas funcionales y de seguridad
```

## Pruebas

```
php tests/run.php
```

Ejecuta cientos de comprobaciones contra el servidor local (Apache debe estar iniciado). **Reinstala la base de datos**, así que cierra las sesiones abiertas y reemplaza los datos actuales; antes guarda una copia de la base y de `storage` en `storage/backups`. Úselo en una instalación de desarrollo, no con datos que quiera conservar.

## Problemas frecuentes

- **Error 404 en todas las páginas:** verifique que `mod_rewrite` esté activo y que Apache permita `AllowOverride All` para la carpeta del proyecto.
- **Los enlaces o estilos se rompen:** `APP_BASE_PATH` no coincide con la carpeta donde está el proyecto.
- **El instalador no conecta:** revise `DB_*` en el `.env` y que MySQL esté iniciado.
- **No se ve el logo o el fondo:** no vienen en el repositorio; cárguelos en Configuración → Apariencia.
- **Olvidó las contraseñas generadas:** vuelva a ejecutar `php database/install.php --fresh` (borra los datos) o restablezca la contraseña desde Usuarios, con una cuenta de administrador.

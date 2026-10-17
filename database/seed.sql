-- Datos de referencia necesarios para operar (roles, permisos, catálogos, configuración).
-- No contiene usuarios ni datos clínicos: ver install.php y seed_demo.sql.

SET NAMES utf8mb4;

INSERT INTO roles (id, codigo, nombre, descripcion) VALUES
  (1, 'administrador', 'Administrador', 'Gestiona cuentas, catálogos, seguridad y auditoría. Sin acceso a contenido clínico.'),
  (2, 'medico',        'Médico',        'Consulta y registra información clínica de los pacientes.'),
  (3, 'paciente',      'Paciente',      'Consulta únicamente su propia información médica.');

INSERT INTO permisos (modulo, accion, descripcion) VALUES
  ('pacientes',     'ver',       'Ver datos personales de pacientes'),
  ('pacientes',     'crear',     'Registrar pacientes'),
  ('pacientes',     'editar',    'Editar datos personales de pacientes'),
  ('pacientes',     'eliminar',  'Inactivar pacientes'),
  ('historial',     'ver',       'Ver historial clínico (antecedentes, alergias, enfermedades, etc.)'),
  ('historial',     'crear',     'Agregar registros al historial clínico'),
  ('historial',     'editar',    'Modificar registros del historial clínico'),
  ('historial',     'eliminar',  'Eliminar registros del historial clínico'),
  ('consultas',     'ver',       'Ver consultas médicas'),
  ('consultas',     'crear',     'Registrar consultas médicas'),
  ('consultas',     'editar',    'Modificar consultas médicas'),
  ('documentos',    'ver',       'Ver listado y datos de documentos médicos'),
  ('documentos',    'crear',     'Cargar documentos médicos'),
  ('documentos',    'descargar', 'Abrir o descargar el contenido de documentos médicos'),
  ('documentos',    'eliminar',  'Eliminar documentos médicos'),
  ('medicos',       'ver',       'Ver médicos y especialidades'),
  ('medicos',       'crear',     'Registrar médicos'),
  ('medicos',       'editar',    'Editar médicos'),
  ('medicos',       'eliminar',  'Inactivar médicos'),
  ('usuarios',      'ver',       'Ver cuentas de usuario'),
  ('usuarios',      'crear',     'Crear cuentas de usuario'),
  ('usuarios',      'editar',    'Editar cuentas de usuario'),
  ('usuarios',      'eliminar',  'Desactivar cuentas de usuario'),
  ('roles',         'ver',       'Ver roles y permisos'),
  ('roles',         'editar',    'Modificar permisos de los roles'),
  ('auditoria',     'ver',       'Consultar la bitácora de auditoría'),
  ('configuracion', 'ver',       'Ver configuración del sistema'),
  ('configuracion', 'editar',    'Modificar configuración del sistema');

-- Administrador
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 1, id FROM permisos WHERE CONCAT(modulo, '.', accion) IN (
  'pacientes.ver','pacientes.crear','pacientes.editar','pacientes.eliminar',
  'documentos.ver','documentos.eliminar',
  'medicos.ver','medicos.crear','medicos.editar','medicos.eliminar',
  'usuarios.ver','usuarios.crear','usuarios.editar','usuarios.eliminar',
  'roles.ver','roles.editar','auditoria.ver','configuracion.ver','configuracion.editar');

-- Médico
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 2, id FROM permisos WHERE CONCAT(modulo, '.', accion) IN (
  'pacientes.ver','pacientes.crear','pacientes.editar',
  'historial.ver','historial.crear','historial.editar','historial.eliminar',
  'consultas.ver','consultas.crear','consultas.editar',
  'documentos.ver','documentos.crear','documentos.descargar');

-- Paciente (siempre limitado a sus propios registros por Policy)
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 3, id FROM permisos WHERE CONCAT(modulo, '.', accion) IN (
  'pacientes.ver','historial.ver','consultas.ver','documentos.ver','documentos.descargar');

INSERT INTO especialidades (nombre, descripcion) VALUES
  ('Medicina general',      'Atención primaria y seguimiento integral'),
  ('Medicina interna',      'Diagnóstico y manejo de enfermedades del adulto'),
  ('Pediatría',             'Atención de niños y adolescentes'),
  ('Ginecología y obstetricia', 'Salud de la mujer y control prenatal'),
  ('Cardiología',           'Enfermedades del corazón y sistema circulatorio'),
  ('Traumatología',         'Lesiones del aparato locomotor'),
  ('Dermatología',          'Enfermedades de la piel'),
  ('Endocrinología',        'Diabetes y trastornos hormonales');

INSERT INTO categorias_documentos (nombre, grupo) VALUES
  ('Laboratorio clínico',        'examen'),
  ('Química sanguínea',          'examen'),
  ('Examen de orina y heces',    'examen'),
  ('Radiografía',                'imagen'),
  ('Ultrasonido',                'imagen'),
  ('Tomografía / Resonancia',    'imagen'),
  ('Electrocardiograma',         'imagen'),
  ('Informe médico',             'documento'),
  ('Receta o indicaciones',      'documento'),
  ('Referencia o epicrisis',     'documento'),
  ('Consentimiento informado',   'documento');

INSERT INTO configuracion (clave, valor, descripcion) VALUES
  ('hospital_nombre',    'Hospital Sanitas',                 'Nombre que aparece en el sistema'),
  ('hospital_direccion', '7a. avenida 12-45, zona 10, Ciudad de Guatemala', 'Dirección institucional'),
  ('hospital_telefono',  '2300-4500',                        'Teléfono de contacto'),
  ('sesion_minutos',     '30',                               'Minutos de inactividad antes de cerrar la sesión'),
  ('archivo_max_mb',     '15',                               'Tamaño máximo por documento, en MB'),
  ('intentos_max',       '5',                                'Intentos de acceso fallidos antes de bloquear la cuenta'),
  ('bloqueo_minutos',    '15',                               'Minutos de bloqueo tras exceder los intentos');

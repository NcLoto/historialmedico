<?php
declare(strict_types=1);

/**
 * [método, patrón, 'Controlador@acción', acceso, opciones?]
 * acceso: 'public' | 'auth' | 'modulo.accion'  (el permiso se valida en el backend en cada solicitud)
 * 'staff': además de tener el permiso, el usuario no puede ser un paciente.
 * Los pacientes acceden solo a sus registros mediante Policy dentro de cada controlador.
 */
return [
    // Autenticación
    ['GET',  '/login',                 'AuthController@loginForm',  'public'],
    ['POST', '/login',                 'AuthController@login',      'public'],
    ['GET',  '/login/cambiar-clave',   'AuthController@changeForm', 'public'],
    ['POST', '/login/cambiar-clave',   'AuthController@change',     'public'],
    ['POST', '/login/cancelar',        'AuthController@cancelChange', 'public'],
    ['POST', '/logout',                'AuthController@logout',     'auth'],
    ['GET',  '/olvide-contrasena',     'AuthController@forgotForm', 'public'],
    ['POST', '/olvide-contrasena',     'AuthController@forgot',     'public'],
    ['GET',  '/restablecer/{token}',   'AuthController@resetForm',  'public'],
    ['POST', '/restablecer/{token}',   'AuthController@reset',      'public'],

    // Inicio y perfil
    ['GET',  '/',                      'DashboardController@index', 'auth'],
    ['GET',  '/perfil',                'PerfilController@show',     'auth'],
    ['GET',  '/perfil/seguridad',      'PerfilController@seguridad', 'auth'],
    ['GET',  '/perfil/notificaciones', 'PerfilController@notificaciones', 'auth'],
    ['GET',  '/perfil/apariencia',     'PerfilController@apariencia', 'auth'],
    ['POST', '/perfil/datos',          'PerfilController@datos',    'auth'],
    ['POST', '/perfil/foto',           'PerfilController@foto',     'auth'],
    ['GET',  '/avatar/{id}',           'PerfilController@avatar',   'auth'],
    ['POST', '/perfil/clave',          'PerfilController@password', 'auth'],
    ['POST', '/perfil/tema',           'PerfilController@theme',    'auth'],

    // Pacientes y ficha médica
    ['GET',  '/mi-historial',          'PacienteController@mine',   'pacientes.ver'],
    ['GET',  '/pacientes',             'PacienteController@index',  'pacientes.ver', ['staff']],
    ['GET',  '/pacientes/exportar.csv',   'ExportController@pacientesCsv', 'pacientes.ver', ['staff']],
    ['GET',  '/pacientes/exportar.pdf',   'ExportController@pacientesPdf', 'pacientes.ver', ['staff']],
    ['GET',  '/pacientes/{id}/exportar.pdf', 'ExportController@pacientePdf', 'historial.ver'],
    ['GET',  '/pacientes/buscar',      'PacienteController@search', 'pacientes.ver', ['staff']],
    ['GET',  '/pacientes/nuevo',       'PacienteController@create', 'pacientes.crear', ['staff']],
    ['POST', '/pacientes',             'PacienteController@store',  'pacientes.crear', ['staff']],
    ['GET',  '/pacientes/{id}',        'PacienteController@show',   'pacientes.ver'],
    ['GET',  '/pacientes/{id}/editar', 'PacienteController@edit',   'pacientes.editar', ['staff']],
    ['POST', '/pacientes/{id}',        'PacienteController@update', 'pacientes.editar', ['staff']],
    ['POST', '/pacientes/{id}/estado', 'PacienteController@toggle', 'pacientes.eliminar', ['staff']],

    // Historial clínico (antecedentes, alergias, enfermedades, cirugías, vacunas, medicamentos, hábitos, familiares)
    ['GET',  '/pacientes/{id}/historial/{sec}/nuevo',                 'HistorialController@create',  'historial.crear',    ['staff']],
    ['POST', '/pacientes/{id}/historial/{sec}',                       'HistorialController@store',   'historial.crear',    ['staff']],
    ['GET',  '/pacientes/{id}/historial/{sec}/{rid}/editar',          'HistorialController@edit',    'historial.editar',   ['staff']],
    ['POST', '/pacientes/{id}/historial/{sec}/{rid}',                 'HistorialController@update',  'historial.editar',   ['staff']],
    ['POST', '/pacientes/{id}/historial/{sec}/{rid}/eliminar',        'HistorialController@destroy', 'historial.eliminar', ['staff']],

    // Consultas
    ['GET',  '/consultas',                    'ConsultaController@index',  'consultas.ver'],
    ['GET',  '/pacientes/{id}/consultas/nueva', 'ConsultaController@create', 'consultas.crear', ['staff']],
    ['POST', '/pacientes/{id}/consultas',     'ConsultaController@store',  'consultas.crear', ['staff']],
    ['GET',  '/consultas/{id}/exportar.pdf', 'ExportController@consultaPdf', 'consultas.ver'],
    ['GET',  '/consultas/{id}',               'ConsultaController@show',   'consultas.ver'],
    ['GET',  '/consultas/{id}/editar',        'ConsultaController@edit',   'consultas.editar', ['staff']],
    ['POST', '/consultas/{id}',               'ConsultaController@update', 'consultas.editar', ['staff']],
    ['POST', '/consultas/{id}/estado',        'ConsultaController@status', 'consultas.editar', ['staff']],

    // Documentos médicos
    ['GET',  '/documentos',                       'DocumentoController@index',   'documentos.ver'],
    ['GET',  '/documentos/exportar.csv',          'ExportController@documentosCsv', 'documentos.ver'],
    ['GET',  '/documentos/exportar.pdf',          'ExportController@documentosPdf', 'documentos.ver'],
    ['GET',  '/documentos/{id}',                  'DocumentoController@show',    'documentos.ver'],
    ['GET',  '/documentos/{id}/archivo',          'DocumentoController@file',    'documentos.descargar'],
    ['GET',  '/pacientes/{id}/documentos/nuevo',  'DocumentoController@create',  'documentos.crear', ['staff']],
    ['POST', '/pacientes/{id}/documentos',        'DocumentoController@store',   'documentos.crear', ['staff']],
    ['POST', '/documentos/{id}/eliminar',         'DocumentoController@destroy', 'documentos.eliminar', ['staff']],

    // Médicos y especialidades
    ['GET',  '/medicos',                'MedicoController@index',   'medicos.ver',    ['staff']],
    ['GET',  '/medicos/exportar.csv',   'ExportController@medicosCsv', 'medicos.ver', ['staff']],
    ['GET',  '/medicos/exportar.pdf',   'ExportController@medicosPdf', 'medicos.ver', ['staff']],
    ['GET',  '/medicos/nuevo',          'MedicoController@create',  'medicos.crear',  ['staff']],
    ['POST', '/medicos',                'MedicoController@store',   'medicos.crear',  ['staff']],
    ['GET',  '/medicos/{id}/editar',    'MedicoController@edit',    'medicos.editar', ['staff']],
    ['POST', '/medicos/{id}',           'MedicoController@update',  'medicos.editar', ['staff']],
    ['POST', '/medicos/{id}/estado',    'MedicoController@toggle',  'medicos.eliminar', ['staff']],
    ['GET',  '/especialidades',         'MedicoController@especialidades',      'medicos.ver',    ['staff']],
    ['POST', '/especialidades',         'MedicoController@especialidadStore',   'medicos.crear',  ['staff']],
    ['POST', '/especialidades/{id}',    'MedicoController@especialidadUpdate',  'medicos.editar', ['staff']],

    // Usuarios
    ['GET',  '/usuarios',               'UsuarioController@index',  'usuarios.ver',    ['staff']],
    ['GET',  '/usuarios/exportar.csv',    'ExportController@usuariosCsv', 'usuarios.ver', ['staff']],
    ['GET',  '/usuarios/exportar.pdf',    'ExportController@usuariosPdf', 'usuarios.ver', ['staff']],
    ['GET',  '/usuarios/nuevo',         'UsuarioController@create', 'usuarios.crear',  ['staff']],
    ['POST', '/usuarios',               'UsuarioController@store',  'usuarios.crear',  ['staff']],
    ['GET',  '/usuarios/{id}/editar',   'UsuarioController@edit',   'usuarios.editar', ['staff']],
    ['POST', '/usuarios/{id}',          'UsuarioController@update', 'usuarios.editar', ['staff']],
    ['POST', '/usuarios/{id}/estado',   'UsuarioController@toggle', 'usuarios.eliminar', ['staff']],
    ['GET',  '/usuarios/{id}/clave',    'UsuarioController@passwordForm',  'usuarios.editar', ['staff']],
    ['POST', '/usuarios/{id}/clave',    'UsuarioController@resetPassword', 'usuarios.editar', ['staff']],

    // Roles y permisos
    ['GET',  '/roles',                  'RolController@index',  'roles.ver',    ['staff']],
    ['GET',  '/roles/{id}',             'RolController@edit',   'roles.ver',    ['staff']],
    ['POST', '/roles/{id}',             'RolController@update', 'roles.editar', ['staff']],

    // Auditoría y configuración
    ['GET',  '/auditoria/exportar.csv',   'ExportController@auditoriaCsv', 'auditoria.ver', ['staff']],
    ['GET',  '/auditoria/exportar.pdf',   'ExportController@auditoriaPdf', 'auditoria.ver', ['staff']],
    ['GET',  '/auditoria',              'AuditoriaController@index',      'auditoria.ver',      ['staff']],
    ['GET',  '/configuracion',          'ConfiguracionController@show',   'configuracion.ver',  ['staff']],
    ['POST', '/configuracion',          'ConfiguracionController@update', 'configuracion.editar', ['staff']],
    ['GET',  '/configuracion/apariencia', 'ConfiguracionController@apariencia',       'configuracion.ver',    ['staff']],
    ['POST', '/configuracion/apariencia', 'ConfiguracionController@aparienciaUpdate', 'configuracion.editar', ['staff']],
    ['POST', '/configuracion/marca',      'ConfiguracionController@marcaUpdate',      'configuracion.editar', ['staff']],
    ['GET',  '/configuracion/seguridad',  'ConfiguracionController@seguridad',        'configuracion.ver',    ['staff']],
    ['POST', '/configuracion/seguridad',  'ConfiguracionController@seguridadUpdate',  'configuracion.editar', ['staff']],

    // Marca institucional (logo y favicon): pública porque la pantalla de acceso también la usa
    ['GET',  '/tema.css',                'MarcaController@tema', 'public'],
    ['GET',  '/marca/{sec}',              'MarcaController@show', 'public'],

    // Verificación pública de documentos emitidos (código impreso en cada PDF)
    ['GET',  '/verificar',                'VerificacionController@form', 'public'],
    ['GET',  '/verificar/{codigo}',       'VerificacionController@show', 'public'],

    // Notificaciones (cada usuario solo ve las suyas)
    ['GET',  '/notificaciones',                  'NotificacionController@index',      'auth'],
    ['GET',  '/notificaciones/contador',         'NotificacionController@count',      'auth'],
    ['POST', '/notificaciones/leer',             'NotificacionController@readAll',    'auth'],
    ['POST', '/notificaciones/limpiar',          'NotificacionController@clearRead',  'auth'],
    ['POST', '/notificaciones/aviso',            'NotificacionController@broadcast',  'configuracion.editar', ['staff']],
    ['GET',  '/notificaciones/{id}',             'NotificacionController@open',       'auth'],
    ['POST', '/notificaciones/{id}/estado',      'NotificacionController@toggle',     'auth'],
    ['POST', '/notificaciones/{id}/eliminar',    'NotificacionController@destroy',    'auth'],
    ['POST', '/perfil/notificaciones',           'NotificacionController@preferences', 'auth'],

    // Búsqueda rápida de pacientes (barra superior)
    ['GET',  '/pacientes/sugerencias',           'PacienteController@suggest', 'pacientes.ver', ['staff']],

    // Documentos imprimibles adicionales
    ['GET',  '/consultas/{id}/receta.pdf',       'ExportController@recetaPdf',     'consultas.ver'],
    ['GET',  '/consultas/{id}/constancia.pdf',   'ExportController@constanciaPdf', 'consultas.ver'],
    ['GET',  '/pacientes/{id}/vacunas.pdf',      'ExportController@vacunasPdf',    'historial.ver'],

    // Configuración: documentos y notificaciones
    ['GET',  '/configuracion/documentos',             'ConfiguracionController@documentos',       'configuracion.ver',    ['staff']],
    ['POST', '/configuracion/documentos',             'ConfiguracionController@documentosUpdate', 'configuracion.editar', ['staff']],
    ['GET',  '/configuracion/documentos/muestra.pdf', 'ExportController@muestraPdf',              'configuracion.ver',    ['staff']],
    ['POST', '/configuracion/categorias',             'ConfiguracionController@categoriaStore',   'configuracion.editar', ['staff']],
    ['POST', '/configuracion/categorias/{id}',        'ConfiguracionController@categoriaUpdate',  'configuracion.editar', ['staff']],
    ['POST', '/configuracion/categorias/{id}/eliminar', 'ConfiguracionController@categoriaDestroy', 'configuracion.editar', ['staff']],
    ['GET',  '/configuracion/notificaciones',         'ConfiguracionController@notificaciones',   'configuracion.ver',    ['staff']],
    ['POST', '/configuracion/notificaciones',         'ConfiguracionController@notificacionesUpdate', 'configuracion.editar', ['staff']],
    ['GET',  '/configuracion/correo',                 'CorreoController@index',   'configuracion.ver',    ['staff']],
    ['POST', '/configuracion/correo',                 'CorreoController@update',  'configuracion.editar', ['staff']],
    ['POST', '/configuracion/correo/prueba',          'CorreoController@test',    'configuracion.editar', ['staff']],
    ['POST', '/configuracion/correo/procesar',        'CorreoController@process', 'configuracion.editar', ['staff']],
    ['GET',  '/configuracion/correo/{id}',            'CorreoController@show',    'configuracion.ver',    ['staff']],
    ['POST', '/configuracion/correo/{id}/reintentar', 'CorreoController@retry',   'configuracion.editar', ['staff']],
    ['POST', '/configuracion/correo/{id}/cancelar',   'CorreoController@cancel',  'configuracion.editar', ['staff']],
    ['GET',  '/correo/baja/{token}',                  'CorreoController@unsubscribe',        'public'],
    ['POST', '/correo/baja/{token}',                  'CorreoController@unsubscribeConfirm', 'public'],

    // Reportes por periodo (cada reporte exige además el permiso de su información)
    ['GET',  '/reportes',                 'ReporteController@index', 'reportes.ver', ['staff']],
    ['GET',  '/reportes/{sec}',           'ReporteController@show',  'reportes.ver', ['staff']],
];

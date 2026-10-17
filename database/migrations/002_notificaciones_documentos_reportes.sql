-- Notificaciones internas, preferencias por usuario, documentos emitidos (verificables) y permiso de reportes.

CREATE TABLE notificaciones (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED NOT NULL,
  tipo        VARCHAR(40)  NOT NULL,
  nivel       ENUM('info','aviso','critico','exito') NOT NULL DEFAULT 'info',
  titulo      VARCHAR(160) NOT NULL,
  mensaje     VARCHAR(255) NULL,
  enlace      VARCHAR(255) NULL,
  paciente_id INT UNSIGNED NULL,
  clave       VARCHAR(80)  NULL,
  leida_en    DATETIME NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notif_clave (usuario_id, clave),
  KEY ix_notif_usuario (usuario_id, leida_en, creado_en),
  CONSTRAINT fk_notif_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios (id)  ON DELETE CASCADE,
  CONSTRAINT fk_notif_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notificacion_preferencias (
  usuario_id INT UNSIGNED NOT NULL,
  tipo       VARCHAR(40) NOT NULL,
  activo     TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (usuario_id, tipo),
  CONSTRAINT fk_notifpref_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE documentos_emitidos (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo             CHAR(12)     NOT NULL,
  tipo               VARCHAR(30)  NOT NULL,
  titulo             VARCHAR(150) NOT NULL,
  paciente_id        INT UNSIGNED NULL,
  consulta_id        INT UNSIGNED NULL,
  emitido_por        INT UNSIGNED NULL,
  emitido_por_nombre VARCHAR(160) NOT NULL,
  creado_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_emitidos_codigo (codigo),
  KEY ix_emitidos_paciente (paciente_id, creado_en),
  CONSTRAINT fk_emit_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes (id) ON DELETE SET NULL,
  CONSTRAINT fk_emit_consulta FOREIGN KEY (consulta_id) REFERENCES consultas (id) ON DELETE SET NULL,
  CONSTRAINT fk_emit_usuario  FOREIGN KEY (emitido_por) REFERENCES usuarios (id)  ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO permisos (modulo, accion, descripcion) VALUES
  ('reportes', 'ver', 'Generar reportes por periodo en PDF y CSV');

INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.modulo = 'reportes' AND p.accion = 'ver'
 WHERE r.codigo IN ('administrador', 'medico');

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('doc_papel',            'a4',            'Tamaño de papel de los documentos: a4 o carta'),
  ('doc_encabezado',       'barra',         'Estilo de encabezado: barra, linea o minimo'),
  ('doc_logo',             '1',             'Mostrar el logo en los documentos'),
  ('doc_contacto',         '1',             'Mostrar dirección y teléfono en el encabezado'),
  ('doc_color',            '',              'Color de los documentos (vacío = color de acento del sistema)'),
  ('doc_letra',            'normal',        'Tamaño de letra: normal o grande'),
  ('doc_pie',              'Documento confidencial. Contiene información médica protegida.', 'Texto del pie de página'),
  ('doc_generado_por',     '1',             'Indicar en el pie quién generó el documento'),
  ('doc_verificacion',     '1',             'Incluir código de verificación de autenticidad'),
  ('doc_marca_agua',       '0',             'Imprimir marca de agua'),
  ('doc_marca_agua_texto', 'CONFIDENCIAL',  'Texto de la marca de agua'),
  ('doc_firma_texto',      'Firma y sello del médico tratante', 'Texto bajo la línea de firma'),
  ('doc_receta_nota',      'Receta válida por 30 días a partir de su emisión. No se automedique.', 'Nota al pie de la receta médica'),
  ('doc_secciones',        'vitales,alertas,antecedentes,enfermedades,cirugias,vacunas,habitos,familiares,consultas', 'Secciones del resumen clínico'),
  ('doc_tipos_permitidos', 'pdf,jpg,jpeg,png,webp', 'Formatos aceptados al adjuntar documentos'),
  ('notif_activas',        '1',             'Notificaciones habilitadas en el sistema'),
  ('notif_tipos_off',      '',              'Tipos de notificación deshabilitados para todos'),
  ('notif_recordatorio_horas', '48',        'Anticipación de los recordatorios de consulta, en horas'),
  ('notif_retencion_dias', '90',            'Días que se conservan las notificaciones leídas');

-- Notificaciones por correo: cola de salida, preferencia por canal, consentimiento del paciente y configuración.

CREATE TABLE correos_salientes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id        INT UNSIGNED NULL,
  paciente_id       INT UNSIGNED NULL,
  destinatario      VARCHAR(150) NOT NULL,
  nombre            VARCHAR(160) NULL,
  tipo              VARCHAR(40)  NOT NULL,
  asunto            VARCHAR(200) NOT NULL,
  cuerpo_html       MEDIUMTEXT   NOT NULL,
  cuerpo_texto      TEXT         NOT NULL,
  estado            ENUM('pendiente','enviado','fallido','cancelado') NOT NULL DEFAULT 'pendiente',
  intentos          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_error      VARCHAR(255) NULL,
  clave             VARCHAR(80)  NULL,
  siguiente_intento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_en        DATETIME NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_correo_clave (clave),
  KEY ix_correo_estado (estado, siguiente_intento),
  KEY ix_correo_usuario (usuario_id, creado_en),
  CONSTRAINT fk_correo_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios (id)  ON DELETE SET NULL,
  CONSTRAINT fk_correo_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- NULL = usar el valor predeterminado del tipo (los avisos importantes van por correo)
ALTER TABLE notificacion_preferencias ADD COLUMN correo TINYINT(1) NULL AFTER activo;

-- Consentimiento para avisar por correo a pacientes sin cuenta en el portal
ALTER TABLE pacientes ADD COLUMN acepta_correo TINYINT(1) NOT NULL DEFAULT 0 AFTER email;

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('correo_activo',     '1', 'Enviar notificaciones también por correo electrónico'),
  ('correo_tipos',      'consulta_programada,consulta_cancelada,recordatorio_consulta,documento_nuevo,alergia_grave,seguridad,aviso', 'Tipos de aviso que se envían por correo'),
  ('correo_limite_hora','300', 'Máximo de correos enviados por hora'),
  ('tareas_ultima_ejecucion', '', 'Última ejecución de la tarea programada (recordatorios y cola de correo)');

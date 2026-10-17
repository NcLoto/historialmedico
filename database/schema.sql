-- Hospital Sanitas — Historial Médico Electrónico
-- Esquema base. Compatible con MySQL 8 y MariaDB 10.4+.
-- La base de datos se crea desde database/install.php (nombre tomado de DB_DATABASE).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- Seguridad

CREATE TABLE roles (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo      VARCHAR(30)  NOT NULL,
  nombre      VARCHAR(60)  NOT NULL,
  descripcion VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE permisos (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  modulo      VARCHAR(30) NOT NULL,
  accion      VARCHAR(20) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permisos_modulo_accion (modulo, accion)
) ENGINE=InnoDB;

CREATE TABLE rol_permisos (
  rol_id     TINYINT UNSIGNED  NOT NULL,
  permiso_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  KEY ix_rol_permisos_permiso (permiso_id),
  CONSTRAINT fk_rp_rol     FOREIGN KEY (rol_id)     REFERENCES roles (id)    ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rol_id              TINYINT UNSIGNED NOT NULL,
  email               VARCHAR(150) NOT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  nombres             VARCHAR(80)  NOT NULL,
  apellidos           VARCHAR(80)  NOT NULL,
  activo              TINYINT(1)   NOT NULL DEFAULT 1,
  intentos_fallidos   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta     DATETIME NULL,
  ultimo_acceso       DATETIME NULL,
  debe_cambiar_clave  TINYINT(1)   NOT NULL DEFAULT 0,
  creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email),
  KEY ix_usuarios_rol (rol_id),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expira_en  DATETIME     NOT NULL,
  usado_en   DATETIME     NULL,
  creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_resets_token (token_hash),
  KEY ix_resets_usuario (usuario_id),
  CONSTRAINT fk_resets_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- Personas

CREATE TABLE especialidades (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(80)  NOT NULL,
  descripcion VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_especialidades_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE medicos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED NOT NULL,
  especialidad_id SMALLINT UNSIGNED NOT NULL,
  colegiado       VARCHAR(20)  NOT NULL,
  telefono        VARCHAR(25)  NULL,
  consultorio     VARCHAR(60)  NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_medicos_usuario (usuario_id),
  UNIQUE KEY uq_medicos_colegiado (colegiado),
  KEY ix_medicos_especialidad (especialidad_id),
  CONSTRAINT fk_medicos_usuario      FOREIGN KEY (usuario_id)      REFERENCES usuarios (id)      ON DELETE RESTRICT,
  CONSTRAINT fk_medicos_especialidad FOREIGN KEY (especialidad_id) REFERENCES especialidades (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE pacientes (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo               VARCHAR(12)  NOT NULL,
  usuario_id           INT UNSIGNED NULL,
  documento_identidad  VARCHAR(20)  NOT NULL,
  nombres              VARCHAR(80)  NOT NULL,
  apellidos            VARCHAR(80)  NOT NULL,
  fecha_nacimiento     DATE NOT NULL,
  sexo                 ENUM('F','M') NOT NULL,
  tipo_sangre          ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
  estado_civil         ENUM('soltero','casado','unido','divorciado','viudo') NULL,
  ocupacion            VARCHAR(80)  NULL,
  telefono             VARCHAR(25)  NULL,
  email                VARCHAR(150) NULL,
  direccion            VARCHAR(200) NULL,
  contacto_nombre      VARCHAR(120) NULL,
  contacto_parentesco  VARCHAR(40)  NULL,
  contacto_telefono    VARCHAR(25)  NULL,
  activo               TINYINT(1)   NOT NULL DEFAULT 1,
  creado_por           INT UNSIGNED NULL,
  creado_en            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pacientes_codigo (codigo),
  UNIQUE KEY uq_pacientes_documento (documento_identidad),
  UNIQUE KEY uq_pacientes_usuario (usuario_id),
  KEY ix_pacientes_apellidos (apellidos, nombres),
  CONSTRAINT fk_pacientes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_pacientes_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT ck_pacientes_nacimiento CHECK (fecha_nacimiento >= '1900-01-01')
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- Historial clínico
-- Todas las tablas clínicas: ON DELETE RESTRICT sobre paciente (los pacientes se inactivan, no se borran).

CREATE TABLE antecedentes_medicos (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id      INT UNSIGNED NOT NULL,
  tipo             ENUM('patologico','traumatico','ginecoobstetrico','otro') NOT NULL DEFAULT 'patologico',
  descripcion      VARCHAR(255) NOT NULL,
  fecha_aproximada VARCHAR(40)  NULL,
  observaciones    TEXT NULL,
  registrado_por   INT UNSIGNED NULL,
  creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_antmed_paciente (paciente_id),
  CONSTRAINT fk_antmed_paciente FOREIGN KEY (paciente_id)    REFERENCES pacientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_antmed_usuario  FOREIGN KEY (registrado_por) REFERENCES usuarios (id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE alergias (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id     INT UNSIGNED NOT NULL,
  agente          VARCHAR(120) NOT NULL,
  tipo            ENUM('medicamento','alimento','ambiental','otra') NOT NULL,
  reaccion        VARCHAR(255) NULL,
  severidad       ENUM('leve','moderada','grave') NOT NULL DEFAULT 'moderada',
  fecha_deteccion DATE NULL,
  activa          TINYINT(1) NOT NULL DEFAULT 1,
  observaciones   TEXT NULL,
  registrado_por  INT UNSIGNED NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_alergias_paciente (paciente_id, activa),
  CONSTRAINT fk_alergias_paciente FOREIGN KEY (paciente_id)    REFERENCES pacientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_alergias_usuario  FOREIGN KEY (registrado_por) REFERENCES usuarios (id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE enfermedades (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id       INT UNSIGNED NOT NULL,
  nombre            VARCHAR(150) NOT NULL,
  codigo_cie10      VARCHAR(10)  NULL,
  fecha_diagnostico DATE NULL,
  estado            ENUM('activa','controlada','resuelta') NOT NULL DEFAULT 'activa',
  cronica           TINYINT(1) NOT NULL DEFAULT 0,
  observaciones     TEXT NULL,
  registrado_por    INT UNSIGNED NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_enfermedades_paciente (paciente_id, estado),
  CONSTRAINT fk_enf_paciente FOREIGN KEY (paciente_id)    REFERENCES pacientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_enf_usuario  FOREIGN KEY (registrado_por) REFERENCES usuarios (id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cirugias (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id    INT UNSIGNED NOT NULL,
  procedimiento  VARCHAR(150) NOT NULL,
  fecha          DATE NOT NULL,
  centro         VARCHAR(120) NULL,
  cirujano       VARCHAR(120) NULL,
  complicaciones VARCHAR(255) NULL,
  observaciones  TEXT NULL,
  registrado_por INT UNSIGNED NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_cirugias_paciente (paciente_id, fecha),
  CONSTRAINT fk_cir_paciente FOREIGN KEY (paciente_id)    REFERENCES pacientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cir_usuario  FOREIGN KEY (registrado_por) REFERENCES usuarios (id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE vacunas (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id      INT UNSIGNED NOT NULL,
  vacuna           VARCHAR(120) NOT NULL,
  dosis            VARCHAR(40)  NULL,
  fecha_aplicacion DATE NOT NULL,
  proxima_dosis    DATE NULL,
  lote             VARCHAR(40)  NULL,
  centro           VARCHAR(120) NULL,
  observaciones    TEXT NULL,
  registrado_por   INT UNSIGNED NULL,
  creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_vacunas_paciente (paciente_id, fecha_aplicacion),
  CONSTRAINT fk_vac_paciente FOREIGN KEY (paciente_id)    REFERENCES pacientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_vac_usuario  FOREIGN KEY (registrado_por) REFERENCES usuarios (id)  ON DELETE SET NULL,
  CONSTRAINT ck_vac_proxima CHECK (proxima_dosis IS NULL OR proxima_dosis >= fecha_aplicacion)
) ENGINE=InnoDB;

CREATE TABLE habitos_salud (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id    INT UNSIGNED NOT NULL,
  tipo           ENUM('tabaco','alcohol','ejercicio','alimentacion','sueno','otras_sustancias','otro') NOT NULL,
  descripcion    VARCHAR(255) NOT NULL,
  frecuencia     VARCHAR(80)  NULL,
  observaciones  TEXT NULL,
  registrado_por INT UNSIGNED NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_habitos_paciente (paciente_id),
  CONSTRAINT fk_hab_paciente FOREIGN KEY (paciente_id)    REFERENCES pacientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_hab_usuario  FOREIGN KEY (registrado_por) REFERENCES usuarios (id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE antecedentes_familiares (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id    INT UNSIGNED NOT NULL,
  parentesco     ENUM('madre','padre','hermano','hermana','abuelo_materno','abuela_materna','abuelo_paterno','abuela_paterna','tio','tia','hijo','hija','otro') NOT NULL,
  condicion      VARCHAR(150) NOT NULL,
  edad_inicio    TINYINT UNSIGNED NULL,
  fallecido      TINYINT(1) NOT NULL DEFAULT 0,
  observaciones  TEXT NULL,
  registrado_por INT UNSIGNED NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_antfam_paciente (paciente_id),
  CONSTRAINT fk_antfam_paciente FOREIGN KEY (paciente_id)    REFERENCES pacientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_antfam_usuario  FOREIGN KEY (registrado_por) REFERENCES usuarios (id)  ON DELETE SET NULL,
  CONSTRAINT ck_antfam_edad CHECK (edad_inicio IS NULL OR edad_inicio <= 120)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- Atención médica

CREATE TABLE consultas (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id        INT UNSIGNED NOT NULL,
  medico_id          INT UNSIGNED NOT NULL,
  fecha_hora         DATETIME NOT NULL,
  estado             ENUM('programada','realizada','cancelada') NOT NULL DEFAULT 'realizada',
  motivo             VARCHAR(255) NOT NULL,
  enfermedad_actual  TEXT NULL,
  presion_sistolica  SMALLINT UNSIGNED NULL,
  presion_diastolica SMALLINT UNSIGNED NULL,
  frecuencia_cardiaca SMALLINT UNSIGNED NULL,
  temperatura        DECIMAL(4,1) NULL,
  saturacion_o2      TINYINT UNSIGNED NULL,
  peso_kg            DECIMAL(5,1) NULL,
  talla_cm           DECIMAL(4,1) NULL,
  examen_fisico      TEXT NULL,
  plan               TEXT NULL,
  notas_internas     TEXT NULL,
  creado_por         INT UNSIGNED NULL,
  creado_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_consultas_paciente (paciente_id, fecha_hora),
  KEY ix_consultas_medico (medico_id, fecha_hora),
  KEY ix_consultas_estado (estado, fecha_hora),
  CONSTRAINT fk_consultas_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_consultas_medico   FOREIGN KEY (medico_id)   REFERENCES medicos (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_consultas_creador  FOREIGN KEY (creado_por)  REFERENCES usuarios (id)  ON DELETE SET NULL,
  CONSTRAINT ck_consultas_pa  CHECK (presion_sistolica IS NULL OR (presion_sistolica BETWEEN 40 AND 300)),
  CONSTRAINT ck_consultas_pad CHECK (presion_diastolica IS NULL OR (presion_diastolica BETWEEN 20 AND 200)),
  CONSTRAINT ck_consultas_fc  CHECK (frecuencia_cardiaca IS NULL OR (frecuencia_cardiaca BETWEEN 20 AND 250)),
  CONSTRAINT ck_consultas_temp CHECK (temperatura IS NULL OR (temperatura BETWEEN 30 AND 45)),
  CONSTRAINT ck_consultas_sat CHECK (saturacion_o2 IS NULL OR saturacion_o2 <= 100)
) ENGINE=InnoDB;

CREATE TABLE diagnosticos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  consulta_id  INT UNSIGNED NOT NULL,
  codigo_cie10 VARCHAR(10) NULL,
  descripcion  VARCHAR(255) NOT NULL,
  tipo         ENUM('principal','secundario') NOT NULL DEFAULT 'principal',
  PRIMARY KEY (id),
  KEY ix_diagnosticos_consulta (consulta_id),
  CONSTRAINT fk_diag_consulta FOREIGN KEY (consulta_id) REFERENCES consultas (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tratamientos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  consulta_id INT UNSIGNED NOT NULL,
  tipo        ENUM('medicamento','procedimiento','indicacion','referencia') NOT NULL DEFAULT 'medicamento',
  descripcion VARCHAR(255) NOT NULL,
  dosis       VARCHAR(80)  NULL,
  frecuencia  VARCHAR(80)  NULL,
  duracion    VARCHAR(80)  NULL,
  PRIMARY KEY (id),
  KEY ix_tratamientos_consulta (consulta_id),
  CONSTRAINT fk_trat_consulta FOREIGN KEY (consulta_id) REFERENCES consultas (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Lista de medicamentos vigentes del paciente (puede originarse en una consulta).
CREATE TABLE medicamentos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id    INT UNSIGNED NOT NULL,
  consulta_id    INT UNSIGNED NULL,
  nombre         VARCHAR(120) NOT NULL,
  dosis          VARCHAR(60)  NULL,
  via            ENUM('oral','intravenosa','intramuscular','subcutanea','topica','inhalada','oftalmica','otra') NOT NULL DEFAULT 'oral',
  frecuencia     VARCHAR(80)  NULL,
  motivo         VARCHAR(150) NULL,
  fecha_inicio   DATE NULL,
  fecha_fin      DATE NULL,
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  observaciones  TEXT NULL,
  registrado_por INT UNSIGNED NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_medicamentos_paciente (paciente_id, activo),
  KEY ix_medicamentos_consulta (consulta_id),
  CONSTRAINT fk_med_paciente FOREIGN KEY (paciente_id)    REFERENCES pacientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_med_consulta FOREIGN KEY (consulta_id)    REFERENCES consultas (id) ON DELETE SET NULL,
  CONSTRAINT fk_med_usuario  FOREIGN KEY (registrado_por) REFERENCES usuarios (id)  ON DELETE SET NULL,
  CONSTRAINT ck_med_fechas CHECK (fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- Documentos

CREATE TABLE categorias_documentos (
  id     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  grupo  ENUM('examen','imagen','documento') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catdoc_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE documentos_medicos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id    INT UNSIGNED NOT NULL,
  consulta_id    INT UNSIGNED NULL,
  categoria_id   TINYINT UNSIGNED NOT NULL,
  titulo         VARCHAR(150) NOT NULL,
  descripcion    VARCHAR(255) NULL,
  fecha_documento DATE NULL,
  nombre_original VARCHAR(255) NOT NULL,
  nombre_interno  CHAR(36)     NOT NULL,
  extension      VARCHAR(10)  NOT NULL,
  tipo_mime      VARCHAR(100) NOT NULL,
  tamano_bytes   INT UNSIGNED NOT NULL,
  sha256         CHAR(64)     NOT NULL,
  cargado_por    INT UNSIGNED NULL,
  cargado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documentos_interno (nombre_interno),
  KEY ix_documentos_paciente (paciente_id, cargado_en),
  KEY ix_documentos_consulta (consulta_id),
  KEY ix_documentos_categoria (categoria_id),
  CONSTRAINT fk_doc_paciente  FOREIGN KEY (paciente_id)  REFERENCES pacientes (id)             ON DELETE RESTRICT,
  CONSTRAINT fk_doc_consulta  FOREIGN KEY (consulta_id)  REFERENCES consultas (id)             ON DELETE SET NULL,
  CONSTRAINT fk_doc_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_documentos (id) ON DELETE RESTRICT,
  CONSTRAINT fk_doc_usuario   FOREIGN KEY (cargado_por)  REFERENCES usuarios (id)              ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- Auditoría y configuración

CREATE TABLE auditoria (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id     INT UNSIGNED NULL,
  usuario_nombre VARCHAR(160) NOT NULL,
  rol            VARCHAR(30)  NULL,
  accion         VARCHAR(30)  NOT NULL,
  modulo         VARCHAR(30)  NOT NULL,
  registro_id    INT UNSIGNED NULL,
  paciente_id    INT UNSIGNED NULL,
  descripcion    VARCHAR(255) NOT NULL,
  detalle        TEXT NULL,
  ip             VARCHAR(45)  NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_aud_fecha (creado_en),
  KEY ix_aud_usuario (usuario_id, creado_en),
  KEY ix_aud_paciente (paciente_id, creado_en),
  KEY ix_aud_modulo (modulo, accion),
  CONSTRAINT fk_aud_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE configuracion (
  clave       VARCHAR(50)  NOT NULL,
  valor       VARCHAR(255) NOT NULL,
  descripcion VARCHAR(255) NULL,
  PRIMARY KEY (clave)
) ENGINE=InnoDB;

CREATE TABLE migraciones (
  nombre      VARCHAR(100) NOT NULL,
  aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (nombre)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

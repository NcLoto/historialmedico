-- Apariencia (tema y color de acento), marca (logo y favicon) y datos de contacto institucionales.
ALTER TABLE usuarios ADD COLUMN tema ENUM('sistema','claro','oscuro') NOT NULL DEFAULT 'sistema' AFTER debe_cambiar_clave;

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('hospital_lema',     'Historial médico electrónico', 'Texto bajo el nombre del hospital'),
  ('hospital_correo',   '',                             'Correo de contacto institucional'),
  ('hospital_horario',  '',                             'Horario de atención'),
  ('tema_defecto',      'claro',                        'Tema por defecto: claro, oscuro o auto (sigue al sistema operativo)'),
  ('color_acento',      'cian',                         'Color de acento de la interfaz'),
  ('logo_archivo',      '',                             'Archivo del logo (vacío = logo por defecto)'),
  ('favicon_archivo',   '',                             'Archivo del favicon (vacío = favicon por defecto)');

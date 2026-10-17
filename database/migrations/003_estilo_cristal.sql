-- Estilo de interfaz «cristal» (paneles translúcidos sobre una imagen de fondo) y sus controles.
INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('estilo_interfaz',    'solido', 'Estilo de la interfaz: solido o cristal (paneles translúcidos)'),
  ('fondo_archivo',      '',       'Imagen de fondo del estilo cristal (vacío = fondo del color de acento)'),
  ('cristal_opacidad',   '74',     'Opacidad de los paneles en estilo cristal (50 a 95 %)'),
  ('cristal_desenfoque', '18',     'Desenfoque detrás de los paneles en estilo cristal (0 a 30 px)'),
  ('fondo_oscurecer',    '20',     'Oscurecimiento de la imagen de fondo (0 a 70 %)'),
  ('fondo_luma',         '',       'Brillo medio de la imagen de fondo (lo calcula el sistema)');

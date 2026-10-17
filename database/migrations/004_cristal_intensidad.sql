-- Controles adicionales del estilo cristal: intensidad del efecto vidrio y transparencia de la imagen de fondo.
INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('cristal_intensidad',  '60', 'Intensidad del efecto vidrio: brillo, borde luminoso y profundidad de los paneles (0 a 100)'),
  ('fondo_transparencia', '0',  'Transparencia de la imagen de fondo: cuánto se funde con el color base del tema (0 a 90 %)');

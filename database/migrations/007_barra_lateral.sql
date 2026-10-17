-- Barra lateral del menú: automática (derivada del color de acento), clara o de un color elegido.
INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('barra_modo',  'automatico', 'Barra lateral: automatico (según el color de acento), claro o personalizado'),
  ('barra_color', '#1f2937',    'Color de la barra lateral cuando el modo es personalizado');

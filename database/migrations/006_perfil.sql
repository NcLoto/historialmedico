-- Perfil de usuario: foto y teléfono de contacto de la cuenta (personal administrativo; médicos y pacientes usan el de su ficha).
ALTER TABLE usuarios
  ADD COLUMN telefono     VARCHAR(25) NULL AFTER apellidos,
  ADD COLUMN foto_archivo VARCHAR(60) NULL AFTER tema;

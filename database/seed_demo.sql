-- Datos de demostración COMPLETAMENTE FICTICIOS. Ninguna persona, documento o dato clínico es real.
-- Requiere que install.php haya creado antes las cuentas de usuario (se enlazan por correo).

SET NAMES utf8mb4;

-- ------------------------------------------------------------------ Médicos
INSERT INTO medicos (usuario_id, especialidad_id, colegiado, telefono, consultorio)
SELECT u.id, e.id, m.colegiado, m.telefono, m.consultorio FROM (
  SELECT 'andres.ovalle@sanitas.local' email, 'Medicina interna' esp, '14872' colegiado, '2300-4511' telefono, 'Consultorio 204' consultorio
  UNION ALL SELECT 'lucia.merida@sanitas.local',   'Pediatría',                 '15390', '2300-4512', 'Consultorio 112'
  UNION ALL SELECT 'rodrigo.pineda@sanitas.local', 'Cardiología',               '13204', '2300-4513', 'Consultorio 301'
  UNION ALL SELECT 'marta.estrada@sanitas.local',  'Ginecología y obstetricia', '16011', '2300-4514', 'Consultorio 118'
) m JOIN usuarios u ON u.email = m.email JOIN especialidades e ON e.nombre = m.esp;

SET @m_ovalle := (SELECT id FROM medicos WHERE colegiado = '14872');
SET @m_merida := (SELECT id FROM medicos WHERE colegiado = '15390');
SET @m_pineda := (SELECT id FROM medicos WHERE colegiado = '13204');
SET @m_estrada := (SELECT id FROM medicos WHERE colegiado = '16011');
SET @u_ovalle := (SELECT usuario_id FROM medicos WHERE id = @m_ovalle);
SET @u_merida := (SELECT usuario_id FROM medicos WHERE id = @m_merida);
SET @u_pineda := (SELECT usuario_id FROM medicos WHERE id = @m_pineda);
SET @u_estrada := (SELECT usuario_id FROM medicos WHERE id = @m_estrada);
SET @u_admin := (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1);

-- ------------------------------------------------------------------ Pacientes
INSERT INTO pacientes (codigo, usuario_id, documento_identidad, nombres, apellidos, fecha_nacimiento, sexo, tipo_sangre, estado_civil, ocupacion, telefono, email, direccion, contacto_nombre, contacto_parentesco, contacto_telefono, creado_por) VALUES
('SAN-000101', (SELECT id FROM usuarios WHERE email='carlos.ramirez@correo.test'),  '2584130210101', 'Carlos Humberto', 'Ramírez Soto',      '1968-03-14', 'M', 'O+',  'casado',     'Contador',              '5512-3401', 'carlos.ramirez@correo.test',  '12 calle 5-33, zona 11, Ciudad de Guatemala',      'Rosa Elena de Ramírez',  'Esposa',  '5512-3402', @u_admin),
('SAN-000102', (SELECT id FROM usuarios WHERE email='mariajose.gomez@correo.test'), '2691044550101', 'María José',       'Gómez Archila',     '1992-07-22', 'F', 'A+',  'soltero',    'Diseñadora gráfica',    '4120-7788', 'mariajose.gomez@correo.test', 'Km 14.5 Carretera a El Salvador, Santa Catarina Pinula', 'Beatriz Archila',   'Madre',   '4120-7790', @u_admin),
('SAN-000103', (SELECT id FROM usuarios WHERE email='pedro.tzul@correo.test'),      '1987655320401', 'Pedro Alejandro',  'Tzul Cojtí',        '1955-11-02', 'M', 'B+',  'viudo',      'Agricultor jubilado',   '5934-1020', 'pedro.tzul@correo.test',      'Aldea Xesuj, Chichicastenango, Quiché',            'Julia Tzul Ixcoy',       'Hija',    '5934-1099', @u_admin),
('SAN-000104', (SELECT id FROM usuarios WHERE email='ana.barrios@correo.test'),     '2745009870101', 'Ana Lucrecia',     'Barrios de León',   '1997-01-30', 'F', 'O-',  'casado',     'Maestra de primaria',   '4455-9012', 'ana.barrios@correo.test',     '3a avenida 8-15, zona 2, Mixco',                   'Daniel de León',         'Esposo',  '4455-9013', @u_admin),
('SAN-000105', NULL,                                                                 '2302918450101', 'José Luis',        'Monzón Reyes',      '1981-05-09', 'M', 'A-',  'unido',      'Ingeniero civil',       '5701-6644', 'jlmonzon@correo.test',        'Colonia El Naranjo, zona 4 de Mixco',              'Andrea Reyes',           'Madre',   '5701-6650', @u_admin),
('SAN-000106', NULL,                                                                 '3012233140101', 'Sofía Valentina',  'Herrera Paz',       '2018-04-17', 'F', 'B+',  NULL,         'Estudiante',            '5588-2301', 'herrera.paz.familia@correo.test', '6a calle 2-40, zona 1, Villa Nueva',            'Karla Paz de Herrera',   'Madre',   '5588-2301', @u_admin),
('SAN-000107', (SELECT id FROM usuarios WHERE email='roberto.cifuentes@correo.test'),'2188457760101','Roberto Esteban',  'Cifuentes Morales', '1963-09-25', 'M', 'AB+', 'casado',     'Comerciante',           '5301-4477', 'roberto.cifuentes@correo.test','10a avenida 14-02, zona 15, Ciudad de Guatemala',  'Silvia de Cifuentes',    'Esposa',  '5301-4478', @u_admin),
('SAN-000108', (SELECT id FROM usuarios WHERE email='gabriela.lopez@correo.test'),  '2476810920101', 'Gabriela Fernanda','López Mazariegos', '1974-02-11', 'F', 'A+',  'divorciado', 'Abogada',               '4709-3355', 'gabriela.lopez@correo.test',  'Condominio Los Álamos, casa 18, zona 16',          'Fernando López',         'Hermano', '4709-3360', @u_admin),
('SAN-000109', NULL,                                                                 '3045590010101', 'Mateo Sebastián',  'Orantes Girón',     '2023-06-08', 'M', 'O+',  NULL,         NULL,                    '5522-8890', 'orantes.giron@correo.test',   'Lote 9, Residenciales San Cristóbal, Mixco',       'Paola Girón',            'Madre',   '5522-8890', @u_admin),
('SAN-000110', NULL,                                                                 '2569871230101', 'Elena Patricia',   'Rodas Quiñónez',    '1987-12-03', 'F', 'O+',  'casado',     'Periodista',            '4033-6120', 'elena.rodas@correo.test',     '5a calle 7-21, zona 3, Quetzaltenango',            'Marcos Rodas',           'Esposo',  '4033-6121', @u_admin);

SET @p1 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000101');
SET @p2 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000102');
SET @p3 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000103');
SET @p4 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000104');
SET @p5 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000105');
SET @p6 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000106');
SET @p7 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000107');
SET @p8 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000108');
SET @p9 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000109');
SET @p10 := (SELECT id FROM pacientes WHERE codigo = 'SAN-000110');

-- ------------------------------------------------------------------ Antecedentes personales
INSERT INTO antecedentes_medicos (paciente_id, tipo, descripcion, fecha_aproximada, observaciones, registrado_por) VALUES
(@p1, 'patologico', 'Hipertensión arterial diagnosticada en control laboral', '2014', 'Inicio con cefalea occipital matutina.', @u_ovalle),
(@p1, 'patologico', 'Diabetes mellitus tipo 2', '2018', 'Detectada por glucosa en ayunas elevada en chequeo anual.', @u_ovalle),
(@p1, 'traumatico',  'Fractura de radio distal izquierdo por caída', '2009', 'Tratamiento conservador con inmovilización.', @u_ovalle),
(@p2, 'patologico', 'Asma bronquial desde la infancia', '1999', 'Crisis ocasionales con el esfuerzo y cambios de clima.', @u_ovalle),
(@p3, 'patologico', 'Neumonía adquirida en la comunidad', '2016', 'Requirió hospitalización por 5 días.', @u_ovalle),
(@p3, 'patologico', 'Tuberculosis pulmonar tratada', '1992', 'Esquema completo de 6 meses, curación documentada.', @u_ovalle),
(@p4, 'ginecoobstetrico', 'Una gesta, un parto eutócico', '2022', 'Sin complicaciones. Lactancia materna exclusiva 6 meses.', @u_estrada),
(@p5, 'patologico', 'Gastritis crónica', '2015', 'Se maneja con dieta y omeprazol a demanda.', @u_ovalle),
(@p6, 'patologico', 'Bronquiolitis a los 8 meses de edad', '2018', 'Manejo ambulatorio.', @u_merida),
(@p7, 'patologico', 'Infarto agudo de miocardio sin elevación del ST', '2021', 'Cateterismo con colocación de un stent en arteria descendente anterior.', @u_pineda),
(@p7, 'patologico', 'Hipertensión arterial', '2010', NULL, @u_pineda),
(@p8, 'patologico', 'Hipotiroidismo primario', '2011', 'Hipotiroidismo posterior a tiroiditis autoinmune.', @u_ovalle),
(@p8, 'ginecoobstetrico', 'Dos gestas, dos partos, menopausia a los 49 años', '2023', NULL, @u_estrada),
(@p9, 'otro', 'Nacido a término por parto vaginal, peso 3,200 g', '2023', 'Sin complicaciones neonatales.', @u_merida),
(@p10,'patologico', 'Migraña con aura', '2008', 'Episodios de 2 a 3 por mes, desencadenados por estrés y falta de sueño.', @u_ovalle);

-- ------------------------------------------------------------------ Alergias
INSERT INTO alergias (paciente_id, agente, tipo, reaccion, severidad, fecha_deteccion, activa, observaciones, registrado_por) VALUES
(@p1, 'Penicilina', 'medicamento', 'Urticaria generalizada y edema de labios', 'grave', '2005-08-10', 1, 'Evitar también amoxicilina y derivados.', @u_ovalle),
(@p1, 'Yodo', 'ambiental', 'Dermatitis de contacto', 'leve', '2012-02-01', 1, NULL, @u_ovalle),
(@p2, 'Mariscos', 'alimento', 'Hinchazón de labios y dificultad respiratoria', 'grave', '2010-12-24', 1, 'Lleva epinefrina autoinyectable.', @u_ovalle),
(@p2, 'Polvo doméstico', 'ambiental', 'Rinitis y crisis asmática', 'moderada', '2000-01-01', 1, NULL, @u_ovalle),
(@p3, 'Ibuprofeno', 'medicamento', 'Broncoespasmo', 'moderada', '2017-05-19', 1, 'Evitar AINE en general.', @u_ovalle),
(@p5, 'Sulfas', 'medicamento', 'Erupción cutánea', 'moderada', '2019-03-03', 1, NULL, @u_ovalle),
(@p6, 'Huevo', 'alimento', 'Eritema perioral', 'leve', '2020-06-11', 0, 'Tolerancia adquirida confirmada en 2024.', @u_merida),
(@p7, 'Aspirina', 'medicamento', 'Sangrado gastrointestinal', 'grave', '2022-01-15', 1, 'Utiliza clopidogrel como antiagregante.', @u_pineda),
(@p8, 'Látex', 'ambiental', 'Urticaria por contacto', 'moderada', '2008-09-09', 1, 'Informar antes de cualquier procedimiento.', @u_ovalle),
(@p10,'Diclofenaco', 'medicamento', 'Erupción y prurito', 'leve', '2014-04-04', 1, NULL, @u_ovalle);

-- ------------------------------------------------------------------ Enfermedades
INSERT INTO enfermedades (paciente_id, nombre, codigo_cie10, fecha_diagnostico, estado, cronica, observaciones, registrado_por) VALUES
(@p1, 'Hipertensión arterial esencial', 'I10', '2014-06-01', 'controlada', 1, 'Meta de presión menor a 130/80 mmHg.', @u_ovalle),
(@p1, 'Diabetes mellitus tipo 2', 'E11.9', '2018-09-12', 'activa', 1, 'Última HbA1c 7.4 %.', @u_ovalle),
(@p2, 'Asma bronquial persistente leve', 'J45.0', '1999-03-01', 'controlada', 1, NULL, @u_ovalle),
(@p3, 'Enfermedad pulmonar obstructiva crónica', 'J44.9', '2019-02-20', 'activa', 1, 'Exfumador de 40 paquetes-año.', @u_ovalle),
(@p3, 'Hipertrofia prostática benigna', 'N40', '2021-07-14', 'controlada', 0, NULL, @u_ovalle),
(@p5, 'Dislipidemia mixta', 'E78.2', '2024-01-18', 'activa', 1, 'Colesterol LDL 162 mg/dL al diagnóstico.', @u_ovalle),
(@p5, 'Gastritis crónica', 'K29.5', '2015-05-02', 'controlada', 0, NULL, @u_ovalle),
(@p7, 'Cardiopatía isquémica', 'I25.1', '2021-03-22', 'controlada', 1, 'Fracción de eyección 52 %.', @u_pineda),
(@p7, 'Hipertensión arterial esencial', 'I10', '2010-05-10', 'controlada', 1, NULL, @u_pineda),
(@p8, 'Hipotiroidismo primario', 'E03.9', '2011-10-05', 'controlada', 1, NULL, @u_ovalle),
(@p8, 'Osteoporosis posmenopáusica', 'M81.0', '2024-08-30', 'activa', 1, 'Densitometría con T-score de -2.7 en columna lumbar.', @u_ovalle),
(@p10,'Migraña con aura', 'G43.1', '2008-06-01', 'activa', 1, NULL, @u_ovalle),
(@p6, 'Asma leve intermitente', 'J45.0', '2022-09-10', 'controlada', 0, NULL, @u_merida);

-- ------------------------------------------------------------------ Cirugías
INSERT INTO cirugias (paciente_id, procedimiento, fecha, centro, cirujano, complicaciones, observaciones, registrado_por) VALUES
(@p1, 'Apendicectomía abierta', '1990-04-18', 'Hospital General San Juan de Dios', 'Dr. Mario Aguilar', NULL, NULL, @u_ovalle),
(@p3, 'Hernioplastia inguinal derecha', '2018-10-02', 'Hospital Sanitas', 'Dr. Héctor Barillas', NULL, 'Colocación de malla.', @u_ovalle),
(@p4, 'Amigdalectomía', '2005-07-21', 'Sanatorio Santa Teresa', 'Dr. Luis Orellana', NULL, NULL, @u_estrada),
(@p7, 'Angioplastia coronaria con colocación de stent', '2021-03-23', 'Hospital Sanitas', 'Dr. Rodrigo Pineda', NULL, 'Stent liberador de fármaco en arteria descendente anterior.', @u_pineda),
(@p8, 'Colecistectomía laparoscópica', '2016-11-12', 'Hospital Sanitas', 'Dra. Ingrid Solares', 'Infección leve de herida umbilical', 'Resuelta con antibiótico oral.', @u_ovalle),
(@p8, 'Cesárea', '2004-05-30', 'Sanatorio Santa Teresa', 'Dr. Ricardo Paiz', NULL, NULL, @u_estrada),
(@p10,'Rinoplastia funcional', '2013-02-08', 'Hospital Sanitas', 'Dr. Álvaro Menchú', NULL, 'Corrección de desviación septal.', @u_ovalle);

-- ------------------------------------------------------------------ Vacunas
INSERT INTO vacunas (paciente_id, vacuna, dosis, fecha_aplicacion, proxima_dosis, lote, centro, observaciones, registrado_por) VALUES
(@p1, 'Influenza estacional', 'Anual', DATE_SUB(CURDATE(), INTERVAL 5 MONTH), DATE_ADD(CURDATE(), INTERVAL 7 MONTH), 'FL24-0381', 'Hospital Sanitas', NULL, @u_ovalle),
(@p1, 'Neumococo 23-valente', 'Dosis única', '2022-10-05', NULL, 'PN22-1180', 'Hospital Sanitas', 'Indicada por diabetes.', @u_ovalle),
(@p3, 'Influenza estacional', 'Anual', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 'FL24-0392', 'Hospital Sanitas', NULL, @u_ovalle),
(@p3, 'COVID-19 (refuerzo)', 'Refuerzo 2', '2023-04-11', NULL, 'CV23-7741', 'Centro de Salud Chichicastenango', NULL, @u_ovalle),
(@p4, 'Tdap', 'Dosis única', '2022-03-15', NULL, 'TD22-0412', 'Hospital Sanitas', 'Aplicada durante el embarazo.', @u_estrada),
(@p4, 'Virus del papiloma humano', '2 de 3', '2025-02-10', DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'HP25-2290', 'Hospital Sanitas', NULL, @u_estrada),
(@p6, 'Triple viral (SPR)', 'Refuerzo', '2023-04-20', NULL, 'SP23-0057', 'Centro de Salud Villa Nueva', NULL, @u_merida),
(@p6, 'Influenza estacional', 'Anual', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_ADD(CURDATE(), INTERVAL 11 MONTH), 'FL24-0401', 'Hospital Sanitas', NULL, @u_merida),
(@p9, 'Hexavalente', '3 de 3', '2023-12-12', NULL, 'HX23-8810', 'Hospital Sanitas', NULL, @u_merida),
(@p9, 'Triple viral (SPR)', '1 de 2', DATE_SUB(CURDATE(), INTERVAL 21 MONTH), DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 'SP24-0102', 'Hospital Sanitas', 'Segunda dosis próxima.', @u_merida),
(@p7, 'Influenza estacional', 'Anual', DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_ADD(CURDATE(), INTERVAL 8 MONTH), 'FL24-0377', 'Hospital Sanitas', NULL, @u_pineda),
(@p8, 'Tétanos y difteria (Td)', 'Refuerzo', '2021-06-02', DATE_ADD('2021-06-02', INTERVAL 10 YEAR), 'TD21-0640', 'Hospital Sanitas', NULL, @u_ovalle),
(@p2, 'Influenza estacional', 'Anual', DATE_SUB(CURDATE(), INTERVAL 6 MONTH), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'FL24-0366', 'Hospital Sanitas', 'Indicada por asma.', @u_ovalle);

-- ------------------------------------------------------------------ Hábitos
INSERT INTO habitos_salud (paciente_id, tipo, descripcion, frecuencia, observaciones, registrado_por) VALUES
(@p1, 'ejercicio', 'Camina 30 minutos', '3 veces por semana', 'Se recomendó aumentar a 5 días.', @u_ovalle),
(@p1, 'alimentacion', 'Dieta alta en carbohidratos refinados', 'Diaria', 'Referido a nutrición.', @u_ovalle),
(@p1, 'alcohol', 'Consumo social de cerveza', '1 a 2 veces al mes', NULL, @u_ovalle),
(@p2, 'ejercicio', 'Yoga y natación', '2 veces por semana', NULL, @u_ovalle),
(@p2, 'tabaco', 'No fuma', 'Nunca', NULL, @u_ovalle),
(@p3, 'tabaco', 'Exfumador desde 2019 (40 paquetes-año)', 'Suspendido', 'Cesación sostenida.', @u_ovalle),
(@p3, 'otras_sustancias', 'Exposición a humo de leña durante décadas', 'Histórica', NULL, @u_ovalle),
(@p5, 'tabaco', 'Fuma 5 cigarrillos al día', 'Diaria', 'Aconsejado a suspender.', @u_ovalle),
(@p5, 'alcohol', 'Consumo de licor los fines de semana', '2 a 4 copas por semana', NULL, @u_ovalle),
(@p7, 'tabaco', 'Exfumador desde el infarto', 'Suspendido', NULL, @u_pineda),
(@p7, 'ejercicio', 'Rehabilitación cardíaca completada, camina a diario', 'Diaria', NULL, @u_pineda),
(@p8, 'sueno', 'Duerme entre 5 y 6 horas', 'Diaria', 'Insomnio de mantenimiento ocasional.', @u_ovalle),
(@p10,'sueno', 'Horario irregular por trabajo periodístico', 'Variable', 'Relacionado con las crisis de migraña.', @u_ovalle);

-- ------------------------------------------------------------------ Antecedentes familiares
INSERT INTO antecedentes_familiares (paciente_id, parentesco, condicion, edad_inicio, fallecido, observaciones, registrado_por) VALUES
(@p1, 'padre', 'Diabetes mellitus tipo 2', 50, 1, NULL, @u_ovalle),
(@p1, 'madre', 'Hipertensión arterial', 55, 0, NULL, @u_ovalle),
(@p1, 'hermano', 'Infarto agudo de miocardio', 52, 0, NULL, @u_ovalle),
(@p2, 'madre', 'Asma bronquial', 20, 0, NULL, @u_ovalle),
(@p3, 'padre', 'Cáncer de próstata', 70, 1, NULL, @u_ovalle),
(@p4, 'madre', 'Preeclampsia en el embarazo', 28, 0, NULL, @u_estrada),
(@p5, 'padre', 'Dislipidemia', 45, 0, NULL, @u_ovalle),
(@p5, 'abuelo_paterno', 'Infarto agudo de miocardio', 60, 1, NULL, @u_ovalle),
(@p7, 'padre', 'Cardiopatía isquémica', 58, 1, 'Fallecido por infarto.', @u_pineda),
(@p8, 'madre', 'Hipotiroidismo', 40, 0, NULL, @u_ovalle),
(@p8, 'madre', 'Osteoporosis', 62, 0, NULL, @u_ovalle),
(@p10,'madre', 'Migraña', 25, 0, NULL, @u_ovalle),
(@p6, 'padre', 'Asma bronquial', 10, 0, NULL, @u_merida);

-- ------------------------------------------------------------------ Consultas, diagnósticos, tratamientos
-- Paciente 1
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, saturacion_o2, peso_kg, talla_cm, examen_fisico, plan, notas_internas, creado_por)
VALUES (@p1, @m_ovalle, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 MONTH), '08:30:00'), 'realizada', 'Control de diabetes e hipertensión', 'Refiere buen apego al tratamiento, ocasional poliuria. Sin cefalea ni dolor torácico.', 138, 86, 76, 36.6, 97, 88.5, 172.0, 'Abdomen globoso, sin edemas. Pulsos pedios presentes.', 'Solicitar HbA1c y perfil lipídico. Reforzar dieta y actividad física.', 'Paciente poco constante con la dieta.', @u_ovalle);
SET @c := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c, 'E11.9', 'Diabetes mellitus tipo 2 sin complicaciones', 'principal'), (@c, 'I10', 'Hipertensión arterial esencial', 'secundario');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c, 'indicacion', 'Dieta hipocalórica baja en carbohidratos', NULL, 'Diaria', 'Continua'), (@c, 'procedimiento', 'Laboratorios: HbA1c, perfil lipídico, creatinina', NULL, NULL, 'Antes del próximo control');

INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, saturacion_o2, peso_kg, talla_cm, examen_fisico, plan, notas_internas, creado_por)
VALUES (@p1, @m_ovalle, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 20 DAY), '09:15:00'), 'realizada', 'Revisión de resultados de laboratorio', 'Sin síntomas nuevos. Trae resultados de laboratorio.', 132, 82, 72, 36.5, 98, 86.0, 172.0, 'Sin hallazgos nuevos. Pérdida de 2.5 kg desde el último control.', 'HbA1c en 7.4 %. Ajustar metformina. Control en 3 meses.', NULL, @u_ovalle);
SET @c1b := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c1b, 'E11.9', 'Diabetes mellitus tipo 2 sin complicaciones', 'principal');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c1b, 'medicamento', 'Metformina', '850 mg', 'Cada 12 horas con alimentos', '3 meses');

INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, creado_por)
VALUES (@p1, @m_ovalle, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 70 DAY), '10:00:00'), 'programada', 'Control trimestral de diabetes e hipertensión', @u_ovalle);

-- Paciente 2
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, saturacion_o2, peso_kg, talla_cm, examen_fisico, plan, creado_por)
VALUES (@p2, @m_ovalle, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 45 DAY), '14:20:00'), 'realizada', 'Tos y sibilancias de tres días', 'Cuadro posterior a cambio de clima, sin fiebre. Usó salbutamol con alivio parcial.', 112, 70, 88, 36.8, 95, 61.0, 165.0, 'Sibilancias espiratorias difusas, sin tiraje.', 'Salbutamol a demanda y budesonida inhalada por 4 semanas.', @u_ovalle);
SET @c2 := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c2, 'J45.0', 'Asma bronquial con exacerbación leve', 'principal');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c2, 'medicamento', 'Budesonida inhalada', '200 mcg', '2 inhalaciones cada 12 horas', '4 semanas'), (@c2, 'medicamento', 'Salbutamol inhalado', '100 mcg', '2 inhalaciones si hay sibilancias', 'A demanda');

-- Paciente 3
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, saturacion_o2, peso_kg, talla_cm, examen_fisico, plan, creado_por)
VALUES (@p3, @m_ovalle, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '10:45:00'), 'realizada', 'Disnea de esfuerzo progresiva', 'Disnea al subir una cuadra, tos productiva matutina. Sin fiebre.', 128, 78, 84, 36.4, 91, 64.0, 163.0, 'Tórax en barril, espiración prolongada, sibilancias basales.', 'Espirometría de control. Ajuste de broncodilatadores. Continuar abstinencia de tabaco.', @u_ovalle);
SET @c3 := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c3, 'J44.9', 'Enfermedad pulmonar obstructiva crónica', 'principal');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c3, 'medicamento', 'Tiotropio inhalado', '18 mcg', 'Una cápsula inhalada al día', 'Continuo'), (@c3, 'procedimiento', 'Espirometría con broncodilatador', NULL, NULL, 'Próximo control');
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, creado_por)
VALUES (@p3, @m_ovalle, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 12 DAY), '15:30:00'), 'programada', 'Resultado de espirometría y control de EPOC', @u_ovalle);

-- Paciente 4 (control ginecológico)
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, peso_kg, talla_cm, examen_fisico, plan, creado_por)
VALUES (@p4, @m_estrada, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '08:50:00'), 'realizada', 'Control ginecológico anual', 'Sin molestias. Ciclos regulares.', 108, 68, 70, 36.5, 58.0, 160.0, 'Examen ginecológico sin hallazgos.', 'Citología tomada. Continuar esquema de vacuna contra VPH.', @u_estrada);
SET @c4 := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c4, 'Z01.4', 'Examen ginecológico de rutina', 'principal');
INSERT INTO tratamientos (consulta_id, tipo, descripcion) VALUES (@c4, 'indicacion', 'Aplicar tercera dosis de vacuna contra VPH');

-- Paciente 5
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, peso_kg, talla_cm, examen_fisico, plan, creado_por)
VALUES (@p5, @m_ovalle, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 WEEK), '11:20:00'), 'realizada', 'Control de colesterol y acidez', 'Acidez ocasional posprandial. Sin pérdida de peso.', 126, 80, 78, 36.6, 92.0, 178.0, 'Abdomen blando, sin dolor a la palpación.', 'Iniciar estatina y cambios de estilo de vida. Repetir perfil lipídico en 12 semanas.', @u_ovalle);
SET @c5 := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c5, 'E78.2', 'Dislipidemia mixta', 'principal'), (@c5, 'K29.5', 'Gastritis crónica', 'secundario');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c5, 'medicamento', 'Atorvastatina', '20 mg', 'Una tableta por la noche', '12 semanas');

-- Paciente 6 (pediatría)
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, frecuencia_cardiaca, temperatura, saturacion_o2, peso_kg, talla_cm, examen_fisico, plan, creado_por)
VALUES (@p6, @m_merida, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 WEEK), '14:40:00'), 'realizada', 'Control pediátrico y tos nocturna', 'Tos seca nocturna de una semana. Sin fiebre.', 96, 36.7, 98, 25.0, 126.0, 'Faringe levemente hiperémica. Pulmones limpios.', 'Medidas de control ambiental y observación. Reevaluar si persiste.', @u_merida);
SET @c6 := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c6, 'J00', 'Rinofaringitis aguda', 'principal');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c6, 'medicamento', 'Acetaminofén en jarabe', '10 ml', 'Cada 8 horas si hay fiebre o molestia', '3 días');

-- Paciente 7 (cardiología)
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, saturacion_o2, peso_kg, talla_cm, examen_fisico, plan, notas_internas, creado_por)
VALUES (@p7, @m_pineda, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 WEEK), '09:40:00'), 'realizada', 'Control cardiológico semestral', 'Asintomático. Sin dolor torácico ni disnea.', 124, 76, 62, 36.5, 98, 79.0, 170.0, 'Ruidos cardíacos rítmicos sin soplos.', 'Continuar clopidogrel, atorvastatina y bisoprolol. Electrocardiograma sin cambios.', 'No usar AINE ni aspirina por antecedente de sangrado.', @u_pineda);
SET @c7 := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c7, 'I25.1', 'Enfermedad aterosclerótica del corazón', 'principal');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c7, 'medicamento', 'Clopidogrel', '75 mg', 'Una tableta al día', 'Continuo'), (@c7, 'medicamento', 'Bisoprolol', '5 mg', 'Una tableta al día', 'Continuo');
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, creado_por)
VALUES (@p7, @m_pineda, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '16:00:00'), 'programada', 'Ecocardiograma de control', @u_pineda);

-- Paciente 8
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, peso_kg, talla_cm, examen_fisico, plan, creado_por)
VALUES (@p8, @m_ovalle, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 WEEK), '11:00:00'), 'realizada', 'Resultados de densitometría', 'Dolor lumbar mecánico ocasional. Sin fracturas previas.', 118, 74, 70, 36.5, 64.0, 158.0, 'Sin dolor a la palpación vertebral.', 'Inicio de calcio con vitamina D y alendronato. Control de TSH en 3 meses.', @u_ovalle);
SET @c8 := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c8, 'M81.0', 'Osteoporosis posmenopáusica', 'principal'), (@c8, 'E03.9', 'Hipotiroidismo primario', 'secundario');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c8, 'medicamento', 'Alendronato', '70 mg', 'Una tableta por semana en ayunas', '6 meses'), (@c8, 'medicamento', 'Carbonato de calcio con vitamina D', '600 mg / 400 UI', 'Cada 12 horas', 'Continuo');

-- Paciente 9
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, frecuencia_cardiaca, temperatura, saturacion_o2, peso_kg, talla_cm, examen_fisico, plan, creado_por)
VALUES (@p9, @m_merida, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 WEEK), '15:10:00'), 'realizada', 'Fiebre de dos días', 'Fiebre de hasta 38.8 °C, rechazo parcial del alimento, sin vómitos.', 118, 38.4, 98, 14.2, 96.0, 'Faringe hiperémica con exudado, adenopatías cervicales pequeñas.', 'Amoxicilina por 10 días e hidratación. Control si persiste fiebre a las 48 horas.', @u_merida);
SET @c9 := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c9, 'J03.9', 'Amigdalitis aguda', 'principal');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c9, 'medicamento', 'Amoxicilina suspensión', '5 ml', 'Cada 8 horas', '10 días');
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, creado_por)
VALUES (@p9, @m_merida, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 25 DAY), '08:10:00'), 'programada', 'Control de niño sano y segunda dosis de triple viral', @u_merida);

-- Paciente 10
INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, peso_kg, talla_cm, examen_fisico, plan, creado_por)
VALUES (@p10, @m_ovalle, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 WEEK), '10:20:00'), 'realizada', 'Aumento en la frecuencia de migrañas', 'Cinco episodios en el último mes con aura visual.', 110, 70, 74, 36.6, 57.0, 163.0, 'Examen neurológico normal.', 'Iniciar profilaxis con propranolol. Diario de cefaleas.', @u_ovalle);
SET @c10 := LAST_INSERT_ID();
INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (@c10, 'G43.1', 'Migraña con aura', 'principal');
INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (@c10, 'medicamento', 'Propranolol', '40 mg', 'Cada 12 horas', '3 meses');

-- ------------------------------------------------------------------ Medicamentos vigentes
INSERT INTO medicamentos (paciente_id, consulta_id, nombre, dosis, via, frecuencia, motivo, fecha_inicio, fecha_fin, activo, observaciones, registrado_por) VALUES
(@p1, @c1b, 'Metformina', '850 mg', 'oral', 'Cada 12 horas con alimentos', 'Diabetes mellitus tipo 2', DATE_SUB(CURDATE(), INTERVAL 20 DAY), NULL, 1, 'Dosis aumentada en la última consulta.', @u_ovalle),
(@p1, NULL, 'Enalapril', '10 mg', 'oral', 'Una vez al día', 'Hipertensión arterial', '2014-06-15', NULL, 1, NULL, @u_ovalle),
(@p1, NULL, 'Atorvastatina', '20 mg', 'oral', 'Una tableta por la noche', 'Prevención cardiovascular', '2019-01-10', NULL, 1, NULL, @u_ovalle),
(@p2, @c2, 'Salbutamol inhalado', '100 mcg', 'inhalada', '2 inhalaciones si hay sibilancias', 'Asma bronquial', '2005-01-01', NULL, 1, NULL, @u_ovalle),
(@p2, @c2, 'Budesonida inhalada', '200 mcg', 'inhalada', 'Cada 12 horas', 'Control de asma', DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_SUB(CURDATE(), INTERVAL 17 DAY), 0, 'Ciclo de 4 semanas finalizado.', @u_ovalle),
(@p3, @c3, 'Tiotropio inhalado', '18 mcg', 'inhalada', 'Una cápsula al día', 'EPOC', DATE_SUB(CURDATE(), INTERVAL 3 MONTH), NULL, 1, NULL, @u_ovalle),
(@p3, NULL, 'Tamsulosina', '0.4 mg', 'oral', 'Una cápsula por la noche', 'Hipertrofia prostática benigna', '2021-07-20', NULL, 1, NULL, @u_ovalle),
(@p5, @c5, 'Atorvastatina', '20 mg', 'oral', 'Una tableta por la noche', 'Dislipidemia', DATE_SUB(CURDATE(), INTERVAL 6 WEEK), NULL, 1, NULL, @u_ovalle),
(@p5, NULL, 'Omeprazol', '20 mg', 'oral', 'Una cápsula en ayunas si hay acidez', 'Gastritis crónica', '2015-05-10', NULL, 1, 'Uso a demanda.', @u_ovalle),
(@p6, @c6, 'Acetaminofén jarabe', '10 ml', 'oral', 'Cada 8 horas si hay molestia', 'Rinofaringitis aguda', DATE_SUB(CURDATE(), INTERVAL 5 WEEK), DATE_SUB(CURDATE(), INTERVAL 32 DAY), 0, NULL, @u_merida),
(@p7, @c7, 'Clopidogrel', '75 mg', 'oral', 'Una tableta al día', 'Cardiopatía isquémica con stent', '2021-03-24', NULL, 1, 'No suspender sin indicación del cardiólogo.', @u_pineda),
(@p7, @c7, 'Bisoprolol', '5 mg', 'oral', 'Una tableta al día', 'Cardiopatía isquémica', '2021-03-24', NULL, 1, NULL, @u_pineda),
(@p7, NULL, 'Atorvastatina', '40 mg', 'oral', 'Una tableta por la noche', 'Cardiopatía isquémica', '2021-03-24', NULL, 1, NULL, @u_pineda),
(@p8, NULL, 'Levotiroxina', '75 mcg', 'oral', 'Una tableta en ayunas', 'Hipotiroidismo', '2011-10-10', NULL, 1, NULL, @u_ovalle),
(@p8, @c8, 'Alendronato', '70 mg', 'oral', 'Una tableta por semana en ayunas', 'Osteoporosis', DATE_SUB(CURDATE(), INTERVAL 5 WEEK), NULL, 1, 'Mantener posición erguida 30 minutos.', @u_ovalle),
(@p8, @c8, 'Carbonato de calcio con vitamina D', '600 mg / 400 UI', 'oral', 'Cada 12 horas', 'Osteoporosis', DATE_SUB(CURDATE(), INTERVAL 5 WEEK), NULL, 1, NULL, @u_ovalle),
(@p9, @c9, 'Amoxicilina suspensión', '5 ml', 'oral', 'Cada 8 horas', 'Amigdalitis aguda', DATE_SUB(CURDATE(), INTERVAL 3 WEEK), DATE_SUB(CURDATE(), INTERVAL 11 DAY), 0, 'Tratamiento completado.', @u_merida),
(@p10,@c10, 'Propranolol', '40 mg', 'oral', 'Cada 12 horas', 'Profilaxis de migraña', DATE_SUB(CURDATE(), INTERVAL 8 WEEK), NULL, 1, NULL, @u_ovalle);

-- Coherencia de fechas de los datos de demostración: cada registro se crea cuando ocurrió, no al instalar.
UPDATE consultas SET creado_en = DATE_ADD(fecha_hora, INTERVAL 20 + (id % 25) MINUTE)
 WHERE estado = 'realizada' AND creado_en > DATE_ADD(fecha_hora, INTERVAL 1 DAY);
UPDATE consultas SET creado_en = GREATEST(DATE_SUB(fecha_hora, INTERVAL 30 + (id % 20) DAY), DATE_SUB(NOW(), INTERVAL 40 DAY))
 WHERE estado = 'programada' AND creado_en > DATE_SUB(fecha_hora, INTERVAL 1 DAY) AND DATE_SUB(fecha_hora, INTERVAL 30 DAY) < NOW();
UPDATE pacientes p JOIN (SELECT paciente_id, MIN(fecha_hora) f FROM consultas GROUP BY paciente_id) c ON c.paciente_id = p.id
   SET p.creado_en = TIMESTAMP(DATE(DATE_SUB(c.f, INTERVAL 7 + (p.id % 60) DAY)), '08:40:00')
 WHERE p.creado_en > c.f;
UPDATE usuarios u JOIN pacientes p ON p.usuario_id = u.id SET u.creado_en = DATE_ADD(p.creado_en, INTERVAL 15 MINUTE) WHERE u.creado_en > DATE_ADD(p.creado_en, INTERVAL 30 DAY);
UPDATE consultas SET creado_en = TIMESTAMP(DATE(creado_en), '10:10:00') WHERE HOUR(creado_en) NOT BETWEEN 7 AND 18 AND DATE(creado_en) < CURDATE();

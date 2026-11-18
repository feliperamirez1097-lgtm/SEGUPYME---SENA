USE segupyme_v15;
INSERT INTO Concepto (id_concepto, codigo, nombre, codigo_pila, columna_destino, afecta_ibc, requiere_dias, requiere_soporte, porcentaje_fijo, suma_dominical, incluye_hora_base) VALUES
(1,'SALARIO','Salario ordinario del periodo',NULL,'ibc',TRUE,FALSE,FALSE,NULL,FALSE,FALSE),
(2,'INCAPACIDAD','Incapacidad','IGE','dias_incapacidad',FALSE,TRUE,TRUE,NULL,FALSE,FALSE),
(3,'LICENCIA','Licencia','SLN','dias_licencia',FALSE,TRUE,TRUE,NULL,FALSE,FALSE),
(4,'VACACIONES','Vacaciones','VAC','dias_vacaciones',FALSE,TRUE,FALSE,NULL,FALSE,FALSE),
(5,'RETIRO','Retiro','RET','fecha_retiro',FALSE,FALSE,FALSE,NULL,FALSE,FALSE),
(6,'HORA_EXTRA_DIURNA','Hora extra diurna','AJU','ibc_variable',TRUE,FALSE,FALSE,25,FALSE,TRUE),
(7,'HORA_EXTRA_NOCTURNA','Hora extra nocturna','AJU','ibc_variable',TRUE,FALSE,FALSE,75,FALSE,TRUE),
(8,'RECARGO_NOCTURNO','Recargo nocturno','AJU','ibc_variable',TRUE,FALSE,FALSE,35,FALSE,FALSE),
(9,'RECARGO_DOMINICAL_FESTIVO_DIURNO','Recargo dominical o festivo diurno','AJU','ibc_variable',TRUE,FALSE,FALSE,0,TRUE,FALSE),
(10,'RECARGO_DOMINICAL_FESTIVO_NOCTURNO','Recargo dominical o festivo nocturno','AJU','ibc_variable',TRUE,FALSE,FALSE,35,TRUE,FALSE),
(11,'HORA_EXTRA_DIURNA_DOMINICAL_FESTIVA','Hora extra diurna dominical o festiva','AJU','ibc_variable',TRUE,FALSE,FALSE,25,TRUE,TRUE),
(12,'HORA_EXTRA_NOCTURNA_DOMINICAL_FESTIVA','Hora extra nocturna dominical o festiva','AJU','ibc_variable',TRUE,FALSE,FALSE,75,TRUE,TRUE);

INSERT INTO Parametro_Legal (vigente_desde, salario_minimo, divisor_hora, recargo_dominical, tarifa_salud_empleador, tarifa_salud_empleado, tarifa_pension_empleador, tarifa_pension_empleado, tarifa_caja, tarifa_sena_icbf, tarifa_arl_i, tarifa_arl_ii, tarifa_arl_iii, tarifa_arl_iv, tarifa_arl_v) VALUES
('2026-01-01', 1750905, 220, 80, 8.50, 4.00, 12.00, 4.00, 4.00, 5.00, 0.522, 1.044, 2.436, 4.350, 6.960),
('2026-07-01', 1750905, 220, 90, 8.50, 4.00, 12.00, 4.00, 4.00, 5.00, 0.522, 1.044, 2.436, 4.350, 6.960),
('2026-07-15', 1750905, 210, 90, 8.50, 4.00, 12.00, 4.00, 4.00, 5.00, 0.522, 1.044, 2.436, 4.350, 6.960);

INSERT INTO Empresa (nit, tipo_empresa, subtipo_entidad, razon_social, representante_legal, correo, fecha_registro) VALUES
('900000001','CONTADORA',NULL,'Contadores Asociados SAS','Carlos Ramirez','contacto@contadores-prueba.co','2026-09-01'),
('800000001','SERVICIOS','EPS','EPS Sanitas (prueba)','N/A','contacto@eps-prueba.co','2026-09-01'),
('800000002','SERVICIOS','AFP','Porvenir AFP (prueba)','N/A','contacto@afp-prueba.co','2026-09-01'),
('800000003','SERVICIOS','ARL','ARL Sura (prueba)','N/A','contacto@arl-prueba.co','2026-09-01'),
('800000004','SERVICIOS','CCF','Compensar CCF (prueba)','N/A','contacto@ccf-prueba.co','2026-09-01');
INSERT INTO Empresa (nit, tipo_empresa, razon_social, representante_legal, correo, fecha_registro, contador_id, honorarios_contador, exonerada_art_114_1, clase_riesgo_arl) VALUES
('900111222','CLIENTE','Textiles Andina SAS','Laura Gomez','contacto@textilesandina-prueba.co','2026-09-01','900000001',800000,TRUE,'II'),
('900333444','CLIENTE','Panaderia El Trigal SAS','Mario Ruiz','contacto@eltrigal-prueba.co','2026-09-15','900000001',600000,FALSE,'I');

INSERT INTO Cargos (nombre_cargo, empresa_id) VALUES
('Contador externo','900000001'),('Gerente','900111222'),('Operario','900111222'),('Gerente','900333444'),('Panadero','900333444'),('Contador interno','900333444');

INSERT INTO Usuario (cedula, nombre, correo_acceso, contrasena_hash, id_rol, cargo_id, salario, tipo_contrato, fecha_vinculacion, estado_laboral, estado_cuenta, autorizacion_datos_en) VALUES
('1078901234','Carlos Ramirez','carlos@contadores-prueba.co','$argon2id$PRUEBA',2,(SELECT id_cargo FROM Cargos WHERE nombre_cargo='Contador externo'),4000000,'TERMINO_INDEFINIDO','2026-09-01','ACTIVO','ACTIVA','2026-09-01 08:00:00'),
('52000111','Laura Gomez','laura@textilesandina-prueba.co','$argon2id$PRUEBA',1,(SELECT id_cargo FROM Cargos WHERE nombre_cargo='Gerente' AND empresa_id='900111222'),3500000,'TERMINO_INDEFINIDO','2026-09-01','ACTIVO','ACTIVA','2026-09-01 09:00:00'),
('1012345678','Ana Perez','ana@textilesandina-prueba.co','$argon2id$PRUEBA',3,(SELECT id_cargo FROM Cargos WHERE nombre_cargo='Operario' AND empresa_id='900111222'),1800000,'TERMINO_INDEFINIDO','2026-09-01','ACTIVO','ACTIVA','2026-09-01 10:00:00'),
('1023456789','Juan Torres','juan@textilesandina-prueba.co','$argon2id$PRUEBA',3,(SELECT id_cargo FROM Cargos WHERE nombre_cargo='Operario' AND empresa_id='900111222'),1750905,'TERMINO_INDEFINIDO','2026-09-01','ACTIVO','ACTIVA','2026-09-01 10:30:00'),
('79000222','Mario Ruiz','mario@eltrigal-prueba.co','$argon2id$PRUEBA',1,(SELECT id_cargo FROM Cargos WHERE nombre_cargo='Gerente' AND empresa_id='900333444'),3000000,'TERMINO_INDEFINIDO','2026-09-15','ACTIVO','ACTIVA','2026-09-15 09:00:00'),
('1056789012','Sofia Martinez','sofia@eltrigal-prueba.co','$argon2id$PRUEBA',3,(SELECT id_cargo FROM Cargos WHERE nombre_cargo='Panadero'),1800000,'TERMINO_INDEFINIDO','2026-09-15','ACTIVO','ACTIVA','2026-09-16 10:00:00'),
('1045678901','Pedro Diaz',NULL,NULL,3,(SELECT id_cargo FROM Cargos WHERE nombre_cargo='Operario' AND empresa_id='900111222'),1900000,'TERMINO_FIJO','2026-09-20','ACTIVO','PENDIENTE_ACTIVACION',NULL),
('1089012345','Marta Londono','marta@eltrigal-prueba.co','$argon2id$PRUEBA',2,(SELECT id_cargo FROM Cargos WHERE nombre_cargo='Contador interno'),2600000,'TERMINO_INDEFINIDO','2026-09-15','ACTIVO','ACTIVA','2026-09-15 10:00:00');
INSERT INTO Usuario (nombre, correo_acceso, contrasena_hash, id_rol, estado_cuenta, autorizacion_datos_en) VALUES ('Super Usuario','soporte@segupyme-prueba.co','$argon2id$PRUEBA',4,'ACTIVA','2026-09-01 07:00:00');

-- Afiliaciones: las cuatro de cada persona, menos la ARL de Pedro (pendiente)
INSERT INTO Afiliacion (usuario_id, tipo, entidad_id, fecha_inicio, estado)
SELECT u.id_usuario, a.tipo, a.entidad, u.fecha_vinculacion, 'APROBADA'
  FROM Usuario u
  JOIN (SELECT 'EPS' AS tipo, '800000001' AS entidad UNION ALL SELECT 'AFP','800000002' UNION ALL SELECT 'ARL','800000003' UNION ALL SELECT 'CCF','800000004') a
 WHERE u.cedula IN ('52000111','1012345678','1023456789','79000222','1056789012','1089012345') OR (u.cedula = '1045678901' AND a.tipo <> 'ARL');
INSERT INTO Afiliacion (usuario_id, tipo, entidad_id, fecha_inicio, estado)
SELECT id_usuario, 'ARL', '800000003', fecha_vinculacion, 'PENDIENTE' FROM Usuario WHERE cedula = '1045678901';

-- Novedades de octubre de 2026 (periodo y valor los calcula el trigger)
SET @ana = (SELECT id_usuario FROM Usuario WHERE cedula='1012345678'); SET @juan = (SELECT id_usuario FROM Usuario WHERE cedula='1023456789'); SET @sofia = (SELECT id_usuario FROM Usuario WHERE cedula='1056789012');
INSERT INTO Novedad (empleado_id, concepto_id, fecha_inicio, horas, estado) VALUES (@juan, 6, '2026-10-10', 4, 'APROBADA');
INSERT INTO Novedad (empleado_id, concepto_id, fecha_inicio, fecha_fin, estado, soporte_clave, soporte_sha256, soporte_tipo, soporte_bytes)
VALUES (@ana, 2, '2026-10-05', '2026-10-07', 'APROBADA', 'a3f5c9d17b2e4f6a8c0d1e2f3a4b5c6d', SHA2('certificado-de-incapacidad-de-prueba',256), 'PDF', 182000);
INSERT INTO Novedad (empleado_id, concepto_id, fecha_inicio, horas, estado) VALUES (@sofia, 9, '2026-10-12', 8, 'APROBADA');
INSERT INTO Novedad (empleado_id, concepto_id, fecha_inicio, horas, estado) VALUES (@ana, 7, '2026-10-14', 2, 'PENDIENTE');

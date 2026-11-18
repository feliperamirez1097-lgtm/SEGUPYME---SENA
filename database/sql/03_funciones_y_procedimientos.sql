USE segupyme_v15;

DELIMITER $$

CREATE FUNCTION fn_empresa_usuario(p_usuario INT) RETURNS VARCHAR(15)
NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER
BEGIN
  DECLARE v VARCHAR(15);
  SELECT c.empresa_id INTO v FROM Usuario u JOIN Cargos c ON c.id_cargo = u.cargo_id WHERE u.id_usuario = p_usuario;
  RETURN v;
END$$

CREATE FUNCTION fn_parametro_vigente(p_fecha DATE) RETURNS INT
NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER
BEGIN
  DECLARE v INT;
  SELECT id_parametro INTO v FROM Parametro_Legal WHERE vigente_desde <= p_fecha ORDER BY vigente_desde DESC LIMIT 1;
  RETURN v;
END$$

CREATE FUNCTION fn_porcentaje_recargo(p_concepto SMALLINT, p_fecha DATE) RETURNS DECIMAL(5,2)
NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER
BEGIN
  DECLARE v DECIMAL(5,2);
  SELECT c.porcentaje_fijo + IF(c.suma_dominical, g.recargo_dominical, 0) INTO v
    FROM Concepto c JOIN Parametro_Legal g ON g.id_parametro = fn_parametro_vigente(p_fecha)
   WHERE c.id_concepto = p_concepto;
  RETURN v;
END$$

CREATE FUNCTION fn_afiliaciones_completas(p_usuario INT, p_fecha DATE) RETURNS TINYINT
NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER
BEGIN
  DECLARE v INT;
  SELECT COUNT(DISTINCT tipo) INTO v FROM Afiliacion
   WHERE usuario_id = p_usuario AND estado = 'APROBADA' AND fecha_inicio <= p_fecha AND (fecha_fin IS NULL OR fecha_fin >= p_fecha);
  RETURN v = 4;
END$$

CREATE FUNCTION fn_rol_usuario(p_usuario INT) RETURNS INT
NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER
BEGIN
  DECLARE v INT;
  SELECT id_rol INTO v FROM Usuario WHERE id_usuario = p_usuario AND estado_cuenta = 'ACTIVA';
  RETURN v;
END$$

CREATE FUNCTION fn_empresa_activa(p_empresa VARCHAR(15)) RETURNS TINYINT
NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER
BEGIN
  DECLARE v INT;
  SELECT COUNT(*) INTO v FROM Empresa WHERE nit = p_empresa AND estado = 'ACTIVA';
  RETURN v > 0;
END$$

CREATE FUNCTION fn_atiende(p_contador INT, p_empresa VARCHAR(15)) RETURNS TINYINT
NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER
BEGIN
  DECLARE v_propia VARCHAR(15);
  IF fn_rol_usuario(p_contador) <> 2 THEN RETURN 0; END IF;
  SET v_propia = fn_empresa_usuario(p_contador);
  IF v_propia IS NULL OR fn_empresa_activa(v_propia) = 0 THEN RETURN 0; END IF;
  IF v_propia = p_empresa THEN RETURN 1; END IF;
  IF EXISTS (SELECT 1 FROM Empresa WHERE nit = p_empresa AND contador_id = v_propia) THEN RETURN 1; END IF;
  RETURN 0;
END$$

CREATE FUNCTION fn_empresa_planilla(p_planilla INT) RETURNS VARCHAR(15)
NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER
BEGIN
  DECLARE v VARCHAR(15);
  SELECT MIN(c.empresa_id) INTO v
    FROM Detalle_Planilla d JOIN Novedad n ON n.id_novedad = d.novedad_id JOIN Usuario u ON u.id_usuario = n.empleado_id JOIN Cargos c ON c.id_cargo = u.cargo_id
   WHERE d.planilla_id = p_planilla;
  RETURN v;
END$$

CREATE PROCEDURE sp_validar_usuario(IN p_rol INT, IN p_cargo INT, IN p_salario DECIMAL(12,2), IN p_contrato VARCHAR(30), IN p_fecha DATE, IN p_estado VARCHAR(10))
SQL SECURITY DEFINER
BEGIN
  DECLARE v_tipo VARCHAR(15);
  DECLARE v_minimo DECIMAL(12,2);
  IF p_rol = 4 THEN
    IF p_cargo IS NOT NULL OR p_salario IS NOT NULL OR p_contrato IS NOT NULL OR p_fecha IS NOT NULL OR p_estado IS NOT NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Super Usuario no tiene cargo ni datos laborales';
    END IF;
  ELSE
    SET v_tipo = (SELECT e.tipo_empresa FROM Cargos c JOIN Empresa e ON e.nit = c.empresa_id WHERE c.id_cargo = p_cargo);
    IF v_tipo IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cargo no existe';
    END IF;
    IF p_rol IN (1,3) AND v_tipo <> 'CLIENTE' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Administrador y Empleado deben tener un cargo en una empresa cliente';
    END IF;
    IF p_rol = 2 AND v_tipo NOT IN ('CLIENTE','CONTADORA') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Contador debe tener un cargo en una empresa cliente o contadora';
    END IF;
    IF p_salario IS NULL OR p_contrato IS NULL OR p_fecha IS NULL OR p_estado IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Faltan los datos laborales: salario, tipo de contrato, fecha de vinculacion y estado laboral';
    END IF;
    SET v_minimo = (SELECT salario_minimo FROM Parametro_Legal WHERE id_parametro = fn_parametro_vigente(COALESCE(p_fecha, CURDATE())));
    IF p_contrato <> 'APRENDIZAJE' AND p_salario < v_minimo THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El salario no puede ser menor al salario minimo vigente';
    END IF;
  END IF;
END$$

CREATE PROCEDURE sp_calcular_planilla(IN p_planilla INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_estado VARCHAR(10);
  DECLARE v_salario SMALLINT;
  SET v_estado = (SELECT estado FROM Planilla WHERE id_planilla = p_planilla);
  IF v_estado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla no existe'; END IF;
  IF v_estado <> 'BORRADOR' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se calculan planillas en BORRADOR'; END IF;
  SET v_salario = (SELECT id_concepto FROM Concepto WHERE codigo = 'SALARIO');
  UPDATE Detalle_Planilla dd
  JOIN (
    SELECT d.id_detalle AS id,
           CASE WHEN c.id_concepto = v_salario THEN LEAST(GREATEST(n.valor, g.salario_minimo), 25 * g.salario_minimo)
                WHEN c.afecta_ibc THEN n.valor ELSE 0 END AS ibc_calc,
           g.tarifa_salud_empleado + IF(e.exonerada_art_114_1 AND ns.valor < 10 * g.salario_minimo, 0, g.tarifa_salud_empleador) AS t_salud,
           g.tarifa_pension_empleado + g.tarifa_pension_empleador AS t_pension,
           CASE e.clase_riesgo_arl WHEN 'I' THEN g.tarifa_arl_i WHEN 'II' THEN g.tarifa_arl_ii WHEN 'III' THEN g.tarifa_arl_iii
                                   WHEN 'IV' THEN g.tarifa_arl_iv ELSE g.tarifa_arl_v END AS t_arl,
           g.tarifa_caja + IF(e.exonerada_art_114_1 AND ns.valor < 10 * g.salario_minimo, 0, g.tarifa_sena_icbf) AS t_para
      FROM Detalle_Planilla d
      JOIN Planilla p ON p.id_planilla = d.planilla_id
      JOIN Parametro_Legal g ON g.id_parametro = p.parametro_id
      JOIN Novedad n ON n.id_novedad = d.novedad_id
      JOIN Concepto c ON c.id_concepto = n.concepto_id
      JOIN Usuario u ON u.id_usuario = n.empleado_id
      JOIN Cargos ca ON ca.id_cargo = u.cargo_id
      JOIN Empresa e ON e.nit = ca.empresa_id
      JOIN Novedad ns ON ns.empleado_id = n.empleado_id AND ns.periodo = n.periodo AND ns.concepto_id = v_salario
     WHERE d.planilla_id = p_planilla
  ) q ON q.id = dd.id_detalle
  SET dd.ibc = q.ibc_calc,
      dd.valor_salud = ROUND(q.ibc_calc * q.t_salud / 100, 2),
      dd.valor_pension = ROUND(q.ibc_calc * q.t_pension / 100, 2),
      dd.valor_arl = ROUND(q.ibc_calc * q.t_arl / 100, 2),
      dd.valor_parafiscales = ROUND(q.ibc_calc * q.t_para / 100, 2)
  WHERE dd.planilla_id = p_planilla;
END$$

CREATE PROCEDURE sp_generar_planilla(IN p_actor INT, IN p_empresa VARCHAR(15), IN p_periodo DATE, IN p_fecha_limite DATE, OUT p_id INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_periodo DATE; DECLARE v_param INT; DECLARE v_salario SMALLINT; DECLARE v_n INT; DECLARE v_tipo VARCHAR(15);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien genera la planilla no esta activa'; END IF;
  IF NOT (v_rol = 4 OR (v_rol = 2 AND fn_atiende(p_actor, p_empresa) = 1)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el Contador que atiende la empresa o el Super Usuario generan la planilla';
  END IF;
  SET v_tipo = (SELECT tipo_empresa FROM Empresa WHERE nit = p_empresa);
  IF v_tipo IS NULL OR v_tipo <> 'CLIENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla se genera para una empresa cliente'; END IF;
  IF fn_empresa_activa(p_empresa) = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa esta inactiva'; END IF;
  SET v_periodo = DATE_SUB(p_periodo, INTERVAL DAYOFMONTH(p_periodo) - 1 DAY);
  SET v_param = fn_parametro_vigente(LAST_DAY(v_periodo));
  IF v_param IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay vigencia legal para ese periodo'; END IF;
  IF EXISTS (SELECT 1 FROM Planilla p JOIN Detalle_Planilla d ON d.planilla_id = p.id_planilla JOIN Novedad n ON n.id_novedad = d.novedad_id
              JOIN Usuario u ON u.id_usuario = n.empleado_id JOIN Cargos c ON c.id_cargo = u.cargo_id
              WHERE p.correccion_nro = 0 AND p.periodo = v_periodo AND c.empresa_id = p_empresa) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe una planilla normal de esa empresa para ese periodo';
  END IF;
  SELECT COUNT(*) INTO v_n FROM Usuario u JOIN Cargos c ON c.id_cargo = u.cargo_id
   WHERE c.empresa_id = p_empresa AND u.estado_laboral = 'ACTIVO' AND u.id_rol IN (1,2,3) AND u.estado_cuenta <> 'ELIMINADA' AND u.fecha_vinculacion <= LAST_DAY(v_periodo);
  IF v_n = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa no tiene personas activas para ese periodo'; END IF;
  SET v_salario = (SELECT id_concepto FROM Concepto WHERE codigo = 'SALARIO');
  START TRANSACTION;
  INSERT INTO Planilla (parametro_id, periodo, tipo_planilla, correccion_nro, estado, fecha_limite)
  VALUES (v_param, v_periodo, 'NORMAL', 0, 'BORRADOR', p_fecha_limite);
  SET p_id = LAST_INSERT_ID();
  INSERT INTO Novedad (empleado_id, concepto_id, fecha_inicio, valor, estado)
  SELECT u.id_usuario, v_salario, v_periodo, u.salario, 'APROBADA'
    FROM Usuario u JOIN Cargos c ON c.id_cargo = u.cargo_id
   WHERE c.empresa_id = p_empresa AND u.estado_laboral = 'ACTIVO' AND u.id_rol IN (1,2,3) AND u.estado_cuenta <> 'ELIMINADA' AND u.fecha_vinculacion <= LAST_DAY(v_periodo)
     AND NOT EXISTS (SELECT 1 FROM Novedad s WHERE s.empleado_id = u.id_usuario AND s.concepto_id = v_salario AND s.periodo = v_periodo);
  INSERT INTO Detalle_Planilla (planilla_id, novedad_id)
  SELECT p_id, n.id_novedad FROM Novedad n JOIN Usuario u ON u.id_usuario = n.empleado_id JOIN Cargos c ON c.id_cargo = u.cargo_id
   WHERE c.empresa_id = p_empresa AND n.periodo = v_periodo AND n.estado = 'APROBADA';
  CALL sp_calcular_planilla(p_id);
  COMMIT;
END$$

CREATE PROCEDURE sp_aprobar_planilla(IN p_actor INT, IN p_planilla INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_estado VARCHAR(10); DECLARE v_empresa VARCHAR(15);
  SET v_estado = (SELECT estado FROM Planilla WHERE id_planilla = p_planilla);
  IF v_estado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla no existe'; END IF;
  IF v_estado <> 'BORRADOR' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se aprueban planillas en BORRADOR'; END IF;
  SET v_empresa = fn_empresa_planilla(p_planilla);
  IF v_empresa IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla no tiene conceptos'; END IF;
  IF fn_empresa_activa(v_empresa) = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa esta inactiva'; END IF;
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien aprueba no esta activa'; END IF;
  IF NOT (v_rol = 4 OR (v_rol = 1 AND fn_empresa_usuario(p_actor) = v_empresa)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el Administrador de la empresa o el Super Usuario aprueban la planilla';
  END IF;
  CALL sp_calcular_planilla(p_planilla);
  -- El trigger de Planilla bloquea si hay afiliaciones incompletas o un IBC menor al salario minimo.
  UPDATE Planilla SET estado = 'APROBADA' WHERE id_planilla = p_planilla;
END$$

CREATE PROCEDURE sp_marcar_generada(IN p_actor INT, IN p_planilla INT, IN p_clave VARCHAR(64))
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_estado VARCHAR(10); DECLARE v_empresa VARCHAR(15);
  SET v_estado = (SELECT estado FROM Planilla WHERE id_planilla = p_planilla);
  IF v_estado IS NULL OR v_estado <> 'APROBADA' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se genera el archivo de una planilla APROBADA'; END IF;
  IF p_clave NOT REGEXP '^[0-9a-f]{32,64}$' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La clave del archivo no es valida'; END IF;
  SET v_empresa = fn_empresa_planilla(p_planilla);
  IF fn_empresa_activa(v_empresa) = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa esta inactiva'; END IF;
  SET v_rol = fn_rol_usuario(p_actor);
  IF NOT (v_rol = 4 OR (v_rol = 2 AND fn_atiende(p_actor, v_empresa) = 1)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el Contador que atiende la empresa o el Super Usuario generan el archivo';
  END IF;
  UPDATE Planilla SET estado = 'GENERADA', archivo_pila_clave = p_clave WHERE id_planilla = p_planilla;
END$$

CREATE PROCEDURE sp_registrar_pago(IN p_actor INT, IN p_planilla INT, IN p_fecha DATE, IN p_monto DECIMAL(14,2), IN p_clave VARCHAR(64), IN p_sha CHAR(64))
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_estado VARCHAR(10); DECLARE v_total DECIMAL(14,2); DECLARE v_periodo DATE; DECLARE v_empresa VARCHAR(15);
  SET v_estado = (SELECT estado FROM Planilla WHERE id_planilla = p_planilla);
  IF v_estado IS NULL OR v_estado <> 'GENERADA' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se registra el pago de una planilla GENERADA'; END IF;
  SET v_total = (SELECT total_aportes FROM v_planilla_total WHERE id_planilla = p_planilla);
  SET v_periodo = (SELECT periodo FROM Planilla WHERE id_planilla = p_planilla);
  IF p_monto < v_total - 1 OR p_monto > v_total + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto pagado no coincide con el total de aportes'; END IF;
  IF p_fecha < v_periodo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha de pago no puede ser anterior al periodo'; END IF;
  IF p_clave NOT REGEXP '^[0-9a-f]{32,64}$' OR p_sha NOT REGEXP '^[0-9a-f]{64}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La clave o el hash del comprobante no son validos';
  END IF;
  SET v_empresa = fn_empresa_planilla(p_planilla);
  IF fn_empresa_activa(v_empresa) = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa esta inactiva'; END IF;
  SET v_rol = fn_rol_usuario(p_actor);
  IF NOT (v_rol = 4 OR (v_rol = 1 AND fn_empresa_usuario(p_actor) = v_empresa) OR (v_rol = 2 AND fn_atiende(p_actor, v_empresa) = 1)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No tiene permiso para registrar el pago de esta planilla';
  END IF;
  UPDATE Planilla SET estado = 'PAGADA', fecha_pago = p_fecha, monto_pagado = p_monto, comprobante_clave = p_clave, comprobante_sha256 = p_sha WHERE id_planilla = p_planilla;
END$$

CREATE PROCEDURE sp_generar_correccion(IN p_actor INT, IN p_original INT, OUT p_id INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_emp VARCHAR(15); DECLARE v_per DATE; DECLARE v_lim DATE; DECLARE v_nro INT; DECLARE v_param INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  SET v_emp = fn_empresa_planilla(p_original);
  SET v_per = (SELECT periodo FROM Planilla WHERE id_planilla = p_original AND correccion_nro = 0);
  SET v_lim = (SELECT fecha_limite FROM Planilla WHERE id_planilla = p_original AND correccion_nro = 0);
  IF v_emp IS NULL OR v_per IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La correccion parte de una planilla normal con lineas'; END IF;
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien genera la correccion no esta activa'; END IF;
  IF NOT (v_rol = 4 OR (v_rol = 2 AND fn_atiende(p_actor, v_emp) = 1)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el Contador que atiende la empresa o el Super Usuario generan la correccion';
  END IF;
  SELECT COALESCE(MAX(p.correccion_nro), 0) + 1 INTO v_nro
    FROM Planilla p JOIN Detalle_Planilla d ON d.planilla_id = p.id_planilla JOIN Novedad n ON n.id_novedad = d.novedad_id
    JOIN Usuario u ON u.id_usuario = n.empleado_id JOIN Cargos c ON c.id_cargo = u.cargo_id
   WHERE p.periodo = v_per AND c.empresa_id = v_emp;
  SET v_param = fn_parametro_vigente(LAST_DAY(v_per));
  START TRANSACTION;
  INSERT INTO Planilla (parametro_id, periodo, tipo_planilla, correccion_nro, estado, fecha_limite)
  VALUES (v_param, v_per, 'CORRECCION', v_nro, 'BORRADOR', v_lim);
  SET p_id = LAST_INSERT_ID();
  INSERT INTO Detalle_Planilla (planilla_id, novedad_id)
  SELECT p_id, n.id_novedad FROM Novedad n JOIN Usuario u ON u.id_usuario = n.empleado_id JOIN Cargos c ON c.id_cargo = u.cargo_id
   WHERE c.empresa_id = v_emp AND n.periodo = v_per AND n.estado = 'APROBADA';
  CALL sp_calcular_planilla(p_id);
  COMMIT;
END$$

CREATE PROCEDURE sp_registrar_novedad(IN p_actor INT, IN p_empleado INT, IN p_concepto VARCHAR(40), IN p_fecha_inicio DATE, IN p_fecha_fin DATE, IN p_horas DECIMAL(5,2), OUT p_id INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_empresa VARCHAR(15); DECLARE v_estado_laboral VARCHAR(10); DECLARE v_concepto SMALLINT;
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien registra no esta activa'; END IF;
  SET v_empresa = fn_empresa_usuario(p_empleado);
  IF v_empresa IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La persona no tiene cargo en ninguna empresa'; END IF;
  IF fn_empresa_activa(v_empresa) = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa de la persona esta inactiva'; END IF;
  SET v_concepto = (SELECT id_concepto FROM Concepto WHERE codigo = p_concepto);
  IF v_concepto IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El concepto no existe'; END IF;
  IF p_concepto = 'SALARIO' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El salario ordinario lo genera la planilla'; END IF;
  IF v_rol = 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Administrador aprueba novedades, no las registra'; END IF;
  IF v_rol = 3 AND p_actor <> p_empleado THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Empleado solo registra novedades propias'; END IF;
  IF v_rol = 3 AND p_concepto = 'RETIRO' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Empleado no registra retiros'; END IF;
  IF v_rol = 2 AND fn_atiende(p_actor, v_empresa) = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Contador solo registra novedades de las empresas que atiende'; END IF;
  SET v_estado_laboral = (SELECT estado_laboral FROM Usuario WHERE id_usuario = p_empleado);
  IF v_estado_laboral = 'RETIRADO' AND p_concepto <> 'RETIRO' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La persona esta retirada'; END IF;
  INSERT INTO Novedad (empleado_id, concepto_id, fecha_inicio, fecha_fin, horas, estado)
  VALUES (p_empleado, v_concepto, p_fecha_inicio, p_fecha_fin, p_horas, 'PENDIENTE');
  SET p_id = LAST_INSERT_ID();
  INSERT INTO Bitacora (usuario_id, accion, tabla_afectada, registro_id) VALUES (p_actor, 'REGISTRAR_NOVEDAD', 'Novedad', p_id);
END$$

CREATE PROCEDURE sp_gestionar_novedad(IN p_actor INT, IN p_id INT, IN p_decision VARCHAR(10))
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_empleado INT; DECLARE v_estado VARCHAR(10);
  SET v_empleado = (SELECT empleado_id FROM Novedad WHERE id_novedad = p_id);
  SET v_estado = (SELECT estado FROM Novedad WHERE id_novedad = p_id);
  IF v_empleado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad no existe'; END IF;
  IF v_estado <> 'PENDIENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad ya fue gestionada'; END IF;
  IF p_decision NOT IN ('APROBADA','RECHAZADA') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La decision debe ser APROBADA o RECHAZADA'; END IF;
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien gestiona no esta activa'; END IF;
  IF p_actor = v_empleado THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nadie puede gestionar su propia novedad'; END IF;
  IF NOT (v_rol = 4 OR (v_rol = 1 AND fn_empresa_usuario(p_actor) = fn_empresa_usuario(v_empleado))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el Administrador de la empresa de la persona o el Super Usuario gestionan novedades';
  END IF;
  UPDATE Novedad SET estado = p_decision WHERE id_novedad = p_id;
  INSERT INTO Bitacora (usuario_id, accion, tabla_afectada, registro_id)
  VALUES (p_actor, IF(p_decision = 'APROBADA', 'APROBAR_NOVEDAD', 'RECHAZAR_NOVEDAD'), 'Novedad', p_id);
END$$

CREATE PROCEDURE sp_adjuntar_soporte(IN p_actor INT, IN p_id INT, IN p_clave VARCHAR(64), IN p_sha CHAR(64), IN p_tipo VARCHAR(3), IN p_bytes INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_empleado INT; DECLARE v_lleva TINYINT; DECLARE v_soporte VARCHAR(64);
  SET v_empleado = (SELECT empleado_id FROM Novedad WHERE id_novedad = p_id);
  IF v_empleado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad no existe'; END IF;
  SET v_lleva = (SELECT c.requiere_soporte FROM Novedad n JOIN Concepto c ON c.id_concepto = n.concepto_id WHERE n.id_novedad = p_id);
  SET v_soporte = (SELECT soporte_clave FROM Novedad WHERE id_novedad = p_id);
  IF v_lleva = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo las incapacidades y licencias llevan soporte'; END IF;
  IF v_soporte IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad ya tiene soporte'; END IF;
  IF p_clave NOT REGEXP '^[0-9a-f]{32,64}$' OR p_sha NOT REGEXP '^[0-9a-f]{64}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La clave o el hash del soporte no son validos';
  END IF;
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien sube el soporte no esta activa'; END IF;
  IF NOT (p_actor = v_empleado OR v_rol = 4
       OR (v_rol = 1 AND fn_empresa_usuario(p_actor) = fn_empresa_usuario(v_empleado))
       OR (v_rol = 2 AND fn_atiende(p_actor, fn_empresa_usuario(v_empleado)) = 1)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No tiene permiso para adjuntar soporte a esta novedad';
  END IF;
  UPDATE Novedad SET soporte_clave = p_clave, soporte_sha256 = p_sha, soporte_tipo = p_tipo, soporte_bytes = p_bytes WHERE id_novedad = p_id;
  INSERT INTO Bitacora (usuario_id, accion, tabla_afectada, registro_id) VALUES (p_actor, 'SUBIR_SOPORTE', 'Novedad', p_id);
END$$

CREATE PROCEDURE sp_ver_soporte(IN p_actor INT, IN p_id INT, IN p_motivo VARCHAR(200))
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_empleado INT; DECLARE v_soporte VARCHAR(64);
  SET v_empleado = (SELECT empleado_id FROM Novedad WHERE id_novedad = p_id);
  SET v_soporte = (SELECT soporte_clave FROM Novedad WHERE id_novedad = p_id);
  IF v_empleado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad no existe'; END IF;
  IF v_soporte IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad no tiene soporte'; END IF;
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien consulta no esta activa'; END IF;
  IF v_rol = 4 AND (p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo)) < 10) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Super Usuario debe indicar el motivo de la consulta';
  END IF;
  IF NOT (p_actor = v_empleado OR v_rol = 4
       OR (v_rol = 1 AND fn_empresa_usuario(p_actor) = fn_empresa_usuario(v_empleado))
       OR (v_rol = 2 AND fn_atiende(p_actor, fn_empresa_usuario(v_empleado)) = 1)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No tiene permiso para ver este soporte';
  END IF;
  INSERT INTO Bitacora (usuario_id, accion, tabla_afectada, registro_id) VALUES (p_actor, 'VER_SOPORTE', 'Novedad', p_id);
  SELECT soporte_clave, soporte_tipo, soporte_bytes FROM Novedad WHERE id_novedad = p_id;
END$$

CREATE PROCEDURE sp_registrar_creacion_cuenta(IN p_actor INT, IN p_cuenta INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_rol_cuenta INT; DECLARE v_empresa VARCHAR(15); DECLARE v_tipo_empresa VARCHAR(15);
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien crea no esta activa'; END IF;
  SET v_rol_cuenta = (SELECT id_rol FROM Usuario WHERE id_usuario = p_cuenta);
  IF v_rol_cuenta IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta creada no existe'; END IF;
  SET v_empresa = fn_empresa_usuario(p_cuenta);
  SET v_tipo_empresa = (SELECT tipo_empresa FROM Empresa WHERE nit = v_empresa);
  IF NOT (v_rol = 4
       OR (v_rol = 1 AND v_empresa IS NOT NULL AND fn_empresa_usuario(p_actor) = v_empresa)
       OR (v_rol = 2 AND v_empresa IS NOT NULL AND fn_atiende(p_actor, v_empresa) = 1)
       OR (p_actor = p_cuenta AND v_rol = 2 AND v_tipo_empresa = 'CONTADORA')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No tiene permiso para registrar la creacion de esta cuenta';
  END IF;
  INSERT INTO Bitacora (usuario_id, accion, tabla_afectada, registro_id) VALUES (p_actor, 'CREAR_CUENTA', 'Usuario', p_cuenta);
END$$

CREATE PROCEDURE sp_registrar_afiliacion(IN p_actor INT, IN p_usuario INT, IN p_tipo VARCHAR(3), IN p_entidad VARCHAR(15), IN p_fecha_inicio DATE, OUT p_id INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_empresa VARCHAR(15);
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien registra no esta activa'; END IF;
  SET v_empresa = fn_empresa_usuario(p_usuario);
  IF v_empresa IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La persona no tiene cargo en ninguna empresa'; END IF;
  IF fn_empresa_activa(v_empresa) = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa de la persona esta inactiva'; END IF;
  IF v_rol = 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Administrador aprueba afiliaciones, no las registra'; END IF;
  IF v_rol = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Empleado no registra afiliaciones'; END IF;
  IF v_rol = 2 AND fn_atiende(p_actor, v_empresa) = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El Contador solo registra afiliaciones de las empresas que atiende'; END IF;
  INSERT INTO Afiliacion (usuario_id, tipo, entidad_id, fecha_inicio, estado) VALUES (p_usuario, p_tipo, p_entidad, p_fecha_inicio, 'PENDIENTE');
  SET p_id = LAST_INSERT_ID();
  INSERT INTO Bitacora (usuario_id, accion, tabla_afectada, registro_id) VALUES (p_actor, 'REGISTRAR_NOVEDAD', 'Afiliacion', p_id);
END$$

CREATE PROCEDURE sp_gestionar_afiliacion(IN p_actor INT, IN p_id INT, IN p_decision VARCHAR(10))
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_usuario INT; DECLARE v_estado VARCHAR(10);
  SET v_usuario = (SELECT usuario_id FROM Afiliacion WHERE id_afiliacion = p_id);
  SET v_estado = (SELECT estado FROM Afiliacion WHERE id_afiliacion = p_id);
  IF v_usuario IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La afiliacion no existe'; END IF;
  IF v_estado <> 'PENDIENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La afiliacion ya fue gestionada'; END IF;
  IF p_decision NOT IN ('APROBADA','RECHAZADA') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La decision debe ser APROBADA o RECHAZADA'; END IF;
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien gestiona no esta activa'; END IF;
  IF p_actor = v_usuario THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nadie puede gestionar su propia afiliacion'; END IF;
  IF NOT (v_rol = 4 OR (v_rol = 1 AND fn_empresa_usuario(p_actor) = fn_empresa_usuario(v_usuario))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el Administrador de la empresa de la persona o el Super Usuario gestionan afiliaciones';
  END IF;
  UPDATE Afiliacion SET estado = p_decision WHERE id_afiliacion = p_id;
  INSERT INTO Bitacora (usuario_id, accion, tabla_afectada, registro_id)
  VALUES (p_actor, IF(p_decision = 'APROBADA', 'APROBAR_NOVEDAD', 'RECHAZAR_NOVEDAD'), 'Afiliacion', p_id);
END$$

CREATE PROCEDURE sp_registrar_parametro_legal(IN p_actor INT, IN p_vigente_desde DATE, IN p_salario_minimo DECIMAL(12,2), IN p_divisor_hora SMALLINT, IN p_recargo_dominical DECIMAL(5,2),
  IN p_salud_empleador DECIMAL(5,2), IN p_salud_empleado DECIMAL(5,2), IN p_pension_empleador DECIMAL(5,2), IN p_pension_empleado DECIMAL(5,2), IN p_caja DECIMAL(5,2), IN p_sena_icbf DECIMAL(5,2),
  IN p_arl_i DECIMAL(5,3), IN p_arl_ii DECIMAL(5,3), IN p_arl_iii DECIMAL(5,3), IN p_arl_iv DECIMAL(5,3), IN p_arl_v DECIMAL(5,3), OUT p_id INT)
SQL SECURITY DEFINER
BEGIN
  IF fn_rol_usuario(p_actor) IS NULL OR fn_rol_usuario(p_actor) <> 4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el Super Usuario registra los valores legales';
  END IF;
  INSERT INTO Parametro_Legal (vigente_desde, salario_minimo, divisor_hora, recargo_dominical, tarifa_salud_empleador, tarifa_salud_empleado, tarifa_pension_empleador, tarifa_pension_empleado,
                               tarifa_caja, tarifa_sena_icbf, tarifa_arl_i, tarifa_arl_ii, tarifa_arl_iii, tarifa_arl_iv, tarifa_arl_v)
  VALUES (p_vigente_desde, p_salario_minimo, p_divisor_hora, p_recargo_dominical, p_salud_empleador, p_salud_empleado, p_pension_empleador, p_pension_empleado,
          p_caja, p_sena_icbf, p_arl_i, p_arl_ii, p_arl_iii, p_arl_iv, p_arl_v);
  SET p_id = LAST_INSERT_ID();
END$$

-- ========== CARGA MASIVA: procedimientos que reparten el archivo en las tablas existentes ==========
CREATE PROCEDURE sp_cargar_novedades(IN p_actor INT, IN p_empresa VARCHAR(15), IN p_datos JSON, OUT p_ok INT, OUT p_error INT, OUT p_detalle TEXT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_fin INT DEFAULT 0; DECLARE v_n INT; DECLARE v_ced VARCHAR(10); DECLARE v_cod VARCHAR(40);
  DECLARE v_ini DATE; DECLARE v_fi DATE; DECLARE v_h DECIMAL(5,2);
  DECLARE cur CURSOR FOR
    SELECT j.n, j.cedula, j.concepto, j.fecha_inicio, j.fecha_fin, j.horas
      FROM JSON_TABLE(p_datos, '$[*]' COLUMNS (n FOR ORDINALITY, cedula VARCHAR(10) PATH '$.cedula', concepto VARCHAR(40) PATH '$.concepto',
           fecha_inicio DATE PATH '$.fecha_inicio' NULL ON EMPTY NULL ON ERROR, fecha_fin DATE PATH '$.fecha_fin' NULL ON EMPTY NULL ON ERROR,
           horas DECIMAL(5,2) PATH '$.horas' NULL ON EMPTY NULL ON ERROR)) j;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fin = 1;
  SET p_ok = 0; SET p_error = 0; SET p_detalle = '';
  IF fn_rol_usuario(p_actor) IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien carga no esta activa'; END IF;
  OPEN cur;
  lectura: LOOP
    FETCH cur INTO v_n, v_ced, v_cod, v_ini, v_fi, v_h;
    IF v_fin = 1 THEN LEAVE lectura; END IF;
    fila: BEGIN
      DECLARE v_msg TEXT; DECLARE v_u INT; DECLARE v_id INT;
      DECLARE EXIT HANDLER FOR SQLEXCEPTION
      BEGIN
        GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT;
        SET p_error = p_error + 1; SET p_detalle = CONCAT(p_detalle, 'Fila ', v_n, ': ', v_msg, '\n');
      END;
      SET v_u = (SELECT u.id_usuario FROM Usuario u JOIN Cargos c ON c.id_cargo = u.cargo_id WHERE u.cedula = v_ced AND c.empresa_id = p_empresa);
      IF v_u IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cedula no pertenece a una persona de esta empresa'; END IF;
      IF v_ini IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falta la fecha de inicio'; END IF;
      -- Cada fila pasa por el mismo procedimiento que la novedad manual: mismos permisos, mismas reglas y su bitacora.
      CALL sp_registrar_novedad(p_actor, v_u, v_cod, v_ini, v_fi, v_h, v_id);
      SET p_ok = p_ok + 1;
    END;
  END LOOP;
  CLOSE cur;
END$$

CREATE PROCEDURE sp_cargar_empleados(IN p_actor INT, IN p_empresa VARCHAR(15), IN p_datos JSON, OUT p_ok INT, OUT p_error INT, OUT p_detalle TEXT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_fin INT DEFAULT 0; DECLARE v_n INT; DECLARE v_ced VARCHAR(10); DECLARE v_nom VARCHAR(150); DECLARE v_cargo VARCHAR(100);
  DECLARE v_sal DECIMAL(12,2); DECLARE v_con VARCHAR(20); DECLARE v_fv DATE; DECLARE v_rol INT;
  DECLARE v_eps VARCHAR(15); DECLARE v_afp VARCHAR(15); DECLARE v_arl VARCHAR(15); DECLARE v_ccf VARCHAR(15);
  DECLARE cur CURSOR FOR
    SELECT j.n, j.cedula, j.nombre, j.cargo, j.salario, j.tipo_contrato, j.fecha_vinculacion, j.eps, j.afp, j.arl, j.ccf
      FROM JSON_TABLE(p_datos, '$[*]' COLUMNS (n FOR ORDINALITY, cedula VARCHAR(10) PATH '$.cedula', nombre VARCHAR(150) PATH '$.nombre', cargo VARCHAR(100) PATH '$.cargo',
           salario DECIMAL(12,2) PATH '$.salario' NULL ON EMPTY NULL ON ERROR, tipo_contrato VARCHAR(20) PATH '$.tipo_contrato', fecha_vinculacion DATE PATH '$.fecha_vinculacion' NULL ON EMPTY NULL ON ERROR,
           eps VARCHAR(15) PATH '$.eps' NULL ON EMPTY, afp VARCHAR(15) PATH '$.afp' NULL ON EMPTY, arl VARCHAR(15) PATH '$.arl' NULL ON EMPTY, ccf VARCHAR(15) PATH '$.ccf' NULL ON EMPTY)) j;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fin = 1;
  SET p_ok = 0; SET p_error = 0; SET p_detalle = '';
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien carga no esta activa'; END IF;
  IF NOT (v_rol = 4 OR (v_rol = 1 AND fn_empresa_usuario(p_actor) = p_empresa) OR (v_rol = 2 AND fn_atiende(p_actor, p_empresa) = 1)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No tiene permiso para cargar personas en esta empresa';
  END IF;
  OPEN cur;
  lectura: LOOP
    FETCH cur INTO v_n, v_ced, v_nom, v_cargo, v_sal, v_con, v_fv, v_eps, v_afp, v_arl, v_ccf;
    IF v_fin = 1 THEN LEAVE lectura; END IF;
    fila: BEGIN
      DECLARE v_msg TEXT; DECLARE v_idc INT; DECLARE v_idu INT;
      DECLARE EXIT HANDLER FOR SQLEXCEPTION
      BEGIN
        GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT;
        ROLLBACK;
        SET p_error = p_error + 1; SET p_detalle = CONCAT(p_detalle, 'Fila ', v_n, ': ', v_msg, '\n');
      END;
      IF NOT EXISTS (SELECT 1 FROM Empresa WHERE nit = p_empresa AND tipo_empresa = 'CLIENTE') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las personas se cargan en una empresa cliente'; END IF;
      IF v_ced IS NULL OR v_nom IS NULL OR v_cargo IS NULL OR v_sal IS NULL OR v_fv IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Faltan datos obligatorios de la persona'; END IF;
      START TRANSACTION;
      INSERT IGNORE INTO Cargos (nombre_cargo, empresa_id) VALUES (v_cargo, p_empresa);
      SET v_idc = (SELECT id_cargo FROM Cargos WHERE nombre_cargo = v_cargo AND empresa_id = p_empresa);
      INSERT INTO Usuario (cedula, nombre, id_rol, cargo_id, salario, tipo_contrato, fecha_vinculacion, estado_laboral, estado_cuenta)
      VALUES (v_ced, v_nom, 3, v_idc, v_sal, v_con, v_fv, 'ACTIVO', 'PENDIENTE_ACTIVACION');
      SET v_idu = LAST_INSERT_ID();
      IF v_eps IS NOT NULL THEN INSERT INTO Afiliacion (usuario_id, tipo, entidad_id, fecha_inicio) VALUES (v_idu, 'EPS', v_eps, v_fv); END IF;
      IF v_afp IS NOT NULL THEN INSERT INTO Afiliacion (usuario_id, tipo, entidad_id, fecha_inicio) VALUES (v_idu, 'AFP', v_afp, v_fv); END IF;
      IF v_arl IS NOT NULL THEN INSERT INTO Afiliacion (usuario_id, tipo, entidad_id, fecha_inicio) VALUES (v_idu, 'ARL', v_arl, v_fv); END IF;
      IF v_ccf IS NOT NULL THEN INSERT INTO Afiliacion (usuario_id, tipo, entidad_id, fecha_inicio) VALUES (v_idu, 'CCF', v_ccf, v_fv); END IF;
      INSERT INTO Bitacora (usuario_id, accion, tabla_afectada, registro_id) VALUES (p_actor, 'CREAR_CUENTA', 'Usuario', v_idu);
      COMMIT;
      SET p_ok = p_ok + 1;
    END;
  END LOOP;
  CLOSE cur;
END$$

CREATE PROCEDURE sp_refrescar_planilla(IN p_actor INT, IN p_planilla INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_estado VARCHAR(10); DECLARE v_tipo VARCHAR(10); DECLARE v_empresa VARCHAR(15); DECLARE v_periodo DATE; DECLARE v_salario SMALLINT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  SET v_estado = (SELECT estado FROM Planilla WHERE id_planilla = p_planilla);
  IF v_estado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla no existe'; END IF;
  IF v_estado <> 'BORRADOR' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se refresca una planilla en BORRADOR'; END IF;
  SET v_empresa = fn_empresa_planilla(p_planilla);
  IF v_empresa IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla no tiene conceptos'; END IF;
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien refresca no esta activa'; END IF;
  IF NOT (v_rol = 4 OR (v_rol = 2 AND fn_atiende(p_actor, v_empresa) = 1)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el Contador que atiende la empresa o el Super Usuario refrescan la planilla';
  END IF;
  SELECT periodo, tipo_planilla INTO v_periodo, v_tipo FROM Planilla WHERE id_planilla = p_planilla;
  SET v_salario = (SELECT id_concepto FROM Concepto WHERE codigo = 'SALARIO');
  START TRANSACTION;
  IF v_tipo = 'NORMAL' THEN
    -- En la planilla normal el salario del periodo se vuelve a tomar de la persona y entran las personas nuevas.
    UPDATE Novedad n JOIN Detalle_Planilla d ON d.novedad_id = n.id_novedad JOIN Usuario u ON u.id_usuario = n.empleado_id
       SET n.valor = u.salario
     WHERE d.planilla_id = p_planilla AND n.concepto_id = v_salario AND n.valor <> u.salario;
    INSERT INTO Novedad (empleado_id, concepto_id, fecha_inicio, valor, estado)
    SELECT u.id_usuario, v_salario, v_periodo, u.salario, 'APROBADA'
      FROM Usuario u JOIN Cargos c ON c.id_cargo = u.cargo_id
     WHERE c.empresa_id = v_empresa AND u.estado_laboral = 'ACTIVO' AND u.id_rol IN (1,2,3) AND u.estado_cuenta <> 'ELIMINADA' AND u.fecha_vinculacion <= LAST_DAY(v_periodo)
       AND NOT EXISTS (SELECT 1 FROM Novedad s WHERE s.empleado_id = u.id_usuario AND s.concepto_id = v_salario AND s.periodo = v_periodo);
  END IF;
  -- Entran las novedades que se aprobaron despues de generar la planilla.
  INSERT INTO Detalle_Planilla (planilla_id, novedad_id)
  SELECT p_planilla, n.id_novedad FROM Novedad n JOIN Usuario u ON u.id_usuario = n.empleado_id JOIN Cargos c ON c.id_cargo = u.cargo_id
   WHERE c.empresa_id = v_empresa AND n.periodo = v_periodo AND n.estado = 'APROBADA'
     AND NOT EXISTS (SELECT 1 FROM Detalle_Planilla x WHERE x.planilla_id = p_planilla AND x.novedad_id = n.id_novedad)
     AND (v_tipo = 'NORMAL' OR n.concepto_id <> v_salario OR EXISTS (SELECT 1 FROM Detalle_Planilla y WHERE y.novedad_id = n.id_novedad));
  CALL sp_calcular_planilla(p_planilla);
  COMMIT;
END$$

CREATE PROCEDURE sp_descartar_planilla(IN p_actor INT, IN p_planilla INT)
SQL SECURITY DEFINER
BEGIN
  DECLARE v_rol INT; DECLARE v_estado VARCHAR(10); DECLARE v_empresa VARCHAR(15);
  SET v_estado = (SELECT estado FROM Planilla WHERE id_planilla = p_planilla);
  IF v_estado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla no existe'; END IF;
  IF v_estado <> 'BORRADOR' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se descarta una planilla en BORRADOR'; END IF;
  SET v_empresa = fn_empresa_planilla(p_planilla);
  SET v_rol = fn_rol_usuario(p_actor);
  IF v_rol IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta de quien descarta no esta activa'; END IF;
  IF NOT (v_rol = 4 OR (v_rol = 2 AND v_empresa IS NOT NULL AND fn_atiende(p_actor, v_empresa) = 1)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el Contador que atiende la empresa o el Super Usuario descartan la planilla';
  END IF;
  DELETE FROM Planilla WHERE id_planilla = p_planilla;
END$$

DELIMITER ;

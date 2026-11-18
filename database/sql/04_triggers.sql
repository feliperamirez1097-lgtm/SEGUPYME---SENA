USE segupyme_v15;

DELIMITER $$

CREATE TRIGGER trg_bitacora_bu BEFORE UPDATE ON Bitacora FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora solo admite inserciones'; END$$

CREATE TRIGGER trg_bitacora_bd BEFORE DELETE ON Bitacora FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora solo admite inserciones'; END$$

CREATE TRIGGER trg_empresa_bi BEFORE INSERT ON Empresa FOR EACH ROW
BEGIN
  IF NEW.contador_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM Empresa WHERE nit = NEW.contador_id AND tipo_empresa = 'CONTADORA') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El contador externo debe ser una empresa de tipo CONTADORA';
  END IF;
END$$

CREATE TRIGGER trg_empresa_bu BEFORE UPDATE ON Empresa FOR EACH ROW
BEGIN
  IF NEW.nit <> OLD.nit THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El NIT de una empresa no se puede cambiar'; END IF;
  IF NEW.contador_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM Empresa WHERE nit = NEW.contador_id AND tipo_empresa = 'CONTADORA') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El contador externo debe ser una empresa de tipo CONTADORA';
  END IF;
  IF (NEW.tipo_empresa <> OLD.tipo_empresa OR NOT (NEW.subtipo_entidad <=> OLD.subtipo_entidad))
     AND (EXISTS (SELECT 1 FROM Empresa WHERE contador_id = OLD.nit) OR EXISTS (SELECT 1 FROM Afiliacion WHERE entidad_id = OLD.nit) OR EXISTS (SELECT 1 FROM Cargos WHERE empresa_id = OLD.nit)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede cambiar el tipo de una empresa que ya se usa';
  END IF;
  IF NEW.estado = 'INACTIVA' AND OLD.estado = 'ACTIVA' AND EXISTS (SELECT 1 FROM Planilla p JOIN Detalle_Planilla d ON d.planilla_id = p.id_planilla JOIN Novedad n ON n.id_novedad = d.novedad_id JOIN Usuario u ON u.id_usuario = n.empleado_id JOIN Cargos c ON c.id_cargo = u.cargo_id WHERE c.empresa_id = OLD.nit AND p.estado <> 'PAGADA') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa tiene planillas sin cerrar y no se puede inactivar';
  END IF;
END$$

CREATE TRIGGER trg_empresa_bd BEFORE DELETE ON Empresa FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM Empresa WHERE contador_id = OLD.nit) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa contadora tiene clientes y no se puede borrar';
  END IF;
  IF EXISTS (SELECT 1 FROM Afiliacion WHERE entidad_id = OLD.nit) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La entidad tiene afiliaciones y no se puede borrar';
  END IF;
END$$

CREATE TRIGGER trg_afiliacion_bi BEFORE INSERT ON Afiliacion FOR EACH ROW
BEGIN
  IF fn_empresa_usuario(NEW.usuario_id) IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La persona no tiene cargo en ninguna empresa'; END IF;
  IF NOT EXISTS (SELECT 1 FROM Empresa WHERE nit = NEW.entidad_id AND tipo_empresa = 'SERVICIOS' AND subtipo_entidad = NEW.tipo) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La entidad debe ser una empresa de servicios del mismo tipo que la afiliacion';
  END IF;
END$$

CREATE TRIGGER trg_afiliacion_bu BEFORE UPDATE ON Afiliacion FOR EACH ROW
BEGIN
  IF NEW.usuario_id <> OLD.usuario_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede cambiar la persona de una afiliacion'; END IF;
  IF NOT EXISTS (SELECT 1 FROM Empresa WHERE nit = NEW.entidad_id AND tipo_empresa = 'SERVICIOS' AND subtipo_entidad = NEW.tipo) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La entidad debe ser una empresa de servicios del mismo tipo que la afiliacion';
  END IF;
END$$

CREATE TRIGGER trg_parametro_bi BEFORE INSERT ON Parametro_Legal FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM Parametro_Legal WHERE vigente_desde > NEW.vigente_desde) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se agregan vigencias posteriores a la ultima';
  END IF;
  -- Ley 2101 de 2021: 48 h (divisor 240) hasta el 14-jul-2023; 47 h (235); 46 h (230); 44 h (220); 42 h (210) desde el 15-jul-2026.
  IF NEW.divisor_hora <> CASE WHEN NEW.vigente_desde >= '2026-07-15' THEN 210 WHEN NEW.vigente_desde >= '2025-07-15' THEN 220
                              WHEN NEW.vigente_desde >= '2024-07-15' THEN 230 WHEN NEW.vigente_desde >= '2023-07-15' THEN 235 ELSE 240 END THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El divisor de la hora no corresponde a la jornada legal de esa vigencia';
  END IF;
  IF NEW.salario_minimo < (SELECT COALESCE(MAX(salario_minimo), 0) FROM Parametro_Legal) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El salario minimo no puede ser menor al de la vigencia anterior';
  END IF;
END$$

CREATE TRIGGER trg_parametro_bu BEFORE UPDATE ON Parametro_Legal FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM Planilla WHERE parametro_id = OLD.id_parametro) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La vigencia ya fue usada por una planilla: registre una nueva';
  END IF;
END$$

CREATE TRIGGER trg_parametro_bd BEFORE DELETE ON Parametro_Legal FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM Planilla WHERE parametro_id = OLD.id_parametro) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La vigencia ya fue usada por una planilla y no se puede borrar';
  END IF;
END$$

CREATE TRIGGER trg_novedad_bi BEFORE INSERT ON Novedad FOR EACH ROW
BEGIN
  DECLARE v_pct DECIMAL(5,2); DECLARE v_dias BOOLEAN; DECLARE v_soporte BOOLEAN; DECLARE v_codigo VARCHAR(40); DECLARE v_base BOOLEAN;
  DECLARE v_empresa VARCHAR(15); DECLARE v_salario DECIMAL(12,2); DECLARE v_div SMALLINT;
  SET v_empresa = fn_empresa_usuario(NEW.empleado_id);
  IF v_empresa IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La persona no tiene cargo en ninguna empresa'; END IF;
  SELECT porcentaje_fijo, requiere_dias, requiere_soporte, codigo, incluye_hora_base INTO v_pct, v_dias, v_soporte, v_codigo, v_base FROM Concepto WHERE id_concepto = NEW.concepto_id;
  IF NEW.fecha_fin IS NOT NULL AND DATE_FORMAT(NEW.fecha_fin, '%Y-%m') <> DATE_FORMAT(NEW.fecha_inicio, '%Y-%m') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una novedad no puede cruzar de mes: registre una por cada mes';
  END IF;
  IF v_pct IS NOT NULL AND NEW.horas IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las horas extra y los recargos exigen la cantidad de horas'; END IF;
  IF v_pct IS NULL AND NEW.horas IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este concepto no lleva horas'; END IF;
  IF v_dias AND NEW.fecha_fin IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este concepto exige la fecha final'; END IF;
  IF NOT v_dias AND NEW.fecha_fin IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este concepto no lleva fecha final'; END IF;
  IF NOT v_soporte AND NEW.soporte_clave IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo las incapacidades y licencias llevan soporte'; END IF;
  IF v_pct IS NOT NULL THEN
    SELECT salario INTO v_salario FROM Usuario WHERE id_usuario = NEW.empleado_id;
    SELECT divisor_hora INTO v_div FROM Parametro_Legal WHERE id_parametro = fn_parametro_vigente(NEW.fecha_inicio);
    SET v_pct = fn_porcentaje_recargo(NEW.concepto_id, NEW.fecha_inicio);
    SET NEW.valor = ROUND(NEW.horas * ROUND(v_salario / v_div, 2) * IF(v_base, 1 + v_pct / 100, v_pct / 100), 2);
  END IF;
  IF v_codigo = 'SALARIO' AND NEW.valor IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El salario ordinario exige el valor del periodo'; END IF;
END$$

CREATE TRIGGER trg_novedad_bu BEFORE UPDATE ON Novedad FOR EACH ROW
BEGIN
  IF NEW.empleado_id <> OLD.empleado_id OR NEW.concepto_id <> OLD.concepto_id OR NEW.fecha_inicio <> OLD.fecha_inicio THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede cambiar la persona, el concepto ni la fecha de una novedad';
  END IF;
  IF EXISTS (SELECT 1 FROM Detalle_Planilla d JOIN Planilla p ON p.id_planilla = d.planilla_id WHERE d.novedad_id = OLD.id_novedad AND p.estado <> 'BORRADOR') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad pertenece a una planilla cerrada';
  END IF;
END$$

CREATE TRIGGER trg_novedad_bd BEFORE DELETE ON Novedad FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM Detalle_Planilla WHERE novedad_id = OLD.id_novedad) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad ya esta en una planilla y no se puede borrar';
  END IF;
END$$

CREATE TRIGGER trg_usuario_bi BEFORE INSERT ON Usuario FOR EACH ROW
BEGIN
  CALL sp_validar_usuario(NEW.id_rol, NEW.cargo_id, NEW.salario, NEW.tipo_contrato, NEW.fecha_vinculacion, NEW.estado_laboral);
END$$

CREATE TRIGGER trg_usuario_bu BEFORE UPDATE ON Usuario FOR EACH ROW
BEGIN
  DECLARE v_old VARCHAR(15); DECLARE v_new VARCHAR(15);
  CALL sp_validar_usuario(NEW.id_rol, NEW.cargo_id, NEW.salario, NEW.tipo_contrato, NEW.fecha_vinculacion, NEW.estado_laboral);
  IF NOT (NEW.cargo_id <=> OLD.cargo_id) THEN
    SELECT empresa_id INTO v_old FROM Cargos WHERE id_cargo = OLD.cargo_id;
    SELECT empresa_id INTO v_new FROM Cargos WHERE id_cargo = NEW.cargo_id;
    IF NOT (v_old <=> v_new) AND EXISTS (SELECT 1 FROM Novedad WHERE empleado_id = OLD.id_usuario) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La persona ya tiene novedades en su empresa: no se puede pasar a otra';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_planilla_bi BEFORE INSERT ON Planilla FOR EACH ROW
BEGIN
  IF NEW.estado <> 'BORRADOR' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una planilla nueva debe iniciar en BORRADOR'; END IF;
  IF NEW.parametro_id <> fn_parametro_vigente(LAST_DAY(NEW.periodo)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La vigencia legal no corresponde al periodo de la planilla';
  END IF;
END$$

CREATE TRIGGER trg_planilla_bu BEFORE UPDATE ON Planilla FOR EACH ROW
BEGIN
  DECLARE v_pend INT;
  IF NEW.periodo <> OLD.periodo OR NEW.parametro_id <> OLD.parametro_id OR NEW.tipo_planilla <> OLD.tipo_planilla OR NEW.correccion_nro <> OLD.correccion_nro THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El periodo, la vigencia y el tipo de una planilla no se pueden cambiar';
  END IF;
  IF NOT (NEW.estado = OLD.estado OR (OLD.estado = 'BORRADOR' AND NEW.estado = 'APROBADA') OR (OLD.estado = 'APROBADA' AND NEW.estado = 'GENERADA') OR (OLD.estado = 'GENERADA' AND NEW.estado = 'PAGADA')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cambio de estado de planilla no permitido';
  END IF;
  IF OLD.estado <> 'BORRADOR' AND NOT (NEW.fecha_limite <=> OLD.fecha_limite) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los valores de una planilla aprobada no se pueden modificar';
  END IF;
  IF OLD.estado = 'BORRADOR' AND NEW.estado = 'APROBADA' THEN
    IF NOT EXISTS (SELECT 1 FROM Detalle_Planilla WHERE planilla_id = OLD.id_planilla) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla no tiene conceptos';
    END IF;
    SELECT COUNT(*) INTO v_pend FROM (SELECT DISTINCT n.empleado_id AS id FROM Detalle_Planilla d JOIN Novedad n ON n.id_novedad = d.novedad_id WHERE d.planilla_id = OLD.id_planilla) x
     WHERE fn_afiliaciones_completas(x.id, LAST_DAY(OLD.periodo)) = 0;
    IF v_pend > 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hay personas con afiliaciones incompletas: la liquidacion esta bloqueada'; END IF;
    IF EXISTS (SELECT 1 FROM Detalle_Planilla d JOIN Novedad n ON n.id_novedad = d.novedad_id JOIN Concepto c ON c.id_concepto = n.concepto_id JOIN Parametro_Legal g ON g.id_parametro = OLD.parametro_id
                WHERE d.planilla_id = OLD.id_planilla AND c.codigo = 'SALARIO' AND d.ibc < g.salario_minimo) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hay un IBC menor al salario minimo';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_planilla_bd BEFORE DELETE ON Planilla FOR EACH ROW
BEGIN
  IF OLD.estado <> 'BORRADOR' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se pueden borrar planillas en BORRADOR'; END IF;
END$$

CREATE TRIGGER trg_detalle_bi BEFORE INSERT ON Detalle_Planilla FOR EACH ROW
BEGIN
  DECLARE v_per DATE; DECLARE v_est VARCHAR(10); DECLARE v_tipo VARCHAR(10); DECLARE v_nro TINYINT;
  DECLARE n_per DATE; DECLARE n_est VARCHAR(10); DECLARE n_emp VARCHAR(15);
  DECLARE v_lineas INT; DECLARE v_emp VARCHAR(15); DECLARE e_tipo VARCHAR(10); DECLARE e_estado VARCHAR(10);
  SELECT periodo, estado, tipo_planilla, correccion_nro INTO v_per, v_est, v_tipo, v_nro FROM Planilla WHERE id_planilla = NEW.planilla_id;
  SELECT periodo, estado, fn_empresa_usuario(empleado_id) INTO n_per, n_est, n_emp FROM Novedad WHERE id_novedad = NEW.novedad_id;
  IF v_est <> 'BORRADOR' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla ya no admite cambios'; END IF;
  IF n_est <> 'APROBADA' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo entran a la planilla las novedades aprobadas'; END IF;
  IF n_per <> v_per THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad es de otro periodo'; END IF;
  SELECT COUNT(*), MIN(c.empresa_id) INTO v_lineas, v_emp
    FROM Detalle_Planilla d JOIN Novedad n ON n.id_novedad = d.novedad_id JOIN Usuario u ON u.id_usuario = n.empleado_id JOIN Cargos c ON c.id_cargo = u.cargo_id
   WHERE d.planilla_id = NEW.planilla_id;
  IF v_lineas > 0 AND v_emp <> n_emp THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La novedad es de otra empresa distinta a la de la planilla'; END IF;
  IF v_lineas = 0 THEN
    SELECT tipo_empresa, estado INTO e_tipo, e_estado FROM Empresa WHERE nit = n_emp;
    IF e_tipo <> 'CLIENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla se genera para una empresa cliente'; END IF;
    IF e_estado <> 'ACTIVA' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La empresa esta inactiva'; END IF;
    IF EXISTS (SELECT 1 FROM Planilla p2 JOIN Detalle_Planilla d2 ON d2.planilla_id = p2.id_planilla JOIN Novedad m ON m.id_novedad = d2.novedad_id
                JOIN Usuario u2 ON u2.id_usuario = m.empleado_id JOIN Cargos c2 ON c2.id_cargo = u2.cargo_id
                WHERE p2.id_planilla <> NEW.planilla_id AND p2.periodo = v_per AND p2.correccion_nro = v_nro AND c2.empresa_id = n_emp) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe una planilla de esa empresa para ese periodo y numero';
    END IF;
    IF v_tipo = 'CORRECCION' AND NOT EXISTS (SELECT 1 FROM Planilla p3 JOIN Detalle_Planilla d3 ON d3.planilla_id = p3.id_planilla JOIN Novedad m3 ON m3.id_novedad = d3.novedad_id
                JOIN Usuario u3 ON u3.id_usuario = m3.empleado_id JOIN Cargos c3 ON c3.id_cargo = u3.cargo_id
                WHERE p3.periodo = v_per AND p3.correccion_nro = 0 AND p3.estado <> 'BORRADOR' AND c3.empresa_id = n_emp) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una correccion exige una planilla normal aprobada del mismo periodo';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_detalle_bu BEFORE UPDATE ON Detalle_Planilla FOR EACH ROW
BEGIN
  IF NEW.planilla_id <> OLD.planilla_id OR NEW.novedad_id <> OLD.novedad_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede cambiar la planilla ni la novedad de una linea'; END IF;
  IF (SELECT estado FROM Planilla WHERE id_planilla = OLD.planilla_id) <> 'BORRADOR' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla ya no admite cambios'; END IF;
END$$

CREATE TRIGGER trg_detalle_bd BEFORE DELETE ON Detalle_Planilla FOR EACH ROW
BEGIN
  IF (SELECT estado FROM Planilla WHERE id_planilla = OLD.planilla_id) <> 'BORRADOR' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La planilla ya no admite cambios'; END IF;
END$$

DELIMITER ;

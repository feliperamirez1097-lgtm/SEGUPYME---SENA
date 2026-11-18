CREATE USER IF NOT EXISTS 'segupyme_app'@'localhost' IDENTIFIED BY 'Cambiar_Clave_App_2026!';
CREATE USER IF NOT EXISTS 'segupyme_lectura'@'localhost' IDENTIFIED BY 'Cambiar_Clave_Lectura_2026!';
CREATE USER IF NOT EXISTS 'segupyme_migraciones'@'localhost' IDENTIFIED BY 'Cambiar_Clave_Migra_2026!';

REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'segupyme_app'@'localhost';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'segupyme_lectura'@'localhost';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'segupyme_migraciones'@'localhost';

-- La aplicacion: lee todo, escribe solo Usuario, Empresa y Cargos, y ejecuta los procedimientos.
-- Novedades, afiliaciones, planillas, valores legales y bitacora SOLO se escriben por procedimiento.
GRANT SELECT ON segupyme_v15.* TO 'segupyme_app'@'localhost';
GRANT INSERT, UPDATE ON segupyme_v15.Usuario TO 'segupyme_app'@'localhost';
GRANT INSERT, UPDATE ON segupyme_v15.Empresa TO 'segupyme_app'@'localhost';
GRANT INSERT, UPDATE ON segupyme_v15.Cargos TO 'segupyme_app'@'localhost';
GRANT EXECUTE ON segupyme_v15.* TO 'segupyme_app'@'localhost';

-- Lectura (reportes): sin cedulas, contrasenas, soportes ni bitacora.
GRANT SELECT ON segupyme_v15.Roles TO 'segupyme_lectura'@'localhost';
GRANT SELECT ON segupyme_v15.Empresa TO 'segupyme_lectura'@'localhost';
GRANT SELECT ON segupyme_v15.Cargos TO 'segupyme_lectura'@'localhost';
GRANT SELECT ON segupyme_v15.Concepto TO 'segupyme_lectura'@'localhost';
GRANT SELECT ON segupyme_v15.Parametro_Legal TO 'segupyme_lectura'@'localhost';
GRANT SELECT ON segupyme_v15.Afiliacion TO 'segupyme_lectura'@'localhost';
GRANT SELECT ON segupyme_v15.Planilla TO 'segupyme_lectura'@'localhost';
GRANT SELECT ON segupyme_v15.Detalle_Planilla TO 'segupyme_lectura'@'localhost';
GRANT SELECT ON segupyme_v15.v_planilla_total TO 'segupyme_lectura'@'localhost';
GRANT SELECT (id_novedad, empleado_id, concepto_id, periodo, fecha_inicio, fecha_fin, dias, horas, valor, estado) ON segupyme_v15.Novedad TO 'segupyme_lectura'@'localhost';
GRANT SELECT (id_usuario, nombre, id_rol, cargo_id, estado_laboral, estado_cuenta) ON segupyme_v15.Usuario TO 'segupyme_lectura'@'localhost';

GRANT CREATE, ALTER, DROP, INDEX, REFERENCES, TRIGGER, CREATE ROUTINE, ALTER ROUTINE, SELECT ON segupyme_v15.* TO 'segupyme_migraciones'@'localhost';

FLUSH PRIVILEGES;

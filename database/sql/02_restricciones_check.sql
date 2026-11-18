USE segupyme_v15;

ALTER TABLE Empresa ADD CONSTRAINT ck_empresa_cliente_aportes CHECK (tipo_empresa <> 'CLIENTE' OR (exonerada_art_114_1 IS NOT NULL AND clase_riesgo_arl IS NOT NULL));
ALTER TABLE Empresa ADD CONSTRAINT ck_empresa_subtipo CHECK ((tipo_empresa = 'SERVICIOS') = (subtipo_entidad IS NOT NULL));
ALTER TABLE Empresa ADD CONSTRAINT ck_empresa_honorarios CHECK (honorarios_contador IS NULL OR honorarios_contador >= 0);
ALTER TABLE Empresa ADD CONSTRAINT ck_empresa_contador CHECK (contador_id IS NULL OR tipo_empresa = 'CLIENTE');
ALTER TABLE Usuario ADD CONSTRAINT ck_usuario_salario CHECK (salario IS NULL OR salario > 0);
ALTER TABLE Empresa ADD CONSTRAINT ck_empresa_nit CHECK (nit REGEXP '^[0-9]{9,15}$');
ALTER TABLE Usuario ADD CONSTRAINT ck_usuario_datos CHECK (id_rol = 4 OR (cedula IS NOT NULL AND cargo_id IS NOT NULL AND salario IS NOT NULL AND tipo_contrato IS NOT NULL AND fecha_vinculacion IS NOT NULL AND estado_laboral IS NOT NULL));
ALTER TABLE Usuario ADD CONSTRAINT ck_usuario_cuenta CHECK (estado_cuenta <> 'ACTIVA' OR (correo_acceso IS NOT NULL AND contrasena_hash IS NOT NULL));
ALTER TABLE Usuario ADD CONSTRAINT ck_usuario_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_vinculacion);
ALTER TABLE Usuario ADD CONSTRAINT ck_usuario_intentos CHECK (intentos_fallidos BETWEEN 0 AND 10);
ALTER TABLE Planilla ADD CONSTRAINT ck_planilla_limite CHECK (fecha_limite >= periodo);

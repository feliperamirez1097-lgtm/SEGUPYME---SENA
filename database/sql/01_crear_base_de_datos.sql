DROP DATABASE IF EXISTS segupyme_v15;
CREATE DATABASE segupyme_v15 CHARACTER SET utf8mb4;
USE segupyme_v15;

CREATE TABLE Roles (
  id_rol INT PRIMARY KEY,
  nombre_rol VARCHAR(100) NOT NULL UNIQUE
);
INSERT INTO Roles (id_rol, nombre_rol) VALUES (1,'ADMINISTRADOR'),(2,'CONTADOR'),(3,'EMPLEADO'),(4,'SUPER_USUARIO');

CREATE TABLE Empresa (
  nit VARCHAR(15) PRIMARY KEY,
  tipo_empresa ENUM('CLIENTE','SERVICIOS','CONTADORA') NOT NULL,
  subtipo_entidad ENUM('EPS','AFP','ARL','CCF') NULL,
  razon_social VARCHAR(200) NOT NULL,
  representante_legal VARCHAR(150) NOT NULL,
  correo VARCHAR(100) NOT NULL,
  fecha_registro DATE NOT NULL,
  estado ENUM('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  tipo_alerta_preferido ENUM('CORREO','APP','AMBAS') NOT NULL DEFAULT 'CORREO',
  contador_id VARCHAR(15) NULL,
  honorarios_contador DECIMAL(12,2) NULL,
  exonerada_art_114_1 BOOLEAN NULL,
  clase_riesgo_arl ENUM('I','II','III','IV','V') NULL,
  INDEX idx_empresa_contador (contador_id)
);

CREATE TABLE Cargos (
  id_cargo INT AUTO_INCREMENT PRIMARY KEY,
  nombre_cargo VARCHAR(100) NOT NULL,
  empresa_id VARCHAR(15) NOT NULL,
  CONSTRAINT uq_cargo_empresa UNIQUE (empresa_id, nombre_cargo),
  CONSTRAINT fk_cargo_empresa FOREIGN KEY (empresa_id) REFERENCES Empresa (nit)
);

CREATE TABLE Usuario (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  cedula VARCHAR(10) NULL UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  correo_acceso VARCHAR(100) NULL UNIQUE,
  contrasena_hash VARCHAR(255) NULL,
  id_rol INT NOT NULL,
  cargo_id INT NULL,
  salario DECIMAL(12,2) NULL,
  tipo_contrato ENUM('TERMINO_FIJO','TERMINO_INDEFINIDO','OBRA_LABOR','APRENDIZAJE') NULL,
  fecha_vinculacion DATE NULL,
  fecha_fin DATE NULL,
  estado_laboral ENUM('ACTIVO','RETIRADO') NULL,
  estado_cuenta ENUM('PENDIENTE_ACTIVACION','ACTIVA','ELIMINADA') NOT NULL DEFAULT 'PENDIENTE_ACTIVACION',
  intentos_fallidos TINYINT NOT NULL DEFAULT 0,
  bloqueada_hasta DATETIME NULL,
  autorizacion_datos_en DATETIME NULL,
  CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES Roles (id_rol),
  CONSTRAINT fk_usuario_cargo FOREIGN KEY (cargo_id) REFERENCES Cargos (id_cargo)
);

CREATE TABLE Bitacora (
  id_bitacora INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  accion ENUM('CREAR_CUENTA','REGISTRAR_NOVEDAD','APROBAR_NOVEDAD','RECHAZAR_NOVEDAD','SUBIR_SOPORTE','VER_SOPORTE') NOT NULL,
  tabla_afectada VARCHAR(30) NOT NULL,
  registro_id INT NOT NULL,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_bitacora_usuario (usuario_id, fecha_hora),
  CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES Usuario (id_usuario)
);

CREATE TABLE Afiliacion (
  id_afiliacion INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo ENUM('EPS','AFP','ARL','CCF') NOT NULL,
  entidad_id VARCHAR(15) NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  estado ENUM('PENDIENTE','APROBADA','RECHAZADA') NOT NULL DEFAULT 'PENDIENTE',
  CONSTRAINT uq_afiliacion UNIQUE (usuario_id, tipo, fecha_inicio),
  INDEX idx_afiliacion_entidad (entidad_id),
  CONSTRAINT ck_afiliacion_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio),
  CONSTRAINT fk_afiliacion_usuario FOREIGN KEY (usuario_id) REFERENCES Usuario (id_usuario)
);

CREATE TABLE Concepto (
  id_concepto SMALLINT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  codigo_pila VARCHAR(3) NULL,
  columna_destino VARCHAR(30) NOT NULL,
  afecta_ibc BOOLEAN NOT NULL,
  requiere_dias BOOLEAN NOT NULL DEFAULT FALSE,
  requiere_soporte BOOLEAN NOT NULL DEFAULT FALSE,
  porcentaje_fijo DECIMAL(5,2) NULL,
  suma_dominical BOOLEAN NOT NULL DEFAULT FALSE,
  incluye_hora_base BOOLEAN NOT NULL DEFAULT FALSE,
  CONSTRAINT ck_concepto_horas CHECK (porcentaje_fijo IS NOT NULL OR (suma_dominical = FALSE AND incluye_hora_base = FALSE))
);

CREATE TABLE Novedad (
  id_novedad INT AUTO_INCREMENT PRIMARY KEY,
  empleado_id INT NOT NULL,
  concepto_id SMALLINT NOT NULL,
  periodo DATE GENERATED ALWAYS AS (DATE_SUB(fecha_inicio, INTERVAL DAYOFMONTH(fecha_inicio) - 1 DAY)) STORED,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  dias INT GENERATED ALWAYS AS (IF(fecha_fin IS NULL, NULL, DATEDIFF(fecha_fin, fecha_inicio) + 1)) STORED,
  horas DECIMAL(5,2) NULL,
  valor DECIMAL(12,2) NULL,
  estado ENUM('PENDIENTE','APROBADA','RECHAZADA') NOT NULL DEFAULT 'PENDIENTE',
  soporte_clave VARCHAR(64) NULL,
  soporte_sha256 CHAR(64) NULL,
  soporte_tipo ENUM('PDF','JPG','PNG') NULL,
  soporte_bytes INT NULL,
  CONSTRAINT uq_novedad UNIQUE (empleado_id, concepto_id, fecha_inicio),
  CONSTRAINT ck_novedad_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio),
  CONSTRAINT ck_novedad_horas CHECK (horas IS NULL OR (horas > 0 AND horas <= 24)),
  CONSTRAINT ck_novedad_soporte CHECK ((soporte_clave IS NULL AND soporte_sha256 IS NULL AND soporte_tipo IS NULL AND soporte_bytes IS NULL)
        OR (soporte_clave IS NOT NULL AND soporte_sha256 IS NOT NULL AND soporte_tipo IS NOT NULL AND soporte_bytes IS NOT NULL AND soporte_bytes <= 5242880)),
  INDEX idx_novedad_periodo (empleado_id, periodo),
  CONSTRAINT fk_novedad_empleado FOREIGN KEY (empleado_id) REFERENCES Usuario (id_usuario),
  CONSTRAINT fk_novedad_concepto FOREIGN KEY (concepto_id) REFERENCES Concepto (id_concepto)
);

CREATE TABLE Parametro_Legal (
  id_parametro INT AUTO_INCREMENT PRIMARY KEY,
  vigente_desde DATE NOT NULL UNIQUE,
  salario_minimo DECIMAL(12,2) NOT NULL,
  divisor_hora SMALLINT NOT NULL,
  recargo_dominical DECIMAL(5,2) NOT NULL,
  tarifa_salud_empleador DECIMAL(5,2) NOT NULL,
  tarifa_salud_empleado DECIMAL(5,2) NOT NULL,
  tarifa_pension_empleador DECIMAL(5,2) NOT NULL,
  tarifa_pension_empleado DECIMAL(5,2) NOT NULL,
  tarifa_caja DECIMAL(5,2) NOT NULL,
  tarifa_sena_icbf DECIMAL(5,2) NOT NULL,
  tarifa_arl_i DECIMAL(5,3) NOT NULL,
  tarifa_arl_ii DECIMAL(5,3) NOT NULL,
  tarifa_arl_iii DECIMAL(5,3) NOT NULL,
  tarifa_arl_iv DECIMAL(5,3) NOT NULL,
  tarifa_arl_v DECIMAL(5,3) NOT NULL,
  CONSTRAINT ck_parametro_valores CHECK (salario_minimo > 0 AND divisor_hora BETWEEN 200 AND 240 AND recargo_dominical BETWEEN 0 AND 200),
  CONSTRAINT ck_parametro_tarifas CHECK (tarifa_salud_empleador BETWEEN 0 AND 100 AND tarifa_salud_empleado BETWEEN 0 AND 100 AND tarifa_pension_empleador BETWEEN 0 AND 100 AND tarifa_pension_empleado BETWEEN 0 AND 100
        AND tarifa_caja BETWEEN 0 AND 100 AND tarifa_sena_icbf BETWEEN 0 AND 100 AND tarifa_arl_i BETWEEN 0.001 AND 100 AND tarifa_arl_ii BETWEEN 0.001 AND 100 AND tarifa_arl_iii BETWEEN 0.001 AND 100 AND tarifa_arl_iv BETWEEN 0.001 AND 100 AND tarifa_arl_v BETWEEN 0.001 AND 100)
);

CREATE TABLE Planilla (
  id_planilla INT AUTO_INCREMENT PRIMARY KEY,
  parametro_id INT NOT NULL,
  periodo DATE NOT NULL,
  tipo_planilla ENUM('NORMAL','CORRECCION') NOT NULL DEFAULT 'NORMAL',
  correccion_nro TINYINT NOT NULL DEFAULT 0,
  origen ENUM('SISTEMA','IMPORTADA') NOT NULL DEFAULT 'SISTEMA',
  estado ENUM('BORRADOR','APROBADA','GENERADA','PAGADA') NOT NULL DEFAULT 'BORRADOR',
  fecha_limite DATE NOT NULL,
  archivo_pila_clave VARCHAR(64) NULL,
  fecha_pago DATE NULL,
  monto_pagado DECIMAL(14,2) NULL,
  comprobante_clave VARCHAR(64) NULL,
  comprobante_sha256 CHAR(64) NULL,
  INDEX idx_planilla_periodo (periodo, correccion_nro),
  CONSTRAINT ck_planilla_periodo CHECK (DAYOFMONTH(periodo) = 1),
  CONSTRAINT ck_planilla_tipo CHECK ((tipo_planilla = 'NORMAL' AND correccion_nro = 0) OR (tipo_planilla = 'CORRECCION' AND correccion_nro > 0)),
  CONSTRAINT ck_planilla_pago CHECK ((estado = 'PAGADA' AND fecha_pago IS NOT NULL AND monto_pagado IS NOT NULL AND comprobante_clave IS NOT NULL AND comprobante_sha256 IS NOT NULL)
        OR (estado <> 'PAGADA' AND fecha_pago IS NULL AND monto_pagado IS NULL AND comprobante_clave IS NULL AND comprobante_sha256 IS NULL)),
  CONSTRAINT ck_planilla_archivo CHECK (estado IN ('BORRADOR','APROBADA') OR archivo_pila_clave IS NOT NULL),
  CONSTRAINT fk_planilla_parametro FOREIGN KEY (parametro_id) REFERENCES Parametro_Legal (id_parametro)
);

CREATE TABLE Detalle_Planilla (
  id_detalle INT AUTO_INCREMENT PRIMARY KEY,
  planilla_id INT NOT NULL,
  novedad_id INT NOT NULL,
  ibc DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_salud DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_pension DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_arl DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_parafiscales DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT uq_detalle UNIQUE (planilla_id, novedad_id),
  CONSTRAINT ck_detalle_valores CHECK (ibc >= 0 AND valor_salud >= 0 AND valor_pension >= 0 AND valor_arl >= 0 AND valor_parafiscales >= 0),
  CONSTRAINT fk_detalle_planilla FOREIGN KEY (planilla_id) REFERENCES Planilla (id_planilla) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_novedad FOREIGN KEY (novedad_id) REFERENCES Novedad (id_novedad)
);

CREATE VIEW v_planilla_total AS
SELECT p.id_planilla, MIN(c.empresa_id) AS empresa_id, p.periodo, p.tipo_planilla, p.correccion_nro, p.estado,
       COUNT(d.id_detalle) AS lineas,
       COALESCE(SUM(d.valor_salud + d.valor_pension + d.valor_arl + d.valor_parafiscales), 0) AS total_aportes
FROM Planilla p
LEFT JOIN Detalle_Planilla d ON d.planilla_id = p.id_planilla
LEFT JOIN Novedad n ON n.id_novedad = d.novedad_id
LEFT JOIN Usuario u ON u.id_usuario = n.empleado_id
LEFT JOIN Cargos c ON c.id_cargo = u.cargo_id
GROUP BY p.id_planilla, p.periodo, p.tipo_planilla, p.correccion_nro, p.estado;

-- ============================================================
--  LAVANDERÍA SUPERIOR — Esquema de Base de Datos
--  MySQL 8.0+ / MariaDB 10.4+ | Normalizado en 3FN | Esquema v2
--  Proyecto universitario — Universidad Politécnica Territorial
--  de los Llanos "Juana Ramírez" (UPTLL), Valle de la Pascua
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- Base de datos
CREATE DATABASE IF NOT EXISTS lavanderia_superior
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lavanderia_superior;

-- ============================================================
-- TABLA: clientes
-- ============================================================
CREATE TABLE clientes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombres          VARCHAR(120)  NOT NULL DEFAULT '' COMMENT 'Nombres, o razón social si es RIF J/G',
  apellidos        VARCHAR(60)   NOT NULL DEFAULT '',
  nombre           VARCHAR(120)  NOT NULL COMMENT 'Nombre completo (nombres + apellidos)',
  cedula_rif       VARCHAR(20)   NOT NULL UNIQUE COMMENT 'Formato: V-12345678, E-…, P-…, J-12345678-9, G-…',
  telefono         VARCHAR(20)   NOT NULL COMMENT 'Número WhatsApp con código de país',
  direccion        TEXT,
  email            VARCHAR(100),
  puntos_acumulados INT UNSIGNED  DEFAULT 0,
  activo           TINYINT(1)    DEFAULT 1,
  fecha_registro   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cedula (cedula_rif),
  INDEX idx_telefono (telefono)
) ENGINE=InnoDB COMMENT='Clientes registrados del negocio';

-- ============================================================
-- TABLA: servicios
-- ============================================================
CREATE TABLE servicios (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre                 VARCHAR(100) NOT NULL,
  descripcion            TEXT,
  unidad_medida          ENUM('Kg','Unidad','Par','Pieza') NOT NULL DEFAULT 'Kg',
  precio_usd             DECIMAL(10,4) NOT NULL,
  tiempo_estimado_horas  DECIMAL(5,1)  NOT NULL DEFAULT 4.0,
  activo                 TINYINT(1)    DEFAULT 1,
  INDEX idx_activo (activo)
) ENGINE=InnoDB COMMENT='Catálogo de servicios ofrecidos';

-- ============================================================
-- TABLA: tasas_cambio
-- ============================================================
CREATE TABLE tasas_cambio (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha      DATE         NOT NULL UNIQUE,
  tasa_bcv   DECIMAL(12,4) NOT NULL COMMENT 'Tasa Banco Central de Venezuela',
  tasa_paralelo DECIMAL(12,4) COMMENT 'Tasa referencial paralela (opcional)',
  registrado_por VARCHAR(80),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fecha (fecha)
) ENGINE=InnoDB COMMENT='Historial de tasas de cambio BS/USD';

-- ============================================================
-- TABLA: ordenes
-- ============================================================
CREATE TABLE ordenes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id       INT UNSIGNED NOT NULL,
  fecha_recepcion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_promesa    DATETIME     NOT NULL,
  fecha_entrega    DATETIME,
  estado           ENUM('Recibido','Lavando','Secando','Listo','Entregado','Cancelado')
                   NOT NULL DEFAULT 'Recibido',
  total_usd        DECIMAL(12,4) NOT NULL DEFAULT 0,
  total_bs         DECIMAL(16,2),
  tasa_cambio_id   INT UNSIGNED,
  metodo_pago      ENUM('Efectivo_USD','Efectivo_BS','Pago_Movil','Punto_Venta','Transferencia','Mixto')
                   DEFAULT 'Efectivo_USD',
  notas            TEXT,
  foto_recepcion   VARCHAR(255) COMMENT 'Ruta a imagen de evidencia',
  notificado_whatsapp TINYINT(1) DEFAULT 0,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE,
  FOREIGN KEY (tasa_cambio_id) REFERENCES tasas_cambio(id),
  INDEX idx_estado (estado),
  INDEX idx_fecha_promesa (fecha_promesa),
  INDEX idx_cliente (cliente_id)
) ENGINE=InnoDB COMMENT='Órdenes de servicio';

-- ============================================================
-- TABLA: detalle_orden
-- ============================================================
CREATE TABLE detalle_orden (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  orden_id    INT UNSIGNED  NOT NULL,
  servicio_id INT UNSIGNED  NOT NULL,
  cantidad    DECIMAL(8,3)  NOT NULL COMMENT 'Kg o unidades según servicio',
  precio_usd  DECIMAL(10,4) NOT NULL COMMENT 'Precio capturado al momento',
  subtotal    DECIMAL(12,4) GENERATED ALWAYS AS (cantidad * precio_usd) STORED,
  notas       VARCHAR(255),
  FOREIGN KEY (orden_id)    REFERENCES ordenes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id),
  INDEX idx_orden (orden_id)
) ENGINE=InnoDB COMMENT='Líneas de detalle por orden';

-- ============================================================
-- TABLA: movimientos_caja
-- ============================================================
CREATE TABLE movimientos_caja (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo           ENUM('Ingreso','Egreso') NOT NULL,
  concepto       ENUM('Servicio','Proveedor','Nomina','Suministros','Otro') NOT NULL,
  descripcion    VARCHAR(255) NOT NULL,
  monto_usd      DECIMAL(12,4),
  monto_bs       DECIMAL(16,2),
  tasa_cambio    DECIMAL(12,4),
  orden_id       INT UNSIGNED COMMENT 'Vincula al ingreso de una orden',
  fecha          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  registrado_por VARCHAR(80),
  FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE SET NULL,
  INDEX idx_fecha (fecha),
  INDEX idx_tipo (tipo)
) ENGINE=InnoDB COMMENT='Libro de caja del negocio';

-- ============================================================
-- TABLA: inventario_insumos
-- ============================================================
CREATE TABLE inventario_insumos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(100) NOT NULL,
  unidad         ENUM('ml','L','g','Kg','Unidad') NOT NULL DEFAULT 'ml',
  stock_actual   DECIMAL(10,3) NOT NULL DEFAULT 0,
  stock_minimo   DECIMAL(10,3) NOT NULL DEFAULT 500,
  consumo_por_kg DECIMAL(8,4)  COMMENT 'Cantidad usada por cada kg de ropa lavada',
  costo_unidad   DECIMAL(10,4),
  activo         TINYINT(1)   DEFAULT 1,
  INDEX idx_stock (stock_actual)
) ENGINE=InnoDB COMMENT='Inventario de químicos e insumos';

-- ============================================================
-- TABLA: consumo_insumos
-- Registra el descuento automático de insumos por orden
-- ============================================================
CREATE TABLE consumo_insumos (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  orden_id     INT UNSIGNED NOT NULL,
  insumo_id    INT UNSIGNED NOT NULL,
  cantidad_usada DECIMAL(10,4) NOT NULL,
  fecha        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (orden_id)  REFERENCES ordenes(id) ON DELETE CASCADE,
  FOREIGN KEY (insumo_id) REFERENCES inventario_insumos(id),
  INDEX idx_orden (orden_id)
) ENGINE=InnoDB COMMENT='Trazabilidad del consumo de insumos';

-- ============================================================
-- TABLA: usuarios (acceso al sistema)
-- ============================================================
CREATE TABLE usuarios (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(80)  NOT NULL,
  username     VARCHAR(40)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  rol          ENUM('admin','vendedor') NOT NULL DEFAULT 'vendedor',
  activo       TINYINT(1)   DEFAULT 1,
  ultimo_login DATETIME,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Usuarios con acceso al sistema';

-- ============================================================
-- TABLA: tipos_prenda (catálogo)
-- ============================================================
CREATE TABLE tipos_prenda (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(60) NOT NULL UNIQUE,
  activo  TINYINT(1)  NOT NULL DEFAULT 1,
  orden   INT         NOT NULL DEFAULT 0
) ENGINE=InnoDB COMMENT='Catálogo de tipos de prenda';

-- ============================================================
-- TABLA: prendas_orden
-- Prendas recibidas en cada orden (se imprimen en el ticket)
-- ============================================================
CREATE TABLE prendas_orden (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  orden_id       INT UNSIGNED NOT NULL,
  tipo_prenda_id INT UNSIGNED NULL,
  descripcion    VARCHAR(60)  NOT NULL COMMENT 'Nombre del tipo al momento de registrar',
  cantidad       SMALLINT UNSIGNED NOT NULL,
  observacion    VARCHAR(150) NULL COMMENT 'Color, marca, daños previos...',
  FOREIGN KEY (orden_id)       REFERENCES ordenes(id)      ON DELETE CASCADE,
  FOREIGN KEY (tipo_prenda_id) REFERENCES tipos_prenda(id) ON DELETE SET NULL,
  INDEX idx_orden (orden_id)
) ENGINE=InnoDB COMMENT='Prendas recibidas en cada orden';

-- ============================================================
-- TABLA: sistema_config (parámetros internos)
-- ============================================================
CREATE TABLE sistema_config (
  clave      VARCHAR(60) PRIMARY KEY,
  valor      TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Parámetros internos del sistema';

-- ============================================================
-- DATOS INICIALES (SEED)
-- ============================================================

-- Tasa inicial de ejemplo
INSERT INTO tasas_cambio (fecha, tasa_bcv, registrado_por)
VALUES (CURDATE(), 46.50, 'sistema');

-- Servicios base
INSERT INTO servicios (nombre, unidad_medida, precio_usd, tiempo_estimado_horas) VALUES
  ('Lavado por Peso',     'Kg',     1.50,  6.0),
  ('Solo Secado',         'Kg',     0.80,  2.0),
  ('Lavado + Secado',     'Kg',     2.20,  8.0),
  ('Edredón Simple',      'Unidad', 5.00, 12.0),
  ('Edredón Doble',       'Unidad', 8.00, 14.0),
  ('Tintorería Camisa',   'Unidad', 3.50,  8.0),
  ('Tintorería Traje',    'Unidad', 8.00, 24.0),
  ('Ropa Delicada',       'Kg',     3.00,  8.0);

-- Insumos base
INSERT INTO inventario_insumos (nombre, unidad, stock_actual, stock_minimo, consumo_por_kg) VALUES
  ('Detergente Industrial', 'ml',  8000, 1000, 25),
  ('Suavizante',            'ml',  5000, 500,  15),
  ('Blanqueador',           'ml',  3000, 300,  10),
  ('Desengrasante',         'ml',  2000, 200,   5);

-- Tipos de prenda
INSERT INTO tipos_prenda (nombre, orden) VALUES
  ('Camisa',1),('Franela',2),('Pantalón',3),('Short / Bermuda',4),('Falda',5),('Vestido',6),
  ('Blusa',7),('Chaqueta / Suéter',8),('Traje / Paltó',9),('Uniforme',10),('Ropa interior',11),
  ('Medias (par)',12),('Pijama',13),('Ropa de bebé',14),('Sábana',15),('Funda de almohada',16),
  ('Toalla',17),('Cobija',18),('Edredón',19),('Cortina',20),('Mantel',21),
  ('Zapatos deportivos (par)',22),('Otro',23);

INSERT INTO sistema_config (clave, valor) VALUES ('schema_version', '5');

-- El primer administrador se crea desde setup.php (no se incluyen contraseñas aquí)

SET FOREIGN_KEY_CHECKS = 1;

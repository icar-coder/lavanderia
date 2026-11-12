<?php
// ============================================================
//  config/migraciones.php — Actualizaciones automáticas del esquema
//  Lavandería Superior
//
//  Se ejecuta una sola vez por versión: la versión aplicada se
//  guarda en la tabla `sistema_config`. Todas las sentencias son
//  idempotentes, por lo que es seguro volver a ejecutarlas.
// ============================================================

define('SCHEMA_VERSION', 5);

function aplicarMigraciones(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS sistema_config (
        clave VARCHAR(60) PRIMARY KEY,
        valor TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Parámetros internos del sistema'");

    $actual = (int) ($db->query("SELECT valor FROM sistema_config WHERE clave='schema_version'")->fetchColumn() ?: 1);
    if ($actual >= SCHEMA_VERSION) return;

    // ── v2: tipos de prenda y prendas por orden ─────────────
    $db->exec("CREATE TABLE IF NOT EXISTS tipos_prenda (
        id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre  VARCHAR(60) NOT NULL UNIQUE,
        activo  TINYINT(1)  NOT NULL DEFAULT 1,
        orden   INT         NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catálogo de tipos de prenda'");

    $db->exec("CREATE TABLE IF NOT EXISTS prendas_orden (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        orden_id       INT UNSIGNED NOT NULL,
        tipo_prenda_id INT UNSIGNED NULL,
        descripcion    VARCHAR(60)  NOT NULL COMMENT 'Nombre del tipo al momento de registrar',
        cantidad       SMALLINT UNSIGNED NOT NULL,
        observacion    VARCHAR(150) NULL COMMENT 'Color, marca, daños previos...',
        FOREIGN KEY (orden_id)       REFERENCES ordenes(id)      ON DELETE CASCADE,
        FOREIGN KEY (tipo_prenda_id) REFERENCES tipos_prenda(id) ON DELETE SET NULL,
        INDEX idx_orden (orden_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Prendas recibidas en cada orden'");

    if ((int) $db->query("SELECT COUNT(*) FROM tipos_prenda")->fetchColumn() === 0) {
        $tipos = ['Camisa', 'Franela', 'Pantalón', 'Short / Bermuda', 'Falda', 'Vestido', 'Blusa',
                  'Chaqueta / Suéter', 'Traje / Paltó', 'Uniforme', 'Ropa interior', 'Medias (par)',
                  'Pijama', 'Ropa de bebé', 'Sábana', 'Funda de almohada', 'Toalla', 'Cobija',
                  'Edredón', 'Cortina', 'Mantel', 'Zapatos deportivos (par)', 'Otro'];
        $ins = $db->prepare("INSERT IGNORE INTO tipos_prenda (nombre, orden) VALUES (?, ?)");
        foreach ($tipos as $i => $t) $ins->execute([$t, $i + 1]);
    }

    // ── v3: método de pago «Punto de venta» ──────────────────
    $db->exec("ALTER TABLE ordenes MODIFY metodo_pago
               ENUM('Efectivo_USD','Efectivo_BS','Pago_Movil','Punto_Venta','Transferencia','Mixto')
               DEFAULT 'Efectivo_USD'");

    // ── v4: nombres y apellidos separados ───────────────────
    // `nombre` se conserva como nombre completo (lo usan tickets,
    // reportes y búsquedas); se recalcula al guardar el cliente.
    $existe = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes'
                          AND COLUMN_NAME = 'nombres'")->fetchColumn();
    if (!$existe) {
        $db->exec("ALTER TABLE clientes
                   ADD nombres   VARCHAR(120) NOT NULL DEFAULT '' AFTER id,
                   ADD apellidos VARCHAR(60) NOT NULL DEFAULT '' AFTER nombres");
        // Datos existentes: primera palabra = nombres, el resto = apellidos.
        // Empresas (RIF J/G): todo el texto queda como razón social.
        $db->exec("UPDATE clientes SET
                     nombres   = IF(cedula_rif REGEXP '^[JG]-' OR LOCATE(' ', TRIM(nombre)) = 0,
                                    LEFT(TRIM(nombre), 120),
                                    LEFT(SUBSTRING_INDEX(TRIM(nombre), ' ', 1), 60)),
                     apellidos = IF(cedula_rif REGEXP '^[JG]-' OR LOCATE(' ', TRIM(nombre)) = 0,
                                    '',
                                    LEFT(TRIM(SUBSTRING(TRIM(nombre), LOCATE(' ', TRIM(nombre)) + 1)), 60))");
    }

    // ── v5: documentos antiguos sin prefijo ─────────────────
    // "12345678" se guarda como "V-12345678", el formato que usa el sistema
    // desde la v2. Se omite si ya existe otro cliente con ese documento.
    $db->exec("UPDATE clientes c
               LEFT JOIN clientes o ON o.cedula_rif = CONCAT('V-', c.cedula_rif)
               SET c.cedula_rif = CONCAT('V-', c.cedula_rif)
               WHERE c.cedula_rif REGEXP '^[0-9]{6,8}$' AND o.id IS NULL");
    // Registros con nombres vacíos (alta anterior a la v4): se toman del nombre completo
    $db->exec("UPDATE clientes SET nombres = LEFT(nombre, 120) WHERE nombres = ''");

    $db->prepare("INSERT INTO sistema_config (clave, valor) VALUES ('schema_version', ?)
                  ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
       ->execute([SCHEMA_VERSION]);
}

// Una comprobación por sesión basta: evita una consulta en cada petición.
if (empty($_SESSION['_schema_v']) || $_SESSION['_schema_v'] < SCHEMA_VERSION) {
    try {
        aplicarMigraciones();
        $_SESSION['_schema_v'] = SCHEMA_VERSION;
    } catch (Throwable $e) {
        error_log('Migración fallida: ' . $e->getMessage());
    }
}

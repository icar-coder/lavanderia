<?php
// ============================================================
//  config/database.php — Conexión PDO y funciones del sistema
//  Lavandería Superior
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'lavanderia_superior');
define('DB_USER', 'root');      // Cambiar en producción
define('DB_PASS', '');          // Cambiar en producción
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // El detalle técnico va al log; al usuario se le indica qué revisar
            error_log('Conexión a la BD fallida: ' . $e->getMessage());
            http_response_code(503);
            $msg = 'No se pudo conectar con la base de datos. Verifique que MySQL esté encendido en XAMPP.';
            if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
                header('Content-Type: application/json; charset=utf-8');
                exit(json_encode(['success' => false, 'ok' => false, 'error' => $msg]));
            }
            exit('<p style="font-family:sans-serif;padding:24px;color:#b91c1c;">' . $msg . '</p>');
        }
    }
    return $pdo;
}

// ============================================================
// FUNCIONES: CLIENTES
// ============================================================

function buscarClientePorCedula(string $cedula): ?array {
    $stmt = getDB()->prepare("SELECT * FROM clientes WHERE cedula_rif = ? LIMIT 1");
    $stmt->execute([$cedula]);
    return $stmt->fetch() ?: null;
}

function crearCliente(array $data): int {
    $sql = "INSERT INTO clientes (nombres, apellidos, nombre, cedula_rif, telefono, direccion, email)
            VALUES (:nombres, :apellidos, :nombre, :cedula_rif, :telefono, :direccion, :email)";
    $stmt = getDB()->prepare($sql);
    $stmt->bindValue(':nombres',    $data[':nombres']    ?? $data['nombres']    ?? '');
    $stmt->bindValue(':apellidos',  $data[':apellidos']  ?? $data['apellidos']  ?? '');
    $stmt->bindValue(':nombre',     $data[':nombre']     ?? $data['nombre']     ?? '');
    $stmt->bindValue(':cedula_rif', $data[':cedula_rif'] ?? $data['cedula_rif'] ?? '');
    $stmt->bindValue(':telefono',   $data[':telefono']   ?? $data['telefono']   ?? '');
    $stmt->bindValue(':direccion',  $data[':direccion']  ?? $data['direccion']  ?? '');
    $stmt->bindValue(':email',      $data[':email']      ?? $data['email']      ?? '');
    $stmt->execute();
    return (int) getDB()->lastInsertId();
}

function listarClientes(string $busqueda = '', int $pagina = 1, int $porPagina = 20): array {
    $offset = ($pagina - 1) * $porPagina;
    if ($busqueda) {
        // PDO no permite el mismo nombre de param repetido; usar ? posicionales
        $like = '%' . escaparLike($busqueda) . '%';
        $sql  = "SELECT * FROM clientes
                 WHERE nombre LIKE ? OR cedula_rif LIKE ? OR telefono LIKE ?
                 ORDER BY nombre
                 LIMIT $porPagina OFFSET $offset";
        $stmt = getDB()->prepare($sql);
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = getDB()->prepare(
            "SELECT * FROM clientes ORDER BY nombre LIMIT $porPagina OFFSET $offset"
        );
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

// ============================================================
// FUNCIONES: ÓRDENES
// ============================================================

/**
 * Crea una orden completa en una transacción.
 * @param array $datos     :cliente_id, :fecha_promesa, :total_usd, :total_bs, :tasa_cambio_id, :metodo_pago, :notas
 * @param array $detalles  [servicio_id, cantidad, precio_usd]
 * @param array $prendas   [tipo_prenda_id, descripcion, cantidad, observacion]
 */
function crearOrden(array $datos, array $detalles, array $prendas = []): int {
    $db = getDB();
    $db->beginTransaction();
    try {
        // 1. Insertar orden
        $sql = "INSERT INTO ordenes
                    (cliente_id, fecha_promesa, estado, total_usd, total_bs, tasa_cambio_id, metodo_pago, notas)
                VALUES
                    (:cliente_id, :fecha_promesa, 'Recibido', :total_usd, :total_bs, :tasa_cambio_id, :metodo_pago, :notas)";
        $stmt = $db->prepare($sql);
        $stmt->execute($datos);
        $ordenId = (int) $db->lastInsertId();

        // 2. Insertar detalles
        $sqlDetalle = "INSERT INTO detalle_orden (orden_id, servicio_id, cantidad, precio_usd)
                       VALUES (:orden_id, :servicio_id, :cantidad, :precio_usd)";
        $stmtDet = $db->prepare($sqlDetalle);
        foreach ($detalles as $det) {
            $det['orden_id'] = $ordenId;
            $stmtDet->execute($det);
        }

        // 3. Insertar prendas recibidas
        if ($prendas) {
            $stmtPr = $db->prepare("INSERT INTO prendas_orden (orden_id, tipo_prenda_id, descripcion, cantidad, observacion)
                                    VALUES (?, ?, ?, ?, ?)");
            foreach ($prendas as $p) {
                $stmtPr->execute([$ordenId, $p['tipo_prenda_id'], $p['descripcion'], $p['cantidad'], $p['observacion'] ?: null]);
            }
        }

        // 4. Descontar insumos solo por los kilos lavados (no por unidades/pares)
        $kg = $db->prepare("SELECT COALESCE(SUM(cantidad),0) FROM detalle_orden d
                            JOIN servicios s ON s.id = d.servicio_id
                            WHERE d.orden_id = ? AND s.unidad_medida = 'Kg'");
        $kg->execute([$ordenId]);
        descontarInsumos($ordenId, (float) $kg->fetchColumn());

        // 5. Registrar en caja
        $tasa = obtenerTasaHoy();
        $descOrden = 'Orden #' . str_pad($ordenId, 4, '0', STR_PAD_LEFT);
        $montoUSD  = isset($datos[':total_usd']) ? (float)$datos[':total_usd'] : 0.0;
        $montoBS   = isset($datos[':total_bs'])  ? (float)$datos[':total_bs']  : 0.0;
        $db->prepare("INSERT INTO movimientos_caja
                      (tipo, concepto, descripcion, monto_usd, monto_bs, tasa_cambio, orden_id, registrado_por)
                      VALUES ('Ingreso','Servicio', :desc, :usd, :bs, :tasa, :oid, :usr)")
           ->execute([':desc'=> $descOrden, ':usd' => $montoUSD,
                      ':bs' => $montoBS, ':tasa' => $tasa['tasa_bcv'], ':oid' => $ordenId,
                      ':usr' => $_SESSION['usuario_nombre'] ?? 'sistema']);

        $db->commit();
        return $ordenId;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/** Tipos de prenda activos para los formularios. */
function listarTiposPrenda(bool $soloActivos = true): array {
    $sql = "SELECT * FROM tipos_prenda" . ($soloActivos ? " WHERE activo = 1" : "") . " ORDER BY orden, nombre";
    return getDB()->query($sql)->fetchAll();
}

/** Estado del que debe venir cada estado (el flujo solo avanza un paso). */
const ESTADO_ANTERIOR = ['Lavando' => 'Recibido', 'Secando' => 'Lavando', 'Listo' => 'Secando', 'Entregado' => 'Listo'];

/**
 * Avanza la orden al estado indicado. Falla si no viene del estado anterior
 * (evita saltos, retrocesos o mover órdenes canceladas/entregadas, incluso
 * si dos personas pulsan a la vez). La cancelación usa cancelarOrden().
 */
function cambiarEstadoOrden(int $ordenId, string $nuevoEstado): bool {
    $anterior = ESTADO_ANTERIOR[$nuevoEstado] ?? null;
    if (!$anterior) return false;

    $sql = "UPDATE ordenes SET estado = :estado" .
           ($nuevoEstado === 'Entregado' ? ", fecha_entrega = NOW()" : "") .
           ($nuevoEstado === 'Listo' ? ", notificado_whatsapp = 0" : "") .
           " WHERE id = :id AND estado = :anterior";
    $stmt = getDB()->prepare($sql);
    $stmt->execute([':estado' => $nuevoEstado, ':id' => $ordenId, ':anterior' => $anterior]);
    return $stmt->rowCount() === 1;
}

/**
 * Cancela una orden que aún no ha empezado a lavarse: anula el cobro en
 * caja y devuelve al inventario los insumos descontados.
 */
function cancelarOrden(int $ordenId, string $motivo): void {
    $db = getDB();
    $db->beginTransaction();
    try {
        $st = $db->prepare("SELECT estado, notas FROM ordenes WHERE id = ? FOR UPDATE");
        $st->execute([$ordenId]);
        $orden = $st->fetch();
        if (!$orden) throw new Exception('La orden no existe.');
        if ($orden['estado'] !== 'Recibido') throw new Exception('Solo se pueden cancelar órdenes en estado «Recibido».');

        // Devolver insumos
        $cons = $db->prepare("SELECT insumo_id, cantidad_usada FROM consumo_insumos WHERE orden_id = ?");
        $cons->execute([$ordenId]);
        $upd = $db->prepare("UPDATE inventario_insumos SET stock_actual = stock_actual + ? WHERE id = ?");
        foreach ($cons->fetchAll() as $c) $upd->execute([$c['cantidad_usada'], $c['insumo_id']]);
        $db->prepare("DELETE FROM consumo_insumos WHERE orden_id = ?")->execute([$ordenId]);

        // Anular el ingreso de caja de la orden
        $db->prepare("DELETE FROM movimientos_caja WHERE orden_id = ? AND tipo = 'Ingreso' AND concepto = 'Servicio'")
           ->execute([$ordenId]);

        $nota = trim(($orden['notas'] ? $orden['notas'] . "\n" : '') .
                '[Cancelada ' . date('d/m/Y H:i') . ' por ' . ($_SESSION['usuario_nombre'] ?? 'sistema') . ': ' . $motivo . ']');
        $db->prepare("UPDATE ordenes SET estado = 'Cancelado', notas = ? WHERE id = ?")->execute([$nota, $ordenId]);
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function obtenerOrdenesPorEstado(string $estado, int $limite = 50): array {
    $stmt = getDB()->prepare("
        SELECT o.*, c.nombre AS cliente_nombre, c.telefono,
               (SELECT GROUP_CONCAT(s.nombre ORDER BY d.id SEPARATOR ', ')
                  FROM detalle_orden d JOIN servicios s ON s.id = d.servicio_id
                 WHERE d.orden_id = o.id) AS servicio_nombre,
               (SELECT COALESCE(SUM(p.cantidad),0) FROM prendas_orden p WHERE p.orden_id = o.id) AS total_prendas
        FROM ordenes o
        JOIN clientes c ON o.cliente_id = c.id
        WHERE o.estado = ?
        ORDER BY o.fecha_promesa ASC
        LIMIT ?
    ");
    $stmt->execute([$estado, $limite]);
    return $stmt->fetchAll();
}

function contarOrdenesPorEstado(string $estado): int {
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM ordenes WHERE estado = ?");
    $stmt->execute([$estado]);
    return (int) $stmt->fetchColumn();
}

function obtenerUltimasOrdenes(int $n = 10): array {
    $stmt = getDB()->prepare("
        SELECT o.id, o.estado, o.total_usd, o.fecha_recepcion,
               c.nombre AS cliente_nombre,
               s.nombre AS servicio_nombre
        FROM ordenes o
        JOIN clientes c ON o.cliente_id = c.id
        LEFT JOIN detalle_orden d ON d.orden_id = o.id
        LEFT JOIN servicios s ON d.servicio_id = s.id
        GROUP BY o.id
        ORDER BY o.fecha_recepcion DESC
        LIMIT ?
    ");
    $stmt->execute([$n]);
    return $stmt->fetchAll();
}

// ============================================================
// FUNCIONES: DASHBOARD STATS
// ============================================================

function obtenerEstadisticasDashboard(): array {
    $db = getDB();

    $ingresosHoy = $db->query("
        SELECT COALESCE(SUM(total_usd), 0)
        FROM ordenes
        WHERE DATE(fecha_recepcion) = CURDATE() AND estado != 'Cancelado'
    ")->fetchColumn();

    $ingresosAyer = $db->query("
        SELECT COALESCE(SUM(total_usd), 0)
        FROM ordenes
        WHERE DATE(fecha_recepcion) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND estado != 'Cancelado'
    ")->fetchColumn();

    $ordenesHoy  = (int) $db->query("SELECT COUNT(*) FROM ordenes WHERE DATE(fecha_recepcion) = CURDATE()")->fetchColumn();
    $ordenesAyer = (int) $db->query("SELECT COUNT(*) FROM ordenes WHERE DATE(fecha_recepcion) = DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetchColumn();

    $pendientes = (int) $db->query("SELECT COUNT(*) FROM ordenes WHERE estado NOT IN ('Entregado','Cancelado')")->fetchColumn();
    $urgentes   = (int) $db->query("SELECT COUNT(*) FROM ordenes WHERE estado NOT IN ('Entregado','Cancelado') AND fecha_promesa <= DATE_ADD(NOW(), INTERVAL 2 HOUR)")->fetchColumn();

    $kgHoy  = (float) $db->query("SELECT COALESCE(SUM(d.cantidad),0) FROM detalle_orden d JOIN ordenes o ON d.orden_id = o.id JOIN servicios s ON d.servicio_id = s.id WHERE DATE(o.fecha_recepcion) = CURDATE() AND s.unidad_medida = 'Kg'")->fetchColumn();
    $kgAyer = (float) $db->query("SELECT COALESCE(SUM(d.cantidad),0) FROM detalle_orden d JOIN ordenes o ON d.orden_id = o.id JOIN servicios s ON d.servicio_id = s.id WHERE DATE(o.fecha_recepcion) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND s.unidad_medida = 'Kg'")->fetchColumn();

    return [
        'ingresos_usd_hoy'     => round($ingresosHoy, 2),
        'variacion_ingresos'   => $ingresosAyer > 0 ? round((($ingresosHoy - $ingresosAyer) / $ingresosAyer) * 100, 1) : 0,
        'ordenes_hoy'          => $ordenesHoy,
        'variacion_ordenes'    => $ordenesHoy - $ordenesAyer,
        'ordenes_pendientes'   => $pendientes,
        'ordenes_urgentes'     => $urgentes,
        'kg_hoy'               => $kgHoy,
        'variacion_kg'         => round($kgHoy - $kgAyer, 1),
    ];
}

function obtenerDatosChart7dias(): array {
    $stmt = getDB()->query("
        SELECT DATE(fecha_recepcion) AS dia, COALESCE(SUM(total_usd),0) AS total
        FROM ordenes
        WHERE fecha_recepcion >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND estado != 'Cancelado'
        GROUP BY dia
        ORDER BY dia ASC
    ");
    $rows = $stmt->fetchAll();
    $labels = [];
    $valores = [];
    foreach ($rows as $r) {
        $labels[]  = date('D d', strtotime($r['dia']));
        $valores[] = round($r['total'], 2);
    }
    return ['labels' => $labels, 'valores' => $valores];
}

// ============================================================
// FUNCIONES: INVENTARIO
// ============================================================

function obtenerAlertasStock(): array {
    $stmt = getDB()->query("
        SELECT * FROM inventario_insumos
        WHERE stock_actual <= stock_minimo AND activo = 1
        ORDER BY stock_actual ASC
    ");
    return $stmt->fetchAll();
}

function descontarInsumos(int $ordenId, float $totalKg): void {
    $db = getDB();
    $insumos = $db->query("SELECT * FROM inventario_insumos WHERE consumo_por_kg > 0 AND activo = 1")->fetchAll();
    foreach ($insumos as $insumo) {
        $consumo = round($insumo['consumo_por_kg'] * $totalKg, 4);
        $db->prepare("UPDATE inventario_insumos SET stock_actual = GREATEST(0, stock_actual - ?) WHERE id = ?")->execute([$consumo, $insumo['id']]);
        $db->prepare("INSERT INTO consumo_insumos (orden_id, insumo_id, cantidad_usada) VALUES (?,?,?)")->execute([$ordenId, $insumo['id'], $consumo]);
    }
}

// ============================================================
// FUNCIONES: TICKET DE ORDEN (datos completos para imprimir)
// ============================================================

/**
 * Retorna todos los datos necesarios para generar el ticket
 * de recepción o de entrega de una orden.
 */
function obtenerOrdenParaTicket(int $ordenId): ?array {
    $db = getDB();

    // Datos principales de la orden + cliente
    $stmt = $db->prepare("
        SELECT
            o.*,
            c.nombre        AS cliente_nombre,
            c.cedula_rif    AS cliente_cedula,
            c.telefono      AS cliente_telefono,
            c.direccion     AS cliente_direccion,
            t.tasa_bcv      AS tasa_bcv_usada
        FROM ordenes o
        JOIN clientes c ON c.id = o.cliente_id
        LEFT JOIN tasas_cambio t ON t.id = o.tasa_cambio_id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$ordenId]);
    $orden = $stmt->fetch();
    if (!$orden) return null;

    // Líneas de detalle
    $stmt2 = $db->prepare("
        SELECT
            d.cantidad,
            d.precio_usd,
            d.subtotal,
            d.notas         AS linea_notas,
            s.nombre        AS servicio_nombre,
            s.unidad_medida AS unidad
        FROM detalle_orden d
        JOIN servicios s ON s.id = d.servicio_id
        WHERE d.orden_id = ?
        ORDER BY d.id
    ");
    $stmt2->execute([$ordenId]);
    $orden['detalles'] = $stmt2->fetchAll();

    // Prendas recibidas
    $stmt3 = $db->prepare("SELECT descripcion, cantidad, observacion FROM prendas_orden WHERE orden_id = ? ORDER BY id");
    $stmt3->execute([$ordenId]);
    $orden['prendas'] = $stmt3->fetchAll();

    return $orden;
}

// ============================================================
// FUNCIONES: REPORTE COMPLETO (rango de fechas — para PDF y pantalla)
// ============================================================

/**
 * Obtiene todos los datos necesarios para el reporte PDF/pantalla.
 * @param string $desde  'YYYY-MM-DD'
 * @param string $hasta  'YYYY-MM-DD'
 */
function obtenerDatosReporte(string $desde, string $hasta): array {
    $db = getDB();

    // ── KPIs principales ─────────────────────────────────────
    $s = $db->prepare("
        SELECT
            COUNT(*)                        AS total_ordenes,
            COALESCE(SUM(total_usd),  0)    AS ingresos_usd,
            COALESCE(SUM(total_bs),   0)    AS ingresos_bs,
            COALESCE(AVG(total_usd),  0)    AS promedio_usd,
            COUNT(CASE WHEN estado='Entregado' THEN 1 END) AS entregadas,
            COUNT(CASE WHEN estado='Cancelado' THEN 1 END) AS canceladas
        FROM ordenes
        WHERE DATE(fecha_recepcion) BETWEEN ? AND ?
    ");
    $s->execute([$desde, $hasta]);
    $kpis = $s->fetch();

    // ── Kg procesados ─────────────────────────────────────────
    $s2 = $db->prepare("
        SELECT COALESCE(SUM(d.cantidad), 0) AS kg_total
        FROM detalle_orden d
        JOIN ordenes o ON d.orden_id = o.id
        JOIN servicios sv ON d.servicio_id = sv.id
        WHERE DATE(o.fecha_recepcion) BETWEEN ? AND ?
          AND sv.unidad_medida = 'Kg'
          AND o.estado != 'Cancelado'
    ");
    $s2->execute([$desde, $hasta]);
    $kpis['kg_total'] = (float) $s2->fetchColumn();

    // ── Ingresos por día ──────────────────────────────────────
    $s3 = $db->prepare("
        SELECT
            DATE(fecha_recepcion)        AS fecha,
            COUNT(*)                     AS ordenes,
            COALESCE(SUM(total_usd), 0)  AS usd,
            COALESCE(SUM(total_bs),  0)  AS bs
        FROM ordenes
        WHERE DATE(fecha_recepcion) BETWEEN ? AND ?
          AND estado != 'Cancelado'
        GROUP BY DATE(fecha_recepcion)
        ORDER BY fecha ASC
    ");
    $s3->execute([$desde, $hasta]);
    $porDia = $s3->fetchAll();

    // ── Top servicios ─────────────────────────────────────────
    $s4 = $db->prepare("
        SELECT
            sv.nombre,
            sv.unidad_medida,
            COUNT(*)                      AS veces,
            COALESCE(SUM(d.cantidad), 0)  AS cantidad_total,
            COALESCE(SUM(d.subtotal), 0)  AS monto_usd
        FROM detalle_orden d
        JOIN servicios sv ON d.servicio_id = sv.id
        JOIN ordenes o    ON d.orden_id    = o.id
        WHERE DATE(o.fecha_recepcion) BETWEEN ? AND ?
          AND o.estado != 'Cancelado'
        GROUP BY sv.id
        ORDER BY monto_usd DESC
    ");
    $s4->execute([$desde, $hasta]);
    $servicios = $s4->fetchAll();

    // ── Métodos de pago ───────────────────────────────────────
    $s5 = $db->prepare("
        SELECT
            metodo_pago,
            COUNT(*)                     AS cantidad,
            COALESCE(SUM(total_usd), 0)  AS usd
        FROM ordenes
        WHERE DATE(fecha_recepcion) BETWEEN ? AND ?
          AND estado != 'Cancelado'
        GROUP BY metodo_pago
        ORDER BY usd DESC
    ");
    $s5->execute([$desde, $hasta]);
    $metodosPago = $s5->fetchAll();

    // ── Top clientes ──────────────────────────────────────────
    $s6 = $db->prepare("
        SELECT
            c.nombre,
            c.cedula_rif,
            c.telefono,
            COUNT(o.id)                  AS ordenes,
            COALESCE(SUM(o.total_usd),0) AS total_usd,
            COALESCE(SUM(o.total_bs), 0) AS total_bs
        FROM ordenes o
        JOIN clientes c ON c.id = o.cliente_id
        WHERE DATE(o.fecha_recepcion) BETWEEN ? AND ?
          AND o.estado != 'Cancelado'
        GROUP BY c.id
        ORDER BY total_usd DESC
        LIMIT 10
    ");
    $s6->execute([$desde, $hasta]);
    $topClientes = $s6->fetchAll();

    // ── Egresos del período ───────────────────────────────────
    $s7 = $db->prepare("
        SELECT
            concepto,
            COUNT(*)                     AS cantidad,
            COALESCE(SUM(monto_usd), 0)  AS usd
        FROM movimientos_caja
        WHERE tipo='Egreso'
          AND DATE(fecha) BETWEEN ? AND ?
        GROUP BY concepto
        ORDER BY usd DESC
    ");
    $s7->execute([$desde, $hasta]);
    $egresos = $s7->fetchAll();

    $totalEgresosUSD = array_sum(array_column($egresos, 'usd'));

    // ── Tasa BCV promedio del período ─────────────────────────
    $s8 = $db->prepare("
        SELECT COALESCE(AVG(tasa_bcv), 1)
        FROM tasas_cambio
        WHERE fecha BETWEEN ? AND ?
    ");
    $s8->execute([$desde, $hasta]);
    $tasaPromedio = (float) $s8->fetchColumn();
    if ($tasaPromedio <= 0) $tasaPromedio = obtenerTasaHoy()['tasa_bcv'];

    return compact(
        'kpis', 'porDia', 'servicios', 'metodosPago',
        'topClientes', 'egresos', 'totalEgresosUSD', 'tasaPromedio'
    );
}

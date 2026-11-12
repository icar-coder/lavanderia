<?php
// ============================================================
//  config/whatsapp.php — Avisos por WhatsApp (modo semiautomático)
//  Lavandería Superior
//
//  Sin costo y sin API: el sistema arma el mensaje y abre un
//  enlace wa.me; el empleado solo pulsa «Enviar» en WhatsApp.
//  `ordenes.notificado_whatsapp` registra si ya se avisó.
// ============================================================

/** Orden + cliente con lo necesario para el aviso, o null si no existe. */
function datosAvisoWhatsApp(int $ordenId): ?array {
    $stmt = getDB()->prepare("
        SELECT o.id, o.estado, o.notificado_whatsapp,
               c.nombres, c.nombre, c.telefono,
               (SELECT COALESCE(SUM(p.cantidad),0) FROM prendas_orden p WHERE p.orden_id = o.id) AS total_prendas
        FROM ordenes o
        JOIN clientes c ON c.id = o.cliente_id
        WHERE o.id = ?
    ");
    $stmt->execute([$ordenId]);
    return $stmt->fetch() ?: null;
}

/**
 * Texto del aviso «orden lista». Sin emojis: algunas versiones de
 * WhatsApp Desktop los muestran dañados cuando llegan por enlace.
 */
function mensajeOrdenLista(array $o): string {
    $nombre  = $o['nombres'] !== '' ? $o['nombres'] : $o['nombre'];
    $prendas = (int) $o['total_prendas'];
    $lineas  = [
        "Hola {$nombre}, le saludamos de *Lavandería Superior*.",
        '',
        'Su orden *#' . str_pad($o['id'], 4, '0', STR_PAD_LEFT) . '* ya está *lista para retirar*'
            . ($prendas > 0 ? " ({$prendas} prenda" . ($prendas === 1 ? '' : 's') . ')' : '') . '.',
        'Por favor traiga su ticket de recepción.',
        '',
        'Valle de la Pascua, Guárico. ¡Le esperamos!',
    ];
    return implode("\n", $lineas);
}

/** Datos que la interfaz necesita para mostrar y abrir el aviso. */
function avisoOrdenLista(int $ordenId): ?array {
    $o = datosAvisoWhatsApp($ordenId);
    if (!$o) return null;
    return [
        'orden_id'  => (int) $o['id'],
        'cliente'   => $o['nombre'],
        'telefono'  => $o['telefono'],
        'wa'        => telefonoWhatsApp($o['telefono']),
        'mensaje'   => mensajeOrdenLista($o),
        'avisado'   => (bool) $o['notificado_whatsapp'],
        'estado'    => $o['estado'],
    ];
}

function marcarAvisadoWhatsApp(int $ordenId): bool {
    $st = getDB()->prepare("UPDATE ordenes SET notificado_whatsapp = 1
                            WHERE id = ? AND estado IN ('Listo','Entregado')");
    $st->execute([$ordenId]);
    // rowCount es 0 también si ya estaba marcada: se confirma que la orden califica
    if ($st->rowCount()) return true;
    $chk = getDB()->prepare("SELECT 1 FROM ordenes WHERE id = ? AND estado IN ('Listo','Entregado')");
    $chk->execute([$ordenId]);
    return (bool) $chk->fetchColumn();
}

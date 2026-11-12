<?php
// ============================================================
//  config/tasa.php — Gestión de tasa de cambio
//  Lavandería Superior
// ============================================================

function obtenerTasaHoy(): array {
    $stmt = getDB()->prepare("SELECT * FROM tasas_cambio WHERE fecha = CURDATE() LIMIT 1");
    $stmt->execute();
    $tasa = $stmt->fetch();
    if (!$tasa) {
        // Si no se registró hoy, devolver la última disponible
        $tasa = getDB()->query("SELECT * FROM tasas_cambio ORDER BY fecha DESC LIMIT 1")->fetch();
    }
    // fetch() devuelve false (no null) cuando no hay filas: sin tasa registrada se usa 1
    return $tasa ?: ['tasa_bcv' => 1, 'tasa_paralelo' => null, 'fecha' => date('Y-m-d')];
}

function registrarTasa(float $bcv, ?float $paralelo = null, string $usuario = 'sistema'): bool {
    $sql = "INSERT INTO tasas_cambio (fecha, tasa_bcv, tasa_paralelo, registrado_por)
            VALUES (CURDATE(), :bcv, :par, :usr)
            ON DUPLICATE KEY UPDATE tasa_bcv = :bcv2, tasa_paralelo = :par2, registrado_por = :usr2";
    $stmt = getDB()->prepare($sql);
    return $stmt->execute([
        ':bcv' => $bcv, ':par' => $paralelo, ':usr' => $usuario,
        ':bcv2'=> $bcv, ':par2'=> $paralelo, ':usr2'=> $usuario,
    ]);
}

function convertirUSDaBS(float $montoUSD, ?float $tasa = null): float {
    if (!$tasa) {
        $tasaHoy = obtenerTasaHoy();
        $tasa = $tasaHoy['tasa_bcv'];
    }
    return round($montoUSD * $tasa, 2);
}

function convertirBSaUSD(float $montoBS, ?float $tasa = null): float {
    if (!$tasa) {
        $tasaHoy = obtenerTasaHoy();
        $tasa = $tasaHoy['tasa_bcv'];
    }
    return $tasa > 0 ? round($montoBS / $tasa, 4) : 0;
}

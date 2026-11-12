<?php
// ============================================================
//  config/respaldo.php — Respaldo y restauración de la base de datos
//  Lavandería Superior
//
//  El respaldo se genera con PHP/PDO (no depende de mysqldump), por
//  lo que funciona igual en XAMPP y en cualquier hosting con PDO.
//  Los archivos se guardan en /respaldos, protegido contra acceso web.
// ============================================================

define('DIR_RESPALDOS', ROOT . 'respaldos' . DIRECTORY_SEPARATOR);
define('RESPALDO_MAX_BYTES', 50 * 1024 * 1024);   // 50 MB
define('RESPALDO_PATRON', '/^respaldo_[A-Za-z0-9_\-]+\.sql$/');

/** Crea la carpeta de respaldos y la protege del acceso directo por URL. */
function prepararDirRespaldos(): void {
    if (!is_dir(DIR_RESPALDOS)) mkdir(DIR_RESPALDOS, 0750, true);
    $ht = DIR_RESPALDOS . '.htaccess';
    if (!file_exists($ht)) file_put_contents($ht, "Require all denied\nDeny from all\n");
    $ix = DIR_RESPALDOS . 'index.html';
    if (!file_exists($ix)) file_put_contents($ix, '');
}

/** Ruta segura de un respaldo por nombre (evita salir de la carpeta). */
function rutaRespaldo(string $nombre): ?string {
    $nombre = basename($nombre);
    if (!preg_match(RESPALDO_PATRON, $nombre)) return null;
    $ruta = DIR_RESPALDOS . $nombre;
    return is_file($ruta) ? $ruta : null;
}

/**
 * Genera un volcado SQL completo (estructura + datos) de la base de datos.
 * @return string Nombre del archivo generado.
 */
function generarRespaldo(string $motivo = 'manual'): string {
    prepararDirRespaldos();
    @set_time_limit(300);
    $db     = getDB();
    $motivo = preg_replace('/[^a-z0-9_]/', '', strtolower($motivo)) ?: 'manual';
    $nombre = 'respaldo_' . date('Ymd_His') . '_' . $motivo . '.sql';
    $ruta   = DIR_RESPALDOS . $nombre;
    $fh     = fopen($ruta, 'wb');
    if (!$fh) throw new RuntimeException('No se pudo crear el archivo de respaldo.');

    $usuario = $_SESSION['usuario_nombre'] ?? 'sistema';
    fwrite($fh, "-- ============================================================\n");
    fwrite($fh, "-- Lavandería Superior — Respaldo de base de datos\n");
    fwrite($fh, "-- Base de datos : " . DB_NAME . "\n");
    fwrite($fh, "-- Fecha         : " . date('d/m/Y H:i:s') . "\n");
    fwrite($fh, "-- Generado por  : " . str_replace(["\n", "\r"], ' ', $usuario) . "\n");
    fwrite($fh, "-- Motivo        : {$motivo}\n");
    fwrite($fh, "-- Servidor      : " . $db->query('SELECT VERSION()')->fetchColumn() . "\n");
    fwrite($fh, "-- ============================================================\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

    $tablas = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

    // Columnas generadas: no se insertan (el motor las recalcula)
    $gen = $db->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = ? AND EXTRA LIKE '%GENERATED%'");
    $gen->execute([DB_NAME]);
    $generadas = [];
    foreach ($gen->fetchAll() as $g) $generadas[$g['TABLE_NAME']][] = $g['COLUMN_NAME'];

    foreach ($tablas as [$tabla]) {
        $create = $db->query("SHOW CREATE TABLE `{$tabla}`")->fetch(PDO::FETCH_NUM)[1];
        fwrite($fh, "-- ------------------------------------------------------------\n");
        fwrite($fh, "-- Tabla `{$tabla}`\n");
        fwrite($fh, "-- ------------------------------------------------------------\n");
        fwrite($fh, "DROP TABLE IF EXISTS `{$tabla}`;\n{$create};\n\n");

        $cols = array_column($db->query("SHOW COLUMNS FROM `{$tabla}`")->fetchAll(), 'Field');
        $cols = array_values(array_diff($cols, $generadas[$tabla] ?? []));
        $lista = '`' . implode('`, `', $cols) . '`';

        $stmt = $db->query("SELECT {$lista} FROM `{$tabla}`");
        $lote = [];
        while ($fila = $stmt->fetch(PDO::FETCH_NUM)) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $db->quote((string) $v), $fila);
            $lote[] = '(' . implode(', ', $vals) . ')';
            if (count($lote) === 200) {
                fwrite($fh, "INSERT INTO `{$tabla}` ({$lista}) VALUES\n" . implode(",\n", $lote) . ";\n");
                $lote = [];
            }
        }
        if ($lote) fwrite($fh, "INSERT INTO `{$tabla}` ({$lista}) VALUES\n" . implode(",\n", $lote) . ";\n");
        fwrite($fh, "\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n-- Fin del respaldo\n");
    fclose($fh);
    return $nombre;
}

/** Lista los respaldos disponibles, del más reciente al más antiguo. */
function listarRespaldos(): array {
    prepararDirRespaldos();
    $lista = [];
    foreach (glob(DIR_RESPALDOS . 'respaldo_*.sql') ?: [] as $ruta) {
        $nombre = basename($ruta);
        if (!preg_match(RESPALDO_PATRON, $nombre)) continue;
        preg_match('/_(\d{8}_\d{6})_([a-z0-9_]+)\.sql$/', $nombre, $m);
        $lista[] = [
            'nombre' => $nombre,
            'bytes'  => filesize($ruta),
            'fecha'  => filemtime($ruta),
            'motivo' => $m[2] ?? 'manual',
        ];
    }
    usort($lista, fn($a, $b) => $b['fecha'] <=> $a['fecha']);
    return $lista;
}

/** Tamaño legible. */
function tamanoLegible(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
}

/**
 * Divide un script SQL en sentencias individuales respetando comillas,
 * identificadores y comentarios. No admite DELIMITER (procedimientos).
 */
function dividirSentenciasSQL(string $sql): array {
    $sentencias = [];
    $actual = '';
    $len = strlen($sql);
    $comilla = null;
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $sig = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($comilla !== null) {
            $actual .= $c;
            if ($c === '\\' && $comilla !== '`') { $actual .= $sig; $i++; continue; }
            if ($c === $comilla) {
                if ($sig === $comilla) { $actual .= $sig; $i++; continue; } // comilla escapada duplicada
                $comilla = null;
            }
            continue;
        }
        // Comentarios de línea: -- y #
        if (($c === '-' && $sig === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) || $c === '#') {
            $fin = strpos($sql, "\n", $i);
            $i = $fin === false ? $len : $fin;
            $actual .= "\n";
            continue;
        }
        // Comentarios de bloque (los /*! ... */ son ejecutables en MySQL y se conservan)
        if ($c === '/' && $sig === '*') {
            $fin = strpos($sql, '*/', $i + 2);
            $fin = $fin === false ? $len : $fin + 2;
            if (($sql[$i + 2] ?? '') === '!') $actual .= substr($sql, $i, $fin - $i);
            $i = $fin - 1;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') { $comilla = $c; $actual .= $c; continue; }
        if ($c === ';') {
            if (trim($actual) !== '') $sentencias[] = trim($actual);
            $actual = '';
            continue;
        }
        $actual .= $c;
    }
    if (trim($actual) !== '') $sentencias[] = trim($actual);
    return $sentencias;
}

/**
 * Comprueba que un archivo parezca un respaldo válido de este sistema.
 * Devuelve la lista de sentencias o lanza excepción.
 */
function analizarArchivoRespaldo(string $ruta): array {
    $bytes = filesize($ruta);
    if ($bytes === 0) throw new RuntimeException('El archivo está vacío.');
    if ($bytes > RESPALDO_MAX_BYTES) throw new RuntimeException('El archivo supera el máximo de 50 MB.');
    $sql = file_get_contents($ruta);
    if (str_starts_with($sql, "\xEF\xBB\xBF")) $sql = substr($sql, 3); // BOM
    if (!mb_check_encoding($sql, 'UTF-8')) throw new RuntimeException('El archivo no está codificado en UTF-8.');
    if (preg_match('/^\s*DELIMITER\s/mi', $sql)) throw new RuntimeException('El archivo contiene procedimientos (DELIMITER), no soportados.');

    $sentencias = dividirSentenciasSQL($sql);
    $tablas = [];
    foreach ($sentencias as $s) {
        if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $s, $m)) $tablas[] = strtolower($m[1]);
    }
    foreach (['clientes', 'ordenes', 'usuarios'] as $req) {
        if (!in_array($req, $tablas, true))
            throw new RuntimeException("El archivo no parece un respaldo de este sistema (falta la tabla «{$req}»).");
    }
    // Se descartan sentencias que cambiarían de base de datos
    return array_values(array_filter($sentencias, fn($s) =>
        !preg_match('/^(CREATE\s+DATABASE|DROP\s+DATABASE|USE\s)/i', $s)));
}

/**
 * Reemplaza la base de datos actual por el contenido del archivo.
 * Antes crea un respaldo de seguridad y, si algo falla, lo reaplica.
 * @return array ['sentencias'=>int, 'seguridad'=>string]
 */
function restaurarRespaldo(string $ruta): array {
    @set_time_limit(600);
    $sentencias = analizarArchivoRespaldo($ruta);
    $seguridad  = generarRespaldo('antes_de_restaurar');
    $db = getDB();

    $ejecutar = function (array $lista) use ($db): int {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$t]) {
            $db->exec("DROP TABLE IF EXISTS `{$t}`");
        }
        $n = 0;
        foreach ($lista as $s) { $db->exec($s); $n++; }
        if ($db->inTransaction()) $db->commit();
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        return $n;
    };

    try {
        $n = $ejecutar($sentencias);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        try {
            $ejecutar(analizarArchivoRespaldo(DIR_RESPALDOS . $seguridad));
        } catch (Throwable $e2) {
            error_log('Fallo al revertir restauración: ' . $e2->getMessage());
        }
        throw new RuntimeException('Error al restaurar (se revirtieron los cambios): ' . $e->getMessage());
    }

    // Asegura que las tablas nuevas existan aunque el respaldo sea anterior
    unset($_SESSION['_schema_v']);
    aplicarMigraciones();
    return ['sentencias' => $n, 'seguridad' => $seguridad];
}

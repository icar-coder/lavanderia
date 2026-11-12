<?php
// ============================================================
//  config/validacion.php — Validación y normalización de datos
//  Lavandería Superior
//  Espejo en el cliente: assets/js/validaciones.js
// ============================================================

// Tipos de documento de identidad aceptados
define('TIPOS_DOCUMENTO', [
    'V' => 'Venezolano (Cédula)',
    'E' => 'Extranjero residente (Cédula)',
    'P' => 'Pasaporte',
    'J' => 'Jurídico (RIF)',
    'G' => 'Gobierno (RIF)',
]);

/**
 * Separa un documento guardado en BD en [tipo, número].
 * Los registros antiguos (solo dígitos) se interpretan como venezolanos.
 */
function separarDocumento(string $doc): array {
    $doc = strtoupper(trim($doc));
    if (preg_match('/^([VEPJG])[\s\-\.]*(.+)$/', $doc, $m)) {
        return [$m[1], preg_replace('/[^0-9A-Z]/', '', $m[2])];
    }
    return ['V', preg_replace('/[^0-9A-Z]/', '', $doc)];
}

/**
 * Valida tipo + número de documento. Devuelve el mensaje de error o null.
 */
function validarDocumento(string $tipo, string $numero): ?string {
    $tipo   = strtoupper(trim($tipo));
    $numero = strtoupper(trim($numero));
    if (!array_key_exists($tipo, TIPOS_DOCUMENTO)) return 'Tipo de documento inválido.';
    if ($numero === '') return 'El número de documento es obligatorio.';

    switch ($tipo) {
        case 'V':
            if (!ctype_digit($numero)) return 'La cédula solo puede contener números.';
            if (strlen($numero) < 6 || strlen($numero) > 8) return 'La cédula venezolana debe tener entre 6 y 8 dígitos.';
            break;
        case 'E':
            if (!ctype_digit($numero)) return 'La cédula solo puede contener números.';
            if (strlen($numero) < 6 || strlen($numero) > 10) return 'La cédula de extranjero debe tener entre 6 y 10 dígitos.';
            break;
        case 'J':
        case 'G':
            if (!ctype_digit($numero)) return 'El RIF solo puede contener números.';
            if (strlen($numero) !== 9) return 'El RIF debe tener 9 dígitos (8 + dígito verificador).';
            break;
        case 'P':
            if (!preg_match('/^[A-Z0-9]{5,15}$/', $numero)) return 'El pasaporte debe tener entre 5 y 15 letras o números.';
            break;
    }
    if (ctype_digit($numero) && (int) $numero === 0) return 'El número de documento no es válido.';
    return null;
}

/**
 * Formato canónico para guardar: V-12345678, E-84123456, J-12345678-9, P-AB123456
 */
function formatearDocumento(string $tipo, string $numero): string {
    $tipo   = strtoupper(trim($tipo));
    $numero = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $numero));
    if (in_array($tipo, ['J', 'G'], true) && strlen($numero) === 9) {
        return $tipo . '-' . substr($numero, 0, 8) . '-' . substr($numero, 8);
    }
    return $tipo . '-' . $numero;
}

/**
 * ¿Existe otro cliente con el mismo documento? Compara por tipo+número,
 * por lo que "12345678" (formato antiguo) y "V-12345678" se consideran iguales.
 */
function documentoDuplicado(string $tipo, string $numero, int $excluirId = 0): bool {
    $clave = strtoupper($tipo) . strtoupper($numero);
    $stmt  = getDB()->prepare("SELECT id, cedula_rif FROM clientes WHERE cedula_rif LIKE ? AND id <> ?");
    $stmt->execute(['%' . $numero . '%', $excluirId]);
    foreach ($stmt->fetchAll() as $row) {
        [$t, $n] = separarDocumento($row['cedula_rif']);
        if ($t . $n === $clave) return true;
    }
    return false;
}

/** Deja solo dígitos y un "+" inicial. */
function normalizarTelefono(string $tel): string {
    $tel = trim($tel);
    // Se aceptan espacios, guiones, puntos y paréntesis al escribir: (0414) 123-4567
    $mas = str_starts_with($tel, '+');
    $dig = preg_replace('/\D/', '', $tel);
    return ($mas ? '+' : '') . $dig;
}

/**
 * Teléfono venezolano (11 dígitos: 04XX / 02XX) o internacional (+ y 8 a 15 dígitos).
 */
function validarTelefono(string $tel): ?string {
    if ($tel === '') return 'El teléfono es obligatorio.';
    if (str_starts_with($tel, '+')) {
        $d = substr($tel, 1);
        if (!ctype_digit($d) || strlen($d) < 8 || strlen($d) > 15)
            return 'El teléfono internacional debe tener entre 8 y 15 dígitos después del "+".';
        return null;
    }
    if (!ctype_digit($tel)) return 'El teléfono solo puede contener números.';
    if (!preg_match('/^0(2\d{2}|4(12|14|16|22|24|26))\d{7}$/', $tel))
        return 'Teléfono inválido. Use 11 dígitos, ej: 04141234567 o 02351234567. Para extranjeros inicie con +.';
    return null;
}

/** Número para enlaces wa.me (formato internacional sin "+"). */
function telefonoWhatsApp(string $tel): string {
    $d = preg_replace('/\D/', '', $tel);
    if (str_starts_with($tel, '+')) return $d;
    if (str_starts_with($d, '0'))   return '58' . substr($d, 1);
    return $d;
}

/** Nombre de persona o empresa: letras, espacios y . ' - & */
function validarNombre(string $nombre, int $min = 3, int $max = 120, string $campo = 'El nombre'): ?string {
    $len = mb_strlen($nombre);
    if ($len === 0) return "{$campo} es obligatorio.";
    if ($len < $min) return "{$campo} debe tener al menos {$min} caracteres.";
    if ($len > $max) return "{$campo} no puede superar {$max} caracteres.";
    if (!preg_match("/^[\p{L}][\p{L}\s\.'\-&,]*$/u", $nombre))
        return "{$campo} solo puede contener letras, espacios y los signos . ' - &";
    return null;
}

/** Los RIF J (jurídico) y G (gobierno) son empresas: sin apellidos. */
function esDocumentoEmpresa(string $tipo): bool {
    return in_array($tipo, ['J', 'G'], true);
}

function validarEmail(string $email): ?string {
    if ($email === '') return null;
    if (mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL))
        return 'El correo electrónico no es válido.';
    return null;
}

/** Colapsa espacios repetidos. */
function limpiarTexto(string $txt): string {
    // Con UTF-8 inválido preg_replace devuelve null: se conserva el texto y lo rechaza la validación
    return trim(preg_replace('/\s+/u', ' ', $txt) ?? $txt);
}

/**
 * Valida y normaliza los datos de un cliente recibidos por POST.
 * Devuelve ['ok'=>true,'data'=>[...]] o ['ok'=>false,'error'=>'...','campo'=>'...'].
 */
function validarDatosCliente(array $in, int $excluirId = 0): array {
    // Cualquier campo que no llegue como texto se trata como vacío
    $txt = fn(string $k): string => isset($in[$k]) && is_scalar($in[$k]) ? (string) $in[$k] : '';

    $nombres   = limpiarTexto($txt('nombres'));
    $apellidos = limpiarTexto($txt('apellidos'));
    $tipo      = strtoupper(trim($txt('doc_tipo')));
    $numero    = strtoupper(preg_replace('/[\s\.\-]/', '', $txt('doc_numero')));

    // Compatibilidad: si solo llega cedula_rif, se separa
    if ($tipo === '' && $txt('cedula_rif') !== '') {
        [$tipo, $numero] = separarDocumento($txt('cedula_rif'));
    }

    $empresa = esDocumentoEmpresa($tipo);

    // Compatibilidad: si solo llega `nombre` completo, se separa
    if ($nombres === '' && $txt('nombre') !== '') {
        $partes    = $empresa ? [limpiarTexto($txt('nombre'))] : explode(' ', limpiarTexto($txt('nombre')), 2);
        $nombres   = $partes[0];
        $apellidos = $partes[1] ?? '';
    }
    if ($empresa) $apellidos = '';

    $tel   = normalizarTelefono($txt('telefono'));
    $email = strtolower(trim($txt('email')));
    $dir   = limpiarTexto($txt('direccion'));

    if ($e = validarNombre($nombres, 2, $empresa ? 120 : 60, $empresa ? 'La razón social' : 'El nombre'))
        return ['ok' => false, 'error' => $e, 'campo' => 'nombres'];
    if (!$empresa && ($e = validarNombre($apellidos, 2, 60, 'El apellido')))
        return ['ok' => false, 'error' => $e, 'campo' => 'apellidos'];
    if ($e = validarDocumento($tipo, $numero))  return ['ok' => false, 'error' => $e, 'campo' => 'documento'];
    if (documentoDuplicado($tipo, $numero, $excluirId))
        return ['ok' => false, 'error' => 'Ya existe un cliente registrado con ese documento.', 'campo' => 'documento'];
    if ($e = validarTelefono($tel))             return ['ok' => false, 'error' => $e, 'campo' => 'telefono'];
    if ($e = validarEmail($email))              return ['ok' => false, 'error' => $e, 'campo' => 'email'];
    if (mb_strlen($dir) > 255)                  return ['ok' => false, 'error' => 'La dirección no puede superar 255 caracteres.', 'campo' => 'direccion'];
    if ($dir !== '' && mb_strlen($dir) < 5)     return ['ok' => false, 'error' => 'La dirección es muy corta (mínimo 5 caracteres) o déjela vacía.', 'campo' => 'direccion'];
    if (!mb_check_encoding($nombres . $apellidos . $dir . $email, 'UTF-8'))
        return ['ok' => false, 'error' => 'Los datos contienen caracteres inválidos.', 'campo' => 'nombres'];

    return ['ok' => true, 'data' => [
        ':nombres'    => $nombres,
        ':apellidos'  => $apellidos,
        ':nombre'     => trim("{$nombres} {$apellidos}"),
        ':cedula_rif' => formatearDocumento($tipo, $numero),
        ':telefono'   => $tel,
        ':direccion'  => $dir,
        ':email'      => $email,
    ]];
}

/** Número decimal dentro de un rango (acepta coma decimal). */
function numeroEnRango($valor, float $min, float $max): ?float {
    $v = str_replace(',', '.', trim((string) $valor));
    if ($v === '' || !is_numeric($v)) return null;
    $f = (float) $v;
    return ($f >= $min && $f <= $max) ? $f : null;
}

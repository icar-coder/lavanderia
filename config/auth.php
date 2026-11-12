<?php
// ============================================================
//  config/auth.php — Sistema de autenticación y autorización
//  Lavandería Superior
// ============================================================

// ── CLAVE MAESTRA ──────────────────────────────────────────
// Solo el administrador la conoce. Se usa para:
//   - Crear nuevos usuarios
//   - Resetear contraseñas
define('CLAVE_MAESTRA', 'LavSup@2024#Master');

// ── PERMISOS POR ROL ────────────────────────────────────────
// Define qué páginas puede ver cada rol (SIEMPRE EN MINÚSCULAS Y SIN .PHP)
define('PERMISOS', [
    'admin' => [
        'index', 'ordenes', 'kanban', 'clientes',
        'inventario', 'caja', 'reportes', 'configuracion',
        'ticket', 'usuarios', 'ayuda',
    ],
    // Caja, inventario, reportes, usuarios y configuración exigen requireAdmin()
    'vendedor' => [
        'index', 'ordenes', 'kanban', 'clientes',
        'ticket', 'ayuda',
    ],
]);

// ── NOMBRES LEGIBLES DE ROL ─────────────────────────────────
define('ROL_LABELS', [
    'admin'    => 'Administrador',
    'vendedor' => 'Vendedor',
]);

// ============================================================
// FUNCIONES DE SESIÓN Y AUTORIZACIÓN (CORREGIDAS Y BLINDADAS)
// ============================================================

/**
 * Verifica si hay una sesión activa válida.
 */
function estaLogueado(): bool {
    return !empty($_SESSION['usuario_id'])
        && !empty($_SESSION['usuario_rol']);
}

/**
 * Devuelve el rol del usuario en sesión o null.
 */
function rolActual(): ?string {
    return $_SESSION['usuario_rol'] ?? null;
}

/**
 * Verifica si el usuario en sesión puede acceder a una página.
 * ¡Inmune a extensiones .php, mayúsculas o espacios!
 */
function puedeAcceder(string $pagina): bool {
    $rol = strtolower(trim(rolActual() ?? '')); 
    if (!$rol) return false;
    
    // Si es administrador, tiene acceso total garantizado siempre
    if ($rol === 'admin') return true;
    
    // Limpiamos el nombre de la página por si viene como "index.php", "Index" o con espacios
    $paginaClean = strtolower(trim(basename($pagina, '.php')));
    
    $permisos = PERMISOS[$rol] ?? [];
    return in_array($paginaClean, $permisos, true);
}

/**
 * Nombres legibles de cada módulo para el overlay de acceso denegado.
 */
function nombreModulo(string $pagina): string {
    $paginaClean = strtolower(trim(basename($pagina, '.php')));
    
    $nombres = [
        'inventario'    => 'Inventario de Insumos',
        'caja'          => 'Libro de Caja',
        'reportes'      => 'Reportes y Estadísticas',
        'configuracion' => 'Configuración del Sistema',
        'usuarios'      => 'Gestión de Usuarios',
        'index'         => 'Dashboard',
        'ordenes'       => 'Órdenes de Servicio',
        'kanban'        => 'Tablero Kanban',
        'clientes'      => 'Gestión de Clientes',
        'ticket'        => 'Tickets de Impresión',
        'ayuda'         => 'Centro de Ayuda',
    ];
    return $nombres[$paginaClean] ?? ucfirst($paginaClean);
}

/**
 * Muestra el overlay de acceso denegado en la página actual sin redirigir.
 */
function mostrarAccesoDenegado(string $pagina = ''): void {
    $paginaClean = strtolower(trim(basename($pagina, '.php')));
    $GLOBALS['_modulo_denegado'] = nombreModulo($paginaClean);

    echo '<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Acceso Restringido — Lavandería Superior</title>
  <script src="' . ROOT_URL . 'assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="' . ROOT_URL . 'assets/css/main.css">
</head>
<body style="margin:0;padding:0;min-height:100vh;background:var(--bg-base,#0b0f14);">';

    include ROOT . 'partials/acceso_denegado.php';

    echo '</body></html>';
    exit;
}

/**
 * Guard principal: Se coloca al inicio de tus archivos protegidos.
 * Si se llama vacío requireAuth(), detectará automáticamente el archivo actual.
 */
function requireAuth(string $pagina = ''): void {
    if (!estaLogueado()) {
        if (esPeticionAjax()) responderError('Su sesión expiró. Vuelva a iniciar sesión.', 401);
        $currentFile = basename($_SERVER['PHP_SELF']);
        $destino = $currentFile ? '?next=' . urlencode($currentFile) : '';
        redirigir(ROOT_URL . 'login.php' . $destino);
    }
    
    // Si no se le envía una página explícita, la detecta por sí mismo de la URL real
    if (empty($pagina)) {
        $pagina = basename($_SERVER['PHP_SELF'], '.php');
    }

    if (!puedeAcceder($pagina)) {
        if (esPeticionAjax()) responderError('No tiene permiso para realizar esta acción.', 403);
        mostrarAccesoDenegado($pagina);
    }
}

/**
 * Guard estricto solo para administradores.
 */
function requireAdmin(): void {
    if (!estaLogueado()) {
        if (esPeticionAjax()) responderError('Su sesión expiró. Vuelva a iniciar sesión.', 401);
        redirigir(ROOT_URL . 'login.php');
    }
    $rol = strtolower(trim(rolActual() ?? ''));
    if ($rol !== 'admin') {
        if (esPeticionAjax()) responderError('Solo un administrador puede realizar esta acción.', 403);
        $pagina = basename($_SERVER['PHP_SELF'], '.php');
        mostrarAccesoDenegado($pagina);
    }
}

// ============================================================
// FUNCIONES DE USUARIO (DB)
// ============================================================

/** Reglas de contraseña. bcrypt solo usa los primeros 72 bytes. */
function validarPassword(string $pass, string $campo = 'La contraseña'): ?string {
    if (strlen($pass) < 6)  return "{$campo} debe tener al menos 6 caracteres.";
    if (strlen($pass) > 72) return "{$campo} no puede superar 72 caracteres.";
    if (trim($pass) === '') return "{$campo} no puede ser solo espacios.";
    return null;
}

// ── Límite de intentos de inicio de sesión ─────────────────
// 5 fallos seguidos desde la misma IP bloquean el acceso 5 minutos.
define('LOGIN_MAX_INTENTOS', 5);
define('LOGIN_BLOQUEO_SEG', 300);

function claveIntentosLogin(): string {
    return 'login_fallos_' . md5($_SERVER['REMOTE_ADDR'] ?? 'local');
}

/** Segundos de bloqueo restantes (0 = puede intentar). */
function loginBloqueado(): int {
    $st = getDB()->prepare("SELECT valor FROM sistema_config WHERE clave = ?");
    $st->execute([claveIntentosLogin()]);
    $d = json_decode((string) $st->fetchColumn(), true);
    if (!is_array($d) || ($d['n'] ?? 0) < LOGIN_MAX_INTENTOS) return 0;
    return max(0, ($d['t'] ?? 0) + LOGIN_BLOQUEO_SEG - time());
}

function registrarFalloLogin(): void {
    $st = getDB()->prepare("SELECT valor FROM sistema_config WHERE clave = ?");
    $st->execute([claveIntentosLogin()]);
    $d = json_decode((string) $st->fetchColumn(), true);
    // Pasado el bloqueo, el conteo empieza de nuevo
    if (!is_array($d) || time() - ($d['t'] ?? 0) > LOGIN_BLOQUEO_SEG) $d = ['n' => 0];
    $d = ['n' => ($d['n'] ?? 0) + 1, 't' => time()];
    getDB()->prepare("INSERT INTO sistema_config (clave, valor) VALUES (?, ?)
                      ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
           ->execute([claveIntentosLogin(), json_encode($d)]);
}

function limpiarFallosLogin(): void {
    getDB()->prepare("DELETE FROM sistema_config WHERE clave = ?")->execute([claveIntentosLogin()]);
}

/**
 * Intenta iniciar sesión. Devuelve el array del usuario o null.
 */
function intentarLogin(string $username, string $password): ?array {
    $stmt = getDB()->prepare(
        "SELECT * FROM usuarios WHERE username = ? AND activo = 1 LIMIT 1"
    );
    $stmt->execute([trim($username)]);
    $usuario = $stmt->fetch();

    if (!$usuario) return null;
    if (!password_verify($password, $usuario['password'])) return null;

    return $usuario;
}

/**
 * Inicia la sesión PHP con los datos del usuario.
 */
function iniciarSesion(array $usuario): void {
    session_regenerate_id(true);
    unset($_SESSION['_csrf']); // token nuevo para la sesión nueva
    $_SESSION['usuario_id']     = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_username']= $usuario['username'];
    $_SESSION['usuario_rol']    = $usuario['rol'];
    $_SESSION['usuario_cargo']  = ROL_LABELS[$usuario['rol']] ?? $usuario['rol'];
    $_SESSION['login_time']     = time();

    getDB()->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")
           ->execute([$usuario['id']]);
}

/**
 * Cierra la sesión actual.
 */
function cerrarSesion(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Crea un nuevo usuario (requiere clave maestra).
 */
function crearUsuario(string $nombre, string $username, string $password,
                      string $rol, string $claveMaestra): array {
    if (!hash_equals(CLAVE_MAESTRA, $claveMaestra)) {
        return ['ok' => false, 'error' => 'Clave maestra incorrecta.'];
    }
    $nombre   = limpiarTexto($nombre);
    $username = strtolower(trim($username));
    if ($e = validarNombre($nombre, 3, 80)) {
        return ['ok' => false, 'error' => $e];
    }
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) {
        return ['ok' => false, 'error' => 'El usuario debe tener de 3 a 40 caracteres: letras minúsculas, números, punto, guion o guion bajo.'];
    }
    if ($e = validarPassword($password)) {
        return ['ok' => false, 'error' => $e];
    }
    if (!array_key_exists($rol, PERMISOS)) {
        return ['ok' => false, 'error' => 'Rol inválido.'];
    }
    $chk = getDB()->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
    $chk->execute([trim($username)]);
    if ($chk->fetch()) {
        return ['ok' => false, 'error' => 'El nombre de usuario ya está en uso.'];
    }
    try {
        getDB()->prepare(
            "INSERT INTO usuarios (nombre, username, password, rol)
             VALUES (:n, :u, :p, :r)"
        )->execute([
            ':n' => trim($nombre),
            ':u' => trim($username),
            ':p' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            ':r' => $rol,
        ]);
        return ['ok' => true, 'id' => (int) getDB()->lastInsertId()];
    } catch (Exception $e) {
        error_log('crearUsuario: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo crear el usuario.'];
    }
}

/**
 * Resetea la contraseña de un usuario (requiere clave maestra).
 */
function resetearPassword(int $userId, string $nuevaPass,
                          string $claveMaestra): array {
    if (!hash_equals(CLAVE_MAESTRA, $claveMaestra)) {
        return ['ok' => false, 'error' => 'Clave maestra incorrecta.'];
    }
    if ($e = validarPassword($nuevaPass)) {
        return ['ok' => false, 'error' => $e];
    }
    $chk = getDB()->prepare("SELECT id FROM usuarios WHERE id = ?");
    $chk->execute([$userId]);
    if (!$chk->fetch()) {
        return ['ok' => false, 'error' => 'El usuario no existe.'];
    }
    $hash = password_hash($nuevaPass, PASSWORD_BCRYPT, ['cost' => 12]);
    getDB()->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
           ->execute([$hash, $userId]);
    return ['ok' => true];
}

/**
 * Cambia contraseña del usuario en sesión (requiere contraseña actual).
 */
function cambiarPasswordPropio(int $userId, string $passActual,
                                string $nuevaPass): array {
    $stmt = getDB()->prepare("SELECT password FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($passActual, $row['password'])) {
        return ['ok' => false, 'error' => 'Contraseña actual incorrecta.'];
    }
    if ($e = validarPassword($nuevaPass, 'La nueva contraseña')) {
        return ['ok' => false, 'error' => $e];
    }
    if (hash_equals($passActual, $nuevaPass)) {
        return ['ok' => false, 'error' => 'La nueva contraseña debe ser distinta de la actual.'];
    }
    $hash = password_hash($nuevaPass, PASSWORD_BCRYPT, ['cost' => 12]);
    getDB()->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
           ->execute([$hash, $userId]);
    return ['ok' => true];
}

/**
 * Lista todos los usuarios (solo admin).
 */
function listarUsuarios(): array {
    return getDB()
        ->query("SELECT id, nombre, username, rol, activo, ultimo_login, created_at
                 FROM usuarios ORDER BY nombre")
        ->fetchAll();
}

/**
 * Activa / desactiva un usuario.
 */
function toggleUsuario(int $id): array {
    if ($id === (int)($_SESSION['usuario_id'] ?? 0)) {
        return ['ok' => false, 'error' => 'No puede desactivar su propio usuario.'];
    }
    $upd = getDB()->prepare("UPDATE usuarios SET activo = NOT activo WHERE id = ?");
    $upd->execute([$id]);
    return $upd->rowCount() ? ['ok' => true] : ['ok' => false, 'error' => 'El usuario no existe.'];
}
<?php
// ============================================================
//  config/init.php — Arranque central del sistema
//  Lavandería Superior
// ============================================================

// Raíz absoluta del proyecto
define('ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Los errores se registran en el log de PHP y no se muestran: un aviso
// impreso rompería las respuestas JSON y expondría rutas del servidor.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/** ¿La petición viene de fetch() y espera JSON? */
function esPeticionAjax(): bool {
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/** Responde un error en JSON (AJAX) o en HTML y termina. */
function responderError(string $mensaje, int $codigo = 400): void {
    if (!headers_sent()) http_response_code($codigo);
    if (esPeticionAjax()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'ok' => false, 'error' => $mensaje]);
    } else {
        echo '<p style="font-family:sans-serif;padding:24px;color:#b91c1c;">' .
             htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    exit;
}

// Último recurso: cualquier error no previsto (p. ej. un campo enviado como
// arreglo donde se espera texto) devuelve un mensaje limpio en vez de una
// página en blanco o un JSON roto.
set_exception_handler(function (Throwable $e) {
    error_log('Error no controlado: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    responderError($e instanceof TypeError || $e instanceof ValueError
        ? 'Los datos enviados no tienen el formato esperado.'
        : 'Ocurrió un error inesperado. Intente de nuevo.', 500);
});

// URL base relativa (para redirects)
if (!defined('ROOT_URL')) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base   = rtrim(str_replace(basename($script), '', $script), '/');
    define('ROOT_URL', $base . '/');
}

// Cargar dependencias en orden correcto
require_once ROOT . 'config/database.php';
require_once ROOT . 'config/tasa.php';
require_once ROOT . 'config/auth.php';
require_once ROOT . 'config/validacion.php';
require_once ROOT . 'config/whatsapp.php';

// Arrancar sesión si no está iniciada (cookie inaccesible desde JS)
if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);
}

// ── PROTECCIÓN CSRF ────────────────────────────────────────
// Toda petición POST debe traer el token de la sesión, en la cabecera
// X-CSRF-Token (fetch, lo añade validaciones.js) o en el campo _csrf (formularios).
function csrfToken(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function campoCsrf(): string {
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tokenRecibido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!is_string($tokenRecibido) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $tokenRecibido)) {
        // Si la sesión expiró, el token también: se pide iniciar sesión de nuevo
        $msgCsrf = empty($_SESSION['usuario_id'])
            ? 'Su sesión expiró. Vuelva a iniciar sesión.'
            : 'La página está desactualizada. Recárguela e intente de nuevo.';
        if (!esPeticionAjax()) {
            // Formulario clásico (login): se vuelve a mostrar con el aviso
            $_SESSION['flash_error'] = $msgCsrf;
            redirigir($_SERVER['REQUEST_URI'] ?? 'login.php');
        }
        responderError($msgCsrf, empty($_SESSION['usuario_id']) ? 401 : 403);
    }
}

// Actualizaciones de esquema pendientes (tablas nuevas, catálogos)
require_once ROOT . 'config/migraciones.php';

// Zona horaria Venezuela
date_default_timezone_set('America/Caracas');

// ── HELPERS ────────────────────────────────────────────────

function partial(string $nombre): void {
    include ROOT . 'partials/' . $nombre . '.php';
}

function redirigir(string $url): void {
    header('Location: ' . $url);
    exit;
}

function h(?string $texto): string {
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Lee un campo de texto de $_POST/$_GET. Si llega como arreglo u otro
 * tipo inesperado devuelve '' (la validación lo rechaza como vacío).
 */
function entrada(array $origen, string $clave, string $defecto = ''): string {
    $v = $origen[$clave] ?? $defecto;
    return is_scalar($v) ? (string) $v : '';
}

/** Lee un campo que debe ser una lista (servicios[], cantidades[]...). */
function entradaLista(array $origen, string $clave): array {
    $v = $origen[$clave] ?? [];
    return is_array($v) ? $v : [];
}

/** Escapa % y _ para usar texto del usuario dentro de LIKE. */
function escaparLike(string $texto): string {
    return addcslashes($texto, '%_\\');
}

/** Fecha YYYY-MM-DD real (rechaza 2026-02-31) o null. */
function fechaValida(string $fecha): ?string {
    $d = DateTime::createFromFormat('!Y-m-d', $fecha);
    return ($d && $d->format('Y-m-d') === $fecha) ? $fecha : null;
}

/** Atributo de tooltip accesible: <button <?= tip('Texto') ?>> */
function tip(string $texto, string $pos = ''): string {
    return 'data-tip="' . h($texto) . '"' . ($pos ? ' data-tip-pos="' . h($pos) . '"' : '');
}

/** Datos del proyecto (créditos institucionales, sin autores personales). */
define('PROYECTO_INSTITUCION', 'Universidad Politécnica Territorial de los Llanos "Juana Ramírez"');
define('PROYECTO_SIGLAS', 'UPTLL');
define('PROYECTO_UBICACION', 'Valle de la Pascua, estado Guárico, Venezuela');
define('SISTEMA_VERSION', '2.1');

/** Métodos de pago aceptados (valor en BD => nombre legible). */
define('METODOS_PAGO', [
    'Efectivo_USD'  => 'Efectivo USD',
    'Efectivo_BS'   => 'Efectivo Bs.',
    'Pago_Movil'    => 'Pago Móvil',
    'Punto_Venta'   => 'Punto de venta',
    'Transferencia' => 'Transferencia',
    'Mixto'         => 'Mixto',
]);

function nombreMetodoPago(?string $metodo): string {
    return METODOS_PAGO[$metodo ?? ''] ?? str_replace('_', ' ', $metodo ?? '—');
}

/** "domingo, 11 de octubre de 2026" (strftime está obsoleta y depende del idioma del servidor). */
function fechaLarga(?int $ts = null): string {
    $ts    = $ts ?? time();
    $dias  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return $dias[(int) date('w', $ts)] . ', ' . date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

function formatUSD(float $monto): string {
    return '$' . number_format($monto, 2);
}

function formatBS(float $monto): string {
    return 'Bs. ' . number_format($monto, 2, ',', '.');
}

// ── FLASH MESSAGES ─────────────────────────────────────────

function flashError(): ?string {
    $msg = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);
    return $msg;
}

function flashOk(): ?string {
    $msg = $_SESSION['flash_ok'] ?? null;
    unset($_SESSION['flash_ok']);
    return $msg;
}
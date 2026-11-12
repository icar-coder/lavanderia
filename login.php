<?php
require_once __DIR__ . '/config/init.php';

if (estaLogueado()) {
    redirigir(ROOT_URL . 'index.php');
}

$error = null;
// Solo se permite volver a una página del propio sistema (sin rutas ni dominios)
$next  = entrada($_GET, 'next', 'index.php');
if (!preg_match('/^[a-z_]+\.php$/', $next) || in_array($next, ['login.php', 'logout.php', 'setup.php'], true)) {
    $next = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim(entrada($_POST, 'username')));
    $password = entrada($_POST, 'password');

    if ($espera = loginBloqueado()) {
        $error = 'Demasiados intentos fallidos. Espere ' . ceil($espera / 60) . ' minuto(s) e intente de nuevo.';
    } elseif ($username === '' || $password === '') {
        $error = 'Completa todos los campos.';
    } elseif (mb_strlen($username) > 40 || strlen($password) > 72) {
        $error = 'Usuario o contraseña incorrectos.';
    } else {
        $usuario = intentarLogin($username, $password);
        if ($usuario) {
            limpiarFallosLogin();
            iniciarSesion($usuario);
            redirigir(ROOT_URL . $next);
        } else {
            registrarFalloLogin();
            $error = 'Usuario o contraseña incorrectos.';
            sleep(1);
        }
    }
}

$flashErr = flashError();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión — Lavandería Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>

    /* ── RESET BODY ─────────────────────────────────────── */
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      position: relative;
      overflow: hidden;
    }

    /* ── FONDO CON GRADIENTE Y ORBES ───────────────────── */
    .login-bg {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
    }
    .login-bg__orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      opacity: .12;
    }
    .login-bg__orb--1 {
      width: 500px; height: 500px;
      background: var(--accent);
      top: -120px; left: -120px;
    }
    .login-bg__orb--2 {
      width: 400px; height: 400px;
      background: var(--teal, #0f766e);
      bottom: -100px; right: -80px;
    }
    .login-bg__orb--3 {
      width: 250px; height: 250px;
      background: var(--accent-light);
      top: 60%; left: 60%;
      opacity: .06;
    }

    /* Cuadrícula decorativa de fondo */
    .login-bg__grid {
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(var(--border) 1px, transparent 1px),
        linear-gradient(90deg, var(--border) 1px, transparent 1px);
      background-size: 48px 48px;
      opacity: .35;
      mask-image: radial-gradient(ellipse 70% 60% at 50% 50%, black 30%, transparent 100%);
    }

    /* ── CONTENEDOR PRINCIPAL ───────────────────────────── */
    .login-wrap {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 28px;
    }

    /* ── MARCA ──────────────────────────────────────────── */
    .login-brand {
      text-align: center;
    }
    .login-brand__icon {
      width: 68px; height: 68px;
      background: var(--accent-bg);
      border: 1.5px solid var(--accent-border);
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--accent-light);
      margin-bottom: 14px;
      box-shadow: 0 0 0 6px rgba(37,99,235,.06), 0 8px 24px rgba(0,0,0,.25);
      position: relative;
    }
    /* Punto de estado activo */
    .login-brand__icon::after {
      content: '';
      position: absolute;
      top: 6px; right: 6px;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--green);
      border: 2px solid var(--bg-base, #0b0f14);
      box-shadow: 0 0 0 3px rgba(52,211,153,.2);
    }
    .login-brand__name {
      font-family: var(--font-display);
      font-size: 24px;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -.025em;
      line-height: 1;
    }
    .login-brand__sub {
      font-size: 12.5px;
      color: var(--text-hint);
      margin-top: 5px;
      letter-spacing: .01em;
    }

    /* ── CARD DEL FORMULARIO ────────────────────────────── */
    .login-card {
      width: 100%;
      background: var(--bg-surface);
      border: 1px solid var(--border-md);
      border-radius: var(--radius-xl, 16px);
      padding: 34px 32px 28px;
      box-shadow:
        0 0 0 1px rgba(255,255,255,.04) inset,
        0 24px 60px rgba(0,0,0,.35),
        0 2px 8px rgba(0,0,0,.2);
    }

    .login-card__title {
      font-family: var(--font-display);
      font-size: 20px;
      font-weight: 700;
      color: var(--text);
      letter-spacing: -.02em;
      margin-bottom: 4px;
    }
    .login-card__sub {
      font-size: 13px;
      color: var(--text-sub);
      margin-bottom: 26px;
    }

    /* ── ALERTA DE ERROR ────────────────────────────────── */
    .login-error {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      background: var(--red-bg);
      border: 1px solid var(--red-border);
      color: var(--red);
      border-radius: var(--radius-md);
      padding: 12px 14px;
      font-size: 13px;
      margin-bottom: 20px;
      animation: shake .45s ease;
    }
    .login-error svg { flex-shrink: 0; margin-top: 1px; }

    /* ── GRUPOS DE CAMPO ────────────────────────────────── */
    .lf-group {
      margin-bottom: 18px;
    }
    .lf-label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      color: var(--text-sub);
      text-transform: uppercase;
      letter-spacing: .09em;
      margin-bottom: 7px;
    }

    /* ── INPUT CON ÍCONO ────────────────────────────────── */
    .lf-input-wrap {
      position: relative;
    }
    .lf-input-wrap__icon {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-hint);
      pointer-events: none;
      transition: color .15s;
    }
    .lf-input-wrap:focus-within .lf-input-wrap__icon {
      color: var(--accent-light);
    }

    .lf-input {
      width: 100%;
      padding: 11px 14px 11px 40px;
      background: var(--bg-surface2);
      border: 1.5px solid var(--border-md);
      border-radius: var(--radius-md);
      color: var(--text);
      font-size: 14px;
      font-family: var(--font-body);
      outline: none;
      transition: border-color .15s, box-shadow .15s, background .15s;
    }
    /* Input de contraseña: padding derecho para el ojo */
    .lf-input.has-toggle {
      padding-right: 44px;
    }
    .lf-input:focus {
      border-color: var(--accent);
      background: var(--bg-surface);
      box-shadow: 0 0 0 3px rgba(37,99,235,.12);
    }
    .lf-input::placeholder { color: var(--text-hint); }
    /* Estado de error */
    .lf-input.error {
      border-color: var(--red);
      box-shadow: 0 0 0 3px rgba(248,113,113,.1);
    }

    /* ── BOTÓN VER CONTRASEÑA ───────────────────────────── */
    .toggle-pass {
      position: absolute;
      right: 11px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-hint);
      padding: 5px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: var(--radius-sm);
      transition: color .15s, background .15s;
      z-index: 2;
    }
    .toggle-pass:hover {
      color: var(--text-sub);
      background: var(--bg-surface3);
    }
    .toggle-pass:focus {
      outline: none;
      color: var(--accent-light);
    }
    /* Ícono de ojo abierto vs tachado */
    .toggle-pass .icon-eye-open  { display: block; }
    .toggle-pass .icon-eye-close { display: none;  }
    .toggle-pass.active .icon-eye-open  { display: none;  }
    .toggle-pass.active .icon-eye-close { display: block; }

    /* ── BOTÓN SUBMIT ───────────────────────────────────── */
    .login-submit {
      width: 100%;
      padding: 12px;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: var(--radius-md);
      font-size: 14.5px;
      font-weight: 700;
      font-family: var(--font-body);
      cursor: pointer;
      transition: background .15s, transform .1s, box-shadow .15s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 8px;
      letter-spacing: .01em;
      box-shadow: 0 4px 16px rgba(37,99,235,.35);
    }
    .login-submit:hover:not(:disabled) {
      background: var(--accent-hover);
      box-shadow: 0 6px 24px rgba(37,99,235,.45);
      transform: translateY(-1px);
    }
    .login-submit:active:not(:disabled) {
      transform: translateY(0) scale(.98);
      box-shadow: 0 2px 8px rgba(37,99,235,.3);
    }
    .login-submit:disabled {
      opacity: .7;
      cursor: not-allowed;
      transform: none;
    }

    /* Spinner dentro del botón */
    .btn-spinner {
      width: 15px; height: 15px;
      border: 2px solid rgba(255,255,255,.35);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .65s linear infinite;
      flex-shrink: 0;
    }

    /* ── FOOTER ─────────────────────────────────────────── */
    .login-footer {
      text-align: center;
      font-size: 11.5px;
      color: var(--text-hint);
    }
    .login-footer span {
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .login-footer span::before,
    .login-footer span::after {
      content: '';
      display: inline-block;
      width: 4px; height: 4px;
      border-radius: 50%;
      background: var(--border-strong, rgba(255,255,255,.15));
    }

    /* ── ANIMACIONES ────────────────────────────────────── */
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      18%       { transform: translateX(-7px); }
      36%       { transform: translateX(7px); }
      54%       { transform: translateX(-5px); }
      72%       { transform: translateX(5px); }
      90%       { transform: translateX(-2px); }
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(14px) scale(.98); }
      to   { opacity: 1; transform: translateY(0)     scale(1);  }
    }
    .login-card { animation: cardIn .35s ease both; }

  </style>
</head>
<body>

  <!-- Fondo decorativo -->
  <div class="login-bg">
    <div class="login-bg__grid"></div>
    <div class="login-bg__orb login-bg__orb--1"></div>
    <div class="login-bg__orb login-bg__orb--2"></div>
    <div class="login-bg__orb login-bg__orb--3"></div>
  </div>

  <!-- Contenedor -->
  <div class="login-wrap">

    <!-- Marca -->
    <div class="login-brand">
      <div class="login-brand__icon">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M8 12a4 4 0 0 1 8 0"/>
          <path d="M6 20l2-2M18 20l-2-2"/>
          <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
        </svg>
      </div>
      <p class="login-brand__name">Lavandería Superior</p>
      <p class="login-brand__sub">Sistema de Gestión &nbsp;·&nbsp; Valle de la Pascua</p>
    </div>

    <!-- Card del formulario -->
    <div class="login-card" id="login-card">
      <p class="login-card__title">Bienvenido de vuelta</p>
      <p class="login-card__sub">Ingresa tus credenciales para continuar</p>

      <!-- Error -->
      <?php if ($error || $flashErr): ?>
      <div class="login-error">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8"  x2="12"   y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <?= h($error ?? $flashErr) ?>
      </div>
      <?php endif; ?>

      <!-- Formulario -->
      <form method="post" id="login-form" onsubmit="return onSubmit()">
        <input type="hidden" name="next" value="<?= h($next) ?>">
        <?= campoCsrf() ?>

        <!-- Usuario -->
        <div class="lf-group">
          <label class="lf-label" for="username">Usuario</label>
          <div class="lf-input-wrap">
            <svg class="lf-input-wrap__icon" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            <input class="lf-input <?= ($error||$flashErr)?'error':'' ?>"
                   type="text" id="username" name="username"
                   value="<?= h(entrada($_POST, 'username')) ?>"
                   placeholder="tu_usuario"
                   autocomplete="username"
                   autofocus required>
          </div>
        </div>

        <!-- Contraseña -->
        <div class="lf-group">
          <label class="lf-label" for="password">Contraseña</label>
          <div class="lf-input-wrap">
            <svg class="lf-input-wrap__icon" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input class="lf-input has-toggle <?= ($error||$flashErr)?'error':'' ?>"
                   type="password" id="password" name="password"
                   placeholder="••••••••"
                   autocomplete="current-password"
                   required>

            <!-- Botón ver/ocultar contraseña -->
            <button type="button" class="toggle-pass" id="toggle-pass-btn"
                    onclick="togglePass()" title="Mostrar u ocultar contraseña"
                    aria-label="Mostrar contraseña">

              <!-- Ojo abierto (estado por defecto: contraseña oculta) -->
              <svg class="icon-eye-open" width="17" height="17" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>

              <!-- Ojo tachado (estado: contraseña visible) -->
              <svg class="icon-eye-close" width="17" height="17" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Submit -->
        <button type="submit" class="login-submit" id="submit-btn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2.5">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            <polyline points="10 17 15 12 10 7"/>
            <line x1="15" y1="12" x2="3" y2="12"/>
          </svg>
          Ingresar al sistema
        </button>
      </form>
    </div>

    <!-- Footer -->
    <div class="login-footer">
      Lavandería Superior &copy; <?= date('Y') ?>
      <span>Sistema LS v1.0</span>
    </div>

  </div><!-- /login-wrap -->

<script>
/* ── VER / OCULTAR CONTRASEÑA ─────────────────────────── */
function togglePass() {
  const inp = document.getElementById('password');
  const btn = document.getElementById('toggle-pass-btn');
  const visible = inp.type === 'text';

  inp.type = visible ? 'password' : 'text';
  btn.classList.toggle('active', !visible);
  btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');

  // Mantener el foco en el input después de hacer clic
  inp.focus();
}

/* ── SUBMIT CON SPINNER ───────────────────────────────── */
function onSubmit() {
  const btn  = document.getElementById('submit-btn');
  const user = document.getElementById('username').value.trim();
  const pass = document.getElementById('password').value;

  if (!user || !pass) return false;

  btn.disabled = true;
  btn.innerHTML =
    '<span class="btn-spinner"></span> Verificando...';
  return true;
}

/* ── SOPORTE ENTER EN CUALQUIER CAMPO ─────────────────── */
document.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    const form = document.getElementById('login-form');
    if (document.activeElement !== document.getElementById('submit-btn')) {
      // Si el usuario presiona Enter desde el campo usuario, pasa al campo contraseña
      if (document.activeElement.id === 'username') {
        e.preventDefault();
        document.getElementById('password').focus();
      }
    }
  }
});
</script>
</body>
</html>

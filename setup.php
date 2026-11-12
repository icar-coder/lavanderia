<?php
/**
 * setup.php — Script de configuración inicial
 *
 * Ejecuta este archivo UNA SOLA VEZ después de importar database/schema.sql
 * para crear el usuario administrador con contraseña segura.
 *
 * Acceso: http://localhost/lavanderia/setup.php
 *
 * ⚠️  BORRA ESTE ARCHIVO después de usarlo por seguridad.
 */
// init.php aporta la sesión, la protección CSRF y las mismas validaciones del sistema
require_once __DIR__ . '/config/init.php';

// Evitar ejecución si ya existe un admin (sin excepciones: otros administradores
// se crean desde usuarios.php con la clave maestra)
$existe = getDB()->query("SELECT COUNT(*) FROM usuarios WHERE rol='admin'")->fetchColumn();
if ($existe > 0) {
    die('
    <div style="font-family:sans-serif;max-width:500px;margin:60px auto;padding:30px;
         background:#fff;border-radius:12px;border:1px solid #e5e7eb;text-align:center;">
      <h2 style="color:#059669;">✓ Sistema ya configurado</h2>
      <p style="color:#6b7280;margin-top:10px;">
        Ya existe al menos un administrador en el sistema.<br>
        Ve a <a href="login.php" style="color:#2563eb;">login.php</a> para ingresar.
      </p>
      <p style="color:#f59e0b;font-size:12px;margin-top:20px;">
        ⚠️ Elimina este archivo (setup.php) del servidor por seguridad.
      </p>
    </div>');
}

$ok     = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = limpiarTexto(entrada($_POST, 'nombre'));
    $username = strtolower(trim(entrada($_POST, 'username')));
    $pass1    = entrada($_POST, 'pass1');
    $pass2    = entrada($_POST, 'pass2');

    if ($e = validarNombre($nombre, 3, 80)) $errors[] = $e;
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username))
        $errors[] = 'El usuario debe tener de 3 a 40 caracteres: letras minúsculas, números, punto, guion o guion bajo.';
    if ($e = validarPassword($pass1)) $errors[] = $e;
    if ($pass1 !== $pass2) $errors[] = 'Las contraseñas no coinciden.';

    if (empty($errors)) {
        try {
            // Verificar si el username ya existe
            $chk = getDB()->prepare("SELECT id FROM usuarios WHERE username=? LIMIT 1");
            $chk->execute([$username]);
            if ($chk->fetch()) {
                $errors[] = 'Ese nombre de usuario ya existe.';
            } else {
                $hash = password_hash($pass1, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = getDB()->prepare(
                    "INSERT INTO usuarios (nombre, username, password, rol)
                     VALUES (:n, :u, :p, 'admin')"
                );
                $stmt->execute([':n' => $nombre, ':u' => $username, ':p' => $hash]);
                $ok = true;
            }
        } catch (Exception $e) {
            error_log('setup.php: ' . $e->getMessage());
            $errors[] = 'No se pudo crear el administrador. Verifique que la base de datos esté importada.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Configuración Inicial — Lavandería Superior</title>
  <style>
    *{ box-sizing:border-box; margin:0; padding:0; }
    body{
      font-family: 'Segoe UI', sans-serif;
      background: #0b0f14;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .card{
      background: #111820;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 16px;
      padding: 36px 32px;
      width: 100%;
      max-width: 440px;
      color: #e8edf2;
    }
    .logo{ text-align:center; margin-bottom:28px; }
    .logo h1{ font-size:22px; font-weight:800; letter-spacing:-.01em; }
    .logo p { font-size:13px; color:#8a95a3; margin-top:4px; }
    .step-badge{
      display:inline-flex; align-items:center; gap:6px;
      background:rgba(37,99,235,.15); color:#60a5fa;
      border:1px solid rgba(37,99,235,.35);
      border-radius:20px; font-size:11px; font-weight:600;
      padding:3px 12px; margin-bottom:20px;
    }
    h2{ font-size:17px; font-weight:700; margin-bottom:6px; }
    .sub{ font-size:13px; color:#8a95a3; margin-bottom:22px; line-height:1.5; }
    .form-group{ margin-bottom:14px; }
    label{
      display:block; font-size:11px; font-weight:600;
      text-transform:uppercase; letter-spacing:.08em;
      color:#8a95a3; margin-bottom:5px;
    }
    input{
      width:100%; padding:9px 12px;
      background:#19222d; border:1px solid rgba(255,255,255,.12);
      border-radius:6px; color:#e8edf2; font-size:13.5px;
      outline:none; font-family:inherit;
    }
    input:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
    .btn{
      width:100%; padding:11px; background:#2563eb; color:#fff;
      border:none; border-radius:6px; font-size:14px; font-weight:600;
      cursor:pointer; font-family:inherit; margin-top:6px;
    }
    .btn:hover{ background:#1d55d4; }
    .error-box{
      background:rgba(248,113,113,.1); border:1px solid rgba(248,113,113,.3);
      border-radius:8px; padding:10px 14px; margin-bottom:16px;
      font-size:13px; color:#f87171;
    }
    .error-box ul{ margin:4px 0 0 16px; }
    .success-box{
      background:rgba(52,211,153,.1); border:1px solid rgba(52,211,153,.3);
      border-radius:8px; padding:20px; text-align:center;
    }
    .success-box h3{ color:#34d399; font-size:18px; margin-bottom:8px; }
    .success-box p { color:#8a95a3; font-size:13px; line-height:1.6; }
    .info-box{
      background:rgba(251,191,36,.08); border:1px solid rgba(251,191,36,.25);
      border-radius:8px; padding:12px 14px; margin-bottom:18px;
      font-size:12px; color:#fbbf24; line-height:1.6;
    }
    .clave-display{
      background:#19222d; border:1px solid rgba(37,99,235,.3);
      border-radius:6px; padding:8px 14px; margin:8px 0;
      font-family:monospace; font-size:15px; font-weight:700;
      color:#60a5fa; text-align:center; letter-spacing:.04em;
    }
    a{ color:#60a5fa; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>🧺 Lavandería Superior</h1>
    <p>Configuración inicial del sistema</p>
  </div>

  <?php if ($ok): ?>
  <!-- ÉXITO -->
  <div class="success-box">
    <h3>✓ ¡Sistema configurado!</h3>
    <p>
      El usuario administrador fue creado exitosamente.<br><br>
      <strong style="color:#e8edf2;">Guarda tu Clave Maestra:</strong>
    </p>
    <div class="clave-display">LavSup@2024#Master</div>
    <p style="font-size:11px;color:#f87171;margin-top:8px;">
      ⚠️ Esta clave es necesaria para crear usuarios y resetear contraseñas.
      Anótala en un lugar seguro. No se puede recuperar si la olvidas.
    </p>
    <br>
    <a href="login.php" style="display:inline-block;padding:10px 24px;background:#2563eb;color:#fff;border-radius:6px;font-weight:600;text-decoration:none;">
      Ir al Login →
    </a>
    <p style="font-size:11px;color:#f87171;margin-top:16px;">
      ⚠️ <strong>IMPORTANTE:</strong> Elimina el archivo <code>setup.php</code> del servidor ahora.
    </p>
  </div>

  <?php else: ?>
  <!-- FORMULARIO -->
  <div class="step-badge">⚙️ Paso único de configuración</div>
  <h2>Crear Administrador</h2>
  <p class="sub">
    Este formulario crea el primer usuario administrador del sistema.
    Solo se ejecuta una vez.
  </p>

  <div class="info-box">
    💡 Después de crear el admin, la <strong>Clave Maestra</strong> del sistema será:<br>
    <div class="clave-display">LavSup@2024#Master</div>
    Necesitarás esta clave para crear más usuarios y resetear contraseñas.
    Puedes cambiarla en <code>config/auth.php</code>.
  </div>

  <?php if (!empty($errors)): ?>
  <div class="error-box">
    <strong>Corrige los siguientes errores:</strong>
    <ul>
      <?php foreach($errors as $e): ?>
      <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form method="post">
    <?= campoCsrf() ?>
    <div class="form-group">
      <label>Nombre completo</label>
      <input type="text" name="nombre" value="<?= h(entrada($_POST, 'nombre')) ?>" maxlength="80"
             placeholder="Ej: Carlos Rodríguez" required autofocus>
    </div>
    <div class="form-group">
      <label>Nombre de usuario</label>
      <input type="text" name="username" value="<?= h(entrada($_POST, 'username')) ?>" maxlength="40"
             placeholder="Ej: admin" required
             oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9._-]/g,'')">
    </div>
    <div class="form-group">
      <label>Contraseña (mín. 6 caracteres)</label>
      <input type="password" name="pass1" placeholder="••••••••" minlength="6" maxlength="72" required>
    </div>
    <div class="form-group">
      <label>Confirmar contraseña</label>
      <input type="password" name="pass2" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn">Crear Administrador →</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>

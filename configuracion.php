<?php
require_once 'config/init.php';
requireAdmin();
require_once ROOT . 'config/respaldo.php';

$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$accion = entrada($_POST, 'action');

/* ── RESPALDOS ─────────────────────────────────────────────── */
if (isset($_GET['descargar_respaldo'])) {
    $ruta = rutaRespaldo(entrada($_GET, 'descargar_respaldo'));
    if (!$ruta) { http_response_code(404); exit('Respaldo no encontrado.'); }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
    header('Content-Length: ' . filesize($ruta));
    header('X-Content-Type-Options: nosniff');
    readfile($ruta);
    exit;
}

if ($esPost && $accion === 'crear_respaldo') {
    header('Content-Type: application/json');
    try {
        $nombre = generarRespaldo('manual');
        echo json_encode(['success'=>true,'nombre'=>$nombre]);
    } catch (Throwable $e) {
        error_log('Respaldo: ' . $e->getMessage());
        echo json_encode(['success'=>false,'error'=>'No se pudo generar el respaldo: ' . $e->getMessage()]);
    }
    exit;
}

if ($esPost && $accion === 'eliminar_respaldo') {
    header('Content-Type: application/json');
    $ruta = rutaRespaldo(entrada($_POST, 'nombre'));
    echo json_encode($ruta && unlink($ruta) ? ['success' => true]
                                            : ['success' => false, 'error' => 'El respaldo no existe.']);
    exit;
}

if ($esPost && $accion === 'restaurar_respaldo') {
    header('Content-Type: application/json');
    try {
        // Confirmación con la contraseña del administrador en sesión
        $u = getDB()->prepare("SELECT password FROM usuarios WHERE id = ?");
        $u->execute([(int)($_SESSION['usuario_id'] ?? 0)]);
        $hash = $u->fetchColumn();
        if (!$hash || !password_verify(entrada($_POST, 'password'), $hash))
            throw new RuntimeException('Contraseña incorrecta. La restauración fue cancelada.');

        if (!empty($_FILES['archivo']['name'])) {
            $f = $_FILES['archivo'];
            if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo recibir el archivo (código ' . $f['error'] . ').');
            if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'sql')
                throw new RuntimeException('El archivo debe tener extensión .sql');
            analizarArchivoRespaldo($f['tmp_name']);           // valida antes de guardar
            prepararDirRespaldos();
            $nombre = 'respaldo_' . date('Ymd_His') . '_subido.sql';
            if (!move_uploaded_file($f['tmp_name'], DIR_RESPALDOS . $nombre))
                throw new RuntimeException('No se pudo guardar el archivo subido.');
            $ruta = DIR_RESPALDOS . $nombre;
        } else {
            $ruta = rutaRespaldo(entrada($_POST, 'nombre'));
            if (!$ruta) throw new RuntimeException('Seleccione un respaldo válido.');
        }

        $res = restaurarRespaldo($ruta);

        // Si el usuario en sesión ya no existe en los datos restaurados, se cierra la sesión
        $chk = getDB()->prepare("SELECT id FROM usuarios WHERE id = ? AND activo = 1");
        $chk->execute([(int)($_SESSION['usuario_id'] ?? 0)]);
        $relogin = !$chk->fetch();
        if ($relogin) cerrarSesion();

        echo json_encode(['success'=>true,'sentencias'=>$res['sentencias'],'seguridad'=>$res['seguridad'],'relogin'=>$relogin]);
    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ── TIPOS DE PRENDA ───────────────────────────────────────── */
if ($esPost && $accion === 'guardar_prenda') {
    header('Content-Type: application/json');
    $id = (int) entrada($_POST, 'id');
    $nombre = limpiarTexto(entrada($_POST, 'nombre'));
    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60 || !preg_match("/^[\p{L}0-9][\p{L}0-9\s\.\/\(\)\-]*$/u", $nombre)) {
        echo json_encode(['success'=>false,'error'=>'Nombre inválido (2 a 60 caracteres: letras, números, espacios y / ( ) - .)']); exit;
    }
    try {
        if ($id) {
            $chk = getDB()->prepare("SELECT id FROM tipos_prenda WHERE id = ?");
            $chk->execute([$id]);
            if (!$chk->fetch()) { echo json_encode(['success'=>false,'error'=>'El tipo de prenda no existe.']); exit; }
            getDB()->prepare("UPDATE tipos_prenda SET nombre=? WHERE id=?")->execute([$nombre, $id]);
        } else {
            $max = (int) getDB()->query("SELECT COALESCE(MAX(orden),0) FROM tipos_prenda")->fetchColumn();
            getDB()->prepare("INSERT INTO tipos_prenda (nombre, orden) VALUES (?, ?)")->execute([$nombre, $max + 1]);
        }
        echo json_encode(['success'=>true]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'error'=> $e->getCode() == 23000 ? 'Ya existe un tipo de prenda con ese nombre.' : 'No se pudo guardar.']);
    }
    exit;
}

if ($esPost && $accion === 'toggle_prenda') {
    header('Content-Type: application/json');
    $upd = getDB()->prepare("UPDATE tipos_prenda SET activo = NOT activo WHERE id = ?");
    $upd->execute([(int) entrada($_POST, 'id')]);
    echo json_encode($upd->rowCount() ? ['success'=>true] : ['success'=>false,'error'=>'El tipo de prenda no existe.']); exit;
}

if ($esPost && $accion === 'toggle_servicio') {
    header('Content-Type: application/json');
    $upd = getDB()->prepare("UPDATE servicios SET activo = NOT activo WHERE id = ?");
    $upd->execute([(int) entrada($_POST, 'id')]);
    echo json_encode($upd->rowCount() ? ['success'=>true] : ['success'=>false,'error'=>'El servicio no existe.']); exit;
}

/* ── AJAX: subir foto de perfil ─── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='subir_foto') {
    header('Content-Type: application/json');
    if (!isset($_FILES['foto']) || !is_array($_FILES['foto']) || ($_FILES['foto']['error'] ?? -1) !== UPLOAD_ERR_OK) {
        $codigo = $_FILES['foto']['error'] ?? -1;
        echo json_encode(['success'=>false,'error'=> in_array($codigo, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'La imagen es demasiado grande.' : 'No se recibió el archivo.']); exit;
    }
    if (!@getimagesize($_FILES['foto']['tmp_name'])) {
        echo json_encode(['success'=>false,'error'=>'El archivo no es una imagen válida.']); exit;
    }
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $_FILES['foto']['tmp_name']); finfo_close($finfo);
    if (!in_array($mime, $allowed)) {
        echo json_encode(['success'=>false,'error'=>'Formato no permitido (JPG, PNG, WEBP o GIF)']); exit;
    }
    $ext    = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime];
    $nombre = 'perfil_' . time() . '.' . $ext;
    if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success'=>false,'error'=>'La imagen supera los 5 MB']); exit;
    }
    if (!is_dir(ROOT . 'assets/uploads/perfiles')) mkdir(ROOT . 'assets/uploads/perfiles', 0755, true);
    $dest   = ROOT . 'assets/uploads/perfiles/' . $nombre;
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
        $_SESSION['foto_perfil'] = 'assets/uploads/perfiles/' . $nombre;
        echo json_encode(['success'=>true,'url'=>$_SESSION['foto_perfil']]);
    } else {
        echo json_encode(['success'=>false,'error'=>'No se pudo guardar la imagen']);
    }
    exit;
}

/* ── AJAX: guardar nombre/cargo perfil ─── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='guardar_perfil') {
    header('Content-Type: application/json');
    $nombre = limpiarTexto(entrada($_POST, 'nombre'));
    $cargo  = limpiarTexto(entrada($_POST, 'cargo'));
    if ($e = validarNombre($nombre, 3, 80)) { echo json_encode(['success'=>false,'error'=>$e]); exit; }
    if (mb_strlen($cargo) > 60) { echo json_encode(['success'=>false,'error'=>'El cargo no puede superar 60 caracteres.']); exit; }
    if ($cargo !== '' && !preg_match("/^[\p{L}0-9][\p{L}0-9\s\.\-\/&]*$/u", $cargo)) {
        echo json_encode(['success'=>false,'error'=>'El cargo solo puede contener letras, números, espacios y . - / &']); exit;
    }
    // El nombre se guarda en la base de datos para que se conserve al volver a iniciar sesión
    getDB()->prepare("UPDATE usuarios SET nombre = ? WHERE id = ?")->execute([$nombre, (int) ($_SESSION['usuario_id'] ?? 0)]);
    $_SESSION['usuario_nombre'] = $nombre;
    $_SESSION['usuario_cargo']  = $cargo ?: 'Administrador';
    echo json_encode(['success'=>true]); exit;
}

/* ── AJAX: tasa ─── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='actualizar_tasa') {
    header('Content-Type: application/json');
    $bcv = numeroEnRango(entrada($_POST, 'tasa_bcv'), 0.0001, 100000000);
    if ($bcv === null) { echo json_encode(['success'=>false,'error'=>'Ingrese una tasa BCV válida mayor que cero.']); exit; }
    $par = null;
    if (trim(entrada($_POST, 'tasa_paralelo')) !== '') {
        $par = numeroEnRango(entrada($_POST, 'tasa_paralelo'), 0.0001, 100000000);
        if ($par === null) { echo json_encode(['success'=>false,'error'=>'La tasa paralela no es válida.']); exit; }
    }
    $ok = registrarTasa($bcv, $par, $_SESSION['usuario_nombre'] ?? 'admin');
    echo json_encode(['success'=>$ok]); exit;
}

/* ── AJAX: servicio ─── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='guardar_servicio') {
    header('Content-Type: application/json');
    $id     = (int) entrada($_POST, 'id');
    $nombre = limpiarTexto(entrada($_POST, 'nombre'));
    $unidad = entrada($_POST, 'unidad');
    $precio = numeroEnRango(entrada($_POST, 'precio'), 0.01, 9999);
    $horas  = numeroEnRango(entrada($_POST, 'horas'), 0.5, 720);
    $err = null;
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) $err = 'El nombre debe tener entre 3 y 100 caracteres.';
    elseif (!preg_match("/^[\p{L}0-9][\p{L}0-9\s\.,\/\(\)\-&+]*$/u", $nombre)) $err = 'El nombre solo puede contener letras, números, espacios y . , / ( ) - & +';
    elseif (!in_array($unidad, ['Kg','Unidad','Par','Pieza'], true)) $err = 'Unidad de medida inválida.';
    elseif ($precio === null) $err = 'El precio debe ser un número mayor que 0 y hasta 9999.';
    elseif ($horas === null)  $err = 'El tiempo estimado debe estar entre 0.5 y 720 horas.';
    if (!$err) {
        $dup = getDB()->prepare("SELECT id FROM servicios WHERE LOWER(nombre) = LOWER(?) AND id <> ?");
        $dup->execute([$nombre, $id]);
        if ($dup->fetch()) $err = 'Ya existe un servicio con ese nombre.';
    }
    if (!$err && $id) {
        $chk = getDB()->prepare("SELECT id FROM servicios WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) $err = 'El servicio no existe.';
    }
    if ($err) { echo json_encode(['success'=>false,'error'=>$err]); exit; }

    $sql = $id
      ? "UPDATE servicios SET nombre=:n,unidad_medida=:u,precio_usd=:p,tiempo_estimado_horas=:t WHERE id=:id"
      : "INSERT INTO servicios (nombre,unidad_medida,precio_usd,tiempo_estimado_horas) VALUES(:n,:u,:p,:t)";
    $params=[':n'=>$nombre,':u'=>$unidad,':p'=>$precio,':t'=>$horas];
    if($id) $params[':id']=$id;
    try { getDB()->prepare($sql)->execute($params); echo json_encode(['success'=>true]); }
    catch(Exception $e){ echo json_encode(['success'=>false,'error'=>'No se pudo guardar el servicio.']); }
    exit;
}

if(isset($_GET['get_svc'])){
    header('Content-Type: application/json');
    $s=getDB()->prepare("SELECT * FROM servicios WHERE id=?"); $s->execute([(int) entrada($_GET, 'get_svc')]);
    echo json_encode($s->fetch()?:[]); exit;
}

$tasa       = obtenerTasaHoy();
$servicios  = getDB()->query("SELECT * FROM servicios ORDER BY nombre")->fetchAll();
$histTasas  = getDB()->query("SELECT * FROM tasas_cambio ORDER BY fecha DESC LIMIT 10")->fetchAll();
$tiposPrenda = listarTiposPrenda(false);
$respaldos  = listarRespaldos();
$tab        = preg_replace('/[^a-z]/', '', entrada($_GET, 'tab', 'perfil'));
$fotoPerfil = $_SESSION['foto_perfil'] ?? '';
$usuNombre  = $_SESSION['usuario_nombre'] ?? 'Administrador';
$usuCargo   = $_SESSION['usuario_cargo']  ?? 'Administrador del Sistema';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Configuración — Lavandería Superior</title>
  <!-- Theme engine carga primero para evitar flash -->
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    /* ── PÁGINA DE CONFIGURACIÓN ─────────────────────────────── */
    .config-layout { display:grid; grid-template-columns:220px 1fr; gap:20px; }

    /* Menú lateral de configuración */
    .config-nav { display:flex; flex-direction:column; gap:2px; }
    .config-nav-item {
      display:flex; align-items:center; gap:10px;
      padding:9px 14px; border-radius:var(--radius-sm);
      font-size:13.5px; color:var(--text-sub);
      cursor:pointer; transition:all var(--t);
      border:1px solid transparent; background:none;
      font-family:var(--font-body); text-align:left; width:100%;
    }
    .config-nav-item:hover { background:var(--bg-surface2); color:var(--text); }
    .config-nav-item.active { background:var(--accent-bg); color:var(--accent-light); border-color:var(--accent-border); font-weight:500; }
    .config-nav-sep { font-size:10px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--text-hint); padding:14px 14px 4px; }

    /* Panel de contenido */
    .config-panel { display:none; }
    .config-panel.active { display:block; }

    /* ── FOTO DE PERFIL ─────────────────────────────────────── */
    .avatar-zona {
      display:flex; align-items:center; gap:24px;
      padding:24px; background:var(--bg-surface2);
      border-radius:var(--radius-lg); border:1px solid var(--border);
      margin-bottom:20px;
    }
    .avatar-wrap {
      position:relative; flex-shrink:0;
      width:96px; height:96px;
    }
    .avatar-img {
      width:96px; height:96px; border-radius:50%;
      object-fit:cover;
      border:3px solid var(--accent-border);
      background:var(--bg-surface3);
    }
    .avatar-placeholder {
      width:96px; height:96px; border-radius:50%;
      background:var(--accent-bg);
      border:3px solid var(--accent-border);
      display:flex; align-items:center; justify-content:center;
      font-family:var(--font-display); font-size:32px; font-weight:800;
      color:var(--accent-light);
    }
    .avatar-badge {
      position:absolute; bottom:2px; right:2px;
      width:26px; height:26px;
      background:var(--accent); border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      cursor:pointer; border:2px solid var(--bg-surface);
      transition:background var(--t);
    }
    .avatar-badge:hover { background:var(--accent-hover); }
    .avatar-info { flex:1; }
    .avatar-nombre { font-family:var(--font-display); font-size:20px; font-weight:700; color:var(--text); }
    .avatar-cargo  { font-size:13px; color:var(--text-sub); margin-top:3px; }
    .avatar-hint   { font-size:11.5px; color:var(--text-hint); margin-top:10px; }

    /* ── MODO CLARO/OSCURO ──────────────────────────────────── */
    .modo-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px; }
    .modo-card {
      border:2px solid var(--border-md); border-radius:var(--radius-lg);
      padding:16px; cursor:pointer; transition:all var(--t);
      position:relative; overflow:hidden; background:var(--bg-surface2);
    }
    .modo-card:hover { border-color:var(--border-strong); }
    .modo-card.selected { border-color:var(--accent); }
    .modo-card.selected::after {
      content:'✓';
      position:absolute; top:10px; right:12px;
      width:22px; height:22px; background:var(--accent);
      border-radius:50%; display:flex; align-items:center;
      justify-content:center; font-size:12px; color:#fff;
      font-weight:700; line-height:22px; text-align:center;
    }
    .modo-preview {
      border-radius:var(--radius-sm); overflow:hidden;
      margin-bottom:10px; border:1px solid var(--border);
    }
    .modo-preview-dark  { background:#0b0f14; height:60px; display:flex; align-items:center; justify-content:center; gap:8px; }
    .modo-preview-light { background:#f4f6f9; height:60px; display:flex; align-items:center; justify-content:center; gap:8px; }
    .mp-sidebar-d { width:14px; height:100%; background:#111820; }
    .mp-sidebar-l { width:14px; height:100%; background:#ffffff; border-right:1px solid #e5e7eb; }
    .mp-content-d { flex:1; padding:8px; display:flex; flex-direction:column; gap:4px; }
    .mp-content-l { flex:1; padding:8px; display:flex; flex-direction:column; gap:4px; }
    .mp-bar { height:4px; border-radius:2px; }
    .modo-label { font-size:13.5px; font-weight:600; color:var(--text); }
    .modo-sub   { font-size:11.5px; color:var(--text-sub); margin-top:2px; }

    /* ── SELECTOR DE ACENTO ─────────────────────────────────── */
    .acento-grid { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:8px; }
    .acento-chip {
      width:44px; height:44px; border-radius:50%;
      cursor:pointer; border:3px solid transparent;
      transition:all var(--t); position:relative;
      display:flex; align-items:center; justify-content:center;
    }
    .acento-chip:hover { transform:scale(1.1); }
    .acento-chip.selected { border-color:var(--text) !important; }
    .acento-chip.selected::after {
      content:'✓'; color:#fff; font-size:16px; font-weight:700;
      text-shadow:0 1px 3px rgba(0,0,0,0.5);
    }
    .acento-nombre { font-size:12px; color:var(--text-sub); margin-top:6px; }

    /* ── SELECTOR DE FUENTES ────────────────────────────────── */
    .fuente-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
    .fuente-card {
      padding:14px 16px; border:1px solid var(--border-md);
      border-radius:var(--radius-md); cursor:pointer;
      transition:all var(--t); background:var(--bg-surface2);
    }
    .fuente-card:hover { border-color:var(--border-strong); }
    .fuente-card.selected { border-color:var(--accent); background:var(--accent-bg); }
    .fuente-muestra { font-size:20px; font-weight:700; color:var(--text); line-height:1; margin-bottom:4px; }
    .fuente-tag { font-size:11px; color:var(--text-sub); }

    /* ── TAMAÑO ─────────────────────────────────────────────── */
    .tamano-row { display:flex; gap:10px; }
    .tamano-btn {
      flex:1; padding:10px; border-radius:var(--radius-md);
      border:1px solid var(--border-md); background:var(--bg-surface2);
      cursor:pointer; transition:all var(--t); text-align:center;
      font-family:var(--font-body);
    }
    .tamano-btn:hover { border-color:var(--border-strong); }
    .tamano-btn.selected { border-color:var(--accent); background:var(--accent-bg); }
    .tamano-letra { color:var(--text); font-weight:600; }
    .tamano-desc  { font-size:11px; color:var(--text-sub); margin-top:3px; }

    /* ── RADIO ──────────────────────────────────────────────── */
    .radio-row { display:flex; gap:10px; }
    .radio-btn {
      flex:1; padding:12px; border-radius:var(--radius-md);
      border:1px solid var(--border-md); background:var(--bg-surface2);
      cursor:pointer; transition:all var(--t); text-align:center;
      font-family:var(--font-body);
    }
    .radio-btn:hover { border-color:var(--border-strong); }
    .radio-btn.selected { border-color:var(--accent); background:var(--accent-bg); }
    .radio-preview { width:100%; height:14px; background:var(--accent-bg); border:1px solid var(--accent-border); margin-bottom:6px; }
    .radio-desc { font-size:11.5px; color:var(--text-sub); }

    /* ── TOGGLE SWITCH ──────────────────────────────────────── */
    .toggle-row { display:flex; align-items:center; justify-content:space-between; padding:14px 0; border-bottom:1px solid var(--border); }
    .toggle-row:last-child { border-bottom:none; }
    .toggle-info p { font-size:13.5px; font-weight:500; color:var(--text); }
    .toggle-info span { font-size:12px; color:var(--text-sub); }
    .toggle {
      width:46px; height:26px;
      background:var(--bg-surface3); border-radius:13px;
      cursor:pointer; position:relative; transition:background .2s;
      border:1px solid var(--border-md); flex-shrink:0;
    }
    .toggle.on { background:var(--accent); border-color:transparent; }
    .toggle::after {
      content:''; position:absolute;
      width:20px; height:20px; border-radius:50%;
      background:#fff; top:2px; left:2px;
      transition:transform .2s; box-shadow:0 1px 4px rgba(0,0,0,.3);
    }
    .toggle.on::after { transform:translateX(20px); }

    /* ── LIVE PREVIEW BADGE ─────────────────────────────────── */
    .live-badge {
      display:inline-flex; align-items:center; gap:6px;
      font-size:11px; color:var(--green);
      background:var(--green-bg); border:1px solid var(--green-border);
      padding:3px 10px; border-radius:20px; margin-bottom:16px;
    }
    .live-dot { width:6px; height:6px; border-radius:50%; background:var(--green); animation:pulse 1.4s infinite; }
    @keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }

    /* ── RESPALDOS ───────────────────────────────────────────── */
    .bk-hero { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px; }
    .bk-box { padding:18px; border-radius:var(--radius-lg); border:1px solid var(--border); background:var(--bg-surface2); }
    .bk-box h3 { font-family:var(--font-display); font-size:15px; font-weight:700; color:var(--text); margin-bottom:6px; display:flex; align-items:center; gap:8px; }
    .bk-box p { font-size:12.5px; color:var(--text-sub); margin-bottom:14px; line-height:1.55; }
    .bk-box.warn { border-color:var(--amber-border); }
    .bk-drop { border:1.5px dashed var(--border-strong); border-radius:var(--radius-md); padding:14px; text-align:center; font-size:12.5px; color:var(--text-sub); cursor:pointer; transition:all var(--t); margin-bottom:12px; }
    .bk-drop:hover, .bk-drop.over { border-color:var(--accent); color:var(--accent-light); background:var(--accent-bg); }
    .bk-motivo { font-size:10.5px; font-weight:600; padding:2px 8px; border-radius:20px; background:var(--bg-surface3); color:var(--text-sub); }
    .bk-motivo.antes_de_restaurar { background:var(--amber-bg); color:var(--amber); }
    .bk-motivo.subido { background:var(--teal-bg); color:var(--teal); }
    @media(max-width:900px){ .bk-hero { grid-template-columns:1fr; } .config-layout { grid-template-columns:1fr; } }

    /* Ajustes de título en página */
    .page-title { font-size:var(--font-size-title, 24px); }
    .stat-value { font-size:var(--font-size-stat, 26px); }
  </style>
</head>
<body>
<div class="layout">
  <?php partial('sidebar'); ?>
  <main class="main-content">
    <div class="topbar">
      <div>
        <h1 class="page-title">Configuración</h1>
        <p class="page-sub">Personalización del sistema</p>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-secondary" onclick="ThemeEngine.reset(); refrescarUI();" data-tip="Vuelve la apariencia (colores, fuentes, tamaños) a los valores de fábrica. No afecta los datos." data-tip-pos="bottom">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
          Restablecer
        </button>
      </div>
    </div>

    <div class="content-area">
      <div class="config-layout">

        <!-- ── MENÚ LATERAL ── -->
        <nav>
          <div class="card" style="padding:10px;">
            <p class="config-nav-sep">Cuenta</p>
            <button class="config-nav-item <?= $tab==='perfil'?'active':'' ?>" onclick="mostrarPanel('perfil',this)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Perfil
            </button>

            <p class="config-nav-sep">Apariencia</p>
            <button class="config-nav-item <?= $tab==='apariencia'?'active':'' ?>" onclick="mostrarPanel('apariencia',this)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20"/><path d="M12 8a4 4 0 0 1 0 8"/></svg>
              Modo y Color
            </button>
            <button class="config-nav-item <?= $tab==='fuentes'?'active':'' ?>" onclick="mostrarPanel('fuentes',this)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
              Tipografía
            </button>
            <button class="config-nav-item <?= $tab==='interfaz'?'active':'' ?>" onclick="mostrarPanel('interfaz',this)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
              Interfaz
            </button>

            <p class="config-nav-sep">Sistema</p>
            <button class="config-nav-item <?= $tab==='tasa'?'active':'' ?>" onclick="mostrarPanel('tasa',this)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              Tasa de Cambio
            </button>
            <button class="config-nav-item <?= $tab==='servicios'?'active':'' ?>" onclick="mostrarPanel('servicios',this)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Servicios
            </button>
            <button class="config-nav-item <?= $tab==='prendas'?'active':'' ?>" onclick="mostrarPanel('prendas',this)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg>
              Tipos de prenda
            </button>
            <button class="config-nav-item <?= $tab==='respaldo'?'active':'' ?>" onclick="mostrarPanel('respaldo',this)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
              Respaldo de datos
            </button>
            <button class="config-nav-item <?= $tab==='sistema'?'active':'' ?>" onclick="mostrarPanel('sistema',this)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41"/></svg>
              Sistema
            </button>
          </div>
        </nav>

        <!-- ── PANELES ── -->
        <div>

          <!-- PANEL: PERFIL -->
          <div id="panel-perfil" class="config-panel <?= $tab==='perfil'?'active':'' ?>">
            <div class="card" style="margin-bottom:16px;">
              <div class="card-header"><h2 class="card-title">Foto de Perfil</h2></div>

              <div class="avatar-zona">
                <div class="avatar-wrap">
                  <?php if($fotoPerfil): ?>
                    <img id="avatar-preview" class="avatar-img" src="<?= h($fotoPerfil) ?>" alt="Foto de perfil">
                  <?php else: ?>
                    <div class="avatar-placeholder" id="avatar-placeholder">
                      <?= strtoupper(substr($usuNombre,0,1)) ?>
                    </div>
                    <img id="avatar-preview" class="avatar-img" src="" alt="" style="display:none;">
                  <?php endif; ?>
                  <div class="avatar-badge" onclick="document.getElementById('input-foto').click()" title="Cambiar foto">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                  </div>
                  <input type="file" id="input-foto" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirFoto(this)">
                </div>
                <div class="avatar-info">
                  <p class="avatar-nombre" id="display-nombre"><?= h($usuNombre) ?></p>
                  <p class="avatar-cargo"  id="display-cargo"><?= h($usuCargo) ?></p>
                  <p class="avatar-hint">JPG, PNG o WEBP · máximo 5 MB</p>
                  <div style="margin-top:12px;display:flex;gap:8px;">
                    <button class="btn btn-sm btn-primary" onclick="document.getElementById('input-foto').click()">Cambiar foto</button>
                    <button class="btn btn-sm btn-danger" onclick="eliminarFoto()" id="btn-eliminar-foto" <?= !$fotoPerfil?'style="display:none"':'' ?>>Eliminar</button>
                  </div>
                </div>
              </div>

              <div class="form-row form-row-2">
                <div class="form-group">
                  <label class="form-label">Nombre para mostrar</label>
                  <input id="p-nombre" class="form-control" maxlength="80" data-mask="name" value="<?= h($usuNombre) ?>" placeholder="Tu nombre" oninput="document.getElementById('display-nombre').textContent=this.value">
                </div>
                <div class="form-group">
                  <label class="form-label">Cargo / Rol</label>
                  <input id="p-cargo" class="form-control" maxlength="60" value="<?= h($usuCargo) ?>" placeholder="Administrador" oninput="document.getElementById('display-cargo').textContent=this.value">
                </div>
              </div>
              <div style="margin-top:16px;">
                <button class="btn btn-primary" onclick="guardarPerfil()">Guardar Perfil</button>
              </div>
            </div>
          </div>

          <!-- PANEL: APARIENCIA -->
          <div id="panel-apariencia" class="config-panel <?= $tab==='apariencia'?'active':'' ?>">
            <div class="live-badge"><span class="live-dot"></span> Vista previa en tiempo real</div>

            <!-- MODO -->
            <div class="card" style="margin-bottom:16px;">
              <div class="card-header"><h2 class="card-title">Modo de pantalla</h2></div>
              <div class="modo-grid">
                <!-- Oscuro -->
                <div class="modo-card" id="modo-dark" onclick="elegirModo('dark')">
                  <div class="modo-preview">
                    <div class="modo-preview-dark" style="height:64px;">
                      <div class="mp-sidebar-d" style="height:64px;display:flex;flex-direction:column;gap:4px;padding:6px 3px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:#2563eb;"></div>
                        <div style="width:8px;height:3px;border-radius:2px;background:#19222d;"></div>
                        <div style="width:8px;height:3px;border-radius:2px;background:#19222d;"></div>
                        <div style="width:8px;height:3px;border-radius:2px;background:#19222d;"></div>
                      </div>
                      <div class="mp-content-d">
                        <div class="mp-bar" style="width:80%;background:#19222d;"></div>
                        <div style="display:flex;gap:3px;">
                          <div style="width:28%;height:18px;border-radius:3px;background:#19222d;"></div>
                          <div style="width:28%;height:18px;border-radius:3px;background:#19222d;"></div>
                          <div style="width:28%;height:18px;border-radius:3px;background:#19222d;"></div>
                        </div>
                        <div class="mp-bar" style="width:60%;background:#19222d;"></div>
                      </div>
                    </div>
                  </div>
                  <p class="modo-label">Oscuro</p>
                  <p class="modo-sub">Ideal para ambientes de trabajo</p>
                </div>
                <!-- Claro -->
                <div class="modo-card" id="modo-light" onclick="elegirModo('light')">
                  <div class="modo-preview">
                    <div class="modo-preview-light" style="height:64px;">
                      <div class="mp-sidebar-l" style="height:64px;display:flex;flex-direction:column;gap:4px;padding:6px 3px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:#2563eb;"></div>
                        <div style="width:8px;height:3px;border-radius:2px;background:#e5e7eb;"></div>
                        <div style="width:8px;height:3px;border-radius:2px;background:#e5e7eb;"></div>
                        <div style="width:8px;height:3px;border-radius:2px;background:#e5e7eb;"></div>
                      </div>
                      <div class="mp-content-l">
                        <div class="mp-bar" style="width:80%;background:#e5e7eb;"></div>
                        <div style="display:flex;gap:3px;">
                          <div style="width:28%;height:18px;border-radius:3px;background:#e5e7eb;"></div>
                          <div style="width:28%;height:18px;border-radius:3px;background:#e5e7eb;"></div>
                          <div style="width:28%;height:18px;border-radius:3px;background:#e5e7eb;"></div>
                        </div>
                        <div class="mp-bar" style="width:60%;background:#e5e7eb;"></div>
                      </div>
                    </div>
                  </div>
                  <p class="modo-label">Claro</p>
                  <p class="modo-sub">Para entornos con mucha luz</p>
                </div>
              </div>
            </div>

            <!-- COLOR DE ACENTO -->
            <div class="card">
              <div class="card-header"><h2 class="card-title">Color de acento</h2></div>
              <div class="acento-grid" id="acento-grid">
                <!-- generado por JS -->
              </div>
              <p class="form-hint">El color de acento aplica a botones, enlaces, estados activos y métricas.</p>
            </div>
          </div>

          <!-- PANEL: FUENTES -->
          <div id="panel-fuentes" class="config-panel <?= $tab==='fuentes'?'active':'' ?>">
            <div class="live-badge"><span class="live-dot"></span> Vista previa en tiempo real</div>

            <!-- FUENTE DISPLAY -->
            <div class="card" style="margin-bottom:16px;">
              <div class="card-header">
                <h2 class="card-title">Fuente de títulos</h2>
                <span class="text-sub" style="font-size:12px;">Para encabezados y valores grandes</span>
              </div>
              <div class="fuente-grid" id="grid-display"><!-- JS --></div>
            </div>

            <!-- FUENTE BODY -->
            <div class="card">
              <div class="card-header">
                <h2 class="card-title">Fuente de cuerpo</h2>
                <span class="text-sub" style="font-size:12px;">Para texto general e interfaz</span>
              </div>
              <div class="fuente-grid" id="grid-body"><!-- JS --></div>
            </div>
          </div>

          <!-- PANEL: INTERFAZ -->
          <div id="panel-interfaz" class="config-panel <?= $tab==='interfaz'?'active':'' ?>">
            <div class="live-badge"><span class="live-dot"></span> Vista previa en tiempo real</div>

            <!-- TAMAÑO DE FUENTE -->
            <div class="card" style="margin-bottom:16px;">
              <div class="card-header"><h2 class="card-title">Tamaño del texto</h2></div>
              <div class="tamano-row" id="tamano-row">
                <button class="tamano-btn" data-val="sm" onclick="elegir('tamano','sm',this)">
                  <div class="tamano-letra" style="font-size:13px;">Aa</div>
                  <div class="tamano-desc">Compacto</div>
                </button>
                <button class="tamano-btn" data-val="md" onclick="elegir('tamano','md',this)">
                  <div class="tamano-letra" style="font-size:17px;">Aa</div>
                  <div class="tamano-desc">Normal</div>
                </button>
                <button class="tamano-btn" data-val="lg" onclick="elegir('tamano','lg',this)">
                  <div class="tamano-letra" style="font-size:21px;">Aa</div>
                  <div class="tamano-desc">Grande</div>
                </button>
              </div>
            </div>

            <!-- BORDER RADIUS -->
            <div class="card" style="margin-bottom:16px;">
              <div class="card-header"><h2 class="card-title">Redondez de esquinas</h2></div>
              <div class="radio-row" id="radio-row">
                <button class="radio-btn" data-val="sm" onclick="elegir('radioUI','sm',this)">
                  <div class="radio-preview" style="border-radius:2px;"></div>
                  <div class="radio-desc">Cuadrado</div>
                </button>
                <button class="radio-btn" data-val="md" onclick="elegir('radioUI','md',this)">
                  <div class="radio-preview" style="border-radius:8px;"></div>
                  <div class="radio-desc">Redondeado</div>
                </button>
                <button class="radio-btn" data-val="lg" onclick="elegir('radioUI','lg',this)">
                  <div class="radio-preview" style="border-radius:16px;"></div>
                  <div class="radio-desc">Muy curvo</div>
                </button>
              </div>
            </div>

            <!-- TOGGLES -->
            <div class="card">
              <div class="card-header"><h2 class="card-title">Otras opciones</h2></div>
              <div class="toggle-row">
                <div class="toggle-info">
                  <p>Sidebar compacto</p>
                  <span>Muestra solo iconos en la barra lateral</span>
                </div>
                <div class="toggle" id="toggle-sidebar" onclick="togglePref('sidebarCompacto','toggle-sidebar')"></div>
              </div>
            </div>
          </div>

          <!-- PANEL: TASA -->
          <div id="panel-tasa" class="config-panel <?= $tab==='tasa'?'active':'' ?>">
            <div class="grid-main-aside">
              <div class="card">
                <div class="card-header"><h2 class="card-title">Actualizar Tasa BCV</h2></div>
                <div class="form-row form-row-2" style="margin-bottom:20px;">
                  <div class="form-group">
                    <label class="form-label" for="t-bcv">Tasa BCV (Bs./USD) <span class="req">*</span> <span class="help-dot" tabindex="0" data-tip="Tasa oficial publicada por el Banco Central de Venezuela. Se usa para convertir los montos a bolívares.">?</span></label>
                    <input id="t-bcv" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="14" value="<?= (float)$tasa['tasa_bcv'] ?>" placeholder="46.50">
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="t-par">Tasa Paralelo (opcional) <span class="help-dot" tabindex="0" data-tip="Solo informativa; no se usa en los cálculos.">?</span></label>
                    <input id="t-par" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="14" value="<?= isset($tasa['tasa_paralelo']) ? (float)$tasa['tasa_paralelo'] : '' ?>" placeholder="—">
                  </div>
                </div>
                <p class="form-hint" style="margin-bottom:16px;">
                  💡 Consulta la tasa en <a href="https://www.bcv.org.ve" target="_blank" style="color:var(--accent-light)">bcv.org.ve</a>
                </p>
                <button class="btn btn-primary" onclick="actualizarTasa()">Guardar Tasa de Hoy</button>
              </div>
              <div class="card">
                <div class="card-header"><h2 class="card-title">Historial</h2></div>
                <?php foreach($histTasas as $h): ?>
                <div class="movimiento-row" style="padding:8px 0;">
                  <div class="mov-desc">
                    <p><?= date('d/m/Y', strtotime($h['fecha'])) ?></p>
                    <span>por <?= h($h['registrado_por']??'—') ?></span>
                  </div>
                  <div style="text-align:right;">
                    <p style="font-weight:600;color:var(--text)">Bs. <?= number_format($h['tasa_bcv'],2,',','.') ?></p>
                    <?php if($h['tasa_paralelo']): ?>
                    <p style="font-size:11px;color:var(--text-hint)">Par. <?= number_format($h['tasa_paralelo'],2,',','.') ?></p>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- PANEL: SERVICIOS -->
          <div id="panel-servicios" class="config-panel <?= $tab==='servicios'?'active':'' ?>">
            <div class="flex-between" style="margin-bottom:16px;">
              <span class="text-sub"><?= count($servicios) ?> servicios</span>
              <button class="btn btn-primary" onclick="abrirSvc()" data-tip="Agregar un servicio al catálogo">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nuevo Servicio
              </button>
            </div>
            <div class="table-wrapper">
              <table class="data-table">
                <thead><tr><th>Nombre</th><th>Unidad</th><th>Precio USD</th><th>Tiempo</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                  <?php foreach($servicios as $s): ?>
                  <tr>
                    <td style="font-weight:500"><?= h($s['nombre']) ?></td>
                    <td class="text-sub"><?= $s['unidad_medida'] ?></td>
                    <td class="text-green"><?= formatUSD($s['precio_usd']) ?></td>
                    <td class="text-sub"><?= $s['tiempo_estimado_horas'] ?>h</td>
                    <td><span class="badge <?= $s['activo']?'badge-green':'badge-gray' ?>"><?= $s['activo']?'Activo':'Inactivo' ?></span></td>
                    <td style="white-space:nowrap;">
                      <button class="btn btn-sm btn-secondary" onclick="editarSvc(<?= $s['id'] ?>)" data-tip="Modificar nombre, precio o tiempo">Editar</button>
                      <button class="btn btn-sm <?= $s['activo']?'btn-danger':'btn-success' ?>" onclick="toggleSvc(<?= $s['id'] ?>)" data-tip="<?= $s['activo']?'Ocultar de las órdenes nuevas (se conserva el historial)':'Volver a ofrecer este servicio' ?>"><?= $s['activo']?'Desactivar':'Activar' ?></button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- PANEL: TIPOS DE PRENDA -->
          <div id="panel-prendas" class="config-panel <?= $tab==='prendas'?'active':'' ?>">
            <div class="card">
              <div class="card-header">
                <h2 class="card-title">Tipos de prenda <span class="help-dot" tabindex="0" data-tip="Catálogo que aparece al registrar las prendas de una orden. Los primeros 12 activos se muestran como accesos rápidos.">?</span></h2>
                <span class="text-sub" style="font-size:12px;"><?= count(array_filter($tiposPrenda, fn($t)=>$t['activo'])) ?> activos</span>
              </div>
              <div class="input-group" style="margin-bottom:16px;max-width:460px;">
                <input id="tp-nuevo" class="form-control" maxlength="60" placeholder="Ej: Chemise, Bata de laboratorio…" onkeydown="if(event.key==='Enter')guardarPrenda()">
                <button class="btn btn-primary" onclick="guardarPrenda()" data-tip="Agregar al catálogo">Agregar</button>
              </div>
              <div class="table-wrapper">
                <table class="data-table">
                  <thead><tr><th>Nombre</th><th>Estado</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach($tiposPrenda as $tp): ?>
                    <tr>
                      <td style="font-weight:500"><?= h($tp['nombre']) ?></td>
                      <td><span class="badge <?= $tp['activo']?'badge-green':'badge-gray' ?>"><?= $tp['activo']?'Activo':'Inactivo' ?></span></td>
                      <td style="text-align:right;white-space:nowrap;">
                        <button class="btn btn-sm btn-secondary" onclick="renombrarPrenda(<?= (int)$tp['id'] ?>, this)" data-nombre="<?= h($tp['nombre']) ?>" data-tip="Cambiar el nombre">Renombrar</button>
                        <button class="btn btn-sm <?= $tp['activo']?'btn-danger':'btn-success' ?>" onclick="togglePrenda(<?= (int)$tp['id'] ?>)" data-tip="<?= $tp['activo']?'Ocultar en órdenes nuevas':'Mostrar en órdenes nuevas' ?>"><?= $tp['activo']?'Desactivar':'Activar' ?></button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- PANEL: RESPALDO -->
          <div id="panel-respaldo" class="config-panel <?= $tab==='respaldo'?'active':'' ?>">
            <div class="bk-hero">
              <div class="bk-box">
                <h3>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  Crear respaldo
                </h3>
                <p>Genera una copia completa de la base de datos (clientes, órdenes, caja, inventario, usuarios y configuración). Se guarda en el servidor y puedes descargarla para conservarla en un pendrive o en la nube.</p>
                <button class="btn btn-primary" id="btn-respaldo" onclick="crearRespaldo()" data-tip="Recomendado: al cierre de cada día">Crear respaldo ahora</button>
              </div>
              <div class="bk-box warn">
                <h3>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                  Restaurar desde archivo
                </h3>
                <p>Sube un archivo <b>.sql</b> generado por este sistema. <b style="color:var(--amber)">Reemplaza todos los datos actuales.</b> Antes se crea automáticamente un respaldo de seguridad.</p>
                <label class="bk-drop" id="bk-drop" data-tip="Arrastra aquí el archivo o haz clic para elegirlo">
                  <input type="file" id="bk-archivo" accept=".sql" style="display:none" onchange="archivoElegido(this.files[0])">
                  <span id="bk-drop-txt">Seleccionar archivo .sql (máx. 50 MB)</span>
                </label>
                <button class="btn btn-danger" id="btn-restaurar-archivo" onclick="pedirRestauracion(null)" disabled>Restaurar archivo</button>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <h2 class="card-title">Respaldos guardados en el servidor</h2>
                <span class="text-sub" style="font-size:12px;"><?= count($respaldos) ?> archivo<?= count($respaldos)===1?'':'s' ?></span>
              </div>
              <?php if (!$respaldos): ?>
                <div class="empty-state"><p>Aún no hay respaldos</p><span>Crea el primero con el botón «Crear respaldo ahora».</span></div>
              <?php else: ?>
              <div class="table-wrapper">
                <table class="data-table">
                  <thead><tr><th>Fecha</th><th>Tipo</th><th>Tamaño</th><th style="text-align:right">Acciones</th></tr></thead>
                  <tbody>
                  <?php
                    $motivos = ['manual'=>'Manual','antes_de_restaurar'=>'Seguridad (antes de restaurar)','subido'=>'Archivo subido'];
                    foreach ($respaldos as $r): ?>
                    <tr>
                      <td><?= date('d/m/Y h:i a', $r['fecha']) ?><br><span class="text-hint mono" style="font-size:11px;"><?= h($r['nombre']) ?></span></td>
                      <td><span class="bk-motivo <?= h($r['motivo']) ?>"><?= h($motivos[$r['motivo']] ?? $r['motivo']) ?></span></td>
                      <td class="text-sub"><?= tamanoLegible($r['bytes']) ?></td>
                      <td style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-sm btn-secondary" href="configuracion.php?descargar_respaldo=<?= urlencode($r['nombre']) ?>" data-tip="Descargar a este equipo">Descargar</a>
                        <button class="btn btn-sm btn-secondary" onclick="pedirRestauracion('<?= h($r['nombre']) ?>')" data-tip="Volver la base de datos a este punto">Restaurar</button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarRespaldo('<?= h($r['nombre']) ?>')" data-tip="Eliminar del servidor">✕</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php endif; ?>
              <p class="form-hint" style="margin-top:12px;">💡 Buenas prácticas: respalda a diario, guarda copias fuera del equipo y elimina respaldos muy antiguos. La carpeta <span class="mono">/respaldos</span> no es accesible desde el navegador.</p>
            </div>
          </div>

          <!-- PANEL: SISTEMA -->
          <div id="panel-sistema" class="config-panel <?= $tab==='sistema'?'active':'' ?>">
            <div class="card">
              <div class="card-header"><h2 class="card-title">Información del Sistema</h2></div>
              <div class="form-row form-row-2" style="gap:16px;">
                <?php
                $info = [
                  'Versión PHP'    => PHP_VERSION,
                  'Base de Datos'  => 'MySQL ' . (getDB()->query("SELECT VERSION()")->fetchColumn()),
                  'Zona horaria'   => date_default_timezone_get(),
                  'Fecha servidor' => date('d/m/Y H:i:s'),
                  'Proyecto'       => 'SISTEMA DE INFORMACIÓN PARA LA AUTOMATIZACIÓN DE SERVICIOS EN LA LAVANDERÍA LA SUPREMA UBICADA EN VALLE DE LA PASCUA ESTADO GUARICO',
                  'Versión del sistema' => SISTEMA_VERSION,
                  'Institución'    => PROYECTO_INSTITUCION . ' (' . PROYECTO_SIGLAS . ')',
                  'Tipo de proyecto' => 'Proyecto universitario — ' . PROYECTO_UBICACION,
                ];
                foreach($info as $k=>$v): ?>
                <div style="padding:12px;background:var(--bg-surface2);border-radius:var(--radius-md);border:1px solid var(--border);">
                  <p class="form-label" style="margin-bottom:4px;"><?= $k ?></p>
                  <p style="color:var(--text);font-weight:500;"><?= h($v) ?></p>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

        </div><!-- /paneles -->
      </div><!-- /config-layout -->
    </div><!-- /content-area -->
  </main>
</div>

<!-- MODAL SERVICIO -->
<div id="modal-svc" class="modal-backdrop" style="display:none;">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="svc-title">Nuevo Servicio</h2>
      <button class="modal-close" onclick="document.getElementById('modal-svc').style.display='none'">×</button>
    </div>
    <input type="hidden" id="s-id">
    <div class="form-row form-row-2" style="margin-bottom:14px;">
      <div class="form-group"><label class="form-label" for="s-nombre">Nombre <span class="req">*</span></label><input id="s-nombre" class="form-control" maxlength="100" placeholder="Lavado por peso"></div>
      <div class="form-group"><label class="form-label" for="s-unidad">Unidad <span class="req">*</span> <span class="help-dot" tabindex="0" data-tip="Kg: se cobra por peso y descuenta insumos. Unidad/Par/Pieza: se cobra por artículo.">?</span></label>
        <select id="s-unidad" class="form-control"><option value="Kg">Kg</option><option value="Unidad">Unidad</option><option value="Par">Par</option><option value="Pieza">Pieza</option></select>
      </div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group"><label class="form-label" for="s-precio">Precio USD <span class="req">*</span></label><input id="s-precio" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="8" placeholder="1.50"></div>
      <div class="form-group"><label class="form-label" for="s-horas">Tiempo estimado (horas) <span class="help-dot" tabindex="0" data-tip="Define la fecha prometida de entrega de la orden.">?</span></label><input id="s-horas" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="5" value="8"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="document.getElementById('modal-svc').style.display='none'">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarSvc()">Guardar</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
/* ── ESTADO ACTUAL ── */
const prefs = ThemeEngine.getPrefs();

/* ── NAVEGACIÓN DE PANELES ── */
function mostrarPanel(id, btn) {
  document.querySelectorAll('.config-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.config-nav-item').forEach(b => b.classList.remove('active'));
  document.getElementById('panel-'+id).classList.add('active');
  btn.classList.add('active');
}

/* ── MODO ── */
function elegirModo(modo) {
  ThemeEngine.set('modo', modo);
  document.getElementById('modo-dark').classList.toggle('selected',  modo==='dark');
  document.getElementById('modo-light').classList.toggle('selected', modo==='light');
  toast('Modo ' + (modo==='dark'?'oscuro':'claro') + ' activado', 'success');
}

/* ── ACENTOS ── */
const ACENTO_LABELS = {
  blue:'Azul', violet:'Violeta', rose:'Rosa', emerald:'Verde',
  amber:'Ámbar', cyan:'Cyan', slate:'Gris',
};

function renderAcentos() {
  const grid = document.getElementById('acento-grid');
  grid.innerHTML = '';
  Object.entries(ThemeEngine.ACENTOS).forEach(([key, val]) => {
    const chip = document.createElement('div');
    chip.style.cssText = `width:44px;height:44px;border-radius:50%;cursor:pointer;background:${val.accent};border:3px solid transparent;transition:all .16s;position:relative;display:flex;align-items:center;justify-content:center;`;
    if (prefs.acento === key) chip.style.border = `3px solid white`;
    chip.title = ACENTO_LABELS[key] || key;
    chip.dataset.key = key;
    if (prefs.acento === key) chip.innerHTML = '<span style="color:#fff;font-size:16px;font-weight:700;text-shadow:0 1px 3px rgba(0,0,0,.5)">✓</span>';
    chip.addEventListener('click', () => {
      ThemeEngine.set('acento', key);
      renderAcentos();
      toast('Acento ' + (ACENTO_LABELS[key]||key) + ' aplicado', 'success');
    });
    chip.addEventListener('mouseenter', () => chip.style.transform = 'scale(1.12)');
    chip.addEventListener('mouseleave', () => chip.style.transform = 'scale(1)');

    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:4px;';
    const label = document.createElement('div');
    label.style.cssText = 'font-size:10.5px;color:var(--text-sub);';
    label.textContent = ACENTO_LABELS[key] || key;
    wrap.appendChild(chip);
    wrap.appendChild(label);
    grid.appendChild(wrap);
  });
}

/* ── FUENTES ── */
function renderFuentes() {
  /* Display */
  const gD = document.getElementById('grid-display');
  gD.innerHTML = '';
  Object.entries(ThemeEngine.FUENTES_DISPLAY).forEach(([key, val]) => {
    const card = document.createElement('div');
    card.className = 'fuente-card' + (prefs.fuenteDisplay===key?' selected':'');
    card.innerHTML = `<div class="fuente-muestra" style="font-family:'${val.name}',sans-serif">${val.name}</div><div class="fuente-tag">Títulos · ${key}</div>`;
    card.addEventListener('click', () => {
      ThemeEngine.set('fuenteDisplay', key);
      renderFuentes();
      toast('Fuente de título: ' + val.name, 'success');
    });
    gD.appendChild(card);
  });

  /* Body */
  const gB = document.getElementById('grid-body');
  gB.innerHTML = '';
  Object.entries(ThemeEngine.FUENTES_BODY).forEach(([key, val]) => {
    const card = document.createElement('div');
    card.className = 'fuente-card' + (prefs.fuenteBody===key?' selected':'');
    card.innerHTML = `<div class="fuente-muestra" style="font-family:'${val.name}',sans-serif;font-weight:500;font-size:16px;">${val.name}</div><div class="fuente-tag">Interfaz · ${key}</div>`;
    card.addEventListener('click', () => {
      ThemeEngine.set('fuenteBody', key);
      renderFuentes();
      toast('Fuente de interfaz: ' + val.name, 'success');
    });
    gB.appendChild(card);
  });
}

/* ── ELEGIR VALOR (tamaño, radio) ── */
function elegir(clave, valor, btn) {
  ThemeEngine.set(clave, valor);
  const fila = btn.parentElement;
  fila.querySelectorAll('button').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  toast('Ajuste aplicado', 'success');
}

/* ── TOGGLES ── */
function togglePref(clave, toggleId) {
  const p = ThemeEngine.getPrefs();
  const nuevo = !p[clave];
  ThemeEngine.set(clave, nuevo);
  document.getElementById(toggleId).classList.toggle('on', nuevo);
  toast(nuevo ? 'Activado' : 'Desactivado', 'success');
}

/* ── FOTO DE PERFIL ── */
function subirFoto(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) { toast('La imagen supera los 5 MB', 'error'); return; }

  /* Preview inmediato */
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('avatar-preview');
    const ph  = document.getElementById('avatar-placeholder');
    img.src = e.target.result;
    img.style.display = 'block';
    if (ph) ph.style.display = 'none';
    document.getElementById('btn-eliminar-foto').style.display = '';
    /* Guardar en localStorage como base64 para persistencia client-side */
    localStorage.setItem(ThemeEngine.clave('ls_foto_perfil'), e.target.result);
  };
  reader.readAsDataURL(file);

  /* Subir al servidor */
  const fd = new FormData(); fd.append('action','subir_foto'); fd.append('foto',file);
  fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (d.success) toast('Foto actualizada','success');
    else toast('Error al subir: '+(d.error||'?'),'error');
  });
}

function eliminarFoto() {
  localStorage.removeItem(ThemeEngine.clave('ls_foto_perfil'));
  const img = document.getElementById('avatar-preview');
  const ph  = document.getElementById('avatar-placeholder');
  img.src = ''; img.style.display = 'none';
  if (ph) ph.style.display = 'flex';
  document.getElementById('btn-eliminar-foto').style.display = 'none';
  toast('Foto eliminada','success');
}

function guardarPerfil() {
  const nombre = document.getElementById('p-nombre').value.trim();
  const cargo  = document.getElementById('p-cargo').value.trim();
  if (!Valida.comprobar([
    [document.getElementById('p-nombre'), Valida.nombre(nombre, 3)],
    [document.getElementById('p-cargo'),  cargo.length > 60 ? 'El cargo no puede superar 60 caracteres.' : null],
  ])) return;

  // Guardar en localStorage para que theme-engine lo sincronice en todos los módulos
  localStorage.setItem(ThemeEngine.clave('ls_perfil'), JSON.stringify({ nombre, cargo }));

  // Actualizar el sidebar de esta página inmediatamente
  ThemeEngine.aplicarPerfil();

  // Persistir también en sesión PHP
  const fd = new FormData();
  fd.append('action','guardar_perfil');
  fd.append('nombre', nombre);
  fd.append('cargo',  cargo);
  fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (d.success) toast('Perfil guardado en todos los módulos ✓','success');
    else toast(d.error || 'Error al guardar','error');
  });
}

/* ── TASA ── */
function actualizarTasa() {
  const bcv = document.getElementById('t-bcv'), par = document.getElementById('t-par');
  if (!Valida.comprobar([
    [bcv, Valida.numero(bcv.value, 0.0001, undefined, 'La tasa BCV')],
    [par, par.value.trim() ? Valida.numero(par.value, 0.0001, undefined, 'La tasa paralela') : null],
  ])) return;
  const fd = new FormData(); fd.append('action','actualizar_tasa');
  fd.append('tasa_bcv',document.getElementById('t-bcv').value);
  fd.append('tasa_paralelo',document.getElementById('t-par').value);
  fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (d.success) { toast('Tasa actualizada','success'); setTimeout(()=>location.reload(),900); }
    else toast(d.error || 'Error al guardar tasa','error');
  });
}

/* ── SERVICIO ── */
function abrirSvc(){
  document.getElementById('s-id').value='';
  ['nombre','precio'].forEach(f=>document.getElementById('s-'+f).value='');
  document.getElementById('s-horas').value=8;
  document.getElementById('svc-title').textContent='Nuevo Servicio';
  document.getElementById('modal-svc').style.display='flex';
}
function editarSvc(id){
  fetch('configuracion.php?get_svc='+id).then(r=>r.json()).then(d=>{
    document.getElementById('s-id').value=d.id;
    document.getElementById('s-nombre').value=d.nombre;
    document.getElementById('s-unidad').value=d.unidad_medida;
    document.getElementById('s-precio').value=d.precio_usd;
    document.getElementById('s-horas').value=d.tiempo_estimado_horas;
    document.getElementById('svc-title').textContent='Editar Servicio';
    document.getElementById('modal-svc').style.display='flex';
  });
}
function guardarSvc(){
  const $ = id => document.getElementById(id);
  const nom = $('s-nombre').value.trim();
  if (!Valida.comprobar([
    [$('s-nombre'), nom.length < 3 || nom.length > 100 ? 'El nombre debe tener entre 3 y 100 caracteres.' : null],
    [$('s-precio'), Valida.numero($('s-precio').value, 0.01, 9999, 'El precio')],
    [$('s-horas'),  Valida.numero($('s-horas').value, 0.5, 720, 'El tiempo estimado')],
  ], $('modal-svc'))) return;
  const fd=new FormData(), id=document.getElementById('s-id').value;
  fd.append('action','guardar_servicio'); if(id)fd.append('id',id);
  fd.append('nombre',document.getElementById('s-nombre').value);
  fd.append('unidad',document.getElementById('s-unidad').value);
  fd.append('precio',document.getElementById('s-precio').value);
  fd.append('horas',document.getElementById('s-horas').value);
  fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){document.getElementById('modal-svc').style.display='none';setTimeout(()=>location.reload(),700);}
    else toast('Error: '+(d.error||'?'),'error');
  });
}

function toggleSvc(id){
  const fd=new FormData(); fd.append('action','toggle_servicio'); fd.append('id',id);
  fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success) location.href='configuracion.php?tab=servicios'; else toast(d.error||'No se pudo cambiar','error');
  });
}

/* ── TIPOS DE PRENDA ── */
function enviarPrenda(id, nombre){
  const fd=new FormData(); fd.append('action','guardar_prenda'); fd.append('nombre',nombre);
  if(id) fd.append('id',id);
  return fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){ toast('Tipo de prenda guardado','success'); setTimeout(()=>location.href='configuracion.php?tab=prendas',600); }
    else toast(d.error||'No se pudo guardar','error');
  });
}
function guardarPrenda(){
  const inp=document.getElementById('tp-nuevo');
  const v=inp.value.trim();
  if(!Valida.comprobar([[inp, v.length<2 || v.length>60 ? 'Escribe entre 2 y 60 caracteres.' : null]])) return;
  enviarPrenda(0, v);
}
function renombrarPrenda(id, btn){
  const nuevo = prompt('Nuevo nombre para el tipo de prenda:', btn.dataset.nombre);
  if(nuevo === null) return;
  const v = nuevo.trim().replace(/\s+/g,' ');
  if(v.length < 2 || v.length > 60){ toast('El nombre debe tener entre 2 y 60 caracteres','error'); return; }
  if(v !== btn.dataset.nombre) enviarPrenda(id, v);
}
function togglePrenda(id){
  const fd=new FormData(); fd.append('action','toggle_prenda'); fd.append('id',id);
  fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success) location.href='configuracion.php?tab=prendas'; else toast(d.error||'No se pudo cambiar','error');
  });
}

/* ── RESPALDOS ── */
function crearRespaldo(){
  const btn=document.getElementById('btn-respaldo');
  btn.disabled=true; btn.textContent='Generando…';
  const fd=new FormData(); fd.append('action','crear_respaldo');
  fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      toast('Respaldo creado. Iniciando descarga…','success');
      window.location.href='configuracion.php?descargar_respaldo='+encodeURIComponent(d.nombre);
      setTimeout(()=>location.href='configuracion.php?tab=respaldo',1500);
    } else { toast(d.error||'No se pudo crear','error'); btn.disabled=false; btn.textContent='Crear respaldo ahora'; }
  }).catch(()=>{ toast('Error de conexión','error'); btn.disabled=false; btn.textContent='Crear respaldo ahora'; });
}
let archivoRespaldo=null;
function archivoElegido(f){
  const txt=document.getElementById('bk-drop-txt'), btn=document.getElementById('btn-restaurar-archivo');
  archivoRespaldo=null; btn.disabled=true;
  if(!f) return;
  if(!/\.sql$/i.test(f.name)){ toast('El archivo debe ser .sql','error'); txt.textContent='Seleccionar archivo .sql (máx. 50 MB)'; return; }
  if(f.size > 50*1024*1024){ toast('El archivo supera 50 MB','error'); return; }
  archivoRespaldo=f; btn.disabled=false;
  txt.textContent='📄 '+f.name+' ('+(f.size/1024).toFixed(1)+' KB)';
}
(function(){
  const drop=document.getElementById('bk-drop');
  ['dragenter','dragover'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.add('over');}));
  ['dragleave','drop'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.remove('over');}));
  drop.addEventListener('drop',e=>archivoElegido(e.dataTransfer.files[0]));
})();
function pedirRestauracion(nombre){
  const origen = nombre ? 'el respaldo «'+nombre+'»' : 'el archivo «'+(archivoRespaldo&&archivoRespaldo.name)+'»';
  if(!confirm('⚠ ATENCIÓN\n\nSe reemplazarán TODOS los datos actuales por '+origen+'.\nAntes se creará un respaldo de seguridad automático.\n\n¿Deseas continuar?')) return;
  const pass = prompt('Para confirmar, escribe tu contraseña de administrador:');
  if(!pass) return;
  const fd=new FormData(); fd.append('action','restaurar_respaldo'); fd.append('password',pass);
  if(nombre) fd.append('nombre',nombre); else fd.append('archivo',archivoRespaldo);
  toast('Restaurando… no cierres esta ventana','warning');
  document.body.style.cursor='wait';
  fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    document.body.style.cursor='';
    if(d.success){
      alert('✓ Restauración completada ('+d.sentencias+' sentencias).\nRespaldo de seguridad: '+d.seguridad);
      location.href = d.relogin ? 'login.php' : 'configuracion.php?tab=respaldo';
    } else toast(d.error||'No se pudo restaurar','error');
  }).catch(()=>{ document.body.style.cursor=''; toast('Error de conexión','error'); });
}
function eliminarRespaldo(nombre){
  if(!confirm('¿Eliminar el respaldo «'+nombre+'» del servidor? Esta acción no se puede deshacer.')) return;
  const fd=new FormData(); fd.append('action','eliminar_respaldo'); fd.append('nombre',nombre);
  fetch('configuracion.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){ toast('Respaldo eliminado','success'); setTimeout(()=>location.href='configuracion.php?tab=respaldo',500); }
    else toast('No se pudo eliminar','error');
  });
}

/* ── TOAST ── */
function toast(msg, type='success') {
  const icons = {success:'✓',error:'✕',warning:'⚠'};
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = `<span style="font-size:14px;">${icons[type]||'ℹ'}</span> ${Valida.esc(msg)}`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 3000);
}

/* ── INICIALIZAR UI ── */
function refrescarUI() {
  const p = ThemeEngine.getPrefs();

  /* Modos */
  document.getElementById('modo-dark').classList.toggle('selected',  p.modo==='dark');
  document.getElementById('modo-light').classList.toggle('selected', p.modo==='light');

  /* Acentos */
  renderAcentos();

  /* Fuentes */
  renderFuentes();

  /* Tamaño */
  document.querySelectorAll('.tamano-btn').forEach(b => b.classList.toggle('selected', b.dataset.val===p.tamano));

  /* Radio */
  document.querySelectorAll('.radio-btn').forEach(b => b.classList.toggle('selected', b.dataset.val===p.radioUI));

  /* Toggles */
  document.getElementById('toggle-sidebar').classList.toggle('on', !!p.sidebarCompacto);

  /* Foto localStorage */
  const fotoLS = localStorage.getItem(ThemeEngine.clave('ls_foto_perfil'));
  if (fotoLS) {
    const img = document.getElementById('avatar-preview');
    const ph  = document.getElementById('avatar-placeholder');
    if (img) { img.src = fotoLS; img.style.display = 'block'; }
    if (ph)  ph.style.display = 'none';
    const btn = document.getElementById('btn-eliminar-foto');
    if (btn) btn.style.display = '';
  }

  /* Nombre y cargo desde localStorage */
  const perfilLS = JSON.parse(localStorage.getItem(ThemeEngine.clave('ls_perfil')) || '{}');
  if (perfilLS.nombre) {
    const inp = document.getElementById('p-nombre');
    if (inp) { inp.value = perfilLS.nombre; document.getElementById('display-nombre').textContent = perfilLS.nombre; }
  }
  if (perfilLS.cargo) {
    const inp = document.getElementById('p-cargo');
    if (inp) { inp.value = perfilLS.cargo; document.getElementById('display-cargo').textContent = perfilLS.cargo; }
  }

  /* Sincronizar sidebar en esta misma página */
  ThemeEngine.aplicarPerfil();
}

refrescarUI();
</script>
</body>
</html>

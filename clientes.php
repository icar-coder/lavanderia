<?php
require_once __DIR__ . '/config/init.php';
requireAuth('clientes');

// --- AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $a = entrada($_POST, 'action');
    if ($a === 'crear' || $a === 'editar') {
        $id  = $a === 'editar' ? (int) entrada($_POST, 'id') : 0;
        if ($a === 'editar') {
            $chk = getDB()->prepare("SELECT id FROM clientes WHERE id = ?");
            $chk->execute([$id]);
            if (!$chk->fetch()) { echo json_encode(['success'=>false,'error'=>'El cliente no existe.']); exit; }
        }
        $val = validarDatosCliente($_POST, $id);
        if (!$val['ok']) {
            echo json_encode(['success'=>false,'error'=>$val['error'],'campo'=>$val['campo']]);
            exit;
        }
        $data = $val['data'];
        try {
            if ($a === 'crear') {
                $id = crearCliente($data);
                echo json_encode(['success'=>true,'id'=>$id]);
            } else {
                $stmt = getDB()->prepare(
                    "UPDATE clientes SET nombres=:nombres, apellidos=:apellidos, nombre=:nombre, cedula_rif=:cedula_rif,
                     telefono=:telefono, direccion=:direccion, email=:email WHERE id=:id"
                );
                $stmt->execute(array_merge($data, [':id'=>$id]));
                echo json_encode(['success'=>true]);
            }
        } catch(Exception $e){
            error_log('clientes.php: ' . $e->getMessage());
            echo json_encode(['success'=>false,'error'=>'No se pudo guardar el cliente. Verifique los datos.']);
        }
        exit;
    }
    if ($a === 'toggle') {
        $upd = getDB()->prepare("UPDATE clientes SET activo = NOT activo WHERE id=?");
        $upd->execute([(int) entrada($_POST, 'id')]);
        echo json_encode($upd->rowCount() ? ['success'=>true] : ['success'=>false,'error'=>'El cliente no existe.']);
        exit;
    }
    echo json_encode(['success'=>false,'error'=>'Acción desconocida.']);
    exit;
}
if (isset($_GET['get_id'])) {
    header('Content-Type: application/json');
    $c = getDB()->prepare("SELECT * FROM clientes WHERE id=?");
    $c->execute([(int) entrada($_GET, 'get_id')]);
    echo json_encode($c->fetch() ?: []);
    exit;
}

$busqueda   = mb_substr(trim(entrada($_GET, 'q')), 0, 60);
$pagina     = max(1, (int) entrada($_GET, 'p', '1'));
$clientes   = listarClientes($busqueda, $pagina);
$totalRows  = (int) getDB()->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$activos    = (int) getDB()->query("SELECT COUNT(*) FROM clientes WHERE activo=1")->fetchColumn();
if ($busqueda) {
    $like = '%' . escaparLike($busqueda) . '%';
    $cnt  = getDB()->prepare("SELECT COUNT(*) FROM clientes WHERE nombre LIKE ? OR cedula_rif LIKE ? OR telefono LIKE ?");
    $cnt->execute([$like, $like, $like]);
    $filtrados = (int) $cnt->fetchColumn();
} else {
    $filtrados = $totalRows;
}
$totalPages = (int) ceil($filtrados / 20);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Clientes — Lavandería Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    /* KPIs superiores */
    .cli-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:18px; }
    .cli-kpi  { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:16px 18px; display:flex; align-items:center; gap:14px; }
    .cli-kpi__icon { width:38px; height:38px; border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .cli-kpi__val  { font-family:var(--font-display); font-size:24px; font-weight:800; color:var(--text); line-height:1; }
    .cli-kpi__lbl  { font-size:11px; color:var(--text-hint); margin-top:3px; text-transform:uppercase; letter-spacing:.07em; }

    /* Barra de búsqueda mejorada */
    .search-bar { display:flex; gap:10px; align-items:center; }
    .search-wrap { position:relative; flex:1; max-width:440px; }
    .search-wrap svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-hint); pointer-events:none; }
    .search-input { width:100%; padding:9px 12px 9px 36px; background:var(--bg-surface2); border:1px solid var(--border-md); border-radius:var(--radius-md); color:var(--text); font-size:13.5px; outline:none; transition:border-color .15s, box-shadow .15s; font-family:var(--font-body); }
    .search-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .search-input::placeholder { color:var(--text-hint); }

    /* Tabla mejorada */
    .ctbl { width:100%; border-collapse:collapse; }
    .ctbl thead th { padding:10px 14px; font-size:10px; font-weight:600; letter-spacing:.09em; text-transform:uppercase; color:var(--text-hint); border-bottom:1px solid var(--border); text-align:left; white-space:nowrap; }
    .ctbl tbody td { padding:13px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .ctbl tbody tr:last-child td { border-bottom:none; }
    .ctbl tbody tr:hover td { background:var(--bg-surface2); }

    /* Avatar letras */
    .cli-av { width:34px; height:34px; border-radius:50%; background:var(--accent-bg); border:1.5px solid var(--accent-border); display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:12px; font-weight:800; color:var(--accent-light); flex-shrink:0; }

    /* Nombre + cédula juntos */
    .cli-id { display:flex; align-items:center; gap:10px; }
    .cli-nombre { font-size:13.5px; font-weight:500; color:var(--text); }
    .cli-ced    { font-size:11.5px; color:var(--text-hint); margin-top:1px; font-family:var(--font-mono); }

    /* WA link */
    .wa-link { display:inline-flex; align-items:center; gap:5px; color:var(--green); font-size:12.5px; transition:opacity .15s; }
    .wa-link:hover { opacity:.75; }

    /* Puntos */
    .pts-badge { display:inline-flex; align-items:center; gap:4px; font-size:11.5px; font-weight:600; padding:3px 9px; border-radius:20px; }
    .pts-badge.con  { background:var(--amber-bg); color:var(--amber); border:1px solid var(--amber-border); }
    .pts-badge.sin  { background:var(--bg-surface3); color:var(--text-hint); }

    /* Estado badge */
    .est-badge { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; }
    .est-badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .est-badge.on  { background:var(--green-bg); color:var(--green); border:1px solid var(--green-border); }
    .est-badge.off { background:var(--red-bg);   color:var(--red);   border:1px solid var(--red-border);   }

    /* Acciones */
    .act-btns { display:flex; gap:6px; }
    .act-btn  { width:30px; height:30px; border-radius:var(--radius-sm); border:1px solid var(--border); background:transparent; color:var(--text-hint); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; }
    .act-btn:hover { border-color:var(--accent-border); color:var(--accent-light); background:var(--accent-bg); }
    .act-btn.danger:hover { border-color:var(--red-border); color:var(--red); background:var(--red-bg); }
    .act-btn.success:hover { border-color:var(--green-border); color:var(--green); background:var(--green-bg); }

    /* Modal mejorado */
    .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.65); backdrop-filter:blur(4px); z-index:500; display:none; align-items:center; justify-content:center; padding:20px; }
    .modal-box { background:var(--bg-surface); border:1px solid var(--border-md); border-radius:var(--radius-xl); width:100%; max-width:520px; overflow:hidden; animation:mIn .2s ease; }
    @keyframes mIn { from{opacity:0;transform:scale(.97) translateY(8px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid var(--border); }
    .modal-ttl { font-family:var(--font-display); font-size:17px; font-weight:700; color:var(--text); }
    .modal-close-btn { width:28px; height:28px; border-radius:var(--radius-sm); border:1px solid var(--border); background:transparent; color:var(--text-hint); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; }
    .modal-close-btn:hover { border-color:var(--red-border); color:var(--red); background:var(--red-bg); }
    .modal-body { padding:20px 24px; }
    .modal-ftr  { padding:14px 24px 20px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border); }

    #toast-container { position:fixed; bottom:24px; right:24px; z-index:1000; display:flex; flex-direction:column; gap:8px; }
    .toast { display:flex; align-items:center; gap:10px; padding:11px 16px; background:var(--bg-surface2); border:1px solid var(--border-md); border-radius:var(--radius-md); font-size:13px; color:var(--text); min-width:260px; animation:sfr .2s ease; box-shadow:0 4px 20px rgba(0,0,0,.4); }
    .toast.success { border-color:var(--green-border); }
    .toast.error   { border-color:var(--red-border);   }
    @keyframes sfr { from{opacity:0;transform:translateX(14px)} to{opacity:1;transform:translateX(0)} }
  </style>
</head>
<body>
<div class="layout">
  <?php partial('sidebar'); ?>
  <main class="main-content">

    <div class="topbar">
      <div>
        <h1 class="page-title">Clientes</h1>
        <p class="page-sub"><?= $totalRows ?> registros totales</p>
      </div>
      <div class="topbar-actions">
        <?php if (rolActual() === 'admin'): ?>
        <a href="clientes_pdf.php?orden=nombre" target="_blank" class="btn btn-secondary" data-tip="Descargar el listado de clientes en PDF" data-tip-pos="bottom">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          PDF
        </a>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="abrirModal()" data-tip="Registrar un cliente nuevo" data-tip-pos="bottom">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuevo Cliente
        </button>
      </div>
    </div>

    <div class="content-area">

      <!-- KPIs -->
      <div class="cli-kpis">
        <div class="cli-kpi">
          <div class="cli-kpi__icon" style="background:var(--accent-bg);border:1px solid var(--accent-border);color:var(--accent-light);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div>
            <p class="cli-kpi__val"><?= $totalRows ?></p>
            <p class="cli-kpi__lbl">Total clientes</p>
          </div>
        </div>
        <div class="cli-kpi">
          <div class="cli-kpi__icon" style="background:var(--green-bg);border:1px solid var(--green-border);color:var(--green);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <div>
            <p class="cli-kpi__val"><?= $activos ?></p>
            <p class="cli-kpi__lbl">Activos</p>
          </div>
        </div>
        <div class="cli-kpi">
          <div class="cli-kpi__icon" style="background:var(--red-bg);border:1px solid var(--red-border);color:var(--red);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          </div>
          <div>
            <p class="cli-kpi__val"><?= $totalRows - $activos ?></p>
            <p class="cli-kpi__lbl">Inactivos</p>
          </div>
        </div>
      </div>

      <!-- Búsqueda -->
      <div class="card" style="margin-bottom:16px;padding:14px 20px;">
        <form method="get" class="search-bar">
          <div class="search-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input name="q" value="<?= h($busqueda) ?>" maxlength="60" class="search-input" placeholder="Buscar por nombre, cédula o teléfono…" autofocus>
          </div>
          <button type="submit" class="btn btn-secondary">Buscar</button>
          <?php if($busqueda): ?>
          <a href="clientes.php" class="btn btn-secondary" data-tip="Quitar el filtro de búsqueda">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Limpiar
          </a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Tabla -->
      <div class="card" style="padding:0;overflow:hidden;">
        <table class="ctbl">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Cédula / RIF</th>
              <th>Teléfono</th>
              <th>Puntos <span class="help-dot" tabindex="0" data-tip="Puntos de fidelidad acumulados por el cliente">?</span></th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($clientes)): ?>
            <tr><td colspan="6" style="padding:48px;text-align:center;">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="color:var(--text-hint);margin:0 auto 10px;display:block;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <p style="color:var(--text-hint);font-size:13.5px;">No se encontraron clientes</p>
              <?php if($busqueda): ?>
              <p style="color:var(--text-hint);font-size:12px;margin-top:4px;">Prueba con otra búsqueda</p>
              <?php endif; ?>
            </td></tr>
            <?php else: foreach($clientes as $c):
              $inicial = mb_strtoupper(mb_substr($c['nombre'], 0, 1));
            ?>
            <tr>
              <td>
                <div class="cli-id">
                  <div class="cli-av"><?= $inicial ?></div>
                  <div>
                    <p class="cli-nombre"><?= h($c['nombre']) ?></p>
                    <?php if($c['email']): ?>
                    <p class="cli-ced"><?= h($c['email']) ?></p>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="cli-ced" style="font-size:13px;"><?= h($c['cedula_rif']) ?></td>
              <td>
                <a href="https://wa.me/<?= telefonoWhatsApp($c['telefono']) ?>" target="_blank" rel="noopener" class="wa-link" data-tip="Abrir chat de WhatsApp">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.122 1.535 5.849L.057 23.776c-.073.297.021.61.247.818.226.208.545.288.839.212l6.144-1.596A11.94 11.94 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>
                  <?= h($c['telefono']) ?>
                </a>
              </td>
              <td>
                <span class="pts-badge <?= $c['puntos_acumulados'] > 0 ? 'con' : 'sin' ?>">
                  <?php if($c['puntos_acumulados'] > 0): ?>
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  <?php endif; ?>
                  <?= $c['puntos_acumulados'] ?> pts
                </span>
              </td>
              <td>
                <span class="est-badge <?= $c['activo'] ? 'on' : 'off' ?>">
                  <?= $c['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
              </td>
              <td>
                <div class="act-btns">
                  <button class="act-btn" onclick="editarCliente(<?= $c['id'] ?>)" data-tip="Editar datos del cliente">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  <button class="act-btn <?= $c['activo'] ? 'danger' : 'success' ?>"
                          onclick="toggleCliente(<?= $c['id'] ?>,<?= $c['activo'] ?>)"
                          data-tip="<?= $c['activo'] ? 'Desactivar (no aparecerá al crear órdenes)' : 'Reactivar cliente' ?>">
                    <?php if($c['activo']): ?>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    <?php else: ?>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php endif; ?>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>

        <?php if($totalPages > 1): ?>
        <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:center;gap:4px;">
          <?php for($i=1;$i<=$totalPages;$i++): ?>
          <a href="?q=<?= urlencode($busqueda) ?>&p=<?= $i ?>" class="page-btn <?= $pagina==$i?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<!-- MODAL -->
<div id="modal" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2 class="modal-ttl" id="modal-title">Nuevo Cliente</h2>
      <button class="modal-close-btn" onclick="cerrarModal()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="c-id">
      <div class="form-row form-row-2" style="margin-bottom:14px;">
        <div class="form-group">
          <label class="form-label" for="c-doc-num">Documento de identidad <span class="req">*</span>
            <span class="help-dot" tabindex="0" data-tip="V: venezolano · E: extranjero residente · P: pasaporte · J/G: RIF de empresa o ente público. No se puede repetir entre clientes.">?</span>
          </label>
          <div class="doc-group" id="c-doc">
            <select class="form-control doc-tipo" aria-label="Tipo de documento">
              <?php foreach (TIPOS_DOCUMENTO as $k => $lbl): ?>
              <option value="<?= $k ?>" title="<?= h($lbl) ?>"><?= $k ?></option>
              <?php endforeach; ?>
            </select>
            <input id="c-doc-num" class="form-control doc-num" placeholder="12345678" maxlength="8" inputmode="numeric" autocomplete="off">
          </div>
          <span class="form-hint doc-hint">Venezolano: 6 a 8 dígitos</span>
        </div>
        <div class="form-group">
          <label class="form-label" for="c-telefono">Teléfono (WhatsApp) <span class="req">*</span>
            <span class="help-dot" tabindex="0" data-tip="Número donde el cliente recibe los avisos de su orden por WhatsApp. Venezuela: 11 dígitos (04XX…). Extranjero: + código de país.">?</span>
          </label>
          <input id="c-telefono" class="form-control" placeholder="04261234567" inputmode="tel" maxlength="16" data-mask="phone">
          <span class="form-hint">Ej: 04261234567 · Extranjero: +573001234567</span>
        </div>
      </div>
      <div class="form-row form-row-2" style="margin-bottom:14px;">
        <div class="form-group">
          <label class="form-label" for="c-nombres"><span class="lbl-txt">Nombres</span> <span class="req">*</span>
            <span class="help-dot" tabindex="0" data-tip="Primer y segundo nombre del cliente. Si el documento es un RIF J o G, escriba aquí la razón social de la empresa.">?</span>
          </label>
          <input id="c-nombres" class="form-control" placeholder="María José" data-ph="María José" maxlength="60" data-mask="name" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label" for="c-apellidos">Apellidos <span class="req">*</span>
            <span class="help-dot" tabindex="0" data-tip="Primer y segundo apellido. No aplica para empresas (RIF J/G).">?</span>
          </label>
          <input id="c-apellidos" class="form-control" placeholder="García Pérez" maxlength="60" data-mask="name" autocomplete="off">
        </div>
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label class="form-label" for="c-email">Email
          <span class="help-dot" tabindex="0" data-tip="Opcional. Sirve como contacto alternativo al WhatsApp.">?</span>
        </label>
        <input id="c-email" type="email" class="form-control" placeholder="correo@ejemplo.com" maxlength="100">
      </div>
      <div class="form-group">
        <label class="form-label" for="c-direccion">Dirección
          <span class="help-dot" tabindex="0" data-tip="Opcional. Útil si el cliente solicita retiro o entrega a domicilio.">?</span>
        </label>
        <textarea id="c-direccion" class="form-control" rows="2" maxlength="255" placeholder="Calle, sector, referencias…"></textarea>
      </div>
    </div>
    <div class="modal-ftr">
      <button class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarCliente()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Guardar Cliente
      </button>
    </div>
  </div>
</div>

<div id="toast-container"></div>
<script>
const docGrupo = document.getElementById('c-doc');
const ajustarNombre = () => Valida.ajustarNombrePorDoc(docGrupo, document.getElementById('c-nombres'), document.getElementById('c-apellidos'));
docGrupo.querySelector('.doc-tipo').addEventListener('change', ajustarNombre);

function abrirModal(clear=true){
  if(clear){
    document.getElementById('c-id').value='';
    document.getElementById('modal-title').textContent='Nuevo Cliente';
    ['nombres','apellidos','doc-num','telefono','email','direccion'].forEach(f=>{ const el=document.getElementById('c-'+f); if(el) el.value=''; });
    Valida.cargarDoc(docGrupo, 'V-');
  }
  Valida.limpiarTodo(document.getElementById('modal'));
  document.getElementById('modal').style.display='flex';
  setTimeout(()=>document.getElementById('c-doc-num').focus(),80);
}
function cerrarModal(){ document.getElementById('modal').style.display='none'; }
document.getElementById('modal').addEventListener('click',e=>{ if(e.target===e.currentTarget) cerrarModal(); });
document.addEventListener('keydown',e=>{ if(e.key==='Escape') cerrarModal(); });

function editarCliente(id){
  fetch('clientes.php?get_id='+id).then(r=>r.json()).then(d=>{
    document.getElementById('c-id').value=d.id;
    Valida.cargarDoc(docGrupo, d.cedula_rif||'');
    document.getElementById('c-nombres').value=d.nombres||d.nombre||'';
    document.getElementById('c-apellidos').value=d.apellidos||'';
    document.getElementById('c-telefono').value=d.telefono||'';
    document.getElementById('c-email').value=d.email||'';
    document.getElementById('c-direccion').value=d.direccion||'';
    document.getElementById('modal-title').textContent='Editar Cliente';
    abrirModal(false);
  });
}

function guardarCliente(){
  const $ = id => document.getElementById(id);
  const doc = Valida.leerDoc(docGrupo);
  const empresa = Valida.esEmpresa(doc.tipo);
  const nom = $('c-nombres').value.trim().replace(/\s+/g,' ');
  const ape = empresa ? '' : $('c-apellidos').value.trim().replace(/\s+/g,' ');
  const t  = $('c-telefono').value.trim();
  const em = $('c-email').value.trim();
  const ok = Valida.comprobar([
    [$('c-doc-num'),  Valida.documento(doc.tipo, doc.num)],
    [$('c-telefono'), Valida.telefono(t)],
    [$('c-nombres'),  Valida.nombre(nom, 2, empresa ? 'La razón social' : 'El nombre')],
    [$('c-apellidos'),empresa ? null : Valida.nombre(ape, 2, 'El apellido')],
    [$('c-email'),    Valida.email(em)],
    [$('c-direccion'), (d => d && d.length < 5 ? 'La dirección es muy corta (mínimo 5 caracteres) o déjela vacía.' : null)($('c-direccion').value.trim())],
  ], $('modal'));
  if(!ok) return;

  const fd=new FormData();
  const id=$('c-id').value;
  fd.append('action',id?'editar':'crear');
  if(id) fd.append('id',id);
  fd.append('nombres',nom); fd.append('apellidos',ape); fd.append('doc_tipo',doc.tipo); fd.append('doc_numero',doc.num);
  fd.append('telefono',t); fd.append('email',em);
  fd.append('direccion',$('c-direccion').value.trim());
  fetch('clientes.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){ toast('Cliente guardado','success'); cerrarModal(); setTimeout(()=>location.reload(),800); }
    else {
      const campos={nombres:'c-nombres',apellidos:'c-apellidos',documento:'c-doc-num',telefono:'c-telefono',email:'c-email',direccion:'c-direccion'};
      if(d.campo && campos[d.campo]) Valida.marcar($(campos[d.campo]), d.error);
      toast(d.error||'No se pudo guardar','error');
    }
  }).catch(()=>toast('Error de conexión con el servidor','error'));
}

function toggleCliente(id,activo){
  if(!confirm(activo?'¿Desactivar este cliente?':'¿Activar este cliente?')) return;
  const fd=new FormData(); fd.append('action','toggle'); fd.append('id',id);
  fetch('clientes.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success) location.reload(); else toast(d.error||'No se pudo cambiar el estado','error');
  });
}

function toast(msg,type='success'){
  const el=document.createElement('div');
  el.className='toast '+type;
  el.textContent=msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(()=>el.remove(),3500);
}
</script>
</body>
</html>

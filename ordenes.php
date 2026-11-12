<?php
require_once __DIR__ . '/config/init.php';
requireAuth('ordenes');


/* ── AJAX: crear orden ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'crear_orden') {
    header('Content-Type: application/json');
    try {
        $db = getDB();

        // Cliente
        $clienteId = (int) entrada($_POST, 'cliente_id');
        $chk = $db->prepare("SELECT id FROM clientes WHERE id = ? AND activo = 1");
        $chk->execute([$clienteId]);
        if (!$chk->fetch()) throw new Exception('Seleccione un cliente válido y activo.');

        // Método de pago
        $metodo  = entrada($_POST, 'metodo_pago');
        if (!array_key_exists($metodo, METODOS_PAGO)) throw new Exception('Método de pago inválido.');

        $notas = limpiarTexto(entrada($_POST, 'notas'));
        if (mb_strlen($notas) > 500) throw new Exception('Las notas no pueden superar 500 caracteres.');

        // Servicios (el precio puede ajustarse manualmente, pero se valida)
        $svcRows = $db->query("SELECT id, nombre, tiempo_estimado_horas FROM servicios WHERE activo = 1")->fetchAll();
        $svcs    = array_column($svcRows, null, 'id');
        $detalles = [];
        $totalUSD = 0.0;
        $horasEst = 0.0;
        $cantidades = entradaLista($_POST, 'cantidades');
        $precios    = entradaLista($_POST, 'precios');
        $vistos     = [];
        foreach (entradaLista($_POST, 'servicios') as $i => $svcId) {
            $svcId = is_scalar($svcId) ? (int) $svcId : 0;
            if (!$svcId) continue;
            if (!isset($svcs[$svcId])) throw new Exception('Uno de los servicios seleccionados no existe o está inactivo.');
            $nombreSvc = $svcs[$svcId]['nombre'];
            if (isset($vistos[$svcId])) throw new Exception("El servicio \"{$nombreSvc}\" está repetido; ajusta la cantidad en una sola línea.");
            $vistos[$svcId] = true;
            $cant = numeroEnRango(is_scalar($cantidades[$i] ?? null) ? $cantidades[$i] : '', 0.1, 999);
            if ($cant === null) throw new Exception("Cantidad inválida en \"{$nombreSvc}\" (entre 0.1 y 999).");
            $prec = numeroEnRango(is_scalar($precios[$i] ?? null) ? $precios[$i] : '', 0, 9999);
            if ($prec === null) throw new Exception("Precio inválido en \"{$nombreSvc}\" (entre 0 y 9999).");
            $detalles[] = ['servicio_id' => $svcId, 'cantidad' => $cant, 'precio_usd' => $prec];
            $totalUSD += $cant * $prec;
            $horasEst  = max($horasEst, (float) $svcs[$svcId]['tiempo_estimado_horas']);
        }
        if (empty($detalles)) throw new Exception('Agrega al menos un servicio.');
        if (count($detalles) > 30) throw new Exception('Demasiadas líneas de servicio en una sola orden.');
        if (round($totalUSD, 2) <= 0) throw new Exception('El total de la orden debe ser mayor que cero.');

        // Prendas recibidas
        $tipos   = array_column(listarTiposPrenda(), 'nombre', 'id');
        $prendas = [];
        $prendaCant = entradaLista($_POST, 'prenda_cant');
        $prendaObs  = entradaLista($_POST, 'prenda_obs');
        foreach (entradaLista($_POST, 'prenda_tipo') as $i => $tipoId) {
            $tipoId = is_scalar($tipoId) ? (int) $tipoId : 0;
            if (!$tipoId) continue;
            if (!isset($tipos[$tipoId])) throw new Exception('Tipo de prenda inválido.');
            $cant = is_scalar($prendaCant[$i] ?? null) ? trim((string) $prendaCant[$i]) : '';
            if (!ctype_digit((string) $cant) || (int) $cant < 1 || (int) $cant > 999)
                throw new Exception("Cantidad de \"{$tipos[$tipoId]}\" inválida (entero entre 1 y 999).");
            $obs = limpiarTexto(is_scalar($prendaObs[$i] ?? null) ? (string) $prendaObs[$i] : '');
            if (mb_strlen($obs) > 150) throw new Exception('La observación de una prenda no puede superar 150 caracteres.');
            $prendas[] = ['tipo_prenda_id' => $tipoId, 'descripcion' => $tipos[$tipoId],
                          'cantidad' => (int) $cant, 'observacion' => $obs];
        }
        if (count($prendas) > 60) throw new Exception('Demasiadas líneas de prendas en una sola orden.');

        $tasa     = obtenerTasaHoy();
        $totalUSD = round($totalUSD, 2);
        $horasEst = $horasEst > 0 ? $horasEst : 8;
        $fechaPromesa = date('Y-m-d H:i:s', time() + (int) round($horasEst * 3600));

        $datos = [
            ':cliente_id'     => $clienteId,
            ':fecha_promesa'  => $fechaPromesa,
            ':total_usd'      => $totalUSD,
            ':total_bs'       => convertirUSDaBS($totalUSD, (float) $tasa['tasa_bcv']),
            ':tasa_cambio_id' => isset($tasa['id']) ? (int) $tasa['id'] : null,
            ':metodo_pago'    => $metodo,
            ':notas'          => $notas,
        ];
        $ordenId = crearOrden($datos, $detalles, $prendas);
        echo json_encode(['success' => true, 'orden_id' => $ordenId, 'total_usd' => $totalUSD]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* ── AJAX: cliente nuevo rápido ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'crear_cliente_rapido') {
    header('Content-Type: application/json');
    $val = validarDatosCliente($_POST);
    if (!$val['ok']) {
        echo json_encode(['success' => false, 'error' => $val['error'], 'campo' => $val['campo']]);
        exit;
    }
    try {
        $id = crearCliente($val['data']);
        $s  = getDB()->prepare("SELECT * FROM clientes WHERE id=?");
        $s->execute([$id]);
        echo json_encode(['success' => true, 'cliente' => $s->fetch()]);
    } catch (Exception $e) {
        error_log('ordenes.php cliente rápido: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'No se pudo registrar el cliente.']);
    }
    exit;
}

/* ── AJAX: buscar cliente ── */
if (isset($_GET['buscar_cedula'])) {
    header('Content-Type: application/json');
    $q    = mb_substr(trim(entrada($_GET, 'buscar_cedula')), 0, 60);
    if ($q === '') { echo json_encode([]); exit; }
    $like = '%' . escaparLike($q) . '%';
    $stmt = getDB()->prepare(
        "SELECT * FROM clientes WHERE (cedula_rif LIKE ? OR nombre LIKE ? OR telefono LIKE ?) AND activo=1 LIMIT 8"
    );
    $stmt->execute([$like, $like, $like]);
    echo json_encode($stmt->fetchAll());
    exit;
}

$tasa      = obtenerTasaHoy();
$servicios = getDB()->query("SELECT * FROM servicios WHERE activo=1 ORDER BY nombre")->fetchAll();
$tiposPrenda = listarTiposPrenda();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Nueva Orden — Lavanderia Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    /* ── Layout ── */
    .orden-layout { display:grid; grid-template-columns:1fr 340px; gap:18px; align-items:start; }

    /* ── Buscador ── */
    .cliente-search-wrap { position:relative; }
    .search-inp-wrap { position:relative; }
    .search-inp-wrap svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-hint); pointer-events:none; }
    .cliente-search-wrap .form-control { padding-left:36px; }

    .cliente-dropdown { position:absolute; top:calc(100% + 6px); left:0; right:0; z-index:300; background:var(--bg-surface); border:1px solid var(--border-md); border-radius:var(--radius-md); overflow:hidden; box-shadow:0 12px 32px rgba(0,0,0,.5); display:none; }
    .cliente-dropdown.open { display:block; animation:ddIn .15s ease; }
    @keyframes ddIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }

    .cliente-option { display:flex; align-items:center; gap:12px; padding:10px 14px; cursor:pointer; transition:background var(--t); border-bottom:1px solid var(--border); }
    .cliente-option:last-child { border-bottom:none; }
    .cliente-option:hover { background:var(--bg-surface2); }
    .co-avatar { width:34px; height:34px; border-radius:50%; flex-shrink:0; background:var(--accent-bg); border:1px solid var(--accent-border); display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:13px; font-weight:700; color:var(--accent-light); }
    .co-nombre { font-size:13.5px; font-weight:500; color:var(--text); }
    .co-cedula { font-size:11.5px; color:var(--text-sub); margin-top:1px; }
    .cliente-no-res { padding:16px; font-size:13px; color:var(--text-sub); text-align:center; }

    /* Cliente seleccionado */
    .cliente-seleccionado { display:none; align-items:center; gap:12px; padding:13px 16px; background:var(--green-bg); border:1px solid var(--green-border); border-radius:var(--radius-md); margin-top:10px; animation:csIn .2s ease; }
    @keyframes csIn { from{opacity:0;transform:scale(.98)} to{opacity:1;transform:scale(1)} }
    .cliente-seleccionado.visible { display:flex; }
    .cs-avatar { width:40px; height:40px; border-radius:50%; background:var(--green-bg); border:2px solid var(--green-border); display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:15px; font-weight:800; color:var(--green); flex-shrink:0; }
    .cs-info { flex:1; }
    .cs-nombre { font-size:14px; font-weight:600; color:var(--text); }
    .cs-tel { font-size:12px; color:var(--text-sub); margin-top:2px; }
    .cs-clear { background:none; border:none; color:var(--text-hint); cursor:pointer; padding:4px; line-height:1; transition:color var(--t); border-radius:var(--radius-sm); }
    .cs-clear:hover { color:var(--red); }

    /* Panel nuevo cliente */
    .panel-nuevo { display:none; margin-top:12px; padding:16px; background:var(--bg-surface2); border-radius:var(--radius-md); border:1px solid var(--accent-border); }
    .panel-nuevo.visible { display:block; animation:ddIn .15s ease; }
    .panel-nuevo h4 { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--accent-light); margin-bottom:12px; display:flex; align-items:center; gap:6px; }

    /* Chips de servicios */
    .chips-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
    .svc-chip { padding:11px 12px; border:1px solid var(--border-md); border-radius:var(--radius-md); cursor:pointer; background:var(--bg-surface2); transition:all .15s; text-align:left; font-family:var(--font-body); position:relative; overflow:hidden; }
    .svc-chip::before { content:''; position:absolute; inset:0; background:var(--accent); opacity:0; transition:opacity .15s; }
    .svc-chip:hover { border-color:var(--accent-border); }
    .svc-chip:hover::before { opacity:.05; }
    .svc-chip.agregado { border-color:var(--green-border); background:var(--green-bg); }
    .svc-chip-ok { position:absolute; top:6px; right:8px; font-size:9px; color:var(--green); display:none; font-weight:700; letter-spacing:.04em; }
    .svc-chip.agregado .svc-chip-ok { display:block; }
    .svc-chip-nom { font-size:12px; font-weight:600; color:var(--text); display:block; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .svc-chip-prec { font-size:13px; color:var(--accent-light); font-weight:700; display:block; }
    .svc-chip-unit { font-size:10px; color:var(--text-hint); display:block; margin-top:1px; }

    /* Tabla detalle */
    .det-table { width:100%; border-collapse:collapse; }
    .det-table th { padding:9px 10px; font-size:10px; text-transform:uppercase; letter-spacing:.08em; color:var(--text-hint); font-weight:600; text-align:left; border-bottom:1px solid var(--border); }
    .det-table td { padding:8px 10px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .det-table tr:last-child td { border-bottom:none; }
    .btn-del { width:28px; height:28px; border-radius:50%; background:var(--red-bg); border:1px solid var(--red-border); color:var(--red); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; flex-shrink:0; }
    .btn-del:hover { background:rgba(248,113,113,.25); transform:scale(1.1); }
    .btn-add-ln { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:9px 14px; background:transparent; margin-top:8px; border:1.5px dashed var(--border-md); border-radius:var(--radius-sm); color:var(--text-hint); font-size:12.5px; cursor:pointer; font-family:var(--font-body); transition:all .15s; }
    .btn-add-ln:hover { border-color:var(--accent); color:var(--accent-light); background:var(--accent-bg); }
    .tabla-empty-msg { text-align:center; padding:28px; color:var(--text-hint); font-size:13px; }

    /* Panel totales */
    .total-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; }
    .total-row + .total-row { border-top:1px solid var(--border); }

    /* Total principal destacado */
    .total-principal { background:var(--accent-bg); border:1px solid var(--accent-border); border-radius:var(--radius-md); padding:14px 16px; margin:12px 0; text-align:center; }
    .total-principal .usd { font-family:var(--font-display); font-size:32px; font-weight:800; color:var(--accent-light); line-height:1; }
    .total-principal .bs  { font-size:13px; color:var(--amber); margin-top:4px; font-weight:500; }

    /* Método de pago visual */
    .metodo-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-top:8px; }
    .metodo-opt  { position:relative; }
    .metodo-opt input { position:absolute; opacity:0; }
    .metodo-opt label { display:flex; align-items:center; gap:6px; padding:8px 10px; border:1px solid var(--border-md); border-radius:var(--radius-sm); font-size:11.5px; color:var(--text-sub); cursor:pointer; transition:all .15s; font-family:var(--font-body); white-space:nowrap; }
    .metodo-opt input:checked + label { border-color:var(--accent-border); background:var(--accent-bg); color:var(--accent-light); font-weight:600; }
    .metodo-opt label:hover { border-color:var(--border-strong); color:var(--text); }

    /* Prendas */
    .prenda-chips { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px; }
    .prenda-chip { padding:5px 11px; border-radius:20px; border:1px solid var(--border-md); background:var(--bg-surface2); color:var(--text-sub); font-size:12px; cursor:pointer; font-family:var(--font-body); transition:all .15s; }
    .prenda-chip:hover { border-color:var(--accent-border); color:var(--accent-light); background:var(--accent-bg); }
    .prenda-chip b { color:var(--green); margin-left:4px; }
    .prenda-row { display:grid; grid-template-columns:1.3fr 90px 1.6fr 30px; gap:8px; align-items:center; margin-bottom:8px; }
    .prenda-row .form-control { font-size:12.5px; padding:7px 10px; }
    .qty-box { display:flex; align-items:stretch; }
    .qty-box button { width:26px; border:1px solid var(--border-md); background:var(--bg-surface3); color:var(--text-sub); cursor:pointer; font-size:14px; line-height:1; }
    .qty-box button:first-child { border-radius:var(--radius-sm) 0 0 var(--radius-sm); border-right:none; }
    .qty-box button:last-child  { border-radius:0 var(--radius-sm) var(--radius-sm) 0; border-left:none; }
    .qty-box button:hover { color:var(--text); background:var(--bg-surface2); }
    .qty-box input { border-radius:0 !important; text-align:center; padding:7px 4px !important; min-width:0; }
    .prenda-head { display:grid; grid-template-columns:1.3fr 90px 1.6fr 30px; gap:8px; font-size:10px; text-transform:uppercase; letter-spacing:.08em; color:var(--text-hint); font-weight:600; margin-bottom:6px; }
    @media(max-width:700px){ .prenda-row,.prenda-head { grid-template-columns:1fr 90px 30px; } .prenda-row .prenda-obs,.prenda-head .prenda-obs { display:none; } }

    @keyframes spin { to { transform:rotate(360deg) } }
    @media(max-width:1100px) { .orden-layout { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<div class="layout">
  <?php partial('sidebar'); ?>
  <main class="main-content">
    <div class="topbar">
      <div>
        <h1 class="page-title">Nueva Orden</h1>
        <div style="display:flex;align-items:center;gap:10px;margin-top:4px;">
          <p class="page-sub">Registro de servicio de lavandería</p>
          <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:2px 9px;border-radius:20px;background:var(--amber-bg);color:var(--amber);border:1px solid var(--amber-border);">
            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            Bs. <?= number_format((float)$tasa['tasa_bcv'],2,',','.') ?> / USD
          </span>
        </div>
      </div>
      <div class="topbar-actions">
        <a href="ayuda.php#manual-ordenes" class="btn btn-secondary" data-tip="Ver cómo registrar una orden paso a paso" data-tip-pos="bottom">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Ayuda
        </a>
        <a href="kanban.php" class="btn btn-secondary" data-tip="Ir al tablero de seguimiento de órdenes" data-tip-pos="bottom">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          Tablero
        </a>
      </div>
    </div>

    <div class="content-area">
      <div class="orden-layout">

        <!-- IZQUIERDA -->
        <div style="display:flex;flex-direction:column;gap:16px;">

          <!-- CLIENTE -->
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">1. Cliente</h2>
              <span class="help-dot" tabindex="0" data-tip="Busca por nombre, número de documento o teléfono. Si no existe, regístralo con «Nuevo cliente».">?</span>
            </div>

            <div class="cliente-search-wrap" id="search-wrap">
              <div class="input-group">
                <input type="text" id="inp-busq" class="form-control"
                       placeholder="Nombre, cedula o telefono..."
                       autocomplete="off"
                       oninput="onInput(this.value)"
                       onkeydown="if(event.key==='Enter')buscar();if(event.key==='Escape')cerrarDD();">
                <button type="button" class="btn btn-secondary" onclick="buscar()" data-tip="Buscar cliente">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
              </div>
              <div class="cliente-dropdown" id="cdd"></div>
            </div>

            <div class="cliente-seleccionado" id="cs">
              <div class="cs-avatar" id="cs-av">?</div>
              <div class="cs-info">
                <p class="cs-nombre" id="cs-nom">—</p>
                <p class="cs-tel" id="cs-tel">—</p>
              </div>
              <button class="cs-clear" onclick="limpiarCliente()" data-tip="Cambiar de cliente">✕</button>
            </div>
            <input type="hidden" id="cliente-id">

            <!-- Nuevo cliente -->
            <div class="panel-nuevo" id="panel-nuevo">
              <h4>Registrar nuevo cliente</h4>
              <div class="form-row form-row-2" style="margin-bottom:10px;">
                <div class="form-group">
                  <label class="form-label" for="nc-doc-num">Documento <span class="req">*</span>
                    <span class="help-dot" tabindex="0" data-tip="V: venezolano · E: extranjero residente · P: pasaporte · J/G: RIF">?</span>
                  </label>
                  <div class="doc-group" id="nc-doc">
                    <select class="form-control doc-tipo" aria-label="Tipo de documento">
                      <?php foreach (TIPOS_DOCUMENTO as $k => $lbl): ?>
                      <option value="<?= $k ?>" title="<?= h($lbl) ?>"><?= $k ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input id="nc-doc-num" class="form-control doc-num" placeholder="12345678" maxlength="8" inputmode="numeric" autocomplete="off">
                  </div>
                  <span class="form-hint doc-hint">Venezolano: 6 a 8 dígitos</span>
                </div>
                <div class="form-group">
                  <label class="form-label" for="nc-tel">Teléfono WhatsApp <span class="req">*</span>
                    <span class="help-dot" tabindex="0" data-tip="Aquí le llegarán los avisos de su orden. 11 dígitos (04XX…) o + código de país.">?</span>
                  </label>
                  <input id="nc-tel" class="form-control" placeholder="04262344033" inputmode="tel" maxlength="16" data-mask="phone">
                  <span class="form-hint">11 dígitos (04XX…) · Extranjero: + código de país</span>
                </div>
              </div>
              <div class="form-row form-row-2" style="margin-bottom:12px;">
                <div class="form-group">
                  <label class="form-label" for="nc-nom"><span class="lbl-txt">Nombres</span> <span class="req">*</span>
                    <span class="help-dot" tabindex="0" data-tip="Nombres del cliente. Con RIF J/G escriba la razón social.">?</span>
                  </label>
                  <input id="nc-nom" class="form-control" placeholder="Juan Carlos" data-ph="Juan Carlos" maxlength="60" data-mask="name" autocomplete="off">
                </div>
                <div class="form-group">
                  <label class="form-label" for="nc-ape">Apellidos <span class="req">*</span>
                    <span class="help-dot" tabindex="0" data-tip="Apellidos del cliente. No aplica para empresas.">?</span>
                  </label>
                  <input id="nc-ape" class="form-control" placeholder="Pérez Rojas" maxlength="60" data-mask="name" autocomplete="off">
                </div>
              </div>
              <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="registrarNuevo()">Guardar y usar</button>
                <button class="btn btn-secondary btn-sm" onclick="cerrarPanelNuevo()">Cancelar</button>
              </div>
            </div>

            <button id="btn-nuevo-cliente" class="btn btn-secondary" style="margin-top:10px;width:100%;" onclick="abrirPanelNuevo()">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Nuevo cliente
            </button>
          </div>

          <!-- CHIPS SERVICIOS -->
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">2. Servicios</h2>
              <span class="text-sub" style="font-size:11.5px;">Clic para agregar</span>
            </div>
            <div class="chips-grid">
              <?php foreach($servicios as $s): ?>
              <button class="svc-chip" id="chip-<?= $s['id'] ?>" data-tip="<?= h($s['nombre']) ?> · tiempo estimado <?= (float)$s['tiempo_estimado_horas'] ?> h"
                      onclick="agregarDesdeChip(<?= (int)$s['id'] ?>,<?= h(json_encode($s['nombre'])) ?>,<?= (float)$s['precio_usd'] ?>,<?= h(json_encode($s['unidad_medida'])) ?>)">
                <span class="svc-chip-ok">✓ Agregado</span>
                <span class="svc-chip-nom"><?= h($s['nombre']) ?></span>
                <span class="svc-chip-prec">$<?= number_format((float)$s['precio_usd'],2) ?></span>
                <span class="svc-chip-unit">/ <?= $s['unidad_medida'] ?></span>
              </button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- DETALLE -->
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">3. Detalle de la orden <span class="help-dot" tabindex="0" data-tip="Ajusta la cantidad (Kg o unidades) y, si hace falta, el precio de cada servicio.">?</span></h2>
              <span id="cnt-lineas" class="badge badge-gray">0 lineas</span>
            </div>
            <div class="tabla-empty-msg" id="msg-empty">
              Usa los servicios de arriba o agrega una línea manual
            </div>
            <table class="det-table" id="det-table" style="display:none">
              <thead>
                <tr>
                  <th style="width:38%">Servicio</th>
                  <th style="width:15%">Cantidad</th>
                  <th style="width:18%">Precio</th>
                  <th style="width:18%">Subtotal</th>
                  <th style="width:11%"></th>
                </tr>
              </thead>
              <tbody id="det-body"></tbody>
            </table>
            <button class="btn-add-ln" onclick="agregarLineaManual()">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Agregar línea manualmente
            </button>
          </div>

          <!-- PRENDAS -->
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">4. Prendas recibidas
                <span class="help-dot" tabindex="0" data-tip="Registra qué prendas entrega el cliente y cuántas. Aparecen en el ticket y sirven para verificar la entrega.">?</span>
              </h2>
              <span id="cnt-prendas" class="badge badge-gray">0 prendas</span>
            </div>
            <div class="prenda-chips">
              <?php foreach (array_slice($tiposPrenda, 0, 12) as $tp): ?>
              <button type="button" class="prenda-chip" data-id="<?= (int)$tp['id'] ?>" data-nombre="<?= h($tp['nombre']) ?>" data-tip="Agregar 1 <?= h($tp['nombre']) ?> (clic de nuevo para sumar)"
                      onclick="sumarPrenda(<?= (int)$tp['id'] ?>)">+ <?= h($tp['nombre']) ?></button>
              <?php endforeach; ?>
            </div>
            <div class="prenda-head" id="prenda-head" style="display:none">
              <span>Tipo de prenda</span><span>Cantidad</span><span class="prenda-obs">Observación (color, marca, daños)</span><span></span>
            </div>
            <div id="prendas-body"></div>
            <button class="btn-add-ln" type="button" onclick="agregarPrenda()">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Agregar otro tipo de prenda
            </button>
          </div>
        </div>

        <!-- DERECHA: TOTALES -->
        <div style="position:sticky;top:24px;display:flex;flex-direction:column;gap:14px;">
          <div class="card">
            <div class="card-header"><h2 class="card-title">Resumen</h2></div>

            <div class="form-group" style="margin-bottom:14px;">
              <label class="form-label" for="metodo-pago">Método de pago <span class="help-dot" tabindex="0" data-tip="Forma en que el cliente paga. Se registra en la caja del día.">?</span></label>
              <select id="metodo-pago" class="form-control">
                <?php foreach (METODOS_PAGO as $val => $lbl): ?>
                <option value="<?= $val ?>"><?= h($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
              <label class="form-label" for="notas">Notas</label>
              <textarea id="notas" class="form-control" rows="2" maxlength="500" placeholder="Manchas, instrucciones especiales..."></textarea>
              <span class="form-hint" id="notas-cnt" style="text-align:right;">0 / 500</span>
            </div>

            <div style="background:var(--bg-surface2);border-radius:var(--radius-md);padding:14px;margin-bottom:16px;">
              <div class="total-row">
                <span style="font-size:12px;color:var(--text-hint)">Subtotal</span>
                <span id="v-sub" style="font-size:13px;font-weight:500;color:var(--text-sub)">$0.00</span>
              </div>
              <div class="total-row">
                <span style="font-size:12px;color:var(--text-hint)">Prendas</span>
                <span id="v-prendas" style="font-size:12px;color:var(--text-sub)">0</span>
              </div>
              <div class="total-row">
                <span style="font-size:12px;color:var(--text-hint)">Tasa BCV</span>
                <span style="font-size:12px;color:var(--text-hint)">Bs. <?= number_format((float)$tasa['tasa_bcv'],2,',','.') ?></span>
              </div>
            </div>

            <!-- Total destacado -->
            <div class="total-principal">
              <div style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--text-hint);margin-bottom:6px;">Total a cobrar</div>
              <div class="usd" id="v-usd">$0.00</div>
              <div class="bs" id="v-bs">Bs. 0,00</div>
            </div>

            <button class="btn btn-primary btn-full btn-lg" id="btn-reg" onclick="guardarOrden()" disabled style="opacity:.5;font-size:14.5px;" data-tip="Guarda la orden, la registra en caja e imprime el ticket">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Registrar Orden
            </button>
            <p id="btn-hint" style="font-size:11px;color:var(--text-hint);text-align:center;margin-top:8px;line-height:1.5;">
              Selecciona cliente y al menos un servicio
            </p>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<div id="toast-container"></div>

<script>
const TASA   = <?= (float)$tasa['tasa_bcv'] ?>;
const SVCS   = <?= json_encode(array_column($servicios, null, 'id')) ?>;
const PRENDAS = <?= json_encode(array_map(fn($t) => ['id' => (int)$t['id'], 'nombre' => $t['nombre']], $tiposPrenda)) ?>;
const esc = Valida.esc;
let idx      = 0;
let timer    = null;
let cliOk    = false;
let linOk    = false;
const chipMap = {}; // svcId -> lineaIdx

/* ── BÚSQUEDA ── */
function onInput(v){
  clearTimeout(timer);
  if(v.trim().length < 2){ cerrarDD(); return; }
  timer = setTimeout(buscar, 320);
}

function buscar(){
  const q = document.getElementById('inp-busq').value.trim();
  if(!q) return;
  fetch('ordenes.php?buscar_cedula='+encodeURIComponent(q))
    .then(r=>r.json()).then(renderDD)
    .catch(()=>toast('No se pudo buscar el cliente','error'));
}

function renderDD(lista){
  const dd = document.getElementById('cdd');
  dd.innerHTML = '';
  if(!lista || lista.length === 0){
    dd.innerHTML = `<div class="cliente-no-res">
      Sin resultados &mdash;
      <button class="btn btn-sm btn-secondary" style="margin-left:8px;"
        onclick="abrirPanelNuevo();cerrarDD()">+ Nuevo cliente</button>
    </div>`;
  } else {
    lista.forEach(c => {
      const el = document.createElement('div');
      el.className = 'cliente-option';
      el.innerHTML = `<div class="co-avatar">${esc(c.nombre.charAt(0).toUpperCase())}</div>
        <div><p class="co-nombre">${esc(c.nombre)}</p>
        <p class="co-cedula">${esc(c.cedula_rif)} &middot; ${esc(c.telefono)}</p></div>`;
      el.addEventListener('click', ()=>elegirCliente(c));
      dd.appendChild(el);
    });
  }
  dd.classList.add('open');
  setTimeout(()=>document.addEventListener('click', fuera, {once:true}), 10);
}

function fuera(e){
  if(!document.getElementById('search-wrap').contains(e.target)) cerrarDD();
}
function cerrarDD(){ document.getElementById('cdd').classList.remove('open'); }

function elegirCliente(c){
  document.getElementById('cliente-id').value = c.id;
  document.getElementById('cs-nom').textContent = c.nombre;
  document.getElementById('cs-tel').textContent = c.cedula_rif+' · '+c.telefono;
  document.getElementById('cs-av').textContent  = c.nombre.charAt(0).toUpperCase();
  document.getElementById('cs').classList.add('visible');
  document.getElementById('search-wrap').style.display='none';
  document.getElementById('btn-nuevo-cliente').style.display='none';
  cerrarPanelNuevo(); cerrarDD();
  document.getElementById('inp-busq').value='';
  cliOk=true; actualizarBoton();
  toast('Cliente: '+c.nombre,'success');
}

function limpiarCliente(){
  document.getElementById('cliente-id').value='';
  document.getElementById('cs').classList.remove('visible');
  document.getElementById('search-wrap').style.display='';
  document.getElementById('btn-nuevo-cliente').style.display='';
  document.getElementById('inp-busq').value='';
  document.getElementById('inp-busq').focus();
  cliOk=false; actualizarBoton();
}

/* ── PANEL NUEVO CLIENTE ── */
function abrirPanelNuevo(){
  document.getElementById('panel-nuevo').classList.add('visible');
  document.getElementById('btn-nuevo-cliente').style.display='none';
  // Si lo buscado parece un documento o un nombre, se pre-carga
  const q = document.getElementById('inp-busq').value.trim();
  if (/^\d{6,10}$/.test(q)) document.getElementById('nc-doc-num').value = q;
  else if (q && !/\d/.test(q)) {
    const [nom, ...ape] = q.split(/\s+/);
    document.getElementById('nc-nom').value = nom;
    document.getElementById('nc-ape').value = ape.join(' ');
  }
  document.getElementById(document.getElementById('nc-doc-num').value ? 'nc-nom' : 'nc-doc-num').focus();
}
function cerrarPanelNuevo(){
  document.getElementById('panel-nuevo').classList.remove('visible');
  Valida.limpiarTodo(document.getElementById('panel-nuevo'));
  if(!cliOk) document.getElementById('btn-nuevo-cliente').style.display='';
}

document.querySelector('#nc-doc .doc-tipo').addEventListener('change', () =>
  Valida.ajustarNombrePorDoc(document.getElementById('nc-doc'), document.getElementById('nc-nom'), document.getElementById('nc-ape')));

function registrarNuevo(){
  const $ = id => document.getElementById(id);
  const doc = Valida.leerDoc($('nc-doc'));
  const empresa = Valida.esEmpresa(doc.tipo);
  const nom = $('nc-nom').value.trim().replace(/\s+/g,' ');
  const ape = empresa ? '' : $('nc-ape').value.trim().replace(/\s+/g,' ');
  const tel = $('nc-tel').value.trim();
  const ok = Valida.comprobar([
    [$('nc-doc-num'), Valida.documento(doc.tipo, doc.num)],
    [$('nc-tel'),     Valida.telefono(tel)],
    [$('nc-nom'),     Valida.nombre(nom, 2, empresa ? 'La razón social' : 'El nombre')],
    [$('nc-ape'),     empresa ? null : Valida.nombre(ape, 2, 'El apellido')],
  ], $('panel-nuevo'));
  if(!ok) return;
  const fd=new FormData();
  fd.append('action','crear_cliente_rapido');
  fd.append('nombres',nom); fd.append('apellidos',ape); fd.append('doc_tipo',doc.tipo); fd.append('doc_numero',doc.num); fd.append('telefono',tel);
  fetch('ordenes.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      elegirCliente(d.cliente);
      ['nc-nom','nc-ape','nc-doc-num','nc-tel'].forEach(id=>$(id).value='');
      toast('Cliente registrado y seleccionado','success');
    } else {
      const campos={nombres:'nc-nom',apellidos:'nc-ape',documento:'nc-doc-num',telefono:'nc-tel'};
      if(d.campo && campos[d.campo]) Valida.marcar($(campos[d.campo]), d.error);
      toast(d.error||'No se pudo registrar','error');
    }
  }).catch(()=>toast('Error de conexión con el servidor','error'));
}

/* ── CHIPS Y LÍNEAS ── */
function agregarDesdeChip(svcId, nombre, precio, unidad){
  if(chipMap[svcId] !== undefined){
    const inp = document.querySelector('#linea-'+chipMap[svcId]+' input[name="cantidades[]"]');
    if(inp){ inp.focus(); inp.select(); }
    toast('Ya agregado — ajusta la cantidad','warning');
    return;
  }
  const i = agregarLinea(svcId, precio);
  chipMap[svcId] = i;
  document.getElementById('chip-'+svcId)?.classList.add('agregado');
}

function agregarLineaManual(){ agregarLinea('', ''); }

function agregarLinea(svcId, precio){
  const body = document.getElementById('det-body');
  const i    = idx++;
  const opts = Object.values(SVCS).map(s=>
    `<option value="${s.id}" data-p="${s.precio_usd}" ${String(s.id)===String(svcId)?'selected':''}>${esc(s.nombre)}</option>`
  ).join('');

  const tr = document.createElement('tr');
  tr.id = 'linea-'+i;
  tr.innerHTML = `
    <td><select class="form-control" name="servicios[]" style="font-size:12.5px;"
          onchange="onChgSvc(${i})">
      <option value="">— Seleccionar —</option>${opts}</select></td>
    <td><input type="text" name="cantidades[]" class="form-control" data-mask="decimal" inputmode="decimal"
          value="1" maxlength="6" style="width:66px;font-size:13px;"
          oninput="calcLin(${i})"></td>
    <td><input type="text" name="precios[]" class="form-control" data-mask="decimal" inputmode="decimal"
          value="${precio||''}" maxlength="8" style="width:74px;font-size:13px;"
          oninput="calcLin(${i})"></td>
    <td id="sub-${i}" style="font-size:13px;font-weight:600;color:var(--accent-light);">$0.00</td>
    <td><button type="button" class="btn-del" onclick="elimLin(${i},'${svcId}')" data-tip="Quitar esta línea">×</button></td>`;
  body.appendChild(tr);
  if(precio) calcLin(i);
  sync(); return i;
}

function onChgSvc(i){
  const tr  = document.getElementById('linea-'+i);
  const sel = tr.querySelector('select');
  const svc = SVCS[sel.value];
  if(svc) tr.querySelector('input[name="precios[]"]').value = parseFloat(svc.precio_usd);
  calcLin(i);
}

function calcLin(i){
  const tr  = document.getElementById('linea-'+i); if(!tr) return;
  const c   = parseFloat(tr.querySelector('input[name="cantidades[]"]').value)||0;
  const p   = parseFloat(tr.querySelector('input[name="precios[]"]').value)||0;
  document.getElementById('sub-'+i).textContent = '$'+(c*p).toFixed(2);
  calcTotales();
}

function elimLin(i, svcId){
  document.getElementById('linea-'+i)?.remove();
  if(svcId && chipMap[svcId]===i){
    delete chipMap[svcId];
    document.getElementById('chip-'+svcId)?.classList.remove('agregado');
  }
  sync(); calcTotales();
}

function sync(){
  const n = document.getElementById('det-body').children.length;
  document.getElementById('det-table').style.display = n ? '' : 'none';
  document.getElementById('msg-empty').style.display  = n ? 'none' : '';
  const badge = document.getElementById('cnt-lineas');
  badge.textContent = n + (n===1?' línea':' líneas');
  badge.className   = 'badge '+(n?'badge-blue':'badge-gray');
  linOk = n > 0; actualizarBoton();
}

/* ── PRENDAS ── */
let pidx = 0;
function opcionesPrenda(sel){
  return '<option value="">— Tipo —</option>' + PRENDAS.map(p =>
    `<option value="${p.id}" ${p.id===sel?'selected':''}>${esc(p.nombre)}</option>`).join('');
}
function agregarPrenda(tipoId, cant){
  const i = pidx++;
  const row = document.createElement('div');
  row.className = 'prenda-row'; row.id = 'prenda-'+i;
  row.innerHTML = `
    <select class="form-control" name="prenda_tipo[]" onchange="syncPrendas()">${opcionesPrenda(tipoId||0)}</select>
    <div class="qty-box">
      <button type="button" onclick="pasoPrenda(${i},-1)" aria-label="Restar">−</button>
      <input class="form-control" name="prenda_cant[]" value="${cant||1}" data-mask="integer" inputmode="numeric" maxlength="3" oninput="syncPrendas()">
      <button type="button" onclick="pasoPrenda(${i},1)" aria-label="Sumar">+</button>
    </div>
    <input class="form-control prenda-obs" name="prenda_obs[]" maxlength="150" placeholder="Ej: azul, marca X, mancha en manga">
    <button type="button" class="btn-del" onclick="document.getElementById('prenda-${i}').remove();syncPrendas();" data-tip="Quitar prenda">×</button>`;
  document.getElementById('prendas-body').appendChild(row);
  syncPrendas();
  if(!tipoId) row.querySelector('select').focus();
  return row;
}
function sumarPrenda(tipoId){
  // Si ya existe una fila de ese tipo, suma 1; si no, crea la fila
  const fila = [...document.querySelectorAll('#prendas-body .prenda-row')]
    .find(r => r.querySelector('select').value === String(tipoId));
  if(fila){
    const inp = fila.querySelector('input[name="prenda_cant[]"]');
    inp.value = Math.min(999, (parseInt(inp.value)||0) + 1);
    syncPrendas();
  } else agregarPrenda(tipoId, 1);
}
function pasoPrenda(i, d){
  const inp = document.querySelector('#prenda-'+i+' input[name="prenda_cant[]"]');
  inp.value = Math.max(1, Math.min(999, (parseInt(inp.value)||0) + d));
  syncPrendas();
}
function syncPrendas(){
  const filas = document.querySelectorAll('#prendas-body .prenda-row');
  let total = 0;
  const cuenta = {};
  filas.forEach(r => {
    const tipo = r.querySelector('select').value;
    const c = parseInt(r.querySelector('input[name="prenda_cant[]"]').value)||0;
    if(tipo){ total += c; cuenta[tipo] = (cuenta[tipo]||0) + c; }
  });
  document.getElementById('prenda-head').style.display = filas.length ? '' : 'none';
  const b = document.getElementById('cnt-prendas');
  b.textContent = total + (total===1?' prenda':' prendas');
  b.className = 'badge ' + (total ? 'badge-green' : 'badge-gray');
  document.getElementById('v-prendas').textContent = total;
  // Marca en los chips cuántas hay de cada tipo
  document.querySelectorAll('.prenda-chip').forEach(ch => {
    const id = ch.dataset.id;
    ch.innerHTML = '+ ' + esc(ch.dataset.nombre) + (cuenta[id] ? `<b>${cuenta[id]}</b>` : '');
  });
}

/* ── TOTALES ── */
function calcTotales(){
  let t = 0;
  document.querySelectorAll('#det-body tr').forEach(tr=>{
    const c = parseFloat(tr.querySelector('input[name="cantidades[]"]')?.value)||0;
    const p = parseFloat(tr.querySelector('input[name="precios[]"]')?.value)||0;
    t += c*p;
  });
  document.getElementById('v-sub').textContent = '$'+t.toFixed(2);
  document.getElementById('v-usd').textContent = '$'+t.toFixed(2);
  document.getElementById('v-bs').textContent  = 'Bs. '+
    (t*TASA).toLocaleString('es-VE',{minimumFractionDigits:2,maximumFractionDigits:2});
}

/* ── BOTÓN ── */
function actualizarBoton(){
  const ok  = cliOk && linOk;
  const btn = document.getElementById('btn-reg');
  btn.disabled = !ok;
  btn.style.opacity = ok?'1':'0.5';
  const h = document.getElementById('btn-hint');
  h.textContent = !cliOk&&!linOk?'Selecciona cliente y al menos un servicio'
                : !cliOk?'Selecciona un cliente'
                : !linOk?'Agrega al menos un servicio':'';
}

/* ── GUARDAR ORDEN ── */
function validarOrden(){
  Valida.limpiarTodo();
  const usados = new Set();
  let total = 0;
  for (const tr of document.querySelectorAll('#det-body tr')) {
    const sel = tr.querySelector('select');
    const c = tr.querySelector('input[name="cantidades[]"]');
    const p = tr.querySelector('input[name="precios[]"]');
    if(!sel.value){ Valida.marcar(sel,'Seleccione el servicio'); toast('Hay una línea sin servicio seleccionado','warning'); return false; }
    if(usados.has(sel.value)){ Valida.marcar(sel,'Servicio repetido'); toast('Un servicio está repetido: ajusta la cantidad en una sola línea','warning'); return false; }
    usados.add(sel.value);
    let e = Valida.numero(c.value, 0.1, 999, 'La cantidad');
    if(e){ Valida.marcar(c,e); toast(e,'warning'); return false; }
    e = Valida.numero(p.value, 0, 9999, 'El precio');
    if(e){ Valida.marcar(p,e); toast(e,'warning'); return false; }
    total += parseFloat(c.value.replace(',','.')) * parseFloat(p.value.replace(',','.'));
  }
  if(Math.round(total*100) <= 0){ toast('El total de la orden debe ser mayor que cero','warning'); return false; }
  const notas = document.getElementById('notas');
  if(notas && notas.value.trim().length > 500){ Valida.marcar(notas,'Máximo 500 caracteres'); toast('Las notas no pueden superar 500 caracteres','warning'); return false; }
  for (const r of document.querySelectorAll('#prendas-body .prenda-row')) {
    const sel = r.querySelector('select');
    const c = r.querySelector('input[name="prenda_cant[]"]');
    if(!sel.value){ Valida.marcar(sel,'Seleccione el tipo'); toast('Hay una prenda sin tipo seleccionado','warning'); return false; }
    if(!/^\d+$/.test(c.value) || +c.value < 1 || +c.value > 999){ Valida.marcar(c,'Entre 1 y 999'); toast('Cantidad de prendas inválida','warning'); return false; }
    const o = r.querySelector('input[name="prenda_obs[]"]');
    if(o && o.value.trim().length > 150){ Valida.marcar(o,'Máximo 150 caracteres'); toast('La observación de una prenda no puede superar 150 caracteres','warning'); return false; }
  }
  return true;
}

function guardarOrden(){
  const cliId = document.getElementById('cliente-id').value;
  if(!cliId){ toast('Selecciona un cliente','warning'); return; }
  const rows = document.querySelectorAll('#det-body tr');
  if(!rows.length){ toast('Agrega al menos un servicio','warning'); return; }
  if(!validarOrden()) return;
  const totalPrendas = parseInt(document.getElementById('v-prendas').textContent)||0;
  if(totalPrendas === 0 && !confirm('No registraste las prendas recibidas.\n¿Deseas registrar la orden de todas formas?')) return;

  const btn = document.getElementById('btn-reg');
  btn.disabled = true;
  btn.innerHTML = '<span style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;display:inline-block;animation:spin .7s linear infinite"></span> Guardando...';

  const fd = new FormData();
  fd.append('action','crear_orden');
  fd.append('cliente_id', cliId);
  fd.append('metodo_pago', document.getElementById('metodo-pago').value);
  fd.append('notas', document.getElementById('notas').value);
  document.querySelectorAll('#det-body select[name="servicios[]"]').forEach(s=>fd.append('servicios[]',s.value));
  document.querySelectorAll('#det-body input[name="cantidades[]"]').forEach(i=>fd.append('cantidades[]',i.value));
  document.querySelectorAll('#det-body input[name="precios[]"]').forEach(i=>fd.append('precios[]',i.value));
  document.querySelectorAll('#prendas-body .prenda-row').forEach(r=>{
    fd.append('prenda_tipo[]', r.querySelector('select').value);
    fd.append('prenda_cant[]', r.querySelector('input[name="prenda_cant[]"]').value);
    fd.append('prenda_obs[]',  r.querySelector('input[name="prenda_obs[]"]').value);
  });

  const restaurar = ()=>{
    btn.disabled=false;
    btn.innerHTML='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Registrar Orden';
    actualizarBoton();
  };

  fetch('ordenes.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      const num = String(d.orden_id).padStart(4,'0');
      toast('Orden #'+num+' registrada! Abriendo ticket…','success');

      // Abrir ticket de recepción en ventana emergente (tickera 80mm)
      window.open(
        'ticket.php?id='+d.orden_id+'&tipo=recepcion&auto=1',
        'ticket_recepcion_'+d.orden_id,
        'width=420,height=700,scrollbars=yes,resizable=yes'
      );

      // Ir al kanban después de un momento
      setTimeout(()=>window.location='kanban.php', 1200);
    } else {
      toast('Error: '+(d.error||'No se pudo guardar'),'error');
      restaurar();
    }
  }).catch(()=>{ toast('Error de conexión con el servidor','error'); restaurar(); });
}

/* ── IMPRIMIR TICKET MANUAL ── */
function imprimirTicket(ordenId, tipo) {
  tipo = tipo || 'recepcion';
  window.open(
    'ticket.php?id='+ordenId+'&tipo='+tipo+'&auto=1',
    'ticket_'+tipo+'_'+ordenId,
    'width=420,height=700,scrollbars=yes,resizable=yes'
  );
}

/* ── TOAST ── */
function toast(msg,type){
  const icons={success:'✓',error:'✕',warning:'⚠'};
  const el=document.createElement('div');
  el.className='toast '+type;
  el.innerHTML='<span>'+icons[type]+'</span> '+esc(msg);
  document.getElementById('toast-container').appendChild(el);
  setTimeout(()=>el.remove(),3200);
}

document.getElementById('notas').addEventListener('input', function(){
  document.getElementById('notas-cnt').textContent = this.value.length + ' / 500';
});
actualizarBoton();
</script>
</body>
</html>

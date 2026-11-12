<?php
require_once __DIR__ . '/config/init.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    $a=entrada($_POST, 'action');
    if ($a==='registrar') {
        $tasa=obtenerTasaHoy();
        $tipo  = entrada($_POST, 'tipo');
        $conc  = entrada($_POST, 'concepto');
        $desc  = limpiarTexto(entrada($_POST, 'descripcion'));
        $montoUSD = numeroEnRango(entrada($_POST, 'monto_usd'), 0.01, 1000000);
        $err = null;
        if (!in_array($tipo, ['Ingreso','Egreso'], true)) $err = 'Tipo de movimiento inválido.';
        elseif (!in_array($conc, ['Servicio','Proveedor','Nomina','Suministros','Otro'], true)) $err = 'Concepto inválido.';
        elseif (mb_strlen($desc) < 3 || mb_strlen($desc) > 255) $err = 'La descripción debe tener entre 3 y 255 caracteres.';
        elseif ($montoUSD === null) $err = 'El monto debe ser un número mayor que cero.';
        if ($err) { echo json_encode(['success'=>false,'error'=>$err]); exit; }
        $sql="INSERT INTO movimientos_caja (tipo,concepto,descripcion,monto_usd,monto_bs,tasa_cambio,registrado_por) VALUES(:t,:c,:d,:u,:b,:tasa,:usr)";
        getDB()->prepare($sql)->execute([
          ':t'=>$tipo,':c'=>$conc,':d'=>$desc,
          ':u'=>$montoUSD,':b'=>convertirUSDaBS($montoUSD,$tasa['tasa_bcv']),
          ':tasa'=>$tasa['tasa_bcv'],':usr'=>$_SESSION['usuario_nombre']??'sistema'
        ]);
        echo json_encode(['success'=>true]); exit;
    }
    echo json_encode(['success'=>false,'error'=>'Acción desconocida.']); exit;
}

// Fecha real y no futura; cualquier otro valor muestra el día de hoy
$fecha       = fechaValida(entrada($_GET, 'fecha')) ?? date('Y-m-d');
if ($fecha > date('Y-m-d')) $fecha = date('Y-m-d');
$db          = getDB();
$movs        = $db->prepare("SELECT *, COALESCE(monto_usd,0) AS monto_usd, COALESCE(monto_bs,0) AS monto_bs FROM movimientos_caja WHERE DATE(fecha)=? ORDER BY fecha DESC");
$movs->execute([$fecha]);
$movimientos = $movs->fetchAll();

$qIng = $db->prepare("SELECT COALESCE(SUM(COALESCE(monto_usd,0)),0) FROM movimientos_caja WHERE tipo='Ingreso' AND DATE(fecha)=?");
$qIng->execute([$fecha]); $totalIngresos = (float)$qIng->fetchColumn();

$qEgr = $db->prepare("SELECT COALESCE(SUM(COALESCE(monto_usd,0)),0) FROM movimientos_caja WHERE tipo='Egreso' AND DATE(fecha)=?");
$qEgr->execute([$fecha]); $totalEgresos = (float)$qEgr->fetchColumn();

$balance   = $totalIngresos - $totalEgresos;
$tasa      = obtenerTasaHoy();
$conceptos = ['Servicio','Proveedor','Nomina','Suministros','Otro'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Caja — Lavandería Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    /* Layout caja */
    .caja-layout { display:grid; grid-template-columns:1fr 340px; gap:18px; align-items:start; }

    /* KPIs */
    .caja-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:18px; }
    .ckpi { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px 20px; position:relative; overflow:hidden; transition:border-color .15s; }
    .ckpi:hover { border-color:var(--border-md); }
    .ckpi__glow { position:absolute; top:-20px; right:-20px; width:80px; height:80px; border-radius:50%; opacity:.07; pointer-events:none; }
    .ckpi--ing  .ckpi__glow { background:var(--green); }
    .ckpi--egr  .ckpi__glow { background:var(--red);   }
    .ckpi--bal  .ckpi__glow { background:var(--accent); }
    .ckpi__lbl  { font-size:10px; font-weight:600; letter-spacing:.09em; text-transform:uppercase; color:var(--text-hint); margin-bottom:8px; }
    .ckpi__usd  { font-family:var(--font-display); font-size:26px; font-weight:800; line-height:1; }
    .ckpi--ing  .ckpi__usd { color:var(--green);  }
    .ckpi--egr  .ckpi__usd { color:var(--red);    }
    .ckpi__bs   { font-size:12px; margin-top:4px; }
    .ckpi--ing  .ckpi__bs  { color:var(--text-hint); }
    .ckpi--egr  .ckpi__bs  { color:var(--text-hint); }
    .ckpi__cnt  { font-size:11px; color:var(--text-hint); margin-top:6px; }

    /* Lista de movimientos */
    .mov-list  { display:flex; flex-direction:column; }
    .mov-item  { display:flex; align-items:center; gap:13px; padding:13px 0; border-bottom:1px solid var(--border); transition:background .12s; }
    .mov-item:last-child { border-bottom:none; }
    .mov-item:hover { margin:0 -22px; padding:13px 22px; background:var(--bg-surface2); border-radius:var(--radius-sm); }
    .mov-ico  { width:36px; height:36px; border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .mov-ico.ing { background:var(--green-bg); color:var(--green); border:1px solid var(--green-border); }
    .mov-ico.egr { background:var(--red-bg);   color:var(--red);   border:1px solid var(--red-border);   }
    .mov-body { flex:1; min-width:0; }
    .mov-desc { font-size:13.5px; font-weight:500; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .mov-meta { font-size:11.5px; color:var(--text-hint); margin-top:2px; display:flex; align-items:center; gap:6px; }
    .mov-meta span { display:inline-flex; align-items:center; gap:3px; }
    .mov-monto { text-align:right; flex-shrink:0; }
    .mov-monto-usd { font-size:14.5px; font-weight:700; }
    .mov-monto-usd.ing { color:var(--green); }
    .mov-monto-usd.egr { color:var(--red);   }
    .mov-monto-bs  { font-size:11px; color:var(--text-hint); margin-top:2px; }

    /* Panel registro rápido */
    .registro-panel { position:sticky; top:20px; }
    .tipo-selector  { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:16px; }
    .tipo-opt       { position:relative; }
    .tipo-opt input { position:absolute; opacity:0; }
    .tipo-opt label { display:flex; align-items:center; justify-content:center; gap:6px; padding:10px; border:1.5px solid var(--border-md); border-radius:var(--radius-md); font-size:13px; font-weight:600; cursor:pointer; transition:all .15s; font-family:var(--font-body); }
    .tipo-opt.ing input:checked + label { border-color:var(--green-border); background:var(--green-bg); color:var(--green); }
    .tipo-opt.egr input:checked + label { border-color:var(--red-border);   background:var(--red-bg);   color:var(--red);   }
    .tipo-opt label:hover { border-color:var(--border-strong); }

    /* Navegación de fechas */
    .date-nav { display:flex; align-items:center; gap:6px; }
    .date-btn  { width:32px; height:32px; border-radius:var(--radius-sm); border:1px solid var(--border-md); background:var(--bg-surface2); color:var(--text-sub); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; text-decoration:none; }
    .date-btn:hover { border-color:var(--accent-border); color:var(--accent-light); background:var(--accent-bg); }

    /* Modal */
    .modal-bd { position:fixed; inset:0; background:rgba(0,0,0,.65); backdrop-filter:blur(4px); z-index:500; display:none; align-items:center; justify-content:center; padding:20px; }
    .modal-bx { background:var(--bg-surface); border:1px solid var(--border-md); border-radius:var(--radius-xl); width:100%; max-width:460px; overflow:hidden; animation:mIn .2s ease; }
    @keyframes mIn { from{opacity:0;transform:scale(.97) translateY(8px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .modal-hd { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid var(--border); }
    .modal-tt { font-family:var(--font-display); font-size:17px; font-weight:700; color:var(--text); }
    .modal-xb { width:28px; height:28px; border-radius:var(--radius-sm); border:1px solid var(--border); background:transparent; color:var(--text-hint); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; }
    .modal-xb:hover { border-color:var(--red-border); color:var(--red); background:var(--red-bg); }
    .modal-bd2 { padding:20px 24px; }
    .modal-ft  { padding:14px 24px 20px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border); }

    .empty-caja { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px; gap:10px; }
    .empty-caja svg { color:var(--text-hint); opacity:.3; }
    .empty-caja p { font-size:13.5px; color:var(--text-hint); }

    #toast-container { position:fixed; bottom:24px; right:24px; z-index:1000; display:flex; flex-direction:column; gap:8px; }
    .toast { display:flex; align-items:center; gap:10px; padding:11px 16px; background:var(--bg-surface2); border:1px solid var(--border-md); border-radius:var(--radius-md); font-size:13px; color:var(--text); min-width:240px; animation:sfr .2s ease; box-shadow:0 4px 20px rgba(0,0,0,.4); }
    .toast.success { border-color:var(--green-border); }
    .toast.error   { border-color:var(--red-border); }
    @keyframes sfr { from{opacity:0;transform:translateX(14px)} to{opacity:1;transform:translateX(0)} }

    @media(max-width:1100px){ .caja-layout{ grid-template-columns:1fr; } .registro-panel{ position:static; } }
  </style>
</head>
<body>
<div class="layout">
  <?php partial('sidebar'); ?>
  <main class="main-content">

    <div class="topbar">
      <div>
        <h1 class="page-title">Libro de Caja</h1>
        <p class="page-sub">Movimientos financieros del negocio</p>
      </div>
      <div class="topbar-actions">
        <!-- Navegación de fechas -->
        <div class="date-nav">
          <?php
            $ayer   = date('Y-m-d', strtotime($fecha.' -1 day'));
            $manana = date('Y-m-d', strtotime($fecha.' +1 day'));
            $esHoy  = $fecha === date('Y-m-d');
          ?>
          <a href="?fecha=<?= $ayer ?>" class="date-btn" data-tip="Día anterior">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </a>
          <form method="get" style="display:flex;gap:0;">
            <input type="date" name="fecha" value="<?= h($fecha) ?>" class="form-control" style="width:150px;border-radius:var(--radius-sm);font-size:13px;" onchange="this.form.submit()">
          </form>
          <a href="?fecha=<?= $manana ?>" class="date-btn" data-tip="Día siguiente" <?= $esHoy ? 'style="opacity:.35;pointer-events:none;"' : '' ?>>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
          <?php if(!$esHoy): ?>
          <a href="caja.php" class="date-btn" data-tip="Volver al día de hoy" style="font-size:10px;font-weight:700;color:var(--accent-light);width:auto;padding:0 8px;">Hoy</a>
          <?php endif; ?>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('modal-mov').style.display='flex'" data-tip="Registrar un ingreso o egreso manual" data-tip-pos="bottom">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuevo Movimiento
        </button>
      </div>
    </div>

    <div class="content-area">

      <!-- KPIs -->
      <div class="caja-kpis">
        <div class="ckpi ckpi--ing">
          <div class="ckpi__glow"></div>
          <p class="ckpi__lbl">Total Ingresos</p>
          <p class="ckpi__usd"><?= formatUSD($totalIngresos) ?></p>
          <p class="ckpi__bs"><?= formatBS(convertirUSDaBS($totalIngresos,$tasa['tasa_bcv'])) ?></p>
          <p class="ckpi__cnt"><?= count(array_filter($movimientos, fn($m) => $m['tipo']==='Ingreso')) ?> movimientos</p>
        </div>
        <div class="ckpi ckpi--egr">
          <div class="ckpi__glow"></div>
          <p class="ckpi__lbl">Total Egresos</p>
          <p class="ckpi__usd"><?= formatUSD($totalEgresos) ?></p>
          <p class="ckpi__bs"><?= formatBS(convertirUSDaBS($totalEgresos,$tasa['tasa_bcv'])) ?></p>
          <p class="ckpi__cnt"><?= count(array_filter($movimientos, fn($m) => $m['tipo']==='Egreso')) ?> movimientos</p>
        </div>
        <div class="ckpi ckpi--bal" style="border-color:<?= $balance>=0?'var(--green-border)':'var(--red-border)' ?>;">
          <div class="ckpi__glow" style="background:<?= $balance>=0?'var(--green)':'var(--red)' ?>;"></div>
          <p class="ckpi__lbl">Balance del Día</p>
          <p class="ckpi__usd" style="color:<?= $balance>=0?'var(--green)':'var(--red)' ?>;">
            <?= ($balance>=0?'+':'') . formatUSD($balance) ?>
          </p>
          <p class="ckpi__bs" style="color:var(--text-hint);"><?= formatBS(convertirUSDaBS(abs($balance),$tasa['tasa_bcv'])) ?></p>
          <p class="ckpi__cnt" style="color:<?= $balance>=0?'var(--green)':'var(--red)' ?>;font-weight:600;">
            <?= $balance>=0 ? '✓ Positivo' : '✗ Negativo' ?>
          </p>
        </div>
      </div>

      <!-- Layout movimientos + registro rápido -->
      <div class="caja-layout">

        <!-- Lista de movimientos -->
        <div class="card">
          <div class="card-header">
            <div>
              <h2 class="card-title">Movimientos</h2>
              <p style="font-size:11.5px;color:var(--text-hint);margin-top:2px;">
                <?= date('l d \d\e F \d\e Y', strtotime($fecha)) ?>
                · <?= count($movimientos) ?> registros
              </p>
            </div>
          </div>

          <?php if(empty($movimientos)): ?>
          <div class="empty-caja">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
            <p>Sin movimientos para este día</p>
          </div>
          <?php else: ?>
          <div class="mov-list">
            <?php foreach($movimientos as $m):
              $esIng = $m['tipo'] === 'Ingreso';
            ?>
            <div class="mov-item">
              <div class="mov-ico <?= $esIng?'ing':'egr' ?>">
                <?php if($esIng): ?>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                <?php else: ?>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                <?php endif; ?>
              </div>
              <div class="mov-body">
                <p class="mov-desc"><?= h($m['descripcion']) ?></p>
                <div class="mov-meta">
                  <span>
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?= $m['concepto'] ?>
                  </span>
                  <span style="color:var(--border-strong);">·</span>
                  <span>
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= date('h:i a', strtotime($m['fecha'])) ?>
                  </span>
                </div>
              </div>
              <div class="mov-monto">
                <p class="mov-monto-usd <?= $esIng?'ing':'egr' ?>">
                  <?= $esIng?'+':'-' ?><?= formatUSD($m['monto_usd']) ?>
                </p>
                <p class="mov-monto-bs"><?= formatBS($m['monto_bs']??0) ?></p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Panel registro rápido -->
        <div class="registro-panel">
          <div class="card">
            <div class="card-header" style="margin-bottom:14px;">
              <h2 class="card-title">Nuevo Movimiento</h2>
            </div>

            <!-- Tipo visual -->
            <div class="tipo-selector">
              <div class="tipo-opt ing">
                <input type="radio" name="qtipo" id="qt-ing" value="Ingreso" checked>
                <label for="qt-ing">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                  Ingreso
                </label>
              </div>
              <div class="tipo-opt egr">
                <input type="radio" name="qtipo" id="qt-egr" value="Egreso">
                <label for="qt-egr">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                  Egreso
                </label>
              </div>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label">Concepto <span class="help-dot" tabindex="0" data-tip="Clasifica el movimiento para los reportes de egresos (proveedor, nómina, suministros…)">?</span></label>
              <select id="q-concepto" class="form-control">
                <?php foreach($conceptos as $c): ?>
                <option value="<?= $c ?>"><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label">Descripción *</label>
              <input id="q-desc" class="form-control" maxlength="255" placeholder="Ej: Compra de detergente">
            </div>
            <div class="form-group" style="margin-bottom:16px;">
              <label class="form-label">Monto en USD *</label>
              <input id="q-monto" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="10" placeholder="0.00" style="font-size:18px;font-weight:700;text-align:center;">
              <p style="font-size:11px;color:var(--text-hint);margin-top:4px;text-align:center;" id="q-bs-preview">Bs. —</p>
            </div>

            <button class="btn btn-primary btn-full" onclick="guardarRapido()" style="font-size:14px;padding:11px;" data-tip="Los cobros de órdenes se registran solos; usa esto para otros movimientos">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Registrar
            </button>

            <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
              <p style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--text-hint);margin-bottom:8px;">Tasa BCV hoy</p>
              <p style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--text);">
                Bs. <?= number_format((float)$tasa['tasa_bcv'],2,',','.') ?>
                <span style="font-size:12px;font-weight:400;color:var(--text-hint);">/ USD</span>
              </p>
              <a href="configuracion.php" style="font-size:11.5px;color:var(--accent-light);margin-top:4px;display:inline-block;">Actualizar tasa →</a>
            </div>
          </div>
        </div>

      </div><!-- /caja-layout -->
    </div>
  </main>
</div>

<!-- MODAL MOVIMIENTO (alternativo) -->
<div id="modal-mov" class="modal-bd">
  <div class="modal-bx">
    <div class="modal-hd">
      <h2 class="modal-tt">Registrar Movimiento</h2>
      <button class="modal-xb" onclick="document.getElementById('modal-mov').style.display='none'">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-bd2">
      <div class="form-row form-row-2" style="margin-bottom:14px;">
        <div class="form-group">
          <label class="form-label">Tipo</label>
          <select id="m-tipo" class="form-control">
            <option value="Ingreso">Ingreso</option>
            <option value="Egreso">Egreso</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Concepto</label>
          <select id="m-concepto" class="form-control">
            <?php foreach($conceptos as $c): ?><option><?= $c ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label class="form-label">Descripción *</label>
        <input id="m-desc" class="form-control" maxlength="255" placeholder="Descripción del movimiento">
      </div>
      <div class="form-group">
        <label class="form-label">Monto (USD) *</label>
        <input id="m-monto" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="10" placeholder="0.00">
      </div>
    </div>
    <div class="modal-ft">
      <button class="btn btn-secondary" onclick="document.getElementById('modal-mov').style.display='none'">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarMovimiento()">Registrar</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>
<script>
const TASA = <?= (float)$tasa['tasa_bcv'] ?>;

// Preview Bs en panel rápido
document.getElementById('q-monto').addEventListener('input', function(){
  const v = parseFloat(this.value)||0;
  document.getElementById('q-bs-preview').textContent = v > 0
    ? 'Bs. ' + (v*TASA).toLocaleString('es-VE',{minimumFractionDigits:2,maximumFractionDigits:2})
    : 'Bs. —';
});

function guardarRapido(){
  const tipo  = document.querySelector('input[name="qtipo"]:checked')?.value || 'Ingreso';
  const conc  = document.getElementById('q-concepto').value;
  const desc  = document.getElementById('q-desc').value.trim();
  const monto = document.getElementById('q-monto').value;
  if(!Valida.comprobar([
    [document.getElementById('q-desc'),  desc.length < 3 ? 'Describe el movimiento (mínimo 3 caracteres).' : null],
    [document.getElementById('q-monto'), Valida.numero(monto, 0.01, 1000000, 'El monto')],
  ])) return;
  const fd=new FormData();
  fd.append('action','registrar'); fd.append('tipo',tipo);
  fd.append('concepto',conc); fd.append('descripcion',desc); fd.append('monto_usd',monto);
  fetch('caja.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      toast('Movimiento registrado','success');
      document.getElementById('q-desc').value='';
      document.getElementById('q-monto').value='';
      document.getElementById('q-bs-preview').textContent='Bs. —';
      setTimeout(()=>location.reload(),800);
    } else toast(d.error||'Error al registrar','error');
  });
}

function guardarMovimiento(){
  const fd=new FormData();
  fd.append('action','registrar');
  fd.append('tipo',document.getElementById('m-tipo').value);
  fd.append('concepto',document.getElementById('m-concepto').value);
  fd.append('descripcion',document.getElementById('m-desc').value);
  fd.append('monto_usd',document.getElementById('m-monto').value);
  if(!Valida.comprobar([
    [document.getElementById('m-desc'),  String(fd.get('descripcion')).trim().length < 3 ? 'Describe el movimiento (mínimo 3 caracteres).' : null],
    [document.getElementById('m-monto'), Valida.numero(fd.get('monto_usd'), 0.01, 1000000, 'El monto')],
  ], document.getElementById('modal-mov'))) return;
  fetch('caja.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){ document.getElementById('modal-mov').style.display='none'; toast('Registrado','success'); setTimeout(()=>location.reload(),800); }
    else toast(d.error||'Error al guardar','error');
  });
}

function toast(msg,type){
  const el=document.createElement('div');
  el.className='toast '+(type||'success');
  el.textContent=msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(()=>el.remove(),3000);
}
</script>
</body>
</html>

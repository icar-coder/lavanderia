<?php
require_once __DIR__ . '/config/init.php';
requireAuth('kanban');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $accion  = entrada($_POST, 'action');
    $ordenId = (int) entrada($_POST, 'orden_id');
    if ($ordenId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Orden inválida.']);
        exit;
    }
    if ($accion === 'cancelar') {
        $motivo = limpiarTexto(entrada($_POST, 'motivo'));
        if (mb_strlen($motivo) < 3 || mb_strlen($motivo) > 200) {
            echo json_encode(['success' => false, 'error' => 'Indique el motivo de la cancelación (3 a 200 caracteres).']);
            exit;
        }
        try {
            cancelarOrden($ordenId, $motivo);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    if ($accion === 'cambiar_estado') {
        $id     = $ordenId;
        $estado = entrada($_POST, 'estado');
        // La cancelación tiene su propia acción (revierte caja e inventario)
        if (!in_array($estado, ['Lavando','Secando','Listo','Entregado'], true)) {
            echo json_encode(['success' => false, 'error' => 'Estado no permitido.']);
            exit;
        }
        $ok   = cambiarEstadoOrden($id, $estado);
        $resp = ['success' => $ok];
        // Falla si la orden ya cambió (otra persona la movió) o no viene del paso anterior
        if (!$ok) $resp['error'] = 'La orden ya no está en «' . ESTADO_ANTERIOR[$estado] . '». Se actualizará el tablero.';
        // Al quedar lista se ofrece avisar al cliente por WhatsApp
        if ($ok && $estado === 'Listo') $resp['aviso'] = avisoOrdenLista($id);
        echo json_encode($resp);
        exit;
    }
    if ($accion === 'aviso_whatsapp') {
        $aviso = avisoOrdenLista($ordenId);
        if ($aviso && !in_array($aviso['estado'], ['Listo', 'Entregado'], true)) $aviso = null;
        echo json_encode($aviso ? ['success' => true, 'aviso' => $aviso]
                                : ['success' => false, 'error' => 'La orden no existe o todavía no está lista.']);
        exit;
    }
    if ($accion === 'marcar_avisado') {
        echo json_encode(marcarAvisadoWhatsApp($ordenId)
            ? ['success' => true]
            : ['success' => false, 'error' => 'Solo se puede avisar órdenes listas o entregadas.']);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Acción desconocida.']);
    exit;
}

$estados = ['Recibido','Lavando','Secando','Listo','Entregado'];

$colDef = [
  'Recibido'  => ['var'=>'--text-sub',    'bg'=>'--bg-surface3',  'icon'=>'M5 12h14M12 5l7 7-7 7',                                          'next'=>'Lavando'],
  'Lavando'   => ['var'=>'--accent-light','bg'=>'--accent-bg',    'icon'=>'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM8 12a4 4 0 0 1 8 0',  'next'=>'Secando'],
  'Secando'   => ['var'=>'--amber',       'bg'=>'--amber-bg',     'icon'=>'M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42', 'next'=>'Listo'],
  'Listo'     => ['var'=>'--green',       'bg'=>'--green-bg',     'icon'=>'M20 6L9 17l-5-5',                                                'next'=>'Entregado'],
  'Entregado' => ['var'=>'--text-hint',   'bg'=>'--bg-surface3',  'icon'=>'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z',               'next'=>null],
];

// Pre-fetch
$data = [];
$totales = [];
foreach ($estados as $e) {
    $data[$e]    = obtenerOrdenesPorEstado($e);
    $totales[$e] = count($data[$e]);
}
$totalActivas = $totales['Recibido'] + $totales['Lavando'] + $totales['Secando'] + $totales['Listo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Tablero — Lavandería Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    /* ── TOPBAR KANBAN ── */
    .kan-topbar { display:flex; align-items:center; justify-content:space-between; padding:20px 28px 0; margin-bottom:16px; }
    .kan-meta   { display:flex; align-items:center; gap:10px; }
    .kan-badge  { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; }
    .kan-badge.activas { background:var(--accent-bg); color:var(--accent-light); border:1px solid var(--accent-border); }
    .kan-badge.refresh { background:var(--bg-surface2); color:var(--text-sub); border:1px solid var(--border); }

    /* ── BOARD ── */
    .kboard { display:flex; gap:12px; align-items:flex-start; padding:0 28px 28px; overflow-x:auto; min-height:calc(100vh - 170px); }

    /* ── COLUMN ── */
    .kcol { flex:1; min-width:230px; max-width:300px; display:flex; flex-direction:column; gap:0; }

    .kcol__head {
      display:flex; align-items:center; gap:9px;
      padding:12px 14px; border-radius:var(--radius-md) var(--radius-md) 0 0;
      border:1px solid var(--border); border-bottom:none;
      background:var(--bg-surface);
    }
    .kcol__icon { width:28px; height:28px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .kcol__name { font-size:11px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; flex:1; }
    .kcol__cnt  { font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; min-width:22px; text-align:center; }

    .kcol--Recibido  .kcol__icon { background:var(--bg-surface3);  color:var(--text-sub);    }
    .kcol--Recibido  .kcol__name { color:var(--text-sub);           }
    .kcol--Recibido  .kcol__cnt  { background:var(--bg-surface3);   color:var(--text-sub);    }
    .kcol--Lavando   .kcol__icon { background:var(--accent-bg);     color:var(--accent-light);}
    .kcol--Lavando   .kcol__name { color:var(--accent-light);       }
    .kcol--Lavando   .kcol__cnt  { background:var(--accent-bg);     color:var(--accent-light);}
    .kcol--Secando   .kcol__icon { background:var(--amber-bg);      color:var(--amber);       }
    .kcol--Secando   .kcol__name { color:var(--amber);              }
    .kcol--Secando   .kcol__cnt  { background:var(--amber-bg);      color:var(--amber);       }
    .kcol--Listo     .kcol__icon { background:var(--green-bg);      color:var(--green);       }
    .kcol--Listo     .kcol__name { color:var(--green);              }
    .kcol--Listo     .kcol__cnt  { background:var(--green-bg);      color:var(--green);       }
    .kcol--Entregado .kcol__icon { background:var(--bg-surface3);   color:var(--text-hint);   }
    .kcol--Entregado .kcol__name { color:var(--text-hint);          }
    .kcol--Entregado .kcol__cnt  { background:var(--bg-surface3);   color:var(--text-hint);   }

    .kcol__body {
      background:var(--bg-surface2); border:1px solid var(--border);
      border-radius:0 0 var(--radius-md) var(--radius-md);
      padding:10px; min-height:180px; flex:1;
    }

    /* ── CARD ── */
    .kcard {
      background:var(--bg-surface); border:1px solid var(--border);
      border-radius:var(--radius-md); padding:13px 14px; margin-bottom:8px;
      transition:transform .15s ease, border-color .15s ease, box-shadow .15s ease;
      position:relative; overflow:hidden;
    }
    .kcard:last-child { margin-bottom:0; }
    .kcard:hover { transform:translateY(-2px); border-color:var(--border-md); box-shadow:0 6px 20px rgba(0,0,0,.25); }
    .kcard.urgente { border-left:3px solid var(--red); }
    .kcard.urgente::after {
      content:''; position:absolute; inset:0;
      background:linear-gradient(90deg,rgba(248,113,113,.04) 0%,transparent 60%);
      pointer-events:none;
    }

    .kc-top  { display:flex; align-items:flex-start; justify-content:space-between; gap:6px; margin-bottom:6px; }
    .kc-num  { font-size:10px; color:var(--text-hint); font-family:var(--font-mono); font-weight:600; }
    .kc-urg  { font-size:9px; font-weight:700; letter-spacing:.05em; color:var(--red); background:var(--red-bg); border:1px solid var(--red-border); padding:1px 6px; border-radius:4px; flex-shrink:0; }

    .kc-cli  { font-size:14px; font-weight:600; color:var(--text); margin-bottom:2px; }
    .kc-svc  { font-size:11.5px; color:var(--text-sub); }

    .kc-mid  { display:flex; align-items:center; justify-content:space-between; margin:9px 0; padding:8px 0; border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
    .kc-tiempo { display:flex; align-items:center; gap:4px; font-size:11px; }
    .kc-tiempo.ok      { color:var(--text-hint); }
    .kc-tiempo.pronto  { color:var(--amber); }
    .kc-tiempo.vencido { color:var(--red); font-weight:600; }
    .kc-monto { font-size:14px; font-weight:700; color:var(--text); }

    .kc-btns { display:flex; gap:6px; }
    .kc-btn-next {
      flex:1; padding:6px 8px; border-radius:var(--radius-sm);
      font-size:11.5px; font-weight:600; font-family:var(--font-body);
      border:1px solid var(--accent-border); background:var(--accent-bg);
      color:var(--accent-light); cursor:pointer; transition:all .15s;
      display:flex; align-items:center; justify-content:center; gap:5px;
    }
    .kc-btn-next:hover { background:var(--accent); color:#fff; }
    .kc-btn-icon {
      width:28px; height:28px; border-radius:var(--radius-sm); flex-shrink:0;
      border:1px solid var(--border); background:transparent;
      color:var(--text-hint); cursor:pointer; transition:all .15s;
      display:flex; align-items:center; justify-content:center;
    }
    .kc-btn-icon:hover { border-color:var(--border-md); color:var(--text); background:var(--bg-surface2); }

    /* Botón Listo → verde */
    .kcol--Secando .kc-btn-next { border-color:var(--green-border); background:var(--green-bg); color:var(--green); }
    .kcol--Secando .kc-btn-next:hover { background:var(--green); color:#fff; }

    /* Aviso por WhatsApp */
    .kc-wa.pendiente { position:relative; border-color:var(--green-border); color:var(--green); background:var(--green-bg); }
    .kc-wa.pendiente::after { content:''; position:absolute; top:-3px; right:-3px; width:8px; height:8px; border-radius:50%; background:var(--green); box-shadow:0 0 0 2px var(--bg-surface); }
    .kc-wa.pendiente:hover { background:var(--green); color:#fff; }
    .kc-wa.avisado { color:var(--green); }
    .wa-dest { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--bg-surface2); border:1px solid var(--border); border-radius:var(--radius-md); margin-bottom:14px; }
    .wa-dest__nom { font-size:13.5px; font-weight:600; color:var(--text); }
    .wa-dest__tel { font-size:12px; color:var(--text-hint); font-family:var(--font-mono); }
    .wa-estado { margin-left:auto; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; white-space:nowrap; }
    .wa-estado.no { background:var(--amber-bg); color:var(--amber); border:1px solid var(--amber-border); }
    .wa-estado.si { background:var(--green-bg); color:var(--green); border:1px solid var(--green-border); }
    #wa-msg { width:100%; min-height:150px; resize:vertical; font-family:var(--font-body); font-size:13px; line-height:1.5; }
    .btn-wa { background:#25D366; border-color:#25D366; color:#fff; }
    .btn-wa:hover { background:#1ebe5a; border-color:#1ebe5a; }
    .btn-wa:disabled { opacity:.5; cursor:not-allowed; }

    /* Columna vacía */
    .kcol__empty { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding:32px 0; }
    .kcol__empty svg { opacity:.25; }
    .kcol__empty p { font-size:11.5px; color:var(--text-hint); }

    /* Toast */
    #toast-kan { position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px; }
    .tkan { display:flex; align-items:center; gap:10px; padding:11px 16px; background:var(--bg-surface2); border:1px solid var(--border-md); border-radius:var(--radius-md); font-size:13px; color:var(--text); min-width:240px; animation:sfr .2s ease; box-shadow:0 4px 20px rgba(0,0,0,.4); }
    .tkan.ok  { border-color:var(--green-border); }
    .tkan.err { border-color:var(--red-border);   }
    @keyframes sfr { from{opacity:0;transform:translateX(16px)} to{opacity:1;transform:translateX(0)} }
  </style>
</head>
<body>
<div class="layout">
  <?php partial('sidebar'); ?>
  <main class="main-content" style="background:var(--bg-base);">

    <!-- TOPBAR -->
    <div class="kan-topbar">
      <div>
        <h1 class="page-title">Tablero de Órdenes</h1>
        <p class="page-sub">Flujo de trabajo en tiempo real</p>
      </div>
      <div class="topbar-actions">
        <span class="kan-badge activas">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>
          <?= $totalActivas ?> activas
        </span>
        <span class="kan-badge refresh" id="refresh-badge">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          60s
        </span>
        <a href="ordenes.php?action=nueva" class="btn btn-primary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nueva Orden
        </a>
      </div>
    </div>

    <!-- BOARD -->
    <div class="kboard">
      <?php foreach ($estados as $estado):
        $def    = $colDef[$estado];
        $orders = $data[$estado];
        $cnt    = $totales[$estado];
        $next   = $def['next'];
      ?>
      <div class="kcol kcol--<?= $estado ?>">

        <!-- Cabecera columna -->
        <div class="kcol__head">
          <div class="kcol__icon">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $def['icon'] ?></svg>
          </div>
          <span class="kcol__name"><?= $estado ?></span>
          <span class="kcol__cnt"><?= $cnt ?></span>
        </div>

        <!-- Cuerpo columna -->
        <div class="kcol__body">
          <?php if(empty($orders)): ?>
            <div class="kcol__empty">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
              <p>Sin órdenes</p>
            </div>
          <?php else: ?>
            <?php foreach($orders as $o):
              $mins    = (strtotime($o['fecha_promesa']) - time()) / 60;
              $urgente = $mins <= 120;
              $vencido = $mins < 0;
              if ($vencido) {
                $tClass = 'vencido';
                $tTxt   = 'Atrasado '.abs(round($mins)).'min';
              } elseif ($urgente) {
                $tClass = 'pronto';
                $tTxt   = 'Vence en '.round($mins).'min';
              } else {
                $h      = (int) floor($mins / 60);
                $m      = (int) $mins % 60;
                $tClass = 'ok';
                $tTxt   = ($h > 0 ? $h.'h ' : '').round($m).'min';
              }
            ?>
            <div class="kcard <?= $urgente ? 'urgente' : '' ?>">
              <div class="kc-top">
                <span class="kc-num">#<?= str_pad($o['id'],4,'0',STR_PAD_LEFT) ?></span>
                <?php if($urgente): ?><span class="kc-urg"><?= $vencido?'ATRASADO':'URGENTE' ?></span><?php endif; ?>
              </div>
              <p class="kc-cli"><?= h($o['cliente_nombre']) ?></p>
              <p class="kc-svc"><?= h($o['servicio_nombre'] ?: 'Sin servicios') ?></p>
              <?php if ((int)$o['total_prendas'] > 0): ?>
              <p class="kc-svc" data-tip="Prendas registradas en la recepción">👕 <?= (int)$o['total_prendas'] ?> prenda<?= (int)$o['total_prendas'] === 1 ? '' : 's' ?></p>
              <?php endif; ?>

              <div class="kc-mid">
                <span class="kc-tiempo <?= $tClass ?>">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?= $tTxt ?>
                </span>
                <span class="kc-monto">$<?= number_format($o['total_usd'],2) ?></span>
              </div>

              <div class="kc-btns">
                <?php if($next): ?>
                <button class="kc-btn-next" onclick="cambiarEstado(<?= $o['id'] ?>,'<?= $next ?>',<?= (int)$o['total_prendas'] ?>)" data-tip="Mover la orden a «<?= $next ?>»">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                  <?= $next ?>
                </button>
                <?php endif; ?>
                <?php if ($estado === 'Listo'): $avisado = (int) $o['notificado_whatsapp'] === 1; ?>
                <button class="kc-btn-icon kc-wa <?= $avisado ? 'avisado' : 'pendiente' ?>" onclick="abrirAviso(<?= $o['id'] ?>)"
                        data-tip="<?= $avisado ? 'Cliente ya avisado por WhatsApp · reenviar' : 'Avisar al cliente por WhatsApp (pendiente)' ?>">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.122 1.535 5.849L.057 23.776c-.073.297.021.61.247.818.226.208.545.288.839.212l6.144-1.596A11.94 11.94 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>
                </button>
                <?php endif; ?>
                <?php if ($estado === 'Recibido'): ?>
                <button class="kc-btn-icon" onclick="cancelarOrden(<?= $o['id'] ?>)" data-tip="Cancelar orden (anula el cobro y devuelve insumos)">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </button>
                <?php endif; ?>
                <button class="kc-btn-icon" onclick="verTicket(<?= $o['id'] ?>,'<?= $estado==='Entregado'?'entrega':'recepcion' ?>')" data-tip="Ver / reimprimir ticket">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                </button>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </main>
</div>
<div id="toast-kan"></div>

<!-- Aviso por WhatsApp (semiautomático: se abre WhatsApp con el mensaje listo) -->
<div id="modal-wa" class="modal-backdrop" style="display:none;">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="wa-titulo">
    <div class="modal-header">
      <h2 class="modal-title" id="wa-titulo">Avisar por WhatsApp</h2>
      <button class="modal-close" onclick="cerrarAviso()" data-tip="Cerrar sin enviar">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="wa-dest">
      <div>
        <p class="wa-dest__nom" id="wa-cliente">—</p>
        <p class="wa-dest__tel" id="wa-tel">—</p>
      </div>
      <span class="wa-estado no" id="wa-estado">Sin avisar</span>
    </div>
    <div class="form-group">
      <label class="form-label" for="wa-msg">Mensaje
        <span class="help-dot" tabindex="0" data-tip="Puedes ajustar el texto antes de enviarlo. *texto* se ve en negrita en WhatsApp.">?</span>
      </label>
      <textarea id="wa-msg" class="form-control" maxlength="1000"></textarea>
      <span class="form-hint">Se abrirá WhatsApp con este mensaje; solo tienes que pulsar «Enviar».</span>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="cerrarAviso()">Ahora no</button>
      <button class="btn btn-wa" id="wa-enviar" onclick="enviarAviso()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Abrir WhatsApp
      </button>
    </div>
  </div>
</div>
<script>
function cambiarEstado(id, estado, prendas) {
  if (estado === 'Entregado') {
    const msg = prendas > 0
      ? `Antes de entregar, verifica con el cliente que recibe ${prendas} prenda${prendas===1?'':'s'} y que el pago esté completo.\n\n¿Confirmar la entrega?`
      : '¿Confirmar la entrega de la orden y que el pago esté completo?';
    if (!confirm(msg)) return;
  }
  const fd = new FormData();
  fd.append('action','cambiar_estado');
  fd.append('orden_id', id);
  fd.append('estado', estado);
  fetch('kanban.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(!d.success){ toast(d.error||'Error al cambiar estado','err'); setTimeout(()=>location.reload(),1500); return; }
    if(estado==='Listo' && d.aviso){ mostrarAviso(d.aviso); return; }
    if(estado==='Entregado'){
      window.open('ticket.php?id='+id+'&tipo=entrega&auto=1','tkt_'+id,'width=420,height=700,scrollbars=yes');
    }
    location.reload();
  });
}
function cancelarOrden(id){
  const num = String(id).padStart(4,'0');
  const motivo = prompt('Cancelar la orden #'+num+'.\nSe anulará el cobro en caja y se devolverán los insumos.\n\nMotivo de la cancelación:');
  if (motivo === null) return;
  if (motivo.trim().length < 3) { toast('Indica un motivo (mínimo 3 caracteres)','err'); return; }
  const fd = new FormData();
  fd.append('action','cancelar'); fd.append('orden_id', id); fd.append('motivo', motivo.trim());
  fetch('kanban.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){ toast('Orden #'+num+' cancelada','ok'); setTimeout(()=>location.reload(),700); }
    else toast(d.error||'No se pudo cancelar','err');
  }).catch(()=>toast('Error de conexión','err'));
}
/* ── Aviso por WhatsApp ── */
let avisoActual = null;
function mostrarAviso(a){
  avisoActual = a;
  document.getElementById('wa-cliente').textContent = a.cliente;
  document.getElementById('wa-tel').textContent     = a.telefono;
  document.getElementById('wa-msg').value           = a.mensaje;
  const est = document.getElementById('wa-estado');
  est.textContent = a.avisado ? 'Ya avisado' : 'Sin avisar';
  est.className   = 'wa-estado ' + (a.avisado ? 'si' : 'no');
  document.getElementById('wa-enviar').disabled = !a.wa;
  if (!a.wa) toast('El cliente no tiene un teléfono válido','err');
  document.getElementById('modal-wa').style.display = 'flex';
}
function abrirAviso(id){
  const fd = new FormData(); fd.append('action','aviso_whatsapp'); fd.append('orden_id', id);
  fetch('kanban.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success) mostrarAviso(d.aviso); else toast(d.error||'No se pudo preparar el aviso','err');
  }).catch(()=>toast('Error de conexión','err'));
}
function enviarAviso(){
  const a = avisoActual, txt = document.getElementById('wa-msg').value.trim();
  if (!a || !a.wa) return;
  if (!txt) { toast('Escribe el mensaje','err'); return; }
  // Debe abrirse dentro del clic para que el navegador no lo bloquee
  window.open('https://wa.me/' + a.wa + '?text=' + encodeURIComponent(txt), '_blank', 'noopener');
  document.getElementById('modal-wa').style.display = 'none';
  avisoActual = null;
  const fd = new FormData(); fd.append('action','marcar_avisado'); fd.append('orden_id', a.orden_id);
  fetch('kanban.php',{method:'POST',body:fd}).finally(()=>{
    toast('Aviso preparado en WhatsApp','ok');
    setTimeout(()=>location.reload(), 900);
  });
}
function cerrarAviso(){
  document.getElementById('modal-wa').style.display = 'none';
  avisoActual = null;
  location.reload();
}
document.getElementById('modal-wa').addEventListener('click', e => { if (e.target === e.currentTarget) cerrarAviso(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && avisoActual) cerrarAviso(); });

function verTicket(id,tipo){
  window.open('ticket.php?id='+id+'&tipo='+(tipo||'recepcion'),'vtk_'+id,'width=420,height=700,scrollbars=yes');
}
function toast(msg,type){
  const el=document.createElement('div');
  el.className='tkan '+(type||'ok');
  el.textContent=msg;
  document.getElementById('toast-kan').appendChild(el);
  setTimeout(()=>el.remove(),3000);
}
let t=60;
const b=document.getElementById('refresh-badge');
setInterval(()=>{
  if (avisoActual) return; // no recargar mientras se prepara un aviso
  t--;
  b.innerHTML=`<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>${t}s`;
  if(t<=0) location.reload();
},1000);
</script>
</body>
</html>
<?php
function obtenerSiguienteEstado(string $actual): ?string {
    return ['Recibido'=>'Lavando','Lavando'=>'Secando','Secando'=>'Listo','Listo'=>'Entregado'][$actual] ?? null;
}
?>

<?php
require_once __DIR__ . '/config/init.php';
requireAuth('index');

$tasa  = obtenerTasaHoy();
$stats = obtenerEstadisticasDashboard();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Lavandería Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>

    /* ── GRID PRINCIPAL ─────────────────────────────────── */
    .dash-row-a { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:14px; margin-bottom:18px; }
    .dash-row-b { display:grid; grid-template-columns:1.55fr 1fr;      gap:14px; margin-bottom:18px; }
    .dash-row-c { display:grid; grid-template-columns:1fr 1.6fr;       gap:14px; }

    /* ── STAT CARDS v2 ──────────────────────────────────── */
    .kpi {
      position: relative; overflow: hidden;
      background: var(--bg-surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 20px 20px 16px;
      transition: transform .18s ease, border-color .18s ease;
    }
    .kpi:hover { transform: translateY(-2px); border-color: var(--border-md); }

    .kpi__glow {
      position: absolute; top: -30px; right: -30px;
      width: 100px; height: 100px; border-radius: 50%;
      opacity: .08; pointer-events: none;
    }
    .kpi--blue  .kpi__glow { background: var(--accent); }
    .kpi--green .kpi__glow { background: var(--green);  }
    .kpi--amber .kpi__glow { background: var(--amber);  }
    .kpi--teal  .kpi__glow { background: var(--teal);   }

    .kpi__top   { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .kpi__label { font-size:10.5px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--text-hint); }

    .kpi__icon  {
      width:36px; height:36px; border-radius:var(--radius-md);
      display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .kpi--blue  .kpi__icon { background:var(--accent-bg);  color:var(--accent-light); border:1px solid var(--accent-border); }
    .kpi--green .kpi__icon { background:var(--green-bg);   color:var(--green);         border:1px solid var(--green-border);  }
    .kpi--amber .kpi__icon { background:var(--amber-bg);   color:var(--amber);         border:1px solid var(--amber-border);  }
    .kpi--teal  .kpi__icon { background:var(--teal-bg);    color:var(--teal);          border:1px solid var(--teal-border);   }

    .kpi__val   { font-family:var(--font-display); font-size:30px; font-weight:800; color:var(--text); line-height:1; letter-spacing:-.03em; }
    .kpi__sub   { font-size:11px; color:var(--text-hint); margin-top:4px; }
    .kpi__change {
      display:inline-flex; align-items:center; gap:4px;
      font-size:11px; font-weight:600; margin-top:10px;
      padding:3px 8px; border-radius:20px;
    }
    .kpi__change.up   { background:rgba(52,211,153,.1);  color:var(--green); }
    .kpi__change.down { background:rgba(248,113,113,.1); color:var(--red);   }
    .kpi__change.flat { background:rgba(251,191,36,.1);  color:var(--amber); }

    /* ── KANBAN MINI v2 ─────────────────────────────────── */
    .kmini        { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
    .kmini__col   { background:var(--bg-surface2); border-radius:var(--radius-md); padding:10px 9px; }

    .kmini__head  {
      display:flex; align-items:center; justify-content:space-between;
      margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--border);
    }
    .kmini__name  { font-size:10px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; }
    .kmini__count {
      font-size:10px; font-weight:700; min-width:20px; height:20px;
      border-radius:10px; display:flex; align-items:center; justify-content:center;
      padding:0 5px;
    }

    .col--recibido  .kmini__name  { color:var(--text-sub);    }
    .col--recibido  .kmini__count { background:var(--bg-surface3); color:var(--text-sub); }
    .col--lavando   .kmini__name  { color:var(--accent-light); }
    .col--lavando   .kmini__count { background:var(--accent-bg);   color:var(--accent-light); }
    .col--secando   .kmini__name  { color:var(--amber);        }
    .col--secando   .kmini__count { background:var(--amber-bg);    color:var(--amber); }
    .col--listo     .kmini__name  { color:var(--green);        }
    .col--listo     .kmini__count { background:var(--green-bg);    color:var(--green); }
    .col--entregado .kmini__name  { color:var(--text-hint);   }
    .col--entregado .kmini__count { background:var(--bg-surface3); color:var(--text-hint); }

    .kmini__card  {
      background:var(--bg-surface); border:1px solid var(--border);
      border-radius:var(--radius-sm); padding:8px 9px; margin-bottom:5px;
      transition:border-color .15s;
    }
    .kmini__card:last-child { margin-bottom:0; }
    .kmini__card:hover      { border-color:var(--border-md); }
    .kmini__card.urgente    { border-left:2.5px solid var(--red); }

    .kc-num  { font-size:9.5px; color:var(--text-hint); font-family:var(--font-mono); margin-bottom:2px; }
    .kc-cli  { font-size:12px; font-weight:500; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .kc-hora { font-size:10px; color:var(--text-sub); margin-top:2px; display:flex; align-items:center; gap:3px; }
    .kc-urg  { font-size:9px; font-weight:700; color:var(--red); background:var(--red-bg); padding:1px 5px; border-radius:4px; margin-left:auto; }

    .kmini__empty { text-align:center; padding:14px 0; }
    .kmini__empty p { font-size:10.5px; color:var(--text-hint); margin-top:4px; }

    /* ── CHART CARD ─────────────────────────────────────── */
    .chart-wrap { position:relative; height:200px; }

    /* ── ALERTAS v2 ─────────────────────────────────────── */
    .alerta2       { display:flex; align-items:center; gap:11px; padding:10px 12px; border-radius:var(--radius-md); margin-bottom:6px; transition:background .15s; }
    .alerta2:last-child { margin-bottom:0; }
    .alerta2:hover { background:var(--bg-surface2); }
    .alerta2.sin   { background:rgba(248,113,113,.05); border:1px solid var(--red-border); }
    .alerta2.bajo  { background:rgba(251,191,36,.05);  border:1px solid var(--amber-border); }
    .alerta2__icon { width:32px; height:32px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .alerta2.sin  .alerta2__icon { background:var(--red-bg);   color:var(--red);   }
    .alerta2.bajo .alerta2__icon { background:var(--amber-bg); color:var(--amber); }
    .alerta2__info { flex:1; min-width:0; }
    .alerta2__nom  { font-size:13px; font-weight:500; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .alerta2__stk  { font-size:11px; color:var(--text-sub); margin-top:1px; }
    .alerta2__bdg  { font-size:10px; font-weight:700; padding:2px 8px; border-radius:20px; flex-shrink:0; text-transform:uppercase; letter-spacing:.04em; }
    .alerta2.sin  .alerta2__bdg { background:var(--red-bg);   color:var(--red);   border:1px solid var(--red-border);   }
    .alerta2.bajo .alerta2__bdg { background:var(--amber-bg); color:var(--amber); border:1px solid var(--amber-border); }

    .ok-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:28px 0; gap:8px; }
    .ok-state svg { color:var(--green); opacity:.7; }
    .ok-state p  { font-size:13px; color:var(--text-hint); }

    /* ── TABLA ÚLTIMAS ÓRDENES v2 ───────────────────────── */
    .tbl { width:100%; border-collapse:collapse; font-size:13px; }
    .tbl thead th {
      padding:9px 12px; font-size:10px; font-weight:600; letter-spacing:.08em;
      text-transform:uppercase; color:var(--text-hint);
      border-bottom:1px solid var(--border); text-align:left;
    }
    .tbl tbody td { padding:11px 12px; border-bottom:1px solid var(--border); color:var(--text); vertical-align:middle; }
    .tbl tbody tr:last-child td { border-bottom:none; }
    .tbl tbody tr:hover td { background:var(--bg-surface2); }

    .tord-num  { font-family:var(--font-mono); font-size:11.5px; color:var(--accent-light); font-weight:600; }
    .tord-cli  { font-weight:500; }
    .tord-svc  { color:var(--text-sub); font-size:12px; }
    .tord-mnt  { color:var(--green); font-weight:700; font-size:13.5px; white-space:nowrap; }
    .tord-bs   { font-size:10.5px; color:var(--text-hint); }

    /* estado badges */
    .eb { display:inline-flex; align-items:center; gap:4px; font-size:10.5px; font-weight:600; padding:3px 9px; border-radius:20px; white-space:nowrap; }
    .eb::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .eb-Recibido  { background:var(--bg-surface3);  color:var(--text-sub);    }
    .eb-Lavando   { background:var(--accent-bg);    color:var(--accent-light);}
    .eb-Secando   { background:var(--amber-bg);     color:var(--amber);       }
    .eb-Listo     { background:var(--green-bg);     color:var(--green);       }
    .eb-Entregado { background:rgba(100,116,139,.12); color:var(--text-hint); }
    .eb-Cancelado { background:var(--red-bg);       color:var(--red);         }

    /* ── CARD RESUMEN TASA ──────────────────────────────── */
    .tasa-card {
      background: linear-gradient(135deg, var(--accent-bg) 0%, transparent 70%);
      border: 1px solid var(--accent-border); border-radius:var(--radius-lg);
      padding:18px 20px; display:flex; align-items:center; gap:14px;
    }
    .tasa-card__icon { width:44px; height:44px; border-radius:var(--radius-md); background:var(--accent-bg); border:1px solid var(--accent-border); display:flex; align-items:center; justify-content:center; color:var(--accent-light); flex-shrink:0; }
    .tasa-card__lbl  { font-size:10px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--text-hint); }
    .tasa-card__val  { font-family:var(--font-display); font-size:22px; font-weight:800; color:var(--text); letter-spacing:-.02em; }
    .tasa-card__sub  { font-size:11px; color:var(--text-sub); margin-top:2px; }

    @media(max-width:1300px) {
      .dash-row-a { grid-template-columns:1fr 1fr; }
      .dash-row-b { grid-template-columns:1fr; }
      .kmini      { grid-template-columns:repeat(3,1fr); }
    }
    @media(max-width:900px) {
      .dash-row-a { grid-template-columns:1fr 1fr; }
      .dash-row-c { grid-template-columns:1fr; }
      .kmini      { grid-template-columns:1fr 1fr; }
    }
  </style>
</head>
<body>
<div class="layout">

  <?php partial('sidebar'); ?>

  <main class="main-content">
    <div class="topbar">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub"><?= ucfirst(fechaLarga()) ?></p>
      </div>
      <div class="topbar-actions">
        <a href="reportes.php" class="btn btn-secondary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Reportes
        </a>
        <a href="ordenes.php?action=nueva" class="btn btn-primary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nueva Orden
        </a>
      </div>
    </div>

    <div class="content-area">

      <!-- ── FILA A: 4 KPIs ──────────────────────────── -->
      <div class="dash-row-a">

        <!-- Ingresos -->
        <div class="kpi kpi--blue">
          <div class="kpi__glow"></div>
          <div class="kpi__top">
            <span class="kpi__label">Ingresos hoy</span>
            <div class="kpi__icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
          <p class="kpi__val">$<?= number_format($stats['ingresos_usd_hoy'], 2) ?></p>
          <p class="kpi__sub">Bs. <?= number_format($stats['ingresos_usd_hoy'] * $tasa['tasa_bcv'], 2, ',', '.') ?></p>
          <?php $vi = $stats['variacion_ingresos']; ?>
          <span class="kpi__change <?= $vi > 0 ? 'up' : ($vi < 0 ? 'down' : 'flat') ?>">
            <?php if($vi > 0): ?>
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
            <?php elseif($vi < 0): ?>
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"/></svg>
            <?php endif; ?>
            <?= $vi >= 0 ? '+' : '' ?><?= $vi ?>% vs ayer
          </span>
        </div>

        <!-- Órdenes hoy -->
        <div class="kpi kpi--green">
          <div class="kpi__glow"></div>
          <div class="kpi__top">
            <span class="kpi__label">Órdenes hoy</span>
            <div class="kpi__icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
          </div>
          <p class="kpi__val"><?= $stats['ordenes_hoy'] ?></p>
          <p class="kpi__sub">órdenes registradas</p>
          <?php $vo = $stats['variacion_ordenes']; ?>
          <span class="kpi__change <?= $vo > 0 ? 'up' : ($vo < 0 ? 'down' : 'flat') ?>">
            <?= $vo >= 0 ? '+' : '' ?><?= $vo ?> vs ayer
          </span>
        </div>

        <!-- Pendientes -->
        <div class="kpi kpi--amber">
          <div class="kpi__glow"></div>
          <div class="kpi__top">
            <span class="kpi__label">Pendientes</span>
            <div class="kpi__icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
          </div>
          <p class="kpi__val"><?= $stats['ordenes_pendientes'] ?></p>
          <p class="kpi__sub">en proceso activo</p>
          <?php if($stats['ordenes_urgentes'] > 0): ?>
          <span class="kpi__change down">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
            <?= $stats['ordenes_urgentes'] ?> urgente<?= $stats['ordenes_urgentes'] > 1 ? 's' : '' ?>
          </span>
          <?php else: ?>
          <span class="kpi__change flat">Sin urgentes</span>
          <?php endif; ?>
        </div>

        <!-- Kg procesados -->
        <div class="kpi kpi--teal">
          <div class="kpi__glow"></div>
          <div class="kpi__top">
            <span class="kpi__label">Kg procesados</span>
            <div class="kpi__icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </div>
          </div>
          <p class="kpi__val"><?= number_format($stats['kg_hoy'], 1) ?><span style="font-size:16px;font-weight:500;margin-left:4px;">kg</span></p>
          <p class="kpi__sub">ropa procesada hoy</p>
          <?php $vk = $stats['variacion_kg']; ?>
          <span class="kpi__change <?= $vk >= 0 ? 'up' : 'down' ?>">
            <?= $vk >= 0 ? '+' : '' ?><?= $vk ?> kg vs ayer
          </span>
        </div>

      </div><!-- /dash-row-a -->

      <!-- ── FILA B: KANBAN MINI + GRÁFICA ──────────── -->
      <div class="dash-row-b">

        <!-- Kanban mini -->
        <div class="card">
          <div class="card-header">
            <div>
              <h2 class="card-title">Estado de Órdenes</h2>
              <p style="font-size:11px;color:var(--text-hint);margin-top:2px;">Vista rápida del flujo de trabajo</p>
            </div>
            <a href="kanban.php" class="btn btn-secondary btn-sm">
              Ver tablero
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
          </div>

          <div class="kmini">
            <?php
            $cols_def = [
              'Recibido'  => 'recibido',
              'Lavando'   => 'lavando',
              'Secando'   => 'secando',
              'Listo'     => 'listo',
              'Entregado' => 'entregado',
            ];
            foreach ($cols_def as $estado => $cls):
              $count   = contarOrdenesPorEstado($estado);
              $ordenes = obtenerOrdenesPorEstado($estado, 3);
            ?>
            <div class="kmini__col col--<?= $cls ?>">
              <div class="kmini__head">
                <span class="kmini__name"><?= $estado ?></span>
                <span class="kmini__count"><?= $count ?></span>
              </div>
              <?php if(empty($ordenes)): ?>
                <div class="kmini__empty">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-hint);opacity:.4"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                  <p>Vacío</p>
                </div>
              <?php else: ?>
                <?php foreach($ordenes as $o):
                  $urgente = strtotime($o['fecha_promesa']) <= strtotime('+2 hours');
                ?>
                <div class="kmini__card <?= $urgente ? 'urgente' : '' ?>">
                  <p class="kc-num">#<?= str_pad($o['id'],4,'0',STR_PAD_LEFT) ?></p>
                  <p class="kc-cli"><?= h($o['cliente_nombre']) ?></p>
                  <div class="kc-hora">
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= date('h:i a', strtotime($o['fecha_promesa'])) ?>
                    <?php if($urgente): ?>
                    <span class="kc-urg">URGENTE</span>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Gráfica -->
        <div class="card">
          <div class="card-header">
            <div>
              <h2 class="card-title">Ingresos — 7 días</h2>
              <p style="font-size:11px;color:var(--text-hint);margin-top:2px;">En USD</p>
            </div>
          </div>
          <div class="chart-wrap">
            <canvas id="chartIngresos"></canvas>
          </div>
        </div>

      </div><!-- /dash-row-b -->

      <!-- ── FILA C: ALERTAS + ÚLTIMAS ÓRDENES ──────── -->
      <div class="dash-row-c">

        <!-- Alertas inventario -->
        <div class="card">
          <div class="card-header">
            <div>
              <h2 class="card-title">Alertas de Inventario</h2>
              <p style="font-size:11px;color:var(--text-hint);margin-top:2px;">Stock bajo o agotado</p>
            </div>
            <a href="inventario.php" class="btn btn-secondary btn-sm">Ver todo →</a>
          </div>
          <?php $alertas = obtenerAlertasStock(); ?>
          <?php if(empty($alertas)): ?>
            <div class="ok-state">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <p>Inventario en orden ✓</p>
            </div>
          <?php else: ?>
            <?php foreach($alertas as $item):
              $sin = $item['stock_actual'] == 0;
            ?>
            <div class="alerta2 <?= $sin ? 'sin' : 'bajo' ?>">
              <div class="alerta2__icon">
                <?php if($sin): ?>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                <?php else: ?>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <?php endif; ?>
              </div>
              <div class="alerta2__info">
                <p class="alerta2__nom"><?= h($item['nombre']) ?></p>
                <p class="alerta2__stk"><?= $item['stock_actual'] ?> <?= $item['unidad'] ?> — mínimo: <?= $item['stock_minimo'] ?></p>
              </div>
              <span class="alerta2__bdg"><?= $sin ? 'Sin stock' : 'Stock bajo' ?></span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Últimas órdenes -->
        <div class="card">
          <div class="card-header">
            <div>
              <h2 class="card-title">Últimas Órdenes</h2>
              <p style="font-size:11px;color:var(--text-hint);margin-top:2px;">Actividad reciente</p>
            </div>
            <a href="ordenes.php" class="btn btn-secondary btn-sm">Ver todas →</a>
          </div>
          <table class="tbl">
            <thead>
              <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Servicio</th>
                <th>Total</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach(obtenerUltimasOrdenes(6) as $o): ?>
              <tr>
                <td class="tord-num">#<?= str_pad($o['id'],4,'0',STR_PAD_LEFT) ?></td>
                <td class="tord-cli"><?= h($o['cliente_nombre']) ?></td>
                <td class="tord-svc"><?= h($o['servicio_nombre'] ?? '—') ?></td>
                <td>
                  <span class="tord-mnt">$<?= number_format($o['total_usd'],2) ?></span>
                </td>
                <td><span class="eb eb-<?= $o['estado'] ?>"><?= $o['estado'] ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /dash-row-c -->

    </div><!-- /content-area -->
  </main>
</div><!-- /layout -->

<?php $chart = obtenerDatosChart7dias(); ?>
<script>
(function(){
  const labels  = <?= json_encode($chart['labels'])  ?>;
  const valores = <?= json_encode($chart['valores']) ?>;
  const style   = getComputedStyle(document.documentElement);
  const accent  = style.getPropertyValue('--accent').trim()       || '#2563eb';
  const accentL = style.getPropertyValue('--accent-light').trim() || '#60a5fa';
  const textSub = style.getPropertyValue('--text-sub').trim()     || '#8a95a3';
  const grid    = style.getPropertyValue('--border').trim()       || 'rgba(255,255,255,0.07)';
  const surface = style.getPropertyValue('--bg-surface2').trim()  || '#19222d';

  // Gradiente bajo las barras
  const ctx = document.getElementById('chartIngresos').getContext('2d');
  const grad = ctx.createLinearGradient(0, 0, 0, 200);
  grad.addColorStop(0,   accentL.replace(')', ', 0.3)').replace('rgb','rgba') || 'rgba(96,165,250,0.3)');
  grad.addColorStop(1,   accentL.replace(')', ', 0.0)').replace('rgb','rgba') || 'rgba(96,165,250,0)');

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        data: valores,
        backgroundColor: grad,
        borderColor: accentL,
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false,
        hoverBackgroundColor: accentL.replace(')', ', 0.4)').replace('rgb','rgba') || 'rgba(96,165,250,0.4)',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: surface,
          borderColor: grid,
          borderWidth: 1,
          titleColor: accentL,
          bodyColor: textSub,
          padding: 10,
          callbacks: { label: ctx => ' $' + ctx.parsed.y.toFixed(2) + ' USD' }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: grid },
          ticks: { color: textSub, callback: v => '$' + v, font: { size: 11 } },
          border: { display: false },
        },
        x: {
          grid: { display: false },
          ticks: { color: textSub, font: { size: 11 } },
          border: { display: false },
        }
      }
    }
  });
})();
</script>
</body>
</html>

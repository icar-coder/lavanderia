<?php
require_once 'config/init.php';
requireAdmin('reportes');

$db   = getDB();
$tasa = obtenerTasaHoy();
$meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril',
          '05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto',
          '09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];

/* ── Modo de visualización ───────────────────────────────────
   ?vista=mes  → filtro mes/año (pantalla y pdf)
   ?vista=rango → rango de fechas libre (pantalla y pdf)       */
$vista = entrada($_GET, 'vista', 'mes') === 'rango' ? 'rango' : 'mes';

if ($vista === 'rango') {
    // Fechas reales; si no lo son se usa el mes en curso
    $desde    = fechaValida(entrada($_GET, 'desde')) ?? date('Y-m-01');
    $hasta    = fechaValida(entrada($_GET, 'hasta')) ?? date('Y-m-d');
    if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
    $rangoIni = $desde;
    $rangoFin = $hasta;
    $labelPeriodo = date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta));
} else {
    $mes      = str_pad(entrada($_GET, 'mes', date('m')), 2, '0', STR_PAD_LEFT);
    if (!isset($meses[$mes])) $mes = date('m');
    $anio     = (int) entrada($_GET, 'anio', date('Y'));
    if ($anio < 2000 || $anio > (int) date('Y') + 1) $anio = (int) date('Y');
    $rangoIni = "$anio-$mes-01";
    $rangoFin = date('Y-m-t', strtotime($rangoIni));
    $labelPeriodo = ($meses[$mes] ?? '') . ' ' . $anio;
    $desde    = $rangoIni;
    $hasta    = $rangoFin;
}

/* ── Consultas de pantalla ───────────────────────────────────  */
// Ingresos por día
$stDia = $db->prepare("
    SELECT DAY(fecha_recepcion) dia, DATE(fecha_recepcion) fecha_full,
           COALESCE(SUM(total_usd),0) usd, COUNT(*) ords
    FROM ordenes
    WHERE DATE(fecha_recepcion) BETWEEN ? AND ? AND estado!='Cancelado'
    GROUP BY DATE(fecha_recepcion) ORDER BY fecha_full ASC");
$stDia->execute([$rangoIni, $rangoFin]);
$ingresosDia = $stDia->fetchAll();

// Top servicios
$stSvc = $db->prepare("
    SELECT s.nombre, COUNT(*) cantidad, COALESCE(SUM(d.subtotal),0) monto
    FROM detalle_orden d
    JOIN servicios s ON d.servicio_id=s.id
    JOIN ordenes o   ON d.orden_id=o.id
    WHERE DATE(o.fecha_recepcion) BETWEEN ? AND ? AND o.estado!='Cancelado'
    GROUP BY s.id ORDER BY cantidad DESC LIMIT 6");
$stSvc->execute([$rangoIni, $rangoFin]);
$topServicios = $stSvc->fetchAll();

// KPIs
$stK = $db->prepare("SELECT COUNT(*) ords, COALESCE(SUM(total_usd),0) usd,
                     COALESCE(SUM(total_bs),0) bs FROM ordenes
                     WHERE DATE(fecha_recepcion) BETWEEN ? AND ? AND estado!='Cancelado'");
$stK->execute([$rangoIni,$rangoFin]);
$kpi = $stK->fetch();

$stKg = $db->prepare("SELECT COALESCE(SUM(d.cantidad),0) FROM detalle_orden d
    JOIN ordenes o ON d.orden_id=o.id JOIN servicios s ON d.servicio_id=s.id
    WHERE DATE(o.fecha_recepcion) BETWEEN ? AND ? AND s.unidad_medida='Kg' AND o.estado!='Cancelado'");
$stKg->execute([$rangoIni,$rangoFin]);
$totalKg = (float)$stKg->fetchColumn();

$totalMes    = (float)$kpi['usd'];
$totalBS     = (float)$kpi['bs'] > 0 ? (float)$kpi['bs'] : $totalMes * $tasa['tasa_bcv'];
$totalOrdenes= (int)$kpi['ords'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reportes — Lavandería Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    .vista-tabs { display:flex; gap:0; margin-bottom:20px; border:1px solid var(--border-md); border-radius:var(--radius-sm); overflow:hidden; width:fit-content; }
    .vista-tab  { padding:7px 18px; font-size:13px; font-weight:500; background:transparent; border:none; cursor:pointer; color:var(--text-sub); font-family:var(--font-body); transition:all var(--t); }
    .vista-tab.active { background:var(--accent); color:#fff; }
    .vista-tab:hover:not(.active) { background:var(--bg-surface2); color:var(--text); }
    .export-card { background:var(--accent-bg); border:1px solid var(--accent-border); border-radius:var(--radius-lg); padding:20px 22px; margin-bottom:20px; }
    .export-card-title { font-family:var(--font-display); font-size:14px; font-weight:700; color:var(--accent-light); margin-bottom:14px; display:flex; align-items:center; gap:8px; }
    .filtro-form { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
    .filtro-group { display:flex; flex-direction:column; gap:5px; }
    .filtro-label { font-size:10.5px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; color:var(--text-sub); }
  </style>
</head>
<body>
<div class="layout">
  <?php partial('sidebar'); ?>
  <main class="main-content">
    <div class="topbar">
      <div>
        <h1 class="page-title">Reportes</h1>
        <p class="page-sub"><?= h($labelPeriodo) ?></p>
      </div>
    </div>

    <div class="content-area">

      <!-- ── PANEL DE FILTROS Y EXPORTACIÓN ── -->
      <div class="export-card">
        <div class="export-card-title">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
            <polyline points="10 9 9 9 8 9"/>
          </svg>
          Filtros y Exportación PDF
        </div>

        <!-- Tabs de modo -->
        <div class="vista-tabs" style="margin-bottom:16px;">
          <button class="vista-tab <?= $vista==='mes'?'active':'' ?>"
                  onclick="cambiarVista('mes')">Por Mes</button>
          <button class="vista-tab <?= $vista==='rango'?'active':'' ?>"
                  onclick="cambiarVista('rango')">Rango de Fechas</button>
        </div>

        <!-- Formulario MES -->
        <form id="form-mes" method="get" class="filtro-form" style="<?= $vista==='rango'?'display:none':'' ?>">
          <input type="hidden" name="vista" value="mes">
          <div class="filtro-group">
            <span class="filtro-label">Mes</span>
            <select name="mes" class="form-control" style="width:140px;">
              <?php foreach($meses as $k=>$v): ?>
              <option value="<?=$k?>" <?=$k==($mes??date('m'))?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filtro-group">
            <span class="filtro-label">Año</span>
            <select name="anio" class="form-control" style="width:95px;">
              <?php for($y=date('Y');$y>=date('Y')-4;$y--): ?>
              <option value="<?=$y?>" <?=$y==($anio??date('Y'))?'selected':''?>><?=$y?></option>
              <?php endfor; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Ver Reporte
          </button>
          <button type="button" class="btn btn-secondary" onclick="exportarPDF('mes')" style="gap:7px;" data-tip="Descargar el reporte del mes en PDF">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/></svg>
            Exportar PDF
          </button>
        </form>

        <!-- Formulario RANGO -->
        <form id="form-rango" method="get" class="filtro-form" style="<?= $vista==='mes'?'display:none':'' ?>">
          <input type="hidden" name="vista" value="rango">
          <div class="filtro-group">
            <span class="filtro-label">Desde</span>
            <input type="date" name="desde" class="form-control"
                   value="<?= h($desde) ?>" max="<?= date('Y-m-d') ?>">
          </div>
          <div class="filtro-group">
            <span class="filtro-label">Hasta</span>
            <input type="date" name="hasta" class="form-control"
                   value="<?= h($hasta) ?>" max="<?= date('Y-m-d') ?>">
          </div>
          <button type="submit" class="btn btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Ver Reporte
          </button>
          <button type="button" class="btn btn-secondary" onclick="exportarPDF('rango')" style="gap:7px;" data-tip="Descargar el reporte del rango de fechas en PDF">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/></svg>
            Exportar PDF
          </button>
        </form>

        <p style="font-size:11.5px;color:var(--text-hint);margin-top:12px;">
          El PDF incluye: resumen ejecutivo · ingresos por día en USD y Bs. · análisis por servicio ·
          métodos de pago · top 10 clientes · egresos · balance neto final.
        </p>
        <hr class="divider" style="margin:14px 0 12px;">
        <p style="font-size:11px;color:var(--text-sub);margin-bottom:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Otros reportes PDF</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="clientes_pdf.php?orden=nombre" target="_blank" class="btn btn-secondary btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            Clientes por Nombre
          </a>
          <a href="clientes_pdf.php?orden=gasto" target="_blank" class="btn btn-secondary btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            Clientes por Gasto
          </a>
          <a href="clientes_pdf.php?orden=ordenes" target="_blank" class="btn btn-secondary btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
            Clientes más Activos
          </a>
          <a href="inventario_pdf.php" target="_blank" class="btn btn-secondary btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 2 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            Inventario Completo
          </a>
          <a href="inventario_pdf.php?solo=bajos" target="_blank" class="btn btn-danger btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
            Solo Alertas Stock
          </a>
        </div>
      </div>

      <!-- ── KPIs ── -->
      <div class="stats-grid" style="margin-bottom:20px;">
        <div class="stat-card stat-card--blue">
          <div class="stat-icon stat-icon--blue">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <div>
            <p class="stat-label">Ingresos USD</p>
            <p class="stat-value"><?= formatUSD($totalMes) ?></p>
            <p class="stat-change neutral"><?= formatBS($totalBS) ?></p>
          </div>
        </div>
        <div class="stat-card stat-card--green">
          <div class="stat-icon stat-icon--green">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
          </div>
          <div>
            <p class="stat-label">Órdenes</p>
            <p class="stat-value"><?= $totalOrdenes ?></p>
            <p class="stat-change neutral"><?= $totalOrdenes > 0 ? formatUSD($totalMes/$totalOrdenes).' prom.' : '—' ?></p>
          </div>
        </div>
        <div class="stat-card stat-card--teal">
          <div class="stat-icon stat-icon--teal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12a4 4 0 0 1 8 0"/></svg>
          </div>
          <div>
            <p class="stat-label">Kg Procesados</p>
            <p class="stat-value"><?= number_format($totalKg, 1) ?> kg</p>
            <p class="stat-change neutral"><?= $totalKg > 0 ? formatUSD($totalMes/$totalKg).'/kg' : '—' ?></p>
          </div>
        </div>
        <div class="stat-card stat-card--amber">
          <div class="stat-icon stat-icon--amber">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          </div>
          <div>
            <p class="stat-label">Tasa BCV hoy</p>
            <p class="stat-value" style="font-size:20px;">Bs. <?= number_format($tasa['tasa_bcv'], 2, ',', '.') ?></p>
            <p class="stat-change neutral"><?= formatBS($totalMes * $tasa['tasa_bcv']) ?> total</p>
          </div>
        </div>
      </div>

      <!-- ── GRÁFICAS ── -->
      <div class="grid-2" style="margin-bottom:16px;">
        <div class="card">
          <div class="card-header"><h2 class="card-title">Ingresos diarios (USD)</h2></div>
          <canvas id="chartDia" height="200"></canvas>
        </div>
        <div class="card">
          <div class="card-header"><h2 class="card-title">Top servicios</h2></div>
          <canvas id="chartSvc" height="200"></canvas>
        </div>
      </div>

      <!-- ── TABLA SERVICIOS ── -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Servicios más solicitados</h2>
          <span class="text-sub" style="font-size:12px;">Período: <?= h($labelPeriodo) ?></span>
        </div>
        <div class="table-wrapper" style="border:none;">
          <table class="data-table">
            <thead><tr>
              <th>Servicio</th><th>Cantidad</th>
              <th>Monto USD</th><th>Monto Bs.</th><th>% del Total</th>
            </tr></thead>
            <tbody>
              <?php if(empty($topServicios)): ?>
              <tr><td colspan="5"><div class="empty-state"><p>Sin datos para el período seleccionado</p></div></td></tr>
              <?php else: foreach($topServicios as $s):
                $pct = $totalMes > 0 ? round($s['monto']/$totalMes*100,1) : 0;
                $sBS = (float)$s['monto'] * $tasa['tasa_bcv'];
              ?>
              <tr>
                <td style="font-weight:500"><?= h($s['nombre']) ?></td>
                <td><?= $s['cantidad'] ?></td>
                <td class="text-accent"><?= formatUSD($s['monto']) ?></td>
                <td class="text-sub"><?= formatBS($sBS) ?></td>
                <td>
                  <div class="flex-gap">
                    <div class="stock-bar-wrap" style="width:80px;">
                      <div class="stock-bar ok" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="text-sub" style="font-size:12px;"><?= $pct ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /content-area -->
  </main>
</div>

<script>
/* ── Datos para gráficas ── */
const diasLabels = <?= json_encode(array_map(fn($r)=>date('d/m',strtotime($r['fecha_full']??date('Y-m-').$r['dia'])), $ingresosDia)) ?>;
const diasData   = <?= json_encode(array_map(fn($r)=>round($r['usd'],2), $ingresosDia)) ?>;
const svcLabels  = <?= json_encode(array_column($topServicios,'nombre')) ?>;
const svcData    = <?= json_encode(array_map(fn($r)=>round($r['monto'],2), $topServicios)) ?>;

const getVar = v => getComputedStyle(document.documentElement).getPropertyValue(v).trim();

const cfg = {
  responsive:true,
  plugins:{legend:{display:false}},
  scales:{
    y:{beginAtZero:true,grid:{color:'rgba(128,128,128,0.08)'},
       ticks:{color:getVar('--text-sub')||'#8a95a3',callback:v=>'$'+v}},
    x:{grid:{display:false},ticks:{color:getVar('--text-sub')||'#8a95a3'}}
  }
};

new Chart(document.getElementById('chartDia'),{
  type:'bar',
  data:{labels:diasLabels,datasets:[{
    data:diasData,
    backgroundColor:'rgba(37,99,235,0.25)',
    borderColor:getVar('--accent-light')||'#60a5fa',
    borderWidth:2,borderRadius:5
  }]},
  options:cfg
});

new Chart(document.getElementById('chartSvc'),{
  type:'doughnut',
  data:{labels:svcLabels,datasets:[{
    data:svcData,
    backgroundColor:['rgba(37,99,235,0.75)','rgba(52,211,153,0.75)','rgba(251,191,36,0.75)',
                     'rgba(45,212,191,0.75)','rgba(167,139,250,0.75)','rgba(248,113,113,0.75)'],
    borderColor:getVar('--bg-surface')||'#111820',
    borderWidth:2
  }]},
  options:{
    responsive:true,
    plugins:{legend:{position:'bottom',labels:{color:getVar('--text-sub')||'#8a95a3',font:{size:11},padding:10}}}
  }
});

/* ── Cambiar modo de filtro ── */
function cambiarVista(v) {
  document.getElementById('form-mes').style.display   = v==='mes'   ? 'flex' : 'none';
  document.getElementById('form-rango').style.display = v==='rango' ? 'flex' : 'none';
  document.querySelectorAll('.vista-tab').forEach(t => t.classList.remove('active'));
  event.target.classList.add('active');
}

/* ── Exportar PDF ── */
function exportarPDF(modo) {
  let url = 'reporte_pdf.php?';
  if (modo === 'mes') {
    const mes  = document.querySelector('[name="mes"]').value;
    const anio = document.querySelector('[name="anio"]').value;
    const desde = anio+'-'+mes.padStart(2,'0')+'-01';
    // último día del mes
    const hasta = new Date(anio, parseInt(mes), 0);
    const h = `${anio}-${mes.padStart(2,'0')}-${String(hasta.getDate()).padStart(2,'0')}`;
    url += `desde=${desde}&hasta=${h}`;
  } else {
    const desde = document.querySelector('[name="desde"]').value;
    const hasta = document.querySelector('[name="hasta"]').value;
    if (!desde || !hasta) { alert('Selecciona el rango de fechas'); return; }
    url += `desde=${desde}&hasta=${hasta}`;
  }

  // Indicar que se está descargando
  const btn = event.target;
  const orig = btn.innerHTML;
  btn.innerHTML = '⏳ Generando PDF...';
  btn.disabled  = true;
  setTimeout(() => { btn.innerHTML=orig; btn.disabled=false; }, 4000);

  window.open(url, '_blank');
}
</script>
</body>
</html>

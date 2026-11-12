<?php
/**
 * reporte_pdf.php — Reporte de Ventas (mPDF)
 * GET: desde=YYYY-MM-DD  hasta=YYYY-MM-DD
 */
require_once __DIR__ . '/config/init.php';
requireAdmin();

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:40px;max-width:560px;margin:60px auto;
         background:#fff;border-radius:12px;border:1px solid #e5e7eb;">
      <h2 style="color:#e11d48;">⚠️ mPDF no instalado</h2>
      <p style="margin:12px 0 0;color:#444;line-height:1.7;">Ejecuta en la carpeta del proyecto:</p>
      <pre style="background:#1e293b;color:#60a5fa;padding:14px;border-radius:8px;margin:10px 0;">composer require mpdf/mpdf</pre>
      <p style="color:#666;font-size:13px;">Composer: <a href="https://getcomposer.org" style="color:#2563eb;">getcomposer.org</a></p>
    </div>');
}
require_once $autoload;

/* ── Parámetros ─────────────────────────────────────────────── */
$desde = fechaValida(entrada($_GET, 'desde')) ?? date('Y-m-01');
$hasta = fechaValida(entrada($_GET, 'hasta')) ?? date('Y-m-d');
if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

/* ── Datos ──────────────────────────────────────────────────── */
$d        = obtenerDatosReporte($desde, $hasta);
$kpis     = $d['kpis'];
$porDia   = $d['porDia'];
$svcList  = $d['servicios'];
$metPago  = $d['metodosPago'];
$clientes = $d['topClientes'];
$egresos  = $d['egresos'];
$egTot    = (float)$d['totalEgresosUSD'];
$tasa     = (float)$d['tasaPromedio'];
$tasaHoy  = obtenerTasaHoy();

$ingUSD    = (float)$kpis['ingresos_usd'];
$ingBS     = $ingUSD * $tasa;
$balance   = $ingUSD - $egTot;
$balanceBS = $balance * $tasa;

$desde_lbl = date('d/m/Y', strtotime($desde));
$hasta_lbl = date('d/m/Y', strtotime($hasta));
$emitido   = date('d/m/Y H:i');
$usuario   = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Sistema');

/* ── Helpers ────────────────────────────────────────────────── */
function usd(float $v): string { return '$'.number_format($v,2,'.',','); }
function bs(float $v):  string { return 'Bs.&nbsp;'.number_format($v,2,',','.'); }
function fn2(float $v, int $d=2): string { return number_format($v,$d,'.',','); }
function porcentaje(float $p, float $t): string {
    return $t > 0 ? number_format(($p/$t)*100,1).'%' : '0%';
}

/* ── Colores ────────────────────────────────────────────────── */
$AZ='#1d4ed8'; $AZL='#eff6ff'; $AZM='#bfdbfe';
$VE='#15803d'; $VEL='#f0fdf4'; $VEM='#bbf7d0';
$RO='#b91c1c'; $ROL='#fef2f2'; $ROM='#fecaca';
$AM='#b45309'; $AML='#fffbeb'; $AMM='#fde68a';
$GR='#1f2937'; $GRL='#f8fafc'; $GRM='#e2e8f0'; $GRT='#64748b'; $GRH='#f1f5f9';

/* ── CSS ────────────────────────────────────────────────────── */
$css = <<<CSS
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #1f2937; line-height: 1.45; }

/* Cabecera */
.brand { font-size: 20pt; font-weight: bold; color: {$AZ}; }
.periodo { font-size: 10pt; color: {$GR}; margin-top: 3px; }
.meta { font-size: 8.5pt; color: {$GRT}; text-align: right; }

/* Títulos de sección */
h2 {
    font-size: 11pt; font-weight: bold; color: {$AZ};
    border-bottom: 2px solid {$AZ}; padding-bottom: 4px;
    margin: 0 0 10px 0;
}

/* KPI boxes */
.kbox {
    border: 1.5px solid {$GRM}; border-radius: 6px;
    padding: 10px 12px; text-align: center;
    background: {$GRL};
}
.kbox-blue  { border-color: {$AZM}; background: {$AZL}; }
.kbox-green { border-color: {$VEM}; background: {$VEL}; }
.kbox-red   { border-color: {$ROM}; background: {$ROL}; }
.kbox-amber { border-color: {$AMM}; background: {$AML}; }

.kval { font-size: 15pt; font-weight: bold; color: {$AZ}; display: block; line-height: 1.2; }
.kval-g { color: {$VE}; }
.kval-r { color: {$RO}; }
.kval-a { color: {$AM}; }
.kbs    { font-size: 8pt; color: {$GRT}; display: block; margin-top: 3px; }
.klbl   { font-size: 7.5pt; color: {$GRT}; display: block; margin-top: 5px;
          text-transform: uppercase; letter-spacing: 0.6px; }

/* Tablas */
table.data { width: 100%; border-collapse: collapse; }
table.data thead th {
    background: {$AZ}; color: #fff;
    padding: 7px 9px; font-size: 9pt; font-weight: bold;
    text-align: left; border: none;
}
table.data thead th.r { text-align: right; }
table.data thead th.c { text-align: center; }
table.data tbody td {
    padding: 7px 9px; font-size: 9.5pt;
    border-bottom: 1px solid {$GRM}; color: {$GR};
    vertical-align: middle;
}
table.data tbody tr.alt td { background: {$GRH}; }
table.data tfoot td {
    padding: 7px 9px; font-size: 9.5pt; font-weight: bold;
    border-top: 2px solid {$GRM};
}
.r { text-align: right; }
.c { text-align: center; }
.bold { font-weight: bold; }
.az   { color: {$AZ}; font-weight: bold; }
.ve   { color: {$VE}; font-weight: bold; }
.ro   { color: {$RO}; font-weight: bold; }
.am   { color: {$AM}; }
.sub  { color: {$GRT}; font-size: 8.5pt; }

/* Badge */
.badge { border-radius: 12px; padding: 2px 9px; font-size: 8pt; font-weight: bold; }
.b-az  { background: {$AZL}; color: {$AZ}; }
.b-ro  { background: {$ROL}; color: {$RO}; }
.b-ve  { background: {$VEL}; color: {$VE}; }

/* Sección */
.sec { margin-bottom: 20px; }

/* Separador */
hr.top { border: none; border-top: 2.5px solid {$AZ}; margin: 12px 0 16px 0; }
hr.mid { border: none; border-top: 1px solid {$GRM}; margin: 14px 0; }
</style>
CSS;

/* ── HTML del reporte ───────────────────────────────────────── */
ob_start();
?>

<!-- ══ CABECERA ══════════════════════════════════════════════ -->
<table width="100%" style="margin-bottom:12px;">
  <tr>
    <td width="62%">
      <div class="brand">&#9783; Lavandería Superior</div>
      <div class="periodo">
        <b>Reporte de Ventas y Estadísticas</b><br>
        Período: <b><?= $desde_lbl ?></b> al <b><?= $hasta_lbl ?></b>
      </div>
    </td>
    <td width="38%" class="meta">
      Emitido: <?= $emitido ?><br>
      Generado por: <b><?= $usuario ?></b><br>
      Tasa BCV promedio período:<br>
      <b style="color:<?= $AZ ?>;font-size:10pt;">Bs. <?= fn2($tasa) ?> / USD</b>
    </td>
  </tr>
</table>
<hr class="top">

<!-- ══ KPIs FILA 1 ═══════════════════════════════════════════ -->
<div class="sec">
<h2>Resumen Ejecutivo</h2>

<table width="100%"><tr>
  <td width="25%" style="padding:4px 3px;">
    <div class="kbox kbox-blue">
      <span class="kval"><?= usd($ingUSD) ?></span>
      <span class="kbs"><?= bs($ingBS) ?></span>
      <span class="klbl">Ingresos Totales</span>
    </div>
  </td>
  <td width="25%" style="padding:4px 3px;">
    <div class="kbox kbox-green">
      <span class="kval kval-g"><?= (int)$kpis['total_ordenes'] ?></span>
      <span class="kbs"><?= (int)$kpis['entregadas'] ?> entregadas &bull; <?= (int)$kpis['canceladas'] ?> canceladas</span>
      <span class="klbl">Órdenes del Período</span>
    </div>
  </td>
  <td width="25%" style="padding:4px 3px;">
    <div class="kbox kbox-amber">
      <span class="kval kval-a"><?= fn2($kpis['kg_total'],1) ?> kg</span>
      <span class="kbs"><?= ($kpis['kg_total']>0 && $ingUSD>0) ? usd($ingUSD/$kpis['kg_total']).'/kg' : '—' ?></span>
      <span class="klbl">Kg Procesados</span>
    </div>
  </td>
  <td width="25%" style="padding:4px 3px;">
    <div class="kbox <?= $balance >= 0 ? 'kbox-green' : 'kbox-red' ?>">
      <span class="kval <?= $balance >= 0 ? 'kval-g' : 'kval-r' ?>"><?= usd($balance) ?></span>
      <span class="kbs"><?= bs($balanceBS) ?></span>
      <span class="klbl">Balance Neto</span>
    </div>
  </td>
</tr></table>

<!-- KPIs FILA 2 -->
<table width="100%" style="margin-top:6px;"><tr>
  <td width="33%" style="padding:4px 3px;">
    <div class="kbox">
      <span class="kval" style="font-size:13pt;"><?= usd((float)$kpis['promedio_usd']) ?></span>
      <span class="kbs"><?= bs((float)$kpis['promedio_usd'] * $tasa) ?></span>
      <span class="klbl">Ticket Promedio</span>
    </div>
  </td>
  <td width="33%" style="padding:4px 3px;">
    <div class="kbox kbox-red">
      <span class="kval kval-r" style="font-size:13pt;"><?= usd($egTot) ?></span>
      <span class="kbs"><?= bs($egTot * $tasa) ?></span>
      <span class="klbl">Total Egresos</span>
    </div>
  </td>
  <td width="33%" style="padding:4px 3px;">
    <div class="kbox">
      <span class="kval" style="font-size:13pt;color:<?= $AM ?>;">
        Bs. <?= fn2($tasa) ?>
      </span>
      <span class="kbs">Actual: Bs. <?= fn2($tasaHoy['tasa_bcv']) ?></span>
      <span class="klbl">Tasa BCV (Promedio)</span>
    </div>
  </td>
</tr></table>
</div>

<!-- ══ INGRESOS POR DÍA ═══════════════════════════════════════ -->
<?php if (!empty($porDia)): ?>
<div class="sec">
<h2>Ingresos por Día</h2>
<table class="data">
  <thead>
    <tr>
      <th style="width:26%">Fecha</th>
      <th class="c" style="width:12%">Órdenes</th>
      <th class="r" style="width:21%">Monto USD</th>
      <th class="r" style="width:24%">Monto Bs.</th>
      <th class="r" style="width:17%">% del Total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($porDia as $i => $row):
      $bsdia  = (float)$row['bs'] > 0 ? (float)$row['bs'] : (float)$row['usd'] * $tasa;
      $pctDia = porcentaje($row['usd'], $ingUSD);
    ?>
    <tr class="<?= $i % 2 ? 'alt' : '' ?>">
      <td class="bold"><?= date('D d/m/Y', strtotime($row['fecha'])) ?></td>
      <td class="c"><?= (int)$row['ordenes'] ?></td>
      <td class="r az"><?= usd($row['usd']) ?></td>
      <td class="r sub"><?= bs($bsdia) ?></td>
      <td class="r"><span class="badge b-az"><?= $pctDia ?></span></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr style="background:<?= $AZ ?>;color:#fff;">
      <td class="bold">TOTAL DEL PERÍODO</td>
      <td class="c bold"><?= (int)$kpis['total_ordenes'] ?></td>
      <td class="r bold"><?= usd($ingUSD) ?></td>
      <td class="r bold"><?= bs($ingBS) ?></td>
      <td class="r bold">100%</td>
    </tr>
  </tfoot>
</table>
</div>
<?php endif; ?>

<!-- ══ ANÁLISIS POR SERVICIO ══════════════════════════════════ -->
<?php if (!empty($svcList)): ?>
<div class="sec">
<h2>Análisis por Servicio</h2>
<table class="data">
  <thead>
    <tr>
      <th style="width:28%">Servicio</th>
      <th class="c" style="width:10%">Unidad</th>
      <th class="c" style="width:10%">Veces</th>
      <th class="c" style="width:12%">Cantidad</th>
      <th class="r" style="width:18%">Monto USD</th>
      <th class="r" style="width:17%">Monto Bs.</th>
      <th class="c" style="width:10%">%</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($svcList as $i => $sv): ?>
    <tr class="<?= $i % 2 ? 'alt' : '' ?>">
      <td class="bold"><?= htmlspecialchars($sv['nombre']) ?></td>
      <td class="c sub"><?= $sv['unidad_medida'] ?></td>
      <td class="c"><?= (int)$sv['veces'] ?></td>
      <td class="c"><?= fn2($sv['cantidad_total']) ?></td>
      <td class="r az"><?= usd($sv['monto_usd']) ?></td>
      <td class="r sub"><?= bs((float)$sv['monto_usd'] * $tasa) ?></td>
      <td class="c"><span class="badge b-az"><?= porcentaje($sv['monto_usd'], $ingUSD) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<!-- ══ MÉTODOS DE PAGO ════════════════════════════════════════ -->
<?php if (!empty($metPago)): ?>
<div class="sec">
<h2>Métodos de Pago</h2>
<table class="data">
  <thead>
    <tr>
      <th style="width:30%">Método</th>
      <th class="c" style="width:18%">Transacciones</th>
      <th class="r" style="width:22%">Monto USD</th>
      <th class="r" style="width:22%">Monto Bs.</th>
      <th class="c" style="width:13%">% Total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($metPago as $i => $mp): ?>
    <tr class="<?= $i % 2 ? 'alt' : '' ?>">
      <td class="bold"><?= h(nombreMetodoPago($mp['metodo_pago'])) ?></td>
      <td class="c"><?= (int)$mp['cantidad'] ?></td>
      <td class="r ve"><?= usd($mp['usd']) ?></td>
      <td class="r sub"><?= bs((float)$mp['usd'] * $tasa) ?></td>
      <td class="c"><span class="badge b-ve"><?= porcentaje($mp['usd'], $ingUSD) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr style="background:<?= $VE ?>;color:#fff;">
      <td class="bold">TOTAL</td>
      <td class="c bold"><?= (int)$kpis['total_ordenes'] ?></td>
      <td class="r bold"><?= usd($ingUSD) ?></td>
      <td class="r bold"><?= bs($ingBS) ?></td>
      <td class="c bold">100%</td>
    </tr>
  </tfoot>
</table>
</div>
<?php endif; ?>

<!-- ══ TOP 10 CLIENTES ════════════════════════════════════════ -->
<?php if (!empty($clientes)): ?>
<div class="sec">
<h2>Top 10 Clientes del Período</h2>
<table class="data">
  <thead>
    <tr>
      <th class="c" style="width:7%">#</th>
      <th style="width:30%">Cliente</th>
      <th style="width:16%">Cédula / RIF</th>
      <th class="c" style="width:12%">Órdenes</th>
      <th class="r" style="width:18%">Total USD</th>
      <th class="r" style="width:17%">Total Bs.</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($clientes as $i => $cl):
      $clbs = (float)$cl['total_bs'] > 0 ? (float)$cl['total_bs'] : (float)$cl['total_usd'] * $tasa;
    ?>
    <tr class="<?= $i % 2 ? 'alt' : '' ?>">
      <td class="c bold" style="color:<?= $AZ ?>;"><?= $i + 1 ?></td>
      <td class="bold"><?= htmlspecialchars($cl['nombre']) ?></td>
      <td class="sub"><?= htmlspecialchars($cl['cedula_rif']) ?></td>
      <td class="c"><?= (int)$cl['ordenes'] ?></td>
      <td class="r az"><?= usd($cl['total_usd']) ?></td>
      <td class="r sub"><?= bs($clbs) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<!-- ══ EGRESOS ════════════════════════════════════════════════ -->
<?php if (!empty($egresos)): ?>
<div class="sec">
<h2>Egresos por Concepto</h2>
<table class="data">
  <thead>
    <tr>
      <th style="width:34%">Concepto</th>
      <th class="c" style="width:15%">Registros</th>
      <th class="r" style="width:22%">Monto USD</th>
      <th class="r" style="width:22%">Monto Bs.</th>
      <th class="c" style="width:12%">%</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($egresos as $i => $eg): ?>
    <tr class="<?= $i % 2 ? 'alt' : '' ?>">
      <td class="bold"><?= htmlspecialchars($eg['concepto']) ?></td>
      <td class="c"><?= (int)$eg['cantidad'] ?></td>
      <td class="r ro"><?= usd($eg['usd']) ?></td>
      <td class="r sub"><?= bs((float)$eg['usd'] * $tasa) ?></td>
      <td class="c"><span class="badge b-ro"><?= porcentaje($eg['usd'], $egTot ?: 1) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr style="background:<?= $RO ?>;color:#fff;">
      <td class="bold">TOTAL EGRESOS</td>
      <td></td>
      <td class="r bold"><?= usd($egTot) ?></td>
      <td class="r bold"><?= bs($egTot * $tasa) ?></td>
      <td></td>
    </tr>
  </tfoot>
</table>
</div>
<?php endif; ?>

<!-- ══ BALANCE FINAL ══════════════════════════════════════════ -->
<div class="sec">
<h2>Balance Final del Período</h2>
<table width="100%">
  <tr>
    <td width="50%" style="padding:4px 3px;">
      <div class="kbox kbox-green" style="text-align:left;padding:14px 16px;">
        <div style="font-size:8.5pt;color:<?= $GRT ?>;margin-bottom:4px;">TOTAL INGRESOS</div>
        <div style="font-size:16pt;font-weight:bold;color:<?= $VE ?>;"><?= usd($ingUSD) ?></div>
        <div style="font-size:9pt;color:<?= $GRT ?>;margin-top:3px;"><?= bs($ingBS) ?></div>
      </div>
    </td>
    <td width="50%" style="padding:4px 3px;">
      <div class="kbox <?= $balance >= 0 ? 'kbox-green' : 'kbox-red' ?>" style="text-align:left;padding:14px 16px;">
        <div style="font-size:8.5pt;color:<?= $GRT ?>;margin-bottom:4px;">BALANCE NETO (Ingresos &minus; Egresos)</div>
        <div style="font-size:16pt;font-weight:bold;color:<?= $balance >= 0 ? $VE : $RO ?>;"><?= usd($balance) ?></div>
        <div style="font-size:9pt;color:<?= $GRT ?>;margin-top:3px;"><?= bs($balanceBS) ?></div>
      </div>
    </td>
  </tr>
</table>
<p style="margin-top:10px;font-size:8.5pt;color:<?= $GRT ?>;">
  Tasa BCV promedio del período: <b>Bs. <?= fn2($tasa) ?>/USD</b>
  &nbsp;&bull;&nbsp;
  Tasa BCV actual (<?= date('d/m/Y') ?>): <b>Bs. <?= fn2($tasaHoy['tasa_bcv']) ?>/USD</b>
  &nbsp;&bull;&nbsp;
  Generado: <?= $emitido ?> por <?= $usuario ?>
</p>
</div>

<?php
$html = ob_get_clean();

/* ── Generar PDF ────────────────────────────────────────────── */
$mpdf = new \Mpdf\Mpdf([
    'mode'           => 'utf-8',
    'format'         => 'A4',
    'margin_top'     => 14,
    'margin_right'   => 13,
    'margin_bottom'  => 22,
    'margin_left'    => 13,
    'margin_footer'  => 8,
    'default_font'   => 'dejavusans',
    'default_font_size' => 10,
]);

$mpdf->SetTitle("Reporte LS {$desde_lbl} – {$hasta_lbl}");
$mpdf->SetAuthor('Lavandería Superior');
$mpdf->SetCreator('Sistema LS v1.0');
$mpdf->SetFooter(
    '<table width="100%"><tr>'.
    '<td style="font-size:7.5pt;color:#94a3b8;">Lavandería Superior &bull; Sistema de Gestión v1.0</td>'.
    '<td style="font-size:7.5pt;color:#94a3b8;text-align:right;">Página {PAGENO} de {nbpg}</td>'.
    '</tr></table>'
);

$mpdf->WriteHTML($css . $html);
$fname = 'reporte_ventas_'.str_replace('-','',$desde).'_'.str_replace('-','',$hasta).'.pdf';
$mpdf->Output($fname, \Mpdf\Output\Destination::DOWNLOAD);
exit;

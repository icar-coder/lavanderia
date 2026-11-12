<?php
/**
 * clientes_pdf.php — Lista completa de clientes (mPDF)
 * GET: busqueda=texto (opcional), orden=nombre|ordenes|puntos
 */
require_once __DIR__ . '/config/init.php';
requireAdmin();

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die('<div style="font-family:sans-serif;padding:40px;max-width:560px;margin:60px auto;">
      <h2 style="color:#e11d48;">⚠️ mPDF no instalado</h2>
      <pre style="background:#1e293b;color:#60a5fa;padding:14px;border-radius:8px;">composer require mpdf/mpdf</pre>
    </div>');
}
require_once $autoload;

/* ── Parámetros ─────────────────────────────────────────────── */
$busqueda = mb_substr(trim(entrada($_GET, 'busqueda')), 0, 60);
$orden    = in_array(entrada($_GET, 'orden'), ['nombre','ordenes','puntos','gasto'], true)
            ? entrada($_GET, 'orden') : 'nombre';
$emitido  = date('d/m/Y H:i');
$usuario  = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Sistema');
$tasaHoy  = obtenerTasaHoy();

/* ── Consulta de clientes con estadísticas ──────────────────── */
$db = getDB();
$orderSQL = match($orden) {
    'ordenes' => 'total_ordenes DESC',
    'puntos'  => 'c.puntos_acumulados DESC',
    'gasto'   => 'total_gastado DESC',
    default   => 'c.nombre ASC',
};

$where  = '';
$params = [];
if ($busqueda) {
    $where  = 'WHERE c.nombre LIKE ? OR c.cedula_rif LIKE ? OR c.telefono LIKE ?';
    $like   = '%'.escaparLike($busqueda).'%';
    $params = [$like, $like, $like];
}

$stmt = $db->prepare("
    SELECT
        c.id, c.nombre, c.cedula_rif, c.telefono, c.direccion,
        c.puntos_acumulados, c.activo,
        c.fecha_registro,
        COUNT(DISTINCT o.id)          AS total_ordenes,
        COALESCE(SUM(o.total_usd), 0) AS total_gastado,
        MAX(o.fecha_recepcion)        AS ultima_orden
    FROM clientes c
    LEFT JOIN ordenes o ON o.cliente_id = c.id AND o.estado != 'Cancelado'
    $where
    GROUP BY c.id
    ORDER BY $orderSQL
");
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$totalClientes = count($clientes);
$totalActivos  = count(array_filter($clientes, fn($c) => $c['activo']));
$totalUSD      = array_sum(array_column($clientes, 'total_gastado'));

/* ── Helpers ────────────────────────────────────────────────── */
function cusd(float $v): string { return '$'.number_format($v,2,'.',','); }
function cbs(float $v):  string { return 'Bs.&nbsp;'.number_format($v,2,',','.'); }

/* ── Colores ────────────────────────────────────────────────── */
$AZ='#1d4ed8'; $AZL='#eff6ff'; $AZM='#bfdbfe';
$VE='#15803d'; $VEL='#f0fdf4';
$RO='#b91c1c'; $ROL='#fef2f2';
$GR='#1f2937'; $GRL='#f8fafc'; $GRM='#e2e8f0'; $GRT='#64748b'; $GRH='#f1f5f9';

/* ── CSS ────────────────────────────────────────────────────── */
$css = <<<CSS
<style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5pt; color: #1f2937; line-height: 1.4; }
.brand { font-size: 18pt; font-weight: bold; color: {$AZ}; }
.meta  { font-size: 8.5pt; color: {$GRT}; text-align: right; }
h2 { font-size: 11pt; font-weight: bold; color: {$AZ}; border-bottom: 2px solid {$AZ}; padding-bottom: 4px; margin: 0 0 10px 0; }

/* Stats summary */
.sbox { border: 1.5px solid {$AZM}; background: {$AZL}; border-radius: 6px; padding: 10px 12px; text-align: center; }
.sval { font-size: 16pt; font-weight: bold; color: {$AZ}; display: block; }
.slbl { font-size: 7.5pt; color: {$GRT}; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-top: 4px; }

/* Tabla */
table.data { width: 100%; border-collapse: collapse; }
table.data thead th {
    background: {$AZ}; color: #fff;
    padding: 7px 8px; font-size: 8.5pt; font-weight: bold; text-align: left;
}
table.data thead th.r { text-align: right; }
table.data thead th.c { text-align: center; }
table.data tbody td {
    padding: 6px 8px; font-size: 9pt;
    border-bottom: 1px solid {$GRM}; color: {$GR}; vertical-align: top;
}
table.data tbody tr.alt td { background: {$GRH}; }
table.data tfoot td { padding: 7px 8px; font-weight: bold; border-top: 2px solid {$GRM}; font-size: 9.5pt; }

.az  { color: {$AZ}; font-weight: bold; }
.ve  { color: {$VE}; font-weight: bold; }
.ro  { color: {$RO}; }
.sub { color: {$GRT}; font-size: 8pt; }
.r   { text-align: right; }
.c   { text-align: center; }
.bold{ font-weight: bold; }
.badge { border-radius:10px; padding:2px 8px; font-size:7.5pt; font-weight:bold; }
.b-ve  { background:{$VEL}; color:{$VE}; }
.b-ro  { background:{$ROL}; color:{$RO}; }
hr.top { border:none; border-top:2.5px solid {$AZ}; margin:12px 0 16px; }
.sec { margin-bottom: 18px; }
</style>
CSS;

/* ── HTML ───────────────────────────────────────────────────── */
ob_start();
?>
<!-- CABECERA -->
<table width="100%" style="margin-bottom:12px;">
  <tr>
    <td width="60%">
      <div class="brand">&#9783; Lavandería Superior</div>
      <div style="font-size:10pt;color:<?=$GR?>;margin-top:4px;">
        <b>Listado de Clientes</b>
        <?= $busqueda ? ' &mdash; Filtro: "<b>'.htmlspecialchars($busqueda).'</b>"' : '' ?>
      </div>
      <div style="font-size:8.5pt;color:<?=$GRT?>;margin-top:3px;">
        Ordenado por: <b><?= ucfirst($orden) ?></b>
      </div>
    </td>
    <td width="40%" class="meta">
      Emitido: <?= $emitido ?><br>
      Generado por: <b><?= $usuario ?></b><br>
      Tasa BCV: <b style="color:<?=$AZ?>">Bs. <?= number_format($tasaHoy['tasa_bcv'],2,',','.') ?>/USD</b>
    </td>
  </tr>
</table>
<hr class="top">

<!-- RESUMEN -->
<div class="sec">
<table width="100%"><tr>
  <td width="25%" style="padding:4px 3px;">
    <div class="sbox">
      <span class="sval"><?= $totalClientes ?></span>
      <span class="slbl">Total Clientes</span>
    </div>
  </td>
  <td width="25%" style="padding:4px 3px;">
    <div class="sbox" style="border-color:<?=$VE?>;background:<?=$VEL?>;">
      <span class="sval" style="color:<?=$VE?>;"><?= $totalActivos ?></span>
      <span class="slbl">Activos</span>
    </div>
  </td>
  <td width="25%" style="padding:4px 3px;">
    <div class="sbox" style="border-color:<?=$RO?>;background:<?=$ROL?>;">
      <span class="sval" style="color:<?=$RO?>;"><?= $totalClientes - $totalActivos ?></span>
      <span class="slbl">Inactivos</span>
    </div>
  </td>
  <td width="25%" style="padding:4px 3px;">
    <div class="sbox">
      <span class="sval" style="font-size:12pt;"><?= cusd($totalUSD) ?></span>
      <span class="slbl">Total Facturado</span>
    </div>
  </td>
</tr></table>
</div>

<!-- TABLA DE CLIENTES -->
<div class="sec">
<h2>Directorio de Clientes</h2>
<table class="data">
  <thead>
    <tr>
      <th style="width:4%">#</th>
      <th style="width:22%">Nombre</th>
      <th style="width:13%">Cédula / RIF</th>
      <th style="width:14%">Teléfono</th>
      <th class="c" style="width:9%">Órdenes</th>
      <th class="r" style="width:14%">Gasto USD</th>
      <th class="r" style="width:14%">Gasto Bs.</th>
      <th class="c" style="width:5%">Pts.</th>
      <th class="c" style="width:8%">Estado</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($clientes as $i => $cl):
      $clbs = (float)$cl['total_gastado'] * $tasaHoy['tasa_bcv'];
    ?>
    <tr class="<?= $i % 2 ? 'alt' : '' ?>">
      <td class="c sub"><?= $i + 1 ?></td>
      <td>
        <b><?= htmlspecialchars($cl['nombre']) ?></b>
        <?php if ($cl['ultima_orden']): ?>
        <div class="sub">Última orden: <?= date('d/m/Y', strtotime($cl['ultima_orden'])) ?></div>
        <?php endif; ?>
      </td>
      <td class="sub"><?= htmlspecialchars($cl['cedula_rif']) ?></td>
      <td style="font-size:8.5pt;"><?= htmlspecialchars($cl['telefono']) ?></td>
      <td class="c bold"><?= (int)$cl['total_ordenes'] ?></td>
      <td class="r az"><?= cusd($cl['total_gastado']) ?></td>
      <td class="r sub"><?= cbs($clbs) ?></td>
      <td class="c" style="color:<?=$AZ?>;font-weight:bold;"><?= (int)$cl['puntos_acumulados'] ?></td>
      <td class="c">
        <?php if ($cl['activo']): ?>
          <span class="badge b-ve">Activo</span>
        <?php else: ?>
          <span class="badge b-ro">Inactivo</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($clientes)): ?>
    <tr><td colspan="9" style="text-align:center;padding:20px;color:<?=$GRT?>;">
      No hay clientes para mostrar.
    </td></tr>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr style="background:<?=$AZ?>;color:#fff;">
      <td colspan="4" class="bold">TOTALES</td>
      <td class="c bold"><?= array_sum(array_column($clientes,'total_ordenes')) ?></td>
      <td class="r bold"><?= cusd($totalUSD) ?></td>
      <td class="r bold"><?= cbs($totalUSD * $tasaHoy['tasa_bcv']) ?></td>
      <td colspan="2"></td>
    </tr>
  </tfoot>
</table>
</div>

<p style="font-size:8pt;color:<?=$GRT?>;margin-top:6px;">
  Tasa BCV aplicada: Bs. <?= number_format($tasaHoy['tasa_bcv'],2,',','.') ?>/USD
  &bull; Generado: <?= $emitido ?> por <?= $usuario ?>
</p>

<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'mode'           => 'utf-8',
    'format'         => 'A4-L',   // Landscape para más columnas
    'margin_top'     => 13,
    'margin_right'   => 12,
    'margin_bottom'  => 20,
    'margin_left'    => 12,
    'margin_footer'  => 7,
    'default_font'   => 'dejavusans',
    'default_font_size' => 9,
]);

$mpdf->SetTitle('Listado de Clientes — Lavandería Superior');
$mpdf->SetAuthor('Lavandería Superior');
$mpdf->SetCreator('Sistema LS v1.0');
$mpdf->SetFooter(
    '<table width="100%"><tr>'.
    '<td style="font-size:7pt;color:#94a3b8;">Lavandería Superior &bull; Listado de Clientes</td>'.
    '<td style="font-size:7pt;color:#94a3b8;text-align:right;">Página {PAGENO} de {nbpg}</td>'.
    '</tr></table>'
);

$mpdf->WriteHTML($css . $html);
$mpdf->Output('clientes_lavanderia_'.date('Ymd').'.pdf', \Mpdf\Output\Destination::DOWNLOAD);
exit;

<?php
/**
 * inventario_pdf.php — Reporte de inventario de insumos (mPDF)
 * GET: solo=bajos (opcional, solo muestra items con stock bajo)
 */
require_once 'config/init.php';
requireAuth();
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die('<div style="font-family:sans-serif;padding:40px;max-width:560px;margin:60px auto;">
      <h2 style="color:#e11d48;">⚠️ mPDF no instalado</h2>
      <pre style="background:#1e293b;color:#60a5fa;padding:14px;border-radius:8px;">composer require mpdf/mpdf</pre>
    </div>');
}
require_once $autoload;

/* ── Parámetros ─────────────────────────────────────────────── */
$soloBajos = entrada($_GET, 'solo') === 'bajos';
$emitido   = date('d/m/Y H:i');
$usuario   = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Sistema');
$tasaHoy   = obtenerTasaHoy();

/* ── Datos ──────────────────────────────────────────────────── */
$db   = getDB();
$where = $soloBajos ? 'WHERE activo=1 AND stock_actual <= stock_minimo' : 'WHERE activo=1';

$insumos = $db->query("
    SELECT * FROM inventario_insumos
    $where
    ORDER BY
        CASE WHEN stock_actual = 0 THEN 0
             WHEN stock_actual <= stock_minimo THEN 1
             ELSE 2 END,
        nombre ASC
")->fetchAll();

/* Consumo total en el último mes */
$consumoMes = $db->query("
    SELECT i.id, COALESCE(SUM(c.cantidad_usada),0) AS total_mes
    FROM inventario_insumos i
    LEFT JOIN consumo_insumos c ON c.insumo_id = i.id
        AND c.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY i.id
")->fetchAll(PDO::FETCH_KEY_PAIR);

/* Totales */
$totalItems    = count($insumos);
$sinStock      = count(array_filter($insumos, fn($x) => (float)$x['stock_actual'] == 0));
$stockBajo     = count(array_filter($insumos, fn($x) => (float)$x['stock_actual'] > 0 && $x['stock_actual'] <= $x['stock_minimo']));
$enOrden       = $totalItems - $sinStock - $stockBajo;
$valorInventario = array_sum(array_map(
    fn($x) => (float)$x['stock_actual'] * (float)($x['costo_unidad'] ?? 0),
    $insumos
));

/* ── Helpers ────────────────────────────────────────────────── */
function inv_usd(float $v): string { return '$'.number_format($v,2,'.',','); }
function inv_bs(float $v): string  { return 'Bs.&nbsp;'.number_format($v,2,',','.'); }
function inv_num(float $v, int $d=2): string { return number_format($v,$d,'.',','); }
function nivelBarra(float $stock, float $minimo): array {
    if ($stock == 0) return ['color' => '#b91c1c', 'label' => 'SIN STOCK', 'pct' => 0];
    if ($stock <= $minimo) return ['color' => '#d97706', 'label' => 'STOCK BAJO', 'pct' => min(49, ($stock/$minimo)*45)];
    $pct = min(100, ($stock / ($minimo * 3)) * 100);
    return ['color' => '#15803d', 'label' => 'OK', 'pct' => $pct];
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
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5pt; color: #1f2937; line-height: 1.4; }
.brand { font-size: 18pt; font-weight: bold; color: {$AZ}; }
.meta  { font-size: 8.5pt; color: {$GRT}; text-align: right; }
h2 { font-size: 11pt; font-weight: bold; color: {$AZ}; border-bottom: 2px solid {$AZ}; padding-bottom: 4px; margin: 0 0 10px 0; }
.sbox { border: 1.5px solid {$AZM}; background: {$AZL}; border-radius: 6px; padding: 10px 12px; text-align: center; }
.sval { font-size: 15pt; font-weight: bold; color: {$AZ}; display: block; }
.slbl { font-size: 7.5pt; color: {$GRT}; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-top: 4px; }
table.data { width: 100%; border-collapse: collapse; }
table.data thead th {
    background: {$AZ}; color: #fff;
    padding: 7px 8px; font-size: 8.5pt; font-weight: bold; text-align: left;
}
table.data thead th.r { text-align: right; }
table.data thead th.c { text-align: center; }
table.data tbody td {
    padding: 7px 8px; font-size: 9pt;
    border-bottom: 1px solid {$GRM}; vertical-align: middle;
}
table.data tbody tr.alt td { background: {$GRH}; }
table.data tbody tr.danger td { background: {$ROL}; }
table.data tbody tr.warning td { background: {$AML}; }
table.data tfoot td { padding: 7px 8px; font-weight: bold; border-top: 2px solid {$GRM}; font-size: 9.5pt; }
.r { text-align: right; }
.c { text-align: center; }
.bold { font-weight: bold; }
.az { color: {$AZ}; font-weight: bold; }
.ve { color: {$VE}; font-weight: bold; }
.ro { color: {$RO}; font-weight: bold; }
.am { color: {$AM}; font-weight: bold; }
.sub { color: {$GRT}; font-size: 8pt; }
.badge { border-radius:10px; padding:2px 8px; font-size:7.5pt; font-weight:bold; }
.b-ve { background:{$VEL}; color:{$VE}; }
.b-ro { background:{$ROL}; color:{$RO}; }
.b-am { background:{$AML}; color:{$AM}; }
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
        <b>Reporte de Inventario de Insumos</b>
        <?= $soloBajos ? ' &mdash; <span style="color:'.$RO.'">Solo items con alerta</span>' : '' ?>
      </div>
      <div style="font-size:8.5pt;color:<?=$GRT?>;margin-top:3px;">
        Ordenado por: estado de stock (crítico primero) &bull; <?= $emitido ?>
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

<!-- RESUMEN KPIs -->
<div class="sec">
<table width="100%"><tr>
  <td width="25%" style="padding:4px 3px;">
    <div class="sbox">
      <span class="sval"><?= $totalItems ?></span>
      <span class="slbl">Total Insumos</span>
    </div>
  </td>
  <td width="25%" style="padding:4px 3px;">
    <div class="sbox" style="border-color:<?=$VEM?>;background:<?=$VEL?>;">
      <span class="sval" style="color:<?=$VE?>;"><?= $enOrden ?></span>
      <span class="slbl">En Orden (OK)</span>
    </div>
  </td>
  <td width="25%" style="padding:4px 3px;">
    <div class="sbox" style="border-color:<?=$AMM?>;background:<?=$AML?>;">
      <span class="sval" style="color:<?=$AM?>;"><?= $stockBajo ?></span>
      <span class="slbl">Stock Bajo</span>
    </div>
  </td>
  <td width="25%" style="padding:4px 3px;">
    <div class="sbox" style="border-color:<?=$ROM?>;background:<?=$ROL?>;">
      <span class="sval" style="color:<?=$RO?>;"><?= $sinStock ?></span>
      <span class="slbl">Sin Stock</span>
    </div>
  </td>
</tr></table>

<?php if ($valorInventario > 0): ?>
<table width="100%" style="margin-top:6px;"><tr>
  <td width="50%" style="padding:4px 3px;">
    <div class="sbox">
      <span class="sval" style="font-size:13pt;"><?= inv_usd($valorInventario) ?></span>
      <span class="slbl">Valor estimado del inventario (USD)</span>
    </div>
  </td>
  <td width="50%" style="padding:4px 3px;">
    <div class="sbox">
      <span class="sval" style="font-size:13pt;color:<?=$AM?>;">
        <?= inv_bs($valorInventario * $tasaHoy['tasa_bcv']) ?>
      </span>
      <span class="slbl">Valor estimado del inventario (Bs.)</span>
    </div>
  </td>
</tr></table>
<?php endif; ?>
</div>

<!-- TABLA INVENTARIO -->
<div class="sec">
<h2>Detalle de Insumos</h2>
<table class="data">
  <thead>
    <tr>
      <th style="width:6%">#</th>
      <th style="width:24%">Insumo</th>
      <th class="c" style="width:8%">Unidad</th>
      <th class="r" style="width:12%">Stock Actual</th>
      <th class="r" style="width:11%">Mínimo</th>
      <th class="r" style="width:10%">Cons./Kg</th>
      <th class="r" style="width:11%">Costo/Un.</th>
      <th class="r" style="width:13%">Valor Stock</th>
      <th class="c" style="width:10%">Estado</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($insumos as $i => $ins):
      $nivel    = nivelBarra((float)$ins['stock_actual'], (float)$ins['stock_minimo']);
      $costo    = (float)($ins['costo_unidad'] ?? 0);
      $valorStk = (float)$ins['stock_actual'] * $costo;
      $consMes  = (float)($consumoMes[$ins['id']] ?? 0);

      // Determinar clase de fila
      if ((float)$ins['stock_actual'] == 0) $rowClass = 'danger';
      elseif ($ins['stock_actual'] <= $ins['stock_minimo']) $rowClass = 'warning';
      else $rowClass = ($i % 2 ? 'alt' : '');
    ?>
    <tr class="<?= $rowClass ?>">
      <td class="c sub"><?= $i + 1 ?></td>
      <td>
        <b><?= htmlspecialchars($ins['nombre']) ?></b>
        <?php if ($consMes > 0): ?>
        <div class="sub">Consumo último mes: <?= inv_num($consMes) ?> <?= $ins['unidad'] ?></div>
        <?php endif; ?>
      </td>
      <td class="c sub"><?= $ins['unidad'] ?></td>
      <td class="r bold <?= $nivel['color']==='#b91c1c'?'ro':($nivel['color']==='#d97706'?'am':'ve') ?>">
        <?= inv_num((float)$ins['stock_actual']) ?>
      </td>
      <td class="r sub"><?= inv_num((float)$ins['stock_minimo']) ?></td>
      <td class="r sub">
        <?= $ins['consumo_por_kg'] ? inv_num((float)$ins['consumo_por_kg']) : '—' ?>
      </td>
      <td class="r sub">
        <?= $costo > 0 ? inv_usd($costo) : '—' ?>
      </td>
      <td class="r">
        <?= $valorStk > 0 ? '<span class="az">'.inv_usd($valorStk).'</span>' : '<span class="sub">—</span>' ?>
      </td>
      <td class="c">
        <?php if ($nivel['label'] === 'SIN STOCK'): ?>
          <span class="badge b-ro">SIN STOCK</span>
        <?php elseif ($nivel['label'] === 'STOCK BAJO'): ?>
          <span class="badge b-am">STOCK BAJO</span>
        <?php else: ?>
          <span class="badge b-ve">OK</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($insumos)): ?>
    <tr><td colspan="9" style="text-align:center;padding:20px;color:<?=$GRT?>;">
      No hay insumos registrados.
    </td></tr>
    <?php endif; ?>
  </tbody>
  <?php if ($valorInventario > 0): ?>
  <tfoot>
    <tr style="background:<?=$AZ?>;color:#fff;">
      <td colspan="7" class="bold">VALOR TOTAL DEL INVENTARIO</td>
      <td class="r bold"><?= inv_usd($valorInventario) ?></td>
      <td></td>
    </tr>
  </tfoot>
  <?php endif; ?>
</table>
</div>

<!-- LEYENDA -->
<table width="100%" style="margin-top:8px;">
  <tr>
    <td style="font-size:8pt;color:<?=$GRT?>;">
      <b>Leyenda:</b>
      <span style="background:<?=$ROL?>;color:<?=$RO?>;padding:1px 6px;border-radius:4px;margin-left:6px;">Rojo = Sin stock</span>
      <span style="background:<?=$AML?>;color:<?=$AM?>;padding:1px 6px;border-radius:4px;margin-left:6px;">Amarillo = Stock bajo (≤ mínimo)</span>
      <span style="background:<?=$VEL?>;color:<?=$VE?>;padding:1px 6px;border-radius:4px;margin-left:6px;">Verde = OK</span>
    </td>
  </tr>
</table>

<p style="margin-top:8px;font-size:8pt;color:<?=$GRT?>;">
  Fecha de corte: <?= date('d/m/Y H:i') ?>
  &bull; Generado por: <?= $usuario ?>
  &bull; Tasa BCV: Bs. <?= number_format($tasaHoy['tasa_bcv'],2,',','.') ?>/USD
</p>

<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'mode'           => 'utf-8',
    'format'         => 'A4-L',   // Landscape — más columnas
    'margin_top'     => 13,
    'margin_right'   => 12,
    'margin_bottom'  => 20,
    'margin_left'    => 12,
    'margin_footer'  => 7,
    'default_font'   => 'dejavusans',
    'default_font_size' => 9,
]);

$mpdf->SetTitle('Inventario de Insumos — Lavandería Superior');
$mpdf->SetAuthor('Lavandería Superior');
$mpdf->SetCreator('Sistema LS v1.0');
$mpdf->SetFooter(
    '<table width="100%"><tr>'.
    '<td style="font-size:7pt;color:#94a3b8;">Lavandería Superior &bull; Inventario de Insumos</td>'.
    '<td style="font-size:7pt;color:#94a3b8;text-align:right;">Página {PAGENO} de {nbpg}</td>'.
    '</tr></table>'
);

$mpdf->WriteHTML($css . $html);
$suffix = $soloBajos ? '_alertas' : '_completo';
$mpdf->Output('inventario'.$suffix.'_'.date('Ymd').'.pdf', \Mpdf\Output\Destination::DOWNLOAD);
exit;

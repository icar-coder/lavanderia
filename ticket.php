<?php
/**
 * ticket.php — Generador e impresor de tickets para tickera térmica
 *
 * Parámetros GET:
 *   id    = ID de la orden  (requerido)
 *   tipo  = 'recepcion' | 'entrega'  (default: recepcion)
 *   auto  = 1  → imprime automáticamente al cargar (sin modal)
 *
 * Uso desde JS:
 *   window.open('ticket.php?id=42&tipo=recepcion&auto=1', '_blank', 'width=400,height=700')
 */
require_once __DIR__ . '/config/init.php';
requireAuth('ticket');

$ordenId = (int) entrada($_GET, 'id');
$tipo    = in_array(entrada($_GET, 'tipo'), ['recepcion','entrega'], true) ? entrada($_GET, 'tipo') : 'recepcion';
$auto    = entrada($_GET, 'auto') === '1';

if ($ordenId <= 0) {
    http_response_code(400);
    die('<p style="font-family:sans-serif;padding:20px;color:red;">Error: ID de orden requerido.</p>');
}

$orden = obtenerOrdenParaTicket($ordenId);
if (!$orden) {
    http_response_code(404);
    die('<p style="font-family:sans-serif;padding:20px;color:red;">Orden #' . $ordenId . ' no encontrada.</p>');
}

/* ── Datos calculados ── */
$numOrden    = str_pad($orden['id'], 4, '0', STR_PAD_LEFT);
$fechaRecep  = date('d/m/Y  H:i', strtotime($orden['fecha_recepcion']));
$fechaProm   = date('d/m/Y  H:i', strtotime($orden['fecha_promesa']));
$fechaEntreg = $orden['fecha_entrega']
               ? date('d/m/Y  H:i', strtotime($orden['fecha_entrega']))
               : '—';
$tasaBCV     = (float) ($orden['tasa_bcv_usada'] ?? obtenerTasaHoy()['tasa_bcv']);
$totalUSD    = (float) $orden['total_usd'];
$totalBS     = $orden['total_bs'] ?? convertirUSDaBS($totalUSD, $tasaBCV);
$metodoPago  = nombreMetodoPago($orden['metodo_pago']);
$estado      = $orden['estado'];

/* ── Determinar título y mensaje según tipo ── */
$esEntrega = ($tipo === 'entrega');

$tituloDoc = $esEntrega ? 'COMPROBANTE DE ENTREGA' : 'TICKET DE RECEPCIÓN';

$mensajeInferior = $esEntrega
    ? "¡Gracias por su preferencia!\nVuelva pronto a Lavandería Superior."
    : "Por favor conserve este ticket.\nLo necesitará para retirar su ropa.";

$totalKg = 0;
foreach ($orden['detalles'] as $det) {
    if (strtolower($det['unidad']) === 'kg') {
        $totalKg += (float) $det['cantidad'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticket #<?= $numOrden ?> — <?= $tituloDoc ?></title>
  <style>
    /* ============================================================
       TICKET CSS
       Ancho estándar tickera térmica: 80 mm  (aprox 302px @96dpi)
       Si tu tickera es de 58 mm cambia a 219px.
       ============================================================ */

    :root {
      --tick-w:      302px;   /* 80 mm */
      --tick-font:   'Courier New', Courier, monospace;
      --tick-size:   11px;
      --tick-gap:    4px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family:     var(--tick-font);
      font-size:       var(--tick-size);
      background:      #f0f0f0;
      display:         flex;
      justify-content: center;
      padding:         20px 10px;
      min-height:      100vh;
    }

    /* ── CONTENEDOR TICKET ── */
    .ticket {
      width:            var(--tick-w);
      background:       #fff;
      padding:          12px 10px 16px;
      border:           1px dashed #ccc;
      border-radius:    4px;
      box-shadow:       0 2px 12px rgba(0,0,0,0.12);
      line-height:      1.45;
      color:            #000;
    }

    /* ── CABECERA ── */
    .t-logo {
      text-align:    center;
      font-size:     15px;
      font-weight:   bold;
      letter-spacing:.06em;
      text-transform:uppercase;
      margin-bottom: 1px;
    }

    .t-subtitulo {
      text-align:    center;
      font-size:     9px;
      letter-spacing:.08em;
      margin-bottom: 2px;
    }

    .t-direccion {
      text-align:  center;
      font-size:   9px;
      color:       #444;
      margin-bottom:3px;
    }

    /* ── SEPARADORES ── */
    .sep-doble {
      border:   none;
      border-top: 2px double #000;
      margin:   5px 0;
    }

    .sep-simple {
      border:   none;
      border-top: 1px dashed #888;
      margin:   5px 0;
    }

    /* ── TÍTULO DEL DOCUMENTO ── */
    .t-tipo {
      text-align:    center;
      font-size:     10.5px;
      font-weight:   bold;
      letter-spacing:.06em;
      text-transform:uppercase;
      padding:       3px 0;
    }

    .t-tipo.entrega {
      background: #000;
      color:      #fff;
      padding:    3px 6px;
      margin:     2px 0;
    }

    /* ── NÚMERO DE ORDEN (grande y prominente) ── */
    .t-num-orden {
      text-align:    center;
      font-size:     28px;
      font-weight:   bold;
      letter-spacing:.04em;
      line-height:   1.1;
      margin:        4px 0 2px;
    }

    .t-num-label {
      text-align: center;
      font-size:  9px;
      color:      #555;
      margin-bottom: 4px;
    }

    /* ── FILAS INFO ── */
    .t-row {
      display:         flex;
      justify-content: space-between;
      font-size:       10px;
      margin-bottom:   2px;
      gap:             4px;
    }

    .t-row .label {
      color:      #555;
      flex-shrink:0;
      white-space:nowrap;
    }

    .t-row .val {
      text-align: right;
      font-weight:bold;
      word-break: break-word;
    }

    /* ── CLIENTE ── */
    .t-cliente-nombre {
      font-size:   11px;
      font-weight: bold;
      text-transform: uppercase;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* ── TABLA SERVICIOS ── */
    .t-servicios {
      width:           100%;
      border-collapse: collapse;
      font-size:       10px;
      margin:          4px 0;
    }

    .t-servicios thead th {
      font-size:    9px;
      text-align:   left;
      border-bottom:1px solid #000;
      padding:      1px 2px;
      text-transform:uppercase;
      letter-spacing:.04em;
    }

    .t-servicios thead th.r { text-align: right; }

    .t-servicios tbody td {
      padding:     2px 2px;
      vertical-align: top;
    }

    .t-servicios tbody td.r {
      text-align:  right;
      white-space: nowrap;
    }

    .t-servicios tfoot td {
      border-top:  1px solid #000;
      padding-top: 2px;
      font-weight: bold;
      font-size:   10px;
    }

    .t-servicios tfoot td.r { text-align: right; }

    /* ── TOTALES ── */
    .t-total-usd {
      display:        flex;
      justify-content:space-between;
      font-size:      13px;
      font-weight:    bold;
      margin:         3px 0 1px;
    }

    .t-total-bs {
      display:        flex;
      justify-content:space-between;
      font-size:      10px;
      color:          #333;
      margin-bottom:  2px;
    }

    .t-tasa {
      font-size: 9px;
      color:     #666;
      text-align:right;
      margin-bottom: 3px;
    }

    /* ── ESTADO BADGE (solo ticket entrega) ── */
    .t-estado {
      text-align:    center;
      font-size:     10px;
      font-weight:   bold;
      border:        1px solid #000;
      padding:       3px;
      margin:        4px 0;
      text-transform:uppercase;
      letter-spacing:.08em;
    }

    /* ── CÓDIGO DE BARRAS VISUAL (generado con CSS) ── */
    .t-barcode-wrap {
      text-align: center;
      margin:     6px 0 2px;
    }

    .t-barcode-wrap canvas {
      display: block;
      margin:  0 auto;
    }

    .t-barcode-num {
      font-size:     9px;
      letter-spacing:.2em;
      text-align:    center;
      margin-top:    2px;
    }

    /* ── AVISO / CONDICIONES ── */
    .t-aviso {
      font-size:  8.5px;
      color:      #555;
      text-align: center;
      line-height:1.4;
      margin:     4px 0 2px;
      white-space:pre-line;
    }

    /* ── MENSAJE FINAL ── */
    .t-mensaje {
      text-align:  center;
      font-size:   9px;
      white-space: pre-line;
      color:       #333;
      margin-top:  4px;
    }

    /* ── FOOTER ── */
    .t-footer {
      text-align: center;
      font-size:  8px;
      color:      #888;
      margin-top: 6px;
    }

    /* ── BOTONES DE CONTROL (no se imprimen) ── */
    .print-controls {
      position:  fixed;
      bottom:    20px;
      right:     20px;
      display:   flex;
      flex-direction: column;
      gap:       8px;
      z-index:   999;
    }

    .print-controls button {
      padding:       10px 20px;
      border:        none;
      border-radius: 6px;
      cursor:        pointer;
      font-size:     13px;
      font-weight:   bold;
      font-family:   sans-serif;
    }

    .btn-imprimir { background: #111; color: #fff; }
    .btn-imprimir:hover { background: #333; }
    .btn-cerrar   { background: #e5e7eb; color: #111; }
    .btn-cerrar:hover   { background: #d1d5db; }

    /* ============================================================
       @MEDIA PRINT — lo que sale al imprimir / enviar a tickera
       ============================================================ */
    @media print {

      @page {
        /* Sin márgenes → el ticket ocupa todo el ancho del papel */
        margin: 0;
        padding: 0;
        /* 80 mm de ancho, alto automático */
        size: 80mm auto;
      }

      body {
        background:  white;
        padding:     0;
        margin:      0;
        display:     block;
      }

      .ticket {
        width:      100%;
        box-shadow: none;
        border:     none;
        padding:    4px 6px 8px;
        margin:     0;
      }

      /* Ocultar controles */
      .print-controls { display: none !important; }
    }
  </style>
</head>
<body>

<!-- ══════════════════════════════════════════════
     TICKET
════════════════════════════════════════════════ -->
<div class="ticket" id="ticket">

  <!-- CABECERA -->
  <div class="t-logo">&#9783; Lavandería Superior</div>
  <div class="t-subtitulo">Servicio de Lavado Profesional</div>
  <div class="t-direccion">Valle de la Pascua, Guárico · Venezuela</div>

  <hr class="sep-doble">

  <!-- TIPO DE DOCUMENTO -->
  <div class="t-tipo <?= $esEntrega ? 'entrega' : '' ?>">
    <?= $tituloDoc ?>
  </div>

  <!-- NÚMERO DE ORDEN -->
  <div class="t-num-orden">#<?= $numOrden ?></div>
  <div class="t-num-label">NÚMERO DE ORDEN</div>

  <hr class="sep-simple">

  <!-- CLIENTE -->
  <div class="t-row">
    <span class="label">Cliente:</span>
    <span class="val t-cliente-nombre"><?= strtoupper(h($orden['cliente_nombre'])) ?></span>
  </div>
  <div class="t-row">
    <span class="label">C.I. / RIF:</span>
    <span class="val"><?= h($orden['cliente_cedula']) ?></span>
  </div>
  <div class="t-row">
    <span class="label">Teléfono:</span>
    <span class="val"><?= h($orden['cliente_telefono']) ?></span>
  </div>

  <hr class="sep-simple">

  <!-- FECHAS -->
  <div class="t-row">
    <span class="label">Recepción:</span>
    <span class="val"><?= $fechaRecep ?></span>
  </div>
  <div class="t-row">
    <span class="label">Entrega prom.:</span>
    <span class="val"><?= $fechaProm ?></span>
  </div>
  <?php if ($esEntrega): ?>
  <div class="t-row">
    <span class="label">Entregado:</span>
    <span class="val"><?= $fechaEntreg ?></span>
  </div>
  <?php endif; ?>

  <?php if ($orden['metodo_pago']): ?>
  <div class="t-row">
    <span class="label">Pago:</span>
    <span class="val"><?= h($metodoPago) ?></span>
  </div>
  <?php endif; ?>

  <hr class="sep-simple">

  <!-- TABLA DE SERVICIOS -->
  <table class="t-servicios">
    <thead>
      <tr>
        <th style="width:45%">Servicio</th>
        <th class="r" style="width:18%">Cant.</th>
        <th class="r" style="width:18%">P/U</th>
        <th class="r" style="width:19%">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orden['detalles'] as $det): ?>
      <tr>
        <td><?= h($det['servicio_nombre']) ?></td>
        <td class="r"><?= number_format((float)$det['cantidad'], 2) ?> <?= $det['unidad'] ?></td>
        <td class="r">$<?= number_format((float)$det['precio_usd'], 2) ?></td>
        <td class="r">$<?= number_format((float)$det['subtotal'], 2) ?></td>
      </tr>
      <?php if ($det['linea_notas']): ?>
      <tr>
        <td colspan="4" style="font-size:8.5px;color:#555;padding-left:4px;">
          ↳ <?= h($det['linea_notas']) ?>
        </td>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <?php if ($totalKg > 0): ?>
      <tr>
        <td colspan="2" style="font-size:9px;color:#555;">
          Total: <?= number_format($totalKg, 2) ?> kg
        </td>
        <td class="r" colspan="2" style="font-size:9px;"></td>
      </tr>
      <?php endif; ?>
    </tfoot>
  </table>

  <?php if (!empty($orden['prendas'])):
    $totalPrendas = array_sum(array_column($orden['prendas'], 'cantidad')); ?>
  <hr class="sep-simple">
  <!-- PRENDAS RECIBIDAS -->
  <table class="t-servicios">
    <thead>
      <tr>
        <th style="width:80%">Prendas recibidas</th>
        <th class="r" style="width:20%">Cant.</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orden['prendas'] as $pr): ?>
      <tr>
        <td><?= h($pr['descripcion']) ?><?php if ($pr['observacion']): ?><br><span style="font-size:8.5px;color:#555;">↳ <?= h($pr['observacion']) ?></span><?php endif; ?></td>
        <td class="r"><?= (int)$pr['cantidad'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td style="font-weight:700;">TOTAL PRENDAS</td>
        <td class="r" style="font-weight:700;"><?= (int)$totalPrendas ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

  <hr class="sep-simple">

  <!-- TOTALES -->
  <div class="t-total-usd">
    <span>TOTAL USD:</span>
    <span>$<?= number_format($totalUSD, 2) ?></span>
  </div>
  <div class="t-total-bs">
    <span>Total Bs.:</span>
    <span>Bs. <?= number_format($totalBS, 2, ',', '.') ?></span>
  </div>
  <div class="t-tasa">
    Tasa BCV: Bs. <?= number_format($tasaBCV, 2, ',', '.') ?> / USD
  </div>

  <?php if ($esEntrega): ?>
  <!-- ESTADO (solo en entrega) -->
  <div class="t-estado">✓ ENTREGADO — PAGADO</div>
  <?php else: ?>
  <!-- NOTAS -->
  <?php if ($orden['notas']): ?>
  <hr class="sep-simple">
  <div style="font-size:9px;color:#444;">
    <strong>Observaciones:</strong><br>
    <?= nl2br(h($orden['notas'])) ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <hr class="sep-doble">

  <!-- CÓDIGO DE BARRAS VISUAL (simple, generado en canvas) -->
  <div class="t-barcode-wrap">
    <canvas id="barcode-canvas" height="36"></canvas>
    <div class="t-barcode-num">
      <?= str_pad($orden['id'], 8, '0', STR_PAD_LEFT) ?>
    </div>
  </div>

  <hr class="sep-simple">

  <!-- AVISO -->
  <div class="t-aviso"><?php
    if (!$esEntrega):
      echo "* El negocio no se hace responsable por\n"
         . "  ropa dejada más de 30 días.\n"
         . "* Presente este ticket al retirar.\n"
         . "* Puede consultar su orden por WhatsApp.";
    else:
      echo "* Este comprobante es su recibo de pago.\n"
         . "* Gracias por confiar en nosotros.";
    endif;
  ?></div>

  <hr class="sep-simple">

  <!-- MENSAJE FINAL -->
  <div class="t-mensaje"><?= $mensajeInferior ?></div>

  <!-- FOOTER -->
  <div class="t-footer">
    Impreso: <?= date('d/m/Y H:i:s') ?> &nbsp;|&nbsp; Sistema LS v1.0
  </div>

</div>
<!-- /ticket -->

<!-- CONTROLES (no se imprimen) -->
<div class="print-controls" id="print-controls">
  <button class="btn-imprimir" onclick="window.print()">
    🖨 Imprimir ticket
  </button>
  <button class="btn-cerrar" onclick="window.close()">
    ✕ Cerrar
  </button>
</div>

<script>
/* ── Código de barras simple (líneas en canvas) ─────────────
   Genera un código de barras Code 39 simplificado visualmente.
   Para producción real se puede usar JsBarcode:
   https://github.com/lindell/JsBarcode (1 archivo JS, sin deps)
   ──────────────────────────────────────────────────────────── */
(function () {
  var canvas  = document.getElementById('barcode-canvas');
  if (!canvas) return;

  var orderId = <?= $orden['id'] ?>;
  var num     = String(orderId).padStart(8, '0');
  var ctx     = canvas.getContext('2d');

  // Ancho proporcional al ticket
  var ticketEl = document.querySelector('.ticket');
  var w        = ticketEl ? (ticketEl.clientWidth - 20) : 260;
  canvas.width  = w;
  canvas.height = 36;

  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, w, 36);

  // Patrón pseudo-barras basado en el número de orden
  ctx.fillStyle = '#000';

  var barW   = Math.floor(w / 70);
  var x      = 2;
  var chars  = (num + '0000000000').split('');

  // Patrón de inicio
  [3,1,1,1,3,1,1].forEach(function(n) {
    ctx.fillRect(x, 2, n * barW, 32);
    x += (n + 1) * barW;
  });

  // Datos del número de orden
  chars.slice(0, 8).forEach(function(c) {
    var d = parseInt(c, 10);
    var pattern = [
      [3,1,1,1,1,1,3],[1,3,1,1,1,3,1],[3,3,1,1,1,1,1],
      [1,1,1,3,1,3,1],[3,1,1,3,1,1,1],[1,3,1,3,1,1,1],
      [1,1,1,1,3,3,1],[3,1,1,1,3,1,1],[1,3,1,1,3,1,1],
      [1,1,1,3,3,1,1]
    ][d];
    pattern.forEach(function(n, i) {
      if (i % 2 === 0) ctx.fillRect(x, 2, n * barW, 32);
      x += (n + 1) * barW;
    });
  });

  // Patrón de cierre
  [3,1,1,1,1,3].forEach(function(n) {
    ctx.fillRect(x, 2, n * barW, 32);
    x += (n + 1) * barW;
  });
})();

/* ── Auto-impresión si viene ?auto=1 ──────────────────────── */
<?php if ($auto): ?>
window.addEventListener('load', function () {
  // Pequeño delay para que el canvas termine de renderizar
  setTimeout(function () { window.print(); }, 350);
});
<?php endif; ?>
</script>
</body>
</html>

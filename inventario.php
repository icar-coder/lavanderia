<?php
require_once __DIR__ . '/config/init.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $a = entrada($_POST, 'action');
    if ($a === 'crear' || $a === 'editar') {
        $id     = $a === 'editar' ? (int) entrada($_POST, 'id') : 0;
        $nombre = limpiarTexto(entrada($_POST, 'nombre'));
        $unidad = entrada($_POST, 'unidad');
        $sa  = numeroEnRango(entrada($_POST, 'stock_actual'), 0, 10000000);
        $sm  = numeroEnRango(entrada($_POST, 'stock_minimo'), 0, 10000000);
        // Vacíos se toman como 0 (campos opcionales)
        $cpk = numeroEnRango(entrada($_POST, 'consumo_por_kg') === '' ? '0' : entrada($_POST, 'consumo_por_kg'), 0, 100000);
        $cu  = numeroEnRango(entrada($_POST, 'costo_unidad') === '' ? '0' : entrada($_POST, 'costo_unidad'), 0, 1000000);
        $err = null;
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) $err = 'El nombre debe tener entre 2 y 100 caracteres.';
        elseif (!preg_match("/^[\p{L}0-9][\p{L}0-9\s\.,\/\(\)\-&%+]*$/u", $nombre)) $err = 'El nombre solo puede contener letras, números, espacios y . , / ( ) - & % +';
        elseif (!in_array($unidad, ['ml','L','g','Kg','Unidad'], true)) $err = 'Unidad inválida.';
        elseif ($sa === null)  $err = 'El stock actual debe ser un número mayor o igual a 0.';
        elseif ($sm === null)  $err = 'El stock mínimo debe ser un número mayor o igual a 0.';
        elseif ($cpk === null) $err = 'El consumo por Kg debe ser un número mayor o igual a 0.';
        elseif ($cu === null)  $err = 'El costo debe ser un número mayor o igual a 0.';
        if (!$err && $a === 'editar') {
            $chk = getDB()->prepare("SELECT id FROM inventario_insumos WHERE id = ?");
            $chk->execute([$id]);
            if (!$chk->fetch()) $err = 'El insumo no existe.';
        }
        if (!$err) {
            $dup = getDB()->prepare("SELECT id FROM inventario_insumos WHERE LOWER(nombre) = LOWER(?) AND activo = 1 AND id <> ?");
            $dup->execute([$nombre, $id]);
            if ($dup->fetch()) $err = 'Ya existe un insumo con ese nombre.';
        }
        if ($err) { echo json_encode(['success'=>false,'error'=>$err]); exit; }
        $sql = $a === 'crear'
          ? "INSERT INTO inventario_insumos (nombre,unidad,stock_actual,stock_minimo,consumo_por_kg,costo_unidad) VALUES(:n,:u,:sa,:sm,:cpk,:cu)"
          : "UPDATE inventario_insumos SET nombre=:n,unidad=:u,stock_actual=:sa,stock_minimo=:sm,consumo_por_kg=:cpk,costo_unidad=:cu WHERE id=:id";
        $params = [':n'=>$nombre,':u'=>$unidad,':sa'=>$sa,':sm'=>$sm,':cpk'=>$cpk,':cu'=>$cu];
        if ($a === 'editar') $params[':id'] = $id;
        try { getDB()->prepare($sql)->execute($params); echo json_encode(['success'=>true]); }
        catch(Exception $e){ echo json_encode(['success'=>false,'error'=>'No se pudo guardar el insumo.']); }
        exit;
    }
    if ($a === 'ajuste') {
        $id = (int) entrada($_POST, 'id');
        $tipo = entrada($_POST, 'tipo');
        $cantidad = numeroEnRango(entrada($_POST, 'cantidad'), 0.001, 10000000);
        if (!in_array($tipo, ['entrada','salida'], true)) {
            echo json_encode(['success'=>false,'error'=>'Tipo de ajuste inválido.']); exit;
        }
        if ($cantidad === null) {
            echo json_encode(['success'=>false,'error'=>'Indique una cantidad mayor que cero.']); exit;
        }
        $st = getDB()->prepare("SELECT stock_actual, unidad FROM inventario_insumos WHERE id = ? AND activo = 1");
        $st->execute([$id]);
        $ins = $st->fetch();
        if (!$ins) { echo json_encode(['success'=>false,'error'=>'El insumo no existe.']); exit; }
        if ($tipo === 'salida' && $cantidad > (float) $ins['stock_actual']) {
            echo json_encode(['success'=>false,'error'=>'La salida (' . rtrim(rtrim(number_format($cantidad, 3, '.', ''), '0'), '.') . ' ' . $ins['unidad'] .
                ') supera el stock disponible (' . rtrim(rtrim(number_format((float) $ins['stock_actual'], 3, '.', ''), '0'), '.') . ' ' . $ins['unidad'] . ').']); exit;
        }
        if ($tipo === 'entrada' && (float) $ins['stock_actual'] + $cantidad > 10000000) {
            echo json_encode(['success'=>false,'error'=>'El stock resultante supera el máximo permitido.']); exit;
        }
        $sql = $tipo==='entrada'
          ? "UPDATE inventario_insumos SET stock_actual=stock_actual+? WHERE id=?"
          : "UPDATE inventario_insumos SET stock_actual=stock_actual-? WHERE id=?";
        getDB()->prepare($sql)->execute([$cantidad,$id]);
        echo json_encode(['success'=>true]); exit;
    }
    echo json_encode(['success'=>false,'error'=>'Acción desconocida.']); exit;
}

if (isset($_GET['get_id'])) {
    header('Content-Type: application/json');
    $s = getDB()->prepare("SELECT * FROM inventario_insumos WHERE id=?");
    $s->execute([(int) entrada($_GET, 'get_id')]);
    echo json_encode($s->fetch() ?: []); exit;
}

$insumos  = getDB()->query("SELECT * FROM inventario_insumos WHERE activo=1 ORDER BY nombre")->fetchAll();
$alertas  = count(obtenerAlertasStock());
$sinStock = count(array_filter($insumos, fn($i) => (float)$i['stock_actual'] == 0));
$stockBaj = count(array_filter($insumos, fn($i) => (float)$i['stock_actual'] > 0 && $i['stock_actual'] <= $i['stock_minimo']));
$enOrden  = count($insumos) - $sinStock - $stockBaj;
$valorTotal = array_sum(array_map(fn($i) => (float)$i['stock_actual'] * (float)($i['costo_unidad']??0), $insumos));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Inventario — Lavandería Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    /* KPIs */
    .inv-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:18px; }
    .inv-kpi  { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:16px 18px; display:flex; align-items:center; gap:13px; transition:border-color .15s; }
    .inv-kpi:hover { border-color:var(--border-md); }
    .inv-kpi__icon { width:40px; height:40px; border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .inv-kpi__val  { font-family:var(--font-display); font-size:26px; font-weight:800; color:var(--text); line-height:1; }
    .inv-kpi__lbl  { font-size:10.5px; color:var(--text-hint); margin-top:3px; text-transform:uppercase; letter-spacing:.07em; }

    /* Tabla inventario */
    .itbl { width:100%; border-collapse:collapse; }
    .itbl thead th { padding:10px 14px; font-size:10px; font-weight:600; letter-spacing:.09em; text-transform:uppercase; color:var(--text-hint); border-bottom:1px solid var(--border); text-align:left; white-space:nowrap; }
    .itbl tbody td { padding:13px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .itbl tbody tr:last-child td { border-bottom:none; }
    .itbl tbody tr:hover td { background:var(--bg-surface2); }
    .itbl tbody tr.row-danger td { background:rgba(248,113,113,.04); }
    .itbl tbody tr.row-warning td { background:rgba(251,191,36,.04); }

    /* Nivel badge */
    .nivel-badge { display:inline-flex; align-items:center; gap:4px; font-size:10.5px; font-weight:700; padding:3px 10px; border-radius:20px; letter-spacing:.03em; text-transform:uppercase; }
    .nivel-ok      { background:var(--green-bg);  color:var(--green); border:1px solid var(--green-border);  }
    .nivel-warning { background:var(--amber-bg);  color:var(--amber); border:1px solid var(--amber-border);  }
    .nivel-danger  { background:var(--red-bg);    color:var(--red);   border:1px solid var(--red-border);    }

    /* Barra de stock */
    .sbar-wrap { width:90px; height:6px; background:var(--bg-surface3); border-radius:4px; overflow:hidden; }
    .sbar      { height:100%; border-radius:4px; transition:width .3s ease; }
    .sbar.ok      { background:var(--green); }
    .sbar.warning { background:var(--amber); }
    .sbar.danger  { background:var(--red); }

    /* Botones acción */
    .inv-acts { display:flex; gap:6px; }
    .inv-btn  { display:inline-flex; align-items:center; gap:4px; padding:5px 10px; border-radius:var(--radius-sm); font-size:11.5px; font-weight:600; cursor:pointer; border:1px solid; font-family:var(--font-body); transition:all .15s; white-space:nowrap; }
    .inv-btn.entrada { background:var(--green-bg); color:var(--green); border-color:var(--green-border); }
    .inv-btn.entrada:hover { background:rgba(52,211,153,.2); }
    .inv-btn.salida  { background:var(--red-bg);   color:var(--red);   border-color:var(--red-border);   }
    .inv-btn.salida:hover  { background:rgba(248,113,113,.2); }
    .inv-btn.editar  { background:var(--bg-surface2); color:var(--text-sub); border-color:var(--border-md); }
    .inv-btn.editar:hover  { border-color:var(--accent-border); color:var(--accent-light); }

    /* Modales */
    .modal-bd { position:fixed; inset:0; background:rgba(0,0,0,.65); backdrop-filter:blur(4px); z-index:500; display:none; align-items:center; justify-content:center; padding:20px; }
    .modal-bx { background:var(--bg-surface); border:1px solid var(--border-md); border-radius:var(--radius-xl); width:100%; overflow:hidden; animation:mIn .2s ease; }
    @keyframes mIn { from{opacity:0;transform:scale(.97) translateY(8px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .modal-hd { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid var(--border); }
    .modal-tt { font-family:var(--font-display); font-size:17px; font-weight:700; color:var(--text); }
    .modal-xb { width:28px; height:28px; border-radius:var(--radius-sm); border:1px solid var(--border); background:transparent; color:var(--text-hint); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; }
    .modal-xb:hover { border-color:var(--red-border); color:var(--red); background:var(--red-bg); }
    .modal-bd2 { padding:20px 24px; }
    .modal-ft { padding:14px 24px 20px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border); }

    #toast-container { position:fixed; bottom:24px; right:24px; z-index:1000; display:flex; flex-direction:column; gap:8px; }
    .toast { display:flex; align-items:center; gap:10px; padding:11px 16px; background:var(--bg-surface2); border:1px solid var(--border-md); border-radius:var(--radius-md); font-size:13px; color:var(--text); min-width:240px; animation:sfr .2s ease; box-shadow:0 4px 20px rgba(0,0,0,.4); }
    .toast.success { border-color:var(--green-border); }
    .toast.error   { border-color:var(--red-border); }
    @keyframes sfr { from{opacity:0;transform:translateX(14px)} to{opacity:1;transform:translateX(0)} }
  </style>
</head>
<body>
<div class="layout">
  <?php partial('sidebar'); ?>
  <main class="main-content">

    <div class="topbar">
      <div>
        <h1 class="page-title">Inventario</h1>
        <p class="page-sub">Control de insumos y químicos</p>
      </div>
      <div class="topbar-actions">
        <?php if($alertas > 0): ?>
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:5px 11px;border-radius:20px;background:var(--red-bg);color:var(--red);border:1px solid var(--red-border);">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
          <?= $alertas ?> alertas
        </span>
        <?php endif; ?>
        <a href="inventario_pdf.php?solo=bajos" target="_blank" class="btn btn-secondary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
          PDF Alertas
        </a>
        <a href="inventario_pdf.php" target="_blank" class="btn btn-secondary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          PDF Completo
        </a>
        <button class="btn btn-primary" onclick="abrirModal()" data-tip="Registrar un insumo nuevo" data-tip-pos="bottom">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuevo Insumo
        </button>
      </div>
    </div>

    <div class="content-area">

      <!-- KPIs -->
      <div class="inv-kpis">
        <div class="inv-kpi">
          <div class="inv-kpi__icon" style="background:var(--accent-bg);border:1px solid var(--accent-border);color:var(--accent-light);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 2 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
          </div>
          <div>
            <p class="inv-kpi__val"><?= count($insumos) ?></p>
            <p class="inv-kpi__lbl">Total insumos</p>
          </div>
        </div>
        <div class="inv-kpi">
          <div class="inv-kpi__icon" style="background:var(--green-bg);border:1px solid var(--green-border);color:var(--green);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div>
            <p class="inv-kpi__val" style="color:var(--green);"><?= $enOrden ?></p>
            <p class="inv-kpi__lbl">En orden</p>
          </div>
        </div>
        <div class="inv-kpi">
          <div class="inv-kpi__icon" style="background:var(--amber-bg);border:1px solid var(--amber-border);color:var(--amber);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
          </div>
          <div>
            <p class="inv-kpi__val" style="color:var(--amber);"><?= $stockBaj ?></p>
            <p class="inv-kpi__lbl">Stock bajo</p>
          </div>
        </div>
        <div class="inv-kpi">
          <div class="inv-kpi__icon" style="background:var(--red-bg);border:1px solid var(--red-border);color:var(--red);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </div>
          <div>
            <p class="inv-kpi__val" style="color:var(--red);"><?= $sinStock ?></p>
            <p class="inv-kpi__lbl">Sin stock</p>
          </div>
        </div>
      </div>

      <!-- Tabla -->
      <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
          <div>
            <p style="font-family:var(--font-display);font-size:14.5px;font-weight:700;color:var(--text);">Detalle de Insumos</p>
            <?php if($valorTotal > 0): ?>
            <p style="font-size:11.5px;color:var(--text-hint);margin-top:2px;">
              Valor estimado del inventario: <strong style="color:var(--accent-light);"><?= formatUSD($valorTotal) ?></strong>
            </p>
            <?php endif; ?>
          </div>
        </div>
        <table class="itbl">
          <thead>
            <tr>
              <th>Insumo</th>
              <th>Unidad</th>
              <th>Stock actual</th>
              <th>Mínimo</th>
              <th>Nivel</th>
              <th>Consumo / Kg</th>
              <th>Costo / Unidad</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($insumos as $ins):
              $sa    = (float)$ins['stock_actual'];
              $sm    = (float)$ins['stock_minimo'];
              $pct   = $sm > 0 ? min(100, ($sa / ($sm * 2)) * 100) : 100;
              if ($sa == 0)       { $nivel = 'danger';  $nClass = 'nivel-danger';  $rClass = 'row-danger';  }
              elseif ($sa <= $sm) { $nivel = 'warning'; $nClass = 'nivel-warning'; $rClass = 'row-warning'; }
              else                { $nivel = 'ok';      $nClass = 'nivel-ok';      $rClass = ''; }
            ?>
            <tr class="<?= $rClass ?>">
              <td>
                <p style="font-size:13.5px;font-weight:500;color:var(--text);"><?= h($ins['nombre']) ?></p>
              </td>
              <td style="font-size:12px;color:var(--text-sub);"><?= $ins['unidad'] ?></td>
              <td>
                <span style="font-family:var(--font-mono);font-size:14px;font-weight:700;
                  color:<?= $nivel==='danger'?'var(--red)':($nivel==='warning'?'var(--amber)':'var(--green)') ?>;">
                  <?= number_format($sa, 2) ?>
                </span>
              </td>
              <td style="font-family:var(--font-mono);font-size:12.5px;color:var(--text-hint);">
                <?= number_format($sm, 2) ?>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:nowrap;">
                  <div class="sbar-wrap">
                    <div class="sbar <?= $nivel ?>" style="width:<?= max(3,$pct) ?>%;"></div>
                  </div>
                  <span class="nivel-badge <?= $nClass ?>">
                    <?= $nivel==='danger' ? 'Sin stock' : ($nivel==='warning' ? 'Bajo' : 'OK') ?>
                  </span>
                </div>
              </td>
              <td style="font-size:12px;color:var(--text-sub);">
                <?= $ins['consumo_por_kg'] ? number_format((float)$ins['consumo_por_kg'],3).' '.$ins['unidad'].'/kg' : '—' ?>
              </td>
              <td style="font-size:12.5px;color:var(--text-sub);">
                <?= $ins['costo_unidad'] ? formatUSD((float)$ins['costo_unidad']) : '—' ?>
              </td>
              <td>
                <div class="inv-acts">
                  <button class="inv-btn entrada" data-tip="Registrar entrada (compra o reposición)" onclick="abrirAjuste(<?= (int)$ins['id'] ?>,'entrada',<?= h(json_encode($ins['nombre'])) ?>)">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Entrada
                  </button>
                  <button class="inv-btn salida" data-tip="Registrar salida (uso, pérdida o merma)" onclick="abrirAjuste(<?= (int)$ins['id'] ?>,'salida',<?= h(json_encode($ins['nombre'])) ?>)">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Salida
                  </button>
                  <button class="inv-btn editar" data-tip="Editar datos del insumo" onclick="editarInsumo(<?= $ins['id'] ?>)">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Editar
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($insumos)): ?>
            <tr><td colspan="8" style="padding:48px;text-align:center;color:var(--text-hint);font-size:13.5px;">
              No hay insumos registrados
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>

<!-- MODAL INSUMO -->
<div id="modal" class="modal-bd">
  <div class="modal-bx" style="max-width:500px;">
    <div class="modal-hd">
      <h2 class="modal-tt" id="modal-title">Nuevo Insumo</h2>
      <button class="modal-xb" onclick="cerrarModal('modal')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-bd2">
      <input type="hidden" id="i-id">
      <div class="form-row form-row-2" style="margin-bottom:14px;">
        <div class="form-group">
          <label class="form-label">Nombre *</label>
          <input id="i-nombre" class="form-control" maxlength="100" placeholder="Detergente Industrial">
        </div>
        <div class="form-group">
          <label class="form-label">Unidad *</label>
          <select id="i-unidad" class="form-control">
            <option value="ml">Mililitros (ml)</option>
            <option value="L">Litros (L)</option>
            <option value="g">Gramos (g)</option>
            <option value="Kg">Kilogramos (Kg)</option>
            <option value="Unidad">Unidad</option>
          </select>
        </div>
      </div>
      <div class="form-row form-row-2" style="margin-bottom:14px;">
        <div class="form-group">
          <label class="form-label">Stock Actual</label>
          <input id="i-stock" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="12" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">Stock Mínimo <span class="help-dot" tabindex="0" data-tip="Cuando el stock llegue a este valor se mostrará una alerta en el Dashboard.">?</span></label>
          <input id="i-minimo" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="12" value="500">
        </div>
      </div>
      <div class="form-row form-row-2">
        <div class="form-group">
          <label class="form-label">Consumo por Kg lavado <span class="help-dot" tabindex="0" data-tip="Cantidad que se descuenta automáticamente por cada kilo de ropa en las órdenes. Use 0 si no se consume por kilo.">?</span></label>
          <input id="i-consumo" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="10" value="0" placeholder="ml o g por kg">
        </div>
        <div class="form-group">
          <label class="form-label">Costo por unidad (USD)</label>
          <input id="i-costo" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="10" value="0">
        </div>
      </div>
    </div>
    <div class="modal-ft">
      <button class="btn btn-secondary" onclick="cerrarModal('modal')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarInsumo()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Guardar Insumo
      </button>
    </div>
  </div>
</div>

<!-- MODAL AJUSTE -->
<div id="modal-ajuste" class="modal-bd">
  <div class="modal-bx" style="max-width:380px;">
    <div class="modal-hd">
      <h2 class="modal-tt" id="ajuste-title">Registrar Entrada</h2>
      <button class="modal-xb" onclick="cerrarModal('modal-ajuste')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-bd2">
      <input type="hidden" id="aj-id">
      <input type="hidden" id="aj-tipo">
      <p id="aj-nombre" style="font-size:13.5px;font-weight:500;color:var(--text);margin-bottom:16px;padding:10px 14px;background:var(--bg-surface2);border-radius:var(--radius-md);border:1px solid var(--border);"></p>
      <div class="form-group">
        <label class="form-label">Cantidad a ajustar</label>
        <input id="aj-cantidad" class="form-control" data-mask="decimal" inputmode="decimal" maxlength="10" value="1" style="font-size:18px;text-align:center;font-weight:700;">
      </div>
    </div>
    <div class="modal-ft">
      <button class="btn btn-secondary" onclick="cerrarModal('modal-ajuste')">Cancelar</button>
      <button class="btn btn-primary" id="ajuste-btn" onclick="guardarAjuste()">Confirmar</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>
<script>
function cerrarModal(id){ document.getElementById(id).style.display='none'; }
function abrirModal(clear=true){
  if(clear){
    document.getElementById('i-id').value='';
    document.getElementById('modal-title').textContent='Nuevo Insumo';
    ['nombre','stock','minimo','consumo','costo'].forEach(f=>{ const el=document.getElementById('i-'+f); if(el) el.value=''; });
    document.getElementById('i-stock').value='0';
    document.getElementById('i-minimo').value='500';
    document.getElementById('i-consumo').value='0';
    document.getElementById('i-costo').value='0';
  }
  document.getElementById('modal').style.display='flex';
  setTimeout(()=>document.getElementById('i-nombre').focus(),80);
}
function editarInsumo(id){
  fetch('inventario.php?get_id='+id).then(r=>r.json()).then(d=>{
    document.getElementById('i-id').value=d.id;
    document.getElementById('i-nombre').value=d.nombre;
    document.getElementById('i-unidad').value=d.unidad;
    document.getElementById('i-stock').value=d.stock_actual;
    document.getElementById('i-minimo').value=d.stock_minimo;
    document.getElementById('i-consumo').value=d.consumo_por_kg||0;
    document.getElementById('i-costo').value=d.costo_unidad||0;
    document.getElementById('modal-title').textContent='Editar Insumo';
    abrirModal(false);
  });
}
function guardarInsumo(){
  const $ = id => document.getElementById(id);
  if(!Valida.comprobar([
    [$('i-nombre'),  $('i-nombre').value.trim().length < 2 || $('i-nombre').value.trim().length > 100 ? 'El nombre debe tener entre 2 y 100 caracteres.' : null],
    [$('i-stock'),   Valida.numero($('i-stock').value, 0, 10000000, 'El stock actual')],
    [$('i-minimo'),  Valida.numero($('i-minimo').value, 0, 10000000, 'El stock mínimo')],
    [$('i-consumo'), Valida.numero($('i-consumo').value || 0, 0, 100000, 'El consumo')],
    [$('i-costo'),   Valida.numero($('i-costo').value || 0, 0, 1000000, 'El costo')],
  ], $('modal'))) return;
  const fd=new FormData(), id=document.getElementById('i-id').value;
  fd.append('action',id?'editar':'crear'); if(id) fd.append('id',id);
  fd.append('nombre',document.getElementById('i-nombre').value);
  fd.append('unidad',document.getElementById('i-unidad').value);
  fd.append('stock_actual',document.getElementById('i-stock').value);
  fd.append('stock_minimo',document.getElementById('i-minimo').value);
  fd.append('consumo_por_kg',document.getElementById('i-consumo').value);
  fd.append('costo_unidad',document.getElementById('i-costo').value);
  fetch('inventario.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){ toast('Insumo guardado','success'); cerrarModal('modal'); setTimeout(()=>location.reload(),700); }
    else toast(d.error||'No se pudo guardar','error');
  });
}
function abrirAjuste(id,tipo,nombre){
  document.getElementById('aj-id').value=id;
  document.getElementById('aj-tipo').value=tipo;
  document.getElementById('aj-nombre').textContent=nombre;
  document.getElementById('ajuste-title').textContent=tipo==='entrada'?'Registrar Entrada de Stock':'Registrar Salida de Stock';
  const btn=document.getElementById('ajuste-btn');
  btn.style.background=tipo==='entrada'?'var(--green)':'var(--red)';
  btn.textContent=tipo==='entrada'?'✓ Confirmar Entrada':'✓ Confirmar Salida';
  document.getElementById('aj-cantidad').value=1;
  document.getElementById('modal-ajuste').style.display='flex';
  setTimeout(()=>document.getElementById('aj-cantidad').focus(),80);
}
function guardarAjuste(){
  const inp=document.getElementById('aj-cantidad');
  if(!Valida.comprobar([[inp, Valida.numero(inp.value, 0.001, 10000000, 'La cantidad')]], document.getElementById('modal-ajuste'))) return;
  const fd=new FormData();
  fd.append('action','ajuste');
  fd.append('id',document.getElementById('aj-id').value);
  fd.append('tipo',document.getElementById('aj-tipo').value);
  fd.append('cantidad',document.getElementById('aj-cantidad').value);
  fetch('inventario.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){ toast('Ajuste registrado','success'); cerrarModal('modal-ajuste'); setTimeout(()=>location.reload(),700); }
    else toast(d.error||'Error al registrar ajuste','error');
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

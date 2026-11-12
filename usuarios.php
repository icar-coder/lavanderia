<?php
require_once 'config/init.php';
requireAdmin();

// ── AJAX ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $a = entrada($_POST, 'action');

    if ($a === 'crear') {
        $res = crearUsuario(
            entrada($_POST, 'nombre'),
            entrada($_POST, 'username'),
            entrada($_POST, 'password'),
            entrada($_POST, 'rol'),
            entrada($_POST, 'clave_maestra')
        );
        echo json_encode($res); exit;
    }

    if ($a === 'resetear') {
        $res = resetearPassword(
            (int) entrada($_POST, 'id'),
            entrada($_POST, 'nueva_pass'),
            entrada($_POST, 'clave_maestra')
        );
        echo json_encode($res); exit;
    }

    if ($a === 'toggle') {
        echo json_encode(toggleUsuario((int) entrada($_POST, 'id'))); exit;
    }

    if ($a === 'cambiar_pass_propio') {
        $res = cambiarPasswordPropio(
            (int)($_SESSION['usuario_id'] ?? 0),
            entrada($_POST, 'pass_actual'),
            entrada($_POST, 'nueva_pass')
        );
        echo json_encode($res); exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Acción desconocida']); exit;
}

$usuarios    = listarUsuarios();
$rolActualUs = rolActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Usuarios — Lavandería Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    .rol-badge-admin    { background:var(--accent-bg);  color:var(--accent-light); border:1px solid var(--accent-border); padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
    .rol-badge-vendedor { background:var(--teal-bg);    color:var(--teal);         border:1px solid var(--teal-border);   padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
    .pass-strength { height:3px; border-radius:2px; margin-top:6px; transition:all .3s; }
    .master-hint {
      font-size:11.5px; color:var(--amber); background:var(--amber-bg);
      border:1px solid var(--amber-border); border-radius:var(--radius-sm);
      padding:8px 12px; margin-bottom:14px; display:flex; gap:8px; align-items:flex-start;
    }
  </style>
</head>
<body>
<div class="layout">
  <?php partial('sidebar'); ?>
  <main class="main-content">
    <div class="topbar">
      <div>
        <h1 class="page-title">Usuarios</h1>
        <p class="page-sub"><?= count($usuarios) ?> usuarios registrados</p>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-secondary" onclick="abrirModalPass()" data-tip="Cambiar tu propia contraseña" data-tip-pos="bottom">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Mi contraseña
        </button>
        <button class="btn btn-primary" onclick="abrirModalCrear()" data-tip="Crear una cuenta nueva (requiere la clave maestra)" data-tip-pos="bottom">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuevo usuario
        </button>
      </div>
    </div>

    <div class="content-area">
      <?php $fe=flashError(); $fo=flashOk(); ?>
      <?php if($fe): ?><div class="alert alert-danger"><?= h($fe) ?></div><?php endif; ?>
      <?php if($fo): ?><div class="alert alert-success"><?= h($fo) ?></div><?php endif; ?>

      <div class="table-wrapper">
        <table class="data-table">
          <thead><tr>
            <th>#</th><th>Nombre</th><th>Usuario</th><th>Rol</th>
            <th>Último acceso</th><th>Estado</th><th>Acciones</th>
          </tr></thead>
          <tbody>
            <?php foreach($usuarios as $u): ?>
            <tr>
              <td class="mono text-hint"><?= $u['id'] ?></td>
              <td style="font-weight:500"><?= h($u['nombre']) ?></td>
              <td class="mono" style="font-size:13px;">@<?= h($u['username']) ?></td>
              <td>
                <span class="rol-badge-<?= $u['rol'] ?>">
                  <?= ROL_LABELS[$u['rol']] ?? $u['rol'] ?>
                </span>
              </td>
              <td class="text-sub" style="font-size:12px;">
                <?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : 'Nunca' ?>
              </td>
              <td>
                <span class="badge <?= $u['activo'] ? 'badge-green' : 'badge-red' ?>">
                  <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
              </td>
              <td>
                <div class="flex-gap">
                  <button class="btn btn-sm btn-secondary"
                    data-tip="Asignar una contraseña nueva a este usuario"
                    onclick="abrirModalReset(<?= $u['id'] ?>, '<?= h($u['nombre']) ?>')">
                    Reset pass
                  </button>
                  <?php if ($u['id'] != ($_SESSION['usuario_id'] ?? 0)): ?>
                  <button class="btn btn-sm <?= $u['activo'] ? 'btn-danger' : 'btn-success' ?>"
                    data-tip="Activar o desactivar el acceso de este usuario"
                    onclick="toggleUser(<?= $u['id'] ?>, this)">
                    <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                  </button>
                  <?php else: ?>
                  <span class="text-hint" style="font-size:11px;">Tú</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- INFO DE ROLES -->
      <div class="grid-2" style="margin-top:20px;">
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">
              <span class="rol-badge-admin">Administrador</span>
            </h2>
          </div>
          <p style="font-size:13px;color:var(--text-sub);line-height:1.7;">
            Acceso completo al sistema: dashboard, órdenes, kanban, clientes,
            inventario, caja, reportes, configuración y gestión de usuarios.
          </p>
        </div>
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">
              <span class="rol-badge-vendedor">Vendedor</span>
            </h2>
          </div>
          <p style="font-size:13px;color:var(--text-sub);line-height:1.7;">
            Acceso solo a las secciones de venta: dashboard (solo métricas),
            registro de órdenes, tablero kanban, clientes e impresión de tickets.
          </p>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- ── MODAL CREAR USUARIO ─────────────────────────────────── -->
<div id="modal-crear" class="modal-backdrop" style="display:none;">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Nuevo Usuario</h2>
      <button class="modal-close" onclick="cerrarModal('modal-crear')">×</button>
    </div>

    <div class="master-hint">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Se requiere la <strong>clave maestra</strong> para crear usuarios.
      Solo el administrador del sistema la conoce.
    </div>

    <div class="form-row form-row-2" style="margin-bottom:14px;">
      <div class="form-group">
        <label class="form-label">Nombre completo *</label>
        <input id="c-nombre" class="form-control" placeholder="Juan Pérez" maxlength="80" data-mask="name">
      </div>
      <div class="form-group">
        <label class="form-label">Nombre de usuario * <span class="help-dot" tabindex="0" data-tip="3 a 40 caracteres: letras minúsculas, números, punto, guion o guion bajo. Se usa para iniciar sesión.">?</span></label>
        <input id="c-username" class="form-control" placeholder="juan.perez" maxlength="40" autocomplete="off"
               oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9._-]/g,'')">
      </div>
    </div>
    <div class="form-row form-row-2" style="margin-bottom:14px;">
      <div class="form-group">
        <label class="form-label">Rol *</label>
        <select id="c-rol" class="form-control">
          <option value="vendedor">Vendedor</option>
          <option value="admin">Administrador</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Contraseña *</label>
        <div style="position:relative;">
          <input id="c-password" type="password" class="form-control"
                 placeholder="mín. 6 caracteres" oninput="medirFuerza(this.value,'c-strength')">
          <button type="button" onclick="verPass('c-password')"
            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-hint);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="pass-strength" id="c-strength"></div>
      </div>
    </div>
    <div class="form-group" style="margin-bottom:14px;">
      <label class="form-label">Clave Maestra * <span class="help-dot" tabindex="0" data-tip="Clave de seguridad del sistema que solo conoce el administrador principal.">?</span></label>
      <div style="position:relative;">
        <input id="c-master" type="password" class="form-control"
               placeholder="Clave maestra del sistema">
        <button type="button" onclick="verPass('c-master')"
          style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-hint);">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <div id="crear-error" class="alert alert-danger" style="display:none;"></div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="cerrarModal('modal-crear')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarUsuario()">Crear Usuario</button>
    </div>
  </div>
</div>

<!-- ── MODAL RESET CONTRASEÑA ──────────────────────────────── -->
<div id="modal-reset" class="modal-backdrop" style="display:none;">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h2 class="modal-title">Resetear Contraseña</h2>
      <button class="modal-close" onclick="cerrarModal('modal-reset')">×</button>
    </div>

    <p id="reset-nombre-label" style="font-size:13.5px;color:var(--text-sub);margin-bottom:16px;"></p>

    <div class="master-hint">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Se requiere la <strong>clave maestra</strong> para resetear contraseñas.
    </div>

    <input type="hidden" id="reset-id">
    <div class="form-group" style="margin-bottom:14px;">
      <label class="form-label">Nueva Contraseña *</label>
      <div style="position:relative;">
        <input id="r-nueva" type="password" class="form-control"
               placeholder="mín. 6 caracteres" oninput="medirFuerza(this.value,'r-strength')">
        <button type="button" onclick="verPass('r-nueva')"
          style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-hint);">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <div class="pass-strength" id="r-strength"></div>
    </div>
    <div class="form-group" style="margin-bottom:14px;">
      <label class="form-label">Clave Maestra * <span class="help-dot" tabindex="0" data-tip="Clave de seguridad del sistema que solo conoce el administrador principal.">?</span></label>
      <input id="r-master" type="password" class="form-control"
             placeholder="Clave maestra del sistema">
    </div>

    <div id="reset-error" class="alert alert-danger" style="display:none;"></div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="cerrarModal('modal-reset')">Cancelar</button>
      <button class="btn btn-danger" onclick="confirmarReset()">Resetear Contraseña</button>
    </div>
  </div>
</div>

<!-- ── MODAL CAMBIAR MI CONTRASEÑA ─────────────────────────── -->
<div id="modal-pass" class="modal-backdrop" style="display:none;">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h2 class="modal-title">Cambiar mi contraseña</h2>
      <button class="modal-close" onclick="cerrarModal('modal-pass')">×</button>
    </div>
    <div class="form-group" style="margin-bottom:14px;">
      <label class="form-label">Contraseña actual *</label>
      <input id="mp-actual" type="password" class="form-control" placeholder="••••••••">
    </div>
    <div class="form-group" style="margin-bottom:14px;">
      <label class="form-label">Nueva contraseña *</label>
      <input id="mp-nueva" type="password" class="form-control"
             placeholder="mín. 6 caracteres" oninput="medirFuerza(this.value,'mp-strength')">
      <div class="pass-strength" id="mp-strength"></div>
    </div>
    <div class="form-group" style="margin-bottom:14px;">
      <label class="form-label">Confirmar nueva contraseña *</label>
      <input id="mp-confirmar" type="password" class="form-control" placeholder="••••••••">
    </div>
    <div id="pass-error" class="alert alert-danger" style="display:none;"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="cerrarModal('modal-pass')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarMiPass()">Guardar Contraseña</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
/* ── MODALES ── */
function abrirModalCrear() {
  ['c-nombre','c-username','c-password','c-master'].forEach(id => document.getElementById(id).value='');
  document.getElementById('c-rol').value='vendedor';
  document.getElementById('c-strength').style.cssText='';
  document.getElementById('crear-error').style.display='none';
  document.getElementById('modal-crear').style.display='flex';
}
function abrirModalReset(id, nombre) {
  document.getElementById('reset-id').value=id;
  document.getElementById('reset-nombre-label').textContent='Vas a resetear la contraseña de: '+nombre;
  ['r-nueva','r-master'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('r-strength').style.cssText='';
  document.getElementById('reset-error').style.display='none';
  document.getElementById('modal-reset').style.display='flex';
}
function abrirModalPass() {
  ['mp-actual','mp-nueva','mp-confirmar'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('mp-strength').style.cssText='';
  document.getElementById('pass-error').style.display='none';
  document.getElementById('modal-pass').style.display='flex';
}
function cerrarModal(id) { document.getElementById(id).style.display='none'; }

/* ── CREAR USUARIO ── */
function guardarUsuario() {
  const errEl = document.getElementById('crear-error');
  errEl.style.display='none';
  const $ = id => document.getElementById(id);
  const usr = $('c-username').value.trim();
  if(!Valida.comprobar([
    [$('c-nombre'),   Valida.nombre($('c-nombre').value.trim())],
    [$('c-username'), /^[a-z0-9._-]{3,40}$/.test(usr) ? null : 'Usuario de 3 a 40 caracteres (a-z, 0-9, . _ -).'],
    [$('c-password'), validarPass($('c-password').value)],
    [$('c-master'),   $('c-master').value ? null : 'Ingrese la clave maestra.'],
  ], $('modal-crear'))) return;
  const fd = new FormData();
  fd.append('action','crear');
  fd.append('nombre',        document.getElementById('c-nombre').value.trim());
  fd.append('username',      document.getElementById('c-username').value.trim());
  fd.append('password',      document.getElementById('c-password').value);
  fd.append('rol',           document.getElementById('c-rol').value);
  fd.append('clave_maestra', document.getElementById('c-master').value);
  fetch('usuarios.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.ok){ toast('Usuario creado correctamente','success'); cerrarModal('modal-crear'); setTimeout(()=>location.reload(),800); }
    else { errEl.textContent=d.error||'Error desconocido'; errEl.style.display='flex'; }
  });
}

/* Mismas reglas que validarPassword() en config/auth.php */
function validarPass(p, campo='La contraseña'){
  if (p.length < 6)  return campo+' debe tener al menos 6 caracteres.';
  if (new TextEncoder().encode(p).length > 72) return campo+' no puede superar 72 caracteres.';
  if (!p.trim()) return campo+' no puede ser solo espacios.';
  return null;
}

/* ── RESET PASSWORD ── */
function confirmarReset() {
  const errEl = document.getElementById('reset-error');
  errEl.style.display='none';
  if(!Valida.comprobar([
    [document.getElementById('r-nueva'),  validarPass(document.getElementById('r-nueva').value, 'La nueva contraseña')],
    [document.getElementById('r-master'), document.getElementById('r-master').value ? null : 'Ingrese la clave maestra.'],
  ], document.getElementById('modal-reset'))) return;
  const fd = new FormData();
  fd.append('action','resetear');
  fd.append('id',           document.getElementById('reset-id').value);
  fd.append('nueva_pass',   document.getElementById('r-nueva').value);
  fd.append('clave_maestra',document.getElementById('r-master').value);
  fetch('usuarios.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.ok){ toast('Contraseña restablecida','success'); cerrarModal('modal-reset'); }
    else { errEl.textContent=d.error||'Error'; errEl.style.display='flex'; }
  });
}

/* ── CAMBIAR MI CONTRASEÑA ── */
function guardarMiPass() {
  const errEl = document.getElementById('pass-error');
  errEl.style.display='none';
  const nueva     = document.getElementById('mp-nueva').value;
  const confirmar = document.getElementById('mp-confirmar').value;
  const actual = document.getElementById('mp-actual').value;
  const errPass = !actual ? 'Escribe tu contraseña actual.'
                : validarPass(nueva, 'La nueva contraseña')
                || (nueva !== confirmar ? 'Las contraseñas no coinciden.' : null)
                || (nueva === actual ? 'La nueva contraseña debe ser distinta de la actual.' : null);
  if (errPass) {
    errEl.textContent=errPass;
    errEl.style.display='flex'; return;
  }
  const fd = new FormData();
  fd.append('action','cambiar_pass_propio');
  fd.append('pass_actual', document.getElementById('mp-actual').value);
  fd.append('nueva_pass',  nueva);
  fetch('usuarios.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.ok){ toast('Contraseña actualizada','success'); cerrarModal('modal-pass'); }
    else { errEl.textContent=d.error||'Error'; errEl.style.display='flex'; }
  });
}

/* ── TOGGLE USUARIO ── */
function toggleUser(id, btn) {
  const activo = btn.classList.contains('btn-danger');
  if(!confirm(activo?'¿Desactivar este usuario?':'¿Activar este usuario?')) return;
  const fd=new FormData(); fd.append('action','toggle'); fd.append('id',id);
  fetch('usuarios.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.ok) location.reload(); else toast(d.error||'No se pudo cambiar el estado','error');
  });
}

/* ── MOSTRAR / OCULTAR CONTRASEÑA ── */
function verPass(inputId) {
  const inp = document.getElementById(inputId);
  inp.type = inp.type==='password' ? 'text' : 'password';
}

/* ── MEDIDOR DE FORTALEZA ── */
function medirFuerza(pass, barId) {
  const bar = document.getElementById(barId);
  let score = 0;
  if (pass.length >= 6)  score++;
  if (pass.length >= 10) score++;
  if (/[A-Z]/.test(pass))  score++;
  if (/[0-9]/.test(pass))  score++;
  if (/[^A-Za-z0-9]/.test(pass)) score++;

  const colores = ['','#f87171','#fb923c','#fbbf24','#34d399','#22c55e'];
  const anchos  = ['0%','25%','45%','65%','85%','100%'];
  bar.style.background = colores[score] || '#f87171';
  bar.style.width      = anchos[score]  || '20%';
}

/* ── TOAST ── */
function toast(msg,type){
  const icons={success:'✓',error:'✕',warning:'⚠'};
  const el=document.createElement('div');
  el.className='toast '+type;
  el.innerHTML='<span>'+icons[type]+'</span> ';
  el.appendChild(document.createTextNode(msg));
  document.getElementById('toast-container').appendChild(el);
  setTimeout(()=>el.remove(),3500);
}
</script>
</body>
</html>

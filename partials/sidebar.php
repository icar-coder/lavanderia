<?php
// partials/sidebar.php
// Único sidebar de todo el sistema — incluido via partial('sidebar') en cada página
$paginaActual = basename($_SERVER['PHP_SELF'], '.php');
$tasa         = obtenerTasaHoy();

// Nombre e inicial para SSR (se sobreescriben por JS si el usuario los guardó)
$usuNombre = $_SESSION['usuario_nombre'] ?? 'Administrador';
$usuCargo  = $_SESSION['usuario_cargo']  ?? 'Administrador del Sistema';
$inicial   = mb_strtoupper(mb_substr($usuNombre, 0, 1));

// Matriz de navegación estructurada por secciones para un filtrado impecable
$nav = [
  'Operaciones' => [
    ['index.php',        'index',         'Dashboard',    '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
    ['ordenes.php',      'ordenes',       'Órdenes',      '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
    ['kanban.php',       'kanban',        'Tablero',      '<rect x="3" y="3" width="4" height="18"/><rect x="10" y="3" width="4" height="14"/><rect x="17" y="3" width="4" height="10"/>'],
  ],
  'Clientes' => [
    ['clientes.php',     'clientes',      'Clientes',     '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['usuarios.php',     'usuarios',      'Usuarios',     '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
  ],
  'Finanzas' => [
    ['caja.php',         'caja',          'Caja',         '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>'],
    ['inventario.php',   'inventario',    'Inventario',   '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 2 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>'],
    ['reportes.php',     'reportes',      'Reportes',     '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
  ],
  'Sistema' => [
    ['configuracion.php','configuracion', 'Configuración','<circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41"/>'],
    ['ayuda.php',        'ayuda',         'Ayuda',        '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
  ],
];
?>
<meta name="csrf-token" content="<?= csrfToken() ?>">
<aside class="sidebar" data-uid="<?= (int) ($_SESSION['usuario_id'] ?? 0) ?>">

  <div class="sidebar__brand">
    <div class="brand-icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <circle cx="12" cy="12" r="10"/>
        <path d="M8 12a4 4 0 0 1 8 0"/>
        <path d="M6 20l2-2M18 20l-2-2"/>
      </svg>
    </div>
    <div>
      <p class="brand-name">Lavandería</p>
      <p class="brand-sub">Superior</p>
    </div>
  </div>

  <nav class="sidebar__nav">
    <?php foreach ($nav as $seccion => $items): 
      // Filtramos los ítems de esta sección basándonos en la matriz de permisos de auth.php
      $itemsVisibles = array_filter($items, function($item) {
          return puedeAcceder($item[1]);
      });

      // Si la sección queda vacía para el rol actual, saltamos el título completo
      if (empty($itemsVisibles)) continue;
      ?>
      
      <span class="nav-section-label"><?= htmlspecialchars($seccion) ?></span>
      
      <?php foreach ($itemsVisibles as $item): 
        [$href, $page, $label, $icon] = $item;
        ?>
        <a href="<?= $href ?>" class="nav-item <?= $paginaActual === $page ? 'active' : '' ?>" data-tip="<?= h($label) ?>" data-tip-pos="right">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <?= $icon ?>
          </svg>
          <span><?= $label ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__tasa">
    <p class="tasa-label">Tasa BCV hoy</p>
    <p class="tasa-valor">Bs. <?= number_format($tasa['tasa_bcv'], 2, ',', '.') ?></p>
    <p class="tasa-fecha"><?= date('d/m/Y', strtotime($tasa['fecha'])) ?></p>
    <?php if (puedeAcceder('configuracion') && rolActual() === 'admin'): ?>
    <a href="configuracion.php?tab=tasa" class="tasa-edit" data-tip="Registrar la tasa oficial del BCV de hoy">Actualizar →</a>
    <?php endif; ?>
  </div>

  <div class="sidebar__perfil">
    <div id="sidebar-avatar-ph" class="perfil-avatar-ph"><?= $inicial ?></div>
    <img id="sidebar-avatar-img" class="perfil-avatar" src="" alt="Foto de perfil" style="display:none;">
    <div class="perfil-info">
      <p class="perfil-nombre" id="sidebar-perfil-nombre"><?= h($usuNombre) ?></p>
      <p class="perfil-cargo"  id="sidebar-perfil-cargo"><?= h($usuCargo) ?></p>
    </div>
    <a href="logout.php" data-tip="Cerrar sesión" data-tip-pos="top" class="logout-btn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
    </a>
  </div>

</aside>
<script src="<?= ROOT_URL ?>assets/js/validaciones.js"></script>
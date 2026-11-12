<?php
require_once __DIR__ . '/config/init.php';
requireAuth('ayuda');
require_once ROOT . 'config/contenido_ayuda.php';

$manual    = manualRapido();
$faq       = preguntasFrecuentes();
$politicas = politicas();
$tab       = preg_replace('/[^a-z]/', '', entrada($_GET, 'tab', 'manual'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ayuda — Lavandería Superior</title>
  <script src="assets/js/theme-engine.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    .ay-hero { display:grid; grid-template-columns:1fr auto; gap:18px; align-items:center; padding:22px 24px; border-radius:var(--radius-lg); background:linear-gradient(135deg,var(--accent-bg),transparent 70%); border:1px solid var(--accent-border); margin-bottom:18px; }
    .ay-hero h2 { font-family:var(--font-display); font-size:20px; font-weight:800; color:var(--text); }
    .ay-hero p  { font-size:13px; color:var(--text-sub); margin-top:4px; max-width:620px; }
    .ay-dl { display:flex; gap:10px; flex-wrap:wrap; }

    .ay-search { position:relative; max-width:420px; margin-bottom:16px; }
    .ay-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-hint); pointer-events:none; }
    .ay-search input { padding-left:36px; }

    .ay-grid { display:grid; grid-template-columns:230px 1fr; gap:18px; align-items:start; }
    .ay-toc { position:sticky; top:20px; }
    .ay-toc a { display:block; padding:7px 12px; font-size:13px; color:var(--text-sub); border-radius:var(--radius-sm); border-left:2px solid transparent; }
    .ay-toc a:hover { color:var(--text); background:var(--bg-surface2); }
    .ay-toc a.on { color:var(--accent-light); border-left-color:var(--accent); background:var(--accent-bg); }

    .ay-sec { scroll-margin-top:20px; margin-bottom:16px; }
    .ay-sec h3 { font-family:var(--font-display); font-size:16px; font-weight:700; color:var(--text); margin-bottom:14px; display:flex; align-items:center; gap:10px; }
    .ay-num { width:26px; height:26px; border-radius:50%; background:var(--accent-bg); border:1px solid var(--accent-border); color:var(--accent-light); font-size:12px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
    .ay-steps { list-style:none; counter-reset:paso; display:flex; flex-direction:column; gap:10px; }
    .ay-steps li { counter-increment:paso; position:relative; padding-left:34px; font-size:13.5px; color:var(--text-sub); line-height:1.6; }
    .ay-steps li::before { content:counter(paso); position:absolute; left:0; top:1px; width:22px; height:22px; border-radius:50%; background:var(--bg-surface3); color:var(--text); font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; }
    .ay-steps b { color:var(--text); }

    .faq { border:1px solid var(--border); border-radius:var(--radius-md); background:var(--bg-surface); margin-bottom:8px; overflow:hidden; }
    .faq summary { list-style:none; cursor:pointer; padding:14px 18px; font-size:14px; font-weight:500; color:var(--text); display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .faq summary::-webkit-details-marker { display:none; }
    .faq summary::after { content:'+'; font-size:18px; color:var(--text-hint); transition:transform .2s; }
    .faq[open] summary::after { transform:rotate(45deg); color:var(--accent-light); }
    .faq[open] { border-color:var(--accent-border); }
    .faq .faq-a { padding:0 18px 16px; font-size:13.5px; color:var(--text-sub); line-height:1.65; }
    .faq .faq-a b { color:var(--text); }

    .pol-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
    .pol-card h4 { font-family:var(--font-display); font-size:14.5px; font-weight:700; color:var(--text); margin-bottom:6px; }
    .pol-card .pol-res { font-size:13px; color:var(--text-sub); margin-bottom:10px; }
    .pol-card ul { padding-left:18px; display:flex; flex-direction:column; gap:6px; }
    .pol-card li { font-size:12.5px; color:var(--text-sub); line-height:1.55; }
    .pol-card li b { color:var(--text); }

    .about { text-align:center; padding:34px 24px; }
    .about .logo { width:64px; height:64px; margin:0 auto 14px; border-radius:18px; background:var(--accent-bg); border:1px solid var(--accent-border); color:var(--accent-light); display:flex; align-items:center; justify-content:center; }
    .about h2 { font-family:var(--font-display); font-size:22px; font-weight:800; color:var(--text); }
    .about p { font-size:13.5px; color:var(--text-sub); margin-top:6px; line-height:1.6; }
    .about .inst { margin-top:18px; padding:16px; border-radius:var(--radius-md); background:var(--bg-surface2); border:1px solid var(--border); display:inline-block; }
    .about .inst b { color:var(--text); display:block; font-size:14.5px; }

    .sin-res { display:none; text-align:center; padding:30px; color:var(--text-hint); font-size:13px; }
    @media(max-width:900px){ .ay-grid,.pol-grid,.ay-hero { grid-template-columns:1fr; } .ay-toc { position:static; } }
  </style>
</head>
<body>
<div class="layout">
  <?php partial('sidebar'); ?>
  <main class="main-content">
    <div class="topbar">
      <div>
        <h1 class="page-title">Centro de Ayuda</h1>
        <p class="page-sub">Manual de usuario, preguntas frecuentes y políticas</p>
      </div>
    </div>

    <div class="content-area">
      <div class="ay-hero">
        <div>
          <h2>¿En qué te podemos ayudar?</h2>
          <p>Aquí tienes una guía rápida de cada módulo. Si necesitas todos los detalles, descarga el manual completo en PDF.</p>
        </div>
        <div class="ay-dl">
          <a class="btn btn-primary" href="ayuda_pdf.php?doc=manual" data-tip="Manual detallado con todos los módulos, validaciones, solución de problemas y glosario" data-tip-pos="bottom">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Manual completo (PDF)
          </a>
          <a class="btn btn-secondary" href="ayuda_pdf.php?doc=politicas" data-tip="Políticas del servicio y del sistema, versión detallada" data-tip-pos="bottom">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Políticas (PDF)
          </a>
        </div>
      </div>

      <div class="tabs" role="tablist">
        <button class="tab-btn <?= $tab==='manual'?'active':'' ?>" data-tab="manual">Manual rápido</button>
        <button class="tab-btn <?= $tab==='faq'?'active':'' ?>" data-tab="faq">Preguntas frecuentes</button>
        <button class="tab-btn <?= $tab==='politicas'?'active':'' ?>" data-tab="politicas">Políticas</button>
        <button class="tab-btn <?= $tab==='acerca'?'active':'' ?>" data-tab="acerca">Acerca del sistema</button>
      </div>

      <!-- MANUAL -->
      <div class="tab-panel <?= $tab==='manual'?'active':'' ?>" id="tab-manual">
        <div class="ay-grid">
          <nav class="card ay-toc" style="padding:10px;">
            <?php foreach ($manual as $i => $sec): ?>
            <a href="#<?= $sec['id'] ?>" class="<?= $i===0?'on':'' ?>"><?= ($i+1) . '. ' . h($sec['titulo']) ?></a>
            <?php endforeach; ?>
          </nav>
          <div>
            <?php foreach ($manual as $i => $sec): ?>
            <section class="card ay-sec" id="<?= $sec['id'] ?>">
              <h3><span class="ay-num"><?= $i+1 ?></span><?= h($sec['titulo']) ?></h3>
              <ol class="ay-steps">
                <?php foreach ($sec['pasos'] as $p): ?><li><?= $p ?></li><?php endforeach; ?>
              </ol>
            </section>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- FAQ -->
      <div class="tab-panel <?= $tab==='faq'?'active':'' ?>" id="tab-faq">
        <div class="ay-search">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input id="faq-q" class="form-control" placeholder="Buscar pregunta… (ej. extranjero, respaldo, ticket)" autocomplete="off">
        </div>
        <div id="faq-list">
          <?php foreach ($faq as [$q, $a]): ?>
          <details class="faq">
            <summary><?= h($q) ?></summary>
            <div class="faq-a"><?= $a ?></div>
          </details>
          <?php endforeach; ?>
        </div>
        <div class="sin-res" id="faq-vacio">No encontramos preguntas con ese texto. Revisa el manual completo en PDF.</div>
      </div>

      <!-- POLÍTICAS -->
      <div class="tab-panel <?= $tab==='politicas'?'active':'' ?>" id="tab-politicas">
        <div class="alert alert-info" style="justify-content:space-between;align-items:center;">
          <span>Resumen de las políticas vigentes. La versión detallada y firmable está disponible en PDF.</span>
          <a class="btn btn-sm btn-primary" href="ayuda_pdf.php?doc=politicas">Descargar políticas</a>
        </div>
        <div class="pol-grid">
          <?php foreach ($politicas as $pol): ?>
          <div class="card pol-card" id="<?= $pol['id'] ?>">
            <h4><?= h($pol['titulo']) ?></h4>
            <p class="pol-res"><?= h($pol['resumen']) ?></p>
            <ul><?php foreach (array_slice($pol['detalle'], 0, 3) as $d): ?><li><?= $d ?></li><?php endforeach; ?></ul>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ACERCA -->
      <div class="tab-panel <?= $tab==='acerca'?'active':'' ?>" id="tab-acerca">
        <div class="card about">
          <div class="logo">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M8 12a4 4 0 0 1 8 0"/><path d="M6 20l2-2M18 20l-2-2"/></svg>
          </div>
          <h2>Lavandería Superior</h2>
          <p>Sistema de información para la automatización de servicios de lavandería<br>Versión <?= SISTEMA_VERSION ?></p>
          <div class="inst">
            <span class="text-hint" style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;">Proyecto universitario</span>
            <b><?= h(PROYECTO_INSTITUCION) ?></b>
            <span class="text-sub" style="font-size:12.5px;"><?= PROYECTO_SIGLAS ?> · <?= h(PROYECTO_UBICACION) ?></span>
          </div>
          <p style="margin-top:18px;font-size:12px;" class="text-hint">Desarrollado con PHP, MySQL/MariaDB, HTML, CSS y JavaScript.</p>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
// Pestañas (conservan la pestaña en la URL para compartir el enlace)
document.querySelectorAll('.tab-btn').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('.tab-btn').forEach(x => x.classList.toggle('active', x === b));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + b.dataset.tab));
  history.replaceState(null, '', '?tab=' + b.dataset.tab);
}));

// Resaltar sección visible en el índice del manual
const links = [...document.querySelectorAll('.ay-toc a')];
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) links.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + e.target.id));
  });
}, { rootMargin: '-20% 0px -70% 0px' });
document.querySelectorAll('.ay-sec').forEach(s => obs.observe(s));

// Si llega con #manual-xxx, abre la pestaña de manual
if (location.hash.startsWith('#manual-')) document.querySelector('[data-tab="manual"]').click();

// Buscador de preguntas frecuentes
const sinAcentos = t => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
document.getElementById('faq-q').addEventListener('input', function () {
  const q = sinAcentos(this.value.trim());
  let visibles = 0;
  document.querySelectorAll('#faq-list .faq').forEach(f => {
    const ok = !q || sinAcentos(f.textContent).includes(q);
    f.style.display = ok ? '' : 'none';
    if (ok) visibles++;
    if (q && ok) f.open = true;
  });
  document.getElementById('faq-vacio').style.display = visibles ? 'none' : 'block';
});
</script>
</body>
</html>

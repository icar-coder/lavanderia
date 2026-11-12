/* ============================================================
   LAVANDERÍA SUPERIOR — theme-engine.js  v2.1
   Carga en <head> de TODAS las páginas.
   Aplica tema + fuentes SIN flash (FOUC-free).
   ============================================================ */
(function () {
  'use strict';

  var STORAGE_KEY = 'ls_prefs';

  var DEFAULTS = {
    modo:           'dark',
    acento:         'blue',
    fuenteBody:     'dm-sans',
    fuenteDisplay:  'syne',
    tamano:         'md',
    radioUI:        'md',
    sidebarCompacto: false,
  };

  /* ── CATÁLOGOS ─────────────────────────────────────────── */

  var ACENTOS = {
    blue:    { accent:'#2563eb', hover:'#1d55d4', light:'#60a5fa', bg:'rgba(37,99,235,0.12)',   border:'rgba(37,99,235,0.35)'   },
    violet:  { accent:'#7c3aed', hover:'#6d28d9', light:'#a78bfa', bg:'rgba(124,58,237,0.12)',  border:'rgba(124,58,237,0.35)'  },
    rose:    { accent:'#e11d48', hover:'#be123c', light:'#fb7185', bg:'rgba(225,29,72,0.12)',   border:'rgba(225,29,72,0.35)'   },
    emerald: { accent:'#059669', hover:'#047857', light:'#34d399', bg:'rgba(5,150,105,0.12)',   border:'rgba(5,150,105,0.35)'   },
    amber:   { accent:'#d97706', hover:'#b45309', light:'#fbbf24', bg:'rgba(217,119,6,0.12)',   border:'rgba(217,119,6,0.35)'   },
    cyan:    { accent:'#0891b2', hover:'#0e7490', light:'#22d3ee', bg:'rgba(8,145,178,0.12)',   border:'rgba(8,145,178,0.35)'   },
    slate:   { accent:'#475569', hover:'#334155', light:'#94a3b8', bg:'rgba(71,85,105,0.12)',   border:'rgba(71,85,105,0.35)'   },
  };

  var FUENTES_BODY = {
    'dm-sans':  { name:'DM Sans',       css:"'DM Sans', sans-serif",        import:'DM+Sans:wght@300;400;500'          },
    'inter':    { name:'Inter',         css:"'Inter', sans-serif",           import:'Inter:wght@300;400;500'             },
    'nunito':   { name:'Nunito',        css:"'Nunito', sans-serif",          import:'Nunito:wght@300;400;500;600'        },
    'outfit':   { name:'Outfit',        css:"'Outfit', sans-serif",          import:'Outfit:wght@300;400;500'            },
    'poppins':  { name:'Poppins',       css:"'Poppins', sans-serif",         import:'Poppins:wght@300;400;500'           },
    'geist':    { name:'Geist Sans',    css:"'Geist', sans-serif",           import:'Geist:wght@300;400;500'             },
    'ibm-plex': { name:'IBM Plex Sans', css:"'IBM Plex Sans', sans-serif",   import:'IBM+Plex+Sans:wght@300;400;500'    },
    'rubik':    { name:'Rubik',         css:"'Rubik', sans-serif",           import:'Rubik:wght@300;400;500'             },
  };

  var FUENTES_DISPLAY = {
    'syne':         { name:'Syne',           css:"'Syne', sans-serif",            import:'Syne:wght@600;700;800'              },
    'space-grotesk':{ name:'Space Grotesk',  css:"'Space Grotesk', sans-serif",   import:'Space+Grotesk:wght@600;700'         },
    'cabinet':      { name:'Plus Jakarta',   css:"'Plus Jakarta Sans', sans-serif",import:'Plus+Jakarta+Sans:wght@600;700;800' },
    'bebas':        { name:'Bebas Neue',     css:"'Bebas Neue', sans-serif",       import:'Bebas+Neue'                         },
    'outfit-d':     { name:'Outfit Bold',    css:"'Outfit', sans-serif",           import:'Outfit:wght@600;700;800'            },
    'raleway':      { name:'Raleway',        css:"'Raleway', sans-serif",          import:'Raleway:wght@600;700;800'           },
    'lexend':       { name:'Lexend',         css:"'Lexend', sans-serif",           import:'Lexend:wght@600;700;800'            },
  };

  var TAMANOS = {
    sm: { base:'12.5px', title:'20px', stat:'22px' },
    md: { base:'14px',   title:'24px', stat:'26px' },
    lg: { base:'15.5px', title:'26px', stat:'28px' },
  };

  var RADIOS = {
    sm: { sm:'3px',  md:'6px',  lg:'8px',  xl:'12px' },
    md: { sm:'6px',  md:'10px', lg:'14px', xl:'20px' },
    lg: { sm:'10px', md:'14px', lg:'20px', xl:'28px' },
  };

  var MODOS = {
    dark: {
      bgBase:'#0b0f14', bgSurface:'#111820', bgSurface2:'#19222d', bgSurface3:'#202c39',
      border:'rgba(255,255,255,0.07)', borderMd:'rgba(255,255,255,0.12)', borderStr:'rgba(255,255,255,0.20)',
      text:'#e8edf2', textSub:'#8a95a3', textHint:'#48555f',
    },
    light: {
      bgBase:'#f4f6f9', bgSurface:'#ffffff', bgSurface2:'#f1f5f9', bgSurface3:'#e8edf4',
      border:'rgba(0,0,0,0.07)', borderMd:'rgba(0,0,0,0.12)', borderStr:'rgba(0,0,0,0.22)',
      text:'#0f172a', textSub:'#4b5563', textHint:'#9ca3af',
    },
  };

  /* ── HELPERS ───────────────────────────────────────────── */

  function cargarPrefs() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var saved = JSON.parse(raw);
        var merged = {};
        for (var k in DEFAULTS) merged[k] = DEFAULTS[k];
        for (var k in saved)    merged[k] = saved[k];
        return merged;
      }
    } catch (_) {}
    return JSON.parse(JSON.stringify(DEFAULTS));
  }

  function guardarPrefs(prefs) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs)); } catch (_) {}
  }

  /* ── INYECCIÓN DE FUENTES ──────────────────────────────────
     Construye la URL de Google Fonts con ambas fuentes y la
     inserta como <link>. Si el script corre en <head> antes de
     que el DOM esté listo, usa document.write para que el
     navegador la descargue de forma síncrona (bloquea render
     igual que cualquier otro <link> en el head → sin FOUC).
     En cambios en caliente (DOM ya listo) usa appendChild.   */

  function buildFontURL(prefs) {
    var body    = FUENTES_BODY[prefs.fuenteBody]      || FUENTES_BODY['dm-sans'];
    var display = FUENTES_DISPLAY[prefs.fuenteDisplay] || FUENTES_DISPLAY['syne'];
    var imports = [];
    imports.push('family=' + body.import);
    if (display.import !== body.import) {
      imports.push('family=' + display.import);
    }
    return 'https://fonts.googleapis.com/css2?' + imports.join('&') + '&display=swap';
  }

  function inyectarFuentes(prefs) {
    var url = buildFontURL(prefs);
    var existing = document.getElementById('ls-gfonts');

    if (existing) {
      // Cambio en caliente: actualizar href
      if (existing.href !== url) existing.href = url;
      return;
    }

    var link = document.createElement('link');
    link.id   = 'ls-gfonts';
    link.rel  = 'stylesheet';
    link.href = url;

    /* Si el <head> ya existe en el DOM, insertamos como primer link
       para que tenga prioridad máxima de descarga */
    var head = document.head || document.getElementsByTagName('head')[0];
    if (head) {
      /* Insertar ANTES de cualquier otro <link> para máxima prioridad */
      var firstLink = head.querySelector('link');
      if (firstLink) {
        head.insertBefore(link, firstLink);
      } else {
        head.appendChild(link);
      }
    }
  }

  /* ── APLICAR VARIABLES CSS ─────────────────────────────── */

  function aplicarVariables(prefs) {
    var root    = document.documentElement;
    var modo    = MODOS[prefs.modo]      || MODOS.dark;
    var acc     = ACENTOS[prefs.acento]  || ACENTOS.blue;
    var tam     = TAMANOS[prefs.tamano]  || TAMANOS.md;
    var rad     = RADIOS[prefs.radioUI]  || RADIOS.md;
    var body    = FUENTES_BODY[prefs.fuenteBody]      || FUENTES_BODY['dm-sans'];
    var display = FUENTES_DISPLAY[prefs.fuenteDisplay] || FUENTES_DISPLAY['syne'];

    root.setAttribute('data-modo', prefs.modo);

    var vars = {
      '--bg-base':        modo.bgBase,
      '--bg-surface':     modo.bgSurface,
      '--bg-surface2':    modo.bgSurface2,
      '--bg-surface3':    modo.bgSurface3,
      '--border':         modo.border,
      '--border-md':      modo.borderMd,
      '--border-strong':  modo.borderStr,
      '--text':           modo.text,
      '--text-sub':       modo.textSub,
      '--text-hint':      modo.textHint,
      '--accent':         acc.accent,
      '--accent-hover':   acc.hover,
      '--accent-light':   acc.light,
      '--accent-bg':      acc.bg,
      '--accent-border':  acc.border,
      '--font-size-base':  tam.base,
      '--font-size-title': tam.title,
      '--font-size-stat':  tam.stat,
      '--radius-sm':      rad.sm,
      '--radius-md':      rad.md,
      '--radius-lg':      rad.lg,
      '--radius-xl':      rad.xl,
      '--font-body':      body.css,
      '--font-display':   display.css,
      '--sidebar-w':      prefs.sidebarCompacto ? '60px' : '248px',
    };

    for (var prop in vars) {
      root.style.setProperty(prop, vars[prop]);
    }

    /* Sidebar compacto */
    if (prefs.sidebarCompacto) {
      document.body && document.body.setAttribute('data-sidebar', 'compact');
    } else {
      document.body && document.body.removeAttribute('data-sidebar');
    }
  }

  /* ── APLICAR TEMA COMPLETO ─────────────────────────────── */

  function aplicarTema(prefs) {
    aplicarVariables(prefs);
    inyectarFuentes(prefs);
  }

  /* ── PERFIL EN SIDEBAR ─────────────────────────────────── */

  /* Clave de localStorage propia de cada usuario: así dos usuarios que
     comparten la computadora no ven el nombre ni la foto del otro. */
  function claveUsuario(base) {
    var el = document.querySelector('[data-uid]');
    return base + '_' + (el ? el.getAttribute('data-uid') : '0');
  }

  function aplicarPerfil() {
    try {
      var foto = localStorage.getItem(claveUsuario('ls_foto_perfil'));
      var img  = document.getElementById('sidebar-avatar-img');
      var ph   = document.getElementById('sidebar-avatar-ph');

      if (foto && img) {
        img.src           = foto;
        img.style.display = 'block';
        if (ph) ph.style.display = 'none';
      }

      var perfil  = JSON.parse(localStorage.getItem(claveUsuario('ls_perfil')) || '{}');
      var elN = document.getElementById('sidebar-perfil-nombre');
      var elC = document.getElementById('sidebar-perfil-cargo');
      if (perfil.nombre && elN) elN.textContent = perfil.nombre;
      if (perfil.cargo  && elC) elC.textContent  = perfil.cargo;
      if (perfil.nombre && ph)  ph.textContent   = perfil.nombre.charAt(0).toUpperCase();
    } catch (_) {}
  }

  /* ── EJECUCIÓN INMEDIATA (en <head>, antes del DOM) ────────
     Aplicar variables es seguro porque sólo toca :root styles.
     Inyectar fuentes también es seguro: en este punto el <head>
     existe pero aún no se terminó de parsear; insertamos el
     <link> como primer elemento del head para máxima prioridad. */

  var prefsIniciales = cargarPrefs();
  aplicarVariables(prefsIniciales);  // variables CSS — instantáneo
  inyectarFuentes(prefsIniciales);   // font <link> — alta prioridad

  /* Cuando el DOM esté listo: aplicar perfil y tamaño al body */
  function onDOMReady() {
    var p = cargarPrefs();
    aplicarVariables(p);  // re-aplicar por si body ya existe
    document.body.style.fontSize = (TAMANOS[p.tamano] || TAMANOS.md).base;
    aplicarPerfil();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onDOMReady);
  } else {
    onDOMReady();
  }

  /* ── SINCRONIZACIÓN ENTRE PESTAÑAS ─────────────────────── */

  window.addEventListener('storage', function (e) {
    if (e.key === STORAGE_KEY)       { aplicarTema(cargarPrefs()); }
    if (e.key === claveUsuario('ls_foto_perfil')) { aplicarPerfil(); }
    if (e.key === claveUsuario('ls_perfil'))      { aplicarPerfil(); }
  });

  /* ── API PÚBLICA ───────────────────────────────────────── */

  window.ThemeEngine = {
    ACENTOS:        ACENTOS,
    FUENTES_BODY:   FUENTES_BODY,
    FUENTES_DISPLAY:FUENTES_DISPLAY,
    TAMANOS:        TAMANOS,
    RADIOS:         RADIOS,
    MODOS:          MODOS,
    DEFAULTS:       DEFAULTS,

    cargar:  cargarPrefs,
    guardar: guardarPrefs,
    aplicar: aplicarTema,

    set: function (clave, valor) {
      var prefs = cargarPrefs();
      prefs[clave] = valor;
      guardarPrefs(prefs);
      aplicarTema(prefs);
      // También actualizar font-size en body
      if (clave === 'tamano') {
        document.body.style.fontSize = (TAMANOS[valor] || TAMANOS.md).base;
      }
      return prefs;
    },

    reset: function () {
      var d = JSON.parse(JSON.stringify(DEFAULTS));
      guardarPrefs(d);
      aplicarTema(d);
      document.body.style.fontSize = TAMANOS.md.base;
    },

    getPrefs:       cargarPrefs,
    aplicarPerfil:  aplicarPerfil,
    clave:          claveUsuario,
  };

})();

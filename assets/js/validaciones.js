/* ============================================================
   validaciones.js — Máscaras de entrada, validación y tooltips
   Lavandería Superior
   Espejo en el servidor: config/validacion.php

   Uso en HTML:
     <input data-mask="digits">     solo números
     <input data-mask="phone">      números y "+" inicial
     <input data-mask="name">       letras, espacios y . ' - &
     <input data-mask="decimal">    números con decimales
     <input data-mask="integer">    enteros positivos
     <div class="doc-group"> <select class="doc-tipo"> + <input class="doc-num"> </div>
     <button data-tip="Texto de ayuda">   tooltip
   ============================================================ */
(function () {
  'use strict';

  const TIPOS_DOC = {
    V: { label: 'V', max: 8,  min: 6, alnum: false, hint: 'Venezolano: 6 a 8 dígitos' },
    E: { label: 'E', max: 10, min: 6, alnum: false, hint: 'Extranjero residente: 6 a 10 dígitos' },
    P: { label: 'P', max: 15, min: 5, alnum: true,  hint: 'Pasaporte: 5 a 15 letras o números' },
    J: { label: 'J', max: 9,  min: 9, alnum: false, hint: 'RIF jurídico: 9 dígitos' },
    G: { label: 'G', max: 9,  min: 9, alnum: false, hint: 'RIF gobierno: 9 dígitos' },
  };

  /* ── Filtros por tipo de máscara ─────────────────────────── */
  const FILTROS = {
    digits:  v => v.replace(/\D/g, ''),
    integer: v => v.replace(/\D/g, '').replace(/^0+(?=\d)/, ''),
    phone:   v => { const mas = v.trim().startsWith('+'); return (mas ? '+' : '') + v.replace(/\D/g, ''); },
    name:    v => v.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s.'\-&,]/g, '').replace(/\s{2,}/g, ' '),
    decimal: v => {
      v = v.replace(',', '.').replace(/[^\d.]/g, '');
      const i = v.indexOf('.');
      return i === -1 ? v : v.slice(0, i + 1) + v.slice(i + 1).replace(/\./g, '');
    },
  };

  function filtroDocumento(input) {
    const grupo = input.closest('.doc-group');
    const tipo  = grupo ? grupo.querySelector('.doc-tipo').value : 'V';
    const cfg   = TIPOS_DOC[tipo] || TIPOS_DOC.V;
    let v = input.value.toUpperCase();
    v = cfg.alnum ? v.replace(/[^A-Z0-9]/g, '') : v.replace(/\D/g, '');
    return v.slice(0, cfg.max);
  }

  function aplicarMascara(el) {
    let nuevo;
    if (el.classList.contains('doc-num')) nuevo = filtroDocumento(el);
    else if (FILTROS[el.dataset.mask])    nuevo = FILTROS[el.dataset.mask](el.value);
    else return;
    if (nuevo !== el.value) {
      const pos = el.selectionStart - (el.value.length - nuevo.length);
      el.value = nuevo;
      try { el.setSelectionRange(pos, pos); } catch (e) { /* type=number no soporta selección */ }
      el.classList.add('input-flash');
      setTimeout(() => el.classList.remove('input-flash'), 350);
    }
  }

  document.addEventListener('input', e => {
    const el = e.target;
    if (el.matches('[data-mask], .doc-num')) aplicarMascara(el);
    if (el.classList.contains('is-invalid')) limpiar(el);
  });

  // Al cambiar el tipo de documento se re-filtra el número y se actualiza la ayuda
  document.addEventListener('change', e => {
    if (!e.target.classList.contains('doc-tipo')) return;
    const grupo = e.target.closest('.doc-group');
    const num   = grupo.querySelector('.doc-num');
    const cfg   = TIPOS_DOC[e.target.value];
    num.maxLength   = cfg.max;
    num.inputMode   = cfg.alnum ? 'text' : 'numeric';
    num.placeholder = cfg.alnum ? 'AB123456' : (cfg.max === 9 ? '123456789' : '12345678');
    num.value = filtroDocumento(num);
    const hint = grupo.parentElement.querySelector('.doc-hint');
    if (hint) hint.textContent = cfg.hint;
    limpiar(num);
  });

  // Bloquea e, E, +, - en inputs type=number (el navegador los permite)
  document.addEventListener('keydown', e => {
    const el = e.target;
    if (el.tagName === 'INPUT' && el.type === 'number' && ['e', 'E', '+', '-'].includes(e.key)) e.preventDefault();
  });

  /* ── Validadores (devuelven mensaje de error o null) ─────── */
  const V = {
    TIPOS_DOC,

    documento(tipo, num) {
      const cfg = TIPOS_DOC[tipo];
      if (!cfg) return 'Seleccione el tipo de documento.';
      if (!num) return 'El número de documento es obligatorio.';
      if (cfg.alnum ? !/^[A-Z0-9]+$/.test(num) : !/^\d+$/.test(num))
        return cfg.alnum ? 'El pasaporte solo admite letras y números.' : 'El documento solo puede contener números.';
      if (num.length < cfg.min || num.length > cfg.max) return cfg.hint + '.';
      if (/^0+$/.test(num)) return 'El número de documento no es válido.';
      return null;
    },

    telefono(t) {
      if (!t) return 'El teléfono es obligatorio.';
      if (t.startsWith('+')) {
        const d = t.slice(1);
        return /^\d{8,15}$/.test(d) ? null : 'Teléfono internacional: "+" seguido de 8 a 15 dígitos.';
      }
      return /^0(2\d{2}|4(12|14|16|22|24|26))\d{7}$/.test(t)
        ? null : 'Use 11 dígitos, ej: 04141234567. Extranjeros: inicie con +.';
    },

    nombre(n, min = 3, campo = 'El nombre') {
      if (!n) return `${campo} es obligatorio.`;
      if (n.length < min) return `${campo} debe tener al menos ${min} caracteres.`;
      if (!/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ][A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s.'\-&,]*$/.test(n)) return `${campo} solo puede contener letras.`;
      return null;
    },

    /** RIF J (jurídico) y G (gobierno): empresas, sin apellidos. */
    esEmpresa(tipo) {
      return tipo === 'J' || tipo === 'G';
    },

    /**
     * Ajusta un par nombres/apellidos al tipo de documento elegido:
     * con RIF J/G el primer campo pasa a «Razón social» y el apellido se oculta.
     */
    ajustarNombrePorDoc(grupoDoc, inNombres, inApellidos) {
      const empresa = V.esEmpresa(grupoDoc.querySelector('.doc-tipo').value);
      const lbl = document.querySelector(`label[for="${inNombres.id}"] .lbl-txt`);
      if (lbl) lbl.textContent = empresa ? 'Razón social' : 'Nombres';
      inNombres.placeholder = empresa ? 'Inversiones Ejemplo C.A.' : (inNombres.dataset.ph || '');
      inNombres.maxLength   = empresa ? 120 : 60;
      const g = inApellidos.closest('.form-group');
      if (g) g.style.display = empresa ? 'none' : '';
      if (empresa) { inApellidos.value = ''; limpiar(inApellidos); }
      return empresa;
    },

    email(e) {
      if (!e) return null;
      return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(e) ? null : 'El correo electrónico no es válido.';
    },

    numero(v, min, max, etiqueta = 'El valor') {
      const n = parseFloat(String(v).replace(',', '.'));
      if (isNaN(n)) return `${etiqueta} es obligatorio.`;
      if (n < min) return `${etiqueta} debe ser mayor o igual a ${min}.`;
      if (max !== undefined && n > max) return `${etiqueta} no puede superar ${max}.`;
      return null;
    },

    /** "V-12345678" / "J-12345678-9" / "12345678" (antiguo) → {tipo, num} */
    separarDoc(doc) {
      doc = String(doc || '').toUpperCase().trim();
      const m = doc.match(/^([VEPJG])[\s\-.]*(.+)$/);
      if (m) return { tipo: m[1], num: m[2].replace(/[^0-9A-Z]/g, '') };
      return { tipo: 'V', num: doc.replace(/[^0-9A-Z]/g, '') };
    },

    /** Carga un documento guardado en un .doc-group */
    cargarDoc(grupo, doc) {
      const { tipo, num } = V.separarDoc(doc);
      const sel = grupo.querySelector('.doc-tipo');
      sel.value = TIPOS_DOC[tipo] ? tipo : 'V';
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      grupo.querySelector('.doc-num').value = num;
    },

    leerDoc(grupo) {
      return { tipo: grupo.querySelector('.doc-tipo').value, num: grupo.querySelector('.doc-num').value.trim().toUpperCase() };
    },

    /* ── Marcado visual de errores ── */
    marcar(el, msg) {
      if (!el) return;
      const target = el.closest('.doc-group') || el;
      target.classList.add('is-invalid');
      el.classList.add('is-invalid');
      const grupo = target.closest('.form-group') || target.parentElement;
      let fb = grupo.querySelector('.invalid-msg');
      if (!fb) { fb = document.createElement('span'); fb.className = 'invalid-msg'; grupo.appendChild(fb); }
      fb.textContent = msg;
      el.focus();
    },

    limpiarTodo(scope) {
      (scope || document).querySelectorAll('.is-invalid').forEach(limpiar);
    },

    /**
     * Ejecuta una lista de reglas [[elemento, mensajeOnull], ...].
     * Marca el primer error y devuelve false; true si todo es válido.
     */
    comprobar(reglas, scope) {
      V.limpiarTodo(scope);
      for (const [el, err] of reglas) {
        if (err) { V.marcar(el, err); return false; }
      }
      return true;
    },

    /** Escapa texto para insertarlo con innerHTML. */
    esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },
  };

  function limpiar(el) {
    el.classList.remove('is-invalid');
    const grupoDoc = el.closest('.doc-group');
    if (grupoDoc) grupoDoc.classList.remove('is-invalid');
    const g = (grupoDoc || el).closest('.form-group') || el.parentElement;
    const fb = g && g.querySelector('.invalid-msg');
    if (fb) fb.remove();
  }

  /* ── Tooltips: data-tip también sirve de etiqueta accesible ── */
  function prepararTooltips(root) {
    (root || document).querySelectorAll('[data-tip]').forEach(el => {
      if (!el.hasAttribute('aria-label') && !el.textContent.trim()) el.setAttribute('aria-label', el.dataset.tip);
      if (el.hasAttribute('title')) el.removeAttribute('title');
    });
  }
  document.addEventListener('DOMContentLoaded', () => prepararTooltips());

  // Un único globo flotante que se posiciona junto al elemento; no altera el layout
  let globo = null, actual = null;
  function mostrarTip(el) {
    const texto = el.dataset.tip;
    if (!texto) return;
    // En la barra lateral solo hace falta cuando está compacta (sin texto visible)
    const lbl = el.classList.contains('nav-item') && el.querySelector('span');
    if (lbl && lbl.offsetParent !== null) return;
    if (!globo) {
      globo = document.createElement('div');
      globo.className = 'tip-globo';
      globo.setAttribute('role', 'tooltip');
      document.body.appendChild(globo);
    }
    actual = el;
    globo.textContent = texto;
    globo.classList.add('visible');
    const r = el.getBoundingClientRect(), g = globo.getBoundingClientRect(), m = 8;
    let pos = el.dataset.tipPos || 'top';
    if (pos === 'top' && r.top - g.height - m < 0) pos = 'bottom';
    let top, left;
    if (pos === 'right')      { top = r.top + r.height / 2 - g.height / 2; left = r.right + m; }
    else if (pos === 'left')  { top = r.top + r.height / 2 - g.height / 2; left = r.left - g.width - m; }
    else if (pos === 'bottom'){ top = r.bottom + m; left = r.left + r.width / 2 - g.width / 2; }
    else                      { top = r.top - g.height - m; left = r.left + r.width / 2 - g.width / 2; }
    left = Math.max(8, Math.min(left, window.innerWidth - g.width - 8));
    top  = Math.max(8, Math.min(top, window.innerHeight - g.height - 8));
    globo.style.top = top + 'px';
    globo.style.left = left + 'px';
  }
  function ocultarTip() { if (globo) globo.classList.remove('visible'); actual = null; }

  document.addEventListener('mouseover', e => {
    const el = e.target.closest && e.target.closest('[data-tip]');
    if (el && el !== actual) mostrarTip(el);
    else if (!el && actual) ocultarTip();
  });
  document.addEventListener('focusin', e => {
    const el = e.target.closest && e.target.closest('[data-tip]');
    if (el) mostrarTip(el);
  });
  document.addEventListener('focusout', ocultarTip);
  document.addEventListener('scroll', ocultarTip, true);
  document.addEventListener('click', ocultarTip);

  /* ── Peticiones al servidor ──────────────────────────────────
     Todas las llamadas fetch() al propio sistema llevan el token CSRF
     y se identifican como AJAX, para que el servidor responda en JSON
     (también cuando la sesión expiró). */
  function avisoGlobal(msg) {
    let caja = document.getElementById('aviso-global');
    if (!caja) {
      caja = document.createElement('div');
      caja.id = 'aviso-global';
      caja.setAttribute('role', 'alert');
      caja.style.cssText = 'position:fixed;left:50%;top:18px;transform:translateX(-50%);z-index:10000;max-width:min(92vw,460px);' +
        'padding:12px 18px;border-radius:10px;font-size:13.5px;line-height:1.4;box-shadow:0 8px 28px rgba(0,0,0,.35);' +
        'background:var(--bg-surface2,#1f2937);color:var(--text,#fff);border:1px solid var(--red-border,#f87171);';
      document.body.appendChild(caja);
    }
    caja.textContent = msg;
    caja.style.display = 'block';
    clearTimeout(caja._t);
    caja._t = setTimeout(() => { caja.style.display = 'none'; }, 5000);
  }

  const fetchOriginal = window.fetch.bind(window);
  window.fetch = function (recurso, opciones) {
    const url = typeof recurso === 'string' ? recurso : recurso.url;
    const propio = new URL(url, location.href).origin === location.origin;
    if (!propio) return fetchOriginal(recurso, opciones);

    const op = Object.assign({ credentials: 'same-origin' }, opciones || {});
    const cab = new Headers(op.headers || {});
    cab.set('X-Requested-With', 'fetch');
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) cab.set('X-CSRF-Token', meta.content);
    op.headers = cab;

    return fetchOriginal(recurso, op).then(r => {
      if (r.status === 401) {
        avisoGlobal('Su sesión expiró. Lo llevaremos al inicio de sesión…');
        setTimeout(() => { location.href = 'login.php?next=' + encodeURIComponent(location.pathname.split('/').pop() || 'index.php'); }, 1800);
      }
      return r;
    });
  };

  // Cualquier fallo de red o respuesta inválida que la página no haya
  // atendido se informa en lugar de quedar en silencio.
  window.addEventListener('unhandledrejection', () => {
    avisoGlobal('No se pudo completar la operación. Verifique la conexión e intente de nuevo.');
  });

  V.aviso = avisoGlobal;
  window.Valida = V;
  window.prepararTooltips = prepararTooltips;
})();

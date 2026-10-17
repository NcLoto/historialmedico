/* Hospital Sanitas — mejoras de interfaz. El sistema funciona sin JavaScript; esto solo agrega comodidad. */
(function () {
  'use strict';

  var body = document.body;

  /* Menú lateral en pantallas pequeñas */
  var toggle = document.getElementById('menu-toggle');
  var scrim = document.getElementById('scrim');
  function setMenu(open) {
    body.classList.toggle('menu-open', open);
    if (toggle) { toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    if (scrim) { scrim.hidden = !open; }
  }
  if (toggle) { toggle.addEventListener('click', function () { setMenu(!body.classList.contains('menu-open')); }); }
  if (scrim) { scrim.addEventListener('click', function () { setMenu(false); }); }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setMenu(false); } });

  /* Confirmación antes de acciones destructivas: <form data-confirm="Texto" data-confirm-label="Eliminar"> */
  var dialog = null;
  function ensureDialog() {
    if (dialog) { return dialog; }
    dialog = document.createElement('dialog');
    dialog.setAttribute('aria-labelledby', 'confirm-title');
    dialog.innerHTML =
      '<form method="dialog"><div class="dialog-body"><h2 id="confirm-title"></h2><p class="muted" id="confirm-text"></p></div>' +
      '<div class="dialog-actions"><button class="btn btn-secondary" value="cancel">Cancelar</button>' +
      '<button class="btn btn-danger-solid" value="ok" id="confirm-ok"></button></div></form>';
    document.body.appendChild(dialog);
    return dialog;
  }
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) { return; }
    var msg = form.getAttribute('data-confirm');
    if (msg && !form.dataset.confirmed && typeof HTMLDialogElement !== 'undefined') {
      e.preventDefault();
      var d = ensureDialog();
      d.querySelector('#confirm-title').textContent = form.getAttribute('data-confirm-title') || 'Confirmar acción';
      d.querySelector('#confirm-text').textContent = msg;
      d.querySelector('#confirm-ok').textContent = form.getAttribute('data-confirm-label') || 'Confirmar';
      d.addEventListener('close', function onClose() {
        d.removeEventListener('close', onClose);
        if (d.returnValue === 'ok') { form.dataset.confirmed = '1'; form.requestSubmit(); }
      });
      d.returnValue = 'cancel';
      d.showModal();
      return;
    }
    /* Evita envíos dobles */
    var btn = form.querySelector('button[type=submit]:not([formnovalidate])');
    if (btn && !form.hasAttribute('data-live-form')) {
      window.setTimeout(function () { btn.disabled = true; btn.classList.add('is-loading'); }, 0);
    }
  });
  window.addEventListener('pageshow', function (ev) {
    if (ev.persisted) {
      document.querySelectorAll('button.is-loading').forEach(function (b) { b.disabled = false; b.classList.remove('is-loading'); });
    }
  });

  /* Búsqueda en vivo: <input data-live-search="#destino" data-live-url="/pacientes/buscar"> */
  var live = document.querySelector('[data-live-search]');
  if (live && window.fetch) {
    var target = document.querySelector(live.getAttribute('data-live-search'));
    var endpoint = live.getAttribute('data-live-url');
    var timer = null;
    var seq = 0;
    var run = function () {
      var id = ++seq;
      var q = live.value.trim();
      var params = new URLSearchParams(new FormData(live.form));
      target.setAttribute('aria-busy', 'true');
      fetch(endpoint + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) {
          if (r.status === 401 || r.redirected) { window.location.reload(); throw new Error('sesion'); }
          if (!r.ok) { throw new Error('http'); }
          return r.text();
        })
        .then(function (html) {
          if (id !== seq) { return; }
          target.innerHTML = html;
          target.classList.remove('fade-in');
          void target.offsetWidth;
          target.classList.add('fade-in');
          var u = new URL(window.location.href);
          q ? u.searchParams.set('q', q) : u.searchParams.delete('q');
          window.history.replaceState(null, '', u);
        })
        .catch(function (err) {
          if (id !== seq || err.message === 'sesion') { return; }
          target.innerHTML = '<div class="empty"><strong>No se pudo completar la búsqueda</strong><p>Revise su conexión e intente de nuevo.</p></div>';
        })
        .finally(function () { if (id === seq) { target.removeAttribute('aria-busy'); } });
    };
    live.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(run, 250); });
    live.form.addEventListener('change', function (e) { if (e.target !== live) { run(); } });
    live.form.setAttribute('data-live-form', '');
    live.form.addEventListener('submit', function (e) { e.preventDefault(); window.clearTimeout(timer); run(); });
  }

  /* Filas repetibles (diagnósticos, tratamientos): <button data-add-row="#tpl" data-target="#lista"> */
  document.addEventListener('click', function (e) {
    var add = e.target.closest('[data-add-row]');
    if (add) {
      var tpl = document.querySelector(add.getAttribute('data-add-row'));
      var list = document.querySelector(add.getAttribute('data-target'));
      if (tpl && list) { list.appendChild(tpl.content.cloneNode(true)); var f = list.lastElementChild.querySelector('input,select'); if (f) { f.focus(); } }
      return;
    }
    var rm = e.target.closest('[data-remove-row]');
    if (rm) { var row = rm.closest('.repeat-row'); if (row) { row.remove(); } }
  });

  /* Tamaño máximo de archivo antes de enviar */
  document.addEventListener('change', function (e) {
    var input = e.target;
    if (input.matches && input.matches('input[type=file][data-max-mb]') && input.files[0]) {
      var max = parseFloat(input.getAttribute('data-max-mb')) * 1048576;
      var hint = input.parentElement.querySelector('.field-error');
      if (hint) { hint.remove(); }
      if (input.files[0].size > max) {
        var p = document.createElement('p');
        p.className = 'field-error';
        p.setAttribute('role', 'alert');
        p.textContent = 'El archivo supera el máximo de ' + input.getAttribute('data-max-mb') + ' MB.';
        input.parentElement.appendChild(p);
        input.value = '';
      }
    }
  });

  /* Navegación de la ficha: resalta la sección visible */
  var links = document.querySelectorAll('.section-nav a[href^="#"]');
  if (links.length && 'IntersectionObserver' in window) {
    var map = {};
    links.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && map[en.target.id]) {
          links.forEach(function (a) { a.classList.remove('is-active'); });
          map[en.target.id].classList.add('is-active');
        }
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    Object.keys(map).forEach(function (id) { var s = document.getElementById(id); if (s) { io.observe(s); } });
  }
})();

/* Tras un error de validación, el foco va al primer campo inválido y se anuncia en la página */
(function () {
  var bad = document.querySelector('.field.has-error input, .field.has-error select, .field.has-error textarea');
  if (bad && !document.querySelector('[autofocus]')) { bad.focus({ preventScroll: false }); }
})();

/* Un formulario que modifica datos solo se envía con su botón de guardar.
   Enter dentro de un campo no lo envía (los formularios de acceso lo permiten con data-enter-submit). */
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Enter' || e.isComposing) { return; }
  var t = e.target;
  if (!(t instanceof HTMLElement) || !t.form || t.form.method.toLowerCase() !== 'post' || t.form.hasAttribute('data-enter-submit')) { return; }
  var tag = t.tagName;
  var type = (t.getAttribute('type') || '').toLowerCase();
  if (tag === 'TEXTAREA' || tag === 'BUTTON' || type === 'submit' || type === 'button') { return; }
  e.preventDefault();
});

/* Aviso al salir de un formulario con cambios sin guardar */
(function () {
  var dirty = false;
  var forms = document.querySelectorAll('form[method="post"] .form-section');
  if (!forms.length) { return; }
  var form = forms[0].closest('form');
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('change', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });
})();
/* Magnificación por proximidad: cada elemento accionable crece y se tiñe según lo cerca que está el cursor.
   JavaScript solo mide la distancia y publica --k (0 a 1); escala y color los define el CSS.
   Las lecturas de layout se agrupan antes de las escrituras (un solo recálculo por cuadro).
   Se desactiva con pantallas táctiles, en elementos deshabilitados o cargando. */
(function () {
  if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) { return; }
  var SEL = '.btn, .icon-btn, .nav-item, .quick a, .section-nav a, .pagination a';
  var frame = null, px = 0, py = 0, active = [];

  function radiusOf(el) { return el.classList.contains('nav-item') ? 70 : 56; }

  function paint() {
    frame = null;
    var reads = [], i, el, r, k;
    var list = document.querySelectorAll(SEL);
    for (i = 0; i < list.length; i++) {              // 1) solo lecturas
      el = list[i];
      if (el.disabled || el.classList.contains('is-loading') || el.getAttribute('aria-disabled') === 'true') { continue; }
      r = el.getBoundingClientRect();
      if (!r.width || r.bottom < -80 || r.top > window.innerHeight + 80) { continue; }
      k = Math.max(0, 1 - Math.hypot(Math.max(r.left - px, 0, px - r.right), Math.max(r.top - py, 0, py - r.bottom)) / radiusOf(el));
      if (k > 0) { reads.push([el, k * k]); }
    }
    var next = reads.map(function (p) { return p[0]; });
    for (i = 0; i < active.length; i++) {            // 2) solo escrituras
      if (next.indexOf(active[i]) === -1) { active[i].style.removeProperty('--k'); }
    }
    for (i = 0; i < reads.length; i++) { reads[i][0].style.setProperty('--k', reads[i][1].toFixed(3)); }
    active = next;
  }
  function clear() {
    if (frame) { window.cancelAnimationFrame(frame); frame = null; }
    active.forEach(function (el) { el.style.removeProperty('--k'); });
    active = [];
  }
  document.addEventListener('pointermove', function (e) {
    px = e.clientX; py = e.clientY;
    if (!frame) { frame = window.requestAnimationFrame(paint); }
  }, { passive: true });
  document.documentElement.addEventListener('pointerleave', clear);
  window.addEventListener('blur', clear);
})();

/* Errores de validación: se asocian al campo para que el lector de pantalla los anuncie */
(function () {
  document.querySelectorAll('.field.has-error').forEach(function (f, n) {
    var msg = f.querySelector('.field-error');
    var input = f.querySelector('input, select, textarea');
    if (!msg || !input) { return; }
    if (!msg.id) { msg.id = 'err-' + (input.id || input.name || n).replace(/[^\w-]/g, '_'); }
    input.setAttribute('aria-invalid', 'true');
    input.setAttribute('aria-describedby', msg.id);
  });
})();

/* Selector de color de acento: sincroniza el selector nativo, el campo hexadecimal y los atajos, con vista previa */
(function () {
  var box = document.querySelector('[data-accent-picker]');
  if (!box) { return; }
  var picker = box.querySelector('input[type=color]');
  var hex = box.querySelector('#color_hex');
  var preview = box.querySelector('[data-accent-preview]');
  function apply(value) {
    picker.value = value;
    hex.value = value;
    hex.setCustomValidity('');
    preview.style.setProperty('--brand', value);
    preview.style.setProperty('--brand-hover', value);
    preview.style.setProperty('--brand-tint', value + '26');
    var c = [1, 3, 5].map(function (i) {
      var v = parseInt(value.substr(i, 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    var lum = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    preview.style.setProperty('--brand-ink', lum > 0.35 ? '#04181f' : '#ffffff');
  }
  picker.addEventListener('input', function () { apply(picker.value); });
  hex.addEventListener('input', function () {
    var v = hex.value.trim();
    if (v.charAt(0) !== '#') { v = '#' + v; }
    if (/^#[0-9a-fA-F]{6}$/.test(v)) { apply(v.toLowerCase()); } else { hex.setCustomValidity('Use el formato #RRGGBB'); }
  });
  box.querySelectorAll('[data-color]').forEach(function (b) { b.addEventListener('click', function () { apply(b.getAttribute('data-color')); }); });
  apply(picker.value);
})();

/* Botón «Volver» de las páginas de error (la política de seguridad no permite enlaces javascript:) */
document.addEventListener('click', function (e) {
  if (e.target.closest && e.target.closest('[data-back]')) { window.history.length > 1 ? window.history.back() : window.location.assign('./'); }
});

/* ================================================================ v3: búsqueda global, campana, menús anclados */

/* Búsqueda rápida de pacientes (combobox accesible). «/» enfoca el buscador. */
(function () {
  var form = document.querySelector('[data-quick-search]');
  if (!form || !window.fetch) { return; }
  var input = form.querySelector('input[type=search]');
  var list = form.querySelector('.qs-list');
  var endpoint = form.getAttribute('data-quick-search');
  var timer = null, seq = 0, items = [], active = -1;

  function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
  function select(i) {
    var opts = list.querySelectorAll('[role=option]');
    opts.forEach(function (o, n) { o.setAttribute('aria-selected', n === i ? 'true' : 'false'); });
    active = i;
    if (opts[i]) { input.setAttribute('aria-activedescendant', opts[i].id); opts[i].scrollIntoView({ block: 'nearest' }); }
  }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text != null) { e.textContent = text; } return e; }
  function render(q) {
    list.textContent = '';
    if (!items.length) {
      list.appendChild(el('li', 'qs-empty', 'Sin coincidencias para «' + q + '».'));
    }
    items.forEach(function (p, i) {
      var li = el('li'); li.setAttribute('role', 'option'); li.id = 'qs-opt-' + i; li.setAttribute('aria-selected', 'false');
      var a = el('a'); a.href = p.url; a.tabIndex = -1;
      var main = el('span', 'qs-main');
      main.appendChild(el('strong', null, p.nombre));
      main.appendChild(el('small', null, p.codigo + ', DPI ' + p.documento + ', ' + p.edad));
      a.appendChild(main);
      if (p.alerta) { a.appendChild(el('span', 'qs-alert', 'Alergia grave')); }
      li.appendChild(a); list.appendChild(li);
    });
    var all = el('li'); var link = el('a', 'qs-all', 'Ver todos los resultados'); link.href = form.action + '?q=' + encodeURIComponent(q); all.appendChild(link); list.appendChild(all);
    list.hidden = false; input.setAttribute('aria-expanded', 'true'); active = -1;
  }
  input.addEventListener('input', function () {
    var q = input.value.trim();
    window.clearTimeout(timer);
    if (q.length < 2) { close(); return; }
    timer = window.setTimeout(function () {
      var id = ++seq;
      fetch(endpoint + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (r.redirected || !r.ok) { throw new Error('http'); } return r.json(); })
        .then(function (data) { if (id === seq) { items = data; render(q); } })
        .catch(function () { if (id === seq) { close(); } });
    }, 180);
  });
  input.addEventListener('keydown', function (e) {
    if (list.hidden) { return; }
    var n = items.length;
    if (e.key === 'ArrowDown') { e.preventDefault(); select(n ? (active + 1) % n : -1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); select(n ? (active - 1 + n) % n : -1); }
    else if (e.key === 'Enter' && active >= 0 && items[active]) { e.preventDefault(); window.location.assign(items[active].url); }
    else if (e.key === 'Escape') { close(); }
  });
  document.addEventListener('click', function (e) { if (!form.contains(e.target)) { close(); } });
  document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) { return; }
    var t = e.target;
    if (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) { return; }
    e.preventDefault(); input.focus(); input.select();
  });
})();

/* Campana: actualiza el contador cada 60 s mientras la pestaña está visible */
(function () {
  var bell = document.querySelector('[data-bell]');
  if (!bell || !window.fetch) { return; }
  var badge = bell.querySelector('[data-bell-badge]');
  var url = bell.getAttribute('data-count-url');
  var base = document.title.replace(/^\(\d+\)\s*/, '');
  function refresh() {
    if (document.hidden) { return; }
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (r.redirected || !r.ok) { throw new Error('http'); } return r.json(); })
      .then(function (d) {
        var n = d.no_leidas | 0;
        badge.hidden = n === 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        bell.setAttribute('aria-label', 'Notificaciones' + (n ? ', ' + n + ' sin leer' : ''));
        document.title = (n ? '(' + n + ') ' : '') + base;
      })
      .catch(function () {});
  }
  window.setInterval(refresh, 60000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(); } });
})();

/* Menús desplegables (details): se cierran al pulsar fuera, con Escape o al abrir otro */
(function () {
  function cerrar(excepto) { document.querySelectorAll('details.dropdown[open]').forEach(function (d) { if (d !== excepto) { d.open = false; } }); }
  document.addEventListener('click', function (e) { var d = e.target.closest && e.target.closest('details.dropdown'); cerrar(d); });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var abierto = document.querySelector('details.dropdown[open]');
    if (abierto) { abierto.open = false; abierto.querySelector('summary').focus(); }
  });
  /* Sin soporte de popover: los botones de campana y usuario llevan a sus páginas */
  if (!HTMLElement.prototype.hasOwnProperty('popover')) {
    document.querySelectorAll('[popovertarget]').forEach(function (b) {
      var target = document.getElementById(b.getAttribute('popovertarget'));
      var first = target && target.querySelector('a[href]');
      b.addEventListener('click', function () { if (first) { window.location.assign(first.href); } });
    });
  }
})();

/* Selects que filtran al cambiar */
document.addEventListener('change', function (e) {
  if (e.target.matches && e.target.matches('select[data-autosubmit]') && e.target.form) { e.target.form.submit(); }
});

/* Carga de logo y favicon: vista previa inmediata, validación antes de enviar y estado del botón */
document.querySelectorAll('form[data-upload]').forEach(function (form) {
  var input = form.querySelector('input[type=file]');
  var nombre = form.querySelector('[data-file-name]');
  var error = form.querySelector('[data-upload-error]');
  var boton = form.querySelector('[data-upload-btn]');
  var caja = (form.closest('[data-upload-scope]') || form.closest('.brand-preview')).querySelector('[data-preview-box]');
  var original = caja.innerHTML;
  var exts = form.getAttribute('data-ext').split(',');
  var maxMb = parseFloat(form.getAttribute('data-max-mb'));
  var url = null;
  function kb(n) { return n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
  function mostrarError(msg) { error.textContent = msg; error.hidden = !msg; }
  boton.disabled = true;
  input.addEventListener('change', function () {
    var f = input.files && input.files[0];
    mostrarError('');
    if (url) { URL.revokeObjectURL(url); url = null; }
    caja.innerHTML = original;
    if (!f) { nombre.textContent = form.getAttribute('data-vacio') || 'Ninguna imagen elegida'; boton.disabled = true; return; }
    var ext = (f.name.split('.').pop() || '').toLowerCase();
    nombre.textContent = f.name + ' (' + kb(f.size) + ')';
    if (exts.indexOf(ext) === -1) { mostrarError('Formato «.' + ext + '» no admitido. Use ' + form.getAttribute('data-formatos') + '.'); boton.disabled = true; return; }
    if (f.size > maxMb * 1048576) { mostrarError('La imagen pesa ' + kb(f.size) + '; el máximo es ' + maxMb + ' MB.'); boton.disabled = true; return; }
    if (ext !== 'ico') {
      url = URL.createObjectURL(f);
      var img = document.createElement('img');
      img.alt = 'Vista previa de la imagen elegida';
      img.src = url;
      caja.textContent = '';
      caja.appendChild(img);
      caja.classList.add('is-pending');
    }
    boton.disabled = false;
  });
});
/* Estilo cristal: vista previa en vivo mientras se ajusta en Configuración → Apariencia (se guarda con el formulario) */
(function () {
  var sec = document.querySelector('[data-estilo-form]');
  if (!sec) return;
  var root = document.documentElement;
  var hint = sec.querySelector('[data-cristal-hint]');
  var fondoUrl = sec.getAttribute('data-fondo-url');
  var vars = { cristal_opacidad: ['--glass-a', function (v) { return v / 100; }], cristal_desenfoque: ['--glass-blur', function (v) { return v + 'px'; }], fondo_oscurecer: ['--scrim-a', function (v) { return v / 100; }], cristal_intensidad: ['--glass-i', function (v) { return v / 100; }], fondo_transparencia: ['--fondo-t', function (v) { return v / 100; }] };
  function aplicarFondo(url) {
    if (url) { root.style.setProperty('--fondo-img', 'url("' + url + '")'); root.setAttribute('data-fondo', '1'); }
  }
  aplicarFondo(fondoUrl);
  sec.querySelectorAll('input[name=estilo_interfaz]').forEach(function (r) {
    r.addEventListener('change', function () {
      if (!r.checked) return;
      root.setAttribute('data-estilo', r.value);
      if (hint) hint.hidden = r.value === 'cristal';
    });
  });
  sec.querySelectorAll('input[type=range]').forEach(function (inp) {
    var out = sec.querySelector('output[for="' + inp.id + '"]');
    var map = vars[inp.name];
    function upd() {
      if (out) out.textContent = inp.value + out.getAttribute('data-unidad');
      if (map) root.style.setProperty(map[0], map[1](parseInt(inp.value, 10)));
    }
    inp.addEventListener('input', upd);
    upd();
  });
  // Una foto recién elegida (aún sin subir) se ve de fondo de inmediato.
  var fondoInput = document.querySelector('#marca-fondo input[type=file]');
  if (fondoInput) {
    fondoInput.addEventListener('change', function () {
      var f = fondoInput.files && fondoInput.files[0];
      if (f && /^image\/(png|jpeg|webp)$/.test(f.type)) aplicarFondo(URL.createObjectURL(f));
    });
  }
})();

/* Reloj de los dashboards: fecha y hora al minuto, en español */
(function () {
  var nodos = document.querySelectorAll('[data-clock]');
  if (!nodos.length || !window.Intl) return;
  var f = new Intl.DateTimeFormat('es', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  var h = new Intl.DateTimeFormat('es', { hour: '2-digit', minute: '2-digit', hour12: false });
  function pintar() {
    var d = new Date();
    var t = f.format(d);
    t = t.charAt(0).toUpperCase() + t.slice(1) + ', ' + h.format(d);
    nodos.forEach(function (n) { n.textContent = t; n.setAttribute('datetime', d.toISOString()); });
  }
  pintar();
  setTimeout(function () { pintar(); setInterval(pintar, 60000); }, (60 - new Date().getSeconds()) * 1000);
})();

/* Filas de tabla con data-href: toda la fila abre el registro (los enlaces, botones y campos internos conservan su acción) */
document.addEventListener('click', function (e) {
  var tr = e.target.closest && e.target.closest('tr[data-href]');
  if (!tr || e.target.closest('a, button, input, select, label, form, summary, details')) return;
  if (window.getSelection && String(window.getSelection())) return;   // no interferir al seleccionar texto
  if (e.ctrlKey || e.metaKey) { window.open(tr.getAttribute('data-href'), '_blank'); return; }
  window.location.assign(tr.getAttribute('data-href'));
});

/* Menú ⋮ de fila: al abrirse se coloca junto a su botón (posición fija), hacia arriba si no cabe abajo */
document.addEventListener('toggle', function (e) {
  var d = e.target;
  if (!d.matches || !d.matches('details.row-menu') || !d.open) return;
  var btn = d.querySelector('summary').getBoundingClientRect();
  var panel = d.querySelector('.dropdown-panel');
  var alto = panel.offsetHeight, ancho = panel.offsetWidth;
  var arriba = btn.bottom + 6 + alto > window.innerHeight && btn.top - 6 - alto > 0;
  var top = arriba ? btn.top - 6 - alto : btn.bottom + 6, left = Math.max(8, btn.right - ancho);
  panel.style.top = top + 'px';
  panel.style.left = left + 'px';
  // Un panel con desenfoque (estilo cristal) se vuelve el contenedor de los elementos fijos: se corrige el desfase.
  var r = panel.getBoundingClientRect();
  panel.style.top = (top - (r.top - top)) + 'px';
  panel.style.left = (left - (r.left - left)) + 'px';
}, true);
var menuAbierto = 0;
document.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('details.row-menu > summary')) menuAbierto = Date.now(); }, true);
/* Al desplazar la página el menú se cierra (no al abrirlo: el navegador puede desplazar la fila a la vista) */
window.addEventListener('scroll', function () { if (Date.now() - menuAbierto < 600) return; document.querySelectorAll('details.row-menu[open]').forEach(function (d) { d.open = false; }); }, { passive: true });

/* Mostrar u ocultar la contraseña */
document.addEventListener('click', function (e) {
  var b = e.target.closest && e.target.closest('[data-pass-toggle]');
  if (!b) return;
  var input = document.getElementById(b.getAttribute('aria-controls'));
  var ver = input.type === 'password';
  input.type = ver ? 'text' : 'password';
  b.textContent = ver ? 'Ocultar' : 'Mostrar';
  b.setAttribute('aria-pressed', ver ? 'true' : 'false');
  input.focus();
});

/* Barra lateral: vista previa en vivo sobre el propio menú mientras se elige (se guarda con el formulario) */
(function () {
  var sec = document.querySelector('[data-barra-form]');
  if (!sec) return;
  var root = document.documentElement;
  var caja = sec.querySelector('[data-barra-color]');
  var picker = sec.querySelector('#barra_color');
  var hex = sec.querySelector('#barra_hex');
  var VARS = ['--side-bg', '--side-ink', '--side-strong', '--side-muted', '--side-line', '--side-hover', '--side-active', '--side-chip', '--side-scroll', '--side-accent'];
  function lum(h) {
    var c = [1, 3, 5].map(function (i) { var v = parseInt(h.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  function aplicar() {
    var modo = sec.querySelector('input[name=barra_modo]:checked').value;
    caja.hidden = modo !== 'personalizado';
    VARS.forEach(function (v) { root.style.removeProperty(v); });
    if (modo === 'automatico') { root.removeAttribute('data-barra'); return; }
    var bg = modo === 'claro' ? '#f3f5f7' : picker.value;
    var claro = lum(bg) > 0.4;
    var p = claro
      ? ['#33444e', '#0f1b21', '#5b6d78', 'rgba(15,27,33,.12)', 'rgba(15,27,33,.06)', 'rgba(15,27,33,.1)', 'rgba(15,27,33,.1)', 'rgba(15,27,33,.25)', 'var(--brand)']
      : ['#d6e1e6', '#ffffff', '#aebfc7', 'rgba(255,255,255,.12)', 'rgba(255,255,255,.08)', 'rgba(255,255,255,.14)', 'rgba(255,255,255,.16)', 'rgba(255,255,255,.22)', '#ffffff'];
    root.setAttribute('data-barra', modo);
    root.style.setProperty('--side-bg', bg);
    VARS.slice(1).forEach(function (v, i) { root.style.setProperty(v, p[i]); });
  }
  sec.querySelectorAll('input[name=barra_modo]').forEach(function (r) { r.addEventListener('change', aplicar); });
  picker.addEventListener('input', function () { hex.value = picker.value; hex.setCustomValidity(''); aplicar(); });
  hex.addEventListener('input', function () {
    var v = hex.value.trim();
    if (v.charAt(0) !== '#') v = '#' + v;
    if (/^#[0-9a-fA-F]{6}$/.test(v)) { picker.value = v.toLowerCase(); hex.setCustomValidity(''); aplicar(); } else { hex.setCustomValidity('Use el formato #RRGGBB'); }
  });
})();

/* Menús desplegables (Documentos, Exportar): si el panel se saldría de la pantalla, se desplaza para quedar completo */
document.addEventListener('toggle', function (e) {
  var d = e.target;
  if (!d.matches || !d.matches('details.dropdown:not(.row-menu)') || !d.open) return;
  var p = d.querySelector('.dropdown-panel');
  if (!p) return;
  p.style.translate = '';
  p.style.width = '';
  var vw = document.documentElement.clientWidth;
  if (p.offsetWidth > vw - 16) p.style.width = (vw - 16) + 'px';
  var r = p.getBoundingClientRect();
  var dx = 0;
  if (r.right > vw - 8) dx = vw - 8 - r.right;
  if (r.left + dx < 8) dx = 8 - r.left;
  if (dx) p.style.translate = Math.round(dx) + 'px 0';
}, true);

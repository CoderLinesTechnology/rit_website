/* RIT Foundation Ghana - admin behaviour. Progressive enhancement; every form works without it. */
document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* sidebar toggle (mobile): visible without JS, collapsed by JS below 62rem */
  var tg = $('.topbar__menu[aria-controls]');
  var side = tg ? document.getElementById(tg.getAttribute('aria-controls')) : null;
  if (tg && side) {
    var small = window.matchMedia('(max-width: 61.99rem)');
    var set = function (open) { side.hidden = !open; tg.setAttribute('aria-expanded', open ? 'true' : 'false'); };
    set(!small.matches);
    small.addEventListener('change', function (e) { set(!e.matches); });
    tg.addEventListener('click', function () { set(side.hidden); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && small.matches && !side.hidden) { set(false); tg.focus(); }
    });
  }
  /* close the user menu on Escape / outside click */
  var um = $('.usermenu');
  if (um) {
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && um.open) { um.open = false; $('summary', um).focus(); } });
    document.addEventListener('click', function (e) { if (um.open && !um.contains(e.target)) um.open = false; });
  }

  /* confirm before submit */
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); e.stopImmediatePropagation(); }
    });
  });

  /* double-submit prevention (registered after confirm so a cancelled confirm does not lock the form) */
  $$('form[data-once]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      if (form.getAttribute('data-submitted') === '1') { e.preventDefault(); return; }
      form.setAttribute('data-submitted', '1');
      var btn = $('[type=submit]', form);
      if (btn) window.setTimeout(function () {
        btn.disabled = true;
        if (btn.getAttribute('data-busy-text')) btn.textContent = btn.getAttribute('data-busy-text');
      }, 0);
    });
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) { form.removeAttribute('data-submitted'); $$('[type=submit]', form).forEach(function (b) { b.disabled = false; }); }
    });
  });

  /* slug autofill */
  $$('input[data-slug-source]').forEach(function (slug) {
    var src = $(slug.getAttribute('data-slug-source'));
    if (!src) return;
    var touched = slug.value !== '';
    var make = function (s) {
      return s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
    };
    slug.addEventListener('input', function () { touched = slug.value !== ''; });
    src.addEventListener('input', function () { if (!touched) slug.value = make(src.value); });
  });

  /* image preview */
  $$('input[type=file][data-preview]').forEach(function (inp) {
    var img = $(inp.getAttribute('data-preview'));
    if (!img) return;
    var url = null;
    inp.addEventListener('change', function () {
      if (url) { URL.revokeObjectURL(url); url = null; }
      var f = inp.files && inp.files[0];
      if (f && /^image\//.test(f.type)) { url = URL.createObjectURL(f); img.src = url; img.hidden = false; }
    });
  });

  /* markup toolbar */
  var wrap = function (ta, before, after, placeholder) {
    var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
    var sel = v.slice(s, e) || placeholder;
    ta.value = v.slice(0, s) + before + sel + after + v.slice(e);
    ta.focus();
    ta.setSelectionRange(s + before.length, s + before.length + sel.length);
    ta.dispatchEvent(new Event('input', { bubbles: true }));
  };
  var prefixLines = function (ta, make) {
    var v = ta.value, s = ta.selectionStart, e = ta.selectionEnd;
    var start = v.lastIndexOf('\n', s - 1) + 1;
    var endIdx = v.indexOf('\n', e); if (endIdx === -1) endIdx = v.length;
    var lines = v.slice(start, endIdx).split('\n').map(function (l, i) { return make(i) + l; });
    var out = lines.join('\n');
    ta.value = v.slice(0, start) + out + v.slice(endIdx);
    ta.focus();
    ta.setSelectionRange(start, start + out.length);
    ta.dispatchEvent(new Event('input', { bubbles: true }));
  };
  $$('[data-markup-toolbar]').forEach(function (bar) {
    var ta = bar.nextElementSibling;
    while (ta && ta.tagName !== 'TEXTAREA') ta = ta.nextElementSibling;
    if (!ta) return;
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-insert]');
      if (!b) return;
      e.preventDefault();
      switch (b.getAttribute('data-insert')) {
        case 'bold': wrap(ta, '**', '**', 'bold text'); break;
        case 'italic': wrap(ta, '*', '*', 'italic text'); break;
        case 'h2': prefixLines(ta, function () { return '## '; }); break;
        case 'h3': prefixLines(ta, function () { return '### '; }); break;
        case 'ul': prefixLines(ta, function () { return '- '; }); break;
        case 'ol': prefixLines(ta, function (i) { return (i + 1) + '. '; }); break;
        case 'quote': prefixLines(ta, function () { return '> '; }); break;
        case 'link': wrap(ta, '[', '](https://)', 'link text'); break;
      }
    });
  });

  /* character counters */
  $$('textarea[maxlength][data-counter]').forEach(function (ta) {
    var max = parseInt(ta.getAttribute('maxlength'), 10);
    var out = document.createElement('p');
    out.className = 'field__counter';
    ta.parentNode.insertBefore(out, ta.nextSibling);
    var update = function () { out.textContent = ta.value.length + ' of ' + max + ' characters'; };
    ta.addEventListener('input', update);
    update();
  });
});

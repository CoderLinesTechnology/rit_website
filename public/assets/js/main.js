/* RIT Foundation Ghana - public site behaviour. Progressive enhancement only: every feature works without this file. */
document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* Polite live region shared by features */
  var live = document.createElement('div');
  live.className = 'visually-hidden';
  live.setAttribute('role', 'status');
  live.setAttribute('aria-live', 'polite');
  document.body.appendChild(live);
  function announce(msg) { live.textContent = ''; window.setTimeout(function () { live.textContent = msg; }, 50); }

  /* (b) mobile navigation toggle */
  var toggle = $('.nav-toggle[aria-controls]');
  if (toggle) {
    var menu = document.getElementById(toggle.getAttribute('aria-controls'));
    var mq = window.matchMedia('(min-width: 70rem)');
    var setOpen = function (open, returnFocus) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!open && returnFocus) toggle.focus();
    };
    setOpen(false);
    toggle.addEventListener('click', function () { setOpen(toggle.getAttribute('aria-expanded') !== 'true'); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true' && !mq.matches) {
        var inside = menu && menu.contains(document.activeElement);
        setOpen(false, inside || document.activeElement === toggle);
      }
    });
  }

  /* (c) gallery lightbox */
  var galleries = $$('[data-gallery]');
  if (galleries.length && typeof HTMLDialogElement === 'function') {
    var dlg = document.createElement('dialog');
    dlg.className = 'lightbox';
    dlg.setAttribute('aria-label', 'Photo viewer');
    var spriteUse = document.querySelector('svg use[href*="icons.svg"]');
    var sprite = spriteUse ? spriteUse.getAttribute('href').split('#')[0] : '/assets/images/icons.svg';
    dlg.innerHTML =
      '<div class="lightbox__inner"><figure class="lightbox__figure"><img class="lightbox__img" alt="">' +
      '<figcaption class="lightbox__caption"></figcaption></figure></div>' +
      '<button type="button" class="lightbox__btn lightbox__close" aria-label="Close"><svg class="icon" aria-hidden="true"><use href="' + sprite + '#i-x-lg"/></svg></button>' +
      '<button type="button" class="lightbox__btn lightbox__prev" aria-label="Previous photo"><svg class="icon" aria-hidden="true"><use href="' + sprite + '#i-chevron-left"/></svg></button>' +
      '<button type="button" class="lightbox__btn lightbox__next" aria-label="Next photo"><svg class="icon" aria-hidden="true"><use href="' + sprite + '#i-chevron-right"/></svg></button>';
    document.body.appendChild(dlg);
    var img = $('.lightbox__img', dlg), cap = $('.lightbox__caption', dlg);
    var items = [], index = 0, opener = null;

    var show = function (i) {
      if (!items.length) return;
      index = (i + items.length) % items.length;
      var a = items[index];
      img.src = a.getAttribute('href');
      img.alt = a.getAttribute('data-alt') || '';
      cap.textContent = a.getAttribute('data-caption') || '';
    };
    var openAt = function (a) {
      var root = a.closest('[data-gallery]');
      items = $$('a[data-lightbox]', root);
      opener = a;
      show(items.indexOf(a));
      if (!dlg.open) dlg.showModal();
    };
    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest ? e.target.closest('a[data-lightbox]') : null;
      if (a && a.closest('[data-gallery]')) { e.preventDefault(); openAt(a); }
    });
    $('.lightbox__close', dlg).addEventListener('click', function () { dlg.close(); });
    $('.lightbox__prev', dlg).addEventListener('click', function () { show(index - 1); });
    $('.lightbox__next', dlg).addEventListener('click', function () { show(index + 1); });
    dlg.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { e.preventDefault(); show(index - 1); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); show(index + 1); }
    });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    dlg.addEventListener('close', function () {
      img.removeAttribute('src');
      if (opener && document.contains(opener)) opener.focus();
    });
  }

  /* (d) "Load more" progressive loading */
  $$('a[data-load-more]').forEach(function (link) {
    var target = $(link.getAttribute('data-target') || '');
    if (!target || !window.fetch) return;
    var busy = false;
    link.addEventListener('click', function (e) {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      if (busy) return;
      busy = true;
      var label = link.textContent;
      link.setAttribute('aria-busy', 'true');
      link.textContent = 'Loading…';
      fetch(link.href, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error('bad status'); return r.text(); })
        .then(function (html) {
          var tpl = document.createElement('template');
          tpl.innerHTML = html.trim();
          var root = tpl.content.firstElementChild;
          if (!root) throw new Error('empty');
          var next = root.getAttribute('data-next-url') || '';
          var before = target.children.length;
          Array.prototype.slice.call(root.children).forEach(function (c) { target.appendChild(document.importNode(c, true)); });
          var added = target.children.length - before;
          announce(added + (added === 1 ? ' more photo loaded' : ' more photos loaded'));
          if (next) {
            link.href = next; link.textContent = label; link.removeAttribute('aria-busy'); busy = false;
          } else {
            var wrap = link.closest('.load-more') || link;
            wrap.parentNode.removeChild(wrap);
          }
        })
        .catch(function () { window.location.href = link.href; });
    });
  });

  /* (e) prevent double submit */
  $$('form[data-once]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var btn = $('[type=submit]', form);
      if (form.getAttribute('data-submitted') === '1') { e.preventDefault(); return; }
      form.setAttribute('data-submitted', '1');
      if (btn) {
        window.setTimeout(function () {
          btn.disabled = true;
          if (btn.getAttribute('data-busy-text')) btn.textContent = btn.getAttribute('data-busy-text');
        }, 0);
      }
    });
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) { form.removeAttribute('data-submitted'); $$('[type=submit]', form).forEach(function (b) { b.disabled = false; }); }
    });
  });

  /* (f) conditional fields: hide only, never disable */
  var conditional = $$('[data-show-when]');
  if (conditional.length) {
    var evaluate = function () {
      conditional.forEach(function (el) {
        var parts = el.getAttribute('data-show-when').split('=');
        var name = parts[0], want = parts.slice(1).join('=');
        var ctl = $$('[name="' + name + '"]');
        var value = '';
        ctl.forEach(function (c) {
          if ((c.type === 'radio' || c.type === 'checkbox') ? c.checked : true) value = c.value;
        });
        el.hidden = want.split('|').indexOf(value) === -1;
      });
    };
    document.addEventListener('change', evaluate);
    evaluate();
  }

  /* (g) character counters */
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

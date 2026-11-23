(() => {
  'use strict';

  // Sidebar mobile
  const side = document.getElementById('side');
  const scrim = document.getElementById('sideScrim');
  const tog = document.getElementById('sideToggle');
  const setSide = open => { side?.classList.toggle('is-open', open); scrim?.classList.toggle('is-open', open); };
  tog?.addEventListener('click', () => setSide(!side.classList.contains('is-open')));
  scrim?.addEventListener('click', () => setSide(false));

  // Dropdown navigasi (pengganti onchange inline agar CSP tetap ketat)
  document.querySelectorAll('select[data-nav]').forEach(sel => {
    sel.addEventListener('change', () => {
      const v = sel.value;
      if (v.startsWith('?')) location.href = location.pathname + v;
    });
  });

  // ---------- Formulir biaya: faktur pajak & PPN otomatis (11/111 dari nominal) ----------
  document.querySelectorAll('[data-faktur]').forEach(chk => {
    const form = chk.closest('form');
    const wrap = form.querySelector('.fakt__ppn');
    const ppn = form.querySelector('[data-ppn]');
    const amt = form.querySelector('[data-amount]');
    const num = el => Number((el.value || '').replace(/[^\d]/g, '')) || 0;
    let auto = !ppn.value;
    const fill = () => { if (chk.checked && auto) { const a = num(amt); ppn.value = a ? Math.round(a - a / 1.11).toLocaleString('id-ID') : ''; } };
    chk.addEventListener('change', () => { wrap.hidden = !chk.checked; fill(); });
    amt.addEventListener('input', fill);
    ppn.addEventListener('input', () => { auto = !ppn.value; });
  });

  // ---------- Halaman proyek ----------
  // Tab aktif selalu terlihat di layar sempit
  const onTab = document.querySelector('.ptabs a.is-on');
  if (onTab) onTab.scrollIntoView({ block: 'nearest', inline: 'center' });

  // Menu ⋯ (details): hanya satu terbuka, tutup saat klik di luar
  const menus = [...document.querySelectorAll('details.dd')];
  menus.forEach(d => d.addEventListener('toggle', () => { if (d.open) menus.forEach(o => { if (o !== d) o.open = false; }); }));
  document.addEventListener('click', e => { menus.forEach(d => { if (d.open && !d.contains(e.target)) d.open = false; }); });

  // Tombol buka/tutup form (unggah, tambah item, album baru, dll.)
  const openPanel = (el, scroll) => {
    el.hidden = false;
    const f = el.querySelector('input:not([type=hidden]):not([type=checkbox]), select');
    if (scroll) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (f && f.type !== 'file') setTimeout(() => f.focus({ preventScroll: true }), 250);
  };
  document.querySelectorAll('[data-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const el = document.getElementById(btn.dataset.toggle);
      if (!el) return;
      const dd = btn.closest('details.dd'); if (dd) dd.open = false;
      if (el.hidden) openPanel(el, !!dd); else el.hidden = true;
    });
  });
  // Datang dari tautan "Unggah" di Ringkasan (#item-xxx): buka form item tersebut
  if (location.hash.startsWith('#item-')) {
    const up = document.getElementById('up-' + location.hash.slice(1));
    if (up) openPanel(up, true);
  }

  // Album: tampilkan semua foto
  document.querySelectorAll('[data-expand]').forEach(b => b.addEventListener('click', () => {
    const grid = b.previousElementSibling; if (grid) grid.classList.remove('is-collapsed');
    b.remove();
  }));

  // Mode pilih foto (hapus / pindahkan beberapa sekaligus)
  const bulk = document.getElementById('bulkForm');
  if (bulk) {
    const boxes = () => [...document.querySelectorAll('input[form="bulkForm"]')];
    const count = bulk.querySelector('[data-sel-count]');
    const update = () => { count.textContent = boxes().filter(b => b.checked).length; };
    document.querySelectorAll('[data-select-toggle]').forEach(b => b.addEventListener('click', () => {
      const on = document.body.classList.toggle('is-selecting');
      bulk.hidden = !on;
      if (!on) boxes().forEach(x => { x.checked = false; });
      update();
    }));
    document.addEventListener('change', e => { if (e.target.matches('input[form="bulkForm"]')) update(); });
    bulk.addEventListener('submit', e => {
      const op = e.submitter ? e.submitter.value : '';
      const n = boxes().filter(b => b.checked).length;
      if (!n) { e.preventDefault(); return; }
      if (op === 'delete' && !confirm('Hapus ' + n + ' foto yang dipilih? Tindakan ini tidak bisa dibatalkan.')) e.preventDefault();
      if (op === 'move' && !bulk.querySelector('select[name=target_album]').value) { e.preventDefault(); bulk.querySelector('select').focus(); }
      if (op) { const h = document.createElement('input'); h.type = 'hidden'; h.name = 'op'; h.value = op; bulk.append(h); }
    });
  }

  // Lightbox galeri foto: buka layar penuh, geser kiri/kanan, tombol panah & Esc
  document.querySelectorAll('[data-gallery]').forEach(gal => {
    const links = [...gal.querySelectorAll('a[data-full]')];
    if (!links.length) return;
    let idx = 0, box, img, cap, count, dl, startX = 0;
    const svg = d => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + d + '</svg>';
    const el = (tag, cls, html) => { const n = document.createElement(tag); if (cls) n.className = cls; if (html) n.innerHTML = html; return n; };
    const build = () => {
      box = el('div', 'lb'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
      const bar = el('div', 'lb__bar'); count = el('span', 'lb__count');
      dl = el('a', 'lb__btn', svg('<path d="M12 4v12M7 11l5 5 5-5M4 20h16"/>')); dl.title = 'Unduh';
      const close = el('button', 'lb__btn', svg('<path d="M6 6l12 12M18 6L6 18"/>')); close.type = 'button'; close.title = 'Tutup (Esc)';
      bar.append(count, dl, close);
      const stage = el('div', 'lb__stage'); img = el('img'); img.alt = '';
      const prev = el('button', 'lb__btn lb__nav lb__prev', svg('<path d="M15 6l-6 6 6 6"/>')); prev.type = 'button'; prev.title = 'Sebelumnya';
      const next = el('button', 'lb__btn lb__nav lb__next', svg('<path d="M9 6l6 6-6 6"/>')); next.type = 'button'; next.title = 'Berikutnya';
      stage.append(img, prev, next);
      cap = el('div', 'lb__cap');
      box.append(bar, stage, cap);
      close.addEventListener('click', hide);
      prev.addEventListener('click', () => show(idx - 1));
      next.addEventListener('click', () => show(idx + 1));
      stage.addEventListener('click', e => { if (e.target === stage) hide(); });
      img.addEventListener('load', () => img.classList.remove('is-loading'));
      box.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
      box.addEventListener('touchend', e => { const dx = e.changedTouches[0].clientX - startX; if (Math.abs(dx) > 50) show(idx + (dx < 0 ? 1 : -1)); });
      if (links.length < 2) { prev.hidden = true; next.hidden = true; }
    };
    const onKey = e => {
      if (e.key === 'Escape') hide();
      else if (e.key === 'ArrowRight') show(idx + 1);
      else if (e.key === 'ArrowLeft') show(idx - 1);
    };
    function show(i) {
      idx = (i + links.length) % links.length;
      const a = links[idx];
      img.classList.add('is-loading');
      img.src = a.dataset.full;
      img.alt = a.dataset.caption || '';
      cap.textContent = a.dataset.caption || '';
      count.textContent = (idx + 1) + ' / ' + links.length;
      dl.href = a.dataset.dl;
      const nxt = links[(idx + 1) % links.length]; if (nxt) { const p = new Image(); p.src = nxt.dataset.full; }
    }
    function hide() { box.remove(); document.body.classList.remove('lb-open'); document.removeEventListener('keydown', onKey); }
    links.forEach((a, i) => a.addEventListener('click', e => {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1 || document.body.classList.contains('is-selecting')) return;
      e.preventDefault();
      if (!box) build();
      document.body.append(box); document.body.classList.add('lb-open');
      document.addEventListener('keydown', onKey);
      show(i);
    }));
  });

  // Lanjutkan proses bertahap otomatis (mis. kompres foto)
  document.querySelectorAll('form[data-autosubmit]').forEach(f => setTimeout(() => f.submit(), 900));

  // Konfirmasi sebelum aksi berbahaya
  document.querySelectorAll('form[data-confirm]').forEach(f => {
    f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // Baris tabel yang bisa diklik
  document.querySelectorAll('tr[data-href]').forEach(tr => {
    tr.addEventListener('click', e => {
      if (e.target.closest('a, button, form, input')) return;
      location.href = tr.dataset.href;
    });
  });

  // Area unggah: tampilkan nama file + efek seret
  document.querySelectorAll('[data-drop]').forEach(zone => {
    const input = zone.querySelector('input[type=file]');
    const label = zone.querySelector('[data-drop-label]');
    const original = label?.textContent;
    const update = () => {
      const n = input.files.length;
      zone.classList.toggle('has-file', n > 0);
      if (label) label.textContent = n === 0 ? original : n === 1 ? input.files[0].name : n + ' file dipilih';
    };
    input.addEventListener('change', update);
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, () => zone.classList.add('is-over')));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('is-over')));
  });

  // Input rupiah: format ribuan saat mengetik
  document.querySelectorAll('[data-rupiah]').forEach(inp => {
    const fmt = () => {
      const digits = inp.value.replace(/[^\d]/g, '');
      inp.value = digits ? Number(digits).toLocaleString('id-ID') : '';
    };
    inp.addEventListener('input', fmt);
  });

  // Slider progres
  document.querySelectorAll('[data-range]').forEach(r => {
    const out = r.closest('.f')?.querySelector('[data-out]');
    r.addEventListener('input', () => { if (out) out.textContent = r.value; });
  });

  // Pembuat kata sandi acak
  document.querySelectorAll('[data-genpw]').forEach(btn => {
    btn.addEventListener('click', () => {
      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
      const arr = new Uint32Array(12);
      crypto.getRandomValues(arr);
      let pw = Array.from(arr, n => chars[n % chars.length]).join('');
      if (!/\d/.test(pw)) pw = pw.slice(0, -1) + '7';
      const inp = btn.parentElement.querySelector('[data-pw]');
      inp.value = pw;
      inp.select();
    });
  });

  // Animasi bilah progres saat halaman dimuat
  document.querySelectorAll('.bar i').forEach(b => {
    const w = b.style.width;
    b.style.width = '0';
    requestAnimationFrame(() => requestAnimationFrame(() => { b.style.width = w; }));
  });
})();

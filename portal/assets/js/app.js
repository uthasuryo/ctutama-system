(() => {
  'use strict';

  // Sidebar mobile
  const side = document.getElementById('side');
  const scrim = document.getElementById('sideScrim');
  const tog = document.getElementById('sideToggle');
  const setSide = open => { side?.classList.toggle('is-open', open); scrim?.classList.toggle('is-open', open); };
  tog?.addEventListener('click', () => setSide(!side.classList.contains('is-open')));
  scrim?.addEventListener('click', () => setSide(false));

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

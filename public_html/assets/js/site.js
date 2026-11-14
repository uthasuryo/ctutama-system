(() => {
  'use strict';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Navigasi: latar saat digulir + menu mobile
  const nav = document.getElementById('nav');
  const onScroll = () => nav && nav.classList.toggle('is-scrolled', scrollY > 30);
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  const toggle = document.getElementById('navToggle');
  const links = document.getElementById('navLinks');
  if (toggle && links) {
    toggle.addEventListener('click', () => {
      const open = links.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
    });
    links.addEventListener('click', e => {
      if (e.target.closest('a')) { links.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); }
    });
  }

  // Animasi muncul saat terlihat
  const items = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !reduce) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    items.forEach(el => io.observe(el));
  } else {
    items.forEach(el => el.classList.add('is-in'));
  }

  // Penghitung angka
  const counters = document.querySelectorAll('[data-count]');
  if (counters.length && 'IntersectionObserver' in window && !reduce) {
    const co = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        const el = en.target, end = +el.dataset.count, t0 = performance.now(), dur = 1400;
        const tick = now => {
          const p = Math.min(1, (now - t0) / dur);
          el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
          if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
        co.unobserve(el);
      });
    }, { threshold: 0.5 });
    counters.forEach(el => co.observe(el));
  }

  // Teks berganti di hero
  const rot = document.getElementById('rot');
  if (rot) {
    const words = [...rot.children];
    let i = 0;
    const show = () => {
      words.forEach((w, j) => w.classList.toggle('is-on', j === i));
      rot.style.color = words[i].dataset.c || '';
    };
    show();
    if (!reduce) setInterval(() => { i = (i + 1) % words.length; show(); }, 2800);
  }

  // Efek paralaks halus pada logo hero
  const art = document.querySelector('.hero__art');
  if (art && !reduce && matchMedia('(pointer: fine)').matches) {
    addEventListener('mousemove', e => {
      const x = (e.clientX / innerWidth - .5) * 18, y = (e.clientY / innerHeight - .5) * 18;
      art.style.transform = `translate(${x}px, ${y}px)`;
    }, { passive: true });
  }

  // Filter portofolio
  const filters = document.querySelectorAll('.filter');
  const grid = document.getElementById('pgrid');
  if (filters.length && grid) {
    const empty = document.getElementById('pEmpty');
    const apply = key => {
      let shown = 0;
      grid.querySelectorAll('.pcard').forEach(c => {
        const on = key === 'all' || c.dataset.division === key;
        c.hidden = !on;
        if (on) { shown++; c.classList.add('is-in'); }
      });
      filters.forEach(f => f.classList.toggle('is-on', f.dataset.filter === key));
      if (empty) empty.hidden = shown > 0;
    };
    filters.forEach(f => f.addEventListener('click', () => {
      apply(f.dataset.filter);
      const u = new URL(location);
      f.dataset.filter === 'all' ? u.searchParams.delete('d') : u.searchParams.set('d', f.dataset.filter);
      history.replaceState(null, '', u);
    }));
    const init = document.querySelector('.filter.is-on');
    if (init) apply(init.dataset.filter);
  }
})();

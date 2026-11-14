/* ==========================================================
   Kalkulator Margin & Net Profit — PT Candaka Tiga Utama
   Rumus ctuCalc() identik dengan margin_calc() di app/margin.php.
   ========================================================== */
(function () {
  'use strict';
  var dataEl = document.getElementById('calcData');
  if (!dataEl) return;
  var C = JSON.parse(dataEl.textContent);
  var $ = function (id) { return document.getElementById(id); };

  /* ---------- Format & parsing ---------- */
  function rp(n) { var s = Math.round(Math.abs(n)).toLocaleString('id-ID'); return (n < 0 ? '−' : '') + 'Rp ' + s; }
  function pc(n) { return (n === null || !isFinite(n)) ? '–' : n.toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '%'; }
  function money(el) { var s = el.value, neg = el.hasAttribute('data-neg') && /^\s*-/.test(s), d = s.replace(/\D/g, ''); return (neg ? -1 : 1) * (d ? Number(d) : 0); }
  function setMoney(el, n) { n = Math.round(Number(n) || 0); el.value = (n < 0 ? '-' : '') + (n ? Math.abs(n).toLocaleString('id-ID') : '0'); }
  function mask(el) { var s = el.value, neg = el.hasAttribute('data-neg') && /^\s*-/.test(s), d = s.replace(/\D/g, ''); el.value = (neg ? '-' : '') + (d ? Number(d).toLocaleString('id-ID') : ''); }
  function pnum(v) { var n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; }
  function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
  function set(id, v, neg) { var e = $(id); if (!e) return; e.textContent = v; e.classList.toggle('neg', !!neg); }

  document.addEventListener('input', function (e) { if (e.target.classList && e.target.classList.contains('money')) mask(e.target); });

  /* ---------- Rumus per proyek ---------- */
  function ctuCalc(i) {
    var r = i.ppn / 100, S = i.jual;
    var net = i.jualMode === 'in' ? S / (1 + r) : S;
    var ppnOut = net * r, total = net + ppnOut, inc = i.costMode === 'in';
    var cost = 0, ppnIn = 0, hpp = 0, cashOut = 0;
    i.rows.forEach(function (x, k) {
      var a = Number(x.a) || 0, n = a, p = 0;
      if (x.f) { if (inc) { n = a / (1 + r); p = a - n; } else { p = a * r; } }
      cost += n; ppnIn += p; cashOut += n + p; if (k === 0) hpp = n;
    });
    var gp = net - hpp, ebt = net - cost, kon = i.jenis === 'k';
    var scheme = kon ? 'final' : i.scheme, t = (kon ? pnum(i.kRate) : i.rate) / 100;
    var pph = scheme === 'badan' ? Math.max(0, ebt) * t : net * t;
    var np = ebt - pph, wh = net * (kon ? t : i.wh / 100), m = i.target / 100;
    var pem = !!i.pem, pn = pem ? -ppnIn : ppnOut - ppnIn;
    var cashIn = (pem ? net : total) - wh;
    var setor = pem ? 0 : Math.max(0, ppnOut - ppnIn);
    var held = pem ? ppnIn : Math.max(0, ppnIn - ppnOut);
    var saldo = cashIn - cashOut - setor;
    var pphLeft = kon ? 0 : scheme === 'badan' ? pph - wh : pph;
    var den = 1 - t - m;
    var need = den > 0 ? (scheme === 'badan' ? cost * (1 - t) / den : cost / den) : null;
    var pct = function (a, b) { return b ? a / b * 100 : null; };
    return {
      net: net, ppnOut: ppnOut, total: total, cost: cost, ppnIn: ppnIn, hpp: hpp, cashOut: cashOut,
      gp: gp, gm: pct(gp, net), ebt: ebt, ebtm: pct(ebt, net), markup: pct(ebt, cost),
      kon: kon, scheme: scheme, t: t, pph: pph, np: np, npm: pct(np, net), wh: wh, pem: pem, pn: pn,
      ppnByClient: pem ? ppnOut : 0, cashIn: cashIn, setor: setor, held: held,
      restMasa: pem ? held : 0, restAkhir: pem ? 0 : held, saldo: saldo,
      ret: cashOut ? Math.min(100, Math.max(0, (cashIn - setor) / cashOut * 100)) : null,
      pphLeft: pphLeft, fin: saldo + held - pphLeft,
      need: need, needIncl: need !== null ? need * (1 + r) : null
    };
  }
  window.ctuCalc = ctuCalc;

  /* =========================================================
     MODE PER PROYEK
     ========================================================= */
  if (C.mode === 'proyek') {
    var I = JSON.parse(JSON.stringify(C.inputs));
    var rows = I.rows;
    var fields = ['name', 'jenis', 'jualMode', 'ppn', 'wh', 'kRate', 'pem', 'costMode', 'scheme', 'rate', 'target', 'year'];

    // Isi formulir dari data
    fields.forEach(function (k) { if ($(k)) $(k).value = I[k]; });
    setMoney($('jual'), I.jual);

    function read() {
      var o = { rows: rows.map(function (r) { return { n: r.n, a: Number(r.a) || 0, f: !!r.f }; }) };
      o.name = $('name').value; o.jenis = $('jenis').value; o.jual = money($('jual'));
      o.jualMode = $('jualMode').value; o.ppn = +$('ppn').value; o.wh = pnum($('wh').value);
      o.kRate = $('kRate').value; o.pem = +$('pem').value; o.costMode = $('costMode').value;
      o.scheme = $('scheme').value; o.rate = pnum($('rate').value); o.target = pnum($('target').value);
      o.year = parseInt($('year').value, 10) || new Date().getFullYear();
      return o;
    }
    var initial = JSON.stringify(read());

    function renderRows() {
      $('rows').innerHTML = rows.map(function (r, i) {
        return '<div class="cost" data-i="' + i + '">' +
          '<input class="cost__n" data-k="n" value="' + esc(r.n) + '" aria-label="Nama biaya" placeholder="Nama biaya">' +
          '<input class="cost__a money" data-k="a" inputmode="numeric" value="' + (Math.round(r.a) || 0).toLocaleString('id-ID') + '" aria-label="Jumlah">' +
          '<label class="cost__f" title="Berfaktur pajak — PPN masukan dikreditkan"><input type="checkbox" data-k="f"' + (r.f ? ' checked' : '') + '><i></i><span>Faktur</span></label>' +
          '<button type="button" class="iconbtn iconbtn--danger" data-del="' + i + '" aria-label="Hapus biaya">✕</button>' +
          (i === 0 ? '<span class="cost__tag">harga pokok</span>' : '') +
          '</div>';
      }).join('') || '<p class="note">Belum ada biaya.</p>';
    }

    function ui() {
      var j = $('jenis').value, k = j === 'k';
      $('whW').hidden = k; $('kW').hidden = !k; $('schW').hidden = k; $('rateW').hidden = k;
      $('whL').textContent = j === 'j' ? 'PPh 23 dipotong klien (%)' : 'PPh 22 dipungut klien (%)';
      $('whN').textContent = k
        ? 'PPh final konstruksi dihitung dari nilai kontrak tanpa PPN, dipotong klien, dan bersifat final. Jadi laba proyek ini tidak kena PPh Badan lagi.'
        : j === 'j'
          ? 'Jasa teknik/IT ke klien berbadan hukum umumnya dipotong PPh 23 sebesar 2%. Potongan ini menjadi kredit pajak, bukan beban.'
          : 'Isi PPh 22 sekitar 1,5% bila klien instansi pemerintah dan Anda punya NPWP; 0 untuk klien swasta.';
      $('rateL').textContent = $('scheme').value === 'badan' ? 'Tarif PPh Badan (%)' : 'Tarif PPh Final (%)';
    }

    function calc() {
      var i = read(), c = ctuCalc(i);
      set('oNet', rp(c.np), c.np < 0); set('oNetM', pc(c.npm), c.np < 0);
      set('oS', rp(c.net)); set('oC', rp(c.cost));
      set('oGP', rp(c.gp), c.gp < 0); set('oGM', pc(c.gm), c.gp < 0);
      set('oEBT', rp(c.ebt), c.ebt < 0); set('oEBTM', pc(c.ebtm), c.ebt < 0);
      set('oMU', pc(c.markup), c.ebt < 0);
      set('oPPh', rp(c.pph));
      $('oPPhL').textContent = c.kon ? 'PPh final konstruksi' : 'PPh terutang';
      set('oPO', rp(c.ppnOut)); set('oPI', rp(c.ppnIn));
      $('oPNl').textContent = c.pn >= 0 ? 'PPN kurang bayar (Anda setor)' : 'PPN lebih bayar (kompensasi/restitusi)';
      set('oPN', rp(Math.abs(c.pn)));
      set('hPN', c.pn > 0 ? 'Kurang bayar ' + rp(c.pn) : c.pn < 0 ? 'Lebih bayar ' + rp(-c.pn) : 'Nihil');
      set('oPS', rp(c.ppnByClient)); set('oT', rp(c.total));
      $('oWHl').textContent = c.kon ? 'PPh final dipotong klien' : (i.jenis === 'j' ? 'PPh 23 dipotong klien' : 'PPh 22 dipungut klien');
      set('oWH', rp(c.wh)); set('oCash', rp(c.cashIn));
      set('kIn', rp(c.cashIn)); set('kOut', rp(c.cashOut)); set('kSet', rp(c.setor));
      $('kSaldoL').textContent = c.saldo >= 0 ? 'Sisa uang di rekening setelah modal kembali' : 'Modal yang belum kembali';
      set('kSaldo', rp(Math.abs(c.saldo)), c.saldo < 0);
      set('kRet', pc(c.ret));
      set('kHeld', rp(c.held)); set('kRes', rp(c.restMasa)); set('kWait', rp(c.restAkhir));
      $('kPphL').textContent = c.pphLeft >= 0 ? 'PPh masih harus dibayar (setelah kredit)' : 'PPh lebih bayar (setelah kredit)';
      set('kPph', rp(Math.abs(c.pphLeft)));
      set('kFin', rp(c.fin), c.fin < 0);
      set('oTS', c.need !== null ? rp(c.need) : 'Target tidak tercapai');
      set('oTI', c.needIncl !== null ? rp(c.needIncl) : '–');

      // Indikator margin vs target (skala 0–30%)
      var npm = c.npm === null ? 0 : c.npm, tg = i.target, max = Math.max(30, tg * 1.5);
      var g = $('gauge');
      g.style.width = Math.max(0, Math.min(100, npm / max * 100)) + '%';
      g.parentElement.style.setProperty('--tg', Math.min(100, tg / max * 100) + '%');
      var card = $('netCard');
      card.classList.toggle('net--bad', c.np < 0);
      card.classList.toggle('net--warn', c.np >= 0 && npm < tg);
      card.classList.toggle('net--good', npm >= tg && c.np >= 0);
      $('gaugeT').textContent = 'Target ' + pc(tg) + ' · ' + (npm >= tg ? 'tercapai' : 'kurang ' + pc(tg - npm).replace('%', ' poin'));

      var d = $('dirty');
      if (d) d.hidden = JSON.stringify(i) === initial;
    }

    $('rows').addEventListener('input', function (e) {
      var row = e.target.closest('.cost'); if (!row) return;
      var k = e.target.getAttribute('data-k'), i = +row.getAttribute('data-i');
      rows[i][k] = k === 'f' ? e.target.checked : k === 'a' ? money(e.target) : e.target.value;
      calc();
    });
    $('rows').addEventListener('click', function (e) {
      var d = e.target.closest('[data-del]');
      if (d) { rows.splice(+d.getAttribute('data-del'), 1); renderRows(); calc(); }
    });
    $('addRow').addEventListener('click', function () {
      rows.push({ n: 'Biaya baru', a: 0, f: false }); renderRows(); calc();
      var ins = $('rows').querySelectorAll('.cost__n'); ins[ins.length - 1].select();
    });
    ['jual', 'jualMode', 'ppn', 'wh', 'pem', 'kRate', 'costMode', 'rate', 'target', 'year', 'name'].forEach(function (id) {
      $(id).addEventListener('input', calc); $(id).addEventListener('change', calc);
    });
    $('scheme').addEventListener('change', function () {
      $('rate').value = $('scheme').value === 'badan' ? 22 : 0.5; ui(); calc();
    });
    $('jenis').addEventListener('change', function () {
      var j = $('jenis').value;
      if (j === 'j' && pnum($('wh').value) === 0) $('wh').value = 2;
      if (j === 'b' && pnum($('wh').value) === 2) $('wh').value = 0;
      if (confirm('Ganti daftar biaya dengan contoh untuk jenis proyek ini?\n(Pilih Batal untuk mempertahankan biaya yang sudah diisi.)')) {
        var base = money($('jual')) || 1e8;
        rows = C.defaults[j].map(function (r) { return { n: r.n, a: Math.round(r.a * base / 1e8), f: r.f }; });
        renderRows();
      }
      ui(); calc();
    });

    var form = $('saveForm');
    if (form) form.addEventListener('submit', function () {
      $('inputsField').value = JSON.stringify(read());
      window.onbeforeunload = null;
    });
    window.onbeforeunload = function () {
      var d = $('dirty'); if (d && !d.hidden) return 'Ada perubahan yang belum disimpan.';
    };

    renderRows(); ui(); calc();
  }

  /* =========================================================
     MODE TAHUNAN
     ========================================================= */
  if (C.mode === 'tahunan') {
    var A = C.annual, P = C.projects;
    ['omLain', 'opex', 'kor', 'komp', 'p23', 'p25'].forEach(function (k) { setMoney($(k), A[k]); });
    $('arate').value = A.rate;

    function readA() {
      return { omLain: money($('omLain')), opex: money($('opex')), kor: money($('kor')), komp: money($('komp')),
        p23: money($('p23')), p25: money($('p25')), rate: pnum($('arate').value) };
    }

    function calcA() {
      var a = readA();
      var om = 0, eb = 0, t22 = 0, omK = 0, fin = 0, kS = 0, kV = 0, mS = 0, mV = 0;
      P.forEach(function (p) {
        om += p.o;
        if (p.k) { omK += p.o; fin += p.t; } else { eb += p.e; t22 += p.t; }
        if (p.pv) { kV += p.po; mV += p.pi; } else { kS += p.po; mS += p.pi; }
      });
      var omzet = om + a.omLain;
      var neto = eb - a.opex + a.kor;
      var pkp = Math.floor(Math.max(0, neto - a.komp) / 1000) * 1000;
      var tr = a.rate / 100, B1 = 4.8e9, B2 = 50e9, fas = 0;
      if (omzet > 0 && omzet <= B1) fas = pkp; else if (omzet > B1 && omzet <= B2) fas = pkp * B1 / omzet;
      var tax = Math.round(fas * tr * 0.5 + (pkp - fas) * tr), full = Math.round(pkp * tr);
      var sel = tax - (t22 + a.p23 + a.p25);
      set('aOm', rp(omzet)); set('aEb', rp(eb), eb < 0); set('aNeto', rp(neto), neto < 0); set('aPkp', rp(pkp));
      set('aFas', rp(fas)); set('aTax', rp(tax)); set('aTax2', rp(tax)); set('aHemat', rp(full - tax));
      set('aK22', rp(t22)); set('aK23', rp(a.p23)); set('aK25', rp(a.p25)); set('aOmK', rp(omK)); set('aFin', rp(fin));
      var mAll = mS + mV, pn = kS - mAll, lb = Math.max(0, -pn), rest = Math.min(lb, mV);
      set('aPK', rp(kS)); set('aPKV', rp(kV)); set('aPM', rp(mAll));
      $('aPNL').textContent = pn > 0 ? 'PPN kurang bayar (Anda setor)' : pn < 0 ? 'PPN lebih bayar' : 'PPN nihil';
      set('aPN', rp(Math.abs(pn))); set('aPR', rp(rest)); set('aPC', rp(lb - rest));
      $('aHL').textContent = sel > 0 ? 'PPh Pasal 29 yang harus disetor' : sel < 0 ? 'PPh lebih bayar' : 'PPh Pasal 29';
      set('aSel', rp(Math.abs(sel)));
      set('aSt', sel > 0 ? 'Ya, kurang bayar' : sel < 0 ? 'Tidak (lebih bayar)' : 'Tidak (nihil)');
      var msg = omzet > B2 ? 'Peredaran bruto di atas Rp50 miliar: Pasal 31E tidak berlaku, seluruh PKP dikenai tarif penuh.'
        : omzet > B1 ? 'Peredaran bruto Rp4,8–50 miliar: diskon 50% tarif berlaku proporsional (Rp4,8 miliar ÷ peredaran bruto) atas PKP.'
        : 'Peredaran bruto sampai Rp4,8 miliar: seluruh PKP mendapat diskon 50% tarif.';
      if (neto < 0) msg += ' Rugi fiskal tahun ini dapat dikompensasikan ke tahun berikutnya (maksimal 5 tahun).';
      if (sel < 0) msg += ' Lebih bayar dapat dikompensasikan atau diajukan restitusi.';
      if (omK > 0) msg += ' Laba proyek konstruksi tidak masuk PKP karena sudah kena PPh final, jadi biaya operasional di atas sebaiknya hanya yang terkait usaha non-final. Omzet konstruksi tetap dihitung dalam peredaran bruto untuk batas dan porsi Pasal 31E.';
      $('aNote').textContent = msg;
    }
    ['omLain', 'opex', 'kor', 'komp', 'p23', 'p25', 'arate'].forEach(function (id) { $(id).addEventListener('input', calcA); });
    $('annualForm').addEventListener('submit', function () { $('annualField').value = JSON.stringify(readA()); });
    calcA();
  }
})();

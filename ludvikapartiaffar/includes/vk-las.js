// Läser av varorna i ett veckansklipp från PDF:ens text (pdf.js getTextContent).
// Bladet från Canva har för varje vara en prisbubbla ("23 :-", ören "95", enhet "/kg")
// och under bubblan namnet med versaler, följt av förpackning och märke på egna rader.
// Resultatet är ett förslag som admin kontrollerar innan det publiceras.

// items per sida: { str, x, y, h, w } där y räknas uppifrån.
function vkLasVaror(sidor) {
  var varor = [];
  var utanNamn = 0;
  sidor.forEach(function (items, si) {
    var rader = vkRader(items);
    var priser = [], namn = [], ovrigt = [];
    rader.forEach(function (r) {
      var m = r.text.match(/^(\d{1,4})\s*:-$/);
      if (m && r.h >= 14) priser.push({ r: r, kr: +m[1] });
      else if (r.h >= 9.3 && r.h < 14 && vkVersaler(r.text)) namn.push(r);
      else ovrigt.push(r);
    });

    // Para ihop pris och namn: namnet står strax under bubblan och börjar till vänster om den.
    var par = [];
    priser.forEach(function (p, pi) {
      namn.forEach(function (n, ni) {
        var dy = n.y - p.r.y;
        if (dy < 10 || dy > 120 || n.x > p.r.x + 10) return;
        par.push({ pi: pi, ni: ni, kostnad: Math.abs(dy - 45) + 0.1 * (p.r.x - n.x) });
      });
    });
    par.sort(function (a, b) { return a.kostnad - b.kostnad; });
    var prisTill = {}, namnTaget = {};
    par.forEach(function (k) {
      if (prisTill[k.pi] !== undefined || namnTaget[k.ni]) return;
      prisTill[k.pi] = k.ni;
      namnTaget[k.ni] = true;
    });

    priser.forEach(function (p, pi) {
      if (prisTill[pi] === undefined) { utanNamn++; return; }
      var n = namn[prisTill[pi]];
      var oren = vkNarmast(ovrigt, function (r) {
        return /^\d{2}$/.test(r.text) && r.h < 12 && r.y < p.r.y && r.y > p.r.y - 30
          && r.x > p.r.x + p.r.w * 0.4 && r.x < p.r.x + p.r.w + 25;
      }, p.r);
      var enhet = vkNarmast(ovrigt, function (r) {
        return /^\/\s*[A-Za-zÅÄÖåäö]+\.?$/.test(r.text) && r.y > p.r.y && r.y < p.r.y + 30
          && Math.abs((r.x + r.w / 2) - (p.r.x + p.r.w / 2)) < 50;
      }, p.r);

      // Raderna under namnet: förpackning (har siffror) och märke eller beskrivning.
      var forp = [], info = [], senast = n.y;
      ovrigt.filter(function (r) { return Math.abs(r.x - n.x) < 4 && r.y > n.y && r.h < n.h; })
        .sort(function (a, b) { return a.y - b.y; })
        .forEach(function (r) {
          if (r.y - senast > 13 || forp.length + info.length >= 3) return;
          senast = r.y;
          (/\d/.test(r.text) ? forp : info).push(r.text);
        });

      varor.push({
        produkt: vkVersalgemener(n.text),
        forpackning: forp.join(', '),
        info: info.join(' · '),
        enhet: enhet ? enhet.text.replace(/^\/\s*|\.$/g, '').toLowerCase() : 'st',
        pris: (p.kr + (oren ? +oren.text / 100 : 0)).toFixed(2).replace('.', ','),
        sida: si + 1, _y: n.y, _x: n.x
      });
    });
  });
  varor.sort(function (a, b) { return a.sida - b.sida || (Math.abs(a._y - b._y) > 20 ? a._y - b._y : a._x - b._x); });
  varor.forEach(function (v) { delete v._y; delete v._x; });
  return { varor: varor, utanNamn: utanNamn };
}

// Slår ihop textbitar på samma rad ("5" + "kg/låda") och tar bort dubbletter.
function vkRader(items) {
  var sedda = {};
  var bitar = items.filter(function (i) {
    if (!i.str || !i.str.trim()) return false;
    var k = i.str + '|' + Math.round(i.x) + '|' + Math.round(i.y);
    if (sedda[k]) return false;
    sedda[k] = true;
    return true;
  }).sort(function (a, b) { return Math.abs(a.y - b.y) > 0.8 ? a.y - b.y : a.x - b.x; });
  var rader = [];
  bitar.forEach(function (i) {
    var r = rader[rader.length - 1];
    var glapp = r ? i.x - (r.x + r.w) : 0;
    if (r && Math.abs(r.y - i.y) <= 0.8 && Math.abs(r.h - i.h) < 0.6 && glapp > -2 && glapp < 8) {
      r.text += (glapp > 1.2 ? ' ' : '') + i.str.trim();
      r.w = i.x + i.w - r.x;
    } else {
      rader.push({ text: i.str.trim(), x: i.x, y: i.y, h: i.h, w: i.w });
    }
  });
  return rader;
}

function vkNarmast(rader, villkor, p) {
  var bast = null, avst = Infinity;
  rader.forEach(function (r) {
    if (!villkor(r)) return;
    var d = Math.hypot(r.x - p.x, r.y - p.y);
    if (d < avst) { avst = d; bast = r; }
  });
  return bast;
}

function vkVersaler(text) {
  var bokstaver = text.replace(/[^A-Za-zÅÄÖÉÜåäöéü]/g, '');
  if (bokstaver.length < 2) return false;
  return bokstaver.replace(/[^A-ZÅÄÖÉÜ]/g, '').length / bokstaver.length >= 0.7;
}

// "GRÄDDOST S:T OLOF" -> "Gräddost S:t Olof", ord med siffror lämnas ("0,33L", "48%").
function vkVersalgemener(text) {
  return text.split(/\s+/).map(function (ord) {
    if (/\d/.test(ord)) return ord;
    return ord.charAt(0).toUpperCase() + ord.slice(1).toLowerCase();
  }).join(' ');
}

if (typeof module !== 'undefined') module.exports = { vkLasVaror: vkLasVaror };

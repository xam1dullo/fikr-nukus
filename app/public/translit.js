// Latin → Cyrillic. Shared by the server (survey texts) and both pages (UI strings).
// toCyr: Uzbek, 1995 alphabet: oʻ→ў, gʻ→ғ, sh/ch digraphs, yo/yu/ya/ye, word-initial e→э, ʼ→ъ.
// toCyrQQ: Karakalpak, 2016 Latin → Cyrillic: á→ә, ǵ→ғ, ı→ы, ń→ң, ó→ө, ú→ү, w→ў, c→ц, h→ҳ.
(function (root) {
  const KEEP = /\b(?:QR|NPS|PDF|Excel|SMS|iOS|Android|macOS|Windows|Linux)|\b(?:ID|A4)\b/g; // suffixes may follow: Excelga → Excelга
  const UZ = {
    multi: [['yoʻ', 'йў'], ["yo'", 'йў'], ['yo‘', 'йў'], // "yoʻq" is й+ў, not ё+ʻ
      ['oʻ', 'ў'], ["o'", 'ў'], ['o‘', 'ў'], ['gʻ', 'ғ'], ["g'", 'ғ'], ['g‘', 'ғ'], ['sh', 'ш'], ['ch', 'ч'], ['yo', 'ё'], ['yu', 'ю'], ['ya', 'я'], ['ye', 'е']],
    one: { a: 'а', b: 'б', d: 'д', e: 'е', f: 'ф', g: 'г', h: 'ҳ', i: 'и', j: 'ж', k: 'к', l: 'л', m: 'м', n: 'н', o: 'о', p: 'п',
      q: 'қ', r: 'р', s: 'с', t: 'т', u: 'у', v: 'в', x: 'х', y: 'й', z: 'з', c: 'с', w: 'в', 'ʼ': 'ъ', '’': 'ъ' },
    initE: true, words: /[A-Za-z][A-Za-zʻʼ‘’']*/g,
  };
  // ponytail: capital I reads as и (Karakalpak writes ı's capital the same), so all-caps poster titles are hand-written in i18n.js
  const QQ = {
    multi: [['sh', 'ш'], ['ch', 'ч'], ['yo', 'ё'], ['yu', 'ю'], ['ya', 'я']],
    one: { a: 'а', á: 'ә', b: 'б', c: 'ц', d: 'д', e: 'е', f: 'ф', g: 'г', ǵ: 'ғ', h: 'ҳ', i: 'и', ı: 'ы', j: 'ж', k: 'к', l: 'л', m: 'м', n: 'н', ń: 'ң',
      o: 'о', ó: 'ө', p: 'п', q: 'қ', r: 'р', s: 'с', t: 'т', u: 'у', ú: 'ү', v: 'в', w: 'ў', x: 'х', y: 'й', z: 'з' },
    initE: false, words: /[A-Za-zÁáǴǵıŃńÓóÚú]+/g,
  };
  const isUp = (c) => c !== c.toLowerCase();
  const like = (cyr, src) => (isUp(src[0]) ? (src.length > 1 && isUp(src[1]) ? cyr.toUpperCase() : cyr[0].toUpperCase() + cyr.slice(1)) : cyr);

  function word(w, R) {
    let out = '';
    for (let i = 0; i < w.length;) {
      const hit = R.multi.find(([l]) => l === w.slice(i, i + l.length).toLowerCase());
      if (hit) { out += like(hit[1], w.slice(i, i + hit[0].length)); i += hit[0].length; continue; }
      const ch = w[i], l = ch.toLowerCase();
      if (R.initE && l === 'e' && i === 0) { out += like('э', ch); i++; continue; } // eʼtibor → эътибор
      out += R.one[l] ? like(R.one[l], ch) : ch;
      i++;
    }
    return out;
  }

  const make = (R) => (text) => {
    if (!text) return text;
    const kept = [];
    return String(text)
      .replace(KEEP, (m) => `\u0000${kept.push(m) - 1}\u0000`)
      .replace(R.words, (w) => word(w, R))
      .replace(/\u0000(\d+)\u0000/g, (_, i) => kept[+i]);
  };
  const toCyr = make(UZ), toCyrQQ = make(QQ);

  if (typeof module === 'object' && module.exports) module.exports = { toCyr, toCyrQQ };
  else { root.toCyr = toCyr; root.toCyrQQ = toCyrQQ; }
})(typeof self !== 'undefined' ? self : this);

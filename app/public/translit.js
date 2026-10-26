// Uzbek Latin → Uzbek Cyrillic. Shared by the server (survey texts) and both pages (UI strings).
// Rules follow the 1995 alphabet: oʻ→ў, gʻ→ғ, sh/ch digraphs, yo/yu/ya/ye, word-initial e→э, ʼ→ъ.
(function (root) {
  const KEEP = /\b(?:QR|NPS|PDF|Excel|SMS|iOS|Android|macOS|Windows|Linux)|\b(?:ID|A4)\b/g; // suffixes may follow: Excelga → Excelга
  const TRI = [['yoʻ', 'йў'], ["yo'", 'йў'], ['yo‘', 'йў']]; // "yoʻq" is й+ў, not ё+ʻ
  const DI = [['oʻ', 'ў'], ["o'", 'ў'], ['o‘', 'ў'], ['gʻ', 'ғ'], ["g'", 'ғ'], ['g‘', 'ғ'], ['sh', 'ш'], ['ch', 'ч'],
    ['yo', 'ё'], ['yu', 'ю'], ['ya', 'я'], ['ye', 'е']];
  const ONE = { a: 'а', b: 'б', d: 'д', e: 'е', f: 'ф', g: 'г', h: 'ҳ', i: 'и', j: 'ж', k: 'к', l: 'л', m: 'м', n: 'н', o: 'о', p: 'п',
    q: 'қ', r: 'р', s: 'с', t: 'т', u: 'у', v: 'в', x: 'х', y: 'й', z: 'з', c: 'с', w: 'в', 'ʼ': 'ъ', '’': 'ъ' };
  const isUp = (c) => c !== c.toLowerCase();
  const like = (cyr, src) => (isUp(src[0]) ? (src.length > 1 && isUp(src[1]) ? cyr.toUpperCase() : cyr[0].toUpperCase() + cyr.slice(1)) : cyr);

  function word(w) {
    let out = '';
    for (let i = 0; i < w.length;) {
      const hit = TRI.find(([l]) => l === w.slice(i, i + 3).toLowerCase()) || DI.find(([l]) => l === w.slice(i, i + 2).toLowerCase());
      if (hit) { out += like(hit[1], w.slice(i, i + hit[0].length)); i += hit[0].length; continue; }
      const ch = w[i], l = ch.toLowerCase();
      if (l === 'e' && i === 0) { out += like('э', ch); i++; continue; } // eʼtibor → эътибор
      out += ONE[l] ? like(ONE[l], ch) : ch;
      i++;
    }
    return out;
  }

  function toCyr(text) {
    if (!text) return text;
    const kept = [];
    return String(text)
      .replace(KEEP, (m) => `\u0000${kept.push(m) - 1}\u0000`)
      .replace(/[A-Za-z][A-Za-zʻʼ‘’']*/g, word)
      .replace(/\u0000(\d+)\u0000/g, (_, i) => kept[+i]);
  }

  if (typeof module === 'object' && module.exports) module.exports = { toCyr };
  else root.toCyr = toCyr;
})(typeof self !== 'undefined' ? self : this);

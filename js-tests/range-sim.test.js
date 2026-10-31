const nodeCrypto = require('node:crypto'); const assert = require('node:assert');
const cs = 1000, TAG = 16, HEADER = 16;     // small chunks to hit many edge cases
const size = 10 * cs + 377, key = nodeCrypto.randomBytes(32), prefix = nodeCrypto.randomBytes(8);
const plain = nodeCrypto.randomBytes(size);
const parts = [Buffer.concat([Buffer.from('PFL1'), Buffer.alloc(4), prefix])];
for (let i = 0, idx = 0; i < size; i += cs, idx++) {
  const nonce = Buffer.alloc(12); prefix.copy(nonce); nonce.writeUInt32BE(idx, 8);
  const c = nodeCrypto.createCipheriv('aes-256-gcm', key, nonce);
  parts.push(Buffer.concat([c.update(plain.subarray(i, i + cs)), c.final(), c.getAuthTag()]));
}
const enc = Buffer.concat(parts), encSize = enc.length;

function serve(start, end, cdnHonoursRange) {            // literal port of the PHP stream() logic
  const c0 = Math.floor(start / cs), c1 = Math.floor(end / cs), last = Math.floor((size - 1) / cs);
  const block = cs + TAG, encStart = HEADER + c0 * block, encEnd = Math.min(encSize - 1, HEADER + (c1 + 1) * block - 1);
  let body = cdnHonoursRange ? enc.subarray(encStart, encEnd + 1) : enc;   // 200 => whole file
  let pos = cdnHonoursRange ? 0 : encStart;                                 // discard()
  let skip = start - c0 * cs, remaining = end - start + 1, out = [];
  for (let i = c0; i <= c1 && remaining > 0; i++) {
    const plainLen = i === last ? size - i * cs : cs;
    const raw = body.subarray(pos, pos + plainLen + TAG); pos += plainLen + TAG;
    assert.strictEqual(raw.length, plainLen + TAG);
    const nonce = Buffer.alloc(12); prefix.copy(nonce); nonce.writeUInt32BE(i, 8);
    const d = nodeCrypto.createDecipheriv('aes-256-gcm', key, nonce); d.setAuthTag(raw.subarray(-TAG));
    let p = Buffer.concat([d.update(raw.subarray(0, -TAG)), d.final()]);
    if (skip > 0) { p = p.subarray(skip); skip = 0; }
    if (p.length > remaining) p = p.subarray(0, remaining);
    out.push(p); remaining -= p.length;
  }
  return Buffer.concat(out);
}
let n = 0;
for (const honour of [true, false]) {
  for (const [s, e] of [[0, size - 1], [0, 0], [size - 1, size - 1], [cs - 1, cs], [cs, 2 * cs - 1], [5, 3 * cs + 9], [size - 3, size - 1], [10 * cs, size - 1]]) {
    assert.ok(serve(s, e, honour).equals(plain.subarray(s, e + 1)), `range ${s}-${e} honour=${honour}`); n++;
  }
  for (let k = 0; k < 300; k++) {
    const s = Math.floor(Math.random() * size), e = s + Math.floor(Math.random() * (size - s));
    assert.ok(serve(s, e, honour).equals(plain.subarray(s, e + 1))); n++;
  }
}
console.log('range algorithm: ' + n + ' ranges OK (CDN honouring Range and ignoring it)');

// Mirrors AdminUploadController: pieces are encrypted independently on arrival, then joined with the header.
// Checks that the result is exactly what the reader (public/js/chunk-decrypt.js) expects.
const nodeCrypto = require('node:crypto'); const assert = require('node:assert');
const { decryptChunked } = require('../public/js/chunk-decrypt.js');
const CHUNK = 262144, PIECE = 3 * 1024 * 1024, TAG = 16, HEADER = 16;
assert.strictEqual(PIECE % CHUNK, 0);

function encryptPiece(plain, key, prefix, firstIndex) {                  // = ChunkCipher::encryptPiece
  const parts = [];
  for (let off = 0, i = firstIndex; off < plain.length; off += CHUNK, i++) {
    const nonce = Buffer.alloc(12); prefix.copy(nonce, 0); nonce.writeUInt32BE(i, 8);
    const c = nodeCrypto.createCipheriv('aes-256-gcm', key, nonce, { authTagLength: 16 });
    parts.push(Buffer.concat([c.update(plain.subarray(off, off + CHUNK)), c.final(), c.getAuthTag()]));
  }
  return Buffer.concat(parts);
}

(async () => {
  const key = nodeCrypto.randomBytes(32), prefix = nodeCrypto.randomBytes(8);
  const sizes = [1, CHUNK, PIECE - 1, PIECE, PIECE + 1, 2 * PIECE + 12345, 5 * PIECE, 50 * 1024 * 1024];
  for (const size of sizes) {
    const plain = nodeCrypto.randomBytes(size);
    const pieces = Math.ceil(size / PIECE), staged = new Map();
    for (const i of [...Array(pieces).keys()].sort(() => Math.random() - 0.5)) {        // pieces may arrive in any order
      const slice = plain.subarray(i * PIECE, Math.min(size, (i + 1) * PIECE));
      const expected = i === pieces - 1 ? size - i * PIECE : PIECE;                       // server-side size check
      assert.strictEqual(slice.length, expected);
      staged.set(i, encryptPiece(slice, key, prefix, (i * PIECE) / CHUNK).toString('base64'));
    }
    const head = Buffer.alloc(16); head.write('PFL1', 0, 'latin1'); head.writeUInt32BE(CHUNK, 4); prefix.copy(head, 8);
    const file = Buffer.concat([head, ...[...Array(pieces).keys()].map((i) => Buffer.from(staged.get(i), 'base64'))]);
    assert.strictEqual(file.length, HEADER + Math.ceil(size / CHUNK) * TAG + size);       // = $encSize in finish()
    const out = await decryptChunked(new Uint8Array(file), { key: key.toString('base64'), nonce_prefix: prefix.toString('base64'), chunk_size: CHUNK, plain_size: size });
    assert.ok(Buffer.from(out).equals(plain), 'mismatch at size ' + size);
  }
  console.log('upload assembly: ' + sizes.length + ' sizes OK (incl. 50 MB, shuffled arrival, partial last piece)');
})().catch((e) => { console.error(e); process.exit(1); });

// Reference encryptor (mirrors ChunkCipher::encryptFile in PHP) + test of public/js/chunk-decrypt.js
const nodeCrypto = require('node:crypto');
const assert = require('node:assert');
const { decryptChunked } = require('../public/js/chunk-decrypt.js');

function encrypt(plain, key, cs) {
  const prefix = nodeCrypto.randomBytes(8);
  const head = Buffer.alloc(16); head.write('PFL1', 0, 'latin1'); head.writeUInt32BE(cs, 4); prefix.copy(head, 8);
  const parts = [head];
  for (let i = 0, idx = 0; i < plain.length; i += cs, idx++) {
    const nonce = Buffer.alloc(12); prefix.copy(nonce, 0); nonce.writeUInt32BE(idx, 8);
    const c = nodeCrypto.createCipheriv('aes-256-gcm', key, nonce, { authTagLength: 16 });
    parts.push(Buffer.concat([c.update(plain.subarray(i, i + cs)), c.final(), c.getAuthTag()]));
  }
  return { file: Buffer.concat(parts), prefix };
}

(async () => {
  const cs = 262144;
  const key = nodeCrypto.randomBytes(32);
  const sizes = [1, 5, cs - 1, cs, cs + 1, 3 * cs, 3 * cs + 77, 5_000_001];
  for (const size of sizes) {
    const plain = nodeCrypto.randomBytes(size);
    const { file, prefix } = encrypt(plain, key, cs);
    assert.strictEqual(file.length, 16 + Math.ceil(size / cs) * 16 + size); // matches enc_size formula in PHP
    const m = { key: key.toString('base64'), nonce_prefix: prefix.toString('base64'), chunk_size: cs, plain_size: size };
    const out = await decryptChunked(new Uint8Array(file), m);
    assert.ok(Buffer.from(out).equals(plain), 'roundtrip mismatch at size ' + size);
  }
  // tampering must be rejected
  const plain = nodeCrypto.randomBytes(cs * 2 + 10);
  const { file, prefix } = encrypt(plain, key, cs);
  const m = { key: key.toString('base64'), nonce_prefix: prefix.toString('base64'), chunk_size: cs, plain_size: plain.length };
  const bad = new Uint8Array(file); bad[16 + cs + 16 + 5] ^= 1;
  await assert.rejects(decryptChunked(bad, m));
  // truncation must be rejected
  await assert.rejects(decryptChunked(new Uint8Array(file).subarray(0, file.length - 20), m));
  // wrong key must be rejected
  await assert.rejects(decryptChunked(new Uint8Array(file), { ...m, key: nodeCrypto.randomBytes(32).toString('base64') }));
  console.log('chunk-decrypt: all tests passed (' + sizes.length + ' sizes + tamper/truncate/wrong-key)');
})().catch((e) => { console.error(e); process.exit(1); });

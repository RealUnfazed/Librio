/* Decrypts the "PFL1" chunked AES-256-GCM format (see app/Services/ChunkCipher.php). */
(function (root) {
  const HEADER = 16, TAG = 16;

  function b64(s) {
    const bin = atob(s), u = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) u[i] = bin.charCodeAt(i);
    return u;
  }

  /**
   * cipher: Uint8Array of the whole encrypted file
   * m: { key, nonce_prefix, chunk_size, plain_size }   (base64 key + prefix)
   * returns Uint8Array with the plaintext PDF. Throws if anything fails authentication.
   */
  async function decryptChunked(cipher, m, onProgress) {
    if (String.fromCharCode(cipher[0], cipher[1], cipher[2], cipher[3]) !== 'PFL1') {
      throw new Error('Unexpected file format');
    }
    const key = await crypto.subtle.importKey('raw', b64(m.key), 'AES-GCM', false, ['decrypt']);
    const cs = m.chunk_size, total = m.plain_size;
    const n = Math.ceil(total / cs);
    const out = new Uint8Array(total);
    const iv = new Uint8Array(12);
    iv.set(b64(m.nonce_prefix), 0);
    const dv = new DataView(iv.buffer);

    for (let i = 0; i < n; i++) {
      dv.setUint32(8, i, false);
      const plainLen = Math.min(cs, total - i * cs);
      const start = HEADER + i * (cs + TAG);
      const block = cipher.subarray(start, start + plainLen + TAG);
      if (block.length !== plainLen + TAG) throw new Error('File is truncated');
      const pt = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: iv.slice(), tagLength: 128 }, key, block);
      out.set(new Uint8Array(pt), i * cs);
      if (onProgress && (i % 8 === 0 || i === n - 1)) onProgress((i + 1) / n);
    }
    return out;
  }

  root.decryptChunked = decryptChunked;
  if (typeof module !== 'undefined' && module.exports) module.exports = { decryptChunked };
})(typeof window !== 'undefined' ? window : globalThis);

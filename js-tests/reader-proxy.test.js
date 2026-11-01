// Runs the REAL proxy-delivery code from public/js/reader.js (extracted) against a fake server + fake PDF.js.
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert'), crypto = require('node:crypto');
const src = fs.readFileSync(__dirname + '/../public/js/reader.js', 'utf8');
const code = src.slice(src.indexOf('const PIECE'), src.indexOf('async function openDocument'));

const TOTAL = 7 * 1024 * 1024 + 12345, plain = crypto.randomBytes(TOTAL), calls = [];
const fakeFetch = async (url, opts) => {                                     // behaves like ReaderController::stream
  const m = /^bytes=(\d+)-(\d+)$/.exec(opts.headers.Range); assert.ok(m, 'bad Range header');
  const b = +m[1], e = Math.min(+m[2], TOTAL - 1); calls.push([b, e - b + 1]);
  assert.ok(e - b + 1 <= 1024 * 1024, 'response bigger than 1 MiB would hit Vercel limits');
  const body = plain.subarray(b, e + 1);
  return { status: 206, arrayBuffer: async () => body.buffer.slice(body.byteOffset, body.byteOffset + body.length) };
};
let docArgs; const received = [];
const sandbox = {
  fetch: fakeFetch, setTimeout, console, Uint8Array, Error, Math, Promise,
  setLoad: () => {},
  pdfjsLib: {
    PDFDataRangeTransport: class { constructor(length, initial) { this.length = length; this.initial = initial; } onDataRange(b, buf) { received.push([b, Buffer.from(buf)]); } },
    getDocument: (o) => { docArgs = o; return { promise: Promise.resolve('doc') }; },
  },
};
vm.createContext(sandbox);
vm.runInContext(code + '\nthis.openStreaming = openStreaming; this.openWhole = openWhole;', sandbox);

(async () => {
  // 1) streaming: first 1 MiB preloaded, rest on demand in <= 1 MiB pieces aligned to PDF.js chunks
  const m = { url: '/stream?t=x', plain_size: TOTAL };
  assert.strictEqual(await sandbox.openStreaming(m), 'doc');
  assert.strictEqual(docArgs.length, TOTAL); assert.strictEqual(docArgs.rangeChunkSize, 262144);
  const t = docArgs.range;
  assert.ok(Buffer.from(t.initial).equals(plain.subarray(0, 1024 * 1024)), 'initial data wrong');
  t.requestDataRange(262144 * 5, 262144 * 5 + 3 * 1024 * 1024 + 262144);      // an awkward 3.25 MiB request
  t.requestDataRange(TOTAL - 70000 - ((TOTAL - 70000) % 262144), TOTAL);       // tail of the file (xref lives there)
  await new Promise((r) => setTimeout(r, 50));
  for (const [b, buf] of received) assert.ok(buf.equals(plain.subarray(b, b + buf.length)), 'piece at ' + b + ' corrupted');
  const covered = received.reduce((n, [, buf]) => n + buf.length, 0);
  assert.strictEqual(covered, 3 * 1024 * 1024 + 262144 + (TOTAL - (TOTAL - 70000 - ((TOTAL - 70000) % 262144))));
  assert.ok(received.every(([b]) => b % 262144 === 0), 'pieces must start on PDF.js chunk boundaries');

  // 2) whole-file fallback reassembles exactly
  calls.length = 0;
  await sandbox.openWhole(m);
  assert.ok(Buffer.from(docArgs.data).equals(plain), 'whole-file fallback differs');
  assert.strictEqual(calls.length, Math.ceil(TOTAL / (1024 * 1024)));
  console.log('reader proxy: streaming ranges + whole-file fallback OK (' + calls.length + ' requests for ' + (TOTAL / 1048576).toFixed(1) + ' MB, all <= 1 MiB)');
})().catch((e) => { console.error(e); process.exit(1); });

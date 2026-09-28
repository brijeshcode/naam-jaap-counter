// Generates solid-color PWA icons (no image deps). Run: node scripts/generate-icons.mjs
import { deflateSync, crc32 } from 'node:zlib';
import { writeFileSync } from 'node:fs';

function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(body) >>> 0);
  return Buffer.concat([len, body, crc]);
}
// Mala-counter icon: orange field, a ring of 24 cream beads, cream centre disc.
const ORANGE = [234, 88, 12], CREAM = [255, 237, 213], WHITE = [255, 248, 240];
function png(size) {
  const sig = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(size, 0); ihdr.writeUInt32BE(size, 4);
  ihdr[8] = 8; ihdr[9] = 2;                       // 8-bit truecolor RGB
  const c = size / 2, ringR = size * 0.36, beadR = size * 0.045, centreR = size * 0.22;
  // 24 bead centres around the ring
  const beads = [];
  for (let i = 0; i < 24; i++) {
    const a = (i / 24) * 2 * Math.PI - Math.PI / 2;
    beads.push([c + Math.cos(a) * ringR, c + Math.sin(a) * ringR]);
  }
  const raw = Buffer.alloc(size * (1 + size * 3));
  for (let y = 0; y < size; y++) {
    const rowStart = y * (1 + size * 3);
    for (let x = 0; x < size; x++) {
      let col = ORANGE;
      const dc = Math.hypot(x - c, y - c);
      if (dc <= centreR) col = CREAM;
      else {
        for (const [bx, by] of beads) {
          if (Math.hypot(x - bx, y - by) <= beadR) { col = WHITE; break; }
        }
      }
      const p = rowStart + 1 + x * 3;
      raw[p] = col[0]; raw[p + 1] = col[1]; raw[p + 2] = col[2];
    }
  }
  const idat = deflateSync(raw);
  return Buffer.concat([sig, chunk('IHDR', ihdr), chunk('IDAT', idat), chunk('IEND', Buffer.alloc(0))]);
}
writeFileSync('icon-192.png', png(192));
writeFileSync('icon-512.png', png(512));
console.log('icons written');

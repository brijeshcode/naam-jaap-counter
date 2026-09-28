const CACHE = 'nj-v2';
const LOCAL = ['index.php', 'util.js', 'i18n.js'];   // same-origin: must cache
const CDN = [
  'https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js',
  'https://cdn.jsdelivr.net/npm/chart.js@4',
  'https://cdn.tailwindcss.com'
];
self.addEventListener('install', e => e.waitUntil((async () => {
  const c = await caches.open(CACHE);
  await c.addAll(LOCAL);                              // atomic, reliable
  await Promise.allSettled(CDN.map(u => c.add(u)));   // best-effort; never fails install
  await self.skipWaiting();
})()));
self.addEventListener('activate', e => e.waitUntil((async () => {
  const ks = await caches.keys();
  await Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)));
  await self.clients.claim();
})()));
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.pathname.endsWith('api.php')) return;  // never cache API
  e.respondWith((async () => {
    const cached = await caches.match(e.request);
    if (cached) return cached;
    try {
      const res = await fetch(e.request);
      if (res && res.status === 200) {
        const copy = res.clone();
        caches.open(CACHE).then(c => c.put(e.request, copy));
      }
      return res;
    } catch (err) {
      return cached || Response.error();
    }
  })());
});

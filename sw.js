// TREND STORE — service worker (oflayn ishlash uchun)
const V='ts-v8',SHELL=['index.html','style.css','app.js','manifest.webmanifest','img/logo.png','img/icon-192.png'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(V).then(c=>c.addAll(SHELL)).then(()=>self.skipWaiting()).catch(()=>self.skipWaiting()))});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==V).map(x=>caches.delete(x)))).then(()=>self.clients.claim()))});
self.addEventListener('fetch',e=>{
  const r=e.request,u=new URL(r.url);
  if(r.method!=='GET'||u.origin!==location.origin||u.pathname.endsWith('/api.php')||/(payme|click|setup)\.php$/.test(u.pathname))return; // API va to'lov — doim tarmoqdan
  if(r.destination==='image'){ // rasmlar: avval kesh
    e.respondWith(caches.match(r).then(h=>h||fetch(r).then(n=>{if(n.ok){const c=n.clone();caches.open(V).then(x=>x.put(r,c))}return n})));return;
  }
  e.respondWith(fetch(r).then(n=>{if(n.ok){const c=n.clone();caches.open(V).then(x=>x.put(r,c))}return n}).catch(()=>caches.match(r).then(h=>h||(r.mode==='navigate'?caches.match('index.html'):Response.error()))));
});

const CACHE='scratchgard-shell-v2.1';
const SHELL=['/assets/app.css','/assets/icon-192.png','/assets/icon-512.png','/manifest.webmanifest'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL)));self.skipWaiting();});
self.addEventListener('activate',event=>event.waitUntil(Promise.all([self.clients.claim(),caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k))))])));
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET') return;
  const url=new URL(event.request.url);
  if(url.origin!==location.origin) return;
  if(url.pathname.startsWith('/api/')||url.pathname.startsWith('/evidence/')) return;
  event.respondWith(fetch(event.request).then(response=>{const copy=response.clone();if(response.ok && ['style','script','image','font'].includes(event.request.destination))caches.open(CACHE).then(c=>c.put(event.request,copy));return response;}).catch(()=>caches.match(event.request)));
});

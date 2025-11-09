// assets/pwa.js - PWA + Theme + A2HS button
(function(){
  // Theme persistence
  const root = document.documentElement;
  const saved = localStorage.getItem('ff-theme');
  if(saved){ root.setAttribute('data-theme', saved); }
  document.addEventListener('click', e=>{
    const t = e.target.closest('[data-action="toggle-theme"]');
    if(!t) return;
    const next = (root.getAttribute('data-theme')==='dark')?'light':'dark';
    root.setAttribute('data-theme', next);
    localStorage.setItem('ff-theme', next);
  });

  // PWA register
  const cacheToken = typeof window.__CACHE_BUSTER__ === 'string' && window.__CACHE_BUSTER__.length
    ? window.__CACHE_BUSTER__
    : '';
  function withCacheKey(url) {
    if (!cacheToken) return url;
    if (/[?&](?:v|cb)=/i.test(url)) return url;
    const glue = url.includes('?') ? '&' : '?';
    return `${url}${glue}cb=${encodeURIComponent(cacheToken)}`;
  }

  if('serviceWorker' in navigator){
    const candidates = Array.isArray(window.__SW_URLS__) && window.__SW_URLS__.length
      ? window.__SW_URLS__
      : [window.__SW_URL__ || './sw.js'];
    const swUrl = withCacheKey(candidates[0]);
    navigator.serviceWorker.register(swUrl).catch(()=>{});
  }

  // A2HS
  let deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', (e)=>{
    e.preventDefault();
    deferredPrompt = e;
    const btn = document.querySelector('[data-action="install-app"]');
    if(btn){ btn.style.display = 'inline-flex'; }
  });
  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-action="install-app"]');
    if(!btn || !deferredPrompt) return;
    btn.disabled = true;
    deferredPrompt.prompt();
    try { await deferredPrompt.userChoice; } finally {
      deferredPrompt = null; btn.disabled = false; btn.style.display='none';
    }
  });
})();

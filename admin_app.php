<?php
require __DIR__.'/bootstrap.php';

$manifestHref = function_exists('cache_busted_url') ? '/' . ltrim(cache_busted_url('manifest-admin.webmanifest'), '/') : '/manifest-admin.webmanifest';
$iconAdmin = function_exists('asset_url') ? '/' . ltrim(asset_url('assets/icons/admin-192.png'), '/') : '/assets/icons/admin-192.png';
$swUrl = function_exists('service_worker_url') ? service_worker_url() : '/sw.js';
$adminAppTitle = setting_get('admin_app_title', 'Get Power Research Admin');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, max-age=0, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title><?= htmlspecialchars($adminAppTitle); ?> (App)</title>

  <link rel="manifest" href="<?= htmlspecialchars($manifestHref); ?>">
  <meta name="theme-color" content="#0F172A">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($adminAppTitle); ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($iconAdmin); ?>">

  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif;margin:0;
         min-height:100vh;display:flex;background:#f6f8fb;color:#0b1320}
    .wrap{margin:auto;padding:20px;max-width:480px;width:100%}
    .card{background:#fff;border-radius:16px;box-shadow:0 10px 28px rgba(2,6,23,.08);
          padding:22px;text-align:center}
    .logo{width:76px;height:76px;border-radius:18px;object-fit:cover;margin-bottom:8px}
    h1{font-size:22px;margin:8px 0}
    p{color:#475569;margin:0 0 16px}
    button{background:#0ea5e9;color:#081018;border:0;border-radius:12px;padding:12px 16px;
           font-weight:800;cursor:pointer;width:100%}
    .hint{font-size:13px;color:#64748b;margin-top:10px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <img class="logo" src="<?= htmlspecialchars($iconAdmin); ?>" alt="Admin">
      <h1><?= htmlspecialchars($adminAppTitle); ?></h1>
      <p>Instale o app do painel administrativo para acesso rápido.</p>
      <button id="installBtn" style="display:none">📲 Instalar App Admin</button>
      <div class="hint" id="androidHint" style="display:none">
        Se o botão não aparecer, abra o menu ⋮ do Chrome e toque em <b>Instalar app</b>.
      </div>
      <div class="hint" id="iosTip" style="display:none">
        No iPhone/iPad (Safari): toque em <b>Compartilhar</b> → <b>Adicionar à Tela de Início</b>.
      </div>
      <div class="hint" style="margin-top:14px">Atalho abrirá: <b>/admin.php</b></div>
    </div>
  </div>

  <script>
    const swUrl = <?= json_encode($swUrl); ?>;
    const ua = navigator.userAgent;
    const isIOS = /iPad|iPhone|iPod/.test(ua) && /^((?!chrome|android).)*safari/i.test(ua);
    const installBtn = document.getElementById('installBtn');
    const androidHint = document.getElementById('androidHint');
    const iosTip = document.getElementById('iosTip');

    if (isIOS) iosTip.style.display = 'block';

    let deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredPrompt = e;
      installBtn.style.display = 'inline-block';
      androidHint.style.display = 'block';
    });

    installBtn.addEventListener('click', async () => {
      if (!deferredPrompt) return;
      installBtn.disabled = true;
      deferredPrompt.prompt();
      try { await deferredPrompt.userChoice; } catch (_) {}
    });

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistration().then(r=>{
        if (!r) navigator.serviceWorker.register(swUrl).catch(()=>{});
      });
    }
  </script>
</body>
</html>

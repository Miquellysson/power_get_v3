<?php
// bootstrap.php — No-cache sólido para LiteSpeed/Cloudflare + sessão estável
// Inclua este arquivo no topo de index.php e admin.php

require_once __DIR__.'/lib/cache_helpers.php';

if (!headers_sent()) {
  // Bloqueia cache agressivo de proxy/CDN e navegador compartilhado
  header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
  header('Pragma: no-cache');
  header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
  header('Surrogate-Control: no-store');
  // Sinais específicos para LiteSpeed/Hostinger
  header('X-LiteSpeed-Cache-Control: no-cache');
  header('X-LiteSpeed-Tag: nocache');
  // Evita buffering agressivo em proxies
  header('X-Accel-Buffering: no');
}

// Desliga LSCache por constante (quando suportado)
if (!defined('LSCACHE_NO_CACHE')) {
  define('LSCACHE_NO_CACHE', true);
}

// Sessão estável (nome fixo + SameSite)
// Evita que o token CSRF “perca” no meio do caminho
if (session_status() !== PHP_SESSION_ACTIVE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
  // Nome fixo evita colisão com outras apps no mesmo domínio
  $cookieDomain = $_SERVER['HTTP_HOST'] ?? '';
  if (strpos($cookieDomain, ':') !== false) {
    $cookieDomain = explode(':', $cookieDomain)[0];
  }
  if ($cookieDomain === '' || $cookieDomain === 'localhost' || $cookieDomain === '127.0.0.1' || strpos($cookieDomain, '.') === false) {
    $cookieDomain = null;
  }
  session_name('GetPowerSESSID');
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $cookieDomain,
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

// (Funções de cache-busting são carregadas via lib/cache_helpers.php)

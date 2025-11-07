<?php
// bootstrap.php — No-cache sólido para LiteSpeed/Cloudflare + sessão estável
// Inclua este arquivo no topo de index.php e admin.php

if (!headers_sent()) {
  // Bloqueia cache agressivo de proxy/CDN e navegador
  header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
  // Sinal específico do LiteSpeed para não cachear esta resposta
  header('X-LiteSpeed-Cache-Control: no-cache');
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

// Helper global para cache-busting de assets (css/js/img)
// Usa filemtime local e fallback para timestamp
if (!function_exists('cache_busted_url')) {
  function cache_busted_url(string $path): string {
    $trimmed = trim($path);
    if ($trimmed === '' || preg_match('#^(?:https?:|data:|mailto:|tel:)#i', $trimmed)) {
      return $trimmed;
    }
    if (preg_match('/[?&]v=\d+/i', $trimmed)) {
      return $trimmed;
    }
    $absolute = __DIR__ . '/' . ltrim($trimmed, '/');
    $version = is_file($absolute) ? filemtime($absolute) : time();
    $separator = strpos($trimmed, '?') !== false ? '&' : '?';
    return $trimmed . $separator . 'v=' . $version;
  }
}

if (!function_exists('asset_url')) {
  function asset_url($rel){
    $clean = ltrim((string)$rel, '/');
    if ($clean === '') {
      return '';
    }
    if (function_exists('cache_busted_url')) {
      return cache_busted_url($clean);
    }
    $abs = __DIR__ . '/' . $clean;
    $v = is_file($abs) ? filemtime($abs) : time();
    $sep = (strpos($clean,'?')!==false) ? '&' : '?';
    return $clean . $sep . 'v=' . $v;
  }
}

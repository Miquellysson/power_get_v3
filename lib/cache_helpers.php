<?php
/**
 * cache_helpers.php — funções compartilhadas para cache-busting e URLs de assets.
 * Este arquivo é incluído pelo bootstrap e pelos utilitários principais.
 */

if (!function_exists('app_base_path')) {
    function app_base_path(string $append = ''): string {
        $base = dirname(__DIR__);
        if ($append !== '') {
            $append = '/' . ltrim($append, '/');
            return rtrim($base, '/\\') . $append;
        }
        return rtrim($base, '/\\');
    }
}

if (!function_exists('cache_bust_token_file')) {
    function cache_bust_token_file(): string {
        return app_base_path('storage/cache_bust_token.txt');
    }
}

if (!function_exists('cache_bust_env_token')) {
    function cache_bust_env_token(): string {
        $env = getenv('FF_CACHE_BUST_TOKEN');
        if ($env === false) {
            return '';
        }
        $env = trim((string)$env);
        return $env;
    }
}

if (!function_exists('cache_bust_sanitize_token')) {
    function cache_bust_sanitize_token(string $token): string {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        $token = preg_replace('/[^a-z0-9\-\._]/i', '', $token);
        return $token ?: '';
    }
}

if (!function_exists('cache_bust_current_token')) {
    function cache_bust_current_token(): string {
        static $token = null;
        if ($token !== null) {
            return $token;
        }
        $token = cache_bust_env_token();
        if ($token === '') {
            $file = cache_bust_token_file();
            if (is_file($file)) {
                $token = trim((string)@file_get_contents($file));
            }
        }
        $token = cache_bust_sanitize_token((string)$token);
        if ($token === '') {
            $token = date('Ymd');
        }
        return $token;
    }
}

if (!function_exists('cache_bust_regenerate_token')) {
    function cache_bust_regenerate_token(?string $custom = null): string {
        $token = $custom !== null ? (string)$custom : (date('YmdHis') . '-' . bin2hex(random_bytes(4)));
        $token = cache_bust_sanitize_token($token);
        if ($token === '') {
            $token = date('YmdHis');
        }
        $file = cache_bust_token_file();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, $token);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        $GLOBALS['__CACHE_BUST_TOKEN'] = $token;
        return $token;
    }
}

if (!function_exists('cache_busted_url')) {
    function cache_busted_url(string $path): string {
        $original = trim($path);
        if ($original === '' || preg_match('#^(?:https?:|data:|mailto:|tel:)#i', $original)) {
            return $original;
        }

        $fragment = '';
        $fragmentPos = strpos($original, '#');
        if ($fragmentPos !== false) {
            $fragment = substr($original, $fragmentPos);
            $original = substr($original, 0, $fragmentPos);
        }

        $query = '';
        $queryPos = strpos($original, '?');
        if ($queryPos !== false) {
            $query = substr($original, $queryPos + 1);
            $pathOnly = substr($original, 0, $queryPos);
        } else {
            $pathOnly = $original;
        }

        $normalizedFs = $pathOnly;
        $normalizedFs = preg_replace('#^(\./)+#', '', $normalizedFs);
        while (strpos($normalizedFs, '../') === 0) {
            $normalizedFs = substr($normalizedFs, 3);
        }
        $normalizedFs = ltrim($normalizedFs, '/');

        $absolute = app_base_path($normalizedFs);
        $mtime = @filemtime($absolute);
        $versionPieces = [];
        $token = cache_bust_current_token();
        if ($token !== '') {
            $versionPieces[] = $token;
        }
        if ($mtime) {
            $versionPieces[] = (string)$mtime;
        } else {
            $versionPieces[] = (string)time();
        }
        $version = implode('-', $versionPieces);

        $existingQuery = $query;
        $hasVersionParam = $existingQuery !== '' && preg_match('/(?:^|&)(?:v|cb)=/i', $existingQuery);
        $paramName = $hasVersionParam ? 'cb' : 'v';
        $glue = $existingQuery === '' ? '?' : '&';
        $rebuilt = $pathOnly;
        if ($existingQuery !== '') {
            $rebuilt .= '?' . $existingQuery;
        }
        $rebuilt .= $glue . $paramName . '=' . rawurlencode($version);
        $rebuilt .= $fragment;
        return $rebuilt;
    }
}

if (!function_exists('asset_url')) {
    function asset_url($rel) {
        $clean = ltrim((string)$rel, '/');
        if ($clean === '') {
            return '';
        }
        return cache_busted_url($clean);
    }
}

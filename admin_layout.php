<?php
// admin_layout.php — layout unificado (claro/escuro), sem dependências além de Tailwind CDN + nossos assets
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('sanitize_html')) {
    function sanitize_html($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('setting_get')) {
    function setting_get($k, $d = null) {
        return $d;
    }
}
if (!function_exists('cfg')) {
    function cfg() {
        return [];
    }
}

function admin_header($title = 'Admin')
{
    $cfg = function_exists('cfg') ? cfg() : [];
    $storeName = setting_get('store_name', $cfg['store']['name'] ?? 'Get Power Research');
    $systemVersion = '3.0 — criação Mike Lins';
    $adminRole = function_exists('current_admin_role') ? current_admin_role() : 'admin';
    $canViewFinance = in_array($adminRole, ['admin', 'super_admin'], true);
    $themeColor = setting_get('theme_color', '#2060C8');
    $logoPath = function_exists('find_logo_path') ? find_logo_path() : null;
    $logoUrl = null;
    if ($logoPath) {
        $versioned = function_exists('cache_busted_url') ? cache_busted_url(ltrim($logoPath, '/')) : $logoPath;
        $logoUrl = '/' . ltrim($versioned, '/');
    }

    $defaultPalette = [
        'DEFAULT' => '#2060C8',
        '50'      => '#EEF4FF',
        '100'     => '#DCE7FF',
        '200'     => '#B9D0FF',
        '300'     => '#96B8FF',
        '400'     => '#6D9CFF',
        '500'     => '#4883F0',
        '600'     => '#2060C8',
        '700'     => '#1C54B0',
        '800'     => '#17448E',
        '900'     => '#10326A',
    ];
    $generatedPalette = function_exists('generate_brand_palette') ? generate_brand_palette($themeColor) : [];
    $normalizedPalette = [];
    if (is_array($generatedPalette)) {
        foreach ($generatedPalette as $k => $v) {
            $normalizedPalette[(string)$k] = $v;
        }
    }
    $brandPalette = array_replace($defaultPalette, $normalizedPalette);
    $accentColor = function_exists('adjust_color_brightness') ? adjust_color_brightness($themeColor, 0.35) : '#4F88FF';
    $tailwindBrandJson = json_encode($brandPalette, JSON_UNESCAPED_SLASHES);
    $accentPaletteJson = json_encode(['400' => $accentColor], JSON_UNESCAPED_SLASHES);
    $adminThemeColor = $brandPalette['600'] ?? $brandPalette['DEFAULT'];

    $adminInfo = function_exists('current_admin') ? current_admin() : null;
    $adminName = trim((string)($adminInfo['name'] ?? ($_SESSION['admin_name'] ?? 'Administrador')));
    if ($adminName === '') {
        $adminName = 'Administrador';
    }
    $adminInitials = '';
    foreach (preg_split('/\s+/', $adminName) as $chunk) {
        if ($chunk !== '') {
            $adminInitials .= mb_strtoupper(mb_substr($chunk, 0, 1));
        }
    }
    $adminInitials = $adminInitials !== '' ? mb_substr($adminInitials, 0, 2) : 'AL';

    $timezoneSetting = setting_get('store_timezone', 'America/Sao_Paulo');
    try {
        $tz = new DateTimeZone($timezoneSetting);
    } catch (Throwable $e) {
        $tz = new DateTimeZone('UTC');
    }
    $now = new DateTime('now', $tz);
    $currentTimestamp = $now->format('d/m/Y H:i');
    $timezoneLabel = str_replace('_', ' ', $tz->getName());

    $navItems = [
        ['href' => 'dashboard.php',  'label' => 'Dashboard',     'icon' => 'fa-solid fa-gauge-high'],
        $canViewFinance ? ['href' => 'financeiro.php', 'label' => 'Financeiro', 'icon' => 'fa-solid fa-coins'] : null,
        ['href' => 'reports.php',    'label' => 'Relatórios',    'icon' => 'fa-solid fa-chart-line'],
        ['href' => 'orders.php',     'label' => 'Pedidos',       'icon' => 'fa-solid fa-receipt'],
        ['href' => 'products.php',   'label' => 'Produtos',      'icon' => 'fa-solid fa-pills'],
        ['href' => 'categories.php', 'label' => 'Categorias',    'icon' => 'fa-solid fa-tags'],
        ['href' => 'customers.php',  'label' => 'Clientes',      'icon' => 'fa-solid fa-users'],
        ['href' => 'users.php',      'label' => 'Usuários',      'icon' => 'fa-solid fa-user-shield'],
        ['href' => 'settings.php',   'label' => 'Configurações', 'icon' => 'fa-solid fa-sliders'],
    ];
    if (function_exists('is_super_admin') && is_super_admin()) {
        $navItems[] = ['href' => 'backup.php', 'label' => 'Backups', 'icon' => 'fa-solid fa-cloud-arrow-down'];
    }
    $navItems = array_values(array_filter($navItems));

    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    $css = function_exists('asset_url') ? asset_url('assets/admin.css') : 'assets/admin.css';
    $jsThemeBootstrap = "(function(){try{var stored=localStorage.getItem('admin.theme');if(stored){document.documentElement.setAttribute('data-theme', stored);}else{document.documentElement.setAttribute('data-theme','light');}}catch(e){document.documentElement.setAttribute('data-theme','light');}})();";
    $cacheBusterToken = function_exists('cache_bust_current_token') ? cache_bust_current_token() : (string)time();
    $adminManifest = function_exists('cache_busted_url') ? '/' . ltrim(cache_busted_url('manifest-admin.webmanifest'), '/') : '/manifest-admin.webmanifest';

    echo '<!doctype html><html lang="pt-br" data-theme="light"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, max-age=0, must-revalidate">';
    echo '<meta http-equiv="Pragma" content="no-cache">';
    echo '<meta http-equiv="Expires" content="0">';
    echo '<meta name="theme-color" content="' . sanitize_html($adminThemeColor) . '">';
    echo '<link rel="manifest" href="' . $adminManifest . '">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
    echo '<script>' . $jsThemeBootstrap . '</script>';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo "<script>tailwind.config={theme:{extend:{colors:{brand:$tailwindBrandJson,accent:$accentPaletteJson}}}};</script>";
    echo '<link rel="stylesheet" href="' . $css . '">';
    echo '<title>' . sanitize_html($title) . ' — Admin</title>';
    echo '<script>window.__CACHE_BUSTER__ = ' . json_encode($cacheBusterToken) . ';</script>';
    echo '<style>:root{--brand:' . $brandPalette['DEFAULT'] . ';--brand-50:' . $brandPalette['50'] . ';--brand-400:' . $brandPalette['400'] . ';--brand-600:' . $brandPalette['600'] . ';--brand-700:' . $brandPalette['700'] . ';--accent:' . $accentColor . ';}</style>';
    echo '</head><body class="admin-shell">';
    echo '<a class="sr-only" href="#main-content">Pular para o conteúdo principal</a>';
    echo '<div class="app-container" data-sidebar-state="expanded">';
    echo '<aside class="app-sidebar" aria-label="Navegação do painel">';
    echo '<div class="sidebar-header">';
    echo '<div class="sidebar-brand">';
    if ($logoUrl) {
        echo '<img src="' . sanitize_html($logoUrl) . '" alt="Logo do painel">';
    } else {
        echo '<div class="avatar" aria-hidden="true"><i class="fa-solid fa-capsules"></i></div>';
    }
    echo '<span>' . sanitize_html($storeName) . '</span></div>';
    echo '<button class="sidebar-toggle" type="button" aria-label="Alternar largura do menu" data-sidebar-toggle><i class="fa-solid fa-chevron-left"></i></button>';
    echo '</div>';
    echo '<nav class="app-nav" role="navigation">';
    foreach ($navItems as $item) {
        $isActive = $currentScript === basename($item['href']);
        $classAttr = $isActive ? ' class="active"' : '';
        echo '<a' . $classAttr . ' href="' . sanitize_html($item['href']) . '">';
        echo '<i class="' . sanitize_html($item['icon']) . '" aria-hidden="true"></i><span>' . sanitize_html($item['label']) . '</span>';
        echo '</a>';
    }
    echo '</nav>';
    echo '</aside>';
    echo '<div class="sidebar-backdrop" data-sidebar-backdrop></div>';
    echo '<div class="app-view">';
    echo '<header class="app-topbar" role="banner">';
    echo '<div class="topbar-left">';
    echo '<button class="icon-btn mobile-nav-btn" type="button" aria-label="Abrir menu" data-sidebar-mobile-trigger><i class="fa-solid fa-bars"></i></button>';
    echo '<div>';
    echo '<div class="breadcrumbs"><span>Admin</span><span aria-current="page">' . sanitize_html($title) . '</span></div>';
    echo '<h1 class="topbar-title">' . sanitize_html($title) . '</h1>';
    echo '</div></div>';
    echo '<div class="topbar-search" role="search">';
    echo '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>';
    echo '<input type="search" placeholder="Pesquisar no painel" aria-label="Pesquisar no painel" />';
    echo '</div>';
    echo '<div class="topbar-actions">';
    echo '<div class="welcome-meta">';
    echo '<span>Seja bem-vindo, <strong>' . sanitize_html($adminName) . '</strong></span>';
    echo '<small>' . sanitize_html($currentTimestamp . ' · ' . $timezoneLabel) . '</small>';
    echo '</div>';
    echo '<button class="icon-btn" type="button" aria-pressed="false" aria-label="Alternar tema" data-theme-toggle>';
    echo '<i class="fa-solid fa-moon" data-theme-icon aria-hidden="true"></i>';
    echo '</button>';
    echo '<a class="icon-btn" href="index.php" target="_blank" rel="noopener noreferrer" title="Ver loja">';
    echo '<i class="fa-solid fa-store" aria-hidden="true"></i>';
    echo '</a>';
    echo '<div class="avatar" title="' . sanitize_html($adminName) . '" aria-hidden="true">' . sanitize_html($adminInitials) . '</div>';
    echo '</div>';
    echo '</header>';
    echo '<main id="main-content" class="app-main" tabindex="-1">';
}

function admin_footer()
{
    $cfg = function_exists('cfg') ? cfg() : [];
    $storeName = setting_get('store_name', $cfg['store']['name'] ?? 'Get Power Research');
    $systemVersion = '3.0 — criação Mike Lins';
    echo '</main>';
    echo '<footer class="dashboard-footer">';
    echo '<span>' . sanitize_html($systemVersion) . '</span>';
    echo '<span>&copy; ' . date('Y') . ' ' . sanitize_html($storeName) . '</span>';
    echo '</footer>';
    echo '</div>'; // .app-view
    echo '</div>'; // .app-container
    $js = function_exists('asset_url') ? asset_url('assets/admin.js') : 'assets/admin.js';
    echo '<script src="' . $js . '"></script>';
    echo '</body></html>';
}

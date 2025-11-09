<?php
// index.php — Loja Get Power Research com UI estilo app (responsiva + PWA + categorias + carrinho/checkout)
// Versão com: tema vermelho/âmbar, cache-busting, endpoint CSRF ao vivo, CSRF em header, e fetch com credenciais.
// Requisitos: config.php, lib/db.php, lib/utils.php, (opcional) bootstrap.php para no-cache e asset_url()

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Anti-cache + sessão estável (recomendado)
if (file_exists(__DIR__.'/bootstrap.php')) require __DIR__.'/bootstrap.php';

require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* ======================
   Idioma & Config
   ====================== */
if (isset($_GET['lang'])) set_lang($_GET['lang']);
$d   = lang();
$cfg = cfg();
$categoryFontChoiceSetting = setting_get('store_category_font_choice', 'default');
$categoryFontCustomSetting = setting_get('store_category_font_custom', '');
if (function_exists('store_category_font_stack')) {
  $categoryFontData = store_category_font_stack($categoryFontChoiceSetting, $categoryFontCustomSetting);
  $categoryFontFamilyValue = trim((string)($categoryFontData['stack'] ?? ''));
  $categoryFontRequires = $categoryFontData['requires'] ?? [];
} else {
  $categoryFontFamilyValue = '';
  $categoryFontRequires = [];
}
if (!is_array($categoryFontRequires)) {
  $categoryFontRequires = [];
}

/* ======================
   Router
   ====================== */
$route = $_GET['route'] ?? 'home';

/* ======================
   Endpoint CSRF ao vivo (não-cacheável)
   ====================== */
if ($route === 'csrf') {
  header('Content-Type: application/json; charset=utf-8');
  if (!headers_sent()) {
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
  }
  echo json_encode(['csrf' => csrf_token(), 'sid' => session_id()]);
  exit;
}

/* ======================
   Páginas institucionais
   ====================== */
if ($route === 'privacy') {
  $policyHtml = setting_get('privacy_policy_content', '');
  if (trim($policyHtml) === '') {
    $policyHtml = '<p>Atualize a política de privacidade pelo painel administrativo para exibir o conteúdo correspondente.</p>';
  }
  $safeContent = sanitize_builder_output($policyHtml);
  app_header();
  echo '<section class="max-w-4xl mx-auto px-4 py-12">';
  echo '  <h1 class="text-3xl font-bold mb-6">Política de Privacidade</h1>';
  echo '  <div class="prose prose-sm sm:prose lg:prose-lg text-gray-700 leading-relaxed">'. $safeContent .'</div>';
  echo '</section>';
  app_footer();
  exit;
}

if ($route === 'refund') {
  $policyHtml = setting_get('refund_policy_content', '');
  if (trim($policyHtml) === '') {
    $policyHtml = '<p>Configure a política de reembolso no painel para exibir as condições aos clientes.</p>';
  }
  $safeContent = sanitize_builder_output($policyHtml);
  app_header();
  echo '<section class="max-w-4xl mx-auto px-4 py-12">';
  echo '  <h1 class="text-3xl font-bold mb-6">Política de Reembolso</h1>';
  echo '  <div class="prose prose-sm sm:prose lg:prose-lg text-gray-700 leading-relaxed">'. $safeContent .'</div>';
  echo '</section>';
  app_footer();
  exit;
}

if ($route === 'page') {
  $slug = isset($_GET['slug']) ? (string)$_GET['slug'] : '';
  $page = find_custom_page($slug);
  if (!$page) {
    app_header();
    echo '<section class="max-w-4xl mx-auto px-4 py-12 text-center space-y-4">';
    echo '  <div class="text-5xl text-gray-300"><i class="fa-regular fa-file-lines"></i></div>';
    echo '  <h1 class="text-2xl font-bold text-gray-700">Página não encontrada</h1>';
    echo '  <p class="text-gray-500">A página solicitada pode ter sido removida ou atualizada. Verifique o endereço e tente novamente.</p>';
    echo '  <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-600 text-white hover:bg-brand-700" href="?route=home"><i class="fa-solid fa-house"></i> Voltar à loja</a>';
    echo '</section>';
    app_footer();
    exit;
  }
  $title = htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8');
  $content = $page['content'];
  app_header();
  echo '<section class="max-w-4xl mx-auto px-4 py-12 space-y-6">';
  echo '  <h1 class="text-3xl font-bold">'.$title.'</h1>';
  echo '  <div class="prose prose-sm sm:prose lg:prose-lg text-gray-700 leading-relaxed">'.$content.'</div>';
  echo '</section>';
  app_footer();
  exit;
}

/* ======================
   Helpers — Header / Footer
   ====================== */
function store_logo_path() {
  $opt = setting_get('store_logo_url');
  if ($opt && file_exists(__DIR__.'/'.$opt)) return $opt;
  foreach (['storage/logo/logo.png','storage/logo/logo.jpg','storage/logo/logo.jpeg','storage/logo/logo.webp','assets/logo.png'] as $c) {
    if (file_exists(__DIR__ . '/' . $c)) return $c;
  }
  return null;
}

function proxy_allowed_hosts(): array {
  static $hosts = null;
  if ($hosts !== null) {
    return $hosts;
  }
  $config = cfg();
  $raw = $config['media']['proxy_whitelist'] ?? [];
  if (!is_array($raw)) {
    $raw = [];
  }
  $hosts = array_values(array_filter(array_map(function ($h) {
    return strtolower(trim((string)$h));
  }, $raw)));
  return $hosts;
}

/* === Proxy de Imagem (apenas esta adição para contornar hotlink) === */
function proxy_img($url) {
  $url = trim((string)$url);
  if ($url === '') {
    return '';
  }
  $url = str_replace('\\', '/', $url);
  $url = preg_replace('#^(\./)+#', '', $url);
  while (strpos($url, '../') === 0) {
    $url = substr($url, 3);
  }
  // Se for link http/https absoluto, passa pelo proxy local img.php
  if (preg_match('~^https?://~i', $url)) {
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($host !== '' && in_array($host, proxy_allowed_hosts(), true)) {
      return '/img.php?u=' . rawurlencode($url);
    }
    return $url;
  }
  // Garante caminho absoluto relativo ao root da aplicação
  if ($url !== '' && $url[0] !== '/') {
    $url = '/' . ltrim($url, '/');
  }
  if (function_exists('cache_busted_url')) {
    $versioned = cache_busted_url(ltrim($url, '/'));
    return '/' . ltrim($versioned, '/');
  }
  return $url;
}

function feature_allow_html($value) {
  $value = trim((string)$value);
  $value = strip_tags($value, '<br><strong><em><span>');
  return $value;
}

if (!function_exists('sanitize_builder_output')) {
  function sanitize_builder_output($html) {
    if ($html === '' || $html === null) {
      return '';
    }
    $clean = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', (string)$html);
    $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean);
    $clean = preg_replace('/javascript\s*:/i', '', $clean);
    return $clean;
  }
}

if (!function_exists('whatsapp_widget_config')) {
  function whatsapp_widget_config() {
    static $cacheReady = false;
    static $cache = null;
    if ($cacheReady) {
      return $cache;
    }
    $cacheReady = true;
    $enabled = setting_get('whatsapp_enabled', '0');
    if ((int)$enabled !== 1) {
      return null;
    }
    $rawNumber = setting_get('whatsapp_number', '');
    $number = preg_replace('/\D+/', '', (string)$rawNumber);
    if ($number === '') {
      return null;
    }
    $buttonText = setting_get('whatsapp_button_text', 'Fale com a gente');
    $message = setting_get('whatsapp_message', 'Olá! Gostaria de tirar uma dúvida sobre os produtos.');
    $displayNumber = $number;
    if ($displayNumber !== '') {
      $displayNumber = '+' . $displayNumber;
    }
    $cache = [
      'number' => $number,
      'button_text' => $buttonText,
      'message' => $message,
      'display_number' => $displayNumber
    ];
    return $cache;
  }
}

if (!function_exists('load_payment_methods')) {
function load_payment_methods(PDO $pdo, array $cfg): array {
  static $cache = null;
  if ($cache !== null) {
    return $cache;
  }

  $cache = [];
  try {
    $rows = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
      $settings = [];
      if (!empty($row['settings'])) {
        $decoded = json_decode($row['settings'], true);
        if (is_array($decoded)) {
          $settings = $decoded;
        }
      }
      if (!isset($settings['type'])) {
        $settings['type'] = $row['code'];
      }
      if (!isset($settings['account_label'])) {
        $settings['account_label'] = '';
      }
      if (!isset($settings['account_value'])) {
        $settings['account_value'] = '';
      }
      $cache[] = [
        'id' => (int)$row['id'],
        'code' => (string)$row['code'],
        'name' => (string)$row['name'],
        'description' => (string)($row['description'] ?? ''),
        'instructions' => (string)($row['instructions'] ?? ''),
        'settings' => $settings,
        'icon_path' => $row['icon_path'] ?? null,
        'require_receipt' => (int)($row['require_receipt'] ?? 0),
      ];
    }
  } catch (Throwable $e) {
    $cache = [];
  }

  $cacheCodes = [];
  foreach ($cache as $m) {
    $cacheCodes[$m['code']] = true;
  }
  $paymentsCfg = $cfg['payments'] ?? [];
  if (!isset($cacheCodes['whatsapp']) && !empty($paymentsCfg['whatsapp']['enabled'])) {
    $whatsMethod = [
      'code' => 'whatsapp',
      'name' => 'WhatsApp',
      'description' => '',
      'instructions' => $paymentsCfg['whatsapp']['instructions'] ?? 'Converse com nossa equipe pelo WhatsApp para concluir: {whatsapp_link}.',
      'settings' => [
        'type' => 'whatsapp',
        'account_label' => 'WhatsApp',
        'account_value' => $paymentsCfg['whatsapp']['number'] ?? '',
        'number' => $paymentsCfg['whatsapp']['number'] ?? '',
        'message' => $paymentsCfg['whatsapp']['message'] ?? 'Olá! Gostaria de finalizar meu pedido.',
        'link' => $paymentsCfg['whatsapp']['link'] ?? ''
      ],
      'icon_path' => null,
      'require_receipt' => 0,
    ];
    $cache[] = $whatsMethod;
  }

  if (!$cache) {
    $paymentsCfg = $cfg['payments'] ?? [];
    $defaults = [];
    if (!empty($paymentsCfg['pix']['enabled'])) {
      $defaults[] = [
        'code' => 'pix',
        'name' => 'Pix',
        'instructions' => "Use o Pix para pagar seu pedido. Valor: {valor_pedido}.\nChave: {pix_key}",
        'settings' => [
          'type' => 'pix',
          'account_label' => 'Chave Pix',
          'account_value' => $paymentsCfg['pix']['pix_key'] ?? '',
          'pix_key' => $paymentsCfg['pix']['pix_key'] ?? '',
          'merchant_name' => $paymentsCfg['pix']['merchant_name'] ?? '',
          'merchant_city' => $paymentsCfg['pix']['merchant_city'] ?? ''
        ],
        'require_receipt' => 0
      ];
    }
    if (!empty($paymentsCfg['zelle']['enabled'])) {
      $defaults[] = [
        'code' => 'zelle',
        'name' => 'Zelle',
        'instructions' => "Envie {valor_pedido} via Zelle ({valor_produtos} + frete {valor_frete}) para {account_value}.",
        'settings' => [
          'type' => 'zelle',
          'account_label' => 'Conta Zelle',
          'account_value' => $paymentsCfg['zelle']['recipient_email'] ?? '',
          'recipient_name' => $paymentsCfg['zelle']['recipient_name'] ?? ''
        ],
        'require_receipt' => (int)($paymentsCfg['zelle']['require_receipt_upload'] ?? 1)
      ];
    }
    if (!empty($paymentsCfg['venmo']['enabled'])) {
      $defaults[] = [
        'code' => 'venmo',
        'name' => 'Venmo',
        'instructions' => "Pague {valor_pedido} no Venmo. Link: {venmo_link}.",
        'settings' => [
          'type' => 'venmo',
          'account_label' => 'Link Venmo',
          'account_value' => $paymentsCfg['venmo']['handle'] ?? '',
          'venmo_link' => $paymentsCfg['venmo']['handle'] ?? ''
        ],
        'require_receipt' => 1
      ];
    }
    if (!empty($paymentsCfg['paypal']['enabled'])) {
      $defaults[] = [
        'code' => 'paypal',
        'name' => 'PayPal',
        'instructions' => "Após finalizar, você será direcionado ao PayPal com o valor {valor_pedido}.",
        'settings' => [
          'type' => 'paypal',
          'account_label' => 'Conta PayPal',
          'account_value' => $paymentsCfg['paypal']['business'] ?? '',
          'business' => $paymentsCfg['paypal']['business'] ?? '',
          'currency' => $paymentsCfg['paypal']['currency'] ?? 'USD',
          'return_url' => $paymentsCfg['paypal']['return_url'] ?? '',
          'cancel_url' => $paymentsCfg['paypal']['cancel_url'] ?? ''
        ],
        'require_receipt' => 0
      ];
    }
    if (!empty($paymentsCfg['whatsapp']['enabled'])) {
      $defaults[] = [
        'code' => 'whatsapp',
        'name' => 'WhatsApp',
        'instructions' => $paymentsCfg['whatsapp']['instructions'] ?? 'Converse com nossa equipe pelo WhatsApp para concluir: {whatsapp_link}.',
        'settings' => [
          'type' => 'whatsapp',
          'account_label' => 'WhatsApp',
          'account_value' => $paymentsCfg['whatsapp']['number'] ?? '',
          'number' => $paymentsCfg['whatsapp']['number'] ?? '',
          'message' => $paymentsCfg['whatsapp']['message'] ?? 'Olá! Gostaria de finalizar meu pedido.',
          'link' => $paymentsCfg['whatsapp']['link'] ?? ''
        ],
        'require_receipt' => 0
      ];
    }
    if (!empty($paymentsCfg['square']['enabled'])) {
      $defaults[] = [
        'code' => 'square',
        'name' => 'Cartão de crédito',
        'instructions' => $paymentsCfg['square']['instructions'] ?? 'Abriremos o checkout de cartão de crédito em uma nova aba.',
        'settings' => [
          'type' => 'square',
          'account_label' => 'Pagamento com cartão de crédito',
          'account_value' => '',
          'mode' => 'square_product_link',
          'open_new_tab' => !empty($paymentsCfg['square']['open_new_tab'])
        ],
        'require_receipt' => 0
      ];
    }
    if (!empty($paymentsCfg['stripe']['enabled'])) {
      $defaults[] = [
        'code' => 'stripe',
        'name' => 'Stripe',
        'instructions' => $paymentsCfg['stripe']['instructions'] ?? 'Abriremos o checkout Stripe em uma nova aba.',
        'settings' => [
          'type' => 'stripe',
          'account_label' => 'Pagamento Stripe',
          'account_value' => '',
          'mode' => 'stripe_product_link',
          'open_new_tab' => !empty($paymentsCfg['stripe']['open_new_tab'])
        ],
        'require_receipt' => 0
      ];
    }
    $cache = $defaults;
  }

  return $cache;
}
}

function payment_placeholders(
  array $method,
  float $totalValue,
  ?int $orderId = null,
  ?string $customerEmail = null,
  ?float $subtotalValue = null,
  ?float $shippingValue = null,
  ?string $currencyOverride = null
): array {
  $settings = $method['settings'] ?? [];
  $currency = $currencyOverride ?: ($settings['currency'] ?? (cfg()['store']['currency'] ?? 'USD'));
  $subtotalValue = ($subtotalValue === null) ? $totalValue : $subtotalValue;
  $shippingValue = ($shippingValue === null) ? 0.0 : $shippingValue;
  $placeholders = [
    '{valor_pedido}' => format_currency($totalValue, $currency),
    '{valor_total}' => format_currency($totalValue, $currency),
    '{valor_total_com_frete}' => format_currency($totalValue, $currency),
    '{valor_produtos}' => format_currency($subtotalValue, $currency),
    '{valor_subtotal}' => format_currency($subtotalValue, $currency),
    '{valor_frete}' => format_currency($shippingValue, $currency),
    '{numero_pedido}' => $orderId ? (string)$orderId : '',
    '{email_cliente}' => $customerEmail ?? '',
    '{account_label}' => $settings['account_label'] ?? '',
    '{account_value}' => $settings['account_value'] ?? '',
    '{pix_key}' => $settings['pix_key'] ?? ($settings['account_value'] ?? ''),
    '{pix_merchant_name}' => $settings['merchant_name'] ?? '',
    '{pix_merchant_city}' => $settings['merchant_city'] ?? '',
    '{venmo_link}' => $settings['venmo_link'] ?? ($settings['account_value'] ?? ''),
    '{paypal_business}' => $settings['business'] ?? '',
    '{stripe_link}' => $settings['redirect_url'] ?? '',
  ];
  $waNumberRaw = trim((string)($settings['number'] ?? $settings['account_value'] ?? ''));
  $waMessage = trim((string)($settings['message'] ?? ''));
  $waLinkCustom = trim((string)($settings['link'] ?? ''));
  $waNumberDigits = preg_replace('/\D+/', '', $waNumberRaw);
  $waLink = $waLinkCustom;
  if ($waNumberDigits !== '') {
    $waLink = 'https://wa.me/'.$waNumberDigits;
    if ($waMessage !== '') {
      $waLink .= '?text='.rawurlencode($waMessage);
    }
  }
  if ($waLink === '' && $waNumberRaw !== '') {
    $waLink = $waNumberRaw;
  }
  if ($waLink === '' && !empty($settings['account_value'])) {
    $waLink = $settings['account_value'];
  }
  $placeholders['{whatsapp_number}'] = $waNumberRaw !== '' ? $waNumberRaw : ($waNumberDigits !== '' ? '+' . $waNumberDigits : '');
  $placeholders['{whatsapp_link}'] = $waLink;
  $placeholders['{whatsapp_message}'] = $waMessage;

  return $placeholders;
}

function render_payment_instructions(string $template, array $placeholders): string {
  if ($template === '') {
    return '';
  }
  $text = strtr($template, $placeholders);
  return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
}

function app_header() {
  global $d, $cfg;

  $lang = $d['_lang'] ?? 'pt';
  $logo = store_logo_path();
  $logoUrl = $logo;
  if ($logo) {
    $logoUrl = function_exists('cache_busted_url') ? cache_busted_url($logo) : $logo;
  }
  $storeNameHeader = setting_get('store_name', $cfg['store']['name'] ?? 'Get Power Research');
  $storeHoursStatus = function_exists('store_hours_status') ? store_hours_status() : ['label' => '', 'is_open' => false, 'status_text' => ''];
  $metaTitle = setting_get('store_meta_title', (($cfg['store']['name'] ?? 'Get Power Research').' | Loja'));
  if (!empty($GLOBALS['app_meta_title'])) {
    $metaTitle = (string)$GLOBALS['app_meta_title'];
  }
  $pwaName = setting_get('pwa_name', $cfg['store']['name'] ?? 'Get Power Research');
  $pwaShortName = setting_get('pwa_short_name', $pwaName);
  $pwaIconApple = pwa_icon_url(180);
  $pwaIcon512 = pwa_icon_url(512);
  $a2hsTitleSetting = setting_get('a2hs_title', 'Instalar App '.$storeNameHeader);
  $a2hsSubtitleSetting = setting_get('a2hs_subtitle', 'Experiência completa no seu dispositivo.');
  $a2hsButtonSetting = setting_get('a2hs_button_label', 'Instalar App');

  // Count carrinho
  $cart_count = 0;
  if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
  }

  $activeTheme = active_store_theme();
  $headerLinks = navigation_links('header');
  if ($activeTheme === 'food' && (!$headerLinks || count($headerLinks) === 0)) {
    $headerLinks = [
      ['label' => 'Início', 'url' => '?route=home#inicio', 'target' => '_self', 'order' => 10],
      ['label' => 'Produtos', 'url' => '?route=home#produtos', 'target' => '_self', 'order' => 20],
      ['label' => 'Sobre Nós', 'url' => '?route=home#historia', 'target' => '_self', 'order' => 30],
      ['label' => 'Contato', 'url' => '?route=home#contato', 'target' => '_self', 'order' => 40],
    ];
  }

  echo '<!doctype html><html lang="'.htmlspecialchars($lang).'"><head>';
  echo '  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '  <meta http-equiv="Cache-Control" content="no-cache, no-store, max-age=0, must-revalidate">';
  echo '  <meta http-equiv="Pragma" content="no-cache">';
  echo '  <meta http-equiv="Expires" content="0">';
  echo '  <title>'.htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8').'</title>';
  $defaultDescription = setting_get('store_meta_description', ($cfg['store']['name'] ?? 'Get Power Research').' — experiência tipo app: rápida, responsiva e segura.');
  if (!empty($GLOBALS['app_meta_description'])) {
    $defaultDescription = (string)$GLOBALS['app_meta_description'];
  }
  echo '  <meta name="description" content="'.htmlspecialchars($defaultDescription, ENT_QUOTES, 'UTF-8').'">';
  $manifestHref = function_exists('cache_busted_url') ? '/' . ltrim(cache_busted_url('manifest.php'), '/') : '/manifest.php';
  echo '  <link rel="manifest" href="'.$manifestHref.'">';
  $themeColor = setting_get('theme_color', '#2060C8');
  echo '  <meta name="theme-color" content="'.htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8').'">';
  echo '  <meta name="application-name" content="'.htmlspecialchars($pwaShortName, ENT_QUOTES, 'UTF-8').'">';

  // ====== iOS PWA (suporte ao Add to Home Screen) ======
  $appleIconHref = $pwaIconApple ?: '/assets/icons/admin-192.png';
  echo '  <link rel="apple-touch-icon" href="'.htmlspecialchars($appleIconHref, ENT_QUOTES, 'UTF-8').'">';
  echo '  <meta name="apple-mobile-web-app-capable" content="yes">';
  echo '  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
  echo '  <meta name="apple-mobile-web-app-title" content="'.htmlspecialchars($pwaName, ENT_QUOTES, 'UTF-8').'">';
  echo '  <link rel="icon" type="image/png" sizes="512x512" href="'.htmlspecialchars($pwaIcon512 ?: '/assets/icons/admin-512.png', ENT_QUOTES, 'UTF-8').'">';

  echo '  <script src="https://cdn.tailwindcss.com"></script>';
  $brandPalette = generate_brand_palette($themeColor);
  $accentPalette = ['400' => adjust_color_brightness($themeColor, 0.35)];
  $brandJson = json_encode($brandPalette, JSON_UNESCAPED_SLASHES);
  $accentJson = json_encode($accentPalette, JSON_UNESCAPED_SLASHES);
  echo "  <script>tailwind.config = { theme: { extend: { colors: { brand: $brandJson, accent: $accentJson }}}};</script>";
  echo '  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">';
  $googleFontFamilies = [];
  if ($activeTheme === 'food') {
    $googleFontFamilies[] = 'Inter:wght@400;500;600;700';
    $googleFontFamilies[] = 'Pacifico';
    $googleFontFamilies[] = 'Playfair+Display:wght@400;600;700';
  }
  foreach ($categoryFontRequires as $reqFont) {
    if ($reqFont === 'inter') {
      $googleFontFamilies[] = 'Inter:wght@400;500;600;700';
    } elseif ($reqFont === 'pacifico') {
      $googleFontFamilies[] = 'Pacifico';
    } elseif ($reqFont === 'playfair') {
      $googleFontFamilies[] = 'Playfair+Display:wght@400;600;700';
    } elseif ($reqFont === 'cormorant') {
      $googleFontFamilies[] = 'Cormorant+Garamond:wght@400;600;700';
    }
  }
  $googleFontFamilies = array_values(array_unique(array_filter($googleFontFamilies)));
  if ($googleFontFamilies) {
    echo '  <link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    $fontQuery = implode('&family=', $googleFontFamilies);
    echo '  <link href="https://fonts.googleapis.com/css2?family='.$fontQuery.'&display=swap" rel="stylesheet">';
  }
  if ($activeTheme === 'food') {
    echo '  <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.3.0/remixicon.min.css" rel="stylesheet">';
  }
  $cacheBusterToken = function_exists('cache_bust_current_token') ? cache_bust_current_token() : (string)time();
  $a2hsScript = function_exists('asset_url') ? '/' . ltrim(asset_url('assets/js/a2hs.js'), '/') : '/assets/js/a2hs.js?v=3';
  $swRegisterUrl = function_exists('service_worker_url') ? service_worker_url() : '/sw.js';
  $swFallbackList = [$swRegisterUrl];
  if (function_exists('cache_busted_url')) {
    $swFallbackList[] = '/' . ltrim(cache_busted_url('sw.js'), '/');
  }
  $swFallbackList[] = '/sw.js';
  $swFallbacks = array_values(array_unique($swFallbackList));
  $a2hsIconSetting = setting_get('a2hs_icon', '');
  if ($a2hsIconSetting) {
    $iconPath = ltrim($a2hsIconSetting, '/');
    $a2hsIcon = '/' . ltrim(function_exists('cache_busted_url') ? cache_busted_url($iconPath) : $iconPath, '/');
  } else {
    $a2hsIcon = function_exists('asset_url') ? '/' . ltrim(asset_url('assets/icons/farma-192.png'), '/') : '/assets/icons/farma-192.png';
  }
  echo '  <script>';
  echo 'window.__CACHE_BUSTER__ = '.json_encode($cacheBusterToken).';';
  echo 'window.__SW_URL__ = '.json_encode($swRegisterUrl).';';
  echo 'window.__SW_URLS__ = '.json_encode($swFallbacks).';';
  echo 'window.__A2HS_ICON__ = '.json_encode($a2hsIcon).';';
  echo 'window.__APP_ICON__ = window.__APP_ICON__ || window.__A2HS_ICON__;';
  echo 'window.__APP_NAME__ = '.json_encode($storeNameHeader).';';
  echo 'window.__A2HS_TITLE__ = '.json_encode($a2hsTitleSetting).';';
  echo 'window.__A2HS_SUBTITLE__ = '.json_encode($a2hsSubtitleSetting).';';
  echo 'window.__A2HS_BUTTON__ = '.json_encode($a2hsButtonSetting).';';
  echo '</script>';
  echo '  <script defer src="'.$a2hsScript.'"></script>';

  // CSS do tema com cache-busting se disponível
  if (function_exists('asset_url')) {
    echo '  <link href="'.asset_url('assets/theme.css').'" rel="stylesheet">';
  } else {
    echo '  <link href="assets/theme.css" rel="stylesheet">';
  }
  if ($activeTheme === 'food') {
    $foodCss = function_exists('asset_url') ? asset_url('assets/theme-food.css') : 'assets/theme-food.css';
    echo '  <link href="'.$foodCss.'" rel="stylesheet">';
  }
  if ($categoryFontFamilyValue !== '') {
    $safeFontValue = htmlspecialchars($categoryFontFamilyValue, ENT_NOQUOTES, 'UTF-8');
    echo '  <style>:root{--category-font-family:'.$safeFontValue.';}</style>';
  }
  $brandPrimary = $brandPalette['600'] ?? $themeColor;
  echo '  <style>:root{--brand-primary:'.htmlspecialchars($brandPrimary, ENT_QUOTES, 'UTF-8').';}
          .btn{transition:all .2s}
          .btn:active{transform:translateY(1px)}
          .badge{min-width:1.5rem; height:1.5rem}
          .card{background:var(--bg, #fff)}
          .blur-bg{backdrop-filter: blur(12px)}
          .a2hs-btn{border:1px solid rgba(185,28,28,.25)}
          .chip{border:1px solid #e5e7eb}
          .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
          .line-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
          .product-card:hover img{transform: scale(1.05)}
          .footer-heading{display:flex;align-items:center;gap:.5rem}
          .footer-heading img{width:28px;height:28px;border-radius:999px;object-fit:cover;background:#fff;padding:2px;box-shadow:0 6px 14px -8px rgba(0,0,0,.35)}
          .footer-heading-icon{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:rgba(32,96,200,.12);color:var(--brand-primary,#2060C8);font-size:.85rem}
        </style>';
  if (!empty($cfg['custom_scripts']['head'])) {
    echo $cfg['custom_scripts']['head'];
  }
  $googleAnalyticsSnippet = setting_get('google_analytics_code', '');
  if (!empty($googleAnalyticsSnippet)) {
    echo "\n  " . $googleAnalyticsSnippet;
  }
  $squareLoadingUrl = function_exists('cache_busted_url') ? cache_busted_url('square-loading.html') : 'square-loading.html';
  $bodyClasses = ['bg-gray-50','text-gray-800','min-h-screen'];
  if ($activeTheme === 'food') {
    $bodyClasses[] = 'theme-food';
  }
  $bodyAttrClass = htmlspecialchars(implode(' ', $bodyClasses), ENT_QUOTES, 'UTF-8');
  $bodyAttrs = 'class="'.$bodyAttrClass.'" data-square-loading-url="'.htmlspecialchars($squareLoadingUrl, ENT_QUOTES, 'UTF-8').'"';
  echo '</head><body '.$bodyAttrs.'>';
  if (!empty($cfg['custom_scripts']['body_start'])) {
    echo $cfg['custom_scripts']['body_start'];
  }

  // Topbar (estilo app) — sticky + blur
  echo '<header class="sticky top-0 z-40 border-b bg-white/90 blur-bg">';
  echo '  <div class="max-w-7xl mx-auto px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">';
  echo '    <div class="flex flex-col md:flex-row md:items-center md:gap-6 w-full md:w-auto">';
  echo '      <a href="?route=home" class="flex items-center gap-3">';
  if ($logoUrl) {
    echo '        <img src="'.htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8').'" class="w-16 h-16 rounded-2xl object-contain bg-white p-1 shadow-sm" alt="logo">';
  } else {
    echo '        <div class="w-16 h-16 rounded-2xl bg-brand-600 text-white grid place-items-center text-2xl"><i class="fas fa-pills"></i></div>';
  }
  echo '        <div>';
  $headerSubline = setting_get('header_subline', 'Loja Online');
  echo '          <div class="font-semibold leading-tight store-brand-name">'.htmlspecialchars($storeNameHeader, ENT_QUOTES, 'UTF-8').'</div>';
  echo '          <div class="text-xs text-gray-500 store-brand-subline">'.htmlspecialchars($headerSubline, ENT_QUOTES, 'UTF-8').'</div>';
  $hoursLabel = trim((string)($storeHoursStatus['label'] ?? ''));
  if (!empty($storeHoursStatus['enabled']) && $hoursLabel !== '') {
    $isOpen = !empty($storeHoursStatus['is_open']);
    $openLabel = $d['open_now'] ?? ($storeHoursStatus['status_text'] ?? 'Aberto agora');
    $closedLabel = $d['closed_now'] ?? ($storeHoursStatus['status_text'] ?? 'Fechado agora');
    $statusText = $isOpen ? $openLabel : $closedLabel;
    $hoursClass = $isOpen ? 'hours-pill hours-pill--open' : 'hours-pill hours-pill--closed';
    echo '          <div class="'.$hoursClass.'"><span class="hours-pill__dot" aria-hidden="true"></span><span class="hours-pill__status">'.htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8').'</span><span class="hours-pill__label">'.htmlspecialchars($hoursLabel, ENT_QUOTES, 'UTF-8').'</span></div>';
  }
  echo '        </div>';
  echo '      </a>';
  $navClasses = 'mt-3 pt-3 border-t border-gray-200 flex flex-wrap gap-2 text-sm text-brand-700 md:mt-0 md:pt-0 md:border-t-0 md:ml-6 md:flex-nowrap';
  if (!empty($headerLinks)) {
    echo '      <nav class="'.$navClasses.'">';
    foreach ($headerLinks as $link) {
      $label = htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
      $url = htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8');
      $target = htmlspecialchars($link['target'], ENT_QUOTES, 'UTF-8');
      echo '        <a href="'.$url.'" target="'.$target.'" class="px-3 py-1 rounded-lg bg-brand-50 hover:bg-brand-100 transition">'.$label.'</a>';
    }
    echo '      </nav>';
  } else {
    echo '      <nav class="'.$navClasses.'">';
    echo '        <a href="?route=home#inicio" target="_self" class="px-3 py-1 rounded-lg bg-brand-50 hover:bg-brand-100 transition">Início</a>';
    echo '        <a href="?route=home#produtos" target="_self" class="px-3 py-1 rounded-lg bg-brand-50 hover:bg-brand-100 transition">Produtos</a>';
    echo '        <a href="?route=home#historia" target="_self" class="px-3 py-1 rounded-lg bg-brand-50 hover:bg-brand-100 transition">Sobre Nós</a>';
    echo '        <a href="?route=home#contato" target="_self" class="px-3 py-1 rounded-lg bg-brand-50 hover:bg-brand-100 transition">Contato</a>';
    echo '      </nav>';
  }
  echo '    </div>';

  echo '    <div class="flex items-center gap-2 w-full md:w-auto mt-2 md:mt-0 justify-end">';
  echo '      <div class="relative">';
  echo '        <select onchange="changeLanguage(this.value)" class="px-3 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-brand-500 text-sm">';
  $languages = ['pt'=>'🇧🇷 PT','en'=>'🇺🇸 EN','es'=>'🇪🇸 ES'];
  foreach ($languages as $code=>$label) {
    $selected = (($d["_lang"] ?? "pt") === $code) ? "selected" : "";
    echo '          <option value="'.$code.'" '.$selected.'>'.$label.'</option>';
  }
  echo '        </select>';
  echo '      </div>';

  echo '      <a href="?route=cart" class="relative">';
  echo '        <div class="btn flex items-center justify-center px-3 py-2 rounded-lg bg-brand-600 text-white hover:bg-brand-700">';
  echo '          <i class="fas fa-shopping-cart"></i>';
  if ($cart_count > 0) {
    echo '        <span id="cart-badge" class="badge absolute -top-2 -right-2 rounded-full bg-red-500 text-white text-xs grid place-items-center px-1">'.(int)$cart_count.'</span>';
  } else {
    echo '        <span id="cart-badge" class="badge hidden absolute -top-2 -right-2 rounded-full bg-red-500 text-white text-xs grid place-items-center px-1">0</span>';
  }
  echo '        </div>';
  echo '        <span class="sr-only">'.htmlspecialchars($d['cart'] ?? 'Carrinho').'</span>';
  echo '      </a>';
  echo '    </div>';
  echo '  </div>';
  echo '</header>';

  echo '<main>';
}

function app_footer() {
  global $cfg;
  echo '</main>';

  // Footer enxuto tipo app
  $footerLinks = navigation_links('footer');
  $footerLogoPath = store_logo_path();
  $footerLogoElement = '<span class="footer-heading-icon"><i class="fa-solid fa-store"></i></span>';
  if ($footerLogoPath) {
    $footerLogoSrc = function_exists('cache_busted_url') ? cache_busted_url($footerLogoPath) : $footerLogoPath;
    $footerLogoElement = '<img src="'.htmlspecialchars($footerLogoSrc, ENT_QUOTES, 'UTF-8').'" alt="Logo da loja" loading="lazy">';
  }
  $footerHeadingIcon = function (string $iconClass): string {
    $iconSafe = htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8');
    return '<span class="footer-heading-icon"><i class="'.$iconSafe.'"></i></span>';
  };
  echo '<footer class="mt-12 bg-white border-t">';
  echo '  <div class="max-w-7xl mx-auto px-4 py-8 grid md:grid-cols-4 gap-8 text-sm">';
  echo '    <div>';
  $footerTitle = setting_get('footer_title', 'Get Power Research');
  $footerDescription = setting_get('footer_description', 'Sua loja online com experiência de app.');
  $instagramLink = trim((string)setting_get('social_instagram_url', ''));
  $facebookLink = trim((string)setting_get('social_facebook_url', ''));
  $hasSocialLinks = ($instagramLink !== '' || $facebookLink !== '');
  echo '      <div class="font-semibold mb-2 footer-heading">'.$footerLogoElement.'<span>'.htmlspecialchars($footerTitle, ENT_QUOTES, 'UTF-8').'</span></div>';
  echo '      <p class="text-gray-500">'.htmlspecialchars($footerDescription, ENT_QUOTES, 'UTF-8').'</p>';
  if ($hasSocialLinks) {
    echo '      <div class="footer-social">';
    if ($instagramLink !== '') {
      $instaAttr = htmlspecialchars($instagramLink, ENT_QUOTES, 'UTF-8');
      echo '        <a class="footer-social-link" href="'.$instaAttr.'" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>';
    }
    if ($facebookLink !== '') {
      $faceAttr = htmlspecialchars($facebookLink, ENT_QUOTES, 'UTF-8');
      echo '        <a class="footer-social-link" href="'.$faceAttr.'" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>';
    }
    echo '      </div>';
  }
  echo '    </div>';
  echo '    <div>';
  echo '      <div class="font-semibold mb-2 footer-heading">'.$footerHeadingIcon('fa-solid fa-link').'<span>Links</span></div>';
  echo '      <ul class="space-y-2 text-gray-600">';
  if ($footerLinks) {
    foreach ($footerLinks as $link) {
      $label = htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
      $url = htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8');
      $target = htmlspecialchars($link['target'], ENT_QUOTES, 'UTF-8');
      echo '        <li><a class="hover:text-brand-700" href="'.$url.'" target="'.$target.'">'.$label.'</a></li>';
    }
  } else {
    echo '        <li><a class="hover:text-brand-700" href="?route=home">Início</a></li>';
    echo '        <li><a class="hover:text-brand-700" href="?route=cart">Carrinho</a></li>';
    echo '        <li><a class="hover:text-brand-700" href="?route=privacy">Política de Privacidade</a></li>';
    echo '        <li><a class="hover:text-brand-700" href="?route=refund">Política de Reembolso</a></li>';
  }
  echo '        <li><button id="btnA2HS" class="hidden inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-600 text-white hover:bg-brand-700 text-sm w-full md:w-auto" type="button"><i class="fa-solid fa-mobile-screen-button"></i><span>Instalar app</span></button></li>';
  echo '        <li><a class="hover:text-brand-700 inline-flex items-center gap-2" href="?route=account"><i class="fa-solid fa-user-circle"></i><span>Minha conta</span></a></li>';
  echo '      </ul>';
  echo '    </div>';
  echo '    <div>';
  echo '      <div class="font-semibold mb-2 footer-heading">'.$footerHeadingIcon('fa-solid fa-headset').'<span>Contato</span></div>';
  echo '      <ul class="space-y-2 text-gray-600">';
  $storeConfig = cfg();
  echo '        <li><i class="fa-solid fa-envelope mr-2"></i>'.htmlspecialchars(setting_get('store_email', $storeConfig['store']['support_email'] ?? 'contato@getpowerresearch.com')).'</li>';
  echo '        <li><i class="fa-solid fa-phone mr-2"></i>'.htmlspecialchars(setting_get('store_phone', $storeConfig['store']['phone'] ?? '(82) 99999-9999')).'</li>';
  echo '        <li><i class="fa-solid fa-location-dot mr-2"></i>'.htmlspecialchars(setting_get('store_address', $storeConfig['store']['address'] ?? 'Maceió - AL')).'</li>';
  echo '      </ul>';
  echo '    </div>';
  echo '    <div>';
  echo '      <div class="font-semibold mb-2 footer-heading">'.$footerHeadingIcon('fa-solid fa-language').'<span>Idioma</span></div>';
  echo '      <div class="flex gap-2">';
  echo '        <button class="chip px-3 py-1 rounded" onclick="changeLanguage(\'pt\')">🇧🇷 PT</button>';
  echo '        <button class="chip px-3 py-1 rounded" onclick="changeLanguage(\'en\')">🇺🇸 EN</button>';
  echo '        <button class="chip px-3 py-1 rounded" onclick="changeLanguage(\'es\')">🇪🇸 ES</button>';
  echo '      </div>';
  echo '    </div>';
  echo '  </div>';
  $storeNameFooter = setting_get('store_name', $storeConfig['store']['name'] ?? 'Get Power Research');
  $footerCopyTpl = setting_get('footer_copy', '© {{year}} {{store_name}}. Todos os direitos reservados.');
  $footerCopyText = strtr($footerCopyTpl, [
    '{{year}}' => date('Y'),
    '{{store_name}}' => $storeNameFooter,
  ]);
  echo '  <div class="text-center text-xs text-gray-500 py-4 border-t">'.sanitize_html($footerCopyText).'</div>';
  echo '</footer>';
  $whats = whatsapp_widget_config();
  if ($whats) {
    $buttonText = htmlspecialchars($whats['button_text'], ENT_QUOTES, 'UTF-8');
    $displayNumber = htmlspecialchars($whats['display_number'], ENT_QUOTES, 'UTF-8');
    $message = $whats['message'] ?? '';
    $url = 'https://wa.me/'.$whats['number'];
    if ($message !== '') {
      $url .= '?text='.rawurlencode($message);
    }
    $urlAttr = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<div class="fixed z-50 bottom-5 right-4 sm:bottom-8 sm:right-6 whatsapp-button">';
    echo '  <a href="'.$urlAttr.'" target="_blank" rel="noopener noreferrer" class="group flex items-center gap-3 bg-[#25D366] text-white px-4 py-3 rounded-full shadow-lg hover:shadow-xl transition-all duration-200 hover:-translate-y-1">';
    echo '    <span class="text-2xl"><i class="fa-brands fa-whatsapp"></i></span>';
    echo '    <span class="flex flex-col leading-tight">';
    echo '      <span class="text-sm font-semibold">'.$buttonText.'</span>';
    if ($displayNumber !== '+') {
      echo '      <span class="text-xs opacity-80">'.$displayNumber.'</span>';
    }
    echo '    </span>';
    echo '  </a>';
    echo '</div>';
  }

  $pwaBannerConfig = pwa_banner_config();
  if (!empty($pwaBannerConfig['enabled'])) {
    $position = $pwaBannerConfig['position'] ?? 'center';
    switch ($position) {
      case 'left':
        $bannerPositionClass = 'fixed bottom-4 left-4';
        break;
      case 'right':
        $bannerPositionClass = 'fixed bottom-4 right-4';
        break;
      default:
        $bannerPositionClass = 'fixed bottom-4 left-1/2 -translate-x-1/2';
        break;
    }
    $bannerStyle = sprintf(
      'background:%s;color:%s;border:1px solid %s;',
      htmlspecialchars($pwaBannerConfig['background_color'], ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($pwaBannerConfig['text_color'], ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($pwaBannerConfig['border_color'], ENT_QUOTES, 'UTF-8')
    );
    $buttonStyle = sprintf(
      'background:%s;color:%s;',
      htmlspecialchars($pwaBannerConfig['button_background'], ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($pwaBannerConfig['button_text_color'], ENT_QUOTES, 'UTF-8')
    );
    $message = sanitize_html($pwaBannerConfig['message']);
    $buttonLabel = sanitize_html($pwaBannerConfig['button_label']);
    $showDelay = (int)($pwaBannerConfig['show_delay_ms'] ?? 0);
    $displayDuration = (int)($pwaBannerConfig['display_duration_ms'] ?? 0);
    $cooldownHours = (int)($pwaBannerConfig['cooldown_hours'] ?? 0);
    echo '<div id="a2hsBanner" class="'.$bannerPositionClass.' z-50 flex items-center gap-3 rounded-xl shadow-lg px-4 py-3 hidden" style="'.$bannerStyle.'" data-show-delay="'.$showDelay.'" data-display-duration="'.$displayDuration.'" data-cooldown-hours="'.$cooldownHours.'">';
    echo '  <span class="text-sm">'.$message.'</span>';
    echo '  <button id="a2hsInstall" class="px-3 py-2 rounded-lg text-sm font-semibold hover:opacity-90" style="'.$buttonStyle.'">'.$buttonLabel.'</button>';
    echo '  <button id="a2hsClose" class="ml-1 text-gray-500 text-lg leading-none" aria-label="Fechar banner">&times;</button>';
    echo '</div>';
  }

  // Scripts (A2HS + helpers + carrinho AJAX com CSRF dinâmico)
  echo '<script>
    // ========= Utilidades A2HS =========
    let deferredPrompt = null;

    const bannerEl = document.getElementById("a2hsBanner");
    const bannerConfig = bannerEl ? {
      delay: parseInt(bannerEl.dataset.showDelay || "0", 10) || 0,
      duration: parseInt(bannerEl.dataset.displayDuration || "0", 10) || 0,
      cooldownMs: (parseInt(bannerEl.dataset.cooldownHours || "0", 10) || 0) * 3600000
    } : { delay: 0, duration: 0, cooldownMs: 0 };
    const bannerStorageKey = "ff_pwa_banner_dismissed_at";

    const isIOS = () => /iphone|ipad|ipod/i.test(navigator.userAgent);
    const isStandalone = () => window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;

    function ensureBtn() {
      const b = document.getElementById("btnA2HS");
      if (b) b.classList.remove("hidden");
      return b;
    }

    function dismissedRecently() {
      if (!bannerEl) return true;
      if (!bannerConfig.cooldownMs) return false;
      const raw = localStorage.getItem(bannerStorageKey);
      if (!raw) return false;
      const last = parseInt(raw, 10);
      if (Number.isNaN(last)) return false;
      return (Date.now() - last) < bannerConfig.cooldownMs;
    }

    function rememberDismiss() {
      localStorage.setItem(bannerStorageKey, String(Date.now()));
    }

    function showBanner(force = false) {
      if (!bannerEl) return;
      if (!force && dismissedRecently()) return;
      bannerEl.classList.remove("hidden");
      if (bannerConfig.duration > 0) {
        setTimeout(() => hideBanner(false), bannerConfig.duration);
      }
    }

    function hideBanner(registerDismiss = true) {
      if (!bannerEl) return;
      bannerEl.classList.add("hidden");
      if (registerDismiss) {
        rememberDismiss();
      }
    }

    function scheduleBanner(force = false) {
      if (!bannerEl) return;
      if (!force && dismissedRecently()) return;
      const delay = force ? 0 : bannerConfig.delay;
      setTimeout(() => showBanner(force), Math.max(0, delay));
    }

    function showIOSInstallHelp() {
      // overlay simples com instruções para iPhone
      document.getElementById("ios-a2hs-overlay")?.remove();
      const overlay = document.createElement("div");
      overlay.id = "ios-a2hs-overlay";
      overlay.className = "fixed inset-0 bg-black/50 z-[1000] grid place-items-center p-4";
      overlay.innerHTML = `
        <div class="max-w-sm w-full bg-white rounded-2xl shadow-xl p-5">
          <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg bg-brand-600 text-white grid place-items-center">
              <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <div class="flex-1">
              <div class="font-semibold text-lg mb-1">Adicionar à Tela de Início</div>
              <p class="text-sm text-gray-600">
                No iPhone, toque em <b>Compartilhar</b>
                (ícone <i class="fa-solid fa-arrow-up-from-bracket"></i>)
                e depois em <b>Adicionar à Tela de Início</b>.
              </p>
              <ol class="text-sm text-gray-600 list-decimal ml-5 mt-3 space-y-1">
                <li>Toque em <b>Compartilhar</b> na barra inferior.</li>
                <li>Role as opções e toque em <b>Adicionar à Tela de Início</b>.</li>
                <li>Confirme com <b>Adicionar</b>.</li>
              </ol>
            </div>
          </div>
          <div class="mt-4 text-right">
            <button id="ios-a2hs-close" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Fechar</button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
      document.getElementById("ios-a2hs-close")?.addEventListener("click", () => overlay.remove());
      overlay.addEventListener("click", (e) => { if (e.target === overlay) overlay.remove(); });
    }

    // Deixa o botão visível por padrão como fallback
    ensureBtn();

    if (bannerEl && !dismissedRecently()) {
      scheduleBanner(false);
    }

    // Chrome/Android/desktop: evento nativo
    window.addEventListener("beforeinstallprompt", (e) => {
      e.preventDefault();
      deferredPrompt = e;
      ensureBtn();
      scheduleBanner(true);
    });

    function installPrompt() {
      if (isIOS() && !isStandalone()) {
        // iOS não tem beforeinstallprompt — mostra instruções
        showIOSInstallHelp();
        hideBanner();
        return;
      }
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.finally(() => {
        deferredPrompt = null;
        rememberDismiss();
      });
      hideBanner(false);
    }

    document.getElementById("btnA2HS")?.addEventListener("click", installPrompt);
    document.getElementById("a2hsInstall")?.addEventListener("click", installPrompt);
    document.getElementById("a2hsClose")?.addEventListener("click", () => hideBanner(true));

    // Ao carregar: em iOS que não está instalado, exibe o banner e o botão
    window.addEventListener("load", () => {
      if (isIOS() && !isStandalone()) {
        ensureBtn();
        scheduleBanner(false);
      }
      // registra SW com versão (evita cache antigo)
      if ("serviceWorker" in navigator) {
        const swBase = window.__SW_URL__ || "sw.js";
        const cacheToken = typeof window.__CACHE_BUSTER__ === "string" && window.__CACHE_BUSTER__.length
          ? window.__CACHE_BUSTER__
          : "";
        const swUrl = (function(url){
          if (!cacheToken) return url;
          if (/[?&](?:v|cb)=/i.test(url)) return url;
          const glue = url.includes("?") ? "&" : "?";
          return url + glue + "cb=" + encodeURIComponent(cacheToken);
        })(swBase);
        try { navigator.serviceWorker.register(swUrl); } catch(e){}
      }
    });

    // ========= Resto (helpers) =========
    function changeLanguage(code){
      const url = new URL(window.location);
      url.searchParams.set("lang", code);
      window.location.href = url.toString();
    }

    function toast(msg, kind="success"){
      const div = document.createElement("div");
      div.className = "fixed bottom-4 left-1/2 -translate-x-1/2 z-50 px-4 py-3 rounded-lg text-white "+(kind==="error"?"bg-red-600":"bg-green-600");
      div.textContent = msg;
      document.body.appendChild(div);
      setTimeout(()=>div.remove(), 2500);
    }

    const foodContactForm = document.querySelector("[data-food-contact-form]");
    if (foodContactForm) {
      foodContactForm.addEventListener("submit", (event) => {
        event.preventDefault();
        const formData = new FormData(foodContactForm);
        const targetEmail = (foodContactForm.getAttribute("data-email") || "").trim();
        if (!targetEmail) {
          toast("Configure o e-mail da loja para receber mensagens.", "error");
          return;
        }
        const name = (formData.get("name") || "").toString().trim();
        const email = (formData.get("email") || "").toString().trim();
        const phone = (formData.get("phone") || "").toString().trim();
        const message = (formData.get("message") || "").toString().trim();
        const bodyLines = [
          name ? `Nome: ${name}` : "",
          email ? `E-mail: ${email}` : "",
          phone ? `Telefone: ${phone}` : "",
          "",
          "Mensagem:",
          message || "(sem mensagem)"
        ].filter(Boolean).join("\n");
        const subject = "Contato - Rancho Nossa Terra";
        const mailtoUrl = `mailto:${encodeURIComponent(targetEmail)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(bodyLines)}`;
        window.location.href = mailtoUrl;
        toast("Abrindo seu cliente de e-mail…", "success");
        foodContactForm.reset();
      });
    }

    function updateCartBadge(val){
      const b = document.getElementById("cart-badge");
      if (!b) return;
      if (val>0) { b.textContent = val; b.classList.remove("hidden"); }
      else { b.classList.add("hidden"); }
    }

    // === CSRF dinâmico ===
    async function getCsrf() {
      const r = await fetch("?route=csrf", {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });
      if (!r.ok) throw new Error("Falha ao obter CSRF");
      const j = await r.json();
      return j.csrf;
    }
    async function postWithCsrf(url, formData) {
      const token = await getCsrf();
      formData = formData || new FormData();
      if (!formData.has("csrf")) formData.append("csrf", token);
      const r = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-Token": token
        },
        body: formData
      });
      return r;
    }

    async function addToCart(productId, productName, quantity){
      const qty = Math.max(1, parseInt(quantity, 10) || 1);
      const form = new FormData();
      form.append("id",productId);
      form.append("qty", qty);
      try {
        const r = await postWithCsrf("?route=add_cart", form);
        if(!r.ok){ throw new Error("Erro no servidor"); }
        const j = await r.json();
        if(j.success){
          if (j.message) {
            toast(j.message, "success");
          } else {
            toast(productName+" adicionado!", "success");
          }
          updateCartBadge(j.cart_count || 0);
        }else{
          toast(j.error || "Falha ao adicionar", "error");
        }
      } catch(e){
        toast("Erro ao adicionar ao carrinho", "error");
      }
    }

    async function updateQuantity(id, delta){
      const form = new FormData();
      form.append("id", id);
      form.append("delta", delta);
      try {
        const r = await postWithCsrf("?route=update_cart", form);
        if(r.ok){ location.reload(); }
        else { toast("Erro ao atualizar carrinho", "error"); }
      } catch(e){
        toast("Erro ao atualizar carrinho", "error");
      }
    }
  </script>';

  if (!empty($cfg['custom_scripts']['body_end'])) {
    echo $cfg['custom_scripts']['body_end'];
  }

  echo '</body></html>';
}

if (!function_exists('render_food_theme_home')) {
  function render_food_theme_home(array $view): void {
    $heroClass = $view['hero_class'] ?? 'food-hero';
    $heroStyleAttr = $view['hero_style_attr'] ?? '';
    $heroBadge = $view['hero_badge'] ?? '';
    $heroTitle = $view['hero_title'] ?? '';
    $heroSubtitle = $view['hero_subtitle'] ?? '';
    $heroDescription = $view['hero_description'] ?? '';
    $heroPrimaryLabel = $view['hero_button_label'] ?? '';
    $heroPrimaryLink = $view['hero_button_link'] ?? '#produtos';
    $heroSecondaryLabel = $view['hero_button_secondary_label'] ?? '';
    $heroSecondaryLink = $view['hero_button_secondary_link'] ?? '#';
    $heroStats = $view['hero_stats'] ?? [];
    $productsHeading = $view['products_heading'] ?? '';
    $productsSubheading = $view['products_subheading'] ?? '';
    $searchLabel = $view['search_label'] ?? 'Buscar';
    $searchQuery = $view['search_query'] ?? '';
    $categories = $view['categories'] ?? [];
    $featured = $view['featured'] ?? ['items' => [], 'label' => '', 'title' => '', 'text' => '', 'subtitle' => ''];
    $products = $view['products'] ?? [];
    $resultCount = (int)($view['result_count'] ?? 0);
    $hasFilters = !empty($view['has_filters']);
    $valuesHeading = $view['values_heading'] ?? '';
    $valuesSubheading = $view['values_subheading'] ?? '';
    $valuesCards = $view['values_cards'] ?? [];
    $historyHeading = $view['history_heading'] ?? '';
    $historySubheading = $view['history_subheading'] ?? '';
    $historyDescription = $view['history_description'] ?? '';
    $historyCards = $view['history_cards'] ?? [];
    $historyStats = $view['history_stats'] ?? [];
    $historyImage = $view['history_image'] ?? '';
    $highlight = $view['highlight'] ?? [];
    $contactHeading = $view['contact_heading'] ?? '';
    $contactText = $view['contact_text'] ?? '';
    $contactEmail = $view['contact_email'] ?? '';
    $contactEmailRaw = $view['contact_email_raw'] ?? '';
    $contactPhone = $view['contact_phone'] ?? '';
    $contactAddress = $view['contact_address'] ?? '';
    $contactFormButton = $view['contact_form_button_label'] ?? 'Enviar Mensagem';
    $themePrimary = $view['theme_primary_color'] ?? '#16A34A';
    $featuredItems = $featured['items'] ?? [];
    $showHero = !empty($view['show_hero']);
    $showCategories = !empty($view['show_categories']);
    $showFeatured = !empty($view['show_featured']);
    $showProducts = !empty($view['show_products']);
    $showValues = !empty($view['show_values']);
    $showHistory = !empty($historyHeading) || !empty($historyCards) || !empty($historyStats) || $historyImage !== '';
    $showHighlight = !empty($view['show_highlight']);
    $showContact = !empty($view['show_contact']);
    $groupProductsByCategory = !empty($view['products_group_by_category']);
    $uncategorizedLabel = $view['products_uncategorized_label'] ?? 'Outros sabores';
    $renderProductCards = static function (array $items): string {
      if (empty($items)) {
        return '';
      }
      ob_start();
      foreach ($items as $item) {
        ?>
        <article class="food-product-card">
          <a class="food-product-card__image" href="<?= $item['url']; ?>">
            <img src="<?= $item['image']; ?>" alt="<?= $item['name']; ?>">
            <?php if ($item['category'] !== ''): ?><span class="food-chip food-chip--floating"><?= $item['category']; ?></span><?php endif; ?>
            <?php if ($item['featured']): ?><span class="food-chip food-chip--badge">Destaque</span><?php endif; ?>
          </a>
          <div class="food-product-card__body">
            <div class="food-product-card__sku">SKU: <?= $item['sku']; ?></div>
            <h3 class="food-product-card__title"><a href="<?= $item['url']; ?>"><?= $item['name']; ?></a></h3>
            <?php if ($item['description'] !== ''): ?><p class="food-product-card__text"><?= $item['description']; ?></p><?php endif; ?>
            <div class="food-product-card__price">
              <?php if ($item['compare'] !== ''): ?><span class="food-product-card__price-old"><?= $item['compare']; ?></span><?php endif; ?>
              <span class="food-product-card__price-now"><?= $item['price']; ?></span>
              <span class="food-product-card__stock <?= $item['in_stock'] ? 'is-available' : 'is-empty'; ?>"><?= $item['in_stock'] ? 'Em estoque' : 'Indisponível'; ?></span>
            </div>
            <div class="food-product-card__actions">
              <a class="food-button food-button--ghost" href="<?= $item['url']; ?>"><i class="fa-solid fa-eye"></i> Ver detalhes</a>
              <?php if ($item['in_stock']): ?>
                <button class="food-button" type="button" onclick="addToCart(<?= $item['id']; ?>, <?= htmlspecialchars($item['name_json'], ENT_QUOTES, 'UTF-8'); ?>, 1)"><i class="fa-solid fa-cart-plus"></i> Adicionar</button>
              <?php else: ?>
                <button class="food-button food-button--disabled" type="button" disabled><i class="fa-solid fa-ban"></i> Indisponível</button>
              <?php endif; ?>
            </div>
          </div>
        </article>
        <?php
      }
      return ob_get_clean();
    };
    ?>
    <?php if ($showHero): ?>
      <section id="inicio" class="<?= htmlspecialchars($heroClass, ENT_QUOTES, 'UTF-8'); ?>"<?= $heroStyleAttr; ?>>
        <div class="food-hero__overlay">
          <div class="food-hero__content">
            <?php if ($heroBadge !== ''): ?>
              <span class="food-hero__badge"><?= $heroBadge; ?></span>
            <?php endif; ?>
            <h1 class="food-hero__title"><?= $heroTitle; ?></h1>
            <?php if ($heroSubtitle !== ''): ?>
              <p class="food-hero__subtitle"><?= $heroSubtitle; ?></p>
            <?php endif; ?>
            <?php if ($heroDescription !== ''): ?>
              <p class="food-hero__description"><?= $heroDescription; ?></p>
            <?php endif; ?>
            <div class="food-hero__actions">
              <?php if ($heroPrimaryLabel !== ''): ?>
                <a class="food-button" href="<?= $heroPrimaryLink; ?>"><i class="fa-solid fa-basket-shopping"></i> <?= $heroPrimaryLabel; ?></a>
              <?php endif; ?>
              <?php if ($heroSecondaryLabel !== ''): ?>
                <a class="food-button food-button--secondary" href="<?= $heroSecondaryLink; ?>"><i class="fa-solid fa-comments"></i> <?= $heroSecondaryLabel; ?></a>
              <?php endif; ?>
            </div>
            <?php if (!empty($heroStats)): ?>
              <div class="food-hero__stats">
                <?php foreach ($heroStats as $stat): ?>
                  <div class="food-hero__stat">
                    <div class="food-hero__stat-icon"><i class="fa-solid <?= $stat['icon']; ?>"></i></div>
                    <div class="food-hero__stat-body">
                      <?php if ($stat['title'] !== ''): ?><div class="food-hero__stat-title"><?= $stat['title']; ?></div><?php endif; ?>
                      <?php if ($stat['description'] !== ''): ?><p class="food-hero__stat-text"><?= $stat['description']; ?></p><?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php else: ?>
      <section id="inicio" class="food-section food-section--products pt-8 pb-6">
        <div class="max-w-6xl mx-auto px-4">
          <form method="get" class="food-search">
            <input type="hidden" name="route" value="home">
            <input class="food-search__input" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?= $searchLabel; ?>...">
            <button class="food-search__button" type="submit"><i class="fa-solid fa-magnifying-glass"></i><?= $searchLabel; ?></button>
          </form>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($showProducts): ?>
      <section id="produtos" class="food-section food-section--products">
        <div class="max-w-6xl mx-auto px-4">
          <div class="food-products-head">
            <div>
              <h2 class="food-section-title"><?= $productsHeading; ?></h2>
              <?php if ($productsSubheading !== ''): ?>
                <p class="food-section-subtitle"><?= $productsSubheading; ?></p>
              <?php endif; ?>
            </div>
            <form method="get" class="food-search">
              <input type="hidden" name="route" value="home">
              <input class="food-search__input" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?= $searchLabel; ?>...">
              <button class="food-search__button" type="submit"><i class="fa-solid fa-magnifying-glass"></i><?= $searchLabel; ?></button>
            </form>
          </div>
          <?php if ($showCategories && !empty($categories)): ?>
          <div class="food-category-chips">
            <?php foreach ($categories as $cat): ?>
              <a class="food-chip<?= $cat['active'] ? ' food-chip--active' : ''; ?>" href="<?= $cat['url']; ?>"><span><?= $cat['label']; ?></span></a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($showFeatured && !empty($featuredItems)): ?>
      <section class="food-section food-section--featured">
        <div class="max-w-6xl mx-auto px-4 space-y-6">
          <div class="food-featured__header">
            <?php if (!empty($featured['label'])): ?><span class="food-featured__badge"><?= $featured['label']; ?></span><?php endif; ?>
            <div>
              <?php if (!empty($featured['title'])): ?><h3 class="food-section-title text-white"><?= $featured['title']; ?></h3><?php endif; ?>
              <?php if (!empty($featured['text'])): ?><p class="food-section-subtitle text-white/85"><?= $featured['text']; ?></p><?php endif; ?>
              <?php if (!empty($featured['subtitle'])): ?><p class="food-section-note text-white/70"><?= $featured['subtitle']; ?></p><?php endif; ?>
            </div>
          </div>
          <div class="food-featured__grid">
            <?php foreach ($featuredItems as $item): ?>
              <article class="food-featured__card">
                <a class="food-featured__image" href="<?= $item['url']; ?>"><img src="<?= $item['image']; ?>" alt="<?= $item['name']; ?>"></a>
                <div class="food-featured__body">
                  <?php if ($item['category'] !== ''): ?><span class="food-chip food-chip--ghost"><?= $item['category']; ?></span><?php endif; ?>
                  <h4 class="food-featured__title"><a href="<?= $item['url']; ?>"><?= $item['name']; ?></a></h4>
                  <?php if ($item['description'] !== ''): ?><p class="food-featured__text"><?= $item['description']; ?></p><?php endif; ?>
                  <div class="food-featured__price">
                    <?php if ($item['compare'] !== ''): ?><span class="food-featured__price-old"><?= $item['compare']; ?></span><?php endif; ?>
                    <span class="food-featured__price-now"><?= $item['price']; ?></span>
                  </div>
                  <div class="food-featured__actions">
                    <a class="food-button food-button--ghost" href="<?= $item['url']; ?>"><i class="fa-solid fa-eye"></i> Ver detalhes</a>
                    <button class="food-button" type="button" onclick="addToCart(<?= $item['id']; ?>, <?= htmlspecialchars($item['name_json'], ENT_QUOTES, 'UTF-8'); ?>, 1)"><i class="fa-solid fa-cart-plus"></i> Adicionar</button>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($showProducts): ?>
      <section class="food-section">
        <div class="max-w-6xl mx-auto px-4">
          <?php if ($hasFilters): ?>
            <div class="food-section-note"><?= $resultCount; ?> resultado(s)<?= $searchQuery !== '' ? ' • busca: <span class="font-semibold">'.htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8').'</span>' : ''; ?></div>
          <?php endif; ?>

          <?php if (empty($products)): ?>
            <div class="food-empty-state">
              <div class="food-empty-state__icon"><i class="fa-solid fa-bowl-food"></i></div>
              <div class="food-empty-state__title">Nenhum produto encontrado</div>
              <p class="food-empty-state__text">Ajuste os filtros ou pesquise novamente para encontrar novidades.</p>
              <a class="food-button" href="?route=home">Limpar filtros</a>
            </div>
          <?php else: ?>
            <?php if ($groupProductsByCategory): ?>
              <?php
                $groupedProducts = [];
                foreach ($products as $productItem) {
                  $groupKey = $productItem['category'] !== '' ? $productItem['category'] : $uncategorizedLabel;
                  if (!isset($groupedProducts[$groupKey])) {
                    $groupedProducts[$groupKey] = [];
                  }
                  $groupedProducts[$groupKey][] = $productItem;
                }
              ?>
              <div class="food-category-groups space-y-10">
                <?php foreach ($groupedProducts as $categoryName => $groupItems): ?>
                  <div class="food-category-group">
                    <div class="food-category-heading">
                      <span class="font-semibold leading-tight store-brand-name text-lg md:text-xl"><?= $categoryName; ?></span>
                      <span class="food-category-heading__line" aria-hidden="true"></span>
                    </div>
                    <div class="food-products-grid mt-5">
                      <?= $renderProductCards($groupItems); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="food-products-grid">
                <?= $renderProductCards($products); ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($showHistory): ?>
      <section id="historia" class="food-section food-section--history">
        <div class="max-w-6xl mx-auto px-4 food-history">
          <div class="food-history__lead">
            <div>
              <span class="food-section-badge" style="background-color: <?= htmlspecialchars($themePrimary, ENT_QUOTES, 'UTF-8'); ?>1a; color: <?= htmlspecialchars($themePrimary, ENT_QUOTES, 'UTF-8'); ?>;">Nossa jornada</span>
              <?php if ($historyHeading !== ''): ?><h2 class="food-section-title"><?= $historyHeading; ?></h2><?php endif; ?>
              <?php if ($historySubheading !== ''): ?><p class="food-section-subtitle"><?= $historySubheading; ?></p><?php endif; ?>
            </div>
            <?php if ($historyDescription !== ''): ?>
              <p class="text-base text-amber-900/90 leading-relaxed"><?= $historyDescription; ?></p>
            <?php endif; ?>
            <?php if (!empty($historyStats)): ?>
              <div class="food-history__stats">
                <?php foreach ($historyStats as $stat): ?>
                  <div class="food-history__stat">
                    <div class="food-history__stat-value" style="color: <?= htmlspecialchars($stat['color'], ENT_QUOTES, 'UTF-8'); ?>;"><?= $stat['value']; ?></div>
                    <div class="food-history__stat-label"><?= $stat['label']; ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="food-history__image">
            <?php if ($historyImage !== ''): ?>
              <img src="<?= $historyImage; ?>" alt="História Rancho Nossa Terra">
            <?php else: ?>
              <img src="https://images.unsplash.com/photo-1528712306091-ed0763094c98?auto=format&fit=crop&w=800&q=80" alt="Cozinha artesanal brasileira">
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($showValues && !empty($valuesCards)): ?>
      <section class="food-section food-section--values">
        <div class="max-w-6xl mx-auto px-4 space-y-6">
          <div class="food-section-head">
            <span class="food-section-badge" style="background-color: <?= htmlspecialchars($themePrimary, ENT_QUOTES, 'UTF-8'); ?>1a; color: <?= htmlspecialchars($themePrimary, ENT_QUOTES, 'UTF-8'); ?>;">Nossos valores</span>
            <h2 class="food-section-title"><?= $valuesHeading; ?></h2>
            <?php if ($valuesSubheading !== ''): ?><p class="food-section-subtitle"><?= $valuesSubheading; ?></p><?php endif; ?>
          </div>
          <div class="food-values-grid">
            <?php foreach ($valuesCards as $card): ?>
              <article class="food-values-card">
                <div class="food-values-card__icon"><i class="fa-solid <?= $card['icon']; ?>"></i></div>
                <?php if ($card['title'] !== ''): ?><h3 class="food-values-card__title"><?= $card['title']; ?></h3><?php endif; ?>
                <?php if ($card['description'] !== ''): ?><p class="food-values-card__text"><?= $card['description']; ?></p><?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($showHighlight && (!empty($highlight['heading']) || !empty($highlight['text']) || !empty($highlight['image']))): ?>
      <section class="food-section food-section--highlight">
        <div class="max-w-6xl mx-auto px-4">
          <div class="food-highlight">
            <div class="food-highlight__content">
              <span class="food-section-badge" style="background-color: <?= htmlspecialchars($themePrimary, ENT_QUOTES, 'UTF-8'); ?>1a; color: <?= htmlspecialchars($themePrimary, ENT_QUOTES, 'UTF-8'); ?>;">Experiência completa</span>
              <?php if (!empty($highlight['heading'])): ?><h2 class="food-section-title"><?= $highlight['heading']; ?></h2><?php endif; ?>
              <?php if (!empty($highlight['subheading'])): ?><p class="food-section-subtitle"><?= $highlight['subheading']; ?></p><?php endif; ?>
              <?php if (!empty($highlight['text'])): ?><p class="food-highlight__text"><?= $highlight['text']; ?></p><?php endif; ?>
              <?php if (!empty($highlight['button_label'])): ?>
                <a class="food-button" href="<?= $highlight['button_link']; ?>"><i class="fa-solid fa-arrow-right"></i> <?= $highlight['button_label']; ?></a>
              <?php endif; ?>
            </div>
            <?php if (!empty($highlight['image'])): ?>
              <div class="food-highlight__image">
                <img src="<?= $highlight['image']; ?>" alt="<?= $highlight['heading'] ?? 'Sabores da Fazenda'; ?>">
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($showContact && ($contactEmail !== '' || $contactPhone !== '' || $contactAddress !== '' || $contactText !== '')): ?>
      <section id="contato" class="food-section food-section--contact">
        <div class="max-w-6xl mx-auto px-4 food-contact">
          <div class="food-contact__content">
            <span class="food-section-badge" style="background-color: <?= htmlspecialchars($themePrimary, ENT_QUOTES, 'UTF-8'); ?>1a; color: <?= htmlspecialchars($themePrimary, ENT_QUOTES, 'UTF-8'); ?>;">Contato</span>
            <h2 class="food-section-title"><?= $contactHeading; ?></h2>
            <?php if ($contactText !== ''): ?><p class="food-section-subtitle"><?= $contactText; ?></p><?php endif; ?>
            <div class="food-contact__details">
              <?php if ($contactEmail !== ''): ?>
                <div><i class="fa-solid fa-envelope"></i> <a href="mailto:<?= $contactEmail; ?>"><?= $contactEmail; ?></a></div>
              <?php endif; ?>
              <?php if ($contactPhone !== ''): ?>
                <div><i class="fa-solid fa-phone"></i> <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contactPhone); ?>"><?= $contactPhone; ?></a></div>
              <?php endif; ?>
              <?php if ($contactAddress !== ''): ?>
                <div><i class="fa-solid fa-location-dot"></i> <?= $contactAddress; ?></div>
              <?php endif; ?>
            </div>
          </div>
          <form class="food-contact__form" data-food-contact-form data-email="<?= htmlspecialchars($contactEmailRaw, ENT_QUOTES, 'UTF-8'); ?>" action="#" method="post">
            <div class="food-contact__grid">
              <div>
                <label class="block text-xs font-medium mb-1 text-amber-900/70">Nome completo</label>
                <input type="text" name="name" required placeholder="Seu nome">
              </div>
              <div>
                <label class="block text-xs font-medium mb-1 text-amber-900/70">E-mail</label>
                <input type="email" name="email" required placeholder="voce@email.com">
              </div>
              <div>
                <label class="block text-xs font-medium mb-1 text-amber-900/70">Telefone</label>
                <input type="tel" name="phone" placeholder="(00) 00000-0000">
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium mb-1 text-amber-900/70">Mensagem</label>
              <textarea name="message" required placeholder="Conte como podemos ajudar"></textarea>
            </div>
            <button class="food-button" type="submit"><i class="fa-solid fa-paper-plane"></i> <?= $contactFormButton; ?></button>
          </form>
        </div>
      </section>
    <?php endif; ?>
    <?php
  }
}

/* ======================
   ROUTES
   ====================== */

// HOME — busca + categorias + listagem
if ($route === 'home') {
  app_header();
  $pdo = db();

  $builderHtml = '';
  $builderCss  = '';
  try {
    $stLayout = $pdo->prepare("SELECT content, styles FROM page_layouts WHERE page_slug = ? AND status = 'published' LIMIT 1");
    $stLayout->execute(['home']);
    $layoutRow = $stLayout->fetch(PDO::FETCH_ASSOC);
    if ($layoutRow) {
      $builderHtml = sanitize_builder_output($layoutRow['content'] ?? '');
      $builderCss  = trim((string)($layoutRow['styles'] ?? ''));
    }
  } catch (Throwable $e) {
    $builderHtml = '';
    $builderCss = '';
  }
  $hasCustomLayout = ($builderHtml !== '');

  $q = trim((string)($_GET['q'] ?? ''));
  $category_id = (int)($_GET['category'] ?? 0);

  // categorias ativas
  $categories = [];
  try {
    $sqlCategories = "
      SELECT DISTINCT c.*
      FROM categories c
      INNER JOIN products p ON p.category_id = c.id AND p.active = 1
      WHERE c.active = 1
      ORDER BY c.sort_order, c.name
    ";
    $categories = $pdo->query($sqlCategories)->fetchAll();
  } catch (Throwable $e) { /* sem categorias ainda */ }

  $homeSectionsVisibility = home_sections_visibility();
  $categoryIconMap = food_theme_category_icon_defaults();
  $activeThemeHome = active_store_theme();
  $themeFoodConfig = $activeThemeHome === 'food' ? theme_food_config() : null;
  $themePrimaryColor = setting_get('theme_color', '#2060C8');

  $heroTitle = setting_get('home_hero_title', 'Tudo para sua saúde');
  $heroSubtitle = setting_get('home_hero_subtitle', 'Experiência de app, rápida e segura.');
  $heroTitleHtml = htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8');
  $heroSubtitleHtml = htmlspecialchars($heroSubtitle, ENT_QUOTES, 'UTF-8');
  $heroBackground = setting_get('hero_background', 'gradient');
  $heroAccentColor = setting_get('hero_accent_color', '#F59E0B');
  $heroBackgroundImage = setting_get('hero_background_image', '');
  $featuredEnabled = (int)setting_get('home_featured_enabled', '0') === 1;
  $featuredTitle = setting_get('home_featured_title', 'Ofertas em destaque');
  $featuredSubtitleHtml = feature_allow_html(setting_get('home_featured_subtitle', 'Seleção especial com preços imperdíveis.'));
  $featuredLabelHtml = feature_allow_html(setting_get('home_featured_label', 'Oferta destaque'));
  $featuredBadgeTitleHtml = feature_allow_html(setting_get('home_featured_badge_title', 'Seleção especial'));
  $featuredBadgeTextHtml = feature_allow_html(setting_get('home_featured_badge_text', 'Selecionados com carinho para você'));
  $featuredTitleHtml = htmlspecialchars($featuredTitle, ENT_QUOTES, 'UTF-8');

  if ($hasCustomLayout) {
    if ($builderCss !== '') {
      echo '<style id="home-builder-css">'.$builderCss.'</style>';
    }
    echo '<section class="home-custom-layout">'.$builderHtml.'</section>';
    if (!empty($homeSectionsVisibility['products'])) {
      echo '<section class="max-w-7xl mx-auto px-4 pt-6 pb-4">';
      echo '  <form method="get" class="bg-white rounded-2xl shadow px-4 py-4 flex flex-col lg:flex-row gap-3 items-stretch">';
      echo '    <input type="hidden" name="route" value="home">';
      echo '    <input class="flex-1 rounded-xl px-4 py-3 border border-gray-200" name="q" value="'.htmlspecialchars($q).'" placeholder="'.htmlspecialchars($d['search'] ?? 'Buscar').'...">';
      echo '    <button class="px-5 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><i class="fa-solid fa-search mr-2"></i>'.htmlspecialchars($d['search'] ?? 'Buscar').'</button>';
      echo '  </form>';
      echo '</section>';
    }
  } else {
    if ($activeThemeHome === 'food') {
      $foodHeroMode = $themeFoodConfig['hero_background_mode'] ?? 'image';
      $foodHeroImage = trim((string)($themeFoodConfig['hero_background_image'] ?? ''));
      $foodHeroColor = trim((string)($themeFoodConfig['hero_background_color'] ?? '#6DBA43'));
      if ($foodHeroColor === '') {
        $foodHeroColor = '#6DBA43';
      }
      $foodHeroStyleParts = [];
      if ($foodHeroMode === 'image' && $foodHeroImage !== '') {
        $foodHeroStyleParts[] = 'background-image:url('.htmlspecialchars($foodHeroImage, ENT_QUOTES, 'UTF-8').')';
      }
      $foodHeroStyleParts[] = 'background-color:'.htmlspecialchars($foodHeroColor, ENT_QUOTES, 'UTF-8');
      $foodHeroStyleAttr = ' style="'.implode(';', $foodHeroStyleParts).'"';
      $foodHeroClass = 'food-hero';
      if ($foodHeroMode !== 'image' || $foodHeroImage === '') {
        $foodHeroClass .= ' food-hero--solid';
      }

      $foodHeroBadge = trim((string)($themeFoodConfig['hero_badge'] ?? ''));
      $foodHeroTitle = trim((string)($themeFoodConfig['hero_title'] ?? 'Sabores da Fazenda'));
      $foodHeroSubtitle = trim((string)($themeFoodConfig['hero_subtitle'] ?? ''));
      $foodHeroButtonLabel = trim((string)($themeFoodConfig['hero_button_label'] ?? 'Conheça nossos produtos'));
      $foodHeroButtonLink = trim((string)($themeFoodConfig['hero_button_link'] ?? '#produtos'));
      if ($foodHeroButtonLink === '') {
        $foodHeroButtonLink = '#produtos';
      }

      $heroStatsData = [];
      if (!empty($themeFoodConfig['hero_stats']) && is_array($themeFoodConfig['hero_stats'])) {
        foreach ($themeFoodConfig['hero_stats'] as $stat) {
          $iconRaw = trim((string)($stat['icon'] ?? 'fa-seedling'));
          $iconClean = preg_replace('/[^a-z0-9\\- ]/i', '', $iconRaw);
          if ($iconClean === '') {
            $iconClean = 'fa-seedling';
          }
          $heroStatsData[] = [
            'icon' => htmlspecialchars($iconClean, ENT_QUOTES, 'UTF-8'),
            'title' => htmlspecialchars(trim((string)($stat['title'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars(trim((string)($stat['description'] ?? '')), ENT_QUOTES, 'UTF-8'),
          ];
        }
      }

  $normalizeCategoryIcon = static function ($value): string {
    $clean = trim((string)$value);
    if ($clean === '') {
      return '';
    }
    $clean = preg_replace('/[^a-z0-9\\-_: ]/i', '', $clean);
    if (!is_string($clean)) {
      return '';
    }
    return trim($clean);
  };

  $categoriesData = [];
  $categoriesData[] = [
    'label' => htmlspecialchars('Todas', ENT_QUOTES, 'UTF-8'),
    'url' => htmlspecialchars('?route=home', ENT_QUOTES, 'UTF-8'),
    'active' => ($category_id === 0),
    'icon' => $normalizeCategoryIcon($categoryIconMap['todas'] ?? '')
  ];
  foreach ($categories as $cat) {
    $catId = (int)($cat['id'] ?? 0);
    $catName = $cat['name'] ?? '';
    $storedIcon = $normalizeCategoryIcon($cat['icon'] ?? '');
    $iconClass = $storedIcon;
    if ($iconClass === '') {
      $iconKey = mb_strtolower($catName, 'UTF-8');
      $fallback = $categoryIconMap[$iconKey] ?? '';
      $iconClass = $normalizeCategoryIcon($fallback);
    }
    $categoriesData[] = [
      'label' => htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'),
      'url' => htmlspecialchars('?route=home&category='.$catId, ENT_QUOTES, 'UTF-8'),
      'active' => ($category_id === $catId),
      'icon' => $iconClass
    ];
  }

  if (count($categoriesData) <= 1) {
    $sampleCategories = [
      ['label' => 'Doces de Leite', 'icon' => 'ri-heart-line'],
      ['label' => 'Conservas', 'icon' => 'ri-plant-line'],
      ['label' => 'Queijos', 'icon' => 'ri-cake-line'],
      ['label' => 'Mel', 'icon' => 'ri-drop-line'],
      ['label' => 'Biscoitos', 'icon' => 'ri-cookie-line'],
      ['label' => 'Cristalizados', 'icon' => 'ri-star-line'],
      ['label' => 'Geleias', 'icon' => 'ri-bubble-chart-line'],
      ['label' => 'Conservas Picantes', 'icon' => 'ri-fire-line'],
    ];
    foreach ($sampleCategories as $sample) {
      $categoriesData[] = [
        'label' => htmlspecialchars($sample['label'], ENT_QUOTES, 'UTF-8'),
        'url' => htmlspecialchars('#produtos', ENT_QUOTES, 'UTF-8'),
        'active' => false,
        'icon' => $normalizeCategoryIcon($sample['icon'])
      ];
    }
  }

      $featuredProducts = [];
      $featuredIds = [];
      if ($featuredEnabled && $q === '' && $category_id === 0) {
        try {
          $featuredStmt = $pdo->prepare("SELECT p.*, c.name AS category_name, pd.short_description FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN product_details pd ON pd.product_id = p.id WHERE p.active = 1 AND p.featured = 1 ORDER BY p.updated_at DESC, p.id DESC LIMIT 8");
          $featuredStmt->execute();
          $featuredProducts = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
          $featuredProducts = [];
        }
        if ($featuredProducts) {
          $featuredIds = array_map('intval', array_column($featuredProducts, 'id'));
        } else {
          $featuredEnabled = false;
        }
      }

      $whereFood = ["p.active=1"];
      $paramsFood = [];
      if ($q !== '') {
        $whereFood[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? )";
        $like = "%$q%";
        $paramsFood[] = $like;
        $paramsFood[] = $like;
        $paramsFood[] = $like;
      }
      if ($category_id > 0) {
        $whereFood[] = "p.category_id = ?";
        $paramsFood[] = $category_id;
      }
      if ($featuredIds) {
        $placeholders = implode(',', array_fill(0, count($featuredIds), '?'));
        $whereFood[] = "p.id NOT IN ($placeholders)";
        $paramsFood = array_merge($paramsFood, $featuredIds);
      }
      $whereSqlFood = 'WHERE '.implode(' AND ', $whereFood);
      $sqlFood = "SELECT p.*, c.name AS category_name, pd.short_description
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                  LEFT JOIN product_details pd ON pd.product_id = p.id
                  $whereSqlFood
                  ORDER BY p.featured DESC, p.created_at DESC";
      $stmtFood = $pdo->prepare($sqlFood);
      $stmtFood->execute($paramsFood);
      $productsRaw = $stmtFood->fetchAll(PDO::FETCH_ASSOC);

      $featuredItems = [];
      foreach ($featuredProducts as $fp) {
        $fpNameRaw = trim((string)($fp['name'] ?? ''));
        $fpNameSafe = htmlspecialchars($fpNameRaw, ENT_QUOTES, 'UTF-8');
        $fpNameJson = json_encode($fpNameRaw, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $fpDescSrc = $fp['short_description'] ?? $fp['description'] ?? '';
        $fpDesc = htmlspecialchars(mb_substr(strip_tags($fpDescSrc), 0, 140), ENT_QUOTES, 'UTF-8');
        $fpImage = proxy_img($fp['image_path'] ?: 'assets/no-image.png');
        $fpImageAttr = htmlspecialchars($fpImage, ENT_QUOTES, 'UTF-8');
        $fpCategory = $fp['category_name'] ? htmlspecialchars($fp['category_name'], ENT_QUOTES, 'UTF-8') : '';
        $fpPrice = htmlspecialchars(format_currency((float)($fp['price'] ?? 0), strtoupper($fp['currency'] ?? ($cfg['store']['currency'] ?? 'USD'))), ENT_QUOTES, 'UTF-8');
        $compareValue = isset($fp['price_compare']) ? (float)$fp['price_compare'] : null;
        $compareFormatted = ($compareValue && $compareValue > (float)($fp['price'] ?? 0))
          ? htmlspecialchars(format_currency($compareValue, strtoupper($fp['currency'] ?? ($cfg['store']['currency'] ?? 'USD'))), ENT_QUOTES, 'UTF-8')
          : '';
        $productUrl = $fp['slug'] ? ('?route=product&slug='.urlencode($fp['slug'])) : ('?route=product&id='.(int)$fp['id']);
        $featuredItems[] = [
          'id' => (int)$fp['id'],
          'name' => $fpNameSafe,
          'name_json' => $fpNameJson,
          'description' => $fpDesc,
          'image' => $fpImageAttr,
          'category' => $fpCategory,
          'price' => $fpPrice,
          'compare' => $compareFormatted,
          'url' => htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8'),
          'featured' => !empty($fp['featured']),
        ];
      }

      $productsView = [];
      foreach ($productsRaw as $item) {
        $nameRaw = trim((string)($item['name'] ?? ''));
        $nameSafe = htmlspecialchars($nameRaw, ENT_QUOTES, 'UTF-8');
        $nameJson = json_encode($nameRaw, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $descSrc = $item['short_description'] ?? $item['description'] ?? '';
        $descSafe = htmlspecialchars(mb_substr(strip_tags($descSrc), 0, 140), ENT_QUOTES, 'UTF-8');
        $imagePath = proxy_img($item['image_path'] ?: 'assets/no-image.png');
        $imageAttr = htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8');
        $productUrl = $item['slug'] ? ('?route=product&slug='.urlencode($item['slug'])) : ('?route=product&id='.(int)$item['id']);
        $categoryName = $item['category_name'] ? htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') : '';
        $priceFormatted = htmlspecialchars(format_currency((float)($item['price'] ?? 0), strtoupper($item['currency'] ?? ($cfg['store']['currency'] ?? 'USD'))), ENT_QUOTES, 'UTF-8');
        $compareValue = isset($item['price_compare']) ? (float)$item['price_compare'] : null;
        $compareFormatted = ($compareValue && $compareValue > (float)($item['price'] ?? 0))
          ? htmlspecialchars(format_currency($compareValue, strtoupper($item['currency'] ?? ($cfg['store']['currency'] ?? 'USD'))), ENT_QUOTES, 'UTF-8')
          : '';
        $productsView[] = [
          'id' => (int)$item['id'],
          'name' => $nameSafe,
          'name_json' => $nameJson,
          'description' => $descSafe,
          'image' => $imageAttr,
          'url' => htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8'),
          'sku' => htmlspecialchars($item['sku'] ?? '', ENT_QUOTES, 'UTF-8'),
          'category' => $categoryName,
          'price' => $priceFormatted,
          'compare' => $compareFormatted,
          'in_stock' => ((int)($item['stock'] ?? 0) > 0),
          'featured' => !empty($item['featured']),
        ];
      }

      $valuesCardsView = [];
      if (!empty($themeFoodConfig['values_items']) && is_array($themeFoodConfig['values_items'])) {
        foreach ($themeFoodConfig['values_items'] as $card) {
          $iconRaw = trim((string)($card['icon'] ?? 'fa-seedling'));
          $iconClean = preg_replace('/[^a-z0-9\\- ]/i', '', $iconRaw);
          if ($iconClean === '') {
            $iconClean = 'fa-seedling';
          }
          $valuesCardsView[] = [
            'icon' => htmlspecialchars($iconClean, ENT_QUOTES, 'UTF-8'),
            'title' => htmlspecialchars(trim((string)($card['title'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars(trim((string)($card['description'] ?? '')), ENT_QUOTES, 'UTF-8'),
          ];
        }
      }

      $historyCardsView = [];
      if (!empty($themeFoodConfig['history_cards']) && is_array($themeFoodConfig['history_cards'])) {
        foreach ($themeFoodConfig['history_cards'] as $card) {
          $iconRaw = trim((string)($card['icon'] ?? 'fa-home-heart'));
          $iconClean = preg_replace('/[^a-z0-9\\- ]/i', '', $iconRaw);
          if ($iconClean === '') {
            $iconClean = 'fa-home-heart';
          }
          $historyCardsView[] = [
            'icon' => htmlspecialchars($iconClean, ENT_QUOTES, 'UTF-8'),
            'title' => htmlspecialchars(trim((string)($card['title'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars(trim((string)($card['description'] ?? '')), ENT_QUOTES, 'UTF-8'),
          ];
        }
      }

      $historyStatsView = [];
      if (!empty($themeFoodConfig['history_stats']) && is_array($themeFoodConfig['history_stats'])) {
        foreach ($themeFoodConfig['history_stats'] as $stat) {
          if (isset($stat['enabled']) && !$stat['enabled']) {
            continue;
          }
          $color = strtoupper(trim((string)($stat['color'] ?? '#16A34A')));
          if (!preg_match('/^#[0-9A-F]{3}(?:[0-9A-F]{3})?$/', $color)) {
            $color = '#16A34A';
          }
          $historyStatsView[] = [
            'label' => htmlspecialchars(trim((string)($stat['label'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'value' => htmlspecialchars(trim((string)($stat['value'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'color' => $color,
          ];
        }
      }

      $highlightHeadingRaw = trim((string)($themeFoodConfig['highlight_heading'] ?? 'Sabores da Fazenda'));
      $highlightSubheadingRaw = trim((string)($themeFoodConfig['highlight_subheading'] ?? ''));
      $highlightTextRaw = trim((string)($themeFoodConfig['highlight_text'] ?? ''));
      $highlightButtonLabelRaw = trim((string)($themeFoodConfig['highlight_button_label'] ?? 'Ver catálogo completo'));
      $highlightButtonLink = trim((string)($themeFoodConfig['highlight_button_link'] ?? '#produtos'));
      if ($highlightButtonLink === '') {
        $highlightButtonLink = '#produtos';
      }
      $highlightImage = trim((string)($themeFoodConfig['highlight_image'] ?? ''));
      if ($highlightImage !== '') {
        $highlightImage = proxy_img($highlightImage);
      }
      $highlightView = [
        'heading' => htmlspecialchars($highlightHeadingRaw, ENT_QUOTES, 'UTF-8'),
        'subheading' => htmlspecialchars($highlightSubheadingRaw, ENT_QUOTES, 'UTF-8'),
        'text' => htmlspecialchars($highlightTextRaw, ENT_QUOTES, 'UTF-8'),
        'button_label' => htmlspecialchars($highlightButtonLabelRaw, ENT_QUOTES, 'UTF-8'),
        'button_link' => htmlspecialchars($highlightButtonLink, ENT_QUOTES, 'UTF-8'),
        'image' => $highlightImage !== '' ? htmlspecialchars($highlightImage, ENT_QUOTES, 'UTF-8') : '',
      ];

      $storeInfoData = store_info();
      $contactHeadingRaw = trim((string)($themeFoodConfig['contact_heading'] ?? 'Fale com a gente'));
      $contactTextRaw = trim((string)($themeFoodConfig['contact_text'] ?? ''));
      $historyImagePath = trim((string)($themeFoodConfig['history_image'] ?? ''));
      $historyImageAttr = $historyImagePath !== '' ? htmlspecialchars(proxy_img($historyImagePath), ENT_QUOTES, 'UTF-8') : '';
      $contactFormButtonLabel = htmlspecialchars(trim((string)($themeFoodConfig['contact_form_button_label'] ?? 'Enviar Mensagem')), ENT_QUOTES, 'UTF-8');
      $heroDescriptionSanitized = htmlspecialchars(trim((string)($themeFoodConfig['hero_description'] ?? '')), ENT_QUOTES, 'UTF-8');
      $heroSecondaryLabel = htmlspecialchars(trim((string)($themeFoodConfig['hero_button_secondary_label'] ?? '')), ENT_QUOTES, 'UTF-8');
      $heroSecondaryLink = htmlspecialchars(trim((string)($themeFoodConfig['hero_button_secondary_link'] ?? '#')), ENT_QUOTES, 'UTF-8');

      $viewData = [
        'hero_class' => $foodHeroClass,
        'hero_style_attr' => $foodHeroStyleAttr,
        'hero_badge' => htmlspecialchars($foodHeroBadge, ENT_QUOTES, 'UTF-8'),
        'hero_title' => htmlspecialchars($foodHeroTitle, ENT_QUOTES, 'UTF-8'),
        'hero_subtitle' => htmlspecialchars($foodHeroSubtitle, ENT_QUOTES, 'UTF-8'),
        'hero_description' => $heroDescriptionSanitized,
        'hero_button_label' => htmlspecialchars($foodHeroButtonLabel, ENT_QUOTES, 'UTF-8'),
        'hero_button_link' => htmlspecialchars($foodHeroButtonLink, ENT_QUOTES, 'UTF-8'),
        'hero_button_secondary_label' => $heroSecondaryLabel,
        'hero_button_secondary_link' => $heroSecondaryLink,
        'hero_stats' => $heroStatsData,
        'products_heading' => htmlspecialchars(trim((string)($themeFoodConfig['products_heading'] ?? 'Nossos Produtos')), ENT_QUOTES, 'UTF-8'),
        'products_subheading' => htmlspecialchars(trim((string)($themeFoodConfig['products_subheading'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'products_group_by_category' => !empty($themeFoodConfig['products_group_by_category']),
        'products_uncategorized_label' => htmlspecialchars(($themeFoodConfig['products_uncategorized_label'] ?? '') !== '' ? $themeFoodConfig['products_uncategorized_label'] : 'Outros sabores', ENT_QUOTES, 'UTF-8'),
        'search_label' => htmlspecialchars($d['search'] ?? 'Buscar', ENT_QUOTES, 'UTF-8'),
        'search_query' => $q,
        'categories' => $categoriesData,
        'featured' => [
          'label' => htmlspecialchars(strip_tags($featuredLabelHtml), ENT_QUOTES, 'UTF-8'),
          'title' => htmlspecialchars(strip_tags($featuredBadgeTitleHtml), ENT_QUOTES, 'UTF-8'),
          'text' => htmlspecialchars(strip_tags($featuredBadgeTextHtml), ENT_QUOTES, 'UTF-8'),
          'subtitle' => htmlspecialchars(strip_tags($featuredSubtitleHtml), ENT_QUOTES, 'UTF-8'),
          'items' => $featuredItems,
        ],
        'products' => $productsView,
        'result_count' => count($productsView),
        'has_filters' => ($q !== '' || $category_id > 0),
        'values_heading' => htmlspecialchars(trim((string)($themeFoodConfig['values_heading'] ?? 'Nossos Valores')), ENT_QUOTES, 'UTF-8'),
        'values_subheading' => htmlspecialchars(trim((string)($themeFoodConfig['values_subheading'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'values_cards' => $valuesCardsView,
        'history_heading' => htmlspecialchars(trim((string)($themeFoodConfig['history_heading'] ?? 'Nossa História')), ENT_QUOTES, 'UTF-8'),
        'history_subheading' => htmlspecialchars(trim((string)($themeFoodConfig['history_subheading'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'history_description' => htmlspecialchars(trim((string)($themeFoodConfig['history_description'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'history_cards' => $historyCardsView,
        'history_stats' => $historyStatsView,
        'history_image' => $historyImageAttr,
        'highlight' => $highlightView,
        'contact_heading' => htmlspecialchars($contactHeadingRaw, ENT_QUOTES, 'UTF-8'),
        'contact_text' => htmlspecialchars($contactTextRaw, ENT_QUOTES, 'UTF-8'),
        'contact_email' => htmlspecialchars($storeInfoData['email'] ?? '', ENT_QUOTES, 'UTF-8'),
        'contact_phone' => htmlspecialchars($storeInfoData['phone'] ?? '', ENT_QUOTES, 'UTF-8'),
        'contact_address' => htmlspecialchars($storeInfoData['addr'] ?? '', ENT_QUOTES, 'UTF-8'),
        'contact_form_button_label' => $contactFormButtonLabel,
        'contact_email_raw' => (string)($storeInfoData['email'] ?? ''),
        'theme_primary_color' => htmlspecialchars($themePrimaryColor, ENT_QUOTES, 'UTF-8'),
        'show_hero' => !empty($homeSectionsVisibility['hero']),
        'show_categories' => !empty($homeSectionsVisibility['categories']),
        'show_featured' => !empty($homeSectionsVisibility['featured']),
        'show_products' => !empty($homeSectionsVisibility['products']),
        'show_values' => !empty($homeSectionsVisibility['values']),
        'show_highlight' => !empty($homeSectionsVisibility['highlight']),
        'show_contact' => !empty($homeSectionsVisibility['contact']),
      ];
      render_food_theme_home($viewData);
      app_footer();
      exit;
    }

    if (!empty($homeSectionsVisibility['hero'])) {
      $heroClasses = 'text-white py-12 md:py-16 mb-10';
      $heroStyleAttr = '';
      if ($heroBackground === 'gradient') {
        $heroStyleAttr = ' style="background: linear-gradient(135deg, '.htmlspecialchars($heroAccentColor, ENT_QUOTES, 'UTF-8').', rgba(32,96,200,0.95));"';
      } elseif ($heroBackground === 'solid') {
        $heroStyleAttr = ' style="background: '.htmlspecialchars($heroAccentColor, ENT_QUOTES, 'UTF-8').';"';
      } elseif ($heroBackground === 'image' && $heroBackgroundImage !== '') {
        $heroStyleAttr = ' style="background:url('.htmlspecialchars($heroBackgroundImage, ENT_QUOTES, 'UTF-8').') center / cover no-repeat;"';
      }
      $heroHighlights = [
      [
        'icon' => 'fa-shield-halved',
        'title' => 'Produtos Originais',
        'desc' => 'Nossos produtos são 100% originais e testados em laboratório.'
      ],
      [
        'icon' => 'fa-award',
        'title' => 'Qualidade e Segurança',
        'desc' => 'Compre com quem se preocupa com a qualidade dos produtos.'
      ],
      [
        'icon' => 'fa-plane-departure',
        'title' => 'Enviamos para todo EUA',
        'desc' => 'Entrega rápida e segura em todo o território norte-americano.'
      ],
      [
        'icon' => 'fa-lock',
        'title' => 'Site 100% Seguro',
        'desc' => 'Pagamentos protegidos pela nossa rede de segurança privada.'
      ],
    ];
      echo '<section class="'.$heroClasses.'"'.$heroStyleAttr.'>';
      echo '  <div class="max-w-7xl mx-auto px-4 hero-section">';
      echo '    <div class="grid lg:grid-cols-[1.1fr,0.9fr] gap-10 items-center">';
      echo '      <div class="text-left space-y-6">';
      echo '        <div>';
      echo '          <h2 class="text-3xl md:text-5xl font-bold mb-3 leading-tight">'.$heroTitleHtml.'</h2>';
      echo '          <p class="text-white/90 text-lg md:text-xl">'.$heroSubtitleHtml.'</p>';
      echo '        </div>';
      echo '        <form method="get" class="flex flex-col sm:flex-row gap-3 bg-white/10 p-2 rounded-2xl backdrop-blur">';
      echo '          <input type="hidden" name="route" value="home">';
      echo '          <input class="flex-1 rounded-xl px-4 py-3 text-gray-900 placeholder-gray-500 focus:ring-4 focus:ring-brand-200" name="q" value="'.htmlspecialchars($q).'" placeholder="'.htmlspecialchars($d['search'] ?? 'Buscar').'...">';
      echo '          <button class="px-5 py-3 rounded-xl bg-white text-brand-700 font-semibold hover:bg-brand-50 cta-button transition"><i class="fa-solid fa-search mr-2"></i>'.htmlspecialchars($d['search'] ?? 'Buscar').'</button>';
      echo '        </form>';
      echo '        <div class="flex flex-wrap items-center gap-4 text-white/80 text-sm">';
      echo '          <span class="flex items-center gap-2"><i class="fa-solid fa-clock text-white"></i> Atendimento humano rápido</span>';
      echo '          <span class="flex items-center gap-2"><i class="fa-solid fa-medal text-white"></i> Produtos verificados</span>';
      echo '        </div>';
      echo '      </div>';
      echo '      <div class="grid sm:grid-cols-2 gap-4">';
      foreach ($heroHighlights as $feature) {
        $title = htmlspecialchars($feature['title'], ENT_QUOTES, 'UTF-8');
        $desc  = htmlspecialchars($feature['desc'], ENT_QUOTES, 'UTF-8');
        $icon  = htmlspecialchars($feature['icon'], ENT_QUOTES, 'UTF-8');
        echo '        <div class="rounded-2xl border border-white/15 bg-white/10 p-5 shadow-lg backdrop-blur flex flex-col gap-2">';
        echo '          <span class="w-10 h-10 rounded-full bg-white/20 text-white grid place-items-center text-lg"><i class="fa-solid '.$icon.'"></i></span>';
        echo '          <div class="font-semibold">'.$title.'</div>';
        echo '          <p class="text-sm text-white/80 leading-relaxed">'.$desc.'</p>';
        echo '        </div>';
      }
      echo '      </div>';
      echo '    </div>';
      echo '  </div>';
      echo '</section>';
    } elseif (!empty($homeSectionsVisibility['products'])) {
      echo '<section class="max-w-7xl mx-auto px-4 pt-8 pb-6">';
      echo '  <form method="get" class="bg-white rounded-2xl shadow px-4 py-4 flex flex-col lg:flex-row gap-3 items-stretch">';
      echo '    <input type="hidden" name="route" value="home">';
      echo '    <input class="flex-1 rounded-xl px-4 py-3 border border-gray-200" name="q" value="'.htmlspecialchars($q).'" placeholder="'.htmlspecialchars($d['search'] ?? 'Buscar').'...">';
      echo '    <button class="px-5 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><i class="fa-solid fa-search mr-2"></i>'.htmlspecialchars($d['search'] ?? 'Buscar').'</button>';
      echo '  </form>';
      echo '</section>';
    }
  }

  // Filtros de categoria (chips)
  if (!empty($homeSectionsVisibility['categories'])) {
  if (!empty($homeSectionsVisibility['categories']) && !empty($categoriesData)) {
    echo '<section class="max-w-7xl mx-auto px-4">';
    echo '  <div class="flex items-center gap-3 flex-wrap mb-6">';
    foreach ($categoriesData as $chip) {
      $chipUrl = $chip['url'] ?? '#produtos';
      $isActiveChip = !empty($chip['active']);
      $chipClasses = 'chip px-4 py-2 rounded-full '.($isActiveChip ? 'bg-brand-600 text-white border-brand-600' : 'bg-white');
      $iconHtml = '';
      if (!empty($chip['icon'])) {
        $iconHtml = '<i class="'.htmlspecialchars($chip['icon'], ENT_QUOTES, 'UTF-8').' mr-2"></i>';
      }
      echo '    <a class="'.$chipClasses.'" href="'.$chipUrl.'">'.$iconHtml.$chip['label'].'</a>';
    }
    echo '  </div>';
    echo '</section>';
  }
  }

  // Sessão de destaque dinâmica (produtos marcados como "Destaque")
  $featuredProducts = [];
  $featuredIds = [];
  if ($featuredEnabled && $q === '' && $category_id === 0) {
    try {
      $featuredStmt = $pdo->prepare("SELECT p.*, c.name AS category_name, pd.short_description FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN product_details pd ON pd.product_id = p.id WHERE p.active = 1 AND p.featured = 1 ORDER BY p.updated_at DESC, p.id DESC LIMIT 8");
      $featuredStmt->execute();
      $featuredProducts = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      $featuredProducts = [];
    }
    if ($featuredProducts) {
      $featuredIds = array_map('intval', array_column($featuredProducts, 'id'));
    } else {
      $featuredEnabled = false;
    }
  }

  if ($featuredEnabled && $featuredProducts && !empty($homeSectionsVisibility['featured'])) {
    echo '<section class="relative overflow-hidden py-12">';
    echo '  <div class="absolute inset-0 bg-gradient-to-r from-[#0f3d91] via-[#1f54c1] to-[#3a7bff] opacity-95"></div>';
    echo '  <div class="relative max-w-5xl mx-auto px-4 text-white space-y-6 text-center">';
    echo '    <div class="space-y-3">';
    echo '      <span class="inline-flex items-center justify-center gap-2 text-xs uppercase tracking-[0.35em] text-white/70"><i class="fa-solid fa-bolt"></i> '.$featuredLabelHtml.'</span>';
    echo '      <h1 class="text-4xl md:text-5xl font-bold">'.$featuredBadgeTitleHtml.'</h1>';
    if ($featuredBadgeTextHtml !== '') {
      echo '      <p class="text-white/80 text-base md:text-lg max-w-2xl mx-auto">'.$featuredBadgeTextHtml.'</p>';
    }
    echo '      <h2 class="text-2xl md:text-3xl font-semibold">'.$featuredTitleHtml.'</h2>';
    if ($featuredSubtitleHtml !== '') {
      echo '      <p class="text-white/80 text-base max-w-2xl mx-auto">'.$featuredSubtitleHtml.'</p>';
    }
    echo '    </div>';
    echo '    <div class="mt-6 flex gap-5 overflow-x-auto pb-2 snap-x snap-mandatory justify-start md:justify-center" style="-webkit-overflow-scrolling: touch;">';
    foreach ($featuredProducts as $fp) {
      $img = $fp['image_path'] ?: 'assets/no-image.png';
      $img = proxy_img($img);
      $nameHtml = htmlspecialchars($fp['name'], ENT_QUOTES, 'UTF-8');
      $short = $fp['short_description'] ?? $fp['description'] ?? '';
      $descHtml = htmlspecialchars(mb_substr(strip_tags($short), 0, 120), ENT_QUOTES, 'UTF-8');
      $categoryHtml = $fp['category_name'] ? htmlspecialchars($fp['category_name'], ENT_QUOTES, 'UTF-8') : 'Sem categoria';
      $priceValue = (float)($fp['price'] ?? 0);
      $productCurrency = strtoupper($fp['currency'] ?? ($cfg['store']['currency'] ?? 'USD'));
      $priceFormatted = format_currency($priceValue, $productCurrency);
      $compareValue = isset($fp['price_compare']) ? (float)$fp['price_compare'] : null;
      $compareFormatted = ($compareValue && $compareValue > $priceValue) ? format_currency($compareValue, $productCurrency) : '';
      $discountBadge = '';
      if ($compareValue && $compareValue > $priceValue && $compareValue > 0) {
        $savingPercent = max(1, min(90, (int)round((($compareValue - $priceValue) / $compareValue) * 100)));
        $discountBadge = '<span class="absolute top-3 right-3 bg-amber-400 text-white text-xs px-3 py-1 rounded-full">'.$savingPercent.'% OFF</span>';
      }
      $productUrl = $fp['slug'] ? ('?route=product&slug='.urlencode($fp['slug'])) : ('?route=product&id='.(int)$fp['id']);
      $inStock = ((int)($fp['stock'] ?? 0) > 0);
      echo '      <div class="min-w-[260px] md:min-w-[280px] bg-white/10 border border-white/20 rounded-3xl p-5 backdrop-blur-lg snap-start hover:border-white/50 transition-shadow shadow-lg flex flex-col">';
      echo '        <a href="'.$productUrl.'" class="block relative rounded-2xl overflow-hidden mb-4 bg-white">';
      echo '          <img src="'.htmlspecialchars($img, ENT_QUOTES, 'UTF-8').'" alt="'.$nameHtml.'" class="w-full h-44 object-cover transition-transform duration-700 hover:scale-105">';
      if (!empty($fp['category_name'])) {
        echo '          <span class="absolute top-3 left-3 bg-brand-600 text-white text-xs px-3 py-1 rounded-full">'.$categoryHtml.'</span>';
      }
      echo            $discountBadge;
      echo '        </a>';
      echo '        <div class="space-y-2 text-left flex-1">';
      echo '          <a href="'.$productUrl.'" class="text-lg font-semibold leading-tight hover:underline">'.$nameHtml.'</a>';
      echo '          <p class="text-sm text-white/80 line-clamp-3">'.$descHtml.'</p>';
      echo '          <div class="flex items-baseline gap-3">';
      if ($compareFormatted) {
        echo '            <span class="text-sm text-white/70 line-through">'.$compareFormatted.'</span>';
      }
      echo '            <span class="text-2xl font-bold text-white">'.$priceFormatted.'</span>';
      echo '          </div>';
      echo '        </div>';
      echo '        <div class="pt-4">';
      if ($inStock) {
        echo '          <button class="w-full px-4 py-3 rounded-xl bg-white text-brand-700 font-semibold shadow hover:bg-brand-50 transition" onclick="addToCart('.(int)$fp['id'].', \''.$nameHtml.'\', 1)"><i class="fa-solid fa-cart-plus mr-2"></i>Adicionar ao carrinho</button>';
      } else {
        echo '          <button class="w-full px-4 py-3 rounded-xl bg-white/30 text-white/70 font-semibold cursor-not-allowed"><i class="fa-solid fa-circle-exclamation mr-2"></i>Indisponível</button>';
      }
      echo '        </div>';
      echo '      </div>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '</section>';
  }

// Busca produtos
  $where = ["p.active=1"];
  $params = [];
  if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? )";
    $like = "%$q%"; $params[]=$like; $params[]=$like; $params[]=$like;
  }
  if ($category_id > 0) {
    $where[] = "p.category_id = ?"; $params[] = $category_id;
  }
  if ($featuredIds) {
    $placeholders = implode(',', array_fill(0, count($featuredIds), '?'));
    $where[] = "p.id NOT IN ($placeholders)";
    $params = array_merge($params, $featuredIds);
  }
  $whereSql = 'WHERE '.implode(' AND ', $where);

  $sql = "SELECT p.*, c.name AS category_name, pd.short_description
          FROM products p
          LEFT JOIN categories c ON c.id = p.category_id
          LEFT JOIN product_details pd ON pd.product_id = p.id
          $whereSql
          ORDER BY p.featured DESC, p.created_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $products = $stmt->fetchAll();

  if (!empty($homeSectionsVisibility['products'])) {
    echo '<section class="max-w-7xl mx-auto px-4 pb-12">';
    if ($q || $category_id) {
      echo '<div class="mb-4 text-sm text-gray-600">'.count($products).' resultado(s)';
      if ($q) echo ' • busca: <span class="font-medium text-brand-700">'.htmlspecialchars($q).'</span>';
      echo '</div>';
    }

    if (!$products) {
      echo '<div class="text-center py-16">';
      echo '  <i class="fa-solid fa-magnifying-glass text-5xl text-gray-300 mb-4"></i>';
      echo '  <div class="text-lg text-gray-600">Nenhum produto encontrado</div>';
      echo '  <a href="?route=home" class="inline-block mt-6 px-6 py-3 rounded-lg bg-brand-600 text-white hover:bg-brand-700">Voltar</a>';
      echo '</div>';
    } else {
      echo '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mobile-grid-products">';
      foreach ($products as $p) {
        $img = $p['image_path'] ?: 'assets/no-image.png';
        $img = proxy_img($img); // passa pelo proxy se for URL absoluta
        $productUrl = !empty($p['slug']) ? ('?route=product&slug='.urlencode($p['slug'])) : ('?route=product&id='.(int)$p['id']);
        $in_stock = ((int)$p['stock'] > 0);
        $priceValue = (float)($p['price'] ?? 0);
        $productCurrency = strtoupper($p['currency'] ?? ($cfg['store']['currency'] ?? 'USD'));
        $priceFormatted = format_currency($priceValue, $productCurrency);
        $compareValue = isset($p['price_compare']) ? (float)$p['price_compare'] : null;
        $compareFormatted = ($compareValue && $compareValue > $priceValue) ? format_currency($compareValue, $productCurrency) : '';
        $short = $p['short_description'] ?? $p['description'] ?? '';
        $shortText = htmlspecialchars(mb_substr(strip_tags($short), 0, 140), ENT_QUOTES, 'UTF-8');
        echo '<div class="product-card card rounded-2xl shadow hover:shadow-lg transition overflow-hidden flex flex-col">';
        echo '  <a href="'.$productUrl.'" class="relative h-48 overflow-hidden block bg-gray-50">';
        echo '    <img src="'.htmlspecialchars($img).'" class="w-full h-full object-cover transition-transform duration-300 hover:scale-105" alt="'.htmlspecialchars($p['name']).'">';
        if (!empty($p['category_name'])) {
          echo '    <div class="absolute top-3 right-3 text-xs bg-white/90 rounded-full px-2 py-1 text-brand-700">'.htmlspecialchars($p['category_name']).'</div>';
        }
        if (!empty($p['featured'])) {
          echo '    <div class="absolute top-3 left-3 text-[10px] bg-amber-400 text-white rounded-full px-2 py-1 font-bold">DESTAQUE</div>';
        }
        echo '  </a>';
        echo '  <div class="p-4 space-y-2 flex-1 flex flex-col">';
        echo '    <div class="text-sm text-gray-500">SKU: '.htmlspecialchars($p['sku']).'</div>';
        echo '    <a href="'.$productUrl.'" class="font-semibold text-lg text-gray-900 hover:text-brand-600">'.htmlspecialchars($p['name']).'</a>';
        echo '    <p class="text-sm text-gray-600 line-clamp-3">'.$shortText.'</p>';
        echo '    <div class="flex items-center justify-between pt-2 mt-auto">';
        if ($compareFormatted) {
          echo '      <div class="flex flex-col leading-tight">';
          echo '        <span class="text-xs text-gray-400 line-through">De '.$compareFormatted.'</span>';
          echo '        <span class="text-2xl font-bold text-gray-900">Por '.$priceFormatted.'</span>';
          echo '      </div>';
        } else {
          echo '      <div class="text-2xl font-bold text-gray-900">'.$priceFormatted.'</div>';
        }
        echo '      <div class="text-xs '.($in_stock?'text-green-600':'text-red-600').'">'.($in_stock?'Em estoque':'Indisponível').'</div>';
        echo '    </div>';
        echo '    <div class="grid grid-cols-1 gap-2 pt-2">';
        echo '      <a href="'.$productUrl.'" class="px-3 py-2 rounded-lg border text-center text-sm hover:border-brand-600 hover:text-brand-600 transition"><i class="fa-solid fa-eye mr-1"></i>Ver detalhes</a>';
        if ($in_stock) {
          echo '      <button class="px-3 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition" onclick="addToCart('.(int)$p['id'].', \''.htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8').'\', 1)"><i class="fa-solid fa-cart-plus mr-1"></i>Adicionar</button>';
        } else {
          echo '      <button class="px-3 py-2 rounded-lg bg-gray-300 text-gray-600 text-sm font-semibold cursor-not-allowed"><i class="fa-solid fa-ban mr-1"></i>Indisponível</button>';
        }
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
      }
      echo '</div>';
    }

    echo '</section>';
  }
  app_footer();
  exit;
}

// ACCOUNT AREA
if ($route === 'account') {
  app_header();
  $pdo = db();
  $errorMsg = null;

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    if (!empty($_POST['logout'])) {
      unset($_SESSION['account_portal_email']);
      header('Location: ?route=account');
      exit;
    }
    $emailInput = strtolower(trim((string)($_POST['email'] ?? '')));
    $orderIdInput = (int)($_POST['order_id'] ?? 0);
    if (!validate_email($emailInput) || $orderIdInput <= 0) {
      $errorMsg = 'Informe um e-mail válido e o número do pedido.';
    } else {
      $verify = $pdo->prepare("SELECT COUNT(*) FROM orders o INNER JOIN customers c ON c.id = o.customer_id WHERE o.id = ? AND LOWER(c.email) = ?");
      $verify->execute([$orderIdInput, $emailInput]);
      if ($verify->fetchColumn()) {
        $_SESSION['account_portal_email'] = $emailInput;
        header('Location: ?route=account');
        exit;
      } else {
        $errorMsg = 'Não encontramos nenhum pedido para esses dados. Verifique o número e o e-mail utilizados na compra.';
      }
    }
  }

  $accountEmail = $_SESSION['account_portal_email'] ?? null;
  $ordersList = [];
  if ($accountEmail) {
    $ordersStmt = $pdo->prepare("SELECT o.*, c.name AS customer_name, c.email AS customer_email FROM orders o INNER JOIN customers c ON c.id = o.customer_id WHERE LOWER(c.email) = ? ORDER BY o.created_at DESC");
    $ordersStmt->execute([$accountEmail]);
    $ordersList = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
  }

  $statusMap = [
    'pending' => ['Pendente', 'bg-amber-100 text-amber-700'],
    'paid' => ['Pago', 'bg-emerald-100 text-emerald-700'],
    'processing' => ['Em processamento', 'bg-blue-100 text-blue-700'],
    'shipped' => ['Enviado', 'bg-sky-100 text-sky-700'],
    'completed' => ['Concluído', 'bg-emerald-100 text-emerald-700'],
    'cancelled' => ['Cancelado', 'bg-red-100 text-red-700']
  ];

  echo '<section class="max-w-5xl mx-auto px-4 py-10">';
  echo '  <div class="bg-white rounded-3xl shadow-lg p-6 md:p-8 space-y-6">';
  echo '    <div class="flex items-start justify-between gap-4 flex-wrap">';
  echo '      <div>';
  echo '        <h2 class="text-2xl font-bold">Minha conta</h2>';
  echo '        <p class="text-sm text-gray-500">Consulte seus pedidos utilizando o e-mail cadastrado e o número do pedido.</p>';
  echo '      </div>';
  if ($accountEmail) {
    echo '      <form method="post" class="flex items-center gap-2">';
    echo '        <input type="hidden" name="csrf" value="'.csrf_token().'">';
    echo '        <input type="hidden" name="logout" value="1">';
    echo '        <span class="text-sm text-gray-600 hidden sm:inline">Logado como <strong>'.htmlspecialchars($accountEmail, ENT_QUOTES, 'UTF-8').'</strong></span>';
    echo '        <button type="submit" class="px-3 py-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm"><i class="fa-solid fa-right-from-bracket mr-1"></i>Sair</button>';
    echo '      </form>';
  }
  echo '    </div>';

  if ($errorMsg) {
    echo '    <div class="px-4 py-3 rounded-xl bg-red-50 text-red-700 flex items-center gap-2"><i class="fa-solid fa-circle-exclamation"></i><span>'.htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8').'</span></div>';
  }

  if (!$accountEmail) {
    echo '    <form method="post" class="grid md:grid-cols-2 gap-4">';
    echo '      <input type="hidden" name="csrf" value="'.csrf_token().'">';
    echo '      <div class="md:col-span-2">';
    echo '        <label class="block text-sm font-medium mb-1">E-mail utilizado na compra</label>';
    echo '        <input class="input w-full" type="email" name="email" required placeholder="ex.: cliente@exemplo.com">';
    echo '      </div>';
    echo '      <div>';
    echo '        <label class="block text-sm font-medium mb-1">Número do pedido</label>';
    echo '        <input class="input w-full" type="number" name="order_id" min="1" required placeholder="ex.: 1024">';
    echo '      </div>';
    echo '      <div class="md:col-span-2 flex justify-end">';
    echo '        <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-magnifying-glass mr-2"></i>Consultar pedidos</button>';
    echo '      </div>';
    echo '    </form>';
  } else {
    if (!$ordersList) {
      echo '    <div class="px-4 py-6 rounded-xl bg-gray-50 text-center text-gray-600">';
      echo '      <i class="fa-solid fa-box-open text-3xl mb-3"></i>';
      echo '      <p>Nenhum pedido encontrado para o e-mail informado.</p>';
      echo '    </div>';
    } else {
      foreach ($ordersList as $order) {
        $created = format_datetime($order['created_at'] ?? '');
        $statusKey = strtolower((string)($order['status'] ?? ''));
        $statusInfo = $statusMap[$statusKey] ?? [ucfirst($statusKey ?: 'Desconhecido'), 'bg-gray-100 text-gray-600'];
        $items = json_decode($order['items_json'] ?? '[]', true);
        if (!is_array($items)) { $items = []; }
        $orderCurrency = strtoupper($order['currency'] ?? ($cfg['store']['currency'] ?? 'USD'));
        $total = format_currency((float)($order['total'] ?? 0), $orderCurrency);
        $shippingCost = format_currency((float)($order['shipping_cost'] ?? 0), $orderCurrency);
        $subtotal = format_currency((float)($order['subtotal'] ?? 0), $orderCurrency);
        $track = trim((string)($order['track_token'] ?? ''));

        echo '    <div class="border border-gray-100 rounded-2xl p-5 space-y-4">';
        echo '      <div class="flex items-start justify-between gap-3 flex-wrap">';
        echo '        <div>';
        echo '          <div class="text-lg font-semibold">Pedido #'.(int)$order['id'].'</div>';
        echo '          <div class="text-xs text-gray-500">'.$created.'</div>';
        echo '        </div>';
        echo '        <span class="px-3 py-1 rounded-full text-xs font-semibold '.$statusInfo[1].'">'.$statusInfo[0].'</span>';
        echo '      </div>';

        if ($items) {
          echo '      <div class="space-y-2">';
          foreach ($items as $item) {
            $itemName = htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $itemQty = (int)($item['qty'] ?? 0);
            $itemPrice = format_currency((float)($item['price'] ?? 0), $item['currency'] ?? $orderCurrency);
            echo '        <div class="flex items-center justify-between text-sm border-b border-dotted pb-1">';
            echo '          <span>'.$itemName.' <span class="text-gray-500">(Qtd: '.$itemQty.')</span></span>';
            echo '          <span>'.$itemPrice.'</span>';
            echo '        </div>';
          }
          echo '      </div>';
        }

        echo '      <div class="grid md:grid-cols-3 gap-3 text-sm bg-gray-50 rounded-xl p-4">';
        echo '        <div><span class="text-gray-500 block text-xs uppercase tracking-wide">Subtotal</span><strong>'.$subtotal.'</strong></div>';
        echo '        <div><span class="text-gray-500 block text-xs uppercase tracking-wide">Frete</span><strong>'.$shippingCost.'</strong></div>';
        echo '        <div><span class="text-gray-500 block text-xs uppercase tracking-wide">Total</span><strong class="text-brand-700">'.$total.'</strong></div>';
        echo '      </div>';

        echo '      <div class="flex items-center justify-between gap-3 text-sm flex-wrap">';
        echo '        <div><strong>Pagamento:</strong> '.htmlspecialchars((string)($order['payment_method'] ?? '-'), ENT_QUOTES, 'UTF-8').'</div>';
        if ($track !== '') {
          $trackSafe = htmlspecialchars($track, ENT_QUOTES, 'UTF-8');
          echo '        <a class="text-brand-700 hover:underline flex items-center gap-1" href="?route=track&code='.$trackSafe.'"><i class="fa-solid fa-location-dot"></i> Acompanhar pedido</a>';
        }
        echo '      </div>';
        echo '    </div>';
      }
    }
  }

  echo '  </div>';
  echo '</section>';
  app_footer();
  exit;
}

// ADD TO CART (AJAX)
if ($route === 'add_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');

  // Aceita CSRF do body ou do header
  $csrfIncoming = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  if (!csrf_check($csrfIncoming)) {
    echo json_encode(['success'=>false, 'error'=>'CSRF inválido']); exit;
  }

  $pdo = db();
  $id  = (int)($_POST['id'] ?? 0);
  $qtyRequested = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;
  if ($qtyRequested < 1) {
    $qtyRequested = 1;
  }

  $st = $pdo->prepare("SELECT id, name, stock, active, currency FROM products WHERE id=? AND active=1");
  $st->execute([$id]);
  $prod = $st->fetch();
  if (!$prod) { echo json_encode(['success'=>false,'error'=>'Produto não encontrado']); exit; }
  if ((int)$prod['stock'] <= 0) { echo json_encode(['success'=>false,'error'=>'Produto fora de estoque']); exit; }

  $productCurrency = strtoupper($prod['currency'] ?? ($cfg['store']['currency'] ?? 'USD'));
  $cartCurrency = $_SESSION['cart_currency'] ?? null;
  if ($cartCurrency === null) {
    $_SESSION['cart_currency'] = $productCurrency;
  } elseif ($cartCurrency !== $productCurrency) {
    echo json_encode(['success'=>false,'error'=>'Carrinho aceita apenas produtos na moeda '.$cartCurrency.'. Remova itens anteriores para adicionar este.']);
    exit;
  }

  if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
  $currentQty = (int)($_SESSION['cart'][$id] ?? 0);
  $newQty = $currentQty + $qtyRequested;
  $stock = (int)$prod['stock'];
  $limited = false;
  if ($stock > 0 && $newQty > $stock) {
    $newQty = $stock;
    $limited = true;
  }
  $_SESSION['cart'][$id] = $newQty;

  // notificação (opcional)
  send_notification('cart_add','Produto ao carrinho', $prod['name'], ['product_id'=>$id]);

  $cartCount = array_sum($_SESSION['cart']);
  $message = $limited
    ? 'Adicionamos a quantidade máxima disponível em estoque.'
    : 'Produto adicionado ao carrinho!';

  echo json_encode([
    'success'=>true,
    'cart_count'=> $cartCount,
    'message' => $message
  ]);
  exit;
}

// CART
if ($route === 'cart') {
  app_header();
  $pdo = db();
  $cart = $_SESSION['cart'] ?? [];
  $ids  = array_keys($cart);
  $items = [];
  $subtotal = 0.0;
  $shippingTotal = 0.0;
  $cartCurrency = $_SESSION['cart_currency'] ?? null;
  $currencyMismatch = false;

  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT * FROM products WHERE id IN ($in) AND active=1");
    $st->execute($ids);
    foreach ($st as $p) {
      $pid = (int)$p['id'];
      if (!isset($cart[$pid])) { continue; }
      $qty = (int)$cart[$pid];
      if ($qty <= 0) { continue; }
      $productCurrency = strtoupper($p['currency'] ?? ($cfg['store']['currency'] ?? 'USD'));
      if ($cartCurrency === null) {
        $cartCurrency = $productCurrency;
        $_SESSION['cart_currency'] = $cartCurrency;
      } elseif ($cartCurrency !== $productCurrency) {
        $currencyMismatch = true;
      }
      $priceValue = (float)$p['price'];
      $line = $priceValue * $qty;
      $subtotal += $line;
      $ship = isset($p['shipping_cost']) ? (float)$p['shipping_cost'] : 7.00;
      if ($ship < 0) { $ship = 0; }
      $shippingTotal += $ship * $qty;
      $items[] = [
        'id'=>$pid,
        'sku'=>$p['sku'],
        'name'=>$p['name'],
        'price'=>$priceValue,
        'qty'=>$qty,
        'image'=>$p['image_path'],
        'stock'=>(int)$p['stock'],
        'shipping_cost'=>$ship,
        'currency'=>$productCurrency
      ];
    }
  }

  $shippingTotal = max(0, $shippingTotal);
  $cartTotal = $subtotal + $shippingTotal;
  if ($cartCurrency === null) {
    $cartCurrency = $cfg['store']['currency'] ?? 'USD';
  }

  echo '<section class="max-w-5xl mx-auto px-4 py-8">';
  echo '  <h2 class="text-2xl font-bold mb-6"><i class="fa-solid fa-bag-shopping mr-2 text-brand-700"></i>'.htmlspecialchars($d['cart'] ?? 'Carrinho').'</h2>';
  if ($currencyMismatch) {
    echo '  <div class="mb-4 p-4 rounded-xl border border-amber-300 bg-amber-50 text-amber-800 flex items-start gap-3"><i class="fa-solid fa-circle-exclamation mt-1"></i><span>Há produtos com moedas diferentes no carrinho. Ajuste os itens para uma única moeda antes de finalizar.</span></div>';
  }

  if (!$items) {
    unset($_SESSION['cart_currency']);
    echo '<div class="text-center py-16">';
    echo '  <i class="fa-solid fa-cart-shopping text-6xl text-gray-300 mb-4"></i>';
    echo '  <div class="text-gray-600 mb-6">Seu carrinho está vazio</div>';
    echo '  <a href="?route=home" class="px-6 py-3 rounded-lg bg-brand-600 text-white hover:bg-brand-700">Continuar comprando</a>';
    echo '</div>';
  } else {
    echo '<div class="bg-white rounded-2xl shadow overflow-hidden">';
    echo '  <div class="divide-y">';
    foreach ($items as $it) {
      $img = $it['image'] ?: 'assets/no-image.png';
      $img = proxy_img($img); // passa pelo proxy se for URL absoluta
      echo '  <div class="p-4 flex flex-col gap-4 md:flex-row md:items-center">';
      echo '    <div class="flex-shrink-0 mx-auto md:mx-0">';
      echo '      <img src="'.htmlspecialchars($img).'" class="w-24 h-24 md:w-20 md:h-20 object-cover rounded-lg" alt="produto">';
      echo '    </div>';
      echo '    <div class="flex-1 text-center md:text-left">';
      echo '      <div class="font-semibold">'.htmlspecialchars($it['name']).'</div>';
      echo '      <div class="text-xs text-gray-500 mt-1">SKU: '.htmlspecialchars($it['sku']).'</div>';
      $itemCurrency = $it['currency'] ?? $cartCurrency;
      echo '      <div class="text-brand-700 font-bold mt-2">'.format_currency($it['price'], $itemCurrency).'</div>';
      echo '    </div>';
      echo '    <div class="flex items-center justify-center md:justify-start gap-2">';
      echo '      <button class="w-8 h-8 rounded-full bg-gray-200" onclick="updateQuantity('.(int)$it['id'].', -1)">-</button>';
      echo '      <span class="w-12 text-center font-semibold">'.(int)$it['qty'].'</span>';
      echo '      <button class="w-8 h-8 rounded-full bg-gray-200" onclick="updateQuantity('.(int)$it['id'].', 1)">+</button>';
      echo '    </div>';
      echo '    <div class="font-semibold text-lg md:text-right md:w-32 text-center">'.format_currency($it['price']*$it['qty'], $itemCurrency).'</div>';
      echo '    <a class="text-red-500 text-sm text-center md:text-left" href="?route=remove_cart&id='.(int)$it['id'].'&csrf='.csrf_token().'">Remover</a>';
      echo '  </div>';
    }
    echo '  </div>';
    echo '  <div class="p-4 bg-gray-50 space-y-2">';
    echo '    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">';
    echo '      <span class="text-lg font-semibold text-center sm:text-left">Subtotal</span>';
    echo '      <span class="text-2xl font-bold text-brand-700 text-center sm:text-right">'.format_currency($subtotal, $cartCurrency).'</span>';
    echo '    </div>';
    echo '    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-sm text-gray-600 gap-2">';
    echo '      <span class="text-center sm:text-left">Frete</span>';
    echo '      <span class="font-semibold text-center sm:text-right">'.format_currency($shippingTotal, $cartCurrency).'</span>';
    echo '    </div>';
    echo '    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-lg font-bold gap-2">';
    echo '      <span class="text-center sm:text-left">Total</span>';
    echo '      <span class="text-brand-700 text-2xl text-center sm:text-right">'.format_currency($cartTotal, $cartCurrency).'</span>';
    echo '    </div>';
    echo '  </div>';
    echo '  <div class="p-4 flex flex-col sm:flex-row gap-3">';
    echo '    <a href="?route=home" class="px-5 py-3 rounded-lg border text-center sm:w-auto">Continuar comprando</a>';
    echo '    <a href="?route=checkout" class="flex-1 px-5 py-3 rounded-lg bg-brand-600 text-white text-center hover:bg-brand-700">'.htmlspecialchars($d["checkout"] ?? "Finalizar Compra").'</a>';
    echo '  </div>';
    echo '</div>';
  }

  echo '</section>';
  app_footer();
  exit;
}

// REMOVE FROM CART
if ($route === 'remove_cart') {
  if (!csrf_check($_GET['csrf'] ?? '')) die('CSRF inválido');
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0 && isset($_SESSION['cart'][$id])) unset($_SESSION['cart'][$id]);
  if (empty($_SESSION['cart'])) {
    unset($_SESSION['cart_currency']);
  }
  header('Location: ?route=cart');
  exit;
}

// UPDATE CART (AJAX)
if ($route === 'update_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  $csrfIncoming = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  if (!csrf_check($csrfIncoming)) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
  $id = (int)($_POST['id'] ?? 0);
  $delta = (int)($_POST['delta'] ?? 0);
  if ($id <= 0 || $delta === 0) { echo json_encode(['ok'=>false]); exit; }
  $cart = $_SESSION['cart'] ?? [];
  $new = max(0, (int)($cart[$id] ?? 0) + $delta);
  if ($new === 0) { unset($cart[$id]); }
  else {
    $pdo = db();
    $st = $pdo->prepare("SELECT stock, currency FROM products WHERE id=? AND active=1");
    $st->execute([$id]);
    $prodRow = $st->fetch(PDO::FETCH_ASSOC);
    if (!$prodRow) {
      echo json_encode(['ok'=>false,'error'=>'Produto indisponível']); exit;
    }
    $productCurrency = strtoupper($prodRow['currency'] ?? ($cfg['store']['currency'] ?? 'USD'));
    $cartCurrency = $_SESSION['cart_currency'] ?? null;
    if ($cartCurrency === null) {
      $_SESSION['cart_currency'] = $productCurrency;
    } elseif ($cartCurrency !== $productCurrency) {
      echo json_encode(['ok'=>false,'error'=>'Carrinho aceita apenas produtos na moeda '.$cartCurrency.'.']); exit;
    }
    $stock = (int)($prodRow['stock'] ?? 0);
    if ($stock > 0) $new = min($new, $stock);
    $cart[$id] = $new;
  }
  $_SESSION['cart'] = $cart;
  if (empty($_SESSION['cart'])) {
    unset($_SESSION['cart_currency']);
  }
  echo json_encode(['ok'=>true,'qty'=>($cart[$id] ?? 0)]); exit;
}

// CHECKOUT
if ($route === 'checkout') {
  $cart = $_SESSION['cart'] ?? [];
  if (empty($cart)) { header('Location: ?route=cart'); exit; }

  app_header();
  $checkoutError = $_SESSION['checkout_error'] ?? null;
  unset($_SESSION['checkout_error']);

  $pdo = db();
  $ids = array_keys($cart);
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = $pdo->prepare("SELECT id, name, price, stock, shipping_cost, currency FROM products WHERE id IN ($in) AND active=1");
  $st->execute($ids);
  $items = []; $subtotal = 0.0; $shipping = 0.0;
  $cartCurrency = $_SESSION['cart_currency'] ?? null;
  $currencyMismatch = false;
  foreach ($st as $p) {
    $pid = (int)$p['id'];
    if (!isset($cart[$pid])) { continue; }
    $qty = (int)$cart[$pid];
    if ($qty <= 0) { continue; }
    $productCurrency = strtoupper($p['currency'] ?? ($cfg['store']['currency'] ?? 'USD'));
    if ($cartCurrency === null) {
      $cartCurrency = $productCurrency;
    } elseif ($cartCurrency !== $productCurrency) {
      $currencyMismatch = true;
    }
    $shipCost = isset($p['shipping_cost']) ? (float)$p['shipping_cost'] : 7.00;
    if ($shipCost < 0) { $shipCost = 0; }
    $priceValue = (float)$p['price'];
    $items[] = [
      'id'=>$pid,
      'name'=>$p['name'],
      'price'=>$priceValue,
      'qty'=>$qty,
      'shipping_cost'=>$shipCost,
      'currency'=>$productCurrency
    ];
    $subtotal += $priceValue * $qty;
    $shipping += $shipCost * $qty;
  }
  $shipping = max(0, $shipping);
  $total = $subtotal + $shipping;
  if ($cartCurrency === null) {
    $cartCurrency = $cfg['store']['currency'] ?? 'USD';
  }
  if ($currencyMismatch) {
    $_SESSION['checkout_error'] = 'Carrinho contém produtos em moedas diferentes. Ajuste os itens antes de finalizar.';
    header('Location: ?route=cart');
    exit;
  }

  $_SESSION['cart_currency'] = $cartCurrency;
  $currencyCode = $cartCurrency;

  // Métodos de pagamento dinâmicos
  $paymentMethods = load_payment_methods($pdo, $cfg);
  $countryOptions = checkout_get_countries();
  $defaultCountryOption = setting_get('checkout_default_country', $countryOptions[0]['code'] ?? 'US');
  $defaultCountryOption = strtoupper(trim((string)$defaultCountryOption));
  $countryCodes = array_map(function ($c) { return strtoupper($c['code']); }, $countryOptions);
  if (!in_array($defaultCountryOption, $countryCodes, true) && $countryOptions) {
    $defaultCountryOption = strtoupper($countryOptions[0]['code']);
  }
  $stateGroups = checkout_group_states();
  $initialStates = checkout_get_states_by_country($defaultCountryOption);
  $deliveryMethods = checkout_get_delivery_methods();

  echo '<section class="max-w-6xl mx-auto px-4 py-8">';
  if ($checkoutError) {
    echo '  <div class="mb-4 p-4 rounded-xl border border-amber-300 bg-amber-50 text-amber-800 flex items-start gap-3"><i class="fa-solid fa-triangle-exclamation mt-1"></i><span>'.htmlspecialchars($checkoutError, ENT_QUOTES, 'UTF-8').'</span></div>';
  }
  echo '  <h2 class="text-2xl font-bold mb-6"><i class="fa-solid fa-lock mr-2 text-brand-600"></i>'.htmlspecialchars($d['checkout'] ?? 'Finalizar Compra').'</h2>';
  echo '  <form id="checkout-form" method="post" action="?route=place_order" enctype="multipart/form-data" class="grid lg:grid-cols-2 gap-6">';
  echo '    <input type="hidden" name="csrf" value="'.csrf_token().'">';
  echo '    <input type="hidden" name="square_option" id="square_option_input" value="">';

  // Coluna 1 — Dados
  echo '    <div class="space-y-4">';
  echo '      <div class="bg-white rounded-2xl shadow p-5">';
  echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-user mr-2 text-brand-700"></i>'.htmlspecialchars($d["customer_info"] ?? "Dados do Cliente").'</div>';
  $countrySelectOptions = '';
  foreach ($countryOptions as $country) {
    $code = strtoupper($country['code']);
    $label = htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8');
    $selected = ($code === $defaultCountryOption) ? ' selected' : '';
    $countrySelectOptions .= '<option value="'.$code.'"'.$selected.'>'.$label.'</option>';
  }
  $initialStateOptions = '';
  if ($initialStates) {
    foreach ($initialStates as $stateItem) {
      $stateCode = strtoupper($stateItem['code'] ?? '');
      $stateName = htmlspecialchars($stateItem['name'] ?? $stateCode, ENT_QUOTES, 'UTF-8');
      $initialStateOptions .= '<option value="'.$stateCode.'">'.$stateName.'</option>';
    }
  }
  $cityGroups = checkout_group_cities();
  $initialStateCode = strtoupper($initialStates[0]['code'] ?? '');
  $initialCityKey = $initialStateCode ? ($defaultCountryOption.'::'.$initialStateCode) : '';
  $initialCities = ($initialCityKey && isset($cityGroups[$initialCityKey])) ? $cityGroups[$initialCityKey] : [];
  $initialCityOptions = '';
  if ($initialCities) {
    foreach ($initialCities as $cityName) {
      $cityLabel = htmlspecialchars($cityName, ENT_QUOTES, 'UTF-8');
      $initialCityOptions .= '<option value="'.$cityLabel.'">'.$cityLabel.'</option>';
    }
  }
  $cityPlaceholderText = ($d['city'] ?? 'Cidade').' *';
  $statePlaceholderText = ($d['state'] ?? 'Estado').' *';
  $cityPlaceholderAttr = htmlspecialchars($cityPlaceholderText, ENT_QUOTES, 'UTF-8');
  $statePlaceholderAttr = htmlspecialchars($statePlaceholderText, ENT_QUOTES, 'UTF-8');

  echo '        <div class="grid md:grid-cols-2 gap-3">';
  echo '          <input class="px-4 py-3 border rounded-lg" name="first_name" placeholder="Nome *" required>';
  echo '          <input class="px-4 py-3 border rounded-lg" name="last_name" placeholder="Sobrenome *" required>';
  echo '          <select class="px-4 py-3 border rounded-lg md:col-span-2" name="country" id="checkout-country" required>'.$countrySelectOptions.'</select>';
  echo '          <input class="px-4 py-3 border rounded-lg md:col-span-2" name="address1" placeholder="Nome da rua e número da casa *" required>';
  echo '          <input class="px-4 py-3 border rounded-lg md:col-span-2" name="address2" placeholder="Adicionar apartamento, suíte, unidade, etc." >';
  $citySelectClass = $initialCities ? '' : ' hidden';
  $cityInputClass = $initialCities ? ' hidden' : '';
  $citySelectName = $initialCities ? 'city' : 'city_select';
  $citySelectAttr = $initialCities ? 'required' : 'disabled';
  $cityInputName = $initialCities ? 'city_text' : 'city';
  $cityInputAttr = $initialCities ? 'disabled' : 'required';
  echo '          <div id="city-select-wrapper" class="md:col-span-1'.$citySelectClass.'">';
  echo '            <select class="px-4 py-3 border rounded-lg w-full" name="'.$citySelectName.'" id="checkout-city" '.$citySelectAttr.'>'.$initialCityOptions.'</select>';
  echo '          </div>';
  echo '          <div id="city-input-wrapper" class="md:col-span-1'.$cityInputClass.'">';
  echo '            <input class="px-4 py-3 border rounded-lg w-full" type="text" '.$cityInputAttr.' name="'.$cityInputName.'" id="checkout-city-text" placeholder="'.$cityPlaceholderAttr.'">';
  echo '          </div>';
  $stateSelectClass = $initialStates ? '' : ' hidden';
  $stateInputClass = $initialStates ? ' hidden' : '';
  $stateSelectName = $initialStates ? 'state' : 'state_select';
  $stateSelectAttr = $initialStates ? 'required' : 'disabled';
  $stateInputName = $initialStates ? 'state_text' : 'state';
  $stateInputAttr = $initialStates ? 'disabled' : 'required';
  echo '          <div id="state-select-wrapper" class="md:col-span-1'.$stateSelectClass.'">';
  echo '            <select class="px-4 py-3 border rounded-lg w-full" name="'.$stateSelectName.'" id="checkout-state" '.$stateSelectAttr.'>'.$initialStateOptions.'</select>';
  echo '          </div>';
  echo '          <div id="state-input-wrapper" class="md:col-span-1'.$stateInputClass.'">';
  echo '            <input class="px-4 py-3 border rounded-lg w-full" type="text" '.$stateInputAttr.' name="'.$stateInputName.'" id="checkout-state-text" placeholder="'.$statePlaceholderAttr.'">';
  echo '          </div>';
  echo '          <input class="px-4 py-3 border rounded-lg" name="zipcode" placeholder="CEP *" required>';
  echo '          <input class="px-4 py-3 border rounded-lg" name="email" type="email" placeholder="E-mail *" required>';
  echo '          <input class="px-4 py-3 border rounded-lg md:col-span-2" name="phone" placeholder="Telefone *" required>';

  echo '        </div>';

  if ($deliveryMethods) {
    echo '      <div class="bg-white rounded-2xl shadow p-5 mt-4">';
    echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-truck-fast mr-2 text-brand-700"></i>Método de Entrega</div>';
    echo '        <div class="space-y-3">';
    foreach ($deliveryMethods as $idx => $method) {
      $code = htmlspecialchars($method['code'], ENT_QUOTES, 'UTF-8');
      $label = htmlspecialchars($method['name'], ENT_QUOTES, 'UTF-8');
      $description = trim((string)($method['description'] ?? ''));
      $descHtml = $description !== '' ? '<div class="text-xs text-gray-500 mt-1">'.htmlspecialchars($description, ENT_QUOTES, 'UTF-8').'</div>' : '';
      $checked = $idx === 0 ? ' checked' : '';
      echo '          <label class="flex items-start gap-3 p-4 border rounded-xl hover:border-brand-300 cursor-pointer">';
      echo '            <input class="mt-1" type="radio" name="delivery_method" value="'.$code.'"'.$checked.' required>';
      echo '            <span class="text-sm text-gray-700">';
      echo '              <span class="font-semibold text-gray-900">'.$label.'</span>';
      if ($descHtml) {
        echo '              '.$descHtml;
      }
      echo '            </span>';
      echo '          </label>';
    }
    echo '        </div>';
    echo '      </div>';
  } else {
    echo '      <input type="hidden" name="delivery_method" value="">';
  }

  // Pagamento
  echo '      <div class="bg-white rounded-2xl shadow p-5">';
  echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-credit-card mr-2 text-brand-700"></i>'.htmlspecialchars($d["payment_info"] ?? "Pagamento").'</div>';
  if (!$paymentMethods) {
    echo '        <p class="text-sm text-red-600">Nenhum método de pagamento disponível. Atualize as configurações no painel.</p>';
  } else {
    echo '        <div class="grid grid-cols-2 gap-3">';
    foreach ($paymentMethods as $pm) {
      $code = htmlspecialchars($pm['code']);
      $label = htmlspecialchars($pm['name']);
      $settings = $pm['settings'] ?? [];
      $payType = $settings['type'] ?? $pm['code'];
      $icon = 'fa-credit-card';
      $iconPrefix = 'fa-solid';
      switch ($payType) {
        case 'pix': $icon = 'fa-qrcode'; break;
        case 'zelle': $icon = 'fa-university'; break;
        case 'venmo': $icon = 'fa-mobile-screen-button'; break;
        case 'paypal': $icon = 'fa-paypal'; break;
        case 'square': $icon = 'fa-arrow-up-right-from-square'; break;
        case 'stripe': $icon = 'fa-cc-stripe'; break;
        case 'whatsapp': $iconPrefix = 'fa-brands'; $icon = 'fa-whatsapp'; break;
      }
      echo '  <label class="border rounded-xl p-4 cursor-pointer hover:border-brand-300 flex flex-col items-center gap-2">';
      echo '    <input type="radio" name="payment" value="'.$code.'" class="sr-only" required data-code="'.$code.'">';
      if (!empty($pm['icon_path'])) {
        echo '    <img src="'.htmlspecialchars($pm['icon_path']).'" alt="'.$label.'" class="h-10">';
      } else {
        echo '    <i class="'.$iconPrefix.' '.$icon.' text-2xl text-brand-700"></i>';
      }
      echo '    <div class="font-medium">'.$label.'</div>';
      echo '  </label>';
    }
    echo '        </div>';

    foreach ($paymentMethods as $pm) {
      $code = htmlspecialchars($pm['code']);
      $settings = $pm['settings'] ?? [];
      $payType = $settings['type'] ?? $pm['code'];
      $accountLabel = htmlspecialchars($settings['account_label'] ?? '');
      $accountValue = htmlspecialchars($settings['account_value'] ?? '');
      $placeholders = payment_placeholders($pm, $total, null, null, $subtotal, $shipping, $currencyCode);
      $instructionsHtml = render_payment_instructions($pm['instructions'] ?? '', $placeholders);

      if ($payType === 'square') {
        $squareOptions = [];
        $squareOptions['credit'] = [
          'key' => 'credit',
          'label' => $settings['credit_label'] ?? 'Cartão de crédito',
          'link' => $settings['credit_link'] ?? ''
        ];
        $squareOptions['debit'] = [
          'key' => 'debit',
          'label' => $settings['debit_label'] ?? 'Cartão de débito',
          'link' => $settings['debit_link'] ?? ''
        ];
        $squareOptions['afterpay'] = [
          'key' => 'afterpay',
          'label' => $settings['afterpay_label'] ?? 'Afterpay',
          'link' => $settings['afterpay_link'] ?? ''
        ];
        $squareOptions = array_filter($squareOptions, function ($opt) {
          return !empty($opt['link']);
        });
        if (empty($squareOptions) && ($settings['mode'] ?? 'square_product_link') === 'direct_url' && !empty($settings['redirect_url'])) {
          $squareOptions = [
            'direct' => [
              'key' => 'direct',
              'label' => $settings['credit_label'] ?? $pm['name'],
              'link' => $settings['redirect_url']
            ]
          ];
        }

        $badgeTitle = htmlspecialchars($settings['badge_title'] ?? '', ENT_QUOTES, 'UTF-8');
        $badgeText = htmlspecialchars($settings['badge_text'] ?? '', ENT_QUOTES, 'UTF-8');
        echo '  <div data-payment-info="'.$code.'" class="hidden mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-gray-700 space-y-4">';
        if ($instructionsHtml !== '') {
          echo '    <div>'.$instructionsHtml.'</div>';
        }
        if ($badgeTitle !== '' || $badgeText !== '') {
          echo '    <div class="p-4 bg-white/80 border border-brand-100 rounded-xl text-left">';
          if ($badgeTitle !== '') {
            echo '      <div class="text-brand-700 font-semibold text-base">'.$badgeTitle.'</div>';
          }
          if ($badgeText !== '') {
            echo '      <div class="text-xs text-gray-600 mt-1">'.$badgeText.'</div>';
          }
          echo '    </div>';
        }
        echo '    <div class="grid sm:grid-cols-3 gap-3 square-option-grid" data-square-options="'.$code.'">';
        if ($squareOptions) {
          foreach ($squareOptions as $opt) {
            $optLabel = htmlspecialchars($opt['label'] ?? '', ENT_QUOTES, 'UTF-8');
            $optKey = htmlspecialchars($opt['key'], ENT_QUOTES, 'UTF-8');
            echo '      <button type="button" class="square-option-card border rounded-xl bg-white text-brand-700 px-4 py-3 flex flex-col items-start gap-1" data-square-option="'.$optKey.'">';
            echo '        <span class="text-sm font-semibold">'.$optLabel.'</span>';
            echo '        <span class="text-xs text-gray-500">Clique para pagar com '.$optLabel.'.</span>';
            echo '      </button>';
          }
        } else {
          echo '      <div class="text-sm text-gray-600">Configure os links das opções de cartão (crédito, débito e Afterpay) nas configurações do painel.</div>';
        }
        echo '    </div>';
        echo '  </div>';
      } else {
        echo '  <div data-payment-info="'.$code.'" class="hidden mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-gray-700">';
        if ($accountLabel || $accountValue) {
          echo '    <p class="mb-2"><strong>'.$accountLabel.'</strong>: '.$accountValue.'</p>';
        }
        if ($instructionsHtml !== '') {
          echo '    <p>'.$instructionsHtml.'</p>';
        }
        if ($payType === 'zelle') {
          $subtotalFormatted = format_currency($subtotal, $currencyCode);
          $shippingFormatted = format_currency($shipping, $currencyCode);
          $totalFormatted = format_currency($total, $currencyCode);
          $recipientName = htmlspecialchars($settings['recipient_name'] ?? '', ENT_QUOTES, 'UTF-8');
          echo '    <div class="mt-3 p-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800">';
          echo '      <div class="font-semibold text-sm">Resumo da transferência</div>';
          echo '      <div class="text-sm mt-1"><strong>Total:</strong> '.$totalFormatted.' (produtos '.$subtotalFormatted.' + frete '.$shippingFormatted.')</div>';
          if ($recipientName !== '') {
            echo '      <div class="text-xs mt-2 text-emerald-900/80">Beneficiário: '.$recipientName.'</div>';
          }
          echo '    </div>';
        }
        if ($payType === 'whatsapp') {
          $waNumberRaw = trim((string)($settings['number'] ?? $settings['account_value'] ?? ''));
          $waDisplay = $waNumberRaw !== '' ? $waNumberRaw : '';
          $waMessage = trim((string)($settings['message'] ?? ''));
          $waLink = trim((string)($settings['link'] ?? ''));
          $waDigits = preg_replace('/\D+/', '', $waNumberRaw);
          if ($waDigits !== '') {
            $waLink = 'https://wa.me/'.$waDigits;
            if ($waMessage !== '') {
              $waLink .= '?text='.rawurlencode($waMessage);
            }
          }
          echo '    <div class="mt-3 p-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 space-y-2">';
          if ($waDisplay !== '') {
            echo '      <div class="text-sm"><strong>Número:</strong> '.htmlspecialchars($waDisplay, ENT_QUOTES, 'UTF-8').'</div>';
          }
          if ($waMessage !== '') {
            echo '      <div class="text-xs text-emerald-900/80">Mensagem sugerida: '.htmlspecialchars($waMessage, ENT_QUOTES, 'UTF-8').'</div>';
          }
          if ($waLink !== '') {
            $safeLink = htmlspecialchars($waLink, ENT_QUOTES, 'UTF-8');
            echo '      <div><a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold shadow hover:bg-emerald-700 transition" href="'.$safeLink.'" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Abrir conversa no WhatsApp</a></div>';
          } else {
            echo '      <div class="text-xs text-emerald-900/80">Envie uma mensagem para nossa equipe via WhatsApp informando os dados do pedido.</div>';
          }
          echo '    </div>';
        }
        echo '  </div>';
      }
      if (!empty($pm['require_receipt'])) {
        echo '  <div data-payment-receipt="'.$code.'" class="hidden mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">';
        echo '    <label class="block text-sm font-medium mb-2">Enviar Comprovante (JPG/PNG/PDF)</label>';
        echo '    <input class="w-full px-3 py-2 border rounded" type="file" name="payment_receipt" accept=".jpg,.jpeg,.png,.pdf">';
        echo '    <p class="text-xs text-gray-500 mt-2">Anexe o comprovante após realizar o pagamento.</p>';
        echo '  </div>';
      }
    }
  }

echo '      </div>';
  echo '    </div>';

  // Coluna 2 — Resumo
  echo '    <div>';
  echo '      <div class="bg-white rounded-2xl shadow p-5 sticky top-24">';
  echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-clipboard-list mr-2 text-brand-600"></i>'.htmlspecialchars($d["order_details"] ?? "Resumo do Pedido").'</div>';
  foreach ($items as $it) {
    echo '        <div class="flex items-center justify-between py-2 border-b">';
    echo '          <div class="text-sm"><div class="font-medium">'.htmlspecialchars($it['name']).'</div><div class="text-gray-500">Qtd: '.(int)$it['qty'].'</div></div>';
    echo '          <div class="font-medium">'.format_currency($it['price']*$it['qty'], $it['currency'] ?? $cartCurrency).'</div>';
    echo '        </div>';
  }
  echo '        <div class="mt-4 space-y-1">';
  echo '          <div class="flex justify-between"><span>'.htmlspecialchars($d["subtotal"] ?? "Subtotal").'</span><span>'.format_currency($subtotal, $cartCurrency).'</span></div>';
  echo '          <div class="flex justify-between text-green-600"><span>Frete</span><span>'.format_currency($shipping, $cartCurrency).'</span></div>';
  echo '          <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Total</span><span class="text-brand-600">'.format_currency($total, $cartCurrency).'</span></div>';
  echo '        </div>';
  echo '        <button type="submit" class="w-full mt-5 px-6 py-4 rounded-xl bg-brand-600 text-white hover:bg-brand-700 font-semibold"><i class="fa-solid fa-lock mr-2"></i>'.htmlspecialchars($d["place_order"] ?? "Finalizar Pedido").'</button>';
  $securityBadges = [
    [
      'icon' => 'fa-shield-check',
      'title' => 'Produtos Originais',
      'desc' => 'Nossos produtos são 100% originais e testados em laboratório.'
    ],
    [
      'icon' => 'fa-star',
      'title' => 'Qualidade e Segurança',
      'desc' => 'Compre com quem se preocupa com a qualidade dos produtos.'
    ],
    [
      'icon' => 'fa-truck-fast',
      'title' => 'Enviamos Para Todo EUA',
      'desc' => 'Entrega rápida e segura em todo o EUA.'
    ],
    [
      'icon' => 'fa-lock',
      'title' => 'Site 100% Seguro',
      'desc' => 'Seus pagamentos estão seguros com nossa rede de segurança privada.'
    ],
  ];
  echo '        <div class="mt-6 space-y-3">';
  foreach ($securityBadges as $badge) {
    $icon = htmlspecialchars($badge['icon'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($badge['title'], ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars($badge['desc'], ENT_QUOTES, 'UTF-8');
    echo '          <div class="flex items-start gap-3 p-3 rounded-xl border border-brand-100 bg-brand-50/40">';
    echo '            <div class="w-10 h-10 rounded-full bg-brand-600 text-white grid place-items-center text-lg"><i class="fa-solid '.$icon.'"></i></div>';
    echo '            <div>';
    echo '              <div class="font-semibold text-sm">'.$title.'</div>';
    echo '              <p class="text-xs text-gray-600 leading-snug">'.$desc.'</p>';
    echo '            </div>';
    echo '          </div>';
  }
  echo '        </div>';
  echo '      </div>';
  echo '    </div>';

  echo '  </form>';
  echo '</section>';

  $stateGroupsJson = json_encode($stateGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $defaultCountryJson = json_encode($defaultCountryOption, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $cityGroupsJson = json_encode($cityGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  echo '<script>window.checkoutStateMap = '.$stateGroupsJson.';window.checkoutDefaultCountry = '.$defaultCountryJson.';window.checkoutCityMap = '.$cityGroupsJson.';</script>';

  echo "<script>
    (function(){
      const stateMap = window.checkoutStateMap || {};
      const cityMap = window.checkoutCityMap || {};
      const countrySelectEl = document.getElementById('checkout-country');
      const stateSelectEl = document.getElementById('checkout-state');
      const stateSelectWrapper = document.getElementById('state-select-wrapper');
      const stateInputWrapper = document.getElementById('state-input-wrapper');
      const stateInputEl = document.getElementById('checkout-state-text');
      const citySelectEl = document.getElementById('checkout-city');
      const citySelectWrapper = document.getElementById('city-select-wrapper');
      const cityInputWrapper = document.getElementById('city-input-wrapper');
      const cityInputEl = document.getElementById('checkout-city-text');

      function applyCityMode(useSelect) {
        if (!citySelectEl || !cityInputEl || !citySelectWrapper || !cityInputWrapper) {
          return;
        }
        if (useSelect) {
          citySelectWrapper.classList.remove('hidden');
          citySelectEl.disabled = false;
          citySelectEl.required = true;
          citySelectEl.name = 'city';
          cityInputWrapper.classList.add('hidden');
          cityInputEl.disabled = true;
          cityInputEl.required = false;
          cityInputEl.name = 'city_text';
          cityInputEl.value = '';
          if (!citySelectEl.value && citySelectEl.options.length) {
            citySelectEl.value = citySelectEl.options[0].value;
          }
        } else {
          citySelectWrapper.classList.add('hidden');
          if (citySelectEl) {
            citySelectEl.disabled = true;
            citySelectEl.required = false;
            citySelectEl.name = 'city_select';
          }
          cityInputWrapper.classList.remove('hidden');
          cityInputEl.disabled = false;
          cityInputEl.required = true;
          cityInputEl.name = 'city';
        }
      }

      function updateCityOptions(countryCode, stateCode) {
        if (!citySelectEl) {
          return;
        }
        const country = (countryCode || '').toUpperCase();
        const state = (stateCode || '').toUpperCase();
        const key = country + '::' + state;
        const cities = state ? (Array.isArray(cityMap[key]) ? cityMap[key] : []) : [];
        citySelectEl.innerHTML = '';
        if (cities.length) {
          cities.forEach(function(name, index) {
            const option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            if (index === 0) option.selected = true;
            citySelectEl.appendChild(option);
          });
          applyCityMode(true);
        } else {
          applyCityMode(false);
        }
      }

      function applyStateMode(useSelect) {
        if (!stateSelectEl || !stateInputEl || !stateSelectWrapper || !stateInputWrapper) {
          return;
        }
        if (useSelect) {
          stateSelectWrapper.classList.remove('hidden');
          stateSelectEl.disabled = false;
          stateSelectEl.required = true;
          stateSelectEl.name = 'state';
          stateInputWrapper.classList.add('hidden');
          stateInputEl.disabled = true;
          stateInputEl.required = false;
          stateInputEl.name = 'state_text';
          stateInputEl.value = '';
          if (!stateSelectEl.value && stateSelectEl.options.length) {
            stateSelectEl.value = stateSelectEl.options[0].value;
          }
        } else {
          stateSelectWrapper.classList.add('hidden');
          stateSelectEl.disabled = true;
          stateSelectEl.required = false;
          stateSelectEl.name = 'state_select';
          stateInputWrapper.classList.remove('hidden');
          stateInputEl.disabled = false;
          stateInputEl.required = true;
          stateInputEl.name = 'state';
          applyCityMode(false);
        }
      }

      function renderCountryStates(countryCode) {
        const country = (countryCode || '').toUpperCase();
        const states = Array.isArray(stateMap[country]) ? stateMap[country] : [];
        if (stateSelectEl) {
          stateSelectEl.innerHTML = '';
          if (states.length) {
            states.forEach(function(item, index) {
              const option = document.createElement('option');
              const code = (item.code || item.name || '').toString();
              option.value = code;
              option.textContent = item.name || item.code || code;
              if (index === 0) {
                option.selected = true;
              }
              stateSelectEl.appendChild(option);
            });
          }
        }
        applyStateMode(states.length > 0);
        if (states.length) {
          const selectedState = stateSelectEl && stateSelectEl.value ? stateSelectEl.value : (states[0] && states[0].code) || '';
          updateCityOptions(country, selectedState);
        } else {
          applyCityMode(false);
        }
      }

      if (countrySelectEl) {
        renderCountryStates(countrySelectEl.value || window.checkoutDefaultCountry || '');
        countrySelectEl.addEventListener('change', function(event) {
          renderCountryStates(event.target.value);
        });
      } else {
        applyStateMode(false);
      }

      if (stateSelectEl) {
        stateSelectEl.addEventListener('change', function(event) {
          const country = countrySelectEl ? (countrySelectEl.value || window.checkoutDefaultCountry || '') : '';
          updateCityOptions(country, event.target.value || '');
        });
      }

      const paymentRadios = document.querySelectorAll(\"input[name='payment']\");
      const style = document.createElement('style');
      style.innerHTML = '.square-option-card{transition:all .2s ease;border:1px solid rgba(32,96,200,.2);} .square-option-card:hover{border-color:rgba(32,96,200,.6);transform:translateY(-2px);} .square-option-card.selected{border-color:rgba(32,96,200,1);background:rgba(255,255,255,0.95);box-shadow:0 10px 25px -12px rgba(32,96,200,.7);}';
      document.head.appendChild(style);
      const infoBlocks = document.querySelectorAll('[data-payment-info]');
      const receiptBlocks = document.querySelectorAll('[data-payment-receipt]');
      const squareOptionInput = document.getElementById('square_option_input');
      const squareStorageKey = 'square_checkout_target';

      function resetSquareSelection() {
        document.querySelectorAll('.square-option-card.selected').forEach(btn => btn.classList.remove('selected'));
        if (squareOptionInput) squareOptionInput.value = '';
      }

      function selectSquareOption(option, btn) {
        if (!squareOptionInput) return;
        resetSquareSelection();
        squareOptionInput.value = option;
        if (btn) btn.classList.add('selected');
      }

      paymentRadios.forEach(radio => {
        radio.addEventListener('change', () => {
          document.querySelectorAll('.border-brand-300').forEach(el => el.classList.remove('border-brand-300'));
          const card = radio.closest('label');
          if (card) card.classList.add('border-brand-300');
          const code = radio.dataset.code;
          infoBlocks.forEach(block => {
            const show = block.getAttribute('data-payment-info') === code;
            block.classList.toggle('hidden', !show);
            if (show && code === 'square') {
              const firstBtn = block.querySelector('.square-option-card');
              if (firstBtn) {
                selectSquareOption(firstBtn.dataset.squareOption, firstBtn);
              }
            }
          });
          receiptBlocks.forEach(block => {
            block.classList.toggle('hidden', block.getAttribute('data-payment-receipt') !== code);
          });
          if (code !== 'square') {
            resetSquareSelection();
          }
        });
      });

      document.querySelectorAll('.square-option-card').forEach(btn => {
        btn.addEventListener('click', () => {
          selectSquareOption(btn.dataset.squareOption, btn);
        });
      });

      const form = document.querySelector('#checkout-form');
      if (form) {
        form.addEventListener('submit', function() {
          const selected = form.querySelector(\"input[name='payment']:checked\");
          if (!selected) { return; }
          const code = selected.dataset.code || selected.value;
          if (code === 'square') {
            const isMobile = window.matchMedia('(max-width: 768px)').matches;
            const features = 'noopener=yes,noreferrer=yes,width=920,height=860,left=120,top=60,resizable=yes,scrollbars=yes';
            try { window.sessionStorage.setItem('square_checkout_pending', '1'); } catch (e) {}
            const loadingUrl = document.body.getAttribute('data-square-loading-url') || 'square-loading.html';
            if (!isMobile) {
              let popup = null;
              try {
                popup = window.open(loadingUrl, 'squareCheckout', features);
              } catch (err) {
                popup = null;
              }
              if (!popup) {
                try {
                  popup = window.open('about:blank', 'squareCheckout', features);
                  if (popup) {
                    popup.location.replace(loadingUrl);
                  }
                } catch (err) {
                  popup = null;
                }
              }
              if (popup) {
                try { popup.focus(); } catch (e) {}
              }
            }
          } else {
            try { window.sessionStorage.removeItem('square_checkout_pending'); } catch (e) {}
            try { localStorage.removeItem(squareStorageKey); } catch (e) {}
          }
        });
      }
    })();
  </script>";

  app_footer();
  exit;
}

// PLACE ORDER
if ($route === 'place_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF inválido');

  $pdo = db();
  if (function_exists('checkout_maybe_upgrade_schema')) {
    checkout_maybe_upgrade_schema();
  }
  $cart = $_SESSION['cart'] ?? [];
  if (!$cart) die('Carrinho vazio');

  $availableCountries = checkout_get_countries();
  $countryCodes = array_map(function ($c) { return strtoupper($c['code']); }, $availableCountries);
  $defaultCountryOption = setting_get('checkout_default_country', $availableCountries[0]['code'] ?? 'US');
  $defaultCountryOption = strtoupper(trim((string)$defaultCountryOption));
  if (!in_array($defaultCountryOption, $countryCodes, true) && $availableCountries) {
    $defaultCountryOption = strtoupper($availableCountries[0]['code']);
  }

  $statesGrouped = checkout_group_states();
  $citiesGrouped = checkout_group_cities();
  $deliveryMethodsAvailable = checkout_get_delivery_methods();

  $firstName = sanitize_string($_POST['first_name'] ?? '', 120);
  $lastName = sanitize_string($_POST['last_name'] ?? '', 120);
  $email = trim((string)($_POST['email'] ?? ''));
  $phone = sanitize_string($_POST['phone'] ?? '', 60);
  $address1 = sanitize_string($_POST['address1'] ?? '', 255);
  $address2 = sanitize_string($_POST['address2'] ?? '', 255);
  $city = sanitize_string($_POST['city'] ?? ($_POST['city_text'] ?? ($_POST['city_select'] ?? '')), 120);
  $stateInput = sanitize_string($_POST['state'] ?? ($_POST['state_text'] ?? ''), 80);
  $zipcode = sanitize_string($_POST['zipcode'] ?? '', 20);
  $country = strtoupper(trim((string)($_POST['country'] ?? '')));
  if ($country === '' || !in_array($country, $countryCodes, true)) {
    $country = $defaultCountryOption;
  }
  $payment_method = $_POST['payment'] ?? '';
  $selectedDeliveryCode = trim((string)($_POST['delivery_method'] ?? ''));

  if ($firstName === '' || $lastName === '' || !validate_email($email) || $phone === '' || $address1 === '' || $city === '' || $zipcode === '') {
    $_SESSION['checkout_error'] = 'Preencha todos os dados obrigatórios do checkout.';
    header('Location: ?route=checkout');
    exit;
  }

  $stateNormalized = trim($stateInput);
  $countryStates = $statesGrouped[$country] ?? [];
  if ($countryStates) {
    $validCode = null;
    foreach ($countryStates as $stateOption) {
      $code = strtoupper(trim((string)($stateOption['code'] ?? '')));
      if ($code !== '' && strtoupper($stateNormalized) === $code) {
        $validCode = $code;
        break;
      }
    }
    if ($validCode === null) {
      $_SESSION['checkout_error'] = 'Selecione um estado válido.';
      header('Location: ?route=checkout');
      exit;
    }
    $state = $validCode;
  } else {
    if ($stateNormalized === '') {
      $_SESSION['checkout_error'] = 'Informe o estado ou região.';
      header('Location: ?route=checkout');
      exit;
    }
    $state = sanitize_string($stateNormalized, 80);
  }

  $cityNormalized = trim($city);
  $cityKey = $country.'::'.$state;
  if (!empty($citiesGrouped[$cityKey])) {
    $validCity = null;
    foreach ($citiesGrouped[$cityKey] as $cityOption) {
      if (strcasecmp($cityOption, $cityNormalized) === 0) {
        $validCity = $cityOption;
        break;
      }
    }
    if ($validCity === null) {
      $_SESSION['checkout_error'] = 'Selecione uma cidade válida.';
      header('Location: ?route=checkout');
      exit;
    }
    $city = $validCity;
  } else {
    if ($cityNormalized === '') {
      $_SESSION['checkout_error'] = 'Informe a cidade.';
      header('Location: ?route=checkout');
      exit;
    }
    $city = sanitize_string($cityNormalized, 120);
  }

  $deliveryMethodCode = '';
  $deliveryMethodLabel = '';
  $deliveryMethodDetails = '';
  if ($deliveryMethodsAvailable) {
    $foundDelivery = null;
    foreach ($deliveryMethodsAvailable as $method) {
      if (strcasecmp($method['code'], $selectedDeliveryCode) === 0) {
        $foundDelivery = $method;
        break;
      }
    }
    if ($foundDelivery === null) {
      $_SESSION['checkout_error'] = 'Selecione um método de entrega disponível.';
      header('Location: ?route=checkout');
      exit;
    }
    $deliveryMethodCode = $foundDelivery['code'];
    $deliveryMethodLabel = $foundDelivery['name'] ?? '';
    $deliveryMethodDetails = $foundDelivery['description'] ?? '';
  }

  $fullName = trim($firstName.' '.$lastName);

  $storeCurrencyBase = strtoupper($cfg['store']['currency'] ?? 'USD');
  $cartCurrency = $_SESSION['cart_currency'] ?? null;
  if (is_string($cartCurrency)) {
    $cartCurrency = strtoupper(trim($cartCurrency));
    if ($cartCurrency === '') {
      $cartCurrency = null;
    }
  } else {
    $cartCurrency = null;
  }

  $ids = array_keys($cart);
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = $pdo->prepare("SELECT * FROM products WHERE id IN ($in) AND active=1");
  $st->execute($ids);

  $items = []; $subtotal = 0.0; $shipping = 0.0;
  $costManagementActive = cost_management_enabled();
  $costAccumulator = 0.0;
  $profitAccumulator = 0.0;
  foreach ($st as $p) {
    $qty = (int)($cart[$p['id']] ?? 0);
    if ((int)$p['stock'] < $qty) die('Produto '.$p['name'].' sem estoque');
    $shipCost = isset($p['shipping_cost']) ? (float)$p['shipping_cost'] : 7.00;
    if ($shipCost < 0) { $shipCost = 0; }
    $productCurrency = strtoupper($p['currency'] ?? $storeCurrencyBase);
    if ($cartCurrency === null) {
      $cartCurrency = $productCurrency;
    } elseif ($productCurrency !== $cartCurrency) {
      $_SESSION['checkout_error'] = 'Carrinho possui produtos com moedas diferentes. Remova itens ou finalize separadamente.';
      header('Location: ?route=checkout');
      exit;
    }
    $costUnit = null;
    $profitUnit = null;
    if ($costManagementActive) {
      $costUnit = $p['cost_price'] !== null ? (float)$p['cost_price'] : null;
      $profitOverride = $p['profit_amount'] !== null ? (float)$p['profit_amount'] : null;
      $profitUnit = product_profit_value((float)$p['price'], $costUnit, $profitOverride);
    }
    $items[] = [
      'id'=>(int)$p['id'],
      'name'=>$p['name'],
      'price'=>(float)$p['price'],
      'qty'=>$qty,
      'sku'=>$p['sku'],
      'shipping_cost'=>$shipCost,
      'square_link'=> trim((string)($p['square_payment_link'] ?? '')),
      'stripe_link'=> trim((string)($p['stripe_payment_link'] ?? '')),
      'currency'=>$productCurrency,
      'cost_price'=>$costUnit,
      'profit_value'=>$profitUnit,
    ];
    $subtotal += (float)$p['price'] * $qty;
    $shipping += $shipCost * $qty;
    if ($costManagementActive && $costUnit !== null) {
      $costAccumulator += $costUnit * $qty;
    }
    if ($costManagementActive && $profitUnit !== null) {
      $profitAccumulator += $profitUnit * $qty;
    }
  }
  $shipping = max(0, $shipping);
  $total = $subtotal + $shipping;
  if ($cartCurrency === null) {
    $cartCurrency = $storeCurrencyBase;
  }
  $_SESSION['cart_currency'] = $cartCurrency;
  $costTotal = $costManagementActive ? $costAccumulator : 0.0;
  $profitTotal = $costManagementActive ? $profitAccumulator : 0.0;

  $methods = load_payment_methods($pdo, $cfg);
  $methodMap = [];
  foreach ($methods as $m) {
    $methodMap[$m['code']] = $m;
  }
  if (!isset($methodMap[$payment_method])) {
    die('Método de pagamento inválido');
  }
  $selectedMethod = $methodMap[$payment_method];
  $methodSettings = $selectedMethod['settings'] ?? [];
  $methodType = $methodSettings['type'] ?? $selectedMethod['code'];
  $storeNameForEmails = setting_get('store_name', $cfg['store']['name'] ?? 'Sua Loja');

  $squareRedirectUrl = null;
  $squareWarning = null;
  $squareSelectedOption = sanitize_string(trim((string)($_POST['square_option'] ?? '')), 20);
  $squareOpenNewTab = !empty($methodSettings['open_new_tab']);
  $squareMode = $methodSettings['mode'] ?? 'square_product_link';
  $squareOptionMap = [];
  if ($methodType === 'square') {
    $squareOptionMap = array_filter([
      'credit'   => ['label' => $methodSettings['credit_label'] ?? 'Cartão de crédito', 'link' => $methodSettings['credit_link'] ?? ''],
      'debit'    => ['label' => $methodSettings['debit_label'] ?? 'Cartão de débito', 'link' => $methodSettings['debit_link'] ?? ''],
      'afterpay' => ['label' => $methodSettings['afterpay_label'] ?? 'Afterpay', 'link' => $methodSettings['afterpay_link'] ?? ''],
    ], function ($opt) {
      return !empty($opt['link']);
    });

    if ($squareOptionMap) {
      if ($squareSelectedOption === '' || empty($squareOptionMap[$squareSelectedOption])) {
        $firstKey = array_key_first($squareOptionMap);
        $squareSelectedOption = $firstKey ?: '';
      }
      if ($squareSelectedOption !== '' && !empty($squareOptionMap[$squareSelectedOption]['link'])) {
        $squareRedirectUrl = $squareOptionMap[$squareSelectedOption]['link'];
      } else {
        $squareWarning = 'Configuração do pagamento com cartão incompleta. Informe os links no painel.';
      }
    } elseif ($squareMode === 'direct_url' && !empty($methodSettings['redirect_url'])) {
      $squareRedirectUrl = $methodSettings['redirect_url'];
    } elseif ($squareMode === 'square_product_link') {
      $squareLinks = [];
      $squareMissing = [];
      foreach ($items as $itemInfo) {
        $link = $itemInfo['square_link'] ?? '';
        if ($link === '') {
          $squareMissing[] = $itemInfo['name'];
        } else {
          $squareLinks[$link] = true;
        }
      }
      if (!empty($squareMissing)) {
        $cleanNames = array_map(function($name){ return sanitize_string($name ?? '', 80); }, $squareMissing);
        $squareWarning = 'Pagamento com cartão pendente para: '.implode(', ', $cleanNames);
      } elseif (count($squareLinks) > 1) {
        $squareWarning = 'Mais de um link de cartão encontrado no carrinho. Ajuste os produtos para usar o mesmo link.';
      } elseif (!empty($squareLinks)) {
        $keys = array_keys($squareLinks);
        $squareRedirectUrl = $keys[0];
      }
    }
  }

  $stripeRedirectUrl = null;
  $stripeWarning = null;
  $stripeOpenNewTab = !empty($methodSettings['open_new_tab']);
  if ($methodType === 'stripe' && ($methodSettings['mode'] ?? 'stripe_product_link') === 'stripe_product_link') {
    $stripeLinks = [];
    $stripeMissing = [];
    foreach ($items as $itemInfo) {
      $link = $itemInfo['stripe_link'] ?? '';
      if ($link === '') {
        $stripeMissing[] = $itemInfo['name'];
      } else {
        $stripeLinks[$link] = true;
      }
    }
    if (!empty($stripeMissing)) {
      $cleanNames = array_map(function($name){ return sanitize_string($name ?? '', 80); }, $stripeMissing);
      $stripeWarning = 'Pagamento Stripe pendente para: '.implode(', ', $cleanNames);
    } elseif (count($stripeLinks) > 1) {
      $stripeWarning = 'Mais de um link Stripe encontrado no carrinho. Ajuste os produtos para usar o mesmo link.';
    } elseif (!empty($stripeLinks)) {
      $keys = array_keys($stripeLinks);
      $stripeRedirectUrl = $keys[0];
    }
  }

  $receiptPath = null;
  $receiptError = null;
  $receiptSources = [];
  if (!empty($_FILES['payment_receipt']['name'])) {
    $receiptSources[] = $_FILES['payment_receipt'];
  }
  if (!empty($_FILES['zelle_receipt']['name'])) {
    $receiptSources[] = $_FILES['zelle_receipt'];
  }
  if ($receiptSources) {
    $destDir = $cfg['paths']['zelle_receipts'] ?? (__DIR__.'/storage/zelle_receipts');
    @mkdir($destDir, 0775, true);
    foreach ($receiptSources as $uploadInfo) {
      if (empty($uploadInfo['name'])) {
        continue;
      }
      $validation = validate_file_upload($uploadInfo, ['image/jpeg','image/png','image/webp','application/pdf'], 2 * 1024 * 1024);
      if (!$validation['success']) {
        $receiptError = $validation['message'] ?? 'Arquivo de comprovante inválido.';
        continue;
      }
      $mime = $validation['mime_type'] ?? '';
      $ext = strtolower(pathinfo((string)$uploadInfo['name'], PATHINFO_EXTENSION));
      $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf'
      ];
      if (!in_array($ext, $map, true)) {
        $ext = $map[$mime] ?? 'pdf';
      }
      $filename = 'receipt_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
      $destination = rtrim($destDir, '/\\').DIRECTORY_SEPARATOR.$filename;
      if (!@move_uploaded_file($uploadInfo['tmp_name'], $destination)) {
        $receiptError = 'Falha ao salvar o comprovante.';
        continue;
      }
      $projectRoot = realpath(__DIR__);
      $destReal = realpath($destination);
      $relative = null;
      if ($projectRoot && $destReal && strpos($destReal, $projectRoot) === 0) {
        $relative = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($destReal, strlen($projectRoot))), '/');
      }
      if (!$relative) {
        $relative = 'storage/zelle_receipts/'.$filename;
      }
      $receiptPath = $relative;
      $receiptError = null;
      break;
    }
  }

  if ($receiptError) {
    $_SESSION['checkout_error'] = $receiptError;
    header('Location: ?route=checkout');
    exit;
  }

  if (!empty($selectedMethod['require_receipt']) && !$receiptPath) {
    $_SESSION['checkout_error'] = 'Envie o comprovante de pagamento para concluir o pedido.';
    header('Location: ?route=checkout');
    exit;
  }

  $whatsappLink = '';
  $whatsappNumberDisplay = '';
  $whatsappMessageValue = '';
  if ($methodType === 'whatsapp') {
    $whatsappNumberDisplay = trim((string)($methodSettings['number'] ?? $methodSettings['account_value'] ?? ''));
    $whatsappMessageValue = trim((string)($methodSettings['message'] ?? ''));
    $whatsappLink = trim((string)($methodSettings['link'] ?? ''));
    $waDigits = preg_replace('/\D+/', '', $whatsappNumberDisplay);
    if ($waDigits !== '') {
      $whatsappLink = 'https://wa.me/'.$waDigits;
      if ($whatsappMessageValue !== '') {
        $whatsappLink .= '?text='.rawurlencode($whatsappMessageValue);
      }
    }
    if ($whatsappLink === '' && $whatsappNumberDisplay !== '') {
      $whatsappLink = $whatsappNumberDisplay;
    }
  }

  $orderCurrency = $cartCurrency;
  $payRef = '';
  switch ($methodType) {
    case 'pix':
      $pixKey = $methodSettings['pix_key'] ?? ($methodSettings['account_value'] ?? '');
      $merchantName = $methodSettings['merchant_name'] ?? $storeNameForEmails;
      $merchantCity = $methodSettings['merchant_city'] ?? 'MACEIO';
      if ($pixKey) {
        $payRef = pix_payload($pixKey, $merchantName, $merchantCity, $total);
      }
      break;
    case 'zelle':
      $payRef = $methodSettings['account_value'] ?? '';
      break;
    case 'venmo':
      $payRef = $methodSettings['venmo_link'] ?? ($methodSettings['account_value'] ?? '');
      break;
    case 'paypal':
      $business = $methodSettings['business'] ?? '';
      $currency = $methodSettings['currency'] ?? 'USD';
      $returnUrl = $methodSettings['return_url'] ?? '';
      $cancelUrl = $methodSettings['cancel_url'] ?? '';
      $baseUrl = rtrim($cfg['store']['base_url'] ?? '', '/');
      if ($baseUrl === '' && !empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme.'://'.$_SERVER['HTTP_HOST'];
      }
      if ($returnUrl === '' && $baseUrl !== '') {
        $returnUrl = $baseUrl.'/index.php?route=order_success';
      }
      if ($cancelUrl === '' && $baseUrl !== '') {
        $cancelUrl = $baseUrl.'/index.php?route=checkout';
      }
      if ($business) {
        $payRef = 'https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business='.
                  rawurlencode($business).
                  '&currency_code='.rawurlencode($currency).
                  '&amount='.number_format($total, 2, '.', '').
                  '&item_name='.rawurlencode('Pedido '.$storeNameForEmails).'&return='.
                  rawurlencode($returnUrl).
                  '&cancel_return='.
                  rawurlencode($cancelUrl);
      }
      break;
    case 'whatsapp':
      if ($whatsappLink !== '') {
        $payRef = $whatsappLink;
      } elseif ($whatsappNumberDisplay !== '') {
        $payRef = $whatsappNumberDisplay;
      } else {
        $payRef = 'WHATSAPP';
      }
      break;
    case 'square':
      if (!empty($squareOptionMap) && $squareSelectedOption !== '' && !empty($squareOptionMap[$squareSelectedOption]['label'])) {
        $payRef = 'SQUARE:'.$squareOptionMap[$squareSelectedOption]['label'];
      } else {
        if ($squareRedirectUrl) {
        $payRef = $squareRedirectUrl;
        } elseif (!empty($methodSettings['redirect_url'])) {
          $payRef = $methodSettings['redirect_url'];
        } else {
          $payRef = 'SQUARE:pendente';
        }
      }
      break;
    case 'stripe':
      $mode = $methodSettings['mode'] ?? 'stripe_product_link';
      if ($mode === 'stripe_product_link') {
        $payRef = $stripeRedirectUrl ?: 'STRIPE:pendente';
      } elseif (!empty($methodSettings['redirect_url'])) {
        $payRef = $methodSettings['redirect_url'];
      }
      break;
    default:
      $payRef = $methodSettings['redirect_url'] ?? ($methodSettings['account_value'] ?? '');
      break;
  }

  if ($methodType === 'square') {
    if ($squareWarning || !$squareRedirectUrl) {
      $_SESSION['checkout_error'] = $squareWarning ?: 'Não encontramos um link de cartão configurado. Escolha outra forma de pagamento.';
      header('Location: ?route=checkout');
      exit;
    }
  }
  if ($methodType === 'stripe') {
    if ($stripeRedirectUrl === null || $stripeRedirectUrl === '' || $stripeWarning) {
      $_SESSION['checkout_error'] = $stripeWarning ?: 'Não encontramos um link Stripe para estes produtos. Escolha outra forma de pagamento.';
      header('Location: ?route=checkout');
      exit;
    }
  }

  $deliveryMethodCodeDb = sanitize_string($deliveryMethodCode, 60);
  $deliveryMethodLabelDb = sanitize_string($deliveryMethodLabel, 120);
  $deliveryMethodDetailsDb = sanitize_string($deliveryMethodDetails, 255);

  $customerColumnsExisting = [];
  try {
    $colsStmt = $pdo->query('SHOW COLUMNS FROM customers');
    if ($colsStmt) {
      while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
        $fieldName = isset($col['Field']) ? strtolower((string)$col['Field']) : '';
        if ($fieldName !== '') {
          $customerColumnsExisting[] = $fieldName;
        }
      }
    }
  } catch (Throwable $e) {
    $customerColumnsExisting = [];
  }
  $customerColumnsLookup = $customerColumnsExisting ? array_flip($customerColumnsExisting) : [];
  $hasCustomerMetadata = !empty($customerColumnsLookup);

  $customerColumnsToInsert = [];
  $customerValuesToInsert = [];
  $customerFieldMap = [
    ['first_name', $firstName, true],
    ['last_name', $lastName, true],
    ['name', $fullName, false],
    ['email', $email, false],
    ['phone', $phone, false],
    ['address', $address1, false],
    ['address2', $address2, true],
    ['city', $city, false],
    ['state', $state, false],
    ['zipcode', $zipcode, false],
    ['country', $country, true],
  ];
  foreach ($customerFieldMap as $entry) {
    [$columnName, $columnValue, $optional] = $entry;
    if (!$optional) {
      $customerColumnsToInsert[] = $columnName;
      $customerValuesToInsert[] = $columnValue;
      continue;
    }
    if ($hasCustomerMetadata && isset($customerColumnsLookup[$columnName])) {
      $customerColumnsToInsert[] = $columnName;
      $customerValuesToInsert[] = $columnValue;
    }
  }
  if (!in_array('name', $customerColumnsToInsert, true)) {
    $customerColumnsToInsert[] = 'name';
    $customerValuesToInsert[] = $fullName;
  }
  if (!$customerColumnsToInsert) {
    $customerColumnsToInsert = ['name', 'email', 'phone'];
    $customerValuesToInsert = [$fullName, $email, $phone];
  }

  try {
    $pdo->beginTransaction();
    // cliente
    $customerPlaceholders = implode(',', array_fill(0, count($customerColumnsToInsert), '?'));
    $customerSql = 'INSERT INTO customers('.implode(',', $customerColumnsToInsert).') VALUES('.$customerPlaceholders.')';
    $cst = $pdo->prepare($customerSql);
    $cst->execute($customerValuesToInsert);
    $customer_id = (int)$pdo->lastInsertId();

    // pedido
    $hasTrack = false;
    try {
      $chk = $pdo->query("SHOW COLUMNS FROM orders LIKE 'track_token'");
      $hasTrack = (bool)($chk && $chk->fetch());
    } catch (Throwable $e) { $hasTrack = false; }

    $hasDeliveryCols = false;
    try {
      $chkDelivery = $pdo->query("SHOW COLUMNS FROM orders LIKE 'delivery_method_code'");
      $hasDeliveryCols = (bool)($chkDelivery && $chkDelivery->fetch());
    } catch (Throwable $e) { $hasDeliveryCols = false; }

    $orderColumns = ['customer_id','items_json','subtotal','shipping_cost','total','cost_total','profit_total','currency','payment_method','payment_ref','status','zelle_receipt'];
    $orderValues = [
      $customer_id,
      json_encode($items, JSON_UNESCAPED_UNICODE),
      $subtotal,
      $shipping,
      $total,
      $costTotal,
      $profitTotal,
      $orderCurrency,
      $payment_method,
      $payRef,
      'pending',
      $receiptPath
    ];

    if ($hasDeliveryCols) {
      $orderColumns[] = 'delivery_method_code';
      $orderColumns[] = 'delivery_method_label';
      $orderColumns[] = 'delivery_method_details';
      $orderValues[] = $deliveryMethodCodeDb;
      $orderValues[] = $deliveryMethodLabelDb;
      $orderValues[] = $deliveryMethodDetailsDb;
    }

    if ($hasTrack) {
      $orderColumns[] = 'track_token';
      $track = bin2hex(random_bytes(16));
      $orderValues[] = $track;
    }

    $placeholders = implode(',', array_fill(0, count($orderColumns), '?'));
    $ordersSql = 'INSERT INTO orders('.implode(',', $orderColumns).') VALUES('.$placeholders.')';
    $o = $pdo->prepare($ordersSql);
    $o->execute($orderValues);

    // >>> CORREÇÃO CRÍTICA: definir $order_id ANTES do commit <<<
    $order_id = (int)$pdo->lastInsertId();
    order_sync_items_table($pdo, $order_id, $items);
    $pdo->commit();

    send_notification("new_order","Novo Pedido","Pedido #$order_id de ".sanitize_html($fullName),["order_id"=>$order_id,"total"=>$total,"payment_method"=>$payment_method]);
    $_SESSION["cart"] = [];
    unset($_SESSION['cart_currency']);
    send_order_confirmation($order_id, $email);
    send_order_admin_alert($order_id);
    if ($methodType === 'square') {
      $_SESSION['square_redirect_url'] = $squareRedirectUrl;
      $_SESSION['square_redirect_warning'] = $squareWarning;
      $_SESSION['square_open_new_tab'] = $squareOpenNewTab ? 1 : 0;
      if (!empty($squareOptionMap) && $squareSelectedOption !== '' && !empty($squareOptionMap[$squareSelectedOption]['label'])) {
        $_SESSION['square_option_label'] = sanitize_string($squareOptionMap[$squareSelectedOption]['label'], 80);
      } else {
        $_SESSION['square_option_label'] = '';
      }
    } else {
      unset($_SESSION['square_redirect_url'], $_SESSION['square_redirect_warning'], $_SESSION['square_open_new_tab'], $_SESSION['square_option_label']);
    }
    if ($methodType === 'stripe') {
      $_SESSION['stripe_redirect_url'] = $stripeRedirectUrl;
      $_SESSION['stripe_redirect_warning'] = $stripeWarning;
      $_SESSION['stripe_open_new_tab'] = $stripeOpenNewTab ? 1 : 0;
    } else {
      unset($_SESSION['stripe_redirect_url'], $_SESSION['stripe_redirect_warning'], $_SESSION['stripe_open_new_tab']);
    }
    if ($methodType === 'whatsapp') {
      $_SESSION['whatsapp_link'] = $whatsappLink;
      $_SESSION['whatsapp_number'] = $whatsappNumberDisplay;
      $_SESSION['whatsapp_message'] = $whatsappMessageValue;
    } else {
      unset($_SESSION['whatsapp_link'], $_SESSION['whatsapp_number'], $_SESSION['whatsapp_message']);
    }

    header("Location: ?route=order_success&id=".$order_id);
    exit;

  } catch (Throwable $e) {
    $pdo->rollBack();
    die("Erro ao processar pedido: ".$e->getMessage());
  }
}


// ORDER SUCCESS
if ($route === 'order_success') {
  $order_id = (int)($_GET['id'] ?? 0);
  if (!$order_id) { header('Location: ?route=home'); exit; }
  app_header();

  // fetch tracking token (safe)
  $track_code = '';
  try {
    $pdo = db();
    $q = $pdo->query("SELECT track_token FROM orders WHERE id=".(int)$order_id);
    if ($q) { $track_code = (string)$q->fetchColumn(); }
  } catch (Throwable $e) {}
  $squareRedirectSession = $_SESSION['square_redirect_url'] ?? null;
  $squareWarningSession = $_SESSION['square_redirect_warning'] ?? null;
  $squareOpenNewTabSession = !empty($_SESSION['square_open_new_tab']);
  $squareOptionLabelSession = $_SESSION['square_option_label'] ?? '';
  $stripeRedirectSession = $_SESSION['stripe_redirect_url'] ?? null;
  $stripeWarningSession = $_SESSION['stripe_redirect_warning'] ?? null;
  $stripeOpenNewTabSession = !empty($_SESSION['stripe_open_new_tab']);
  $whatsappLinkSession = $_SESSION['whatsapp_link'] ?? null;
  $whatsappNumberSession = $_SESSION['whatsapp_number'] ?? null;
  $whatsappMessageSession = $_SESSION['whatsapp_message'] ?? null;
  unset($_SESSION['square_redirect_url'], $_SESSION['square_redirect_warning'], $_SESSION['square_open_new_tab'], $_SESSION['square_option_label'], $_SESSION['stripe_redirect_url'], $_SESSION['stripe_redirect_warning'], $_SESSION['stripe_open_new_tab'], $_SESSION['whatsapp_link'], $_SESSION['whatsapp_number'], $_SESSION['whatsapp_message']);

  echo '<section class="max-w-3xl mx-auto px-4 py-16 text-center">';
  echo '  <div class="bg-white rounded-2xl shadow p-8">';
  echo '    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-check text-2xl"></i></div>';
  echo '    <h2 class="text-2xl font-bold mb-2">'.htmlspecialchars($d["thank_you_order"] ?? "Obrigado pelo seu pedido!").'</h2>';
  echo '    <p class="text-gray-600 mb-2">Pedido #'.$order_id.' recebido. Enviamos um e-mail com os detalhes.</p>';
  if (!empty($squareWarningSession)) {
    echo '    <div class="mt-4 p-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-800"><i class="fa-solid fa-triangle-exclamation mr-2"></i>'.htmlspecialchars($squareWarningSession, ENT_QUOTES, "UTF-8").'</div>';
  }
  if (!empty($squareRedirectSession)) {
    $safeSquare = htmlspecialchars($squareRedirectSession, ENT_QUOTES, "UTF-8");
    $squareJs = json_encode($squareRedirectSession, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $squareLabelNote = $squareOptionLabelSession ? ' ('.$squareOptionLabelSession.')' : '';
    echo '    <div class="mt-4 p-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800">';
    echo '      <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i> Redirecionaremos você para o pagamento com cartão'.$squareLabelNote.'. Caso não abra automaticamente, <a class="underline" id="squareManualLink" href="'.$safeSquare.'" target="_blank" rel="noopener">clique aqui</a>.';
    echo '    </div>';
    echo '    <script>
      window.addEventListener("load", function(){
        const redirectKey = "square_redirect_'.$order_id.'";
        if (window.sessionStorage.getItem(redirectKey)) {
          return;
        }
        window.sessionStorage.setItem(redirectKey, "1");
        const checkoutUrl = '.$squareJs.';
        const isMobile = window.matchMedia("(max-width: 768px)").matches;
        const openInNewTabConfigured = '.($squareOpenNewTabSession ? 'true' : 'false').';
        const openInNewTab = isMobile ? false : openInNewTabConfigured;
        const popupFeatures = "noopener=yes,noreferrer=yes,width=920,height=860,left=120,top=60,resizable=yes,scrollbars=yes";
        const storageKey = "square_checkout_target";
        const origin = window.location.origin || (window.location.protocol + "//" + window.location.host);
        const loadingUrl = document.body.getAttribute("data-square-loading-url") || "square-loading.html";
        const targetLoaderUrl = loadingUrl + (loadingUrl.includes("?") ? "&" : "?") + "target=" + encodeURIComponent(checkoutUrl);

        let opened = false;
        let storagePayload = null;
        let placeholderWindow = null;

        try {
          storagePayload = JSON.stringify({ url: checkoutUrl, ts: Date.now() });
          localStorage.setItem(storageKey, storagePayload);
          setTimeout(function(){
            try { localStorage.removeItem(storageKey); } catch (err) {}
          }, 180000);
        } catch (err) {
          storagePayload = null;
        }

        if (!isMobile) {
          try {
            const existingPopup = window.open("", "squareCheckout", popupFeatures);
            if (existingPopup) {
              placeholderWindow = existingPopup;
              if (storagePayload) {
                try {
                  existingPopup.postMessage({ type: "square_checkout_url", payload: storagePayload }, origin);
                } catch (err) {}
              }
              try {
                existingPopup.location.replace(checkoutUrl);
                opened = true;
              } catch (err) {}
              try { existingPopup.focus(); } catch (err) {}
            }
          } catch (err) {}
        }

        if (!opened) {
          if (openInNewTab) {
            try {
              const popup = window.open(checkoutUrl, "squareCheckout", popupFeatures);
              if (popup) {
                placeholderWindow = popup;
                try { popup.focus(); } catch (err) {}
                opened = true;
              }
            } catch (err) {
              opened = false;
            }
          } else {
            if (placeholderWindow) {
              try { placeholderWindow.close(); } catch (err) {}
              placeholderWindow = null;
            }
            try {
              window.location.assign(targetLoaderUrl);
              opened = true;
            } catch (err) {
              try {
                window.location.assign(checkoutUrl);
                opened = true;
              } catch (err2) {
                opened = false;
              }
            }
          }
        }

        // Fallback: botão manual
        if (!opened) {
          const manualLink = document.getElementById("squareManualLink");
          if (manualLink) {
            manualLink.textContent = "Abrir checkout do cartão";
            manualLink.classList.add("font-semibold");
          }
        }

        try { window.sessionStorage.removeItem("square_checkout_pending"); } catch (err) {}
      });
    </script>';
  }
  if (!empty($whatsappLinkSession) || !empty($whatsappNumberSession) || !empty($whatsappMessageSession)) {
    $safeWaLink = $whatsappLinkSession ? htmlspecialchars($whatsappLinkSession, ENT_QUOTES, 'UTF-8') : '';
    $safeWaNumber = $whatsappNumberSession ? htmlspecialchars($whatsappNumberSession, ENT_QUOTES, 'UTF-8') : '';
    $safeWaMessage = $whatsappMessageSession ? htmlspecialchars($whatsappMessageSession, ENT_QUOTES, 'UTF-8') : '';
    echo '    <div class="mt-4 p-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800">';
    echo '      <div class="font-semibold text-sm mb-1"><i class="fa-brands fa-whatsapp mr-2"></i>Finalize pelo WhatsApp</div>';
    if ($safeWaNumber !== '') {
      echo '      <div class="text-sm">Número: '.$safeWaNumber.'</div>';
    }
    if ($safeWaMessage !== '') {
      echo '      <div class="text-xs text-emerald-900/80 mt-1">Mensagem sugerida: '.$safeWaMessage.'</div>';
    }
    if ($safeWaLink !== '') {
      echo '      <div class="mt-2"><a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold shadow hover:bg-emerald-700 transition" href="'.$safeWaLink.'" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Abrir conversa</a></div>';
    }
    echo '    </div>';
  }
  if (!empty($stripeWarningSession)) {
    echo '    <div class="mt-4 p-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-800"><i class="fa-solid fa-triangle-exclamation mr-2"></i>'.htmlspecialchars($stripeWarningSession, ENT_QUOTES, "UTF-8").'</div>';
  }
  if (!empty($stripeRedirectSession)) {
    $safeStripe = htmlspecialchars($stripeRedirectSession, ENT_QUOTES, "UTF-8");
    $stripeJs = json_encode($stripeRedirectSession, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo '    <div class="mt-4 p-4 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-800">';
    echo '      <i class="fa-brands fa-cc-stripe mr-2"></i> Redirecionando para o pagamento Stripe... Caso não avance automaticamente, <a class="underline" href="'.$safeStripe.'">clique aqui</a>.';
    echo '    </div>';
    echo '    <script>
      window.addEventListener("load", function(){
        const key = "stripe_redirect_'.$order_id.'";
        if (!window.sessionStorage.getItem(key)) {
          window.sessionStorage.setItem(key, "1");
          if ('.($stripeOpenNewTabSession ? 'true' : 'false').') {
            window.open('.$stripeJs.', "_blank");
          } else {
            window.location.href = '.$stripeJs.';
          }
        }
      });
    </script>';
  }
  if ($track_code !== '') {
    echo '    <p class="mb-6">Acompanhe seu pedido: <a class="text-brand-600 underline" href="?route=track&code='.htmlspecialchars($track_code, ENT_QUOTES, "UTF-8").'">clique aqui</a></p>';
  }
  echo '    <a href="?route=home" class="px-6 py-3 rounded-lg bg-brand-600 text-white hover:bg-brand-700">Voltar à loja</a>';
  echo '  </div>';
  echo '</section>';

  app_footer();
  exit;
}





// TRACK ORDER (public)
if ($route === 'track') {
  $code = isset($_GET['code']) ? (string)$_GET['code'] : '';
  app_header();
  echo '<section class="container mx-auto p-6">';
  echo '<div class="max-w-2xl mx-auto bg-white rounded-xl shadow p-6">';
  echo '<h2 class="text-2xl font-bold mb-4">Acompanhar Pedido</h2>';
  if ($code === '') {
    echo '<p class="text-gray-600">Código inválido.</p>';
  } else {
    try {
      $pdo = db();
      $st = $pdo->prepare("SELECT id, status, created_at, total FROM orders WHERE track_token = ?");
      $st->execute([substr($code, 0, 64)]);
      $ord = $st->fetch(PDO::FETCH_ASSOC);
      if (!$ord) {
        echo '<p class="text-gray-600">Pedido não encontrado.</p>';
      } else {
        $id     = (int)($ord['id'] ?? 0);
        $status = htmlspecialchars((string)($ord['status'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $total  = format_currency((float)($ord['total'] ?? 0), (cfg()['store']['currency'] ?? 'USD'));
        $created= htmlspecialchars((string)($ord['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');

        echo '<p class="mb-2">Pedido #'.strval($id).'</p>';
        echo '<p class="mb-2">Status: <span class="font-semibold">'.$status.'</span></p>';
        echo '<p class="mb-2">Total: <span class="font-semibold">'.$total.'</span></p>';
        echo '<p class="text-sm text-gray-500">Criado em: '.$created.'</p>';
      }
    } catch (Throwable $e) {
      echo '<p class="text-red-600">Erro: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
    }
  }
  echo '</div></section>';
  app_footer();
  exit;
}

// Fallback — volta pra home

header('Location: ?route=home');
exit;
// PRODUCT DETAIL
if ($route === 'product') {
  $pdo = db();
  $productId = (int)($_GET['id'] ?? 0);
  $slugParam = trim((string)($_GET['slug'] ?? ''));

  if ($productId <= 0 && $slugParam === '') {
    header('Location: ?route=home');
    exit;
  }

  if ($slugParam !== '') {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.id AS category_id,
                                  d.short_description, d.detailed_description, d.specs_json,
                                  d.additional_info, d.payment_conditions, d.delivery_info,
                                  d.media_gallery, d.video_url
                           FROM products p
                           LEFT JOIN categories c ON c.id = p.category_id
                           LEFT JOIN product_details d ON d.product_id = p.id
                           WHERE p.slug = ? AND p.active = 1
                           LIMIT 1");
    $stmt->execute([$slugParam]);
  } else {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.id AS category_id,
                                  d.short_description, d.detailed_description, d.specs_json,
                                  d.additional_info, d.payment_conditions, d.delivery_info,
                                  d.media_gallery, d.video_url
                           FROM products p
                           LEFT JOIN categories c ON c.id = p.category_id
                           LEFT JOIN product_details d ON d.product_id = p.id
                           WHERE p.id = ? AND p.active = 1
                           LIMIT 1");
    $stmt->execute([$productId]);
  }

  $product = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$product) {
    app_header();
    echo '<section class="max-w-xl mx-auto px-4 py-24 text-center">';
    echo '  <div class="text-6xl text-gray-300 mb-4"><i class="fa-solid fa-box-open"></i></div>';
    echo '  <h1 class="text-2xl font-semibold mb-2">Produto não encontrado</h1>';
    echo '  <p class="text-gray-500 mb-6">O item que você tentou acessar não está disponível ou foi retirado de nossa vitrine.</p>';
    echo '  <a class="px-6 py-3 rounded-lg bg-brand-600 text-white hover:bg-brand-700" href="?route=home">Voltar à loja</a>';
    echo '</section>';
    app_footer();
    exit;
  }

  $productId = (int)$product['id'];
  $productName = $product['name'] ?? 'Produto';
  $storeNameMeta = setting_get('store_name', $cfg['store']['name'] ?? 'Get Power Research');
  $metaTitleOverride = $product['meta_title'] ?? '';
  $metaDescOverride = $product['meta_description'] ?? '';
  $GLOBALS['app_meta_title'] = $metaTitleOverride !== '' ? $metaTitleOverride : ($productName.' | '.$storeNameMeta);
  $GLOBALS['app_meta_description'] = $metaDescOverride !== '' ? $metaDescOverride : mb_substr(strip_tags($product['short_description'] ?? $product['description'] ?? ''), 0, 150);

  $shortDescription = trim((string)($product['short_description'] ?? ''));
  if ($shortDescription === '') {
    $shortDescription = mb_substr(strip_tags($product['description'] ?? ''), 0, 220);
  }
  $detailedDescription = trim((string)($product['detailed_description'] ?? $product['description'] ?? ''));
  $detailedDescription = sanitize_builder_output($detailedDescription);

  $specs = [];
  if (!empty($product['specs_json'])) {
    $decodedSpecs = json_decode($product['specs_json'], true);
    if (is_array($decodedSpecs)) {
      foreach ($decodedSpecs as $entry) {
        if (!isset($entry['label']) && isset($entry['key'])) {
          $entry['label'] = $entry['key'];
        }
        $label = trim((string)($entry['label'] ?? ''));
        $value = trim((string)($entry['value'] ?? ''));
        if ($label !== '' && $value !== '') {
          $specs[] = [
            'label' => htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            'value' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
          ];
        }
      }
    }
  }

  $additionalInfo = trim((string)($product['additional_info'] ?? ''));
  if ($additionalInfo !== '') {
    $additionalInfo = sanitize_builder_output($additionalInfo);
  }

  $paymentConditions = trim((string)($product['payment_conditions'] ?? ''));
  $deliveryInfo = trim((string)($product['delivery_info'] ?? ''));
  $videoUrl = trim((string)($product['video_url'] ?? ''));

  $galleryImages = [];
  if (!empty($product['media_gallery'])) {
    $decodedGallery = json_decode($product['media_gallery'], true);
    if (is_array($decodedGallery)) {
      foreach ($decodedGallery as $path) {
        $path = trim((string)$path);
        if ($path !== '') {
          $galleryImages[] = proxy_img($path);
        }
      }
    }
  }
  if (!$galleryImages && !empty($product['image_path'])) {
    $galleryImages[] = proxy_img($product['image_path']);
  }
  $galleryImages = array_values(array_unique(array_filter($galleryImages)));
  $galleryImages = array_slice($galleryImages, 0, 4);

  $currencyCode = strtoupper($product['currency'] ?? ($cfg['store']['currency'] ?? 'USD'));
  $priceValue = (float)($product['price'] ?? 0);
  $priceFormatted = format_currency($priceValue, $currencyCode);
  $compareValue = isset($product['price_compare']) ? (float)$product['price_compare'] : null;
  $compareFormatted = ($compareValue && $compareValue > $priceValue) ? format_currency($compareValue, $currencyCode) : '';
  $discountPercent = ($compareValue && $compareValue > $priceValue) ? max(1, round(100 - ($priceValue / $compareValue * 100))) : null;

  $shippingCost = (float)($product['shipping_cost'] ?? 0);
  $shippingFormatted = format_currency($shippingCost, $currencyCode);

  $stock = (int)($product['stock'] ?? 0);
  $inStock = $stock > 0;

  $categoryName = $product['category_name'] ?? '';
  $categoryId = (int)($product['category_id'] ?? 0);

  $relatedStmt = $pdo->prepare("SELECT p.id, p.slug, p.name, p.price, p.price_compare, p.image_path
                                FROM products p
                                WHERE p.active = 1 AND p.id <> ? AND p.category_id = ?
                                ORDER BY p.featured DESC, p.updated_at DESC
                                LIMIT 4");
  $relatedStmt->execute([$productId, $categoryId]);
  $relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$relatedProducts) {
    $relatedStmt = $pdo->prepare("SELECT p.id, p.slug, p.name, p.price, p.price_compare, p.image_path
                                  FROM products p
                                  WHERE p.active = 1 AND p.id <> ?
                                  ORDER BY p.featured DESC, p.updated_at DESC
                                  LIMIT 4");
    $relatedStmt->execute([$productId]);
    $relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
  }

  app_header();

  echo '<section class="max-w-6xl mx-auto px-4 py-10 space-y-12">';

  echo '  <div class="grid lg:grid-cols-2 gap-10">';
  echo '    <div class="space-y-6">';
  if ($galleryImages) {
    $mainImage = htmlspecialchars($galleryImages[0], ENT_QUOTES, 'UTF-8');
    echo '      <div class="relative bg-white rounded-3xl shadow overflow-hidden">';
    echo '        <div class="aspect-square w-full bg-gray-50 flex items-center justify-center">';
    echo '          <img id="product-main-image" src="'.$mainImage.'" alt="'.htmlspecialchars($productName, ENT_QUOTES, 'UTF-8').'" class="max-h-full max-w-full object-contain transition-transform duration-300">';
    echo '        </div>';
    echo '      </div>';
    if (count($galleryImages) > 1) {
      echo '      <div class="flex gap-3 flex-wrap">';
      foreach ($galleryImages as $idx => $imgPath) {
        $thumb = htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8');
        $isActive = $idx === 0 ? 'border-brand-600 ring-2 ring-brand-200' : 'border-transparent';
        echo '        <button type="button" class="w-20 h-20 rounded-xl border '.$isActive.' overflow-hidden bg-white shadow-sm hover:border-brand-400" data-gallery-image="'.$thumb.'">';
        echo '          <img src="'.$thumb.'" alt="thumb" class="w-full h-full object-cover">';
        echo '        </button>';
      }
      echo '      </div>';
    }
  }
  if ($videoUrl !== '') {
    $safeVideo = htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8');
    echo '      <div class="bg-white rounded-2xl shadow p-4">';
    echo '        <h3 class="font-semibold mb-3 flex items-center gap-2"><i class="fa-solid fa-circle-play text-brand-600"></i> Vídeo demonstrativo</h3>';
    if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false) {
      if (preg_match('~(youtu\\.be/|v=)([\\w-]+)~', $videoUrl, $match)) {
        $videoId = $match[2];
        echo '        <div class="relative aspect-video rounded-xl overflow-hidden">';
        echo '          <iframe class="w-full h-full" src="https://www.youtube.com/embed/'.htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8').'" frameborder="0" allowfullscreen></iframe>';
        echo '        </div>';
      } else {
        echo '        <a class="text-brand-600 underline" href="'.$safeVideo.'" target="_blank" rel="noopener">Assistir vídeo</a>';
      }
    } else {
      echo '        <a class="text-brand-600 underline" href="'.$safeVideo.'" target="_blank" rel="noopener">Assistir vídeo</a>';
    }
    echo '      </div>';
  }
  echo '    </div>';

  echo '    <div class="space-y-6">';
  if ($categoryName) {
    echo '      <div class="text-sm text-brand-600 uppercase font-semibold">'.htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8').'</div>';
  }
  echo '      <h1 class="text-3xl md:text-4xl font-bold text-gray-900">'.htmlspecialchars($productName, ENT_QUOTES, 'UTF-8').'</h1>';
  if ($shortDescription) {
    echo '      <p class="text-gray-600">'.$shortDescription.'</p>';
  }

  echo '      <div class="bg-white rounded-2xl shadow p-5 space-y-3">';
  echo '        <div class="flex items-center gap-3 flex-wrap">';
  if ($compareFormatted) {
    echo '          <span class="text-sm text-gray-400 line-through">De '.$compareFormatted.'</span>';
  }
  echo '          <span class="text-3xl font-bold text-brand-700">'.$priceFormatted.'</span>';
  if ($discountPercent) {
    echo '          <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-sm font-semibold">'.$discountPercent.'% OFF</span>';
  }
  echo '        </div>';
  echo '        <div class="text-sm text-gray-600">Pagamento: '.($paymentConditions !== '' ? htmlspecialchars($paymentConditions, ENT_QUOTES, 'UTF-8') : 'Ver opções no checkout.').'</div>';
  echo '        <div class="text-sm text-gray-600">Frete padrão: <strong>'.$shippingFormatted.'</strong></div>';
  echo '        <div class="flex items-center gap-2 text-sm">';
  if ($inStock) {
    $stockLabel = $stock <= 5 ? 'Poucas unidades restantes' : 'Em estoque';
    echo '          <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i> '.$stockLabel.'</span>';
    echo '          <span class="text-gray-500">Estoque: '.$stock.' unidade(s)</span>';
  } else {
    echo '          <span class="px-3 py-1 rounded-full bg-rose-100 text-rose-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Esgotado</span>';
  }
  echo '        </div>';
  echo '      </div>';

  echo '      <div class="bg-white rounded-2xl shadow p-5 space-y-4">';
  echo '        <div class="flex items-center gap-3">';
  echo '          <div class="text-sm font-semibold">Quantidade</div>';
  echo '          <div class="flex items-center border rounded-full overflow-hidden">';
  echo '            <button type="button" class="w-10 h-10 flex items-center justify-center text-lg text-gray-600" id="qtyDecrease"><i class="fa-solid fa-minus"></i></button>';
  echo '            <input type="number" min="1" value="1" id="quantityInput" class="w-16 text-center border-x border-gray-200 focus:outline-none" />';
  echo '            <button type="button" class="w-10 h-10 flex items-center justify-center text-lg text-gray-600" id="qtyIncrease"><i class="fa-solid fa-plus"></i></button>';
  echo '          </div>';
  echo '        </div>';
  if ($inStock) {
    echo '        <div class="grid sm:grid-cols-2 gap-3">';
    echo '          <button type="button" class="px-5 py-3 rounded-xl bg-brand-600 text-white hover:bg-brand-700 text-sm font-semibold flex items-center justify-center gap-2" id="btnBuyNow"><i class="fa-solid fa-cart-shopping"></i> Adicionar ao carrinho</button>';
    echo '          <a href="?route=checkout" class="px-5 py-3 rounded-xl border border-brand-200 text-brand-700 hover:bg-brand-50 text-sm font-semibold flex items-center justify-center gap-2"><i class="fa-solid fa-flash"></i> Comprar agora</a>';
    echo '        </div>';
  } else {
    echo '        <div class="px-5 py-3 rounded-xl bg-gray-200 text-gray-500 text-center font-semibold">Avise-me quando disponível</div>';
  }
  echo '      </div>';

  echo '      <div class="bg-white rounded-2xl shadow p-5 space-y-4">';
  echo '        <h3 class="font-semibold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-truck-fast text-brand-600"></i> Estimativa de frete</h3>';
  echo '        <p class="text-sm text-gray-500">Informe seu CEP para estimar o prazo de entrega. O valor base é configurado no painel administrativo.</p>';
  echo '        <div class="flex gap-3 flex-col sm:flex-row">';
  echo '          <input type="text" maxlength="9" placeholder="00000-000" id="cepInput" class="flex-1 px-4 py-3 border rounded-lg" />';
  echo '          <button type="button" class="px-5 py-3 rounded-lg bg-gray-900 text-white hover:bg-gray-800" id="calcFreightBtn">Calcular frete</button>';
  echo '        </div>';
  echo '        <div id="freightResult" class="text-sm text-gray-600 hidden"></div>';
  echo '      </div>';

  echo '      <div class="bg-white rounded-2xl shadow p-5 space-y-3">';
  echo '        <h3 class="font-semibold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-shield-check text-brand-600"></i> Compra segura</h3>';
  echo '        <ul class="text-sm text-gray-600 space-y-2">';
  echo '          <li><i class="fa-solid fa-lock text-brand-600 mr-2"></i> Pagamento criptografado e seguro</li>';
  echo '          <li><i class="fa-solid fa-arrow-rotate-left text-brand-600 mr-2"></i> Troca e devolução garantidas (consulte nossas políticas)</li>';
  echo '          <li><i class="fa-solid fa-headset text-brand-600 mr-2"></i> Suporte ao cliente dedicado via WhatsApp</li>';
  echo '        </ul>';
  echo '        <div class="text-xs text-gray-500 space-x-3 pt-2">';
  echo '          <a class="underline hover:text-brand-600" href="?route=privacy" target="_blank">Política de privacidade</a>';
  echo '          <a class="underline hover:text-brand-600" href="?route=refund" target="_blank">Política de reembolso</a>';
  echo '        </div>';
  echo '      </div>';
  echo '    </div>';
  echo '  </div>';

  echo '  <div class="bg-white rounded-3xl shadow p-6 space-y-6">';
  echo '    <nav class="flex flex-wrap gap-4 text-sm font-semibold text-gray-600 border-b pb-3" id="productTabs">';
  echo '      <button type="button" data-tab="description" class="px-3 py-2 rounded-lg bg-brand-50 text-brand-700">Descrição</button>';
  if ($specs) {
    echo '      <button type="button" data-tab="specs" class="px-3 py-2 rounded-lg hover:bg-gray-100">Especificações</button>';
  }
  if ($additionalInfo !== '') {
    echo '      <button type="button" data-tab="additional" class="px-3 py-2 rounded-lg hover:bg-gray-100">Informações adicionais</button>';
  }
  echo '      <button type="button" data-tab="reviews" class="px-3 py-2 rounded-lg hover:bg-gray-100">Avaliações</button>';
  echo '    </nav>';
  echo '    <div class="space-y-8" id="productTabPanels">';
  echo '      <div data-panel="description">';
  echo '        <div class="prose prose-sm sm:prose md:prose-lg max-w-none text-gray-700">'.$detailedDescription.'</div>';
  echo '      </div>';
  if ($specs) {
    echo '      <div data-panel="specs" class="hidden">';
    echo '        <div class="overflow-x-auto">';
    echo '          <table class="min-w-full text-sm">';
    echo '            <tbody>';
    foreach ($specs as $entry) {
      echo '              <tr class="border-b last:border-0">';
      echo '                <th class="text-left font-semibold py-3 pr-6 text-gray-600">'. $entry['label'] .'</th>';
      echo '                <td class="py-3 text-gray-700">'. $entry['value'] .'</td>';
      echo '              </tr>';
    }
    echo '            </tbody>';
    echo '          </table>';
    echo '        </div>';
    echo '      </div>';
  }
  if ($additionalInfo !== '') {
    echo '      <div data-panel="additional" class="hidden">';
    echo '        <div class="prose prose-sm sm:prose md:prose-lg max-w-none text-gray-700">'.$additionalInfo.'</div>';
    echo '      </div>';
  }
  echo '      <div data-panel="reviews" class="hidden">';
  echo '        <div class="flex items-center gap-3 mb-4">';
  echo '          <div class="text-3xl font-bold text-brand-600">4.8</div>';
  echo '          <div class="text-sm text-gray-500">Avaliação média (sistema de reviews simplificado em desenvolvimento)</div>';
  echo '        </div>';
  echo '        <p class="text-sm text-gray-600">Em breve você poderá conferir opiniões reais de outros clientes sobre este produto.</p>';
  echo '      </div>';
  echo '    </div>';
  echo '  </div>';

  if ($relatedProducts) {
    echo '  <div>';
    echo '    <div class="flex items-center justify-between mb-4">';
    echo '      <h2 class="text-xl font-bold">Produtos relacionados</h2>';
    echo '      <a class="text-sm text-brand-600 hover:underline" href="?route=home">Ver todos</a>';
    echo '    </div>';
    echo '    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">';
    foreach ($relatedProducts as $rel) {
      $relImg = $rel['image_path'] ? proxy_img($rel['image_path']) : 'assets/no-image.png';
      $relImgSafe = htmlspecialchars($relImg, ENT_QUOTES, 'UTF-8');
      $relPrice = format_currency((float)$rel['price'], $currencyCode);
      $relCompare = isset($rel['price_compare']) && $rel['price_compare'] > $rel['price'] ? format_currency((float)$rel['price_compare'], $currencyCode) : '';
      $relDiscount = ($relCompare !== '') ? max(1, round(100 - ($rel['price'] / $rel['price_compare'] * 100))) : null;
      $relUrl = $rel['slug'] ? ('?route=product&slug='.urlencode($rel['slug'])) : ('?route=product&id='.(int)$rel['id']);
      echo '      <div class="bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden flex flex-col">';
      echo '        <a href="'.$relUrl.'" class="block relative h-44 bg-gray-50 overflow-hidden">';
      echo '          <img src="'.$relImgSafe.'" alt="'.htmlspecialchars($rel['name'], ENT_QUOTES, 'UTF-8').'" class="w-full h-full object-cover transition-transform duration-300 hover:scale-105">';
      echo '          '.($relDiscount ? '<span class="absolute top-3 left-3 bg-brand-600 text-white text-xs font-semibold px-2 py-1 rounded-full">'.$relDiscount.'% OFF</span>' : '').'';
      echo '        </a>';
      echo '        <div class="p-4 flex flex-col space-y-2 flex-1">';
      echo '          <a href="'.$relUrl.'" class="font-semibold text-gray-900 hover:text-brand-600">'.htmlspecialchars($rel['name'], ENT_QUOTES, 'UTF-8').'</a>';
      if ($relCompare) {
        echo '          <div class="text-sm text-gray-400 line-through">'.$relCompare.'</div>';
      }
      echo '          <div class="text-lg font-bold text-brand-700">'.$relPrice.'</div>';
      echo '          <button type="button" class="mt-auto px-4 py-2 rounded-lg bg-brand-600 text-white hover:bg-brand-700 text-sm" onclick="addToCart('.(int)$rel['id'].', \''.htmlspecialchars($rel['name'], ENT_QUOTES, 'UTF-8').'\', 1)">Adicionar</button>';
      echo '        </div>';
      echo '      </div>';
    }
    echo '    </div>';
    echo '  </div>';
  }

  echo '</section>';

  echo '<script>
    (function(){
      const mainImage = document.getElementById("product-main-image");
      const buttons = document.querySelectorAll("[data-gallery-image]");
      buttons.forEach(btn => {
        btn.addEventListener("click", () => {
          const url = btn.getAttribute("data-gallery-image");
          if (mainImage && url) {
            mainImage.src = url;
            buttons.forEach(b => b.classList.remove("border-brand-600","ring-2","ring-brand-200"));
            btn.classList.add("border-brand-600","ring-2","ring-brand-200");
          }
        });
      });

      const qtyInput = document.getElementById("quantityInput");
      const decrease = document.getElementById("qtyDecrease");
      const increase = document.getElementById("qtyIncrease");
      if (decrease && increase && qtyInput) {
        decrease.addEventListener("click", () => {
          let current = parseInt(qtyInput.value, 10) || 1;
          current = Math.max(1, current - 1);
          qtyInput.value = current;
        });
        increase.addEventListener("click", () => {
          let current = parseInt(qtyInput.value, 10) || 1;
          qtyInput.value = current + 1;
        });
      }

      const buyBtn = document.getElementById("btnBuyNow");
      if (buyBtn && qtyInput) {
        buyBtn.addEventListener("click", () => {
          const qty = parseInt(qtyInput.value, 10) || 1;
          addToCart('.$productId.', "'.htmlspecialchars($productName, ENT_QUOTES, 'UTF-8').'", qty);
        });
      }

      const tabs = document.querySelectorAll("#productTabs button[data-tab]");
      const panels = document.querySelectorAll("#productTabPanels [data-panel]");
      tabs.forEach(tab => {
        tab.addEventListener("click", () => {
          const target = tab.getAttribute("data-tab");
          tabs.forEach(t => t.classList.remove("bg-brand-50","text-brand-700"));
          tab.classList.add("bg-brand-50","text-brand-700");
          panels.forEach(panel => {
            panel.classList.toggle("hidden", panel.getAttribute("data-panel") !== target);
          });
        });
      });

      const freightBtn = document.getElementById("calcFreightBtn");
      const cepInput = document.getElementById("cepInput");
      const resultBox = document.getElementById("freightResult");
      if (freightBtn && cepInput && resultBox) {
        freightBtn.addEventListener("click", () => {
          const cep = (cepInput.value || "").replace(/\\D+/g, "");
          if (cep.length !== 8) {
            resultBox.textContent = "Informe um CEP válido com 8 dígitos.";
            resultBox.classList.remove("hidden");
            resultBox.classList.add("text-rose-600");
            return;
          }
          resultBox.classList.remove("text-rose-600");
          resultBox.classList.add("text-emerald-700");
          resultBox.textContent = "Frete padrão disponível para o CEP "+cep.substr(0,5)+"-"+cep.substr(5)+" por '.$shippingFormatted.' (valor configurado pelo administrador).";
          resultBox.classList.remove("hidden");
        });
      }
    })();
  </script>';

  app_footer();
  unset($GLOBALS['app_meta_title'], $GLOBALS['app_meta_description']);
  exit;
}

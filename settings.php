<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/utils.php';
require __DIR__.'/admin_layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('require_admin')) {
  function require_admin(){
    if (empty($_SESSION['admin_id'])) {
      header('Location: admin.php?route=login');
      exit;
    }
  }
}
if (!function_exists('csrf_token')) {
  function csrf_token(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
}
if (!function_exists('csrf_check')) {
  function csrf_check($token){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token); }
}

require_admin();

$pdo = db();
$canEditSettings = admin_can('manage_settings');
$canManagePayments = admin_can('manage_payment_methods');
$canManageBuilder = admin_can('manage_builder');
$isSuperAdmin = is_super_admin();

function pm_sanitize($value, $max = 255) {
  $value = trim((string)$value);
  if (mb_strlen($value) > $max) {
    $value = mb_substr($value, 0, $max);
  }
  return $value;
}

function pm_clip_text($value, $max = 8000) {
  $value = (string)$value;
  if (mb_strlen($value) > $max) {
    $value = mb_substr($value, 0, $max);
  }
  return $value;
}

function pm_safe_html($value, $allowed = '<br><strong><em><span>', $max = 8000) {
  $value = pm_clip_text($value, $max);
  $value = trim((string)$value);
  $value = strip_tags($value, $allowed);
  return $value;
}

function pm_slug($text) {
  $text = strtolower($text);
  $text = preg_replace('/[^a-z0-9\-]+/i', '-', $text);
  $text = trim($text, '-');
  return $text ?: 'metodo';
}

function pm_decode_settings($row) {
  $settings = [];
  if (!empty($row['settings'])) {
    $json = json_decode($row['settings'], true);
    if (is_array($json)) {
      $settings = $json;
    }
  }
  return $settings;
}

function pm_collect_settings($type, array $data) {
  $settings = [
    'type' => $type,
    'account_label' => pm_sanitize($data['account_label'] ?? '', 120),
    'account_value' => pm_sanitize($data['account_value'] ?? '', 255),
    'button_bg' => pm_sanitize($data['button_bg'] ?? '#dc2626', 20),
    'button_text' => pm_sanitize($data['button_text'] ?? '#ffffff', 20),
    'button_hover_bg' => pm_sanitize($data['button_hover_bg'] ?? '#b91c1c', 20),
  ];

  switch ($type) {
    case 'pix':
      $settings['pix_key'] = pm_sanitize($data['pix_key'] ?? '', 140);
      $settings['merchant_name'] = pm_sanitize($data['pix_merchant_name'] ?? '', 120);
      $settings['merchant_city'] = pm_sanitize($data['pix_merchant_city'] ?? '', 60);
      break;
    case 'zelle':
      $settings['recipient_name'] = pm_sanitize($data['zelle_recipient_name'] ?? '', 120);
      break;
    case 'venmo':
      $settings['venmo_link'] = pm_sanitize($data['venmo_link'] ?? '', 255);
      break;
    case 'paypal':
      $settings['business'] = pm_sanitize($data['paypal_business'] ?? '', 180);
      $settings['currency'] = strtoupper(pm_sanitize($data['paypal_currency'] ?? 'USD', 3));
      $settings['return_url'] = pm_sanitize($data['paypal_return_url'] ?? '', 255);
      $settings['cancel_url'] = pm_sanitize($data['paypal_cancel_url'] ?? '', 255);
      break;
    case 'whatsapp':
      $settings['number'] = pm_sanitize($data['whatsapp_number'] ?? '', 60);
      $settings['message'] = pm_clip_text($data['whatsapp_message'] ?? '', 500);
      $settings['link'] = pm_sanitize($data['whatsapp_link'] ?? '', 255);
      break;
    case 'square':
      $settings['mode'] = pm_sanitize($data['square_mode'] ?? 'square_product_link', 60);
      $settings['open_new_tab'] = !empty($data['square_open_new_tab']);
      $settings['redirect_url'] = pm_sanitize($data['square_redirect_url'] ?? '', 255);
      $settings['badge_title'] = pm_safe_html($data['square_badge_title'] ?? 'Seleção especial', '<br><strong><em><span>', 240);
      $settings['badge_text'] = pm_safe_html($data['square_badge_text'] ?? 'Selecionados com carinho para você', '<br><strong><em><span>', 400);
      $settings['credit_label'] = pm_sanitize($data['square_credit_label'] ?? 'Cartão de crédito', 80);
      $settings['credit_link'] = pm_sanitize($data['square_credit_link'] ?? '', 255);
      $settings['debit_label'] = pm_sanitize($data['square_debit_label'] ?? 'Cartão de débito', 80);
      $settings['debit_link'] = pm_sanitize($data['square_debit_link'] ?? '', 255);
      $settings['afterpay_label'] = pm_sanitize($data['square_afterpay_label'] ?? 'Afterpay', 80);
      $settings['afterpay_link'] = pm_sanitize($data['square_afterpay_link'] ?? '', 255);
      break;
    case 'stripe':
      $settings['mode'] = pm_sanitize($data['stripe_mode'] ?? 'stripe_product_link', 60);
      $settings['open_new_tab'] = !empty($data['stripe_open_new_tab']);
      $settings['redirect_url'] = pm_sanitize($data['stripe_redirect_url'] ?? '', 255);
      break;
    default:
      $settings['mode'] = pm_sanitize($data['custom_mode'] ?? 'manual', 60);
      $settings['redirect_url'] = pm_sanitize($data['custom_redirect_url'] ?? '', 255);
      break;
  }

  return $settings;
}

function pm_upload_icon(array $file) {
  if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
    return [true, null];
  }
  $validation = validate_file_upload($file, ['image/jpeg','image/png','image/webp','image/svg+xml'], 1024 * 1024);
  if (!$validation['success']) {
    return [false, $validation['message'] ?? 'Arquivo inválido'];
  }
  $dir = __DIR__.'/storage/payment_icons';
  @mkdir($dir, 0775, true);
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp','svg'], true)) {
    $map = [
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
      'image/svg+xml' => 'svg'
    ];
    $ext = $map[$validation['mime_type'] ?? ''] ?? 'png';
  }
  $filename = 'pm_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
  $dest = $dir.'/'.$filename;
  if (!@move_uploaded_file($file['tmp_name'], $dest)) {
    return [false, 'Falha ao salvar ícone'];
  }
  return [true, 'storage/payment_icons/'.$filename];
}

$action = $_GET['action'] ?? 'list';
$tab = $_GET['tab'] ?? 'general';
$allowedTabs = ['general','social','payments','builder','checkout'];
if ($isSuperAdmin) {
  $allowedTabs[] = 'theme';
  $allowedTabs[] = 'experience';
  $allowedTabs[] = 'navigation';
}
if (!in_array($tab, $allowedTabs, true)) {
  $tab = 'general';
}

if ($action === 'reorder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  require_admin_capability('manage_payment_methods');
  header('Content-Type: application/json; charset=utf-8');
  $payload = json_decode(file_get_contents('php://input'), true);
  $csrf = $payload['csrf'] ?? '';
  if (!csrf_check($csrf)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_csrf']);
    exit;
  }
  $ids = $payload['ids'] ?? [];
  if (!is_array($ids) || !$ids) {
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
  }
  $order = 0;
  $st = $pdo->prepare('UPDATE payment_methods SET sort_order = ? WHERE id = ?');
  foreach ($ids as $id) {
    $order += 10;
    $st->execute([$order, (int)$id]);
  }
  echo json_encode(['ok' => true]);
  exit;
}

if ($action === 'cache_bust_refresh' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  require_super_admin();
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('CSRF');
  }
  try {
    $newToken = cache_bust_regenerate_token();
    $_SESSION['cache_bust_notice'] = 'Novo token de cache gerado: '.$newToken;
  } catch (Throwable $e) {
    $_SESSION['cache_bust_error'] = 'Falha ao regenerar o token: '.$e->getMessage();
  }
  header('Location: settings.php?tab=general#cache-busting');
  exit;
}

if ($action === 'toggle' && isset($_GET['id'])) {
  if (!csrf_check($_GET['csrf'] ?? '')) die('CSRF');
  require_admin_capability('manage_payment_methods');
  $id = (int)$_GET['id'];
  $pdo->prepare('UPDATE payment_methods SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
  header('Location: settings.php?tab=payments');
  exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
  if (!csrf_check($_GET['csrf'] ?? '')) die('CSRF');
  require_super_admin();
  $id = (int)$_GET['id'];
  $pdo->prepare('DELETE FROM payment_methods WHERE id=?')->execute([$id]);
  header('Location: settings.php?tab=payments');
  exit;
}

if (($action === 'create' || $action === 'update') && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  require_admin_capability('manage_payment_methods');

  $id = (int)($_GET['id'] ?? 0);
  $name = pm_sanitize($_POST['name'] ?? '');
  if ($name === '') {
    die('Nome obrigatório');
  }
  $codeInput = pm_sanitize($_POST['code'] ?? '', 50);
  $type = pm_sanitize($_POST['method_type'] ?? 'custom', 50);
  $code = $codeInput ?: pm_slug($name);
  if ($type !== 'custom') {
    $code = $type;
  }

  $description = pm_sanitize($_POST['description'] ?? '', 500);
  $instructions = trim((string)($_POST['instructions'] ?? ''));
  $isActive = isset($_POST['is_active']) ? 1 : 0;
  $requireReceipt = isset($_POST['require_receipt']) ? 1 : 0;

  $settings = pm_collect_settings($type, $_POST);

  if ($type === 'square' && $isActive) {
    $mode = $settings['mode'] ?? 'square_product_link';
    $hasLink = false;
    if (!empty($settings['credit_link']) || !empty($settings['debit_link']) || !empty($settings['afterpay_link'])) {
      $hasLink = true;
    }
    if (!$hasLink && $mode === 'direct_url' && !empty($settings['redirect_url'])) {
      $hasLink = true;
    }
    if (!$hasLink) {
      die('Configure ao menos um link (crédito, débito, Afterpay ou URL fixa) antes de ativar o cartão de crédito (Square).');
    }
  }

  $iconPath = null;
  if ($action === 'update') {
    $st = $pdo->prepare('SELECT icon_path FROM payment_methods WHERE id=?');
    $st->execute([$id]);
    $iconPath = $st->fetchColumn();
  }

  if (!empty($_FILES['icon']['name'])) {
    [$ok, $result] = pm_upload_icon($_FILES['icon']);
    if (!$ok) {
      die('Erro no upload de ícone: '.$result);
    }
    if ($iconPath && file_exists(__DIR__.'/'.$iconPath)) {
      @unlink(__DIR__.'/'.$iconPath);
    }
    $iconPath = $result;
  }

  if ($action === 'create') {
    $check = $pdo->prepare('SELECT COUNT(*) FROM payment_methods WHERE code = ?');
    $check->execute([$code]);
    if ($check->fetchColumn()) {
      die('Código já utilizado por outro método.');
    }
    $ins = $pdo->prepare('INSERT INTO payment_methods(code,name,description,instructions,settings,icon_path,is_active,require_receipt,sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
    $sortOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM payment_methods')->fetchColumn();
    $ins->execute([
      $code,
      $name,
      $description,
      $instructions,
      json_encode($settings, JSON_UNESCAPED_UNICODE),
      $iconPath,
      $isActive,
      $requireReceipt,
      $sortOrder
    ]);
  } else {
    $dup = $pdo->prepare('SELECT COUNT(*) FROM payment_methods WHERE code = ? AND id <> ?');
    $dup->execute([$code, $id]);
    if ($dup->fetchColumn()) {
      die('Outro método já utiliza este código.');
    }
    $upd = $pdo->prepare('UPDATE payment_methods SET code=?, name=?, description=?, instructions=?, settings=?, icon_path=?, is_active=?, require_receipt=?, updated_at=NOW() WHERE id=?');
    $upd->execute([
      $code,
      $name,
      $description,
      $instructions,
      json_encode($settings, JSON_UNESCAPED_UNICODE),
      $iconPath,
      $isActive,
      $requireReceipt,
      $id
    ]);
  }

  header('Location: settings.php?tab=payments');
  exit;
}

if ($action === 'save_general' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  require_admin_capability('manage_settings');
  $errors = [];

  $storeName = pm_sanitize($_POST['store_name'] ?? '', 120);
  if ($storeName === '') {
    $errors[] = 'Informe o nome da loja.';
  } else {
    setting_set('store_name', $storeName);
  }

  $storeEmail = trim((string)($_POST['store_email'] ?? ''));
  if ($storeEmail !== '') {
    if (validate_email($storeEmail)) {
      setting_set('store_email', $storeEmail);
    } else {
      $errors[] = 'E-mail de suporte inválido.';
    }
  } else {
    setting_set('store_email', '');
  }

  $storePhone = pm_sanitize($_POST['store_phone'] ?? '', 60);
  setting_set('store_phone', $storePhone);

  $storeAddress = pm_sanitize($_POST['store_address'] ?? '', 240);
  setting_set('store_address', $storeAddress);

  $metaTitle = pm_sanitize($_POST['store_meta_title'] ?? '', 160);
  if ($metaTitle === '') {
    $metaTitle = ($storeName ?: 'Get Power Research').' | Loja';
  }
  setting_set('store_meta_title', $metaTitle);

  $pwaName = pm_sanitize($_POST['pwa_name'] ?? '', 80);
  if ($pwaName === '') {
    $pwaName = $storeName ?: 'Get Power Research';
  }
  setting_set('pwa_name', $pwaName);

  $pwaShort = pm_sanitize($_POST['pwa_short_name'] ?? '', 40);
  if ($pwaShort === '') {
    $pwaShort = $pwaName;
  }
  setting_set('pwa_short_name', $pwaShort);

  if (!empty($_FILES['store_logo']['name'])) {
    $upload = save_logo_upload($_FILES['store_logo']);
    if (!empty($upload['success'])) {
      setting_set('store_logo_url', $upload['path']);
      setting_set('store_logo', $upload['path']);
    } else {
      $errors[] = $upload['message'] ?? 'Falha ao enviar logo.';
    }
  }

  $heroTitle = pm_sanitize($_POST['home_hero_title'] ?? '', 160);
  $heroSubtitle = pm_sanitize($_POST['home_hero_subtitle'] ?? '', 240);
  if ($heroTitle === '') {
    $heroTitle = 'Tudo para sua saúde';
  }
  if ($heroSubtitle === '') {
    $heroSubtitle = 'Experiência de app, rápida e segura.';
  }
  setting_set('home_hero_title', $heroTitle);
  setting_set('home_hero_subtitle', $heroSubtitle);

  $featuredEnabled = isset($_POST['home_featured_enabled']) ? '1' : '0';
  $featuredTitle = pm_sanitize($_POST['home_featured_title'] ?? '', 80);
  $featuredSubtitle = pm_safe_html($_POST['home_featured_subtitle'] ?? '', '<br><strong><em><span>', 400);
  $featuredLabel = pm_sanitize($_POST['home_featured_label'] ?? '', 80);
  $featuredBadgeTitle = pm_safe_html($_POST['home_featured_badge_title'] ?? '', '<br><strong><em><span>', 240);
  $featuredBadgeText = pm_safe_html($_POST['home_featured_badge_text'] ?? '', '<br><strong><em><span>', 400);
  if ($featuredTitle === '') {
    $featuredTitle = 'Ofertas em destaque';
  }
  if ($featuredSubtitle === '') {
    $featuredSubtitle = 'Seleção especial com preços imperdíveis.';
  }
  if ($featuredLabel === '') {
    $featuredLabel = 'Oferta destaque';
  }
  if ($featuredBadgeTitle === '') {
    $featuredBadgeTitle = 'Seleção especial';
  }
  if ($featuredBadgeText === '') {
    $featuredBadgeText = 'Selecionados com carinho para você';
  }
  setting_set('home_featured_enabled', $featuredEnabled);
  setting_set('home_featured_title', $featuredTitle);
  setting_set('home_featured_subtitle', $featuredSubtitle);
  setting_set('home_featured_label', $featuredLabel);
  setting_set('home_featured_badge_title', $featuredBadgeTitle);
  setting_set('home_featured_badge_text', $featuredBadgeText);

  $footerCopy = pm_clip_text($_POST['footer_copy'] ?? '', 280);
  if ($footerCopy === '') {
    $footerCopy = '© {{year}} '.($storeName ?: 'Sua Loja').'. Todos os direitos reservados.';
  }
  setting_set('footer_copy', $footerCopy);

  $hoursEnabled = !empty($_POST['store_hours_enabled']) ? '1' : '0';
  setting_set('store_hours_enabled', $hoursEnabled);

  $hoursLabel = pm_sanitize($_POST['store_hours_label'] ?? '', 160);
  if ($hoursLabel === '') {
    $hoursLabel = 'Seg a Sex: 09h às 18h (BRT)';
  }
  setting_set('store_hours_label', $hoursLabel);

  $openInput = trim((string)($_POST['store_hours_open_time'] ?? ''));
  if (!preg_match('/^\d{2}:\d{2}$/', $openInput)) {
    $openInput = '09:00';
  }
  setting_set('store_hours_open_time', $openInput);

  $closeInput = trim((string)($_POST['store_hours_close_time'] ?? ''));
  if (!preg_match('/^\d{2}:\d{2}$/', $closeInput)) {
    $closeInput = '18:00';
  }
  setting_set('store_hours_close_time', $closeInput);

  $tzInput = trim((string)($_POST['store_hours_timezone'] ?? ''));
  if ($tzInput === '') {
    $tzInput = 'America/Sao_Paulo';
  }
  try {
    $tzInput = (new DateTimeZone($tzInput))->getName();
  } catch (Throwable $e) {
    $tzInput = 'America/Sao_Paulo';
  }
  setting_set('store_hours_timezone', $tzInput);

  $emailFromName = pm_sanitize($_POST['email_from_name'] ?? '', 160);
  if ($emailFromName === '') {
    $emailFromName = $storeName ?: 'Get Power Research';
  }
  setting_set('email_from_name', $emailFromName);

  $emailFromAddress = trim((string)($_POST['email_from_address'] ?? ''));
  if ($emailFromAddress !== '' && !validate_email($emailFromAddress)) {
    $errors[] = 'Remetente (e-mail) inválido.';
  } else {
    if ($emailFromAddress === '') {
      $emailFromAddress = $storeEmail ?: setting_get('store_email', '');
    }
    setting_set('email_from_address', $emailFromAddress);
  }

  $a2hsTitle = pm_sanitize($_POST['a2hs_title'] ?? '', 160);
  if ($a2hsTitle === '') {
    $a2hsTitle = 'Instalar App '.($storeName ?: 'Get Power Research');
  }
  setting_set('a2hs_title', $a2hsTitle);

  $a2hsSubtitle = pm_clip_text($_POST['a2hs_subtitle'] ?? '', 240);
  if ($a2hsSubtitle === '') {
    $a2hsSubtitle = 'Experiência completa no seu dispositivo.';
  }
  setting_set('a2hs_subtitle', $a2hsSubtitle);

  $a2hsButton = pm_sanitize($_POST['a2hs_button_label'] ?? '', 80);
  if ($a2hsButton === '') {
    $a2hsButton = 'Instalar App';
  }
  setting_set('a2hs_button_label', $a2hsButton);

  $currentA2hsIcon = setting_get('a2hs_icon', '');
  if (!empty($_FILES['a2hs_icon']['name'])) {
    $upload = save_theme_asset_upload($_FILES['a2hs_icon'], 'pwa', 'a2hs-icon', 1_048_576);
    if (!empty($upload['success'])) {
      if ($currentA2hsIcon && strpos($currentA2hsIcon, 'storage/') === 0) {
        $full = __DIR__ . '/' . ltrim($currentA2hsIcon, '/');
        if (is_file($full)) {
          @unlink($full);
        }
      }
      setting_set('a2hs_icon', $upload['path']);
    } else {
      $errors[] = $upload['message'] ?? 'Falha ao enviar ícone do popup.';
    }
  } elseif (!empty($_POST['a2hs_icon_remove'])) {
    if ($currentA2hsIcon && strpos($currentA2hsIcon, 'storage/') === 0) {
      $full = __DIR__ . '/' . ltrim($currentA2hsIcon, '/');
      if (is_file($full)) {
        @unlink($full);
      }
    }
    setting_set('a2hs_icon', '');
  }

  $emailDefaultSet = email_template_defaults($storeName ?: (cfg()['store']['name'] ?? 'Sua Loja'));
  $emailCustomerSubject = pm_sanitize($_POST['email_customer_subject'] ?? '', 180);
  if ($emailCustomerSubject === '') {
    $emailCustomerSubject = $emailDefaultSet['customer_subject'];
  }
  setting_set('email_customer_subject', $emailCustomerSubject);

  $emailCustomerBody = pm_clip_text($_POST['email_customer_body'] ?? '', 8000);
  if ($emailCustomerBody === '') {
    $emailCustomerBody = $emailDefaultSet['customer_body'];
  }
  setting_set('email_customer_body', $emailCustomerBody);

  $emailAdminSubject = pm_sanitize($_POST['email_admin_subject'] ?? '', 180);
  if ($emailAdminSubject === '') {
    $emailAdminSubject = $emailDefaultSet['admin_subject'];
  }
  setting_set('email_admin_subject', $emailAdminSubject);

  $emailAdminBody = pm_clip_text($_POST['email_admin_body'] ?? '', 8000);
  if ($emailAdminBody === '') {
    $emailAdminBody = $emailDefaultSet['admin_body'];
  }
  setting_set('email_admin_body', $emailAdminBody);

  $whatsEnabled = isset($_POST['whatsapp_enabled']) ? '1' : '0';
  $whatsNumberRaw = pm_sanitize($_POST['whatsapp_number'] ?? '', 40);
  $whatsNumber = preg_replace('/\D+/', '', $whatsNumberRaw);
  $whatsButtonText = pm_sanitize($_POST['whatsapp_button_text'] ?? '', 80);
  $whatsMessage = pm_sanitize($_POST['whatsapp_message'] ?? '', 400);
  if ($whatsButtonText === '') {
    $whatsButtonText = 'Fale com a gente';
  }
  if ($whatsMessage === '') {
    $whatsMessage = 'Olá! Gostaria de tirar uma dúvida sobre os produtos.';
  }
  setting_set('whatsapp_enabled', $whatsEnabled);
  setting_set('whatsapp_number', $whatsNumber);
  setting_set('whatsapp_button_text', $whatsButtonText);
  setting_set('whatsapp_message', $whatsMessage);

  if (!empty($_FILES['pwa_icon']['name'])) {
    $pwaUpload = save_pwa_icon_upload($_FILES['pwa_icon']);
    if (empty($pwaUpload['success'])) {
      $errors[] = $pwaUpload['message'] ?? 'Falha ao atualizar o ícone do app.';
    }
  }
  $themeColor = pm_sanitize($_POST['theme_color'] ?? '#2060C8', 20);
  if (!preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $themeColor)) {
    $themeColor = '#2060C8';
  }
  setting_set('theme_color', strtoupper($themeColor));
  $headerSublineNew = pm_sanitize($_POST['header_subline'] ?? '', 120);
  if ($headerSublineNew === '') $headerSublineNew = 'Loja Online';
  setting_set('header_subline', $headerSublineNew);
  $footerTitleNew = pm_sanitize($_POST['footer_title'] ?? '', 80);
  if ($footerTitleNew === '') $footerTitleNew = 'Get Power Research';
  setting_set('footer_title', $footerTitleNew);
  $footerDescriptionNew = pm_sanitize($_POST['footer_description'] ?? '', 160);
  if ($footerDescriptionNew === '') $footerDescriptionNew = 'Sua loja online com experiência de app.';
  setting_set('footer_description', $footerDescriptionNew);

  $googleAnalyticsCode = pm_clip_text($_POST['google_analytics_code'] ?? '', 8000);
  setting_set('google_analytics_code', $googleAnalyticsCode);

  $policyAllowedTags = '<p><br><strong><em><span><ul><ol><li><a><h1><h2><h3>';
  $privacyContent = pm_safe_html($_POST['privacy_policy_content'] ?? '', $policyAllowedTags, 10000);
  $refundContent = pm_safe_html($_POST['refund_policy_content'] ?? '', $policyAllowedTags, 10000);
  setting_set('privacy_policy_content', $privacyContent);
  setting_set('refund_policy_content', $refundContent);

  if ($errors) {
    $_SESSION['settings_general_error'] = implode(' ', $errors);
    header('Location: settings.php?tab=general&error=1');
    exit;
  }

  header('Location: settings.php?tab=general&saved=1');
  exit;
}

if ($action === 'export_cities') {
  if (!csrf_check($_GET['csrf'] ?? '')) die('CSRF');
  require_super_admin();
  $cityMap = checkout_group_cities();
  $lines = [];
  foreach ($cityMap as $key => $cities) {
    [$country, $state] = array_pad(explode('::', $key, 2), 2, '');
    foreach ($cities as $name) {
      $lines[] = strtoupper($country).'|'.strtoupper($state).'|'.$name;
    }
  }
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="checkout_cities.txt"');
  echo "country|state|city\n";
  echo implode("\n", $lines);
  exit;
}

if ($action === 'import_cities' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  require_super_admin();
  $cityData = trim((string)($_POST['city_data'] ?? ''));
  $parsed = [];
  if ($cityData !== '') {
    foreach (preg_split('/\r?\n/', $cityData) as $line) {
      $line = trim($line);
      if ($line === '' || stripos($line, 'country|state|city') === 0) {
        continue;
      }
      $parts = array_map('trim', explode('|', $line));
      if (count($parts) >= 3) {
        $country = strtoupper($parts[0]);
        $state   = strtoupper($parts[1]);
        $name    = $parts[2];
        if ($country !== '' && $state !== '' && $name !== '') {
          $parsed[] = ['country' => $country, 'state' => $state, 'name' => $name];
        }
      }
    }
  }
  if ($parsed) {
    setting_set('checkout_cities', $parsed);
    $_SESSION['settings_cities_success'] = 'Lista de cidades importada com sucesso ('.count($parsed).' registros).';
  } else {
    $_SESSION['settings_cities_error'] = 'Nenhuma cidade válida encontrada. Use o formato COUNTRY|STATE|CITY.';
  }
  header('Location: settings.php?tab=general');
  exit;
}

if ($action === 'save_costs' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('CSRF');
  }
  require_super_admin();
  $enabled = !empty($_POST['cost_management_enabled']) ? '1' : '0';
  setting_set('cost_management_enabled', $enabled);
  $_SESSION['settings_costs_success'] = 'Configurações de gestão de custos atualizadas.';
  header('Location: settings.php?tab=costs&saved=1');
  exit;
}

if ($action === 'save_theme' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('CSRF');
  }
  require_super_admin();

  $errors = [];
  $themes = store_theme_options();
  $selectedTheme = $_POST['store_theme'] ?? 'default';
  if (!isset($themes[$selectedTheme])) {
    $selectedTheme = 'default';
  }
  setting_set('store_theme', $selectedTheme);
  $fontOptionsMap = store_category_font_options();
  $categoryFontChoiceInput = $_POST['store_category_font_choice'] ?? 'default';
  if ($categoryFontChoiceInput !== 'custom' && !isset($fontOptionsMap[$categoryFontChoiceInput])) {
    $categoryFontChoiceInput = 'default';
  }
  $categoryFontCustomInput = pm_clip_text($_POST['store_category_font_custom'] ?? '', 180);
  setting_set('store_category_font_choice', $categoryFontChoiceInput);
  setting_set('store_category_font_custom', $categoryFontCustomInput);

  $foodCurrent = theme_food_config();
  $foodNew = $foodCurrent;

  $foodNew['hero_badge'] = pm_sanitize($_POST['theme_food_hero_badge'] ?? $foodCurrent['hero_badge'], 160);
  $foodNew['hero_title'] = pm_sanitize($_POST['theme_food_hero_title'] ?? $foodCurrent['hero_title'], 160);
  $foodNew['hero_subtitle'] = pm_clip_text($_POST['theme_food_hero_subtitle'] ?? $foodCurrent['hero_subtitle'], 360);
  $foodNew['hero_description'] = pm_clip_text($_POST['theme_food_hero_description'] ?? ($foodCurrent['hero_description'] ?? ''), 400);
  $foodNew['hero_button_label'] = pm_sanitize($_POST['theme_food_hero_button_label'] ?? $foodCurrent['hero_button_label'], 120);
  $foodNew['hero_button_link'] = pm_sanitize($_POST['theme_food_hero_button_link'] ?? $foodCurrent['hero_button_link'], 255);
  $foodNew['hero_button_secondary_label'] = pm_sanitize($_POST['theme_food_hero_button_secondary_label'] ?? ($foodCurrent['hero_button_secondary_label'] ?? ''), 120);
  $foodNew['hero_button_secondary_link'] = pm_sanitize($_POST['theme_food_hero_button_secondary_link'] ?? ($foodCurrent['hero_button_secondary_link'] ?? '#'), 255);

  $heroMode = $_POST['theme_food_hero_background_mode'] ?? $foodCurrent['hero_background_mode'];
  if (!in_array($heroMode, ['image', 'solid'], true)) {
    $heroMode = 'image';
  }
  $foodNew['hero_background_mode'] = $heroMode;

  $heroBgColorInput = trim((string)($_POST['theme_food_hero_background_color'] ?? $foodCurrent['hero_background_color']));
  if ($heroBgColorInput === '') {
    $heroBgColorInput = $foodCurrent['hero_background_color'];
  }
  $foodNew['hero_background_color'] = '#'.normalize_hex_color($heroBgColorInput);

  $stats = $foodNew['hero_stats'] ?? theme_food_default_config()['hero_stats'];
  foreach ($stats as $idx => $stat) {
    $stats[$idx]['title'] = pm_sanitize($_POST['theme_food_hero_stat_title_'.$idx] ?? $stat['title'], 80);
    $stats[$idx]['description'] = pm_clip_text($_POST['theme_food_hero_stat_description_'.$idx] ?? $stat['description'], 240);
    $stats[$idx]['icon'] = pm_sanitize($_POST['theme_food_hero_stat_icon_'.$idx] ?? $stat['icon'], 60);
  }
  $foodNew['hero_stats'] = $stats;

  $foodNew['products_heading'] = pm_sanitize($_POST['theme_food_products_heading'] ?? $foodCurrent['products_heading'], 120);
  $foodNew['products_subheading'] = pm_clip_text($_POST['theme_food_products_subheading'] ?? $foodCurrent['products_subheading'], 240);
  $foodNew['products_group_by_category'] = !empty($_POST['theme_food_products_group_by_category']);
  $uncategorizedLabel = pm_sanitize($_POST['theme_food_products_uncategorized_label'] ?? ($foodCurrent['products_uncategorized_label'] ?? 'Outros sabores'), 160);
  if ($uncategorizedLabel === '') {
    $uncategorizedLabel = 'Outros sabores';
  }
  $foodNew['products_uncategorized_label'] = $uncategorizedLabel;

  $values = $foodNew['values_items'] ?? theme_food_default_config()['values_items'];
  foreach ($values as $idx => $value) {
    $values[$idx]['title'] = pm_sanitize($_POST['theme_food_value_title_'.$idx] ?? $value['title'], 80);
    $values[$idx]['description'] = pm_clip_text($_POST['theme_food_value_description_'.$idx] ?? $value['description'], 240);
    $values[$idx]['icon'] = pm_sanitize($_POST['theme_food_value_icon_'.$idx] ?? $value['icon'], 60);
  }
  $foodNew['values_items'] = $values;

  $foodNew['values_heading'] = pm_sanitize($_POST['theme_food_values_heading'] ?? $foodCurrent['values_heading'], 120);
  $foodNew['values_subheading'] = pm_clip_text($_POST['theme_food_values_subheading'] ?? $foodCurrent['values_subheading'], 240);

  $foodNew['history_heading'] = pm_sanitize($_POST['theme_food_history_heading'] ?? ($foodCurrent['history_heading'] ?? 'Nossa História'), 160);
  $foodNew['history_subheading'] = pm_clip_text($_POST['theme_food_history_subheading'] ?? ($foodCurrent['history_subheading'] ?? ''), 360);
  $foodNew['history_description'] = pm_clip_text($_POST['theme_food_history_description'] ?? ($foodCurrent['history_description'] ?? ''), 600);

  $historyCardsDefaults = theme_food_default_config()['history_cards'];
  $historyCards = $foodNew['history_cards'] ?? $historyCardsDefaults;
  foreach ($historyCardsDefaults as $idx => $cardDefault) {
    $historyCards[$idx]['icon'] = pm_sanitize($_POST['theme_food_history_card_icon_'.$idx] ?? ($historyCards[$idx]['icon'] ?? $cardDefault['icon']), 60);
    $historyCards[$idx]['title'] = pm_sanitize($_POST['theme_food_history_card_title_'.$idx] ?? ($historyCards[$idx]['title'] ?? $cardDefault['title']), 120);
    $historyCards[$idx]['description'] = pm_clip_text($_POST['theme_food_history_card_description_'.$idx] ?? ($historyCards[$idx]['description'] ?? $cardDefault['description']), 360);
  }
  $foodNew['history_cards'] = $historyCards;

  $historyStatsDefaults = theme_food_default_config()['history_stats'];
  $historyStats = $foodNew['history_stats'] ?? $historyStatsDefaults;
  foreach ($historyStatsDefaults as $idx => $statDefault) {
    $historyStats[$idx]['label'] = pm_sanitize($_POST['theme_food_history_stat_label_'.$idx] ?? ($historyStats[$idx]['label'] ?? $statDefault['label']), 160);
    $historyStats[$idx]['value'] = pm_sanitize($_POST['theme_food_history_stat_value_'.$idx] ?? ($historyStats[$idx]['value'] ?? $statDefault['value']), 40);
    $colorInput = strtoupper(trim((string)($_POST['theme_food_history_stat_color_'.$idx] ?? ($historyStats[$idx]['color'] ?? $statDefault['color']))));
    if (!preg_match('/^#[0-9A-F]{3}(?:[0-9A-F]{3})?$/', $colorInput)) {
      $colorInput = $statDefault['color'];
    }
    $historyStats[$idx]['color'] = $colorInput;
    $historyStats[$idx]['enabled'] = !empty($_POST['theme_food_history_stat_enabled_'.$idx]);
  }
  $foodNew['history_stats'] = $historyStats;

  $foodNew['highlight_heading'] = pm_sanitize($_POST['theme_food_highlight_heading'] ?? $foodCurrent['highlight_heading'], 120);
  $foodNew['highlight_subheading'] = pm_clip_text($_POST['theme_food_highlight_subheading'] ?? $foodCurrent['highlight_subheading'], 200);
  $foodNew['highlight_text'] = pm_clip_text($_POST['theme_food_highlight_text'] ?? $foodCurrent['highlight_text'], 600);
  $foodNew['highlight_button_label'] = pm_sanitize($_POST['theme_food_highlight_button_label'] ?? $foodCurrent['highlight_button_label'], 120);
  $foodNew['highlight_button_link'] = pm_sanitize($_POST['theme_food_highlight_button_link'] ?? $foodCurrent['highlight_button_link'], 255);

  $foodNew['contact_heading'] = pm_sanitize($_POST['theme_food_contact_heading'] ?? $foodCurrent['contact_heading'], 120);
  $foodNew['contact_text'] = pm_clip_text($_POST['theme_food_contact_text'] ?? $foodCurrent['contact_text'], 360);
  $foodNew['contact_form_button_label'] = pm_sanitize($_POST['theme_food_contact_form_button_label'] ?? ($foodCurrent['contact_form_button_label'] ?? 'Enviar Mensagem'), 120);

  if (!empty($_FILES['theme_food_hero_background']['name'])) {
    $upload = save_theme_asset_upload($_FILES['theme_food_hero_background'], 'food', 'hero');
    if (!empty($upload['success'])) {
      $old = $foodNew['hero_background_image'] ?? '';
      $foodNew['hero_background_image'] = $upload['path'];
      if ($old && $old !== $upload['path'] && strpos($old, 'storage/') === 0) {
        $full = __DIR__.'/'.ltrim($old, '/');
        if (is_file($full)) {
          @unlink($full);
        }
      }
    } else {
      $errors[] = $upload['message'] ?? 'Falha ao enviar o fundo da hero.';
    }
  } elseif (!empty($_POST['theme_food_hero_background_remove'])) {
    $old = $foodNew['hero_background_image'] ?? '';
    $foodNew['hero_background_image'] = '';
    if ($old && strpos($old, 'storage/') === 0) {
      $full = __DIR__.'/'.ltrim($old, '/');
      if (is_file($full)) {
        @unlink($full);
      }
    }
  }

  if (!empty($_FILES['theme_food_highlight_image']['name'])) {
    $upload = save_theme_asset_upload($_FILES['theme_food_highlight_image'], 'food', 'highlight');
    if (!empty($upload['success'])) {
      $old = $foodNew['highlight_image'] ?? '';
      $foodNew['highlight_image'] = $upload['path'];
      if ($old && $old !== $upload['path'] && strpos($old, 'storage/') === 0) {
        $full = __DIR__.'/'.ltrim($old, '/');
        if (is_file($full)) {
          @unlink($full);
        }
      }
    } else {
      $errors[] = $upload['message'] ?? 'Falha ao enviar imagem de destaque.';
    }
  } elseif (!empty($_POST['theme_food_highlight_image_remove'])) {
    $old = $foodNew['highlight_image'] ?? '';
    $foodNew['highlight_image'] = '';
    if ($old && strpos($old, 'storage/') === 0) {
      $full = __DIR__.'/'.ltrim($old, '/');
      if (is_file($full)) {
        @unlink($full);
      }
    }
  }

  if (!empty($_FILES['theme_food_history_image']['name'])) {
    $upload = save_theme_asset_upload($_FILES['theme_food_history_image'], 'food', 'history');
    if (!empty($upload['success'])) {
      $old = $foodNew['history_image'] ?? '';
      $foodNew['history_image'] = $upload['path'];
      if ($old && $old !== $upload['path'] && strpos($old, 'storage/') === 0) {
        $full = __DIR__.'/'.ltrim($old, '/');
        if (is_file($full)) {
          @unlink($full);
        }
      }
    } else {
      $errors[] = $upload['message'] ?? 'Falha ao enviar imagem da seção história.';
    }
  } elseif (!empty($_POST['theme_food_history_image_remove'])) {
    $old = $foodNew['history_image'] ?? '';
    $foodNew['history_image'] = '';
    if ($old && strpos($old, 'storage/') === 0) {
      $full = __DIR__.'/'.ltrim($old, '/');
      if (is_file($full)) {
        @unlink($full);
      }
    }
  }

  if ($errors) {
    $_SESSION['settings_theme_error'] = implode(' ', array_unique($errors));
    header('Location: settings.php?tab=theme&error=1');
    exit;
  }

  $themeSaved = setting_set('theme_food_config', $foodNew);
  $themeFileSaved = false;
  $themeConfigPath = __DIR__ . '/storage/theme_food_config.json';
  $storageDir = dirname($themeConfigPath);
  if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0775, true);
  }
  if (is_dir($storageDir) && is_writable($storageDir)) {
    $jsonPayload = json_encode($foodNew, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonPayload !== false) {
      $themeFileSaved = (@file_put_contents($themeConfigPath, $jsonPayload) !== false);
    }
  }

  if (!$themeSaved && !$themeFileSaved) {
    $_SESSION['settings_theme_error'] = 'Não foi possível salvar o tema. Verifique a conexão com o banco e as permissões de escrita em storage/.';
    header('Location: settings.php?tab=theme&error=1');
    exit;
  }

  header('Location: settings.php?tab=theme&saved=1');
  exit;
}

if ($action === 'save_experience' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('CSRF');
  }
  require_super_admin();

  $currentConfig = pwa_banner_config();

  $enabled = !empty($_POST['pwa_banner_enabled']);
  $showDelay = max(0, (int)($_POST['pwa_banner_show_delay'] ?? $currentConfig['show_delay_ms']));
  $displayDuration = max(0, (int)($_POST['pwa_banner_display_duration'] ?? $currentConfig['display_duration_ms']));
  $cooldownHours = max(0, (int)($_POST['pwa_banner_cooldown_hours'] ?? $currentConfig['cooldown_hours']));

  $message = pm_clip_text($_POST['pwa_banner_message'] ?? $currentConfig['message'], 400);
  if ($message === '') {
    $message = $currentConfig['message'];
  }
  $buttonLabel = pm_sanitize($_POST['pwa_banner_button_label'] ?? $currentConfig['button_label'], 80);
  if ($buttonLabel === '') {
    $buttonLabel = $currentConfig['button_label'];
  }

  $positionInput = strtolower((string)($_POST['pwa_banner_position'] ?? $currentConfig['position']));
  $allowedPositions = ['center','left','right'];
  if (!in_array($positionInput, $allowedPositions, true)) {
    $positionInput = $currentConfig['position'];
  }

  $colorFields = [
    'background_color' => $currentConfig['background_color'],
    'text_color' => $currentConfig['text_color'],
    'button_background' => $currentConfig['button_background'],
    'button_text_color' => $currentConfig['button_text_color'],
    'border_color' => $currentConfig['border_color'],
  ];
  foreach ($colorFields as $field => $fallback) {
    $val = strtoupper(trim((string)($_POST['pwa_banner_'.$field] ?? $fallback)));
    if (!preg_match('/^#[0-9A-F]{3}(?:[0-9A-F]{3})?$/', $val)) {
      $colorFields[$field] = $fallback;
    } else {
      $colorFields[$field] = $val;
    }
  }

  $config = [
    'enabled' => $enabled,
    'show_delay_ms' => $showDelay,
    'display_duration_ms' => $displayDuration,
    'cooldown_hours' => $cooldownHours,
    'message' => $message,
    'button_label' => $buttonLabel,
    'position' => $positionInput,
    'background_color' => $colorFields['background_color'],
    'text_color' => $colorFields['text_color'],
    'button_background' => $colorFields['button_background'],
    'button_text_color' => $colorFields['button_text_color'],
    'border_color' => $colorFields['border_color'],
  ];

  setting_set('pwa_banner_config', json_encode($config, JSON_UNESCAPED_UNICODE));

  $homeSectionsDefaults = home_sections_visibility_defaults();
  $homeSectionsInput = $_POST['home_sections'] ?? [];
  $visibility = [];
  foreach ($homeSectionsDefaults as $sectionKey => $defaultValue) {
    $visibility[$sectionKey] = !empty($homeSectionsInput[$sectionKey]);
  }
  setting_set('home_sections_visibility', json_encode($visibility, JSON_UNESCAPED_UNICODE));

  header('Location: settings.php?tab=experience&saved=1');
  exit;
}

if ($action === 'save_navigation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('CSRF');
  }
  require_super_admin();

  $errors = [];

  $headerInput = $_POST['header_links'] ?? [];
  $headerLinks = [];
  if (is_array($headerInput)) {
    $order = 0;
    foreach ($headerInput as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $label = pm_sanitize($entry['label'] ?? '', 160);
      $url = trim((string)($entry['url'] ?? ''));
      if ($label === '' || $url === '') {
        continue;
      }
      $target = strtolower(trim((string)($entry['target'] ?? '_self')));
      $target = $target === '_blank' ? '_blank' : '_self';
      $order += 10;
      $headerLinks[] = [
        'label' => $label,
        'url' => $url,
        'target' => $target,
        'order' => $order,
      ];
    }
  }

  $footerInput = $_POST['footer_links'] ?? [];
  $footerLinks = [];
  if (is_array($footerInput)) {
    $order = 0;
    foreach ($footerInput as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $label = pm_sanitize($entry['label'] ?? '', 160);
      $url = trim((string)($entry['url'] ?? ''));
      if ($label === '' || $url === '') {
        continue;
      }
      $target = strtolower(trim((string)($entry['target'] ?? '_self')));
      $target = $target === '_blank' ? '_blank' : '_self';
      $order += 10;
      $footerLinks[] = [
        'label' => $label,
        'url' => $url,
        'target' => $target,
        'order' => $order,
      ];
    }
  }

  $pagesInput = $_POST['pages'] ?? [];
  $pages = [];
  $slugRegistry = [];
  $allowedTags = '<p><br><strong><em><span><ul><ol><li><a><blockquote><h1><h2><h3><h4>';
  if (is_array($pagesInput)) {
    $orderHeader = 0;
    $orderFooter = 0;
    foreach ($pagesInput as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $title = pm_sanitize($entry['title'] ?? '', 160);
      $slug = pm_slug($entry['slug'] ?? '');
      $contentRaw = (string)($entry['content'] ?? '');
      $content = pm_safe_html($contentRaw, $allowedTags, 12000);
      if ($title === '' || $slug === '' || $content === '') {
        continue;
      }
      if (isset($slugRegistry[$slug])) {
        $errors[] = 'Já existe uma página com o slug “' . $slug . '”. Ajuste para continuar.';
        continue;
      }
      $slugRegistry[$slug] = true;
      $orderHeader += 10;
      $orderFooter += 10;
      $pages[] = [
        'title' => $title,
        'slug' => $slug,
        'content' => $content,
        'show_in_header' => !empty($entry['show_in_header']),
        'show_in_footer' => !empty($entry['show_in_footer']),
        'order_header' => $orderHeader,
        'order_footer' => $orderFooter,
      ];
    }
  }

  if ($errors) {
    $_SESSION['settings_navigation_error'] = implode(' ', array_unique($errors));
    header('Location: settings.php?tab=navigation&error=1');
    exit;
  }

  setting_set('navigation_header_links', json_encode($headerLinks, JSON_UNESCAPED_UNICODE));
  setting_set('navigation_footer_links', json_encode($footerLinks, JSON_UNESCAPED_UNICODE));
  setting_set('custom_pages', json_encode($pages, JSON_UNESCAPED_UNICODE));

  header('Location: settings.php?tab=navigation&saved=1');
  exit;
}

if ($action === 'save_social' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  require_admin_capability('manage_settings');
  $errors = [];

  $instagramUrl = trim((string)($_POST['social_instagram_url'] ?? ''));
  $facebookUrl = trim((string)($_POST['social_facebook_url'] ?? ''));

  if ($instagramUrl !== '' && !filter_var($instagramUrl, FILTER_VALIDATE_URL)) {
    $errors[] = 'Informe uma URL válida para o Instagram (ex.: https://instagram.com/sualoja).';
  }
  if ($facebookUrl !== '' && !filter_var($facebookUrl, FILTER_VALIDATE_URL)) {
    $errors[] = 'Informe uma URL válida para o Facebook (ex.: https://facebook.com/sualoja).';
  }

  if ($errors) {
    $_SESSION['settings_social_error'] = implode(' ', $errors);
    header('Location: settings.php?tab=social&error=1');
    exit;
  }

  setting_set('social_instagram_url', $instagramUrl);
  setting_set('social_facebook_url', $facebookUrl);

  header('Location: settings.php?tab=social&saved=1');
  exit;
}

if ($action === 'save_checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  require_admin_capability('manage_settings');

  $countriesRaw = trim((string)($_POST['checkout_countries'] ?? ''));
  $statesRaw = trim((string)($_POST['checkout_states'] ?? ''));
  $deliveryRaw = trim((string)($_POST['checkout_delivery_methods'] ?? ''));

  $countriesParsed = [];
  if ($countriesRaw !== '') {
    foreach (preg_split('/\r?\n/', $countriesRaw) as $line) {
      $line = trim($line);
      if ($line === '') continue;
      $parts = array_map('trim', explode('|', $line));
      if (count($parts) < 2) continue;
      $code = strtoupper(pm_sanitize($parts[0], 5));
      $label = pm_sanitize($parts[1], 80);
      if ($code === '' || $label === '') continue;
      $countriesParsed[$code] = ['code' => $code, 'name' => $label];
    }
  }
  if (!$countriesParsed) {
    foreach (checkout_default_countries() as $defaultCountry) {
      $code = strtoupper($defaultCountry['code']);
      $countriesParsed[$code] = ['code' => $code, 'name' => $defaultCountry['name']];
    }
  }

  $statesParsed = [];
  if ($statesRaw !== '') {
    foreach (preg_split('/\r?\n/', $statesRaw) as $line) {
      $line = trim($line);
      if ($line === '') continue;
      $parts = array_map('trim', explode('|', $line));
      if (count($parts) === 3) {
        [$countryCode, $stateCode, $stateName] = $parts;
      } elseif (count($parts) === 2) {
        $countryCode = 'US';
        [$stateCode, $stateName] = $parts;
      } else {
        continue;
      }
      $countryCode = strtoupper(pm_sanitize($countryCode, 5));
      $stateCode = strtoupper(pm_sanitize($stateCode, 10));
      $stateName = pm_sanitize($stateName, 100);
      if ($countryCode === '' || $stateCode === '' || $stateName === '') continue;
      $statesParsed[] = [
        'country' => $countryCode,
        'code' => $stateCode,
        'name' => $stateName,
      ];
    }
  }
  if (!$statesParsed) {
    $statesParsed = checkout_default_states();
  }

  $deliveryParsed = [];
  if ($deliveryRaw !== '') {
    foreach (preg_split('/\r?\n/', $deliveryRaw) as $line) {
      $line = trim($line);
      if ($line === '') continue;
      $parts = array_map('trim', explode('|', $line));
      if (count($parts) < 2) continue;
      $codeInput = $parts[0];
      $label = pm_sanitize($parts[1], 120);
      $description = pm_clip_text($parts[2] ?? '', 255);
      if ($label === '') continue;
      $normalizedCode = strtolower(preg_replace('/[^a-z0-9\-_]+/i', '-', $codeInput));
      if ($normalizedCode === '') {
        $normalizedCode = strtolower(preg_replace('/[^a-z0-9\-_]+/i', '-', slugify($label)));
      }
      if ($normalizedCode === '') {
        $normalizedCode = 'method';
      }
      $deliveryParsed[$normalizedCode] = [
        'code' => $normalizedCode,
        'name' => $label,
        'description' => $description,
      ];
    }
  }
  if (!$deliveryParsed) {
    foreach (checkout_default_delivery_methods() as $method) {
      $normalized = strtolower(preg_replace('/[^a-z0-9\-_]+/i', '-', $method['code'] ?? slugify($method['name'] ?? 'method')));
      $deliveryParsed[$normalized] = [
        'code' => $normalized,
        'name' => $method['name'] ?? 'Entrega padrão',
        'description' => $method['description'] ?? '',
      ];
    }
  }

  $countriesList = array_values($countriesParsed);
  $defaultCountrySubmitted = strtoupper(trim((string)($_POST['checkout_default_country'] ?? '')));
  $validCountryCodes = array_map(function ($c) { return strtoupper($c['code']); }, $countriesList);
  if (!in_array($defaultCountrySubmitted, $validCountryCodes, true)) {
    $defaultCountrySubmitted = $countriesList[0]['code'] ?? 'US';
  }

  setting_set('checkout_countries', $countriesList);
  setting_set('checkout_states', $statesParsed);
  setting_set('checkout_delivery_methods', array_values($deliveryParsed));
  setting_set('checkout_default_country', $defaultCountrySubmitted ?: 'US');

  header('Location: settings.php?tab=checkout&saved=1');
  exit;
}

try {
$methods = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
$hasWhatsapp = false;
foreach ($methods as $m) {
  if (($m['code'] ?? '') === 'whatsapp') {
    $hasWhatsapp = true;
    break;
  }
}
if (!$hasWhatsapp && $canManagePayments) {
  $sortOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM payment_methods')->fetchColumn();
  $settingsJson = json_encode([
    'type' => 'whatsapp',
    'account_label' => 'WhatsApp',
    'account_value' => '',
    'number' => '',
    'message' => 'Olá! Gostaria de finalizar meu pedido.',
    'link' => ''
  ], JSON_UNESCAPED_UNICODE);
  $insWhatsapp = $pdo->prepare('INSERT INTO payment_methods(code,name,description,instructions,settings,icon_path,is_active,require_receipt,sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
  $insWhatsapp->execute([
    'whatsapp',
    'WhatsApp',
    '',
    'Converse com nossa equipe pelo WhatsApp para concluir: {whatsapp_link}.',
    $settingsJson,
    null,
    0,
    0,
    $sortOrder
  ]);
  $methods = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
}
} catch (Throwable $e) {
  $methods = [];
}

$editRow = null;
$editSettings = [];
if ($action === 'edit' && isset($_GET['id'])) {
  $id = (int)$_GET['id'];
  $st = $pdo->prepare('SELECT * FROM payment_methods WHERE id=?');
  $st->execute([$id]);
  $editRow = $st->fetch(PDO::FETCH_ASSOC);
  if ($editRow) {
    $editSettings = pm_decode_settings($editRow);
    $tab = 'payments';
  }
}

$draftStmt = $pdo->prepare("SELECT content, styles FROM page_layouts WHERE page_slug=? AND status='draft' LIMIT 1");
$draftStmt->execute(['home']);
$draftRow = $draftStmt->fetch(PDO::FETCH_ASSOC);

$publishedStmt = $pdo->prepare("SELECT content, styles FROM page_layouts WHERE page_slug=? AND status='published' LIMIT 1");
$publishedStmt->execute(['home']);
$publishedRow = $publishedStmt->fetch(PDO::FETCH_ASSOC);

$layoutData = [
  'draft' => $draftRow ?: null,
  'published' => $publishedRow ?: null,
  'csrf' => csrf_token(),
];
$layoutJson = json_encode($layoutData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$storeCfg = cfg()['store'] ?? [];
$storeNameCurrent = setting_get('store_name', $storeCfg['name'] ?? 'Get Power Research');
$storeEmailCurrent = setting_get('store_email', $storeCfg['support_email'] ?? 'contato@example.com');
$storePhoneCurrent = setting_get('store_phone', $storeCfg['phone'] ?? '');
$storeAddressCurrent = setting_get('store_address', $storeCfg['address'] ?? '');
$storeLogoCurrent = get_logo_path();
$costManagementEnabledSetting = cost_management_enabled();
$categoryFontChoiceCurrent = setting_get('store_category_font_choice', 'default');
$categoryFontCustomCurrent = setting_get('store_category_font_custom', '');
$categoryFontOptions = store_category_font_options();
$themeColorCurrent = setting_get('theme_color', '#2060C8');
$generalError = $_SESSION['settings_general_error'] ?? '';
unset($_SESSION['settings_general_error']);
$socialError = $_SESSION['settings_social_error'] ?? '';
unset($_SESSION['settings_social_error']);
$themeError = $_SESSION['settings_theme_error'] ?? '';
unset($_SESSION['settings_theme_error']);
$navigationError = $_SESSION['settings_navigation_error'] ?? '';
unset($_SESSION['settings_navigation_error']);
$costsSuccess = $_SESSION['settings_costs_success'] ?? '';
unset($_SESSION['settings_costs_success']);

$pwaBannerConfig = pwa_banner_config();
$homeSectionsVisibility = home_sections_visibility();
$headerLinksCurrent = navigation_links_raw('header');
$footerLinksCurrent = navigation_links_raw('footer');
$customPagesCurrent = custom_pages_list();
$cacheBustNotice = $_SESSION['cache_bust_notice'] ?? '';
$cacheBustError = $_SESSION['cache_bust_error'] ?? '';
unset($_SESSION['cache_bust_notice'], $_SESSION['cache_bust_error']);
$cacheBustCurrentToken = function_exists('cache_bust_current_token') ? cache_bust_current_token() : '';

$checkoutCountriesCurrent = checkout_get_countries();
$checkoutStatesCurrent = checkout_get_states();
$checkoutDeliveryCurrent = checkout_get_delivery_methods();
$checkoutCountriesText = implode("\n", array_filter(array_map(function ($entry) {
  $code = strtoupper($entry['code'] ?? '');
  $name = $entry['name'] ?? '';
  return $code !== '' ? $code.'|'.$name : '';
}, $checkoutCountriesCurrent)));
$checkoutStatesText = implode("\n", array_filter(array_map(function ($entry) {
  $country = strtoupper($entry['country'] ?? 'US');
  $code = strtoupper($entry['code'] ?? '');
  $name = $entry['name'] ?? '';
  return ($country && $code && $name) ? $country.'|'.$code.'|'.$name : '';
}, $checkoutStatesCurrent)));
$checkoutDeliveryText = implode("\n", array_filter(array_map(function ($entry) {
  $code = $entry['code'] ?? '';
  $name = $entry['name'] ?? '';
  $description = $entry['description'] ?? '';
  return ($code && $name) ? $code.'|'.$name.'|'.$description : '';
}, $checkoutDeliveryCurrent)));
$checkoutDefaultCountryCurrent = setting_get('checkout_default_country', $checkoutCountriesCurrent[0]['code'] ?? 'US');
$checkoutDefaultCountryCurrent = strtoupper(trim((string)$checkoutDefaultCountryCurrent));

$sections = [
  [
    'key' => 'general',
    'title' => 'Dados da loja',
    'description' => 'Nome, endereço, telefone, e-mail e logo exibidos para os clientes.',
    'icon' => 'fa-store'
  ],
  [
    'key' => 'payments',
    'title' => 'Pagamentos',
    'description' => 'Configure métodos ativos, instruções personalizadas e ordem de exibição.',
    'icon' => 'fa-credit-card'
  ],
  [
    'key' => 'checkout',
    'title' => 'Checkout',
    'description' => 'Defina países, estados e métodos de entrega exibidos no checkout.',
    'icon' => 'fa-truck-fast'
  ],
  [
    'key' => 'social',
    'title' => 'Redes sociais',
    'description' => 'Links das redes sociais exibidos na loja e em materiais de contato.',
    'icon' => 'fa-earth-americas'
  ],
  [
    'key' => 'builder',
    'title' => 'Editor da Home',
    'description' => 'Personalize a página inicial com o editor visual (drag-and-drop).',
    'icon' => 'fa-paintbrush'
  ],
];

if ($isSuperAdmin) {
  $sections[] = [
    'key' => 'theme',
    'title' => 'Temas',
    'description' => 'Gerencie o visual da vitrine e os conteúdos do tema alimentação.',
    'icon' => 'fa-palette'
  ];
  $sections[] = [
    'key' => 'costs',
    'title' => 'Gestão de custos',
    'description' => 'Ative o controle de custos e margens por produto.',
    'icon' => 'fa-scale-balanced'
  ];
  $sections[] = [
    'key' => 'experience',
    'title' => 'Experiência',
    'description' => 'Banner PWA e visibilidade das seções da home.',
    'icon' => 'fa-mobile-screen-button'
  ];
  $sections[] = [
    'key' => 'navigation',
    'title' => 'Menus & páginas',
    'description' => 'Organize os menus principais e cadastre páginas institucionais.',
    'icon' => 'fa-bars-staggered'
  ];
}

admin_header('Configurações');
?>
<section class="page-header">
  <div class="page-header__content">
    <p class="page-eyebrow">Painel Administrativo</p>
    <h1>Configurações da plataforma</h1>
    <p class="page-subtitle">Ajuste rapidamente informações da loja, pagamentos e layout da home.</p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn-ghost" href="dashboard.php">
      <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
      <span>Voltar ao dashboard</span>
    </a>
  </div>
</section>

<div class="quick-links">
  <a class="quick-link" href="settings.php?tab=general">
    <span class="icon"><i class="fa-solid fa-store" aria-hidden="true"></i></span>
    <span>
      <span class="quick-link__title">Dados gerais</span>
      <span class="quick-link__desc">Logo, contatos e textos da vitrine</span>
    </span>
  </a>
  <a class="quick-link" href="settings.php?tab=payments">
    <span class="icon"><i class="fa-solid fa-credit-card" aria-hidden="true"></i></span>
    <span>
      <span class="quick-link__title">Pagamentos</span>
      <span class="quick-link__desc">Formas de pagamento e instruções</span>
    </span>
  </a>
  <a class="quick-link" href="settings.php?tab=checkout">
    <span class="icon"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span>
    <span>
      <span class="quick-link__title">Checkout</span>
      <span class="quick-link__desc">Campos, países e métodos de entrega</span>
    </span>
  </a>
  <a class="quick-link" href="settings.php?tab=social">
    <span class="icon"><i class="fa-brands fa-instagram" aria-hidden="true"></i></span>
    <span>
      <span class="quick-link__title">Redes sociais</span>
      <span class="quick-link__desc">Links do Instagram e Facebook</span>
    </span>
  </a>
  <?php if ($isSuperAdmin): ?>
  <a class="quick-link" href="settings.php?tab=theme">
    <span class="icon"><i class="fa-solid fa-palette" aria-hidden="true"></i></span>
    <span>
      <span class="quick-link__title">Temas</span>
      <span class="quick-link__desc">Escolha e personalize o tema da vitrine</span>
    </span>
  </a>
  <a class="quick-link" href="settings.php?tab=costs">
    <span class="icon"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i></span>
    <span>
      <span class="quick-link__title">Gestão de custos</span>
      <span class="quick-link__desc">Defina custos e margens por produto</span>
    </span>
  </a>
  <a class="quick-link" href="settings.php?tab=experience">
    <span class="icon"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i></span>
    <span>
      <span class="quick-link__title">Experiência</span>
      <span class="quick-link__desc">Banner PWA e visibilidade da home</span>
    </span>
  </a>
  <a class="quick-link" href="settings.php?tab=navigation">
    <span class="icon"><i class="fa-solid fa-bars-staggered" aria-hidden="true"></i></span>
    <span>
      <span class="quick-link__title">Menus & páginas</span>
      <span class="quick-link__desc">Gerencie navegação e páginas extras</span>
    </span>
  </a>
  <?php endif; ?>
  <a class="quick-link" href="settings.php?tab=builder">
    <span class="icon"><i class="fa-solid fa-paintbrush" aria-hidden="true"></i></span>
    <span>
      <span class="quick-link__title">Editor da home</span>
      <span class="quick-link__desc">Monte a página inicial em tempo real</span>
    </span>
  </a>
</div>

<div class="tab-controls">
    <?php foreach ($sections as $section): ?>
      <a href="settings.php?tab=<?= $section['key']; ?>" class="<?= $tab === $section['key'] ? 'active' : ''; ?>">
        <i class="fa-solid <?= $section['icon']; ?>" aria-hidden="true"></i><?= sanitize_html($section['title']); ?>
      </a>
    <?php endforeach; ?>
</div>

<div class="settings-grid">
  <div data-tab-panel="general" class="card brand-card <?= $tab === 'general' ? '' : 'hidden'; ?>">
    <div class="card-body settings-form">
    <h2 class="text-lg font-semibold mb-1">Informações da Loja</h2>
    <div class="alert alert-warning"><i class="fa-solid fa-circle-info"></i><span>Versão do sistema: <strong>3.0 — criação Mike Lins</strong></span></div>
    <?php if ($isSuperAdmin): ?>
      <div id="cache-busting" class="mb-4 rounded-2xl border border-brand-100 bg-brand-50/60 p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-brand-900">Forçar atualização (cache bust)</p>
            <p class="text-xs text-gray-600">Token atual: <code class="font-mono text-xs bg-white/70 px-1.5 py-0.5 rounded"><?= sanitize_html($cacheBustCurrentToken); ?></code></p>
          </div>
          <form method="post" action="settings.php?tab=general&amp;action=cache_bust_refresh">
            <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
            <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-arrows-rotate mr-1"></i>Gerar novo token</button>
          </form>
        </div>
        <p class="text-xs text-gray-600">Ao regenerar o token, todas as folhas de estilo, scripts, service worker e manifest são carregados novamente em navegadores com cache agressivo (Hostinger/LiteSpeed).</p>
        <?php if ($cacheBustNotice): ?>
          <div class="alert alert-success mt-2">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= sanitize_html($cacheBustNotice); ?></span>
          </div>
        <?php endif; ?>
        <?php if ($cacheBustError): ?>
          <div class="alert alert-error mt-2">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= sanitize_html($cacheBustError); ?></span>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <span>Configurações atualizadas com sucesso.</span>
      </div>
    <?php endif; ?>
    <?php if ($generalError): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span><?= sanitize_html($generalError); ?></span>
      </div>
    <?php endif; ?>
    <?php
      $heroTitleCurrent = setting_get('home_hero_title', 'Tudo para sua saúde');
      $heroSubtitleCurrent = setting_get('home_hero_subtitle', 'Experiência de app, rápida e segura.');
$featuredEnabledCurrent = (int)setting_get('home_featured_enabled', '0');
$featuredLabelCurrent = setting_get('home_featured_label', 'Oferta destaque');
$featuredTitleCurrent = setting_get('home_featured_title', 'Ofertas em destaque');
$featuredSubtitleCurrent = setting_get('home_featured_subtitle', 'Seleção especial com preços imperdíveis.');
$featuredBadgeTitleCurrent = setting_get('home_featured_badge_title', 'Seleção especial');
$featuredBadgeTextCurrent = setting_get('home_featured_badge_text', 'Selecionados com carinho para você');
$storeHoursEnabledCurrent = setting_get('store_hours_enabled', '0') === '1';
$storeHoursLabelCurrent = setting_get('store_hours_label', 'Seg a Sex: 09h às 18h (BRT)');
$storeHoursOpenTimeCurrent = setting_get('store_hours_open_time', '09:00');
$storeHoursCloseTimeCurrent = setting_get('store_hours_close_time', '18:00');
$storeHoursTimezoneCurrent = setting_get('store_hours_timezone', 'America/Sao_Paulo');
$a2hsTitleCurrent = setting_get('a2hs_title', 'Instalar App '.($storeNameCurrent ?: 'Get Power Research'));
$a2hsSubtitleCurrent = setting_get('a2hs_subtitle', 'Experiência completa no seu dispositivo.');
$a2hsButtonCurrent = setting_get('a2hs_button_label', 'Instalar App');
$a2hsIconCurrent = setting_get('a2hs_icon', '');
$a2hsIconPreview = '';
if ($a2hsIconCurrent) {
  $iconPath = ltrim($a2hsIconCurrent, '/');
  $previewPath = function_exists('cache_busted_url') ? cache_busted_url($iconPath) : $iconPath;
  $a2hsIconPreview = '/' . ltrim($previewPath, '/');
}
$emailFromNameCurrent = setting_get('email_from_name', $storeNameCurrent ?: 'Get Power Research');
$emailFromAddressCurrent = setting_get('email_from_address', setting_get('store_email', $storeEmailCurrent));
$emailDefaults = email_template_defaults($storeNameCurrent ?: ($storeCfg['name'] ?? ''));
$emailCustomerSubjectCurrent = setting_get('email_customer_subject', $emailDefaults['customer_subject']);
$emailCustomerBodyCurrent = setting_get('email_customer_body', $emailDefaults['customer_body']);
$emailAdminSubjectCurrent = setting_get('email_admin_subject', $emailDefaults['admin_subject']);
$emailAdminBodyCurrent = setting_get('email_admin_body', $emailDefaults['admin_body']);
$whatsappEnabled = (int)setting_get('whatsapp_enabled', '0');
$whatsappNumber = setting_get('whatsapp_number', '');
$whatsappButtonText = setting_get('whatsapp_button_text', 'Fale com a gente');
$whatsappMessage = setting_get('whatsapp_message', 'Olá! Gostaria de tirar uma dúvida sobre os produtos.');
$headerSublineCurrent = setting_get('header_subline', 'Loja Online');
$footerTitleCurrent = setting_get('footer_title', 'Get Power Research');
$footerDescriptionCurrent = setting_get('footer_description', 'Sua loja online com experiência de app.');
$footerCopyCurrent = setting_get('footer_copy', '© {{year}} '.($storeNameCurrent ?: 'Sua Loja').'. Todos os direitos reservados.');
$googleAnalyticsCurrent = setting_get('google_analytics_code', '');
$privacyPolicyCurrent = setting_get('privacy_policy_content', '');
$refundPolicyCurrent = setting_get('refund_policy_content', '');
$heroBackgroundCurrent = setting_get('hero_background', 'gradient');
$heroAccentColorCurrent = setting_get('hero_accent_color', '#F59E0B');
$instagramCurrent = setting_get('social_instagram_url', '');
$facebookCurrent = setting_get('social_facebook_url', '');
$metaTitleCurrent = setting_get('store_meta_title', ($storeNameCurrent ?: 'Get Power Research').' | Loja');
$pwaNameCurrent = setting_get('pwa_name', $storeNameCurrent ?: 'Get Power Research');
$pwaShortNameCurrent = setting_get('pwa_short_name', $pwaNameCurrent);
$pwaIcons = get_pwa_icon_paths();
$pwaIconPreview = pwa_icon_url(192);
$storeLogoPreviewUrl = '';
if ($storeLogoCurrent) {
  $logoPreviewPath = function_exists('cache_busted_url') ? cache_busted_url($storeLogoCurrent) : $storeLogoCurrent;
  $storeLogoPreviewUrl = '/' . ltrim($logoPreviewPath, '/');
}
$cityTextareaValue = '';
if ($isSuperAdmin) {
  $cityTextareaLines = [];
  foreach (checkout_group_cities() as $key => $cities) {
    [$cCountry, $cState] = array_pad(explode('::', $key, 2), 2, '');
    foreach ($cities as $cityName) {
      $cityTextareaLines[] = strtoupper($cCountry).'|'.strtoupper($cState).'|'.$cityName;
    }
  }
  $cityTextareaValue = implode("\n", $cityTextareaLines);
}
$cityMessageSuccess = $_SESSION['settings_cities_success'] ?? '';
$cityMessageError = $_SESSION['settings_cities_error'] ?? '';
unset($_SESSION['settings_cities_success'], $_SESSION['settings_cities_error']);
    ?>
    <form method="post" enctype="multipart/form-data" action="settings.php?tab=general&action=save_general">
      <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
      <?php if (!$canEditSettings): ?>
        <div class="alert alert-warning">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span>Você não tem permissão para editar estas configurações. Os campos estão bloqueados para leitura.</span>
        </div>
      <?php endif; ?>
      <fieldset class="space-y-6" <?= $canEditSettings ? '' : 'disabled'; ?>>
      <div class="field-grid two">
        <div>
          <label class="block text-sm font-medium mb-1">Nome da loja</label>
          <input class="input w-full" name="store_name" value="<?= sanitize_html($storeNameCurrent); ?>" maxlength="120" required>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">E-mail de suporte</label>
          <input class="input w-full" name="store_email" type="email" value="<?= sanitize_html($storeEmailCurrent); ?>" maxlength="160" placeholder="contato@minhaloja.com">
          <p class="hint mt-1">Utilizado em notificações e exibição para o cliente.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Telefone</label>
          <input class="input w-full" name="store_phone" value="<?= sanitize_html($storePhoneCurrent); ?>" maxlength="60" placeholder="+1 (305) 555-0123">
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Endereço</label>
          <textarea class="textarea w-full" name="store_address" rows="2" maxlength="240" placeholder="Rua, bairro, cidade, estado"><?= sanitize_html($storeAddressCurrent); ?></textarea>
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Logo da loja (PNG/JPG/WEBP · máx 2MB)</label>
          <?php if ($storeLogoCurrent): ?>
            <div class="mb-3"><img src="<?= sanitize_html($storeLogoPreviewUrl ?: $storeLogoCurrent); ?>" alt="Logo atual" class="h-16 object-contain rounded-md border border-gray-200 p-2 bg-white"></div>
          <?php else: ?>
            <p class="hint mb-2">Nenhuma logo encontrada. Você pode enviar uma agora.</p>
          <?php endif; ?>
          <input class="block w-full text-sm text-gray-600" type="file" name="store_logo" accept=".png,.jpg,.jpeg,.webp">
        </div>
      </div>

      <hr class="border-gray-200">

      <h3 class="text-md font-semibold">Texto do destaque na Home</h3>
      <div class="field-grid two">
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Título principal</label>
          <input class="input w-full" name="home_hero_title" maxlength="160" value="<?= sanitize_html($heroTitleCurrent); ?>" required>
          <p class="text-xs text-gray-500 mt-1">Texto destacado exibido em negrito (ex.: "Tudo para sua saúde").</p>
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Subtítulo</label>
          <textarea class="textarea w-full" name="home_hero_subtitle" rows="2" maxlength="240" required><?= sanitize_html($heroSubtitleCurrent); ?></textarea>
          <p class="text-xs text-gray-500 mt-1">Linha de apoio exibida logo abaixo do título.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Texto curto abaixo do logo</label>
          <input class="input w-full" name="header_subline" maxlength="120" value="<?= sanitize_html($headerSublineCurrent); ?>" placeholder="Farmácia Online">
          <p class="hint mt-1">Exibido no topo, ao lado da logo.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Cor primária (theme-color)</label>
          <input class="input w-full" type="color" name="theme_color" value="<?= sanitize_html($themeColorCurrent); ?>">
          <p class="hint mt-1">Usada em navegadores móveis e barras de título.</p>
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Google Analytics</label>
          <textarea class="textarea w-full font-mono text-xs" name="google_analytics_code" rows="4" placeholder="Cole aqui o snippet do Google Analytics (ex.: gtag.js)"><?= htmlspecialchars($googleAnalyticsCurrent, ENT_QUOTES, 'UTF-8'); ?></textarea>
          <p class="hint mt-1">Cole o código completo fornecido pelo Google (incluindo &lt;script&gt;). Ele será injetado no &lt;head&gt; da loja.</p>
        </div>
        <div class="field-span-2">
          <h3 class="text-md font-semibold mt-4">Páginas legais</h3>
          <p class="text-xs text-gray-500 mb-2">Edite o conteúdo exibido nas páginas de Política de Privacidade e Política de Reembolso da loja.</p>
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Política de privacidade</label>
          <textarea class="textarea w-full h-48 font-mono text-sm" name="privacy_policy_content"><?= htmlspecialchars($privacyPolicyCurrent, ENT_QUOTES, 'UTF-8'); ?></textarea>
          <p class="hint mt-1">Aceita HTML básico (&lt;p&gt;, &lt;ul&gt;, &lt;a&gt;, &lt;strong&gt;...). Será exibida na página “Política de Privacidade”.</p>
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Política de reembolso</label>
          <textarea class="textarea w-full h-48 font-mono text-sm" name="refund_policy_content"><?= htmlspecialchars($refundPolicyCurrent, ENT_QUOTES, 'UTF-8'); ?></textarea>
          <p class="hint mt-1">Mesmo formato; exibida na página “Política de Reembolso”.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Título do rodapé</label>
          <input class="input w-full" name="footer_title" maxlength="80" value="<?= sanitize_html($footerTitleCurrent); ?>">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Descrição do rodapé</label>
          <textarea class="textarea w-full" name="footer_description" rows="2" maxlength="160"><?= sanitize_html($footerDescriptionCurrent); ?></textarea>
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Texto do rodapé</label>
          <textarea class="textarea w-full font-mono text-sm" name="footer_copy" rows="2" maxlength="280"><?= sanitize_html($footerCopyCurrent); ?></textarea>
          <p class="hint mt-1">Suporta placeholders <code>{{year}}</code> e <code>{{store_name}}</code>. Ex.: “© {{year}} {{store_name}}. Todos os direitos reservados.”</p>
        </div>
      </div>

      <div class="field-span-2 settings-panel">
        <div class="flex flex-col gap-1">
          <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-clock text-brand-600"></i> Horário de funcionamento</h3>
          <p class="text-xs text-gray-500">Esse horário aparece no topo da loja com o status Aberto/Fechado. Informe o fuso no padrão IANA (ex.: <code>America/Sao_Paulo</code>).</p>
        </div>
        <div class="field-grid two">
          <div>
            <label class="inline-flex items-center gap-2 text-sm font-medium">
              <input type="checkbox" name="store_hours_enabled" value="1" <?= $storeHoursEnabledCurrent ? 'checked' : ''; ?>>
              Exibir status Aberto/Fechado no topo
            </label>
          </div>
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Texto exibido</label>
            <input class="input w-full" name="store_hours_label" maxlength="160" value="<?= sanitize_html($storeHoursLabelCurrent); ?>" placeholder="Seg a Sex: 09h às 18h (BRT)">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Início do expediente</label>
            <input class="input w-full" type="time" name="store_hours_open_time" value="<?= sanitize_html($storeHoursOpenTimeCurrent); ?>" step="60">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Fim do expediente</label>
            <input class="input w-full" type="time" name="store_hours_close_time" value="<?= sanitize_html($storeHoursCloseTimeCurrent); ?>" step="60">
          </div>
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Fuso horário</label>
            <input class="input w-full" name="store_hours_timezone" maxlength="80" value="<?= sanitize_html($storeHoursTimezoneCurrent); ?>" placeholder="America/Sao_Paulo">
            <p class="hint mt-1">Use identificadores IANA para que o cálculo considere horário de verão automaticamente.</p>
          </div>
        </div>
      </div>

      <?php if ($isSuperAdmin): ?>
      <div class="field-span-2 settings-panel">
        <div class="flex flex-col gap-1">
          <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-database text-brand-600"></i> Importar/Exportar cidades</h3>
          <p class="text-xs text-gray-500">Use o formato <code>PAÍS|ESTADO|CIDADE</code> (ex.: <code>BR|SP|São Paulo</code>). Apenas super administradores podem alterar essa lista.</p>
        </div>
        <?php if ($cityMessageSuccess): ?>
          <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><span><?= sanitize_html($cityMessageSuccess); ?></span></div>
        <?php endif; ?>
        <?php if ($cityMessageError): ?>
          <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><span><?= sanitize_html($cityMessageError); ?></span></div>
        <?php endif; ?>
        <div class="flex flex-wrap gap-3">
          <a class="btn btn-ghost" href="settings.php?tab=general&action=export_cities&csrf=<?= csrf_token(); ?>"><i class="fa-solid fa-file-arrow-down"></i> Exportar TXT</a>
        </div>
        <form method="post" action="settings.php?tab=general&action=import_cities" class="space-y-3">
          <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
          <label class="block text-sm font-medium mb-1" for="city-data-textarea">Cole a lista de cidades</label>
          <textarea id="city-data-textarea" class="textarea w-full font-mono text-xs" name="city_data" rows="8" placeholder="BR|SP|São Paulo&#10;BR|RJ|Rio de Janeiro"><?= htmlspecialchars($cityTextareaValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
          <p class="hint mt-1">Cada linha representa uma cidade. Para limpar a lista, deixe o campo em branco antes de enviar.</p>
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-file-import mr-2"></i>Importar lista</button>
        </form>
      </div>
      <?php endif; ?>

      <hr class="border-gray-200">

      <h3 class="text-md font-semibold">Vitrine de destaques</h3>
      <div class="field-grid two">
        <div>
          <label class="block text-sm font-medium mb-1">Exibir seção na home</label>
          <select class="select" name="home_featured_enabled">
            <option value="0" <?= !$featuredEnabledCurrent ? 'selected' : ''; ?>>Ocultar</option>
            <option value="1" <?= $featuredEnabledCurrent ? 'selected' : ''; ?>>Mostrar</option>
          </select>
          <p class="hint mt-1">Quando ativa, aparece antes da lista principal com os produtos marcados como “Destaque”.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Título da seção</label>
          <input class="input w-full" name="home_featured_title" maxlength="80" value="<?= sanitize_html($featuredTitleCurrent); ?>" placeholder="Ofertas em destaque">
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Descrição de apoio</label>
          <textarea class="textarea w-full" name="home_featured_subtitle" rows="2" maxlength="200"><?= sanitize_html($featuredSubtitleCurrent); ?></textarea>
          <p class="hint mt-1">Ex.: “Seleção especial com preços imperdíveis — de X por Y”.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Etiqueta superior</label>
          <input class="input w-full" name="home_featured_label" maxlength="80" value="<?= sanitize_html($featuredLabelCurrent); ?>" placeholder="Oferta destaque">
          <p class="hint mt-1">Texto pequeno exibido acima do título.</p>
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Título principal (H1)</label>
          <input class="input w-full" name="home_featured_badge_title" maxlength="120" value="<?= sanitize_html($featuredBadgeTitleCurrent); ?>" placeholder="Seleção especial">
        </div>
        <div class="field-span-2">
          <label class="block text-sm font-medium mb-1">Texto complementar</label>
          <textarea class="textarea w-full" name="home_featured_badge_text" rows="2" maxlength="240"><?= sanitize_html($featuredBadgeTextCurrent); ?></textarea>
        </div>
      </div>

      <div class="field-span-2 settings-panel">
        <div class="flex flex-col gap-1">
          <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-envelope text-brand-600"></i> Templates de e-mail</h3>
          <p class="text-xs text-gray-500">Personalize os e-mails enviados para o cliente e para a equipe. Placeholders disponíveis: <code>{{site_name}}</code>, <code>{{store_name}}</code>, <code>{{order_id}}</code>, <code>{{order_number}}</code>, <code>{{order_date}}</code>, <code>{{customer_name}}</code>, <code>{{billing_full_name}}</code>, <code>{{billing_email}}</code>, <code>{{billing_phone}}</code>, <code>{{billing_address_html}}</code>, <code>{{shipping_address_html}}</code>, <code>{{shipping_method}}</code>, <code>{{shipping_method_description}}</code>, <code>{{order_items_rows}}</code>, <code>{{order_items}}</code>, <code>{{order_subtotal}}</code>, <code>{{order_shipping_total}}</code>, <code>{{order_tax_total}}</code>, <code>{{order_discount_total}}</code>, <code>{{order_total}}</code>, <code>{{payment_method}}</code>, <code>{{payment_status}}</code>, <code>{{payment_reference}}</code>, <code>{{track_link}}</code>, <code>{{track_url}}</code>, <code>{{support_email}}</code>, <code>{{customer_note}}</code>, <code>{{admin_order_url}}</code>, <code>{{additional_content}}</code>, <code>{{year}}</code>.</p>
        </div>
        <div class="field-grid two">
          <div>
            <label class="block text-sm font-medium mb-1">Nome do remetente dos e-mails</label>
            <input class="input w-full" name="email_from_name" maxlength="160" value="<?= sanitize_html($emailFromNameCurrent); ?>" placeholder="<?= sanitize_html($storeNameCurrent); ?>">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">E-mail do remetente</label>
            <input class="input w-full" type="email" name="email_from_address" maxlength="160" value="<?= sanitize_html($emailFromAddressCurrent); ?>" placeholder="<?= sanitize_html($storeEmailCurrent); ?>">
            <p class="hint mt-1">Esse endereço será exibido no campo “De:” e usado para notificações internas.</p>
          </div>
        </div>
        <div class="field-grid two">
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Assunto (cliente)</label>
            <input class="input w-full" name="email_customer_subject" maxlength="180" value="<?= htmlspecialchars($emailCustomerSubjectCurrent, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Conteúdo (cliente)</label>
            <textarea class="textarea w-full font-mono text-sm h-44" name="email_customer_body"><?= htmlspecialchars($emailCustomerBodyCurrent, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <p class="hint mt-1">Você pode usar HTML básico. Ex.: &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;.</p>
          </div>
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Assunto (admin)</label>
            <input class="input w-full" name="email_admin_subject" maxlength="180" value="<?= htmlspecialchars($emailAdminSubjectCurrent, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Conteúdo (admin)</label>
            <textarea class="textarea w-full font-mono text-sm h-44" name="email_admin_body"><?= htmlspecialchars($emailAdminBodyCurrent, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
        </div>
      </div>

      <div class="field-span-2 settings-panel">
        <h3 class="text-md font-semibold mb-2 flex items-center gap-2"><i class="fa-brands fa-whatsapp text-[#25D366]"></i> WhatsApp Flutuante</h3>
        <p class="text-xs text-gray-500 mb-3">Defina o número e a mensagem exibida no botão flutuante da loja. O link abre a conversa direto no WhatsApp.</p>
        <div class="field-grid two">
          <label class="inline-flex items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="whatsapp_enabled" value="1" <?= $whatsappEnabled ? 'checked' : ''; ?>>
            Exibir botão flutuante
          </label>
          <div>
            <label class="block text-sm font-medium mb-1">Número com DDI e DDD</label>
            <input class="input w-full" name="whatsapp_number" value="<?= sanitize_html($whatsappNumber); ?>" placeholder="ex.: 1789101122" maxlength="30">
            <p class="hint mt-1">Informe apenas números (ex.: 1789101122 para +1 789 101 122).</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Texto do botão</label>
            <input class="input w-full" name="whatsapp_button_text" value="<?= sanitize_html($whatsappButtonText); ?>" maxlength="80" placeholder="Fale com nossa equipe">
          </div>
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Mensagem inicial enviada no WhatsApp</label>
            <textarea class="textarea w-full" name="whatsapp_message" rows="3" maxlength="400"><?= sanitize_html($whatsappMessage); ?></textarea>
            <p class="hint mt-1">Será preenchida automaticamente quando o cliente abrir a conversa.</p>
          </div>
        </div>
      </div>

      <div class="field-span-2 settings-panel">
        <h3 class="text-md font-semibold mb-2 flex items-center gap-2"><i class="fa-solid fa-mobile-screen-button text-brand-600"></i> Identidade do App/PWA</h3>
        <p class="text-xs text-gray-500 mb-3">Personalize o título da aba, o nome exibido quando instalado e o ícone utilizado pelo aplicativo.</p>
        <div class="field-grid two">
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Título da aba (meta title)</label>
            <input class="input w-full" name="store_meta_title" maxlength="160" value="<?= sanitize_html($metaTitleCurrent); ?>">
            <p class="hint mt-1">Aparece em <code>&lt;title&gt;</code> e no histórico do navegador. Ex.: "<?= sanitize_html($storeNameCurrent ?: 'Get Power Research'); ?> | Loja".</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Nome do app (PWA)</label>
            <input class="input w-full" name="pwa_name" maxlength="80" value="<?= sanitize_html($pwaNameCurrent); ?>" required>
            <p class="hint mt-1">Nome completo exibido ao instalar o app.</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Nome curto</label>
            <input class="input w-full" name="pwa_short_name" maxlength="40" value="<?= sanitize_html($pwaShortNameCurrent); ?>" required>
            <p class="hint mt-1">Usado em ícones e notificações. Máximo recomendado: 12 caracteres.</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Ícone do app (PNG fundo transparente)</label>
            <?php if ($pwaIconPreview): ?>
              <div class="flex items-center gap-4 mb-2">
                <img src="<?= sanitize_html($pwaIconPreview); ?>" alt="Ícone atual" class="h-16 w-16 rounded-lg border bg-white p-2">
                <span class="text-xs text-gray-500 leading-snug">Tamanhos gerados automaticamente (512x512, 192x192 e 180x180).</span>
              </div>
            <?php endif; ?>
            <input class="block w-full text-sm text-gray-600" type="file" name="pwa_icon" accept=".png">
            <p class="hint mt-1">Envie uma imagem quadrada, preferencialmente 512x512 px, em formato PNG.</p>
          </div>
        </div>

        <hr class="border-gray-200">
        <div>
          <h4 class="text-sm font-semibold mb-1">Popup de instalação (Add to Home Screen)</h4>
          <p class="text-xs text-gray-500">Personalize o modal exibido aos usuários ao convidá-los para instalar o app.</p>
        </div>
        <div class="field-grid two">
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Título do popup</label>
            <input class="input w-full" name="a2hs_title" maxlength="160" value="<?= sanitize_html($a2hsTitleCurrent); ?>" placeholder="Instalar App <?= sanitize_html($storeNameCurrent ?: ''); ?>">
          </div>
          <div class="field-span-2">
            <label class="block text-sm font-medium mb-1">Descrição</label>
            <textarea class="textarea w-full" name="a2hs_subtitle" rows="2" maxlength="240" placeholder="Use nossa experiência completa no seu dispositivo."><?= sanitize_html($a2hsSubtitleCurrent); ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Texto do botão</label>
            <input class="input w-full" name="a2hs_button_label" maxlength="80" value="<?= sanitize_html($a2hsButtonCurrent); ?>" placeholder="Instalar App">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Ícone do popup (PNG/JPG/WEBP · máx 1MB)</label>
            <?php if ($a2hsIconPreview): ?>
              <div class="flex items-center gap-3 mb-2">
                <img src="<?= htmlspecialchars($a2hsIconPreview, ENT_QUOTES, 'UTF-8'); ?>" alt="Ícone A2HS" class="h-12 w-12 rounded-lg border bg-white p-1">
                <label class="inline-flex items-center gap-2 text-xs font-medium">
                  <input type="checkbox" name="a2hs_icon_remove" value="1">
                  Remover ícone atual
                </label>
              </div>
            <?php endif; ?>
            <input class="block w-full text-sm text-gray-600" type="file" name="a2hs_icon" accept=".png,.jpg,.jpeg,.webp">
          </div>
        </div>
      </div>

      </fieldset>
      <div class="flex justify-end gap-3">
        <?php if ($canEditSettings): ?>
          <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar alterações</button>
        <?php endif; ?>
        <a href="index.php" target="_blank" class="btn btn-ghost px-5 py-2"><i class="fa-solid fa-up-right-from-square mr-2"></i>Ver loja</a>
      </div>
    </form>
  </div>
  </div>

  <?php if ($isSuperAdmin): ?>
  <?php
    $themeOptionsList = store_theme_options();
    $storeThemeCurrent = active_store_theme();
    $themeFoodConfig = theme_food_config();
    $themeFoodHeroStats = $themeFoodConfig['hero_stats'] ?? [];
    $themeFoodValueCards = $themeFoodConfig['values_items'] ?? [];
    $themeFoodHeroBackground = $themeFoodConfig['hero_background_image'] ?? '';
    $themeFoodHighlightImage = $themeFoodConfig['highlight_image'] ?? '';
  ?>
  <div data-tab-panel="theme" class="card <?= $tab === 'theme' ? '' : 'hidden'; ?>">
    <div class="card-body settings-form space-y-6">
      <div>
        <h2 class="text-lg font-semibold mb-1">Temas da vitrine</h2>
        <p class="text-sm text-gray-500">Escolha o tema ativo da loja e personalize o layout alimentação com textos, imagens e destaques editáveis.</p>
      </div>
      <?php if ($tab === 'theme' && isset($_GET['saved'])): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          <span>Tema atualizado com sucesso.</span>
        </div>
      <?php endif; ?>
      <?php if ($themeError): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span><?= sanitize_html($themeError); ?></span>
        </div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" action="settings.php?tab=theme&action=save_theme" class="space-y-6">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <fieldset class="space-y-6">
          <div class="field-grid two">
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Tema ativo</label>
              <select class="select w-full" name="store_theme">
                <?php foreach ($themeOptionsList as $themeKey => $themeLabel): ?>
                  <option value="<?= sanitize_html($themeKey); ?>" <?= $storeThemeCurrent === $themeKey ? 'selected' : ''; ?>><?= sanitize_html($themeLabel); ?></option>
                <?php endforeach; ?>
              </select>
              <p class="hint mt-1">Apenas super administradores podem alterar o tema publicado.</p>
            </div>
          </div>

          <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4 settings-panel">
            <div class="flex flex-col gap-1">
              <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-font text-brand-600"></i> Fonte das categorias</h3>
              <p class="text-xs text-gray-500">Escolha a família tipográfica utilizada nos títulos das categorias exibidos na vitrine. Essa opção afeta tanto a home quanto seções agrupadas por categoria.</p>
            </div>
            <div class="field-grid two">
              <div>
                <label class="block text-sm font-medium mb-1">Fonte padrão</label>
                <select class="select w-full" name="store_category_font_choice">
                  <?php foreach ($categoryFontOptions as $fontKey => $fontData): ?>
                    <option value="<?= sanitize_html($fontKey); ?>" <?= $categoryFontChoiceCurrent === $fontKey ? 'selected' : ''; ?>>
                      <?= sanitize_html($fontData['label']); ?>
                    </option>
                  <?php endforeach; ?>
                  <option value="custom" <?= $categoryFontChoiceCurrent === 'custom' ? 'selected' : ''; ?>>Personalizada (CSS)</option>
                </select>
                <p class="hint mt-1">Selecione uma das opções sugeridas ou use "Personalizada" para inserir um <code>font-family</code> manualmente.</p>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Fonte personalizada (font-family)</label>
                <input class="input w-full" name="store_category_font_custom" maxlength="180" value="<?= sanitize_html($categoryFontCustomCurrent); ?>" placeholder="'Playfair Display', serif">
                <p class="hint mt-1">Aplicada somente quando a opção "Personalizada" estiver selecionada.</p>
              </div>
            </div>
          </div>

          <hr class="border-gray-200">

          <div>
            <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-carrot text-brand-600"></i> Tema alimentação</h3>
            <p class="text-xs text-gray-500 mt-1">Campos exibidos quando o tema alimentação está ativo. Todos os textos e imagens ficam editáveis.</p>
          </div>

          <div class="field-grid two">
            <div>
              <label class="block text-sm font-medium mb-1">Etiqueta superior</label>
              <input class="input w-full" name="theme_food_hero_badge" maxlength="120" value="<?= sanitize_html($themeFoodConfig['hero_badge']); ?>" placeholder="Ex.: Direto da fazenda">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Título principal</label>
              <input class="input w-full" name="theme_food_hero_title" maxlength="160" value="<?= sanitize_html($themeFoodConfig['hero_title']); ?>" placeholder="Rancho Nossa Terra">
            </div>
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Subtítulo</label>
              <textarea class="textarea w-full" name="theme_food_hero_subtitle" rows="2" maxlength="360"><?= sanitize_html($themeFoodConfig['hero_subtitle']); ?></textarea>
            </div>
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Descrição da hero</label>
              <textarea class="textarea w-full" name="theme_food_hero_description" rows="3" maxlength="400"><?= sanitize_html($themeFoodConfig['hero_description'] ?? ''); ?></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Texto do botão principal</label>
              <input class="input w-full" name="theme_food_hero_button_label" maxlength="120" value="<?= sanitize_html($themeFoodConfig['hero_button_label']); ?>" placeholder="Ver produtos">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Link do botão principal</label>
              <input class="input w-full" name="theme_food_hero_button_link" maxlength="255" value="<?= sanitize_html($themeFoodConfig['hero_button_link']); ?>" placeholder="#produtos ou URL completa">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Texto do botão secundário</label>
              <input class="input w-full" name="theme_food_hero_button_secondary_label" maxlength="120" value="<?= sanitize_html($themeFoodConfig['hero_button_secondary_label'] ?? ''); ?>" placeholder="Fale Conosco">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Link do botão secundário</label>
              <input class="input w-full" name="theme_food_hero_button_secondary_link" maxlength="255" value="<?= sanitize_html($themeFoodConfig['hero_button_secondary_link'] ?? '#contato'); ?>" placeholder="#contato ou URL completa">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Fundo da hero</label>
              <select class="select w-full" name="theme_food_hero_background_mode">
                <option value="image" <?= $themeFoodConfig['hero_background_mode'] === 'image' ? 'selected' : ''; ?>>Imagem com overlay</option>
                <option value="solid" <?= $themeFoodConfig['hero_background_mode'] === 'solid' ? 'selected' : ''; ?>>Cor sólida</option>
              </select>
              <p class="hint mt-1">Quando escolher cor sólida, a imagem será ignorada.</p>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Cor sólida da hero</label>
              <input class="input w-full" type="color" name="theme_food_hero_background_color" value="<?= sanitize_html($themeFoodConfig['hero_background_color']); ?>">
            </div>
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Imagem de fundo (JPG/PNG/WEBP · máx 3MB)</label>
              <?php if ($themeFoodHeroBackground): ?>
                <div class="mb-3">
                  <img src="<?= sanitize_html($themeFoodHeroBackground); ?>" alt="Atual fundo da hero" class="h-36 w-full object-cover rounded-xl border border-gray-200">
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-medium mb-2">
                  <input type="checkbox" name="theme_food_hero_background_remove" value="1">
                  Remover imagem atual
                </label>
              <?php else: ?>
                <p class="hint mb-2">Recomende dimensões 1920x720 para melhor resultado.</p>
              <?php endif; ?>
              <input class="block w-full text-sm text-gray-600" type="file" name="theme_food_hero_background" accept=".jpg,.jpeg,.png,.webp">
            </div>
          </div>

          <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4 settings-panel">
            <div>
              <h4 class="text-sm font-semibold">Destaques da hero</h4>
              <p class="hint mt-1">Use ícones da Font Awesome (ex.: fa-leaf, fa-wheat-awn). Os três cards aparecem ao lado da hero.</p>
            </div>
            <div class="field-grid three">
              <?php foreach ($themeFoodHeroStats as $idx => $stat): ?>
              <div class="space-y-3 border border-gray-200 rounded-lg p-3 bg-white/60">
                <div class="text-xs uppercase text-gray-500 font-semibold tracking-wide">Card <?= $idx + 1; ?></div>
                <div>
                  <label class="block text-xs font-medium mb-1">Ícone (Font Awesome)</label>
                  <input class="input w-full" name="theme_food_hero_stat_icon_<?= $idx; ?>" value="<?= sanitize_html($stat['icon']); ?>" maxlength="60" placeholder="fa-seedling">
                </div>
                <div>
                  <label class="block text-xs font-medium mb-1">Título</label>
                  <input class="input w-full" name="theme_food_hero_stat_title_<?= $idx; ?>" value="<?= sanitize_html($stat['title']); ?>" maxlength="80">
                </div>
                <div>
                  <label class="block text-xs font-medium mb-1">Descrição</label>
                  <textarea class="textarea w-full text-sm" name="theme_food_hero_stat_description_<?= $idx; ?>" rows="2" maxlength="240"><?= sanitize_html($stat['description']); ?></textarea>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4 settings-panel">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
              <div>
                <h4 class="text-sm font-semibold flex items-center gap-2"><i class="fa-solid fa-book-open text-brand-600"></i> Nossa História</h4>
                <p class="hint mt-1">Conte a trajetória da marca, pilares e principais números do rancho.</p>
              </div>
              <div class="text-right">
                <?php $historyImage = $themeFoodConfig['history_image'] ?? ''; ?>
                <?php if ($historyImage): ?>
                  <div class="mb-2">
                    <img src="<?= sanitize_html($historyImage); ?>" class="h-28 rounded-xl border object-cover" alt="Imagem história atual">
                  </div>
                  <label class="inline-flex items-center gap-2 text-xs font-medium mb-2">
                    <input type="checkbox" name="theme_food_history_image_remove" value="1">
                    Remover imagem
                  </label>
                <?php endif; ?>
                <input class="block w-full text-sm text-gray-600" type="file" name="theme_food_history_image" accept=".jpg,.jpeg,.png,.webp">
              </div>
            </div>
            <div class="field-grid two">
              <div>
                <label class="block text-sm font-medium mb-1">Título da seção</label>
                <input class="input w-full" name="theme_food_history_heading" maxlength="160" value="<?= sanitize_html($themeFoodConfig['history_heading'] ?? ''); ?>" placeholder="Nossa História">
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Subtítulo</label>
                <input class="input w-full" name="theme_food_history_subheading" maxlength="200" value="<?= sanitize_html($themeFoodConfig['history_subheading'] ?? ''); ?>" placeholder="Da fazenda para a sua mesa">
              </div>
              <div class="field-span-2">
                <label class="block text-sm font-medium mb-1">Descrição</label>
                <textarea class="textarea w-full" name="theme_food_history_description" rows="3" maxlength="600"><?= sanitize_html($themeFoodConfig['history_description'] ?? ''); ?></textarea>
              </div>
            </div>
            <div class="field-grid two xl-four">
              <?php $historyCards = $themeFoodConfig['history_cards'] ?? []; ?>
              <?php foreach ($historyCards as $idx => $card): ?>
                <div class="space-y-3 border border-gray-200 rounded-lg p-3 bg-white/60">
                  <div class="text-xs uppercase text-gray-500 font-semibold tracking-wide">Pilar <?= $idx + 1; ?></div>
                  <div>
                    <label class="block text-xs font-medium mb-1">Ícone</label>
                    <input class="input w-full" name="theme_food_history_card_icon_<?= $idx; ?>" value="<?= sanitize_html($card['icon'] ?? ''); ?>" maxlength="60" placeholder="fa-home-heart">
                  </div>
                  <div>
                    <label class="block text-xs font-medium mb-1">Título</label>
                    <input class="input w-full" name="theme_food_history_card_title_<?= $idx; ?>" value="<?= sanitize_html($card['title'] ?? ''); ?>" maxlength="120">
                  </div>
                  <div>
                    <label class="block text-xs font-medium mb-1">Descrição</label>
                    <textarea class="textarea w-full text-sm" name="theme_food_history_card_description_<?= $idx; ?>" rows="2" maxlength="360"><?= sanitize_html($card['description'] ?? ''); ?></textarea>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php $historyStats = $themeFoodConfig['history_stats'] ?? []; ?>
            <div class="field-grid three xl-six">
              <?php foreach ($historyStats as $idx => $stat): ?>
                <div class="space-y-2 border border-gray-200 rounded-lg p-3 bg-white/60">
                  <div class="text-xs uppercase text-gray-500 font-semibold tracking-wide">Indicador <?= $idx + 1; ?></div>
                  <label class="flex items-center gap-2 text-xs font-semibold text-brand-700">
                    <input type="checkbox" name="theme_food_history_stat_enabled_<?= $idx; ?>" value="1" <?= !empty($stat['enabled']) ? 'checked' : ''; ?>>
                    Exibir indicador
                  </label>
                  <div>
                    <label class="block text-xs font-medium mb-1">Valor</label>
                    <input class="input w-full" name="theme_food_history_stat_value_<?= $idx; ?>" value="<?= sanitize_html($stat['value'] ?? ''); ?>" maxlength="40">
                  </div>
                  <div>
                    <label class="block text-xs font-medium mb-1">Legenda</label>
                    <input class="input w-full" name="theme_food_history_stat_label_<?= $idx; ?>" value="<?= sanitize_html($stat['label'] ?? ''); ?>" maxlength="160">
                  </div>
                  <div>
                    <label class="block text-xs font-medium mb-1">Cor de destaque</label>
                    <input class="input w-full" type="color" name="theme_food_history_stat_color_<?= $idx; ?>" value="<?= sanitize_html($stat['color'] ?? '#16A34A'); ?>">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

  <div class="field-grid two">
    <div>
      <label class="block text-sm font-medium mb-1">Título da seção de produtos</label>
      <input class="input w-full" name="theme_food_products_heading" maxlength="120" value="<?= sanitize_html($themeFoodConfig['products_heading']); ?>" placeholder="Nossos Produtos">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Subtítulo de produtos</label>
      <input class="input w-full" name="theme_food_products_subheading" maxlength="240" value="<?= sanitize_html($themeFoodConfig['products_subheading']); ?>" placeholder="Filtre por categoria e monte sua cesta saudável.">
    </div>
  </div>

  <div class="field-grid two align-end">
    <div>
      <label class="flex items-center gap-2 text-sm font-medium">
        <input type="checkbox" name="theme_food_products_group_by_category" value="1" <?= !empty($themeFoodConfig['products_group_by_category']) ? 'checked' : ''; ?>>
        Agrupar produtos por categoria (exibe divisórias)
      </label>
      <p class="hint mt-1">Quando ativo, cada categoria recebe um título com a mesma fonte do nome da loja.</p>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Rótulo para itens sem categoria</label>
      <input class="input w-full" name="theme_food_products_uncategorized_label" maxlength="160" value="<?= sanitize_html($themeFoodConfig['products_uncategorized_label'] ?? 'Outros sabores'); ?>" placeholder="Outros sabores">
    </div>
  </div>

          <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4 settings-panel">
            <div>
              <h4 class="text-sm font-semibold">Nossos valores</h4>
              <p class="hint mt-1">Quatro cartões com ícone, título e descrição.</p>
            </div>
            <div class="field-grid two xl-four">
              <?php foreach ($themeFoodValueCards as $idx => $card): ?>
              <div class="space-y-3 border border-gray-200 rounded-lg p-3 bg-white/60">
                <div class="text-xs uppercase text-gray-500 font-semibold tracking-wide">Valor <?= $idx + 1; ?></div>
                <div>
                  <label class="block text-xs font-medium mb-1">Ícone</label>
                  <input class="input w-full" name="theme_food_value_icon_<?= $idx; ?>" value="<?= sanitize_html($card['icon']); ?>" maxlength="60" placeholder="fa-leaf">
                </div>
                <div>
                  <label class="block text-xs font-medium mb-1">Título</label>
                  <input class="input w-full" name="theme_food_value_title_<?= $idx; ?>" value="<?= sanitize_html($card['title']); ?>" maxlength="80">
                </div>
                <div>
                  <label class="block text-xs font-medium mb-1">Descrição</label>
                  <textarea class="textarea w-full text-sm" name="theme_food_value_description_<?= $idx; ?>" rows="2" maxlength="240"><?= sanitize_html($card['description']); ?></textarea>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Título da seção</label>
              <input class="input w-full" name="theme_food_values_heading" maxlength="120" value="<?= sanitize_html($themeFoodConfig['values_heading']); ?>" placeholder="Nossos Valores">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Subtítulo</label>
              <input class="input w-full" name="theme_food_values_subheading" maxlength="240" value="<?= sanitize_html($themeFoodConfig['values_subheading']); ?>" placeholder="Transparência, sabor e cuidado em cada etapa.">
            </div>
          </div>

          <div class="field-grid two">
            <div>
              <label class="block text-sm font-medium mb-1">Título do bloco destaque</label>
              <input class="input w-full" name="theme_food_highlight_heading" maxlength="120" value="<?= sanitize_html($themeFoodConfig['highlight_heading']); ?>" placeholder="Sabores da Fazenda">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Subtítulo do destaque</label>
              <input class="input w-full" name="theme_food_highlight_subheading" maxlength="200" value="<?= sanitize_html($themeFoodConfig['highlight_subheading']); ?>" placeholder="Descubra experiências gastronômicas completas.">
            </div>
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Texto de apoio</label>
              <textarea class="textarea w-full" name="theme_food_highlight_text" rows="3" maxlength="600"><?= sanitize_html($themeFoodConfig['highlight_text']); ?></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Texto do botão</label>
              <input class="input w-full" name="theme_food_highlight_button_label" maxlength="120" value="<?= sanitize_html($themeFoodConfig['highlight_button_label']); ?>" placeholder="Ver catálogo completo">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Link do botão</label>
              <input class="input w-full" name="theme_food_highlight_button_link" maxlength="255" value="<?= sanitize_html($themeFoodConfig['highlight_button_link']); ?>" placeholder="#produtos ou URL">
            </div>
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Imagem lateral do destaque (JPG/PNG/WEBP · máx 3MB)</label>
              <?php if ($themeFoodHighlightImage): ?>
                <div class="mb-3">
                  <img src="<?= sanitize_html($themeFoodHighlightImage); ?>" alt="Imagem destaque atual" class="h-40 w-full object-cover rounded-xl border border-gray-200">
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-medium mb-2">
                  <input type="checkbox" name="theme_food_highlight_image_remove" value="1">
                  Remover imagem atual
                </label>
              <?php else: ?>
                <p class="hint mb-2">Sugestão: fotos de embalagens, prateleira ou preparo.</p>
              <?php endif; ?>
              <input class="block w-full text-sm text-gray-600" type="file" name="theme_food_highlight_image" accept=".jpg,.jpeg,.png,.webp">
            </div>
          </div>

          <div class="field-grid two">
            <div>
              <label class="block text-sm font-medium mb-1">Título da seção de contato</label>
              <input class="input w-full" name="theme_food_contact_heading" maxlength="120" value="<?= sanitize_html($themeFoodConfig['contact_heading']); ?>" placeholder="Fale com a gente">
            </div>
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Texto informativo</label>
              <textarea class="textarea w-full" name="theme_food_contact_text" rows="2" maxlength="360"><?= sanitize_html($themeFoodConfig['contact_text']); ?></textarea>
              <p class="hint mt-1">Será exibido acima dos contatos padrão configurados na aba “Dados da loja”.</p>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Texto do botão do formulário</label>
              <input class="input w-full" name="theme_food_contact_form_button_label" maxlength="120" value="<?= sanitize_html($themeFoodConfig['contact_form_button_label'] ?? 'Enviar Mensagem'); ?>" placeholder="Enviar Mensagem">
            </div>
          </div>

        <div class="flex justify-end">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar tema</button>
        </div>
      </fieldset>
      </form>
    </div>
  </div>

  <div data-tab-panel="costs" class="card <?= $tab === 'costs' ? '' : 'hidden'; ?>">
    <div class="card-body settings-form space-y-6">
      <div>
        <h2 class="text-lg font-semibold mb-1">Gestão de custos e margens</h2>
        <p class="text-sm text-gray-500">Ative o controle de custos unitários e lucros estimados por produto. Os valores são utilizados nos relatórios e no painel financeiro.</p>
      </div>
      <?php if ($tab === 'costs' && isset($_GET['saved'])): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          <span>Configurações atualizadas com sucesso.</span>
        </div>
      <?php elseif ($costsSuccess): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          <span><?= sanitize_html($costsSuccess); ?></span>
        </div>
      <?php endif; ?>
      <form method="post" action="settings.php?tab=costs&action=save_costs">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4">
          <label class="flex items-start gap-3">
            <input type="checkbox" name="cost_management_enabled" value="1" <?= $costManagementEnabledSetting ? 'checked' : ''; ?>>
            <span>
              <div class="text-sm font-semibold">Ativar gestão de custos</div>
              <p class="text-xs text-gray-500 mt-1">Quando habilitado, cada produto passa a ter campos de custo unitário e lucro estimado. Os pedidos novos registram essas informações automaticamente e o painel financeiro exibe indicadores de margem.</p>
            </span>
          </label>
          <p class="text-xs text-gray-500">Desativar a função mantém os dados existentes, porém oculta os campos no cadastro de produtos e remove os indicadores extras dos relatórios.</p>
        </div>
        <div class="flex justify-end">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar configurações</button>
        </div>
      </form>
    </div>
  </div>

  <div data-tab-panel="experience" class="card <?= $tab === 'experience' ? '' : 'hidden'; ?>">
    <div class="card-body settings-form space-y-6">
      <div>
        <h2 class="text-lg font-semibold mb-1">Experiência do app</h2>
        <p class="text-sm text-gray-500">Gerencie o banner de instalação PWA e escolha quais seções aparecem na home.</p>
      </div>
      <?php if ($tab === 'experience' && isset($_GET['saved'])): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          <span>Configurações de experiência atualizadas com sucesso.</span>
        </div>
      <?php endif; ?>
      <form method="post" class="space-y-6" action="settings.php?tab=experience&action=save_experience">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <fieldset class="space-y-6">
        <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4 settings-panel">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
              <div>
                <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-mobile-screen-button text-brand-600"></i> Banner de instalação PWA</h3>
                <p class="text-xs text-gray-500 mt-1">Configure cores, texto e frequência com que o banner é exibido para os visitantes.</p>
              </div>
              <label class="inline-flex items-center gap-2 font-medium text-sm">
                <input type="checkbox" name="pwa_banner_enabled" value="1" <?= !empty($pwaBannerConfig['enabled']) ? 'checked' : ''; ?>>
                Exibir banner PWA
              </label>
            </div>
            <div class="field-grid three">
              <div>
                <label class="block text-sm font-medium mb-1">Atraso para aparecer (ms)</label>
                <input class="input w-full" type="number" min="0" name="pwa_banner_show_delay" value="<?= (int)$pwaBannerConfig['show_delay_ms']; ?>">
                <p class="hint mt-1">Ex.: 2500 para exibir após 2,5 segundos.</p>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Duração visível (ms)</label>
                <input class="input w-full" type="number" min="0" name="pwa_banner_display_duration" value="<?= (int)$pwaBannerConfig['display_duration_ms']; ?>">
                <p class="hint mt-1">Use 0 para manter visível até o usuário fechar.</p>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Intervalo entre exibições (horas)</label>
                <input class="input w-full" type="number" min="0" name="pwa_banner_cooldown_hours" value="<?= (int)$pwaBannerConfig['cooldown_hours']; ?>">
                <p class="hint mt-1">Define após quantas horas o banner pode reaparecer.</p>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Posição na tela</label>
                <select class="select w-full" name="pwa_banner_position">
                  <option value="center" <?= $pwaBannerConfig['position'] === 'center' ? 'selected' : ''; ?>>Centralizado</option>
                  <option value="left" <?= $pwaBannerConfig['position'] === 'left' ? 'selected' : ''; ?>>Canto inferior esquerdo</option>
                  <option value="right" <?= $pwaBannerConfig['position'] === 'right' ? 'selected' : ''; ?>>Canto inferior direito</option>
                </select>
              </div>
              <div class="field-span-2">
                <label class="block text-sm font-medium mb-1">Mensagem exibida</label>
                <textarea class="textarea w-full" name="pwa_banner_message" rows="2" maxlength="280"><?= htmlspecialchars($pwaBannerConfig['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Texto do botão</label>
                <input class="input w-full" name="pwa_banner_button_label" maxlength="80" value="<?= sanitize_html($pwaBannerConfig['button_label']); ?>">
              </div>
            </div>
            <div class="field-grid five">
              <div>
                <label class="block text-sm font-medium mb-1">Cor de fundo</label>
                <input class="input w-full" type="color" name="pwa_banner_background_color" value="<?= sanitize_html($pwaBannerConfig['background_color']); ?>">
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Cor do texto</label>
                <input class="input w-full" type="color" name="pwa_banner_text_color" value="<?= sanitize_html($pwaBannerConfig['text_color']); ?>">
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Cor do botão</label>
                <input class="input w-full" type="color" name="pwa_banner_button_background" value="<?= sanitize_html($pwaBannerConfig['button_background']); ?>">
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Cor do texto do botão</label>
                <input class="input w-full" type="color" name="pwa_banner_button_text_color" value="<?= sanitize_html($pwaBannerConfig['button_text_color']); ?>">
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Cor da borda</label>
                <input class="input w-full" type="color" name="pwa_banner_border_color" value="<?= sanitize_html($pwaBannerConfig['border_color']); ?>">
              </div>
            </div>
          </div>

        <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4 settings-panel">
            <div>
              <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-table-list text-brand-600"></i> Seções da página inicial</h3>
              <p class="text-xs text-gray-500 mt-1">Desmarque os blocos que não devem ser exibidos na home. Os itens se aplicam ao tema atual e ao tema alimentação.</p>
            </div>
            <?php
              $homeSectionsLabels = [
                'hero' => 'Hero (destaque principal)',
                'categories' => 'Filtro de categorias / chips',
                'featured' => 'Vitrine de destaques',
                'products' => 'Lista principal de produtos',
                'values' => 'Seção “Nossos valores” (tema alimentação)',
                'highlight' => 'Bloco de destaque gastronômico (tema alimentação)',
                'contact' => 'Seção de contato (tema alimentação)',
              ];
            ?>
            <div class="field-grid two gap-tight">
              <?php foreach ($homeSectionsLabels as $sectionKey => $label): ?>
                <label class="inline-flex items-start gap-3 bg-gray-50 border border-gray-200 rounded-lg p-3">
                  <input type="checkbox" class="mt-1" name="home_sections[<?= $sectionKey; ?>]" value="1" <?= !empty($homeSectionsVisibility[$sectionKey]) ? 'checked' : ''; ?>>
                  <span class="text-sm"><?= sanitize_html($label); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </fieldset>
        <div class="flex justify-end">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar experiência</button>
        </div>
      </form>
    </div>
  </div>

  <div data-tab-panel="navigation" class="card <?= $tab === 'navigation' ? '' : 'hidden'; ?>">
    <div class="card-body settings-form space-y-6">
      <div>
        <h2 class="text-lg font-semibold mb-1">Menus & páginas adicionais</h2>
        <p class="text-sm text-gray-500">Defina os links do topo, rodapé e cadastre páginas institucionais exibidas na vitrine.</p>
      </div>
      <?php if ($tab === 'navigation' && isset($_GET['saved'])): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          <span>Menus e páginas atualizados com sucesso.</span>
        </div>
      <?php endif; ?>
      <?php if ($navigationError): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span><?= sanitize_html($navigationError); ?></span>
        </div>
      <?php endif; ?>
      <?php
        if ($headerLinksCurrent) {
          $headerLinksForForm = $headerLinksCurrent;
        } else {
          $headerLinksForForm = [
            ['label' => 'Início', 'url' => '?route=home#inicio', 'target' => '_self'],
            ['label' => 'Produtos', 'url' => '?route=home#produtos', 'target' => '_self'],
            ['label' => 'Sobre Nós', 'url' => '?route=home#historia', 'target' => '_self'],
            ['label' => 'Contato', 'url' => '?route=home#contato', 'target' => '_self'],
          ];
        }

        if ($footerLinksCurrent) {
          $footerLinksForForm = $footerLinksCurrent;
        } else {
          $footerLinksForForm = [
            ['label' => 'Rancho Nossa Terra', 'url' => '?route=home#historia', 'target' => '_self'],
            ['label' => 'Produtos', 'url' => '?route=home#produtos', 'target' => '_self'],
            ['label' => 'Contato', 'url' => '?route=home#contato', 'target' => '_self'],
            ['label' => 'Política de Privacidade', 'url' => '?route=privacy', 'target' => '_self'],
          ];
        }

        if ($customPagesCurrent) {
          $customPagesForForm = $customPagesCurrent;
        } else {
          $customPagesForForm = [[
            'title' => 'Sobre Nós',
            'slug' => 'sobre-nos',
            'content' => '<p><strong>Rancho Nossa Terra Nossa Gente</strong> nasceu do sonho de levar os sabores artesanais do Brasil para todo o território americano. Trabalhamos diretamente com famílias produtoras, preservando receitas tradicionais e ingredientes naturais.</p><p>Localizados em Indiantown, FL, atendemos todos os estados com logística ágil e cuidados especiais em cada etapa.</p>',
            'show_in_header' => true,
            'show_in_footer' => true,
            'order_header' => 10,
            'order_footer' => 10,
          ]];
        }
      ?>
      <form method="post" class="space-y-6" action="settings.php?tab=navigation&action=save_navigation">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <fieldset class="space-y-6">
        <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4 settings-panel">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-list-ul text-brand-600"></i> Menu do header</h3>
                <p class="text-xs text-gray-500 mt-1">Links exibidos ao lado do logotipo. Use URLs absolutas ou caminhos relativos (ex.: <code>?route=page&slug=sobre</code>).</p>
              </div>
              <button type="button" class="btn btn-ghost text-sm" data-action="add-row" data-target="header-links-list" data-template="tpl-header-link"><i class="fa-solid fa-plus mr-2"></i>Adicionar link</button>
            </div>
            <div id="header-links-list" class="space-y-3" data-next-index="<?= count($headerLinksForForm); ?>">
              <?php foreach ($headerLinksForForm as $idx => $link): ?>
                <div class="border border-gray-200 rounded-xl p-4 space-y-3 bg-gray-50" data-entry>
                  <div class="field-grid two gap-tight">
                    <div>
                      <label class="block text-sm font-medium mb-1">Rótulo</label>
                      <input class="input w-full" name="header_links[<?= $idx; ?>][label]" maxlength="160" value="<?= sanitize_html($link['label'] ?? ''); ?>" required>
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-1">URL</label>
                      <input class="input w-full" name="header_links[<?= $idx; ?>][url]" value="<?= sanitize_html($link['url'] ?? ''); ?>" required>
                      <p class="hint mt-1">Ex.: <code>?route=home</code>, <code>?route=page&slug=sobre</code> ou <code>https://...</code></p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-1">Destino</label>
                      <?php $target = ($link['target'] ?? '_self') === '_blank' ? '_blank' : '_self'; ?>
                      <select class="select w-full" name="header_links[<?= $idx; ?>][target]">
                        <option value="_self" <?= $target === '_self' ? 'selected' : ''; ?>>Mesma aba</option>
                        <option value="_blank" <?= $target === '_blank' ? 'selected' : ''; ?>>Nova aba</option>
                      </select>
                    </div>
                  </div>
                  <div class="text-right">
                    <button type="button" class="btn btn-ghost text-sm text-red-500" data-action="remove-entry"><i class="fa-solid fa-trash mr-1"></i>Remover</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4 settings-panel">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-link text-brand-600"></i> Links do rodapé</h3>
                <p class="text-xs text-gray-500 mt-1">Links exibidos na coluna “Links” do rodapé.</p>
              </div>
              <button type="button" class="btn btn-ghost text-sm" data-action="add-row" data-target="footer-links-list" data-template="tpl-footer-link"><i class="fa-solid fa-plus mr-2"></i>Adicionar link</button>
            </div>
            <div id="footer-links-list" class="space-y-3" data-next-index="<?= count($footerLinksForForm); ?>">
              <?php foreach ($footerLinksForForm as $idx => $link): ?>
                <div class="border border-gray-200 rounded-xl p-4 space-y-3 bg-gray-50" data-entry>
                  <div class="field-grid two gap-tight">
                    <div>
                      <label class="block text-sm font-medium mb-1">Rótulo</label>
                      <input class="input w-full" name="footer_links[<?= $idx; ?>][label]" maxlength="160" value="<?= sanitize_html($link['label'] ?? ''); ?>" required>
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-1">URL</label>
                      <input class="input w-full" name="footer_links[<?= $idx; ?>][url]" value="<?= sanitize_html($link['url'] ?? ''); ?>" required>
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-1">Destino</label>
                      <?php $target = ($link['target'] ?? '_self') === '_blank' ? '_blank' : '_self'; ?>
                      <select class="select w-full" name="footer_links[<?= $idx; ?>][target]">
                        <option value="_self" <?= $target === '_self' ? 'selected' : ''; ?>>Mesma aba</option>
                        <option value="_blank" <?= $target === '_blank' ? 'selected' : ''; ?>>Nova aba</option>
                      </select>
                    </div>
                  </div>
                  <div class="text-right">
                    <button type="button" class="btn btn-ghost text-sm text-red-500" data-action="remove-entry"><i class="fa-solid fa-trash mr-1"></i>Remover</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        <div class="border border-gray-200 rounded-xl p-4 bg-white space-y-4 settings-panel">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-file-lines text-brand-600"></i> Páginas institucionais</h3>
                <p class="text-xs text-gray-500 mt-1">Crie páginas simples com conteúdo HTML básico. Elas ficam disponíveis em <code>?route=page&amp;slug=seu-slug</code>.</p>
              </div>
              <button type="button" class="btn btn-ghost text-sm" data-action="add-row" data-target="custom-pages-list" data-template="tpl-custom-page"><i class="fa-solid fa-plus mr-2"></i>Adicionar página</button>
            </div>
            <div id="custom-pages-list" class="space-y-4" data-next-index="<?= count($customPagesForForm); ?>">
              <?php foreach ($customPagesForForm as $idx => $page): ?>
                <div class="border border-gray-200 rounded-xl p-4 space-y-4 bg-gray-50" data-entry>
                  <div class="field-grid two gap-tight">
                    <div>
                      <label class="block text-sm font-medium mb-1">Título da página</label>
                      <input class="input w-full" name="pages[<?= $idx; ?>][title]" maxlength="160" value="<?= sanitize_html($page['title'] ?? ''); ?>">
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-1">Slug</label>
                      <input class="input w-full" name="pages[<?= $idx; ?>][slug]" maxlength="120" value="<?= sanitize_html($page['slug'] ?? ''); ?>" placeholder="ex.: sobre-nos">
                      <p class="hint mt-1">Utilize apenas letras, números e hífens.</p>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Conteúdo</label>
                    <textarea class="textarea w-full font-mono text-sm" name="pages[<?= $idx; ?>][content]" rows="5"><?= htmlspecialchars($page['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <p class="hint mt-1">Aceita HTML básico (&lt;p&gt;, &lt;ul&gt;, &lt;a&gt;, &lt;strong&gt;...).</p>
                  </div>
                  <div class="flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2">
                      <input type="checkbox" name="pages[<?= $idx; ?>][show_in_header]" value="1" <?= !empty($page['show_in_header']) ? 'checked' : ''; ?>>
                      Mostrar no header
                    </label>
                    <label class="inline-flex items-center gap-2">
                      <input type="checkbox" name="pages[<?= $idx; ?>][show_in_footer]" value="1" <?= !empty($page['show_in_footer']) ? 'checked' : ''; ?>>
                      Mostrar no rodapé
                    </label>
                  </div>
                  <div class="flex items-center justify-between text-xs text-gray-500">
                    <?php if (!empty($page['slug'])): ?>
                      <span>Link: <code>?route=page&amp;slug=<?= sanitize_html($page['slug']); ?></code></span>
                    <?php else: ?>
                      <span>O link será gerado após informar o slug e salvar.</span>
                    <?php endif; ?>
                    <button type="button" class="btn btn-ghost text-sm text-red-500" data-action="remove-entry"><i class="fa-solid fa-trash mr-1"></i>Remover</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </fieldset>
        <div class="flex justify-end">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar menus e páginas</button>
        </div>
      </form>

      <template id="tpl-header-link">
        <div class="border border-gray-200 rounded-xl p-4 space-y-3 bg-gray-50" data-entry>
          <div class="field-grid two gap-tight">
            <div>
              <label class="block text-sm font-medium mb-1">Rótulo</label>
              <input class="input w-full" name="header_links[__INDEX__][label]" maxlength="160" value="" required>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">URL</label>
              <input class="input w-full" name="header_links[__INDEX__][url]" value="" required>
              <p class="hint mt-1">Ex.: <code>?route=home</code> ou <code>?route=page&amp;slug=sobre</code></p>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Destino</label>
              <select class="select w-full" name="header_links[__INDEX__][target]">
                <option value="_self">Mesma aba</option>
                <option value="_blank">Nova aba</option>
              </select>
            </div>
          </div>
          <div class="text-right">
            <button type="button" class="btn btn-ghost text-sm text-red-500" data-action="remove-entry"><i class="fa-solid fa-trash mr-1"></i>Remover</button>
          </div>
        </div>
      </template>

      <template id="tpl-footer-link">
        <div class="border border-gray-200 rounded-xl p-4 space-y-3 bg-gray-50" data-entry>
          <div class="field-grid two gap-tight">
            <div>
              <label class="block text-sm font-medium mb-1">Rótulo</label>
              <input class="input w-full" name="footer_links[__INDEX__][label]" maxlength="160" value="" required>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">URL</label>
              <input class="input w-full" name="footer_links[__INDEX__][url]" value="" required>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Destino</label>
              <select class="select w-full" name="footer_links[__INDEX__][target]">
                <option value="_self">Mesma aba</option>
                <option value="_blank">Nova aba</option>
              </select>
            </div>
          </div>
          <div class="text-right">
            <button type="button" class="btn btn-ghost text-sm text-red-500" data-action="remove-entry"><i class="fa-solid fa-trash mr-1"></i>Remover</button>
          </div>
        </div>
      </template>

      <template id="tpl-custom-page">
        <div class="border border-gray-200 rounded-xl p-4 space-y-4 bg-gray-50" data-entry>
          <div class="field-grid two gap-tight">
            <div>
              <label class="block text-sm font-medium mb-1">Título da página</label>
              <input class="input w-full" name="pages[__INDEX__][title]" maxlength="160" value="">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Slug</label>
              <input class="input w-full" name="pages[__INDEX__][slug]" maxlength="120" value="" placeholder="ex.: politicas">
              <p class="hint mt-1">Use apenas letras, números e hífens.</p>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Conteúdo</label>
            <textarea class="textarea w-full font-mono text-sm" name="pages[__INDEX__][content]" rows="5"></textarea>
            <p class="hint mt-1">Aceita HTML básico (&lt;p&gt;, &lt;ul&gt;, &lt;a&gt;, &lt;strong&gt;...).</p>
          </div>
          <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" name="pages[__INDEX__][show_in_header]" value="1">
              Mostrar no header
            </label>
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" name="pages[__INDEX__][show_in_footer]" value="1">
              Mostrar no rodapé
            </label>
          </div>
          <div class="flex items-center justify-between text-xs text-gray-500">
            <span>Link será gerado após salvar.</span>
            <button type="button" class="btn btn-ghost text-sm text-red-500" data-action="remove-entry"><i class="fa-solid fa-trash mr-1"></i>Remover</button>
          </div>
        </div>
      </template>
      <script>
        (function () {
          function addRow(button) {
            const listId = button.getAttribute('data-target');
            const templateId = button.getAttribute('data-template');
            if (!listId || !templateId) return;
            const list = document.getElementById(listId);
            const template = document.getElementById(templateId);
            if (!list || !template) return;
            const currentIndex = parseInt(list.getAttribute('data-next-index') || list.querySelectorAll('[data-entry]').length, 10);
            const nextIndex = Number.isNaN(currentIndex) ? list.querySelectorAll('[data-entry]').length : currentIndex;
            const html = template.innerHTML.replace(/__INDEX__/g, nextIndex);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            const entry = wrapper.firstElementChild;
            if (!entry) return;
            list.appendChild(entry);
            list.setAttribute('data-next-index', nextIndex + 1);
          }

          document.querySelectorAll('[data-action="add-row"]').forEach((btn) => {
            btn.addEventListener('click', function () {
              addRow(this);
            });
          });

          document.addEventListener('click', function (event) {
            const removeBtn = event.target.closest('[data-action="remove-entry"]');
            if (!removeBtn) return;
            const entry = removeBtn.closest('[data-entry]');
            if (entry) {
              entry.remove();
            }
          });

          document.querySelectorAll('#custom-pages-list input[name^="pages"][name$="[slug]"]').forEach((input) => {
            if ((input.value || '').trim() !== '') {
              input.dataset.userEdited = 'true';
            }
          });

          document.addEventListener('input', function (event) {
            if (event.target.matches('#custom-pages-list input[name^="pages"][name$="[slug]"]')) {
              event.target.dataset.userEdited = 'true';
              return;
            }
            if (!event.target.matches('#custom-pages-list input[name^="pages"][name$="[title]"]')) {
              return;
            }
            const entry = event.target.closest('[data-entry]');
            if (!entry) return;
            const slugInput = entry.querySelector('input[name^="pages"][name$="[slug]"]');
            if (!slugInput || slugInput.dataset.userEdited === 'true') return;
            const base = event.target.value || '';
            const slug = base
              .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
              .toLowerCase()
              .replace(/[^a-z0-9]+/g, '-')
              .replace(/^-+|-+$/g, '')
              .substr(0, 120);
            slugInput.value = slug;
          });
        })();
      </script>
    </div>
  </div>
  <?php endif; ?>

  <div data-tab-panel="checkout" class="card <?= $tab === 'checkout' ? '' : 'hidden'; ?>">
    <div class="card-body settings-form">
      <h2 class="text-lg font-semibold mb-1">Configurações do Checkout</h2>
      <?php if ($tab === 'checkout' && isset($_GET['saved'])): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          <span>Checkout atualizado com sucesso.</span>
        </div>
      <?php endif; ?>
      <form method="post" action="settings.php?tab=checkout&action=save_checkout">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <?php if (!$canEditSettings): ?>
          <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Você não tem permissão para editar estas configurações.</span>
          </div>
        <?php endif; ?>
        <fieldset class="space-y-6" <?= $canEditSettings ? '' : 'disabled'; ?>>
          <div class="field-grid two gap-roomy">
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Países disponíveis</label>
              <textarea class="textarea w-full font-mono text-sm" name="checkout_countries" rows="4" placeholder="US|Estados Unidos" <?= $canEditSettings ? '' : 'readonly'; ?>><?= htmlspecialchars($checkoutCountriesText, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <p class="hint mt-1">Use o formato <code>CODIGO|Nome</code>. Ex.: <code>US|Estados Unidos</code>.</p>
            </div>
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Estados / Províncias</label>
              <textarea class="textarea w-full font-mono text-sm" name="checkout_states" rows="6" placeholder="US|AL|Alabama" <?= $canEditSettings ? '' : 'readonly'; ?>><?= htmlspecialchars($checkoutStatesText, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <p class="hint mt-1">Formato <code>PAIS|CODIGO|Nome</code>. Se um país não tiver estados listados, o checkout exibirá um campo de texto livre.</p>
            </div>
            <div class="field-span-2">
              <label class="block text-sm font-medium mb-1">Métodos de entrega</label>
              <textarea class="textarea w-full font-mono text-sm" name="checkout_delivery_methods" rows="5" placeholder="standard|Entrega padrão (5-7 dias)|Envio com rastreio para todo o país." <?= $canEditSettings ? '' : 'readonly'; ?>><?= htmlspecialchars($checkoutDeliveryText, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <p class="hint mt-1">Formato <code>codigo|Nome|Descrição</code>. Utilize códigos curtos, sem espaços.</p>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">País selecionado por padrão</label>
              <select class="select w-full" name="checkout_default_country" <?= $canEditSettings ? '' : 'disabled'; ?>>
                <?php foreach ($checkoutCountriesCurrent as $countryEntry): ?>
                  <?php $code = strtoupper($countryEntry['code'] ?? ''); ?>
                  <option value="<?= $code; ?>" <?= $code === $checkoutDefaultCountryCurrent ? 'selected' : ''; ?>><?= sanitize_html($countryEntry['name'] ?? $code); ?></option>
                <?php endforeach; ?>
              </select>
              <p class="hint mt-1">Aplicado quando o cliente abre o checkout pela primeira vez.</p>
            </div>
          </div>
          <?php if ($canEditSettings): ?>
            <div class="pt-4">
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar checkout</button>
            </div>
          <?php endif; ?>
        </fieldset>
      </form>
    </div>
  </div>

  <div data-tab-panel="social" class="card <?= $tab === 'social' ? '' : 'hidden'; ?>">
    <div class="card-body settings-form">
      <h2 class="text-lg font-semibold mb-1">Redes sociais</h2>
      <p class="text-xs text-gray-500 mb-4">Adicione os links oficiais para que sejam usados na vitrine e em materiais de contato.</p>
      <?php if ($tab === 'social' && isset($_GET['saved'])): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          <span>Links atualizados com sucesso.</span>
        </div>
      <?php endif; ?>
      <?php if ($socialError): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-circle-exclamation"></i>
          <span><?= sanitize_html($socialError); ?></span>
        </div>
      <?php endif; ?>
      <form method="post" class="space-y-4" action="settings.php?tab=social&action=save_social">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <div class="field-grid two">
          <div>
            <label class="block text-sm font-medium mb-1">Perfil do Instagram</label>
            <input class="input w-full" type="url" name="social_instagram_url" value="<?= sanitize_html($instagramCurrent); ?>" placeholder="https://instagram.com/sualoja">
            <p class="hint mt-1">Cole a URL completa do seu perfil. Deixe em branco para não exibir.</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Página do Facebook</label>
            <input class="input w-full" type="url" name="social_facebook_url" value="<?= sanitize_html($facebookCurrent); ?>" placeholder="https://facebook.com/sualoja">
            <p class="hint mt-1">URLs com http(s) são aceitas (ex.: https://facebook.com/seuPerfil).</p>
          </div>
        </div>
        <div class="flex gap-3 pt-2">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar redes</button>
          <a class="btn btn-ghost" href="settings.php?tab=general"><i class="fa-solid fa-arrow-left mr-2"></i>Voltar</a>
        </div>
      </form>
    </div>
  </div>

  <div data-tab-panel="payments" class="space-y-4 <?= $tab === 'payments' ? '' : 'hidden'; ?>">
    <div class="card p-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h2 class="text-lg font-semibold">Métodos de pagamento</h2>
          <p class="text-sm text-gray-500">Arraste para reordenar e clique para editar ou ativar/desativar.</p>
        </div>
        <?php if ($canManagePayments): ?>
          <a class="btn btn-primary" href="settings.php?tab=payments&action=new"><i class="fa-solid fa-plus mr-2"></i>Novo método</a>
        <?php endif; ?>
      </div>
      <?php if (!$methods): ?>
        <p class="text-center text-gray-500 mt-6">Nenhum método cadastrado.</p>
      <?php else: ?>
        <ul id="pm-sortable" class="divide-y divide-gray-200 mt-4" data-sortable-enabled="<?= $canManagePayments ? '1' : '0'; ?>">
          <?php foreach ($methods as $pm): $settings = pm_decode_settings($pm); ?>
            <li class="flex items-center justify-between gap-4 px-4 py-3 bg-white" data-id="<?= (int)$pm['id']; ?>">
              <div class="flex items-center gap-3">
                <span class="cursor-move text-gray-400"><i class="fa-solid fa-grip-lines"></i></span>
                <?php if (!empty($pm['icon_path'])): ?>
                  <img src="<?= sanitize_html($pm['icon_path']); ?>" class="h-8 w-8 rounded" alt="icon">
                <?php else: ?>
                  <div class="h-8 w-8 rounded flex items-center justify-center" style="background:rgba(32,96,200,.08);color:var(--brand-700);">
                    <i class="fa-solid fa-credit-card"></i>
                  </div>
                <?php endif; ?>
                <div>
                  <div class="font-semibold"><?= sanitize_html($pm['name']); ?></div>
                  <div class="text-xs text-gray-500">Código: <?= sanitize_html($pm['code']); ?></div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <?= ((int)$pm['is_active'] === 1) ? '<span class="badge ok">Ativo</span>' : '<span class="badge danger">Inativo</span>'; ?>
                <?= !empty($pm['require_receipt']) ? '<span class="badge warn">Comprovante</span>' : ''; ?>
              </div>
              <div class="flex items-center gap-2">
                <?php if ($canManagePayments): ?>
                  <a class="btn btn-ghost" href="settings.php?tab=payments&action=edit&id=<?= (int)$pm['id']; ?>" title="Editar"><i class="fa-solid fa-pen"></i></a>
                  <a class="btn btn-ghost" href="settings.php?tab=payments&action=toggle&id=<?= (int)$pm['id']; ?>&csrf=<?= csrf_token(); ?>" title="Ativar/Inativar"><i class="fa-solid fa-power-off"></i></a>
                <?php else: ?>
                  <span class="text-xs text-gray-400">Somente leitura</span>
                <?php endif; ?>
                <?php if ($isSuperAdmin): ?>
                  <a class="btn btn-ghost text-red-600" href="settings.php?tab=payments&action=delete&id=<?= (int)$pm['id']; ?>&csrf=<?= csrf_token(); ?>" onclick="return confirm('Remover este método?')" title="Excluir"><i class="fa-solid fa-trash"></i></a>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="card p-6">
      <h3 class="text-md font-semibold mb-3"><?= $editRow ? 'Editar método' : 'Novo método'; ?></h3>
      <?php
        $formRow = $editRow ?: [];
        $formSettings = $editSettings ?: ['type' => 'custom'];
        $idForForm = (int)($formRow['id'] ?? 0);
        $formAction = $editRow ? 'update&id='.$idForForm : 'create';
      ?>
      <form class="space-y-4" method="post" enctype="multipart/form-data" action="settings.php?tab=payments&action=<?= $formAction; ?>">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <?php if (!$canManagePayments): ?>
          <div class="alert alert-warning">
            <i class="fa-solid fa-circle-info"></i>
            <span>Você não possui permissão para alterar métodos de pagamento.</span>
          </div>
        <?php endif; ?>
        <fieldset class="space-y-4" <?= $canManagePayments ? '' : 'disabled'; ?>>
        <div class="field-grid two">
          <div>
            <label class="block text-sm font-medium mb-1">Nome</label>
            <input class="input w-full" name="name" value="<?= sanitize_html($formRow['name'] ?? ''); ?>" required>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Código</label>
            <?php $isDefaultCode = in_array($formRow['code'] ?? '', ['pix','zelle','venmo','paypal','square','stripe','whatsapp'], true); ?>
            <input class="input w-full" name="code" value="<?= sanitize_html($formRow['code'] ?? ''); ?>" <?= $isDefaultCode ? 'readonly' : ''; ?> placeholder="ex.: square">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Tipo</label>
            <?php $currentType = $isDefaultCode ? ($formRow['code'] ?? 'custom') : ($formSettings['type'] ?? 'custom'); ?>
            <select class="select w-full" name="method_type" <?= $isDefaultCode ? 'disabled' : ''; ?>>
            <?php $types = ['pix'=>'Pix','zelle'=>'Zelle','venmo'=>'Venmo','paypal'=>'PayPal','square'=>'Cartão de crédito (Square)','stripe'=>'Stripe','whatsapp'=>'WhatsApp','custom'=>'Personalizado']; ?>
              <?php foreach ($types as $value => $label): ?>
                <option value="<?= $value; ?>" <?= $currentType === $value ? 'selected' : ''; ?>><?= $label; ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($isDefaultCode): ?><input type="hidden" name="method_type" value="<?= sanitize_html($currentType); ?>"><?php endif; ?>
          </div>
          <div class="flex items-center gap-4">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?= (!isset($formRow['is_active']) || (int)$formRow['is_active'] === 1) ? 'checked' : ''; ?>> Ativo</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="require_receipt" value="1" <?= !empty($formRow['require_receipt']) ? 'checked' : ''; ?>> Exigir comprovante</label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Descrição interna</label>
          <input class="input w-full" name="description" value="<?= sanitize_html($formRow['description'] ?? ''); ?>" placeholder="Visível apenas no painel">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Instruções (placeholders: {valor_pedido}, {valor_produtos}, {valor_frete}, {numero_pedido}, {email_cliente}, {account_label}, {account_value}, {stripe_link}, {whatsapp_link}, {whatsapp_number}, {whatsapp_message})</label>
          <textarea class="textarea w-full" name="instructions" rows="4"><?= htmlspecialchars($formRow['instructions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="field-grid two">
          <div>
            <label class="block text-sm font-medium mb-1">Legenda do campo</label>
            <input class="input w-full" name="account_label" value="<?= sanitize_html($formSettings['account_label'] ?? ''); ?>">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Valor/Conta</label>
            <input class="input w-full" name="account_value" value="<?= sanitize_html($formSettings['account_value'] ?? ''); ?>">
          </div>
        </div>

        <div class="field-grid three">
          <div>
            <label class="block text-sm font-medium mb-1">Cor do botão</label>
            <input class="input w-full" type="color" name="button_bg" value="<?= sanitize_html($formSettings['button_bg'] ?? '#dc2626'); ?>">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Cor do texto</label>
            <input class="input w-full" type="color" name="button_text" value="<?= sanitize_html($formSettings['button_text'] ?? '#ffffff'); ?>">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Cor ao hover</label>
            <input class="input w-full" type="color" name="button_hover_bg" value="<?= sanitize_html($formSettings['button_hover_bg'] ?? '#b91c1c'); ?>">
          </div>
        </div>

        <div id="type-fields" class="field-grid two">
          <div data-type="pix">
            <label class="block text-sm font-medium mb-1">Chave Pix</label>
            <input class="input w-full" name="pix_key" value="<?= sanitize_html($formSettings['pix_key'] ?? ''); ?>">
          </div>
          <div data-type="pix">
            <label class="block text-sm font-medium mb-1">Nome do recebedor</label>
            <input class="input w-full" name="pix_merchant_name" value="<?= sanitize_html($formSettings['merchant_name'] ?? ''); ?>">
          </div>
          <div data-type="pix">
            <label class="block text-sm font-medium mb-1">Cidade</label>
            <input class="input w-full" name="pix_merchant_city" value="<?= sanitize_html($formSettings['merchant_city'] ?? ''); ?>">
          </div>

          <div data-type="zelle">
            <label class="block text-sm font-medium mb-1">Nome do recebedor</label>
            <input class="input w-full" name="zelle_recipient_name" value="<?= sanitize_html($formSettings['recipient_name'] ?? ''); ?>">
          </div>

          <div data-type="venmo">
            <label class="block text-sm font-medium mb-1">Link/Usuário do Venmo</label>
            <input class="input w-full" name="venmo_link" value="<?= sanitize_html($formSettings['venmo_link'] ?? ''); ?>">
          </div>

          <div data-type="whatsapp">
            <label class="block text-sm font-medium mb-1">Número WhatsApp</label>
            <input class="input w-full" name="whatsapp_number" value="<?= sanitize_html($formSettings['number'] ?? ''); ?>" placeholder="+55 8299999-0000">
            <p class="text-xs text-gray-500 mt-1">Informe com DDI/DD. Ex.: +55 82999990000</p>
          </div>
          <div data-type="whatsapp">
            <label class="block text-sm font-medium mb-1">Mensagem padrão</label>
            <textarea class="textarea w-full" name="whatsapp_message" rows="3" placeholder="Olá! Gostaria de finalizar meu pedido."><?= htmlspecialchars($formSettings['message'] ?? 'Olá! Gostaria de finalizar meu pedido.', ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
          <div data-type="whatsapp">
            <label class="block text-sm font-medium mb-1">Link personalizado (opcional)</label>
            <input class="input w-full" name="whatsapp_link" value="<?= sanitize_html($formSettings['link'] ?? ''); ?>" placeholder="https://wa.me/...">
            <p class="text-xs text-gray-500 mt-1">Se o número estiver preenchido, o link é gerado automaticamente.</p>
          </div>

          <div data-type="paypal">
            <label class="block text-sm font-medium mb-1">Conta PayPal / Email</label>
            <input class="input w-full" name="paypal_business" value="<?= sanitize_html($formSettings['business'] ?? ''); ?>">
          </div>
          <div data-type="paypal">
            <label class="block text-sm font-medium mb-1">Moeda</label>
            <input class="input w-full" name="paypal_currency" value="<?= sanitize_html($formSettings['currency'] ?? 'USD'); ?>">
          </div>
          <div data-type="paypal">
            <label class="block text-sm font-medium mb-1">Return URL</label>
            <input class="input w-full" name="paypal_return_url" value="<?= sanitize_html($formSettings['return_url'] ?? ''); ?>">
          </div>
          <div data-type="paypal">
            <label class="block text-sm font-medium mb-1">Cancel URL</label>
            <input class="input w-full" name="paypal_cancel_url" value="<?= sanitize_html($formSettings['cancel_url'] ?? ''); ?>">
          </div>

          <div data-type="square">
            <label class="block text-sm font-medium mb-1">Modo</label>
            <?php $squareMode = $formSettings['mode'] ?? 'square_product_link'; ?>
            <select class="select w-full" name="square_mode">
              <option value="square_product_link" <?= $squareMode === 'square_product_link' ? 'selected' : ''; ?>>Link definido por produto</option>
              <option value="direct_url" <?= $squareMode === 'direct_url' ? 'selected' : ''; ?>>URL fixa</option>
            </select>
          </div>
          <div data-type="square">
            <label class="block text-sm font-medium mb-1">Abrir em nova aba?</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="square_open_new_tab" value="1" <?= !empty($formSettings['open_new_tab']) ? 'checked' : ''; ?>> Nova aba</label>
          </div>
          <div data-type="square">
            <label class="block text-sm font-medium mb-1">URL fixa (opcional)</label>
            <input class="input w-full" name="square_redirect_url" value="<?= sanitize_html($formSettings['redirect_url'] ?? ''); ?>" placeholder="https://">
          </div>
          <div data-type="square" class="field-span-2 field-grid two">
            <div>
              <label class="block text-sm font-medium mb-1">Título (H1)</label>
              <input class="input w-full" name="square_badge_title" value="<?= sanitize_html($formSettings['badge_title'] ?? 'Seleção especial'); ?>" placeholder="Seleção especial">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Texto complementar</label>
              <input class="input w-full" name="square_badge_text" value="<?= sanitize_html($formSettings['badge_text'] ?? 'Selecionados com carinho para você'); ?>" placeholder="Selecionados com carinho para você">
            </div>
          </div>
          <div data-type="square" class="field-span-2 field-grid three">
            <div>
              <label class="block text-sm font-medium mb-1">Crédito - rótulo</label>
              <input class="input w-full" name="square_credit_label" value="<?= sanitize_html($formSettings['credit_label'] ?? 'Cartão de crédito'); ?>">
              <label class="block text-xs font-medium mt-2">Crédito - link do checkout (Square)</label>
              <input class="input w-full" name="square_credit_link" value="<?= sanitize_html($formSettings['credit_link'] ?? ''); ?>" placeholder="https://square.link/...">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Débito - rótulo</label>
              <input class="input w-full" name="square_debit_label" value="<?= sanitize_html($formSettings['debit_label'] ?? 'Cartão de débito'); ?>">
              <label class="block text-xs font-medium mt-2">Débito - link do checkout (Square)</label>
              <input class="input w-full" name="square_debit_link" value="<?= sanitize_html($formSettings['debit_link'] ?? ''); ?>" placeholder="https://square.link/...">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Afterpay - rótulo</label>
              <input class="input w-full" name="square_afterpay_label" value="<?= sanitize_html($formSettings['afterpay_label'] ?? 'Afterpay'); ?>">
              <label class="block text-xs font-medium mt-2">Afterpay - link do checkout (Square)</label>
              <input class="input w-full" name="square_afterpay_link" value="<?= sanitize_html($formSettings['afterpay_link'] ?? ''); ?>" placeholder="https://square.link/...">
            </div>
          </div>

          <div data-type="stripe">
            <label class="block text-sm font-medium mb-1">Modo</label>
            <?php $stripeMode = $formSettings['mode'] ?? 'stripe_product_link'; ?>
            <select class="select w-full" name="stripe_mode">
              <option value="stripe_product_link" <?= $stripeMode === 'stripe_product_link' ? 'selected' : ''; ?>>Link definido por produto</option>
              <option value="direct_url" <?= $stripeMode === 'direct_url' ? 'selected' : ''; ?>>URL fixa</option>
            </select>
          </div>
          <div data-type="stripe">
            <label class="block text-sm font-medium mb-1">Abrir em nova aba?</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="stripe_open_new_tab" value="1" <?= !empty($formSettings['open_new_tab']) ? 'checked' : ''; ?>> Nova aba</label>
          </div>
          <div data-type="stripe">
            <label class="block text-sm font-medium mb-1">URL fixa (opcional)</label>
            <input class="input w-full" name="stripe_redirect_url" value="<?= sanitize_html($formSettings['redirect_url'] ?? ''); ?>" placeholder="https://">
          </div>

          <div data-type="custom">
            <label class="block text-sm font-medium mb-1">Modo</label>
            <input class="input w-full" name="custom_mode" value="<?= sanitize_html($formSettings['mode'] ?? 'manual'); ?>">
          </div>
          <div data-type="custom">
            <label class="block text-sm font-medium mb-1">URL de redirecionamento</label>
            <input class="input w-full" name="custom_redirect_url" value="<?= sanitize_html($formSettings['redirect_url'] ?? ''); ?>">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Ícone (PNG/SVG opcional)</label>
          <input type="file" name="icon" accept="image/png,image/jpeg,image/webp,image/svg+xml">
          <?php if (!empty($formRow['icon_path'])): ?>
            <div class="mt-2"><img src="<?= sanitize_html($formRow['icon_path']); ?>" alt="ícone" class="h-10"></div>
          <?php endif; ?>
        </div>
        </fieldset>

        <div class="flex items-center gap-2 pt-2">
          <?php if ($canManagePayments): ?>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar</button>
          <?php endif; ?>
          <a class="btn btn-ghost" href="settings.php?tab=payments">Cancelar</a>
        </div>
      </form>
    </div>
  </div>

  <div data-tab-panel="builder" class="card p-6 <?= $tab === 'builder' ? '' : 'hidden'; ?>">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
      <div>
        <h2 class="text-lg font-semibold">Editor visual da home</h2>
        <p class="text-sm text-gray-500">Arraste blocos, edite textos e publique a nova página inicial.</p>
      </div>
      <div class="flex gap-2">
        <?php if ($canManageBuilder): ?>
          <button id="btn-preview" class="btn btn-ghost"><i class="fa-solid fa-eye mr-2"></i>Preview</button>
          <button id="btn-save" class="btn btn-ghost"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar rascunho</button>
          <button id="btn-publish" class="btn btn-primary"><i class="fa-solid fa-rocket mr-2"></i>Publicar</button>
        <?php else: ?>
          <span class="text-xs text-gray-400">Somente leitura</span>
        <?php endif; ?>
      </div>
    </div>
    <div id="builder-alert" class="hidden px-4 py-3 rounded-lg text-sm"></div>
    <div class="border border-gray-200 rounded-xl overflow-hidden">
      <?php if ($canManageBuilder): ?>
        <div id="gjs" style="min-height:600px;background:#f5f5f5;"></div>
      <?php else: ?>
        <div class="p-6 text-sm text-gray-500 bg-white">Você não possui permissão para editar o layout. Solicite a um administrador com acesso.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
</section>

<script>
  document.querySelectorAll('[data-tab-panel]').forEach(panel => {
    panel.classList.toggle('hidden', panel.getAttribute('data-tab-panel') !== '<?= $tab; ?>');
  });
  document.querySelectorAll('[href^="settings.php?tab="]').forEach(link => {
    link.addEventListener('click', function(e){
      if (this.pathname === window.location.pathname && this.search === window.location.search) {
        e.preventDefault();
      }
    });
  });
</script>

<?php if ($tab === 'payments'): ?>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
  <script>
    const list = document.getElementById("pm-sortable");
    if (list && list.dataset.sortableEnabled === '1') {
      new Sortable(list, {
        animation: 150,
        handle: ".fa-grip-lines",
        onEnd: function(){
          const ids = Array.from(list.querySelectorAll("li[data-id]")).map(el => el.dataset.id);
          fetch("settings.php?action=reorder", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ ids, csrf: '<?= csrf_token(); ?>' })
          });
        }
      });
    }
    const typeSelect = document.querySelector("select[name=method_type]");
    const groups = document.querySelectorAll("#type-fields [data-type]");
    function toggleTypeFields(){
      const current = typeSelect ? typeSelect.value : 'custom';
      groups.forEach(el => {
        el.style.display = (el.dataset.type === current) ? 'block' : 'none';
      });
    }
    if (typeSelect) {
      typeSelect.addEventListener('change', toggleTypeFields);
    }
    toggleTypeFields();
  </script>
<?php endif; ?>

<?php if ($tab === 'builder' && $canManageBuilder): ?>
  <link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.6/dist/css/grapes.min.css">
  <script src="https://unpkg.com/grapesjs@0.21.6/dist/grapes.min.js"></script>
  <script src="https://unpkg.com/grapesjs-blocks-basic@0.1.9/dist/grapesjs-blocks-basic.min.js"></script>
  <script>
    const API_URL = 'admin_api_layouts.php';
    const PAGE_SLUG = 'home';
    const CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES); ?>;
    const EXISTING_LAYOUT = <?= $layoutJson; ?>;

    function showMessage(msg, type='info') {
      const alertBox = document.getElementById('builder-alert');
      alertBox.textContent = msg;
      alertBox.className = '';
      alertBox.classList.add('px-4','py-3','rounded-lg','text-sm');
      if (type === 'success') alertBox.classList.add('bg-emerald-100','text-emerald-800');
      else if (type === 'warning') alertBox.classList.add('bg-amber-100','text-amber-800');
      else if (type === 'error') alertBox.classList.add('bg-red-100','text-red-800');
      else alertBox.classList.add('bg-gray-100','text-gray-800');
      alertBox.classList.remove('hidden');
      setTimeout(()=>alertBox.classList.add('hidden'), 7000);
    }

    const editor = grapesjs.init({
      container: '#gjs',
      height: '100%','storageManager': false,
      plugins: ['gjs-blocks-basic'],
      pluginsOpts: { 'gjs-blocks-basic': { flexGrid: true } },
      blockManager: { appendTo: '.gjs-pn-blocks-container' },
      selectorManager: { appendTo: '.gjs-sm-sectors' },
      styleManager: {
        appendTo: '.gjs-style-manager',
        sectors: [
          { name: 'Layout', open: true, buildProps: ['display','position','width','height','margin','padding'] },
          { name: 'Tipografia', open: false, buildProps: ['font-family','font-size','font-weight','letter-spacing','color','line-height','text-align'] },
          { name: 'Decoração', open: false, buildProps: ['background-color','background','border-radius','box-shadow'] }
        ]
      },
      canvas: { styles: ['https://cdn.tailwindcss.com'] }
    });

    function addCustomBlocks(){
      const bm = editor.BlockManager;
      bm.add('hero-banner', {
        category: 'Seções',
        label: '<i class="fa-solid fa-image mr-2"></i>Hero Banner',
        content: `
          <section class="hero-section" style="padding:60px 20px;background:linear-gradient(135deg,#dc2626,#f59e0b);color:#fff;text-align:center;">
            <div style="max-width:700px;margin:0 auto;">
              <h1 style="font-size:42px;font-weight:700;margin-bottom:20px;">Título chamativo para sua campanha</h1>
              <p style="font-size:19px;opacity:.9;margin-bottom:30px;">Conte ao cliente o benefício principal da loja e adicione um call-to-action para o produto mais importante.</p>
              <a href="#" class="cta-btn" style="display:inline-block;padding:14px 28px;background:#fff;color:#dc2626;font-weight:600;border-radius:999px;text-decoration:none;">Comprar agora</a>
            </div>
          </section>
        `
      });
      bm.add('product-grid', {
        category: 'Seções',
        label: '<i class="fa-solid fa-table-cells mr-2"></i>Grade de Produtos',
        content: `
          <section style="padding:50px 20px;background:#f9fafb;">
            <div style="max-width:1100px;margin:0 auto;">
              <h2 style="font-size:32px;text-align:center;margin-bottom:24px;">Destaques da semana</h2>
              <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
                <div class="product-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;">
                  <div style="height:160px;background:#f1f5f9;border-radius:12px;margin-bottom:16px;"></div>
                  <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">Nome do Produto</h3>
                  <p style="color:#475569;font-size:14px;margin-bottom:12px;">Descrição breve do produto e benefícios.</p>
                  <strong style="font-size:20px;color:#dc2626;">$ 29,90</strong>
                </div>
                <div class="product-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;">
                  <div style="height:160px;background:#f1f5f9;border-radius:12px;margin-bottom:16px;"></div>
                  <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">Nome do Produto</h3>
                  <p style="color:#475569;font-size:14px;margin-bottom:12px;">Descrição breve do produto e benefícios.</p>
                  <strong style="font-size:20px;color:#dc2626;">$ 39,90</strong>
                </div>
                <div class="product-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;">
                  <div style="height:160px;background:#f1f5f9;border-radius:12px;margin-bottom:16px;"></div>
                  <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">Nome do Produto</h3>
                  <p style="color:#475569;font-size:14px;margin-bottom:12px;">Descrição breve do produto e benefícios.</p>
                  <strong style="font-size:20px;color:#dc2626;">$ 19,90</strong>
                </div>
              </div>
            </div>
          </section>
        `
      });
      bm.add('testimonial', {
        category: 'Seções',
        label: '<i class="fa-solid fa-comment-dots mr-2"></i>Depoimentos',
        content: `
          <section style="padding:60px 20px;">
            <div style="max-width:900px;margin:0 auto;text-align:center;">
              <h2 style="font-size:32px;margin-bottom:30px;">O que nossos clientes dizem</h2>
              <div style="display:grid;gap:20px;">
                <blockquote style="background:#fff;border-radius:16px;padding:30px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
                  <p style="font-style:italic;color:#475569;">“Excelente atendimento, entrega rápida e produtos de qualidade. Recomendo muito!”</p>
                  <footer style="margin-top:18px;font-weight:600;">Maria Andrade — Fort Lauderdale</footer>
                </blockquote>
                <blockquote style="background:#fff;border-radius:16px;padding:30px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
                  <p style="font-style:italic;color:#475569;">“A loja online é super intuitiva e o suporte me ajudou rapidamente com minhas dúvidas.”</p>
                  <footer style="margin-top:18px;font-weight:600;">João Silva — Orlando</footer>
                </blockquote>
              </div>
            </div>
          </section>
        `
      });
    }
    addCustomBlocks();

    const DEFAULT_TEMPLATE = `
      <section style="padding:80px 20px;background:linear-gradient(135deg,#dc2626,#f59e0b);color:#fff;text-align:center;">
        <div style="max-width:760px;margin:0 auto;">
          <h1 style="font-size:48px;font-weight:700;margin-bottom:18px;">Tudo para sua saúde em poucos cliques</h1>
          <p style="font-size:20px;opacity:0.92;margin-bottom:28px;">Entrega rápida, atendimento humano e os melhores medicamentos do Brasil para os Estados Unidos.</p>
          <a href="#catalogo" style="display:inline-block;padding:16px 36px;border-radius:999px;background:#fff;color:#dc2626;font-weight:600;text-decoration:none;">Ver catálogo</a>
        </div>
      </section>
      <section id="catalogo" style="padding:60px 20px;background:#f9fafb;">
        <div style="max-width:1100px;margin:0 auto;">
          <h2 style="font-size:34px;font-weight:700;text-align:center;margin-bottom:16px;">Categorias em destaque</h2>
          <p style="text-align:center;color:#475569;margin-bottom:36px;">Escolha a linha de produtos que melhor atende à sua necessidade e receba tudo no conforto da sua casa.</p>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;">
            <div style="background:#fff;border-radius:18px;padding:26px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
              <h3 style="font-size:20px;font-weight:600;margin-bottom:6px;">Medicamentos</h3>
              <p style="color:#64748b;font-size:15px;margin-bottom:14px;">Genéricos, manipulados e medicamentos de alto custo com procedência garantida.</p>
              <a href="?route=home&category=1" style="color:#dc2626;font-weight:600;">Ver produtos →</a>
            </div>
            <div style="background:#fff;border-radius:18px;padding:26px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
              <h3 style="font-size:20px;font-weight:600;margin-bottom:6px;">Suplementos</h3>
              <p style="color:#64748b;font-size:15px;margin-bottom:14px;">Vitaminas, minerais e boosters energéticos selecionados por especialistas.</p>
              <a href="?route=home&category=4" style="color:#dc2626;font-weight:600;">Ver suplementos →</a>
            </div>
            <div style="background:#fff;border-radius:18px;padding:26px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
              <h3 style="font-size:20px;font-weight:600;margin-bottom:6px;">Dermocosméticos</h3>
              <p style="color:#64748b;font-size:15px;margin-bottom:14px;">Tratamentos faciais, linhas anti-idade e cuidados específicos para a pele.</p>
              <a href="?route=home&category=8" style="color:#dc2626;font-weight:600;">Ver dermocosméticos →</a>
            </div>
          </div>
        </div>
      </section>
      <section style="padding:60px 20px;">
        <div style="max-width:960px;margin:0 auto;border-radius:24px;background:linear-gradient(135deg,#22c55e,#14b8a6);padding:50px;color:#fff;">
          <h2 style="font-size:36px;font-weight:700;margin-bottom:18px;">Atendimento humano e entrega garantida</h2>
          <ul style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;font-size:16px;">
            <li>✔️ Pagamentos por Pix, Zelle, Venmo, PayPal, Cartão de crédito (Square) ou WhatsApp</li>
            <li>✔️ Equipe especializada para auxiliar na compra e prescrição</li>
            <li>✔️ Acompanhamento do pedido em tempo real pelo painel</li>
            <li>✔️ Entregas expressas em todo território norte-americano</li>
          </ul>
        </div>
      </section>
    `;
    const DEFAULT_STYLES = ``;

    function loadDraft(){
      try {
        const data = EXISTING_LAYOUT || {};
        let loaded = false;
        if (data.draft && data.draft.content) {
          editor.setComponents(data.draft.content);
          if (data.draft.styles) editor.setStyle(data.draft.styles);
          loaded = true;
        } else if (data.published && data.published.content) {
          showMessage('Nenhum rascunho encontrado. Carregando versão publicada.', 'warning');
          editor.setComponents(data.published.content);
          if (data.published.styles) editor.setStyle(data.published.styles);
          loaded = true;
        }
        if (!loaded) {
          editor.setComponents(DEFAULT_TEMPLATE);
          editor.setStyle(DEFAULT_STYLES);
          showMessage('Layout padrão carregado. Publique para substituir a home atual.', 'info');
        }
      } catch (err) {
        console.error(err);
        showMessage('Não foi possível carregar o layout: '+err.message, 'error');
        editor.setComponents(DEFAULT_TEMPLATE);
        editor.setStyle(DEFAULT_STYLES);
      }
    }

    function getPayload(){
      return {
        page: PAGE_SLUG,
        content: editor.getHtml({ componentFirst: true }),
        styles: editor.getCss(),
        meta: {
          updated_by: <?= json_encode($_SESSION['admin_email'] ?? 'admin', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
          updated_at: new Date().toISOString()
        },
        csrf: CSRF_TOKEN
      };
    }

    async function saveDraft(){
      showMessage('Salvando rascunho...', 'info');
      const payload = getPayload();
      try {
        const res = await fetch(API_URL+'?action=save', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Erro ao salvar');
        showMessage('Rascunho salvo com sucesso!', 'success');
      } catch (err) {
        showMessage('Erro ao salvar: '+err.message, 'error');
      }
    }

    async function publishDraft(){
      showMessage('Publicando alterações...', 'info');
      await saveDraft();
      try {
        const res = await fetch(API_URL+'?action=publish', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ page: PAGE_SLUG, csrf: CSRF_TOKEN }),
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Erro ao publicar');
        showMessage('Página publicada! As mudanças já estão na home.', 'success');
      } catch (err) {
        showMessage('Erro ao publicar: '+err.message, 'error');
      }
    }

    function previewDraft(){
      const html = editor.getHtml({ componentFirst: true });
      const css = editor.getCss();
      const win = window.open('', '_blank');
      const doc = win.document;
      doc.open();
      doc.write(`
        <!doctype html>
        <html lang="pt-br">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title>Preview - Home personalizada</title>
          <style>${css}</style>
        </head>
        <body>${html}</body>
        </html>
      `);
      doc.close();
    }

    document.getElementById('btn-save').addEventListener('click', saveDraft);
    document.getElementById('btn-publish').addEventListener('click', publishDraft);
    document.getElementById('btn-preview').addEventListener('click', previewDraft);

    if (editor.isReady) {
      loadDraft();
    } else {
      editor.on('load', loadDraft);
    }
  </script>
<?php endif; ?>
<?php
admin_footer();

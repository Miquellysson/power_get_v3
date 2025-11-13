<?php
// lib/utils.php - Utilitários do sistema Get Power Research (com settings, upload de logo e helpers)
require_once __DIR__.'/cache_helpers.php';

/* =========================================================================
   Carregamento de configuração (cfg)
   ========================================================================= */
if (!function_exists('cfg')) {
    function cfg() {
        static $config = null;
        if ($config === null) {
            // config.php retorna um array (além de definir constantes)
            $config = require __DIR__ . '/../config.php';
        }
        return $config;
    }
}

/* =========================================================================
   Settings persistentes (tabela settings)
   ========================================================================= */
if (!function_exists('settings_bootstrap')) {
    function settings_bootstrap(): void {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }
        try {
            $pdo = db();
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    skey VARCHAR(191) PRIMARY KEY,
                    svalue LONGTEXT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $bootstrapped = true;
        } catch (Throwable $e) {
            // Ignoramos para evitar fatal em ambientes sem permissão de migração automática.
            $bootstrapped = true;
        }
    }
}

if (!function_exists('setting_get')) {
    /**
     * Recupera um valor armazenado em settings, retornando $default quando ausente.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function setting_get(string $key, $default = null) {
        settings_bootstrap();
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            if ($value === false) {
                return $default;
            }
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            return $value;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('setting_set')) {
    /**
     * Persiste um valor na tabela settings. Objetos/arrays são serializados em JSON.
     *
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    function setting_set(string $key, $value): bool {
        settings_bootstrap();
        try {
            $pdo = db();
            $serialized = (is_array($value) || is_object($value))
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : (string)$value;
            $stmt = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
            return (bool)$stmt->execute([$key, $serialized]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('setting_set_multi')) {
    /**
     * Persiste vários valores de uma só vez na tabela settings.
     *
     * @param array $keyValuePairs ['chave' => 'valor', ...]
     * @return bool
     */
    function setting_set_multi(array $keyValuePairs): bool {
        if (!$keyValuePairs) {
            return true;
        }
        settings_bootstrap();
        $filtered = [];
        foreach ($keyValuePairs as $key => $value) {
            $key = trim((string)$key);
            if ($key === '') {
                continue;
            }
            $filtered[$key] = $value;
        }
        if (!$filtered) {
            return true;
        }
        try {
            $pdo = db();
            $placeholders = [];
            $params = [];
            foreach ($filtered as $key => $value) {
                $placeholders[] = '(?, ?)';
                $params[] = $key;
                $params[] = (is_array($value) || is_object($value))
                    ? json_encode($value, JSON_UNESCAPED_UNICODE)
                    : (string)$value;
            }
            $sql = 'INSERT INTO settings (skey, svalue) VALUES '.implode(',', $placeholders).' ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)';
            $stmt = $pdo->prepare($sql);
            return (bool)$stmt->execute($params);
        } catch (Throwable $e) {
            return false;
        }
    }
}

/* =========================================================================
   Migração automática mínima para campos do checkout
   ========================================================================= */
if (!function_exists('checkout_maybe_upgrade_schema')) {
    function checkout_maybe_upgrade_schema(): void {
        static $verified = false;
        if ($verified) {
            return;
        }

        try {
            $pdo = db();
        } catch (Throwable $e) {
            $verified = true;
            return;
        }

        try {
            $customerCols = [];
            try {
                $stmt = $pdo->query('SHOW COLUMNS FROM customers');
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        if (!empty($row['Field'])) {
                            $customerCols[] = $row['Field'];
                        }
                    }
                }
            } catch (Throwable $e) {
                $customerCols = [];
            }

            if ($customerCols) {
                if (!in_array('first_name', $customerCols, true)) {
                    try {
                        $pdo->exec("ALTER TABLE customers ADD COLUMN first_name VARCHAR(100) NOT NULL DEFAULT '' AFTER id");
                    } catch (Throwable $e) {}
                }
                if (!in_array('last_name', $customerCols, true)) {
                    try {
                        $pdo->exec("ALTER TABLE customers ADD COLUMN last_name VARCHAR(100) NOT NULL DEFAULT '' AFTER first_name");
                    } catch (Throwable $e) {}
                }
                if (!in_array('address2', $customerCols, true)) {
                    try {
                        $pdo->exec("ALTER TABLE customers ADD COLUMN address2 VARCHAR(255) NULL AFTER address");
                    } catch (Throwable $e) {}
                }
                if (!in_array('country', $customerCols, true)) {
                    try {
                        $pdo->exec("ALTER TABLE customers ADD COLUMN country VARCHAR(50) DEFAULT 'US' AFTER zipcode");
                    } catch (Throwable $e) {}
                }
            }

            $orderCols = [];
            try {
                $stmt = $pdo->query('SHOW COLUMNS FROM orders');
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        if (!empty($row['Field'])) {
                            $orderCols[] = $row['Field'];
                        }
                    }
                }
            } catch (Throwable $e) {
                $orderCols = [];
            }

            if ($orderCols) {
                if (!in_array('delivery_method_code', $orderCols, true)) {
                    try {
                        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_method_code VARCHAR(60) NULL AFTER payment_method");
                    } catch (Throwable $e) {}
                }
                if (!in_array('delivery_method_label', $orderCols, true)) {
                    try {
                        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_method_label VARCHAR(120) NULL AFTER delivery_method_code");
                    } catch (Throwable $e) {}
                }
                if (!in_array('delivery_method_details', $orderCols, true)) {
                    try {
                        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_method_details VARCHAR(255) NULL AFTER delivery_method_label");
                    } catch (Throwable $e) {}
                }
            }
        } finally {
            $verified = true;
        }
    }
}

/* =========================================================================
   Internacionalização
   ========================================================================= */
if (!function_exists('lang')) {
    function lang($key = null) {
        static $dict = null;

        if ($dict === null) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $lang = $_SESSION['lang'] ?? (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'pt_BR');
            $lang_code = substr($lang, 0, 2); // pt_BR -> pt

            $lang_files = [
                'pt' => __DIR__ . '/../i18n/pt.php',
                'en' => __DIR__ . '/../i18n/en.php',
                'es' => __DIR__ . '/../i18n/es.php',
            ];

            $file = $lang_files[$lang_code] ?? $lang_files['pt'];
            $dict = file_exists($file) ? require $file : get_default_dict();
            $dict['_lang'] = $lang_code;
        }

        return $key === null ? $dict : ($dict[$key] ?? $key);
    }
}

if (!function_exists('set_lang')) {
    function set_lang($lang) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $allowed = ['pt', 'pt_BR', 'en', 'en_US', 'es', 'es_ES'];
        if (in_array($lang, $allowed, true)) {
            $_SESSION['lang'] = $lang;
        }
    }
}

if (!function_exists('t')) {
    function t($key) { return lang($key); }
}

if (!function_exists('get_default_dict')) {
    function get_default_dict() {
        return [
            'title' => 'Get Power Research',
            'cart' => 'Carrinho',
            'search' => 'Buscar',
            'lang' => 'Idioma',
            'products' => 'Produtos',
            'subtotal' => 'Subtotal',
            'checkout' => 'Finalizar Compra',
            'name' => 'Nome',
            'email' => 'E-mail',
            'phone' => 'Telefone',
            'address' => 'Endereço',
            'city' => 'Cidade',
            'state' => 'Estado',
            'zipcode' => 'CEP',
            'customer_info' => 'Dados do Cliente',
            'payment_info' => 'Pagamento',
            'order_details' => 'Resumo do Pedido',
            'continue_shopping' => 'Continuar Comprando',
            'thank_you_order' => 'Obrigado pelo seu pedido!',
            'zelle' => 'Zelle',
            'venmo' => 'Venmo',
            'pix'   => 'PIX',
            'paypal'=> 'PayPal',
            'square'=> 'Cartão de crédito',
            'whatsapp'=> 'WhatsApp',
            'upload_receipt' => 'Enviar Comprovante',
            'place_order' => 'Finalizar Pedido',
            'add_to_cart' => 'Adicionar ao Carrinho',
            'order_received' => 'Pedido Recebido',
            'status' => 'Status',
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
        ];
    }
}

/* =========================================================================
   CSRF
   ========================================================================= */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}

/* =========================================================================
   Admin helpers
   ========================================================================= */
if (!function_exists('set_admin_session')) {
    function set_admin_session(array $adminData) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $id    = isset($adminData['id']) ? (int)$adminData['id'] : 0;
        $email = $adminData['email'] ?? null;
        $role  = $adminData['role'] ?? 'admin';
        $name  = $adminData['name'] ?? null;

        $_SESSION['admin'] = [
            'id'    => $id,
            'email' => $email,
            'role'  => $role,
            'name'  => $name,
        ];

        // Mantém compatibilidade com verificações existentes
        $_SESSION['admin_id']      = $id ?: 1;
        $_SESSION['admin_user_id'] = $id ?: null;
        $_SESSION['admin_email']   = $email;
        $_SESSION['admin_role']    = $role;
        if ($name) {
            $_SESSION['admin_name'] = $name;
        }
    }
}

if (!function_exists('current_admin')) {
    function current_admin(): ?array {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) {
            return $_SESSION['admin'];
        }
        if (!empty($_SESSION['admin_id'])) {
            return [
                'id'    => $_SESSION['admin_user_id'] ?? (int)$_SESSION['admin_id'],
                'email' => $_SESSION['admin_email'] ?? null,
                'role'  => $_SESSION['admin_role'] ?? 'admin',
                'name'  => $_SESSION['admin_name'] ?? null,
            ];
        }
        return null;
    }
}

if (!function_exists('current_admin_role')) {
    function current_admin_role(): string {
        $admin = current_admin();
        return $admin['role'] ?? 'admin';
    }
}

if (!function_exists('is_super_admin')) {
    function is_super_admin(): bool {
        return current_admin_role() === 'super_admin';
    }
}

if (!function_exists('require_super_admin')) {
    function require_super_admin(): void {
        if (!is_super_admin()) {
            admin_forbidden('Apenas super administradores podem executar esta ação.');
        }
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
            $cache[] = [
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
                    'link' => $paymentsCfg['whatsapp']['link'] ?? '',
                ],
                'icon_path' => null,
                'require_receipt' => 0,
            ];
        }

        if (!$cache) {
            if (!empty($paymentsCfg['pix']['enabled'])) {
                $cache[] = [
                    'code' => 'pix',
                    'name' => 'Pix',
                    'instructions' => "Use o Pix para pagar seu pedido. Valor: {valor_pedido}.\nChave: {pix_key}",
                    'settings' => [
                        'type' => 'pix',
                        'account_label' => 'Chave Pix',
                        'account_value' => $paymentsCfg['pix']['pix_key'] ?? '',
                        'pix_key' => $paymentsCfg['pix']['pix_key'] ?? '',
                        'merchant_name' => $paymentsCfg['pix']['merchant_name'] ?? '',
                        'merchant_city' => $paymentsCfg['pix']['merchant_city'] ?? '',
                    ],
                    'require_receipt' => 0,
                ];
            }
            if (!empty($paymentsCfg['zelle']['enabled'])) {
                $cache[] = [
                    'code' => 'zelle',
                    'name' => 'Zelle',
                    'instructions' => "Envie {valor_pedido} via Zelle para {account_value}.",
                    'settings' => [
                        'type' => 'zelle',
                        'account_label' => 'Zelle',
                        'account_value' => $paymentsCfg['zelle']['recipient_email'] ?? '',
                    ],
                    'require_receipt' => 0,
                ];
            }
        }

        return $cache;
    }
}

if (!function_exists('categories_supports_icon_column')) {
    function categories_supports_icon_column(PDO $pdo): bool {
        static $supported = null;
        if ($supported !== null) {
            return $supported;
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'icon'");
            $supported = $stmt && $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
        } catch (Throwable $e) {
            $supported = false;
        }
        return $supported;
    }
}

if (!function_exists('admin_forbidden')) {
    function admin_forbidden(string $message = 'Você não tem permissão para executar esta ação.'): void {
        http_response_code(403);
        if (function_exists('admin_header') && function_exists('admin_footer')) {
            admin_header('Acesso negado');
            echo '<div class="card p-6 mx-auto max-w-xl mt-10">';
            echo '<div class="card-title">Permissão negada</div>';
            echo '<div class="text-sm text-gray-600">'.sanitize_html($message).'</div>';
            echo '<div class="mt-4"><a class="btn" href="dashboard.php"><i class="fa-solid fa-arrow-left"></i> Voltar ao painel</a></div>';
            echo '</div>';
            admin_footer();
        } else {
            echo sanitize_html($message);
        }
        exit;
    }
}

if (!function_exists('admin_role_capabilities')) {
    function admin_role_capabilities(string $role): array {
        switch ($role) {
            case 'super_admin':
                return ['*'];
            case 'admin':
                return [
                    'manage_products',
                    'manage_categories',
                    'manage_orders',
                    'manage_customers',
                    'manage_settings',
                    'manage_payment_methods',
                    'manage_users',
                    'manage_builder',
                ];
            case 'manager':
                return [
                    'manage_products',
                    'manage_categories',
                    'manage_orders',
                    'manage_customers',
                ];
            case 'viewer':
            default:
                return [];
        }
    }
}

if (!function_exists('admin_can')) {
    function admin_can(string $capability): bool {
        if (is_super_admin()) {
            return true;
        }
        $role = current_admin_role();
        $caps = admin_role_capabilities($role);
        if (in_array('*', $caps, true)) {
            return true;
        }
        return in_array($capability, $caps, true);
    }
}

if (!function_exists('require_admin_capability')) {
    function require_admin_capability(string $capability): void {
        if (!admin_can($capability)) {
            admin_forbidden('Você não tem permissão para executar esta ação.');
        }
    }
}

if (!function_exists('normalize_hex_color')) {
    function normalize_hex_color(string $hex): string {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $hex = strtoupper(preg_replace('/[^0-9A-F]/i', '', $hex));
        if (strlen($hex) !== 6) {
            return '2060C8';
        }
        return $hex;
    }
}

if (!function_exists('adjust_color_brightness')) {
    function adjust_color_brightness(string $hex, float $factor): string {
        $hex = normalize_hex_color($hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $adjust = function ($channel) use ($factor) {
            if ($factor >= 0) {
                $channel = $channel + (255 - $channel) * $factor;
            } else {
                $channel = $channel * (1 + $factor);
            }
            return (int)max(0, min(255, round($channel)));
        };

        $r = $adjust($r);
        $g = $adjust($g);
        $b = $adjust($b);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}

if (!function_exists('generate_brand_palette')) {
    function generate_brand_palette(string $baseColor): array {
        $base = '#'.normalize_hex_color($baseColor);
        return [
            '50'      => adjust_color_brightness($base, 0.85),
            '100'     => adjust_color_brightness($base, 0.7),
            '200'     => adjust_color_brightness($base, 0.5),
            '300'     => adjust_color_brightness($base, 0.3),
            '400'     => adjust_color_brightness($base, 0.15),
            '500'     => adjust_color_brightness($base, 0.05),
            '600'     => $base,
            '700'     => adjust_color_brightness($base, -0.15),
            '800'     => adjust_color_brightness($base, -0.25),
            '900'     => adjust_color_brightness($base, -0.35),
            'DEFAULT' => $base,
        ];
    }
}

/* =========================================================================
   PIX - Payload EMV
   ========================================================================= */
if (!function_exists('pix_payload')) {
    function pix_payload($pix_key, $merchant_name, $merchant_city, $amount = 0.00, $txid = null) {
        // Payload Format Indicator
        $payload = "000201";

        // Point of Initiation Method
        if ($amount > 0) { $payload .= "010212"; } else { $payload .= "010211"; }

        // Merchant Account Information (GUI + chave + TXID opcional)
        $gui = "br.gov.bcb.pix";
        // GUI (00) + chave (01) + (02)TXID opcional
        $ma = "00" . sprintf("%02d", strlen($gui)) . $gui
            . "01" . sprintf("%02d", strlen($pix_key)) . $pix_key;
        if ($txid) {
            $ma .= "02" . sprintf("%02d", strlen($txid)) . $txid;
        }
        // ID 26 => Merchant Account Info template
        $payload .= "26" . sprintf("%02d", strlen($ma)) . $ma;

        // MCC
        $payload .= "52040000";

        // Currency BRL
        $payload .= "5303986";

        // Amount
        if ($amount > 0) {
            $amount_str = number_format((float)$amount, 2, '.', '');
            $payload .= "54" . sprintf("%02d", strlen($amount_str)) . $amount_str;
        }

        // Country
        $payload .= "5802BR";

        // Merchant Name (sem acentos, máx 25)
        $mname = substr(remove_accents($merchant_name), 0, 25);
        $payload .= "59" . sprintf("%02d", strlen($mname)) . $mname;

        // Merchant City (sem acentos, máx 15)
        $mcity = substr(remove_accents($merchant_city), 0, 15);
        $payload .= "60" . sprintf("%02d", strlen($mcity)) . $mcity;

        // CRC16
        $payload .= "6304";
        $crc = crc16_ccitt($payload);
        $payload .= strtoupper(sprintf("%04X", $crc));

        return $payload;
    }
}

if (!function_exists('remove_accents')) {
    function remove_accents($str) {
        $accents = [
            'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A',
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
            'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'Ç'=>'C','ç'=>'c','Ñ'=>'N','ñ'=>'n'
        ];
        return strtr($str, $accents);
    }
}

if (!function_exists('crc16_ccitt')) {
    function crc16_ccitt($data) {
        $crc = 0xFFFF;
        $poly = 0x1021;

        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8) & 0xFFFF;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ $poly) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return $crc & 0xFFFF;
    }
}

/* =========================================================================
   Validações e sanitização
   ========================================================================= */
if (!function_exists('validate_email')) {
    function validate_email($email) { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL); }
}
if (!function_exists('validate_phone')) {
    function validate_phone($phone) {
        $clean = preg_replace('/\D+/', '', (string)$phone);
        return strlen($clean) >= 10;
    }
}
if (!function_exists('sanitize_string')) {
    function sanitize_string($str, $max_length = 255) {
        $clean = trim(strip_tags((string)$str));
        return mb_substr($clean, 0, $max_length, 'UTF-8');
    }
}
if (!function_exists('sanitize_html')) {
    function sanitize_html($html) {
        return htmlspecialchars((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/* =========================================================================
   Uploads seguros (genérico) + upload específico de logo
   ========================================================================= */
if (!function_exists('validate_file_upload')) {
    function validate_file_upload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], $max_size = 2097152) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $err = isset($file['error']) ? (int)$file['error'] : -1;
            return ['success' => false, 'message' => 'Erro no upload: ' . $err];
        }
        if ((int)$file['size'] > (int)$max_size) {
            return ['success' => false, 'message' => 'Arquivo muito grande (máx: ' . formatBytes($max_size) . ').'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_types, true)) {
            return ['success' => false, 'message' => 'Tipo de arquivo não permitido.'];
        }
        return ['success' => true, 'mime_type' => $mime];
    }
}

if (!function_exists('save_logo_upload')) {
    function save_logo_upload(array $file) {
        // Salva a logo em storage/logo/logo.(png|jpg|jpeg|webp) e retorna caminho relativo ("storage/logo/logo.png")
        $validation = validate_file_upload($file, ['image/jpeg','image/png','image/webp'], 2 * 1024 * 1024);
        if (!$validation['success']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        $cfg = cfg();
        $dir = $cfg['paths']['logo'] ?? (__DIR__ . '/../storage/logo');
        @mkdir($dir, 0775, true);

        // extensão segura
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
            // mapear pelo mime
            $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $ext = $map[$validation['mime_type']] ?? 'png';
        }

        $filename = 'logo.' . $ext;
        $destAbs  = rtrim($dir, '/\\') . '/' . $filename;
        $destRel  = 'storage/logo/' . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
            return ['success' => false, 'message' => 'Falha ao mover arquivo de logo.'];
        }
        // opcionalmente: apagar outras extensões antigas para evitar conflito visual/cache
        foreach (['png','jpg','jpeg','webp'] as $e) {
            $p = rtrim($dir, '/\\') . '/logo.' . $e;
            if ($e !== $ext && file_exists($p)) { @unlink($p); }
        }
        // Grava a referência nas settings (logo_path)
        setting_set('store_logo', $destRel);

        return ['success' => true, 'path' => $destRel];
    }
}

if (!function_exists('get_logo_path')) {
    function get_logo_path() {
        $stored = (string)setting_get('store_logo', '');
        if ($stored && file_exists(__DIR__ . '/../' . $stored)) {
            return $stored;
        }
        // fallback: procurar logo física
        $candidates = [
            'storage/logo/logo.png',
            'storage/logo/logo.jpg',
            'storage/logo/logo.jpeg',
            'storage/logo/logo.webp',
        ];
        foreach ($candidates as $c) {
            if (file_exists(__DIR__ . '/../' . $c)) {
                return $c;
            }
        }
        return ''; // sem logo
    }
}

if (!function_exists('cost_management_enabled')) {
    function cost_management_enabled(): bool {
        return setting_get('cost_management_enabled', '0') === '1';
    }
}

if (!function_exists('product_profit_value')) {
    function product_profit_value(float $price, ?float $costPrice, ?float $profitOverride): float {
        if ($profitOverride !== null) {
            return $profitOverride;
        }
        if ($costPrice !== null) {
            return $price - $costPrice;
        }
        return 0.0;
    }
}

if (!function_exists('store_category_font_options')) {
    function store_category_font_options(): array {
        return [
            'default' => [
                'label' => 'Padrão (Inter)',
                'stack' => '',
                'requires' => [],
            ],
            'playfair' => [
                'label' => 'Playfair Display (serif elegante)',
                'stack' => "'Playfair Display', 'Georgia', serif",
                'requires' => ['playfair'],
            ],
            'pacifico' => [
                'label' => 'Pacifico (manuscrita)',
                'stack' => "'Pacifico', cursive",
                'requires' => ['pacifico'],
            ],
            'inter' => [
                'label' => 'Inter (sans moderna)',
                'stack' => "'Inter', 'system-ui', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
                'requires' => ['inter'],
            ],
            'serif' => [
                'label' => 'Serif clássica',
                'stack' => "'Cormorant Garamond', 'Times New Roman', serif",
                'requires' => ['cormorant'],
            ],
        ];
    }
}

if (!function_exists('store_category_font_stack')) {
    function store_category_font_stack(string $choice, string $custom = ''): array {
        $choice = trim(strtolower($choice));
        $options = store_category_font_options();

        if ($choice === 'custom') {
            $stack = trim($custom);
            if ($stack === '') {
                return ['stack' => '', 'requires' => []];
            }
            $stack = preg_replace('/[^a-zA-Z0-9,\s"\'\-&\/\+\.\(\)]/', '', $stack);
            return ['stack' => $stack, 'requires' => []];
        }

        if (!isset($options[$choice])) {
            $choice = 'default';
        }
        $entry = $options[$choice];
        return [
            'stack' => $entry['stack'],
            'requires' => $entry['requires'] ?? [],
        ];
    }
}

if (!function_exists('save_pwa_icon_upload')) {
    function save_pwa_icon_upload(array $file) {
        $validation = validate_file_upload($file, ['image/png'], 2 * 1024 * 1024);
        if (!$validation['success']) {
            return ['success' => false, 'message' => $validation['message'] ?? 'Arquivo inválido'];
        }

        $dir = __DIR__ . '/../storage/pwa';
        @mkdir($dir, 0775, true);

        $data = @file_get_contents($file['tmp_name']);
        if ($data === false) {
            return ['success' => false, 'message' => 'Falha ao ler o arquivo enviado.'];
        }

        $targets = [
            512 => $dir . '/icon-512.png',
            192 => $dir . '/icon-192.png',
            180 => $dir . '/icon-180.png',
        ];

        $generated = [];
        $canResize = function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor') && function_exists('imagepng');

        if ($canResize) {
            $src = @imagecreatefromstring($data);
            if ($src !== false) {
                $srcWidth  = imagesx($src);
                $srcHeight = imagesy($src);
                $square    = min($srcWidth, $srcHeight);
                foreach ($targets as $size => $path) {
                    $canvas = imagecreatetruecolor($size, $size);
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                    imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
                    imagecopyresampled(
                        $canvas,
                        $src,
                        0, 0,
                        ($srcWidth > $srcHeight) ? (int)(($srcWidth - $square) / 2) : 0,
                        ($srcHeight > $srcWidth) ? (int)(($srcHeight - $square) / 2) : 0,
                        $size, $size,
                        $square, $square
                    );
                    if (!@imagepng($canvas, $path, 9)) {
                        imagedestroy($canvas);
                        imagedestroy($src);
                        $canResize = false;
                        break;
                    }
                    $generated[] = $path;
                    imagedestroy($canvas);
                }
                imagedestroy($src);
            } else {
                $canResize = false;
            }
        }

        if (!$canResize) {
            $target512 = $targets[512];
            if (!@move_uploaded_file($file['tmp_name'], $target512)) {
                return ['success' => false, 'message' => 'Falha ao salvar ícone do app.'];
            }
            @copy($target512, $targets[192]);
            @copy($target512, $targets[180]);
        }

        setting_set('pwa_icon_last_update', (string)time());

        return ['success' => true];
    }
}

if (!function_exists('store_theme_options')) {
    function store_theme_options(): array {
        return [
            'default' => 'Tema padrão',
            'food'    => 'Tema alimentação',
        ];
    }
}

if (!function_exists('active_store_theme')) {
    function active_store_theme(): string {
        $available = store_theme_options();
        $stored = setting_get('store_theme', 'default');
        if (!is_string($stored) || !isset($available[$stored])) {
            return 'default';
        }
        return $stored;
    }
}

if (!function_exists('theme_food_default_config')) {
    function theme_food_default_config(): array {
        return [
            'hero_badge' => 'Sabores da Fazenda',
            'hero_title' => 'Rancho Nossa Terra',
            'hero_subtitle' => 'Nossa Gente',
            'hero_description' => 'Produtos artesanais brasileiros feitos com amor e tradição.',
            'hero_button_label' => 'Ver Produtos',
            'hero_button_link' => '#produtos',
            'hero_button_secondary_label' => 'Fale Conosco',
            'hero_button_secondary_link' => '#contato',
            'hero_background_mode' => 'image',
            'hero_background_color' => '#6DBA43',
            'hero_background_image' => 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?auto=format&fit=crop&w=1400&q=80',
            'hero_stats' => [
                [
                    'icon' => 'fa-leaf',
                    'title' => '100% Natural',
                    'description' => 'Ingredientes artesanais sem conservantes artificiais.',
                ],
                [
                    'icon' => 'fa-truck',
                    'title' => 'Entrega EUA',
                    'description' => 'Chegamos em todos os estados com logística refrigerada.',
                ],
                [
                    'icon' => 'fa-heart',
                    'title' => 'Tradição Familiar',
                    'description' => 'Receitas passadas de geração em geração.',
                ],
            ],
            'products_heading' => 'Nossos Produtos',
            'products_subheading' => 'Filtre por categoria e monte sua cesta saudável.',
            'products_group_by_category' => false,
            'products_uncategorized_label' => 'Outros sabores',
            'history_heading' => 'Nossa História',
            'history_subheading' => 'Da fazenda para a sua mesa, mantendo viva a essência do Brasil.',
            'history_description' => 'Trabalhamos com pequenos produtores e famílias artesãs que preservam técnicas tradicionais e ingredientes naturais.',
            'history_cards' => [
                [
                    'icon' => 'fa-home-heart',
                    'title' => 'Tradição Familiar',
                    'description' => 'Receitas cuidadosamente transmitidas de geração para geração.',
                ],
                [
                    'icon' => 'fa-seedling',
                    'title' => 'Ingredientes Naturais',
                    'description' => 'Nossa produção valoriza matérias-primas frescas e de origem consciente.',
                ],
                [
                    'icon' => 'fa-map-pin',
                    'title' => 'Raízes no Brasil',
                    'description' => 'Direto de Indiantown, FL, atendemos todo território americano.',
                ],
                [
                    'icon' => 'fa-people-roof',
                    'title' => 'Comunidade Local',
                    'description' => 'Criamos oportunidades para artesãos brasileiros próximos a você.',
                ],
            ],
            'history_stats' => [
                [
                    'label' => 'Produtos Artesanais',
                    'value' => '62+',
                    'color' => '#F59E0B',
                    'enabled' => true,
                ],
                [
                    'label' => 'Ingredientes Naturais',
                    'value' => '100%',
                    'color' => '#16A34A',
                    'enabled' => true,
                ],
                [
                    'label' => 'Estados Atendidos',
                    'value' => '50+',
                    'color' => '#2563EB',
                    'enabled' => true,
                ],
                [
                    'label' => 'Anos de Tradição',
                    'value' => '15+',
                    'color' => '#9333EA',
                    'enabled' => true,
                ],
                [
                    'label' => 'Parcerias com Fazendas',
                    'value' => '28+',
                    'color' => '#F97316',
                    'enabled' => true,
                ],
                [
                    'label' => 'Clientes Felizes',
                    'value' => '5k+',
                    'color' => '#0EA5E9',
                    'enabled' => true,
                ],
                [
                    'label' => 'Comunidades Atendidas',
                    'value' => '80+',
                    'color' => '#EC4899',
                    'enabled' => true,
                ],
                [
                    'label' => 'Pedidos Semanais',
                    'value' => '900+',
                    'color' => '#14B8A6',
                    'enabled' => true,
                ],
            ],
            'history_image' => 'https://images.unsplash.com/photo-1514996937319-344454492b37?auto=format&fit=crop&w=1200&q=80',
            'values_heading' => 'Nossos Valores',
            'values_subheading' => 'Transparência, sabor e cuidado em cada etapa.',
            'values_items' => [
                [
                    'icon' => 'fa-leaf',
                    'title' => 'Sustentabilidade',
                    'description' => 'Processos alinhados com boas práticas ambientais.',
                ],
                [
                    'icon' => 'fa-hand-holding-heart',
                    'title' => 'Cuidado humano',
                    'description' => 'Atendimento próximo para entender suas necessidades.',
                ],
                [
                    'icon' => 'fa-utensils',
                    'title' => 'Sabor autêntico',
                    'description' => 'Receitas tradicionais que valorizam ingredientes frescos.',
                ],
                [
                    'icon' => 'fa-truck-fast',
                    'title' => 'Entrega cuidadosa',
                    'description' => 'Envios refrigerados que preservam o frescor artesanal.',
                ],
            ],
            'highlight_heading' => 'Sabores da Fazenda',
            'highlight_subheading' => 'Descubra experiências gastronômicas completas.',
            'highlight_text' => 'Monte boxes especiais com doces, conservas, queijos e pães artesanais produzidos com carinho semanalmente.',
            'highlight_button_label' => 'Ver catálogo completo',
            'highlight_button_link' => '#produtos',
            'highlight_image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80',
            'contact_heading' => 'Fale com a gente',
            'contact_text' => 'Precisa de uma cesta personalizada ou quer revender? Nossa equipe está pronta para te ajudar.',
            'contact_form_button_label' => 'Enviar Mensagem',
        ];
    }
}

if (!function_exists('theme_food_config')) {
    function theme_food_config(): array {
        $defaults = theme_food_default_config();
        $raw = setting_get('theme_food_config', null);
        $data = [];
        if (is_array($raw)) {
            $data = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if (!$data) {
            $fallbackFile = __DIR__ . '/../storage/theme_food_config.json';
            if (is_file($fallbackFile)) {
                $fileContents = @file_get_contents($fallbackFile);
                if ($fileContents !== false) {
                    $decoded = json_decode($fileContents, true);
                    if (is_array($decoded)) {
                        $data = $decoded;
                    }
                }
            }
        }
        $config = array_replace_recursive($defaults, $data);

        $normalizeItems = static function (array $defaultsItems, array $providedItems): array {
            $normalized = [];
            $count = count($defaultsItems);
            for ($i = 0; $i < $count; $i++) {
                $base = $defaultsItems[$i];
                $incoming = $providedItems[$i] ?? [];
                if (!is_array($incoming)) {
                    $incoming = [];
                }
                $normalized[] = [
                    'icon' => trim((string)($incoming['icon'] ?? $base['icon'] ?? '')),
                    'title' => trim((string)($incoming['title'] ?? $base['title'] ?? '')),
                    'description' => trim((string)($incoming['description'] ?? $base['description'] ?? '')),
                ];
            }
            return $normalized;
        };

        $config['hero_stats'] = $normalizeItems($defaults['hero_stats'], is_array($config['hero_stats'] ?? null) ? $config['hero_stats'] : []);
        $config['values_items'] = $normalizeItems($defaults['values_items'], is_array($config['values_items'] ?? null) ? $config['values_items'] : []);

        $config['history_cards'] = $normalizeItems($defaults['history_cards'], is_array($config['history_cards'] ?? null) ? $config['history_cards'] : []);

        $normalizeStats = static function (array $defaultsStats, array $providedStats): array {
            $normalized = [];
            $count = count($defaultsStats);
            for ($i = 0; $i < $count; $i++) {
                $base = $defaultsStats[$i];
                $incoming = $providedStats[$i] ?? [];
                if (!is_array($incoming)) {
                    $incoming = [];
                }
                $label = trim((string)($incoming['label'] ?? $base['label'] ?? ''));
                $value = trim((string)($incoming['value'] ?? $base['value'] ?? ''));
                $color = strtoupper(trim((string)($incoming['color'] ?? $base['color'] ?? '#16A34A')));
                if (!preg_match('/^#[0-9A-F]{3}(?:[0-9A-F]{3})?$/', $color)) {
                    $color = $base['color'] ?? '#16A34A';
                }
                $enabledValue = $incoming['enabled'] ?? ($base['enabled'] ?? true);
                $normalized[] = [
                    'label' => $label,
                    'value' => $value,
                    'color' => $color,
                    'enabled' => (bool)$enabledValue,
                ];
            }
            return $normalized;
        };

        $config['history_stats'] = $normalizeStats($defaults['history_stats'], is_array($config['history_stats'] ?? null) ? $config['history_stats'] : []);
        $config['products_group_by_category'] = !empty($config['products_group_by_category']);
        $config['products_uncategorized_label'] = trim((string)($config['products_uncategorized_label'] ?? 'Outros sabores'));

        return $config;
    }
}

if (!function_exists('food_theme_category_icon_defaults')) {
    function food_theme_category_icon_defaults(): array {
        return [
            'todas' => 'ri-apps-line',
            'doces de leite' => 'ri-heart-line',
            'conservas' => 'ri-plant-line',
            'queijos' => 'ri-cake-line',
            'mel' => 'ri-drop-line',
            'biscoitos' => 'ri-cookie-line',
            'cristalizados' => 'ri-star-line',
            'geleias' => 'ri-bubble-chart-line',
            'conservas picantes' => 'ri-fire-line',
        ];
    }
}

if (!function_exists('save_theme_asset_upload')) {
    function save_theme_asset_upload(array $file, string $themeKey, string $prefix, int $maxSize = 3_145_728): array {
        if (empty($file['name'])) {
            return ['success' => false, 'message' => 'Nenhum arquivo enviado.'];
        }
        $validation = validate_file_upload($file, ['image/jpeg', 'image/png', 'image/webp'], $maxSize);
        if (!$validation['success']) {
            return ['success' => false, 'message' => $validation['message'] ?? 'Arquivo inválido'];
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $map = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];
            $ext = $map[$validation['mime_type'] ?? ''] ?? 'jpg';
        }
        $dir = __DIR__ . '/../storage/themes/' . $themeKey;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = sprintf(
            '%s_%s_%s.%s',
            $prefix,
            date('Ymd_His'),
            bin2hex(random_bytes(4)),
            $ext
        );
        $destAbs = rtrim($dir, '/\\') . '/' . $filename;
        if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
            return ['success' => false, 'message' => 'Falha ao salvar arquivo do tema.'];
        }
        $relative = 'storage/themes/' . $themeKey . '/' . $filename;
        return ['success' => true, 'path' => $relative];
    }
}

if (!function_exists('pwa_banner_config')) {
    function pwa_banner_config(): array {
        $defaults = [
            'enabled' => true,
            'show_delay_ms' => 2500,
            'display_duration_ms' => 0,
            'cooldown_hours' => 24,
            'message' => '📲 Instale o app para uma experiência melhor',
            'button_label' => 'Instalar agora',
            'position' => 'center',
            'background_color' => '#FFFFFF',
            'text_color' => '#111827',
            'button_background' => '#166534',
            'button_text_color' => '#FFFFFF',
            'border_color' => '#D1D5DB',
        ];

        $raw = setting_get('pwa_banner_config', '');
        $data = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $config = array_merge($defaults, array_intersect_key($data, $defaults));
        $config['enabled'] = !empty($config['enabled']);
        $config['show_delay_ms'] = max(0, (int)($config['show_delay_ms'] ?? 0));
        $config['display_duration_ms'] = max(0, (int)($config['display_duration_ms'] ?? 0));
        $config['cooldown_hours'] = max(0, (int)($config['cooldown_hours'] ?? 0));

        $positions = ['center', 'left', 'right'];
        if (!in_array($config['position'], $positions, true)) {
            $config['position'] = $defaults['position'];
        }

        foreach (['background_color', 'text_color', 'button_background', 'button_text_color', 'border_color'] as $colorKey) {
            $value = strtoupper(trim((string)$config[$colorKey]));
            if (!preg_match('/^#[0-9A-F]{3}(?:[0-9A-F]{3})?$/', $value)) {
                $config[$colorKey] = $defaults[$colorKey];
            } else {
                $config[$colorKey] = $value;
            }
        }

        $config['message'] = trim((string)$config['message']);
        if ($config['message'] === '') {
            $config['message'] = $defaults['message'];
        }

        $config['button_label'] = trim((string)$config['button_label']);
        if ($config['button_label'] === '') {
            $config['button_label'] = $defaults['button_label'];
        }

        return $config;
    }
}

if (!function_exists('home_sections_visibility_defaults')) {
    function home_sections_visibility_defaults(): array {
        return [
            'hero' => true,
            'categories' => true,
            'featured' => true,
            'products' => true,
            'values' => true,
            'highlight' => true,
            'contact' => true,
        ];
    }
}

if (!function_exists('home_sections_visibility')) {
    function home_sections_visibility(): array {
        $defaults = home_sections_visibility_defaults();
        $raw = setting_get('home_sections_visibility', '');
        $data = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        $visibility = [];
        foreach ($defaults as $key => $default) {
            $visibility[$key] = array_key_exists($key, $data) ? !empty($data[$key]) : $default;
        }
        return $visibility;
    }
}

if (!function_exists('navigation_links_raw')) {
    function navigation_links_raw(string $area): array {
        $area = strtolower($area);
        $settingKey = $area === 'header' ? 'navigation_header_links' : 'navigation_footer_links';
        $raw = setting_get($settingKey, '');
        $decoded = [];
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        }

        $links = [];
        $index = 0;
        foreach ($decoded as $entry) {
            $label = pm_sanitize($entry['label'] ?? '', 160);
            $url = trim((string)($entry['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $target = strtolower(trim((string)($entry['target'] ?? '_self')));
            $target = $target === '_blank' ? '_blank' : '_self';
            $order = isset($entry['order']) ? (int)$entry['order'] : (($index + 1) * 10);

            $links[] = [
                'label' => $label,
                'url' => $url,
                'target' => $target,
                'order' => $order,
            ];
            $index++;
        }

        usort($links, static function (array $a, array $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return $links;
    }
}

if (!function_exists('custom_pages_list')) {
    function custom_pages_list(): array {
        $raw = setting_get('custom_pages', '');
        $decoded = [];
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        }

        $pages = [];
        $allowedTags = '<p><br><strong><em><span><ul><ol><li><a><blockquote><h1><h2><h3><h4>';

        foreach ($decoded as $idx => $entry) {
            $slug = pm_slug($entry['slug'] ?? '');
            $title = pm_sanitize($entry['title'] ?? '', 160);
            if ($slug === '' || $title === '') {
                continue;
            }
            $contentRaw = (string)($entry['content'] ?? '');
            $content = pm_safe_html($contentRaw, $allowedTags, 12000);
            if ($content === '') {
                continue;
            }

            $pages[] = [
                'slug' => $slug,
                'title' => $title,
                'content' => $content,
                'show_in_header' => !empty($entry['show_in_header']),
                'show_in_footer' => !empty($entry['show_in_footer']),
                'order_header' => isset($entry['order_header']) ? (int)$entry['order_header'] : (($idx + 1) * 10),
                'order_footer' => isset($entry['order_footer']) ? (int)$entry['order_footer'] : (($idx + 1) * 10),
            ];
        }

        return $pages;
    }
}

if (!function_exists('navigation_links')) {
    function navigation_links(string $area, bool $includePages = true): array {
        $links = navigation_links_raw($area);
        $area = strtolower($area);
        $allowedAreas = ['header', 'footer'];
        if (!in_array($area, $allowedAreas, true)) {
            return $links;
        }

        $keyed = [];
        foreach ($links as $link) {
            $key = $link['url'] . '|' . $link['target'];
            $keyed[$key] = $link;
        }

        if ($includePages) {
            $pages = custom_pages_list();
            $orderField = $area === 'header' ? 'order_header' : 'order_footer';
            foreach ($pages as $page) {
                $shouldShow = $area === 'header' ? $page['show_in_header'] : $page['show_in_footer'];
                if (!$shouldShow) {
                    continue;
                }
                $url = '?route=page&slug=' . rawurlencode($page['slug']);
                $key = $url . '|_self';
                if (isset($keyed[$key])) {
                    continue;
                }
                $keyed[$key] = [
                    'label' => $page['title'],
                    'url' => $url,
                    'target' => '_self',
                    'order' => (int)($page[$orderField] ?? 0) + 500,
                ];
            }
        }

        $final = array_values($keyed);
        usort($final, static function (array $a, array $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return $final;
    }
}

if (!function_exists('find_custom_page')) {
    function find_custom_page(string $slug): ?array {
        $slug = pm_slug($slug);
        if ($slug === '') {
            return null;
        }
        foreach (custom_pages_list() as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }
        return null;
    }
}

if (!function_exists('app_base_uri')) {
    function app_base_uri(): string {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $appReal = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $scriptReal = $scriptFilename ? str_replace('\\', '/', realpath($scriptFilename)) : '';
        $scriptDirReal = $scriptReal ? str_replace('\\', '/', dirname($scriptReal)) : '';
        $scriptUrlDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

        $appBaseUri = '';
        if ($appReal && $scriptDirReal && strpos($scriptDirReal, $appReal) === 0) {
            $relativeDir = trim(substr($scriptDirReal, strlen($appReal)), '/');
            if ($relativeDir !== '') {
                $levels = substr_count($relativeDir, '/') + 1;
                $parts = $scriptUrlDir === '' ? [] : explode('/', $scriptUrlDir);
                if ($levels < count($parts)) {
                    $appBaseUri = implode('/', array_slice($parts, 0, count($parts) - $levels));
                }
            } else {
                $appBaseUri = $scriptUrlDir;
            }
        } else {
            $appBaseUri = $scriptUrlDir;
        }

        $appBaseUri = $appBaseUri !== '' ? '/' . ltrim($appBaseUri, '/') : '';
        $base = $appBaseUri;
        return $base;
    }
}

if (!function_exists('app_public_path')) {
    function app_public_path(string $path): string {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }
        if (preg_match('~^(?:[a-z][a-z0-9+\-.]*:|//)~i', $path)) {
            return $path;
        }

        $fragment = '';
        if (false !== $hashPos = strpos($path, '#')) {
            $fragment = substr($path, $hashPos);
            $path = substr($path, 0, $hashPos);
        }

        $query = '';
        if (false !== $qPos = strpos($path, '?')) {
            $query = substr($path, $qPos);
            $path = substr($path, 0, $qPos);
        }

        $path = preg_replace('#^\./#', '', $path);
        while (strpos($path, '../') === 0) {
            $path = substr($path, 3);
        }

        $normalized = '/' . ltrim($path, '/');
        if ($normalized === '//') {
            $normalized = '/';
        }

        $base = app_base_uri();
        if ($base !== '') {
            if ($normalized === $base || strpos($normalized, $base . '/') === 0) {
                $uri = $normalized;
            } else {
                $uri = $base . $normalized;
            }
        } else {
            $uri = $normalized;
        }

        return $uri . $query . $fragment;
    }
}

if (!function_exists('get_pwa_icon_paths')) {
    function get_pwa_icon_paths(): array {
        $defaults = [
            512 => 'assets/icons/admin-512.png',
            192 => 'assets/icons/admin-192.png',
            180 => 'assets/icons/admin-192.png',
        ];

        $storeLogo = null;
        if (function_exists('find_logo_path')) {
            $storeLogo = find_logo_path();
        } elseif (function_exists('get_logo_path')) {
            $storeLogo = get_logo_path();
        } else {
            $cfgLogo = function_exists('setting_get') ? setting_get('store_logo_url') : null;
            if ($cfgLogo) {
                $storeLogo = ltrim((string)$cfgLogo, '/');
            } else {
                $candidates = [
                    'storage/logo/logo.png',
                    'storage/logo/logo.jpg',
                    'storage/logo/logo.jpeg',
                    'storage/logo/logo.webp',
                    'assets/logo.png'
                ];
                foreach ($candidates as $c) {
                    if (file_exists(__DIR__ . '/../' . $c)) {
                        $storeLogo = $c;
                        break;
                    }
                }
            }
        }
        if ($storeLogo) {
            $storeLogo = ltrim($storeLogo, '/');
            foreach (array_keys($defaults) as $size) {
                $defaults[$size] = $storeLogo;
            }
        }

        $paths = [];
        foreach ($defaults as $size => $fallback) {
            $custom = 'storage/pwa/icon-' . $size . '.png';
            $rel = $fallback;
            if (file_exists(__DIR__ . '/../' . $custom)) {
                $rel = $custom;
            }
            $paths[$size] = [
                'relative' => $rel,
                'absolute' => __DIR__ . '/../' . $rel
            ];
        }
        return $paths;
    }
}

if (!function_exists('get_pwa_icon_path')) {
    function get_pwa_icon_path(int $size = 512): string {
        $icons = get_pwa_icon_paths();
        return $icons[$size]['relative'] ?? '';
    }
}

if (!function_exists('service_worker_url')) {
    function service_worker_url(string $path = 'sw.js'): string {
        $relative = ltrim($path, '/');
        if ($relative === '') {
            return '/sw.js';
        }
        $versioned = function_exists('cache_busted_url')
            ? cache_busted_url($relative)
            : $relative;

        if (!function_exists('cache_busted_url')) {
            $absolute = __DIR__ . '/../' . $relative;
            if (is_file($absolute) && strpos($versioned, 'v=') === false) {
                $versioned .= (strpos($versioned, '?') !== false ? '&' : '?') . 'v=' . filemtime($absolute);
            }
        }
        return '/' . ltrim($versioned, '/');
    }
}

if (!function_exists('pwa_icon_url')) {
    function pwa_icon_url(int $size = 512): string {
        $icons = get_pwa_icon_paths();
        if (!isset($icons[$size])) {
            return '';
        }
        $rel = $icons[$size]['relative'];
        $abs = $icons[$size]['absolute'];
        $url = function_exists('app_public_path') ? app_public_path($rel) : '';
        if (function_exists('cache_busted_url')) {
            $versioned = cache_busted_url($rel);
            return '/' . ltrim($versioned, '/');
        }
        if ($url === '') {
            $url = '/' . ltrim($rel, '/');
        }
        if (file_exists($abs)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'v=' . filemtime($abs);
        }
        return $url;
    }
}

/* =========================================================================
   Helpers de formatação
   ========================================================================= */
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = (float)$bytes;
        if ($bytes <= 0) return "0 B";
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount, $currency = null) {
        $amount = (float)$amount;
        $currency = strtoupper($currency ?? (cfg()['store']['currency'] ?? 'BRL'));
        switch ($currency) {
            case 'USD': return '$' . number_format($amount, 2, '.', ',');
            case 'EUR': return '€' . number_format($amount, 2, ',', '.');
            case 'BRL': return 'R$ ' . number_format($amount, 2, ',', '.');
            default:    return $currency . ' ' . number_format($amount, 2, ',', '.');
        }
    }
}

if (!function_exists('format_date')) {
    function format_date($date, $format = 'd/m/Y') {
        if (empty($date)) return '-';
        return date($format, strtotime($date));
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($datetime, $format = 'd/m/Y H:i') {
        if (empty($datetime)) return '-';
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('slugify')) {
    function slugify($text) {
        $text = remove_accents((string)$text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'n-a';
    }
}

/* =========================================================================
   Sistema de Notificações
   ========================================================================= */
if (!function_exists('send_notification')) {
    function send_notification($type, $title, $message, $data = null) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("INSERT INTO notifications (type, title, message, data, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                (string)$type,
                (string)$title,
                (string)$message,
                $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null
            ]);
            return true;
        } catch (Throwable $e) {
            error_log("Failed to send notification: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_unread_notifications')) {
    function get_unread_notifications($limit = 10) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('mark_notifications_read')) {
    function mark_notifications_read($ids = null) {
        try {
            $pdo = db();
            if ($ids === null) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
                $stmt->execute();
            } else {
                if (!is_array($ids)) $ids = [$ids];
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)");
                $stmt->execute(array_map('intval', $ids));
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

/* =========================================================================
   E-mail
   ========================================================================= */
if (!function_exists('send_email')) {
    function send_email($to, $subject, $body, $from = null) {
        $to = (string)$to;
        $subject = (string)$subject;
        $body = (string)$body;
        if (!$from) {
            $config = cfg();
            $from = $config['store']['support_email'] ?? 'no-reply@localhost';
        }
        $headers = [
            'From: ' . $from,
            'Reply-To: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];
        return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, implode("\r\n", $headers));
    }
}

if (!function_exists('email_template_defaults')) {
    function email_template_defaults($storeName = null) {
        $info = store_info();
        $detectedName = $storeName ?: ($info['name'] ?? 'Sua Loja');

        $customerSubject = "Pedido {{order_number}} confirmado - {$detectedName}";
        $customerBody = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Pedido confirmado – {{site_name}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    body,table,td,a { font-family: Arial, Helvetica, sans-serif; text-size-adjust:100%; -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; }
    table { border-collapse: collapse !important; }
    img { border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }
    a { color:#1a73e8; text-decoration:none; }
    body { margin:0; padding:0; width:100% !important; background:#f5f7fb; }
    .wrapper { width:100%; background:#f5f7fb; padding:24px 0; }
    .container { width:100%; max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(24,39,75,.06), 0 2px 6px rgba(24,39,75,.12); }
    .header { background:#111827; color:#ffffff; padding:24px; }
    .header h1 { margin:0; font-size:20px; font-weight:700; }
    .header .sub { margin:4px 0 0; font-size:12px; opacity:.85; }
    .intro { padding:20px 24px; background:#fafbff; border-bottom:1px solid #eef1f6; }
    .intro p { margin:0; font-size:14px; color:#111827; }
    .table { width:100%; }
    .table th { text-align:left; font-size:12px; color:#6b7280; padding:12px 24px; border-bottom:1px solid #eef1f6; }
    .table td { font-size:14px; color:#111827; padding:12px 24px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
    .muted { color:#6b7280; font-size:12px; }
    .section-title { padding:18px 24px 8px; font-size:13px; color:#374151; font-weight:700; text-transform:uppercase; letter-spacing:.02em; }
    .card { padding:0; }
    .additional { padding:16px 24px; background:#fff7ed; color:#7c2d12; border-top:1px solid #fde68a; font-size:13px; }
    .footer { text-align:center; padding:18px; font-size:12px; color:#6b7280; }
    @media (max-width: 600px) {
      .header, .intro, .additional { padding-left:16px; padding-right:16px; }
      .table th, .table td { padding-left:16px; padding-right:16px; }
      .section-title { padding-left:16px; padding-right:16px; }
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td align="center">
          <table role="presentation" class="container" cellspacing="0" cellpadding="0">
            <tr>
              <td class="header">
                <h1>{{site_name}}</h1>
                <div class="sub">Pedido {{order_number}} • {{order_date}}</div>
              </td>
            </tr>
            <tr>
              <td class="intro">
                <p>Olá {{billing_full_name}}, seu pedido foi confirmado! Acompanhe abaixo os detalhes.</p>
              </td>
            </tr>
            <tr>
              <td class="card">
                <div class="section-title">Resumo do pedido</div>
                <table role="presentation" class="table" cellspacing="0" cellpadding="0">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Qtd</th>
                      <th>Preço</th>
                    </tr>
                  </thead>
                  <tbody>
                    {{order_items_rows}}
                    <tr>
                      <td colspan="2" style="text-align:right;"><strong>Subtotal</strong></td>
                      <td>{{order_subtotal}}</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="text-align:right;"><strong>Frete</strong></td>
                      <td>{{order_shipping_total}}</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="text-align:right;"><strong>Impostos</strong></td>
                      <td>{{order_tax_total}}</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="text-align:right;"><strong>Descontos</strong></td>
                      <td>{{order_discount_total}}</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="text-align:right; font-size:16px;"><strong>Total</strong></td>
                      <td style="font-size:16px;"><strong>{{order_total}}</strong></td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td class="card">
                <div class="section-title">Pagamento e entrega</div>
                <table role="presentation" class="table" cellspacing="0" cellpadding="0">
                  <tbody>
                    <tr>
                      <td style="width:220px;">Método de pagamento</td>
                      <td>{{payment_method}}</td>
                    </tr>
                    <tr>
                      <td>Status do pagamento</td>
                      <td>{{payment_status}}</td>
                    </tr>
                    <tr>
                      <td>Forma de entrega</td>
                      <td>{{shipping_method}}</td>
                    </tr>
                    <tr>
                      <td>Detalhes da entrega</td>
                      <td>{{shipping_method_description}}</td>
                    </tr>
                    <tr>
                      <td>Observações</td>
                      <td>{{customer_note}}</td>
                    </tr>
                    <tr>
                      <td>Acompanhar pedido</td>
                      <td>{{track_link}}</td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td class="card">
                <div class="section-title">Seus dados</div>
                <table role="presentation" class="table" cellspacing="0" cellpadding="0">
                  <tbody>
                    <tr>
                      <td style="width:220px;">Nome</td>
                      <td>{{billing_first_name}}</td>
                    </tr>
                    <tr>
                      <td>Sobrenome</td>
                      <td>{{billing_last_name}}</td>
                    </tr>
                    <tr>
                      <td>E-mail</td>
                      <td><a href="mailto:{{billing_email_href}}">{{billing_email}}</a></td>
                    </tr>
                    <tr>
                      <td>Telefone</td>
                      <td>{{billing_phone}}</td>
                    </tr>
                    <tr>
                      <td>Rua e número</td>
                      <td>{{billing_address1}}</td>
                    </tr>
                    <tr>
                      <td>Complemento</td>
                      <td>{{billing_address2}}</td>
                    </tr>
                    <tr>
                      <td>Cidade</td>
                      <td>{{billing_city}}</td>
                    </tr>
                    <tr>
                      <td>Estado</td>
                      <td>{{billing_state}}</td>
                    </tr>
                    <tr>
                      <td>CEP</td>
                      <td>{{billing_postcode}}</td>
                    </tr>
                    <tr>
                      <td>País</td>
                      <td>{{billing_country}}</td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td class="additional">
                {{additional_content}}
              </td>
            </tr>
            <tr>
              <td class="footer">
                © {{year}} {{site_name}} · Este é um e-mail automático sobre o seu pedido.
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
HTML;

        $adminSubject = "Novo pedido {{order_number}} - {$detectedName}";
        $adminBody = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Novo pedido – {{site_name}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    body,table,td,a { font-family: Arial, Helvetica, sans-serif; text-size-adjust:100%; -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; }
    table { border-collapse: collapse !important; }
    img { border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }
    a { color:#1a73e8; text-decoration:none; }
    body { margin:0; padding:0; width:100% !important; background:#f5f7fb; }
    .wrapper { width:100%; background:#f5f7fb; padding:24px 0; }
    .container { width:100%; max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(24,39,75,.06), 0 2px 6px rgba(24,39,75,.12); }
    .header { background:#111827; color:#ffffff; padding:24px; }
    .header h1 { margin:0; font-size:20px; font-weight:700; }
    .header .sub { margin:4px 0 0; font-size:12px; opacity:.85; }
    .intro { padding:20px 24px; background:#fafbff; border-bottom:1px solid #eef1f6; }
    .intro p { margin:0; font-size:14px; color:#111827; }
    .table { width:100%; }
    .table th { text-align:left; font-size:12px; color:#6b7280; padding:12px 24px; border-bottom:1px solid #eef1f6; }
    .table td { font-size:14px; color:#111827; padding:12px 24px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
    .muted { color:#6b7280; font-size:12px; }
    .section-title { padding:18px 24px 8px; font-size:13px; color:#374151; font-weight:700; text-transform:uppercase; letter-spacing:.02em; }
    .card { padding:0; }
    .additional { padding:16px 24px; background:#fff7ed; color:#7c2d12; border-top:1px solid #fde68a; font-size:13px; }
    .footer { text-align:center; padding:18px; font-size:12px; color:#6b7280; }
    @media (max-width: 600px) {
      .header, .intro, .additional { padding-left:16px; padding-right:16px; }
      .table th, .table td { padding-left:16px; padding-right:16px; }
      .section-title { padding-left:16px; padding-right:16px; }
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td align="center">
          <table role="presentation" class="container" cellspacing="0" cellpadding="0">
            <tr>
              <td class="header">
                <h1>{{site_name}}</h1>
                <div class="sub">Novo pedido {{order_number}} • {{order_date}}</div>
              </td>
            </tr>
            <tr>
              <td class="intro">
                <p>Você recebeu um novo pedido de <strong>{{billing_full_name}}</strong>. Confira os detalhes abaixo.</p>
              </td>
            </tr>
            <tr>
              <td class="card">
                <div class="section-title">Itens do pedido</div>
                <table role="presentation" class="table" cellspacing="0" cellpadding="0">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Qtd</th>
                      <th>Preço</th>
                    </tr>
                  </thead>
                  <tbody>
                    {{order_items_rows}}
                    <tr>
                      <td colspan="2" style="text-align:right;"><strong>Subtotal</strong></td>
                      <td>{{order_subtotal}}</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="text-align:right;"><strong>Frete</strong></td>
                      <td>{{order_shipping_total}}</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="text-align:right;"><strong>Impostos</strong></td>
                      <td>{{order_tax_total}}</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="text-align:right;"><strong>Descontos</strong></td>
                      <td>{{order_discount_total}}</td>
                    </tr>
                    <tr>
                      <td colspan="2" style="text-align:right; font-size:16px;"><strong>Total</strong></td>
                      <td style="font-size:16px;"><strong>{{order_total}}</strong></td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td class="card">
                <div class="section-title">Metadados do pedido</div>
                <table role="presentation" class="table" cellspacing="0" cellpadding="0">
                  <tbody>
                    <tr>
                      <td style="width:220px;">Cliente</td>
                      <td>{{billing_full_name}}</td>
                    </tr>
                    <tr>
                      <td>E-mail</td>
                      <td><a href="mailto:{{billing_email_href}}">{{billing_email}}</a></td>
                    </tr>
                    <tr>
                      <td>Telefone</td>
                      <td>{{billing_phone}}</td>
                    </tr>
                    <tr>
                      <td>Método de pagamento</td>
                      <td>{{payment_method}}</td>
                    </tr>
                    <tr>
                      <td>Status do pagamento</td>
                      <td>{{payment_status}}</td>
                    </tr>
                    <tr>
                      <td>Forma de entrega</td>
                      <td>{{shipping_method}}</td>
                    </tr>
                    <tr>
                      <td>Detalhes da entrega</td>
                      <td>{{shipping_method_description}}</td>
                    </tr>
                    <tr>
                      <td>Observações do cliente</td>
                      <td>{{customer_note}}</td>
                    </tr>
                    <tr>
                      <td>Acompanhar pedido</td>
                      <td>{{track_link}}</td>
                    </tr>
                    <tr>
                      <td>Link no painel</td>
                      <td><a href="{{admin_order_url}}">{{admin_order_url}}</a></td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td class="card">
                <div class="section-title">Dados do checkout</div>
                <table role="presentation" class="table" cellspacing="0" cellpadding="0">
                  <tbody>
                    <tr>
                      <td style="width:220px;">Nome</td>
                      <td>{{billing_first_name}}</td>
                    </tr>
                    <tr>
                      <td>Sobrenome</td>
                      <td>{{billing_last_name}}</td>
                    </tr>
                    <tr>
                      <td>E-mail</td>
                      <td><a href="mailto:{{billing_email_href}}">{{billing_email}}</a></td>
                    </tr>
                    <tr>
                      <td>Telefone</td>
                      <td>{{billing_phone}}</td>
                    </tr>
                    <tr>
                      <td>Rua e número</td>
                      <td>{{billing_address1}}</td>
                    </tr>
                    <tr>
                      <td>Complemento</td>
                      <td>{{billing_address2}}</td>
                    </tr>
                    <tr>
                      <td>Cidade</td>
                      <td>{{billing_city}}</td>
                    </tr>
                    <tr>
                      <td>Estado</td>
                      <td>{{billing_state}}</td>
                    </tr>
                    <tr>
                      <td>CEP</td>
                      <td>{{billing_postcode}}</td>
                    </tr>
                    <tr>
                      <td>País</td>
                      <td>{{billing_country}}</td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td class="additional">
                {{additional_content}}
              </td>
            </tr>
            <tr>
              <td class="footer">
                © {{year}} {{site_name}} · Acesse o painel administrativo para atualizar o status do pedido.
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
HTML;

        return [
            'customer_subject' => $customerSubject,
            'customer_body' => $customerBody,
            'admin_subject' => $adminSubject,
            'admin_body' => $adminBody,
        ];
    }
}

if (!function_exists('email_render_template')) {
    function email_render_template($template, array $vars) {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string)$value;
        }
        return strtr((string)$template, $replacements);
    }
}

if (!function_exists('order_normalize_items')) {
    function order_normalize_items(array $items, ?string $fallbackCurrency = null): array {
        $cfg = cfg();
        $defaultCurrency = $fallbackCurrency !== null
            ? strtoupper($fallbackCurrency)
            : strtoupper($cfg['store']['currency'] ?? 'USD');
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string)($item['name'] ?? ($item['product_name'] ?? '')));
            if ($name === '') {
                continue;
            }
            $productId = isset($item['id']) ? (int)$item['id'] : (int)($item['product_id'] ?? 0);
            $sku = trim((string)($item['sku'] ?? ''));
            $qtyRaw = $item['qty'] ?? ($item['quantity'] ?? 0);
            $qty = max(1, (int)$qtyRaw);
            $price = (float)($item['price'] ?? 0);
            $currency = strtoupper($item['currency'] ?? $defaultCurrency);
            $shipping = isset($item['shipping_cost']) ? (float)$item['shipping_cost'] : 0.0;
            $costPrice = isset($item['cost_price']) ? (float)$item['cost_price'] : (isset($item['cost']) ? (float)$item['cost'] : null);
            if ($costPrice !== null && $costPrice < 0) {
                $costPrice = null;
            }
            $profitValue = null;
            if (isset($item['profit_value'])) {
                $profitValue = (float)$item['profit_value'];
            } elseif (isset($item['profit_amount'])) {
                $profitValue = (float)$item['profit_amount'];
            }
            if ($profitValue === null && $costPrice !== null) {
                $profitValue = $price - $costPrice;
            }
            $normalized[] = [
                'id' => $productId ?: null,
                'product_id' => $productId ?: null,
                'name' => $name,
                'sku' => $sku,
                'qty' => $qty,
                'price' => $price,
                'currency' => $currency,
                'shipping_cost' => $shipping,
                'cost_price' => $costPrice,
                'profit_value' => $profitValue,
            ];
        }
        return $normalized;
    }
}

if (!function_exists('order_fetch_items_from_table')) {
    function order_fetch_items_from_table(PDO $pdo, int $orderId): array {
        if ($orderId <= 0) {
            return [];
        }
        try {
            $stmt = $pdo->prepare('SELECT product_id, name, sku, price, quantity, cost_price, profit_amount FROM order_items WHERE order_id = ? ORDER BY id ASC');
            $stmt->execute([$orderId]);
            $items = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[] = [
                    'product_id' => isset($row['product_id']) ? (int)$row['product_id'] : null,
                    'name' => $row['name'],
                    'sku' => $row['sku'],
                    'price' => (float)$row['price'],
                    'qty' => max(1, (int)$row['quantity']),
                    'cost_price' => isset($row['cost_price']) ? (float)$row['cost_price'] : null,
                    'profit_value' => isset($row['profit_amount']) ? (float)$row['profit_amount'] : null,
                ];
            }
            return $items;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('order_get_items')) {
    function order_get_items(PDO $pdo, array $orderRow): array {
        $raw = $orderRow['items_json'] ?? '[]';
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = json_decode((string)$raw, true);
        }
        if (!is_array($items) || !$items) {
            $items = order_fetch_items_from_table($pdo, (int)($orderRow['id'] ?? 0));
        }
        $currency = strtoupper($orderRow['currency'] ?? (cfg()['store']['currency'] ?? 'USD'));
        return order_normalize_items($items, $currency);
    }
}

if (!function_exists('order_sync_items_table')) {
    function order_sync_items_table(PDO $pdo, int $orderId, array $items): void {
        if ($orderId <= 0) {
            return;
        }
        $normalized = order_normalize_items($items, null);
        try {
            $del = $pdo->prepare('DELETE FROM order_items WHERE order_id = ?');
            $del->execute([$orderId]);
        } catch (Throwable $e) {
            return;
        }
        if (!$normalized) {
            return;
        }
        try {
            $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, sku, price, quantity, cost_price, profit_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($normalized as $item) {
                $ins->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['sku'],
                    $item['price'],
                    $item['qty'],
                    $item['cost_price'],
                    $item['profit_value'],
                ]);
            }
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('email_build_order_rows')) {
    function email_build_order_rows(array $items, string $defaultCurrency): string {
        if (!$items) {
            return '<tr><td colspan="3">Nenhum item informado</td></tr>';
        }
        $rows = '';
        foreach ($items as $item) {
            $name = sanitize_html($item['name'] ?? '');
            $qty = max(1, (int)($item['qty'] ?? 0));
            $priceValue = (float)($item['price'] ?? 0);
            $itemCurrency = $item['currency'] ?? $defaultCurrency;
            $metaParts = [];
            if (!empty($item['sku'])) {
                $metaParts[] = 'SKU: ' . sanitize_html($item['sku']);
            }
            $meta = $metaParts ? '<br><span class="muted">' . implode(' • ', $metaParts) . '</span>' : '';
            $rows .= '<tr><td>' . $name . $meta . '</td><td>' . $qty . '</td><td>' . format_currency($priceValue * $qty, $itemCurrency) . '</td></tr>';
        }
        return $rows;
    }
}

if (!function_exists('send_order_confirmation')) {
    function send_order_confirmation($order_id, $customer_email) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT o.*,
                       c.first_name AS customer_first_name,
                       c.last_name  AS customer_last_name,
                       c.name       AS customer_name,
                       c.email      AS customer_email,
                       c.phone      AS customer_phone,
                       c.address    AS customer_address,
                       c.address2   AS customer_address2,
                       c.city       AS customer_city,
                       c.state      AS customer_state,
                       c.zipcode    AS customer_zipcode,
                       c.country    AS customer_country
                FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                WHERE o.id = ?
            ");
            $stmt->execute([(int)$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return false;
            }

            $items = order_get_items($pdo, $order);

            $cfg = cfg();
            $storeInfo = store_info();
            $storeName = $storeInfo['name'] ?? 'Sua Loja';
            $orderCurrency = strtoupper($order['currency'] ?? ($storeInfo['currency'] ?? ($cfg['store']['currency'] ?? 'USD')));

            $defaults   = email_template_defaults($storeName);
            $subjectTpl = setting_get('email_customer_subject', $defaults['customer_subject']);
            $bodyTpl    = setting_get('email_customer_body', $defaults['customer_body']);

            $orderItemsRows = email_build_order_rows($items, $orderCurrency);
            $itemsHtml = '<ul style="padding-left:18px;margin:0;">';
            foreach ($items as $item) {
                $nm = sanitize_html($item['name'] ?? '');
                $qt = max(1, (int)($item['qty'] ?? 0));
                $vl = (float)($item['price'] ?? 0);
                $itemCurrency = $item['currency'] ?? $orderCurrency;
                $itemsHtml .= '<li>'.$nm.' — Qtd: '.$qt.' — '.format_currency($vl * $qt, $itemCurrency).'</li>';
            }
            $itemsHtml .= '</ul>';

            $subtotalVal = (float)($order['subtotal'] ?? 0);
            $shippingVal = (float)($order['shipping_cost'] ?? 0);
            $totalVal    = (float)($order['total'] ?? 0);
            $taxFormatted = format_currency(0, $orderCurrency);
            $discountFormatted = format_currency(0, $orderCurrency);

            $baseUrl = rtrim($cfg['store']['base_url'] ?? '', '/');
            $trackToken = trim((string)($order['track_token'] ?? ''));
            $trackUrl = '';
            if ($trackToken !== '') {
                if ($baseUrl !== '') {
                    $trackUrl = rtrim($baseUrl, '/') . '/index.php?route=track&code=' . urlencode($trackToken);
                } else {
                    $trackUrl = '/index.php?route=track&code=' . urlencode($trackToken);
                }
            }
            $safeTrackUrl = $trackUrl ? sanitize_html($trackUrl) : '';
            $trackLink = $safeTrackUrl ? '<a href="'.$safeTrackUrl.'">'.$safeTrackUrl.'</a>' : '—';

            $paymentLabel = $order['payment_method'] ?? '-';
            try {
                $pm = $pdo->prepare("SELECT name FROM payment_methods WHERE code = ? LIMIT 1");
                $pm->execute([$order['payment_method'] ?? '']);
                $pmName = $pm->fetchColumn();
                if ($pmName) {
                    $paymentLabel = $pmName;
                }
            } catch (Throwable $e) {
                // ignore
            }

            $billingFirst = trim((string)($order['customer_first_name'] ?? ''));
            $billingLast  = trim((string)($order['customer_last_name'] ?? ''));
            $billingFull  = trim($billingFirst.' '.$billingLast);
            if ($billingFull === '') {
                $billingFull = trim((string)($order['customer_name'] ?? ''));
            }
            if ($billingFirst === '' && $billingFull !== '') {
                $billingFirst = $billingFull;
            }

            $addressLine1 = trim((string)($order['customer_address'] ?? ''));
            $addressLine2 = trim((string)($order['customer_address2'] ?? ''));
            $city = trim((string)($order['customer_city'] ?? ''));
            $state = trim((string)($order['customer_state'] ?? ''));
            $zipcode = trim((string)($order['customer_zipcode'] ?? ''));
            $country = trim((string)($order['customer_country'] ?? ''));

            $cityStateZip = trim($city);
            if ($state !== '') {
                $cityStateZip = $cityStateZip ? $cityStateZip.' - '.$state : $state;
            }
            if ($zipcode !== '') {
                $cityStateZip = $cityStateZip ? $cityStateZip.' '.$zipcode : $zipcode;
            }
            $addressParts = array_filter([$addressLine1, $addressLine2, $cityStateZip, $country], fn($part) => trim((string)$part) !== '');
            $billingAddressHtml = $addressParts ? implode('<br>', array_map('sanitize_html', $addressParts)) : '—';

            $deliveryCode = trim((string)($order['delivery_method_code'] ?? ''));
            $deliveryLabel = trim((string)($order['delivery_method_label'] ?? ''));
            $deliveryDetails = trim((string)($order['delivery_method_details'] ?? ''));
            if ($deliveryLabel === '' && $deliveryCode !== '') {
                $method = checkout_find_delivery_method($deliveryCode);
                if ($method) {
                    $deliveryLabel = $method['name'] ?? '';
                    if ($deliveryDetails === '' && !empty($method['description'])) {
                        $deliveryDetails = $method['description'];
                    }
                }
            }
            if ($deliveryLabel === '') {
                $deliveryLabel = 'Não informado';
            }
            if ($deliveryDetails === '') {
                $deliveryDetails = '—';
            }

            $supportEmail = setting_get('email_from_address', $storeInfo['email'] ?? ($cfg['store']['support_email'] ?? ''));
            $emailFromName = setting_get('email_from_name', $storeName);
            $additionalContent = 'Dúvidas? Responda este e-mail';
            if ($supportEmail) {
                $safeSupport = sanitize_html($supportEmail);
                $additionalContent .= ' ou fale com nossa equipe em <a href="mailto:'.$safeSupport.'">'.$safeSupport.'</a>.';
            } else {
                $additionalContent .= '.';
            }

            $orderDate = $order['created_at'] ?? '';
            try {
                $dt = $orderDate ? new DateTimeImmutable($orderDate) : new DateTimeImmutable();
                $orderDateFormatted = $dt->format('d/m/Y H:i');
            } catch (Throwable $e) {
                $orderDateFormatted = date('d/m/Y H:i');
            }

            $formatValue = function ($value) {
                $value = trim((string)$value);
                return $value !== '' ? sanitize_html($value) : '—';
            };

            $billingFirstSafe   = $formatValue($billingFirst);
            $billingLastSafe    = $formatValue($billingLast);
            $billingFullSafe    = $formatValue($billingFull);
            $billingEmailRaw    = trim((string)($order['customer_email'] ?? $customer_email));
            $billingEmailSafe   = $billingEmailRaw !== '' ? sanitize_html($billingEmailRaw) : '—';
            $billingEmailHref   = $billingEmailRaw !== '' ? sanitize_html($billingEmailRaw) : '';
            $billingPhoneRaw    = trim((string)($order['customer_phone'] ?? ''));
            $billingPhoneSafe   = $billingPhoneRaw !== '' ? sanitize_html($billingPhoneRaw) : '—';
            $address1Safe       = $formatValue($addressLine1);
            $address2Safe       = $formatValue($addressLine2);
            $citySafe           = $formatValue($city);
            $stateSafe          = $formatValue($state);
            $zipcodeSafe        = $formatValue($zipcode);
            $countrySafe        = $formatValue($country);

            $deliveryCodeRaw    = trim((string)$deliveryMethodCode);
            $deliveryCodeSafe   = $deliveryCodeRaw !== '' ? sanitize_html($deliveryCodeRaw) : '';

            $vars = [
                'store_name' => sanitize_html($storeName),
                'site_name' => sanitize_html($storeName),
                'order_id' => (string)$order_id,
                'order_number' => '#'.$order_id,
                'order_date' => sanitize_html($orderDateFormatted),
                'customer_name' => sanitize_html($order['customer_name'] ?? $billingFull),
                'customer_email' => sanitize_html($order['customer_email'] ?? $customer_email),
                'customer_phone' => $billingPhoneSafe,
                'billing_first_name' => $billingFirstSafe,
                'billing_last_name' => $billingLastSafe,
                'billing_full_name' => $billingFullSafe,
                'billing_email' => $billingEmailSafe,
                'billing_email_href' => $billingEmailHref,
                'billing_phone' => $billingPhoneSafe,
                'billing_address1' => $address1Safe,
                'billing_address2' => $address2Safe,
                'billing_city' => $citySafe,
                'billing_state' => $stateSafe,
                'billing_postcode' => $zipcodeSafe,
                'billing_country' => $countrySafe,
                'billing_address_html' => $billingAddressHtml,
                'billing_address' => $billingAddressHtml,
                'shipping_address1' => $address1Safe,
                'shipping_address2' => $address2Safe,
                'shipping_city' => $citySafe,
                'shipping_state' => $stateSafe,
                'shipping_postcode' => $zipcodeSafe,
                'shipping_country' => $countrySafe,
                'shipping_address_html' => $billingAddressHtml,
                'shipping_address' => $billingAddressHtml,
                'order_total' => format_currency($totalVal, $orderCurrency),
                'order_subtotal' => format_currency($subtotalVal, $orderCurrency),
                'order_shipping' => format_currency($shippingVal, $orderCurrency),
                'order_shipping_total' => format_currency($shippingVal, $orderCurrency),
                'order_tax_total' => $taxFormatted,
                'order_discount_total' => $discountFormatted,
                'order_items' => $itemsHtml,
                'order_items_rows' => $orderItemsRows,
                'payment_method' => sanitize_html($paymentLabel),
                'payment_status' => sanitize_html($order['payment_status'] ?? 'pending'),
                'payment_reference' => sanitize_html($order['payment_ref'] ?? ''),
                'customer_note' => sanitize_html($order['notes'] ?? '—'),
                'order_notes' => sanitize_html($order['notes'] ?? '—'),
                'track_link' => $trackLink,
                'track_url' => $safeTrackUrl,
                'support_email' => sanitize_html($supportEmail ?? ''),
                'shipping_method' => sanitize_html($deliveryLabel),
                'shipping_method_description' => sanitize_html($deliveryDetails),
                'shipping_address' => $billingAddressHtml,
                'delivery_method_code' => $deliveryCodeSafe,
                'additional_content' => $additionalContent,
                'year' => date('Y'),
            ];

            $subjectVars = $vars;
            $subjectVars['order_items'] = '';
            $subjectVars['order_items_rows'] = '';
            $subjectVars['track_link'] = $safeTrackUrl ?: '';

            $subject = email_render_template($subjectTpl, $subjectVars);
            $body    = email_render_template($bodyTpl, $vars);

            $mailOptions = [
                'subject' => $subject,
                'body' => $body,
                'from_name' => $emailFromName,
                'from_email' => $supportEmail ?: null,
            ];
            return send_email($customer_email, $subject, $body, $mailOptions);
        } catch (Throwable $e) {
            error_log('Failed to send order confirmation: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('send_order_admin_alert')) {
    function send_order_admin_alert($order_id, $extraEmails = null) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT o.*,
                       c.first_name AS customer_first_name,
                       c.last_name  AS customer_last_name,
                       c.name       AS customer_name,
                       c.email      AS customer_email,
                       c.phone      AS customer_phone,
                       c.address    AS customer_address,
                       c.address2   AS customer_address2,
                       c.city       AS customer_city,
                       c.state      AS customer_state,
                       c.zipcode    AS customer_zipcode,
                       c.country    AS customer_country
                FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                WHERE o.id = ?
            ");
            $stmt->execute([(int)$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return false;
            }

            $items = order_get_items($pdo, $order);

            $cfg = cfg();
            $storeInfo = store_info();
            $storeName = $storeInfo['name'] ?? ($cfg['store']['name'] ?? 'Sua Loja');
            $orderCurrency = strtoupper($order['currency'] ?? ($storeInfo['currency'] ?? ($cfg['store']['currency'] ?? 'USD')));

            $defaults   = email_template_defaults($storeName);
            $subjectTpl = setting_get('email_admin_subject', $defaults['admin_subject']);
            $bodyTpl    = setting_get('email_admin_body', $defaults['admin_body']);

            $orderItemsRows = email_build_order_rows($items, $orderCurrency);
            $itemsHtml = '<ul style="padding-left:18px;margin:0;">';
            for ($i = 0, $n = count($items); $i < $n; $i++) {
                $item = $items[$i];
                $nm = sanitize_html($item['name'] ?? '');
                $qt = max(1, (int)($item['qty'] ?? 0));
                $vl = (float)($item['price'] ?? 0);
                $itemCurrency = $item['currency'] ?? $orderCurrency;
                $itemsHtml .= '<li>'.$nm.' — Qtd: '.$qt.' — '.format_currency($vl * $qt, $itemCurrency).'</li>';
            }
            $itemsHtml .= '</ul>';

            $subtotalVal = (float)($order['subtotal'] ?? 0);
            $shippingVal = (float)($order['shipping_cost'] ?? 0);
            $totalVal    = (float)($order['total'] ?? 0);
            $taxFormatted = format_currency(0, $orderCurrency);
            $discountFormatted = format_currency(0, $orderCurrency);

            $baseUrl = rtrim($cfg['store']['base_url'] ?? '', '/');
            $adminOrderUrl = $baseUrl ? $baseUrl.'/admin.php?route=orders&action=view&id='.$order_id : 'admin.php?route=orders&action=view&id='.$order_id;

            $trackToken = trim((string)($order['track_token'] ?? ''));
            $trackUrl = '';
            if ($trackToken !== '') {
                if ($baseUrl !== '') {
                    $trackUrl = rtrim($baseUrl, '/') . '/index.php?route=track&code=' . urlencode($trackToken);
                } else {
                    $trackUrl = '/index.php?route=track&code=' . urlencode($trackToken);
                }
            }
            $safeTrackUrl = $trackUrl ? sanitize_html($trackUrl) : '';
            $trackLink = $safeTrackUrl ? '<a href="'.$safeTrackUrl.'">'.$safeTrackUrl.'</a>' : '—';

            $paymentLabel = $order['payment_method'] ?? '-';
            try {
                $pm = $pdo->prepare("SELECT name FROM payment_methods WHERE code = ? LIMIT 1");
                $pm->execute([$order['payment_method'] ?? '']);
                $pmName = $pm->fetchColumn();
                if ($pmName) {
                    $paymentLabel = $pmName;
                }
            } catch (Throwable $e) {
            }

            $billingFirst = trim((string)($order['customer_first_name'] ?? ''));
            $billingLast  = trim((string)($order['customer_last_name'] ?? ''));
            $billingFull  = trim($billingFirst.' '.$billingLast);
            if ($billingFull === '') {
                $billingFull = trim((string)($order['customer_name'] ?? ''));
            }
            if ($billingFirst === '' && $billingFull !== '') {
                $billingFirst = $billingFull;
            }

            $addressLine1 = trim((string)($order['customer_address'] ?? ''));
            $addressLine2 = trim((string)($order['customer_address2'] ?? ''));
            $city = trim((string)($order['customer_city'] ?? ''));
            $state = trim((string)($order['customer_state'] ?? ''));
            $zipcode = trim((string)($order['customer_zipcode'] ?? ''));
            $country = trim((string)($order['customer_country'] ?? ''));

            $cityStateZip = trim($city);
            if ($state !== '') {
                $cityStateZip = $cityStateZip ? $cityStateZip.' - '.$state : $state;
            }
            if ($zipcode !== '') {
                $cityStateZip = $cityStateZip ? $cityStateZip.' '.$zipcode : $zipcode;
            }
            $addressParts = array_filter([$addressLine1, $addressLine2, $cityStateZip, $country], fn($part) => trim((string)$part) !== '');
            $billingAddressHtml = $addressParts ? implode('<br>', array_map('sanitize_html', $addressParts)) : '—';

            $deliveryCode = trim((string)($order['delivery_method_code'] ?? ''));
            $deliveryLabel = trim((string)($order['delivery_method_label'] ?? ''));
            $deliveryDetails = trim((string)($order['delivery_method_details'] ?? ''));
            if ($deliveryLabel === '' && $deliveryCode !== '') {
                $method = checkout_find_delivery_method($deliveryCode);
                if ($method) {
                    $deliveryLabel = $method['name'] ?? '';
                    if ($deliveryDetails === '' && !empty($method['description'])) {
                        $deliveryDetails = $method['description'];
                    }
                }
            }
            if ($deliveryLabel === '') {
                $deliveryLabel = 'Não informado';
            }
            if ($deliveryDetails === '') {
                $deliveryDetails = '—';
            }

            $supportEmail = setting_get('email_from_address', $storeInfo['email'] ?? ($cfg['store']['support_email'] ?? null));
            $emailFromName = setting_get('email_from_name', $storeName);
            if (!$supportEmail && defined('ADMIN_EMAIL')) {
                $supportEmail = ADMIN_EMAIL;
            }

            $additionalContent = 'Gerencie este pedido no painel: <a href="'.sanitize_html($adminOrderUrl).'">'.sanitize_html($adminOrderUrl).'</a>';

            $orderDate = $order['created_at'] ?? '';
            try {
                $dt = $orderDate ? new DateTimeImmutable($orderDate) : new DateTimeImmutable();
                $orderDateFormatted = $dt->format('d/m/Y H:i');
            } catch (Throwable $e) {
                $orderDateFormatted = date('d/m/Y H:i');
            }

            $formatValue = function ($value) {
                $value = trim((string)$value);
                return $value !== '' ? sanitize_html($value) : '—';
            };

            $billingFirstSafe   = $formatValue($billingFirst);
            $billingLastSafe    = $formatValue($billingLast);
            $billingFullSafe    = $formatValue($billingFull);
            $billingEmailRaw    = trim((string)($order['customer_email'] ?? ''));
            $billingEmailSafe   = $billingEmailRaw !== '' ? sanitize_html($billingEmailRaw) : '—';
            $billingEmailHref   = $billingEmailRaw !== '' ? sanitize_html($billingEmailRaw) : '';
            $billingPhoneRaw    = trim((string)($order['customer_phone'] ?? ''));
            $billingPhoneSafe   = $billingPhoneRaw !== '' ? sanitize_html($billingPhoneRaw) : '—';
            $address1Safe       = $formatValue($addressLine1);
            $address2Safe       = $formatValue($addressLine2);
            $citySafe           = $formatValue($city);
            $stateSafe          = $formatValue($state);
            $zipcodeSafe        = $formatValue($zipcode);
            $countrySafe        = $formatValue($country);

            $deliveryCodeRaw    = trim((string)$deliveryCode);
            $deliveryCodeSafe   = $deliveryCodeRaw !== '' ? sanitize_html($deliveryCodeRaw) : '';

            $vars = [
                'store_name' => sanitize_html($storeName),
                'site_name' => sanitize_html($storeName),
                'order_id' => (string)$order_id,
                'order_number' => '#'.$order_id,
                'order_date' => sanitize_html($orderDateFormatted),
                'customer_name' => $billingFullSafe,
                'customer_email' => $billingEmailSafe,
                'customer_phone' => $billingPhoneSafe,
                'billing_first_name' => $billingFirstSafe,
                'billing_last_name' => $billingLastSafe,
                'billing_full_name' => $billingFullSafe,
                'billing_email' => $billingEmailSafe,
                'billing_email_href' => $billingEmailHref,
                'billing_phone' => $billingPhoneSafe,
                'billing_address1' => $address1Safe,
                'billing_address2' => $address2Safe,
                'billing_city' => $citySafe,
                'billing_state' => $stateSafe,
                'billing_postcode' => $zipcodeSafe,
                'billing_country' => $countrySafe,
                'billing_address_html' => $billingAddressHtml,
                'billing_address' => $billingAddressHtml,
                'shipping_address1' => $address1Safe,
                'shipping_address2' => $address2Safe,
                'shipping_city' => $citySafe,
                'shipping_state' => $stateSafe,
                'shipping_postcode' => $zipcodeSafe,
                'shipping_country' => $countrySafe,
                'shipping_address_html' => $billingAddressHtml,
                'shipping_address' => $billingAddressHtml,
                'order_total' => format_currency($totalVal, $orderCurrency),
                'order_subtotal' => format_currency($subtotalVal, $orderCurrency),
                'order_shipping' => format_currency($shippingVal, $orderCurrency),
                'order_shipping_total' => format_currency($shippingVal, $orderCurrency),
                'order_tax_total' => $taxFormatted,
                'order_discount_total' => $discountFormatted,
                'order_items' => $itemsHtml,
                'order_items_rows' => $orderItemsRows,
                'payment_method' => sanitize_html($paymentLabel),
                'payment_status' => sanitize_html($order['payment_status'] ?? 'pending'),
                'payment_reference' => sanitize_html($order['payment_ref'] ?? ''),
                'customer_note' => sanitize_html($order['notes'] ?? '—'),
                'order_notes' => sanitize_html($order['notes'] ?? '—'),
                'track_link' => $trackLink,
                'track_url' => $safeTrackUrl,
                'shipping_method' => sanitize_html($deliveryLabel),
                'shipping_method_description' => sanitize_html($deliveryDetails),
                'shipping_address' => $billingAddressHtml,
                'delivery_method_code' => $deliveryCodeSafe,
                'admin_order_url' => sanitize_html($adminOrderUrl),
                'additional_content' => $additionalContent,
                'year' => date('Y'),
            ];

            $subjectVars = $vars;
            $subjectVars['order_items'] = '';
            $subjectVars['order_items_rows'] = '';

            $subject = email_render_template($subjectTpl, $subjectVars);
            $body    = email_render_template($bodyTpl, $vars);

            $recipients = [];
            if ($extraEmails) {
                if (is_array($extraEmails)) {
                    $recipients = array_merge($recipients, $extraEmails);
                } else {
                    $recipients[] = (string)$extraEmails;
                }
            }
            if ($supportEmail) {
                $recipients[] = $supportEmail;
            }

            $recipients = array_filter(array_unique(array_map('trim', $recipients)), fn($email) => validate_email($email));
            if (!$recipients) {
                return false;
            }

            $success = true;
            $mailOptions = [
                'from_name' => $emailFromName,
                'from_email' => $supportEmail ?: null,
            ];
            foreach ($recipients as $recipient) {
                if (!send_email($recipient, $subject, $body, $mailOptions)) {
                    $success = false;
                }
            }
            return $success;
        } catch (Throwable $e) {
            error_log('Failed to send admin alert: ' . $e->getMessage());
            return false;
        }
    }
}


/* =========================================================================
   Checkout — países, estados e métodos de entrega configuráveis
   ========================================================================= */
if (!function_exists('checkout_default_countries')) {
    function checkout_default_countries(): array {
        return [
            ['code' => 'US', 'name' => 'Estados Unidos'],
            ['code' => 'BR', 'name' => 'Brasil'],
        ];
    }
}

if (!function_exists('checkout_default_states')) {
    function checkout_default_states(): array {
        return [
            ['country' => 'US', 'code' => 'AL', 'name' => 'Alabama'],
            ['country' => 'US', 'code' => 'AK', 'name' => 'Alaska'],
            ['country' => 'US', 'code' => 'AZ', 'name' => 'Arizona'],
            ['country' => 'US', 'code' => 'AR', 'name' => 'Arkansas'],
            ['country' => 'US', 'code' => 'CA', 'name' => 'Califórnia'],
            ['country' => 'US', 'code' => 'CO', 'name' => 'Colorado'],
            ['country' => 'US', 'code' => 'CT', 'name' => 'Connecticut'],
            ['country' => 'US', 'code' => 'DE', 'name' => 'Delaware'],
            ['country' => 'US', 'code' => 'DC', 'name' => 'Distrito de Columbia'],
            ['country' => 'US', 'code' => 'FL', 'name' => 'Flórida'],
            ['country' => 'US', 'code' => 'GA', 'name' => 'Geórgia'],
            ['country' => 'US', 'code' => 'HI', 'name' => 'Havaí'],
            ['country' => 'US', 'code' => 'ID', 'name' => 'Idaho'],
            ['country' => 'US', 'code' => 'IL', 'name' => 'Illinois'],
            ['country' => 'US', 'code' => 'IN', 'name' => 'Indiana'],
            ['country' => 'US', 'code' => 'IA', 'name' => 'Iowa'],
            ['country' => 'US', 'code' => 'KS', 'name' => 'Kansas'],
            ['country' => 'US', 'code' => 'KY', 'name' => 'Kentucky'],
            ['country' => 'US', 'code' => 'LA', 'name' => 'Louisiana'],
            ['country' => 'US', 'code' => 'ME', 'name' => 'Maine'],
            ['country' => 'US', 'code' => 'MD', 'name' => 'Maryland'],
            ['country' => 'US', 'code' => 'MA', 'name' => 'Massachusetts'],
            ['country' => 'US', 'code' => 'MI', 'name' => 'Michigan'],
            ['country' => 'US', 'code' => 'MN', 'name' => 'Minnesota'],
            ['country' => 'US', 'code' => 'MS', 'name' => 'Mississippi'],
            ['country' => 'US', 'code' => 'MO', 'name' => 'Missouri'],
            ['country' => 'US', 'code' => 'MT', 'name' => 'Montana'],
            ['country' => 'US', 'code' => 'NE', 'name' => 'Nebraska'],
            ['country' => 'US', 'code' => 'NV', 'name' => 'Nevada'],
            ['country' => 'US', 'code' => 'NH', 'name' => 'New Hampshire'],
            ['country' => 'US', 'code' => 'NJ', 'name' => 'New Jersey'],
            ['country' => 'US', 'code' => 'NM', 'name' => 'Novo México'],
            ['country' => 'US', 'code' => 'NY', 'name' => 'Nova Iorque'],
            ['country' => 'US', 'code' => 'NC', 'name' => 'Carolina do Norte'],
            ['country' => 'US', 'code' => 'ND', 'name' => 'Dacota do Norte'],
            ['country' => 'US', 'code' => 'OH', 'name' => 'Ohio'],
            ['country' => 'US', 'code' => 'OK', 'name' => 'Oklahoma'],
            ['country' => 'US', 'code' => 'OR', 'name' => 'Oregon'],
            ['country' => 'US', 'code' => 'PA', 'name' => 'Pensilvânia'],
            ['country' => 'US', 'code' => 'RI', 'name' => 'Rhode Island'],
            ['country' => 'US', 'code' => 'SC', 'name' => 'Carolina do Sul'],
            ['country' => 'US', 'code' => 'SD', 'name' => 'Dacota do Sul'],
            ['country' => 'US', 'code' => 'TN', 'name' => 'Tennessee'],
            ['country' => 'US', 'code' => 'TX', 'name' => 'Texas'],
            ['country' => 'US', 'code' => 'UT', 'name' => 'Utah'],
            ['country' => 'US', 'code' => 'VT', 'name' => 'Vermont'],
            ['country' => 'US', 'code' => 'VA', 'name' => 'Virgínia'],
            ['country' => 'US', 'code' => 'WA', 'name' => 'Washington'],
            ['country' => 'US', 'code' => 'WV', 'name' => 'Virgínia Ocidental'],
            ['country' => 'US', 'code' => 'WI', 'name' => 'Wisconsin'],
            ['country' => 'US', 'code' => 'WY', 'name' => 'Wyoming'],
            ['country' => 'BR', 'code' => 'AC', 'name' => 'Acre'],
            ['country' => 'BR', 'code' => 'AL', 'name' => 'Alagoas'],
            ['country' => 'BR', 'code' => 'AP', 'name' => 'Amapá'],
            ['country' => 'BR', 'code' => 'AM', 'name' => 'Amazonas'],
            ['country' => 'BR', 'code' => 'BA', 'name' => 'Bahia'],
            ['country' => 'BR', 'code' => 'CE', 'name' => 'Ceará'],
            ['country' => 'BR', 'code' => 'DF', 'name' => 'Distrito Federal'],
            ['country' => 'BR', 'code' => 'ES', 'name' => 'Espírito Santo'],
            ['country' => 'BR', 'code' => 'GO', 'name' => 'Goiás'],
            ['country' => 'BR', 'code' => 'MA', 'name' => 'Maranhão'],
            ['country' => 'BR', 'code' => 'MT', 'name' => 'Mato Grosso'],
            ['country' => 'BR', 'code' => 'MS', 'name' => 'Mato Grosso do Sul'],
            ['country' => 'BR', 'code' => 'MG', 'name' => 'Minas Gerais'],
            ['country' => 'BR', 'code' => 'PA', 'name' => 'Pará'],
            ['country' => 'BR', 'code' => 'PB', 'name' => 'Paraíba'],
            ['country' => 'BR', 'code' => 'PR', 'name' => 'Paraná'],
            ['country' => 'BR', 'code' => 'PE', 'name' => 'Pernambuco'],
            ['country' => 'BR', 'code' => 'PI', 'name' => 'Piauí'],
            ['country' => 'BR', 'code' => 'RJ', 'name' => 'Rio de Janeiro'],
            ['country' => 'BR', 'code' => 'RN', 'name' => 'Rio Grande do Norte'],
            ['country' => 'BR', 'code' => 'RS', 'name' => 'Rio Grande do Sul'],
            ['country' => 'BR', 'code' => 'RO', 'name' => 'Rondônia'],
            ['country' => 'BR', 'code' => 'RR', 'name' => 'Roraima'],
            ['country' => 'BR', 'code' => 'SC', 'name' => 'Santa Catarina'],
            ['country' => 'BR', 'code' => 'SP', 'name' => 'São Paulo'],
            ['country' => 'BR', 'code' => 'SE', 'name' => 'Sergipe'],
            ['country' => 'BR', 'code' => 'TO', 'name' => 'Tocantins'],
        ];
    }
}

if (!function_exists('checkout_default_delivery_methods')) {
    function checkout_default_delivery_methods(): array {
        return [
            [
                'code' => 'standard',
                'name' => 'Entrega padrão (5-7 dias)',
                'description' => 'Envio com rastreio para todos os Estados Unidos. Prazo estimado de 5 a 7 dias úteis.'
            ],
        ];
    }
}

if (!function_exists('checkout_get_countries')) {
    function checkout_get_countries(): array {
        $raw = setting_get('checkout_countries', null);
        $entries = [];
        if (is_array($raw)) {
            $entries = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $entries = $decoded;
            } else {
                foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $parts = array_map('trim', explode('|', $line));
                    if (count($parts) >= 2) {
                        $entries[] = ['code' => $parts[0], 'name' => $parts[1]];
                    }
                }
            }
        }
        $result = [];
        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $parts = array_map('trim', explode('|', $entry));
                $code = strtoupper($parts[0] ?? '');
                $name = $parts[1] ?? '';
            } else {
                $code = strtoupper(trim((string)($entry['code'] ?? '')));
                $name = trim((string)($entry['name'] ?? ''));
            }
            if ($code === '' || $name === '') {
                continue;
            }
            $result[$code] = ['code' => $code, 'name' => $name];
        }
        if (!$result) {
            $defaults = checkout_default_countries();
            foreach ($defaults as $item) {
                $code = strtoupper($item['code']);
                $result[$code] = ['code' => $code, 'name' => $item['name']];
            }
        }
        return array_values($result);
    }
}

if (!function_exists('checkout_get_states')) {
    function checkout_get_states(): array {
        $raw = setting_get('checkout_states', null);
        $entries = [];
        if (is_array($raw)) {
            $entries = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $entries = $decoded;
            } else {
                foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $parts = array_map('trim', explode('|', $line));
                    if (count($parts) === 3) {
                        $entries[] = ['country' => $parts[0], 'code' => $parts[1], 'name' => $parts[2]];
                    } elseif (count($parts) === 2) {
                        $entries[] = ['country' => 'US', 'code' => $parts[0], 'name' => $parts[1]];
                    }
                }
            }
        }

        $result = [];
        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $parts = array_map('trim', explode('|', $entry));
                if (count($parts) === 3) {
                    [$country, $code, $name] = $parts;
                } elseif (count($parts) === 2) {
                    $country = 'US';
                    [$code, $name] = $parts;
                } else {
                    continue;
                }
            } else {
                $country = strtoupper(trim((string)($entry['country'] ?? 'US')));
                $code    = strtoupper(trim((string)($entry['code'] ?? '')));
                $name    = trim((string)($entry['name'] ?? ''));
            }
            if ($code === '' || $name === '') {
                continue;
            }
            $country = strtoupper($country ?: 'US');
            $result[] = [
                'country' => $country,
                'code'    => $code,
                'name'    => $name,
            ];
        }
        if (!$result) {
            $result = checkout_default_states();
        }
        return $result;
    }
}

if (!function_exists('checkout_group_states')) {
    function checkout_group_states(): array {
        $states = checkout_get_states();
        $grouped = [];
        foreach ($states as $state) {
            $country = strtoupper(trim((string)($state['country'] ?? 'US')));
            if (!isset($grouped[$country])) {
                $grouped[$country] = [];
            }
            $grouped[$country][] = [
                'country' => $country,
                'code'    => strtoupper(trim((string)$state['code'] ?? '')),
                'name'    => trim((string)$state['name'] ?? ''),
            ];
        }
        return $grouped;
    }
}

if (!function_exists('checkout_get_states_by_country')) {
    function checkout_get_states_by_country(string $country): array {
        $country = strtoupper(trim($country));
        $grouped = checkout_group_states();
        return $grouped[$country] ?? [];
    }
}

if (!function_exists('checkout_default_cities')) {
    function checkout_default_cities(): array {
        return [
            ['country' => 'BR', 'state' => 'AC', 'name' => 'Rio Branco'],
            ['country' => 'BR', 'state' => 'AL', 'name' => 'Maceió'],
            ['country' => 'BR', 'state' => 'AP', 'name' => 'Macapá'],
            ['country' => 'BR', 'state' => 'AM', 'name' => 'Manaus'],
            ['country' => 'BR', 'state' => 'BA', 'name' => 'Salvador'],
            ['country' => 'BR', 'state' => 'CE', 'name' => 'Fortaleza'],
            ['country' => 'BR', 'state' => 'DF', 'name' => 'Brasília'],
            ['country' => 'BR', 'state' => 'ES', 'name' => 'Vitória'],
            ['country' => 'BR', 'state' => 'GO', 'name' => 'Goiânia'],
            ['country' => 'BR', 'state' => 'MA', 'name' => 'São Luís'],
            ['country' => 'BR', 'state' => 'MT', 'name' => 'Cuiabá'],
            ['country' => 'BR', 'state' => 'MS', 'name' => 'Campo Grande'],
            ['country' => 'BR', 'state' => 'MG', 'name' => 'Belo Horizonte'],
            ['country' => 'BR', 'state' => 'PA', 'name' => 'Belém'],
            ['country' => 'BR', 'state' => 'PB', 'name' => 'João Pessoa'],
            ['country' => 'BR', 'state' => 'PR', 'name' => 'Curitiba'],
            ['country' => 'BR', 'state' => 'PE', 'name' => 'Recife'],
            ['country' => 'BR', 'state' => 'PI', 'name' => 'Teresina'],
            ['country' => 'BR', 'state' => 'RJ', 'name' => 'Rio de Janeiro'],
            ['country' => 'BR', 'state' => 'RN', 'name' => 'Natal'],
            ['country' => 'BR', 'state' => 'RS', 'name' => 'Porto Alegre'],
            ['country' => 'BR', 'state' => 'RO', 'name' => 'Porto Velho'],
            ['country' => 'BR', 'state' => 'RR', 'name' => 'Boa Vista'],
            ['country' => 'BR', 'state' => 'SC', 'name' => 'Florianópolis'],
            ['country' => 'BR', 'state' => 'SP', 'name' => 'São Paulo'],
            ['country' => 'BR', 'state' => 'SE', 'name' => 'Aracaju'],
            ['country' => 'BR', 'state' => 'TO', 'name' => 'Palmas'],
        ];
    }
}

if (!function_exists('checkout_get_cities')) {
    function checkout_get_cities(): array {
        $raw = setting_get('checkout_cities', null);
        $entries = [];
        if (is_array($raw)) {
            $entries = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $entries = $decoded;
            } else {
                foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $parts = array_map('trim', explode('|', $line));
                    if (count($parts) >= 3) {
                        $entries[] = [
                            'country' => $parts[0],
                            'state'   => $parts[1],
                            'name'    => $parts[2],
                        ];
                    }
                }
            }
        }
        $result = [];
        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $parts = array_map('trim', explode('|', $entry));
                if (count($parts) < 3) {
                    continue;
                }
                [$country, $state, $name] = $parts;
            } else {
                $country = strtoupper(trim((string)($entry['country'] ?? '')));
                $state   = strtoupper(trim((string)($entry['state'] ?? '')));
                $name    = trim((string)($entry['name'] ?? ''));
            }
            if ($country === '' || $state === '' || $name === '') {
                continue;
            }
            $key = strtoupper($country).'::'.strtoupper($state);
            $result[$key][] = $name;
        }
        if (!$result) {
            foreach (checkout_default_cities() as $city) {
                $key = strtoupper($city['country']).'::'.strtoupper($city['state']);
                $result[$key][] = $city['name'];
            }
        }
        return $result;
    }
}

if (!function_exists('checkout_group_cities')) {
    function checkout_group_cities(): array {
        return checkout_get_cities();
    }
}

if (!function_exists('checkout_get_delivery_methods')) {
    function checkout_get_delivery_methods(): array {
        $raw = setting_get('checkout_delivery_methods', null);
        $entries = [];
        if (is_array($raw)) {
            $entries = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $entries = $decoded;
            } else {
                foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $parts = array_map('trim', explode('|', $line));
                    if (count($parts) >= 2) {
                        $entries[] = [
                            'code' => $parts[0],
                            'name' => $parts[1],
                            'description' => $parts[2] ?? ''
                        ];
                    }
                }
            }
        }

        $result = [];
        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $parts = array_map('trim', explode('|', $entry));
                $code = $parts[0] ?? '';
                $name = $parts[1] ?? '';
                $description = $parts[2] ?? '';
            } else {
                $code = trim((string)($entry['code'] ?? ''));
                $name = trim((string)($entry['name'] ?? ''));
                $description = trim((string)($entry['description'] ?? ''));
            }
            if ($name === '') {
                continue;
            }
            $slug = $code !== '' ? strtolower(preg_replace('/[^a-z0-9\-_]+/i', '-', $code)) : slugify($name);
            if ($slug === '') {
                continue;
            }
            $result[$slug] = [
                'code' => $slug,
                'name' => $name,
                'description' => $description,
            ];
        }
        if (!$result) {
            $defaults = checkout_default_delivery_methods();
            foreach ($defaults as $item) {
                $slug = strtolower(preg_replace('/[^a-z0-9\-_]+/i', '-', $item['code'] ?? slugify($item['name'] ?? '')));
                $result[$slug] = [
                    'code' => $slug ?: 'standard',
                    'name' => $item['name'] ?? 'Entrega padrão',
                    'description' => $item['description'] ?? '',
                ];
            }
        }
        return array_values($result);
    }
}

if (!function_exists('checkout_find_delivery_method')) {
    function checkout_find_delivery_method(string $code): ?array {
        $code = strtolower(trim($code));
        foreach (checkout_get_delivery_methods() as $method) {
            if (strtolower($method['code']) === $code) {
                return $method;
            }
        }
        return null;
    }
}

/* =========================================================================
   Helper para carregar nome/contatos exibidos na loja (com fallback)
   ========================================================================= */
if (!function_exists('store_info')) {
    function store_info() {
        $cfg = cfg();
        return [
            'name'   => setting_get('store_name',   $cfg['store']['name']   ?? 'Get Power Research'),
            'email'  => setting_get('store_email',  $cfg['store']['support_email'] ?? 'contato@example.com'),
            'phone'  => setting_get('store_phone',  $cfg['store']['phone']  ?? '(00) 00000-0000'),
            'addr'   => setting_get('store_address',$cfg['store']['address']?? 'Endereço não configurado'),
            'logo'   => get_logo_path(),
            'currency' => $cfg['store']['currency'] ?? 'BRL',
        ];
    }
}
if (!function_exists('store_hours_config')) {
    function store_hours_config(): array {
        $defaults = [
            'enabled'    => false,
            'label'      => 'Seg a Sex: 09h às 18h (BRT)',
            'open_time'  => '09:00',
            'close_time' => '18:00',
            'timezone'   => 'America/Sao_Paulo',
        ];
        return [
            'enabled'    => (bool)setting_get('store_hours_enabled', $defaults['enabled'] ? '1' : '0'),
            'label'      => setting_get('store_hours_label', $defaults['label']),
            'open_time'  => setting_get('store_hours_open_time', $defaults['open_time']),
            'close_time' => setting_get('store_hours_close_time', $defaults['close_time']),
            'timezone'   => setting_get('store_hours_timezone', $defaults['timezone']),
        ];
    }
}
if (!function_exists('store_hours_status')) {
    function store_hours_status(): array {
        $config = store_hours_config();
        if (empty($config['enabled'])) {
            return [
                'enabled' => false,
                'label' => '',
                'is_open' => false,
                'status_text' => '',
                'timezone' => $config['timezone'] ?? 'UTC',
                'open_time' => $config['open_time'] ?? '00:00',
                'close_time' => $config['close_time'] ?? '00:00',
                'next_change' => null,
            ];
        }
        $timezone = $config['timezone'] ?: 'UTC';
        try {
            $tz = new DateTimeZone($timezone);
        } catch (Throwable $e) {
            $tz = new DateTimeZone('UTC');
            $timezone = 'UTC';
        }
        $now = new DateTimeImmutable('now', $tz);

        $openTime = preg_match('/^\d{2}:\d{2}$/', $config['open_time'] ?? '') ? $config['open_time'] : '00:00';
        $closeTime = preg_match('/^\d{2}:\d{2}$/', $config['close_time'] ?? '') ? $config['close_time'] : '23:59';

        [$openHour, $openMinute] = array_map('intval', explode(':', $openTime));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', $closeTime));

        $openDate = $now->setTime($openHour, $openMinute, 0);
        $closeDate = $now->setTime($closeHour, $closeMinute, 0);

        if ($closeDate <= $openDate) {
            // período que cruza a meia-noite
            if ($now < $openDate) {
                $openDate = $openDate->modify('-1 day');
            }
            $closeDate = $closeDate->modify('+1 day');
        }

        $isOpen = ($now >= $openDate && $now < $closeDate);
        $statusText = $isOpen ? 'Aberto agora' : 'Fechado agora';

        return [
            'enabled' => true,
            'label' => trim((string)$config['label']) !== '' ? (string)$config['label'] : 'Seg a Sex: 09h às 18h (BRT)',
            'is_open' => $isOpen,
            'status_text' => $statusText,
            'timezone' => $timezone,
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'next_change' => $isOpen ? $closeDate : $openDate,
        ];
    }
}
if (!function_exists('find_logo_path')) {
    function find_logo_path(): ?string {
        $cfgLogo = function_exists('setting_get') ? setting_get('store_logo_url') : null;
        if ($cfgLogo) {
            return ltrim((string)$cfgLogo, '/');
        }
        $candidates = [
            'storage/logo/logo.png',
            'storage/logo/logo.jpg',
            'storage/logo/logo.jpeg',
            'storage/logo/logo.webp',
            'assets/logo.png'
        ];
        foreach ($candidates as $c) {
            if (file_exists(__DIR__ . '/../' . $c)) {
                return $c;
            }
        }
        return null;
    }
}

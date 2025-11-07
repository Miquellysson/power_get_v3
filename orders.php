<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/utils.php';
require __DIR__.'/admin_layout.php';

if (!function_exists('require_admin')){
  function require_admin(){
    if (empty($_SESSION['admin_id'])) {
      header('Location: admin.php?route=login'); exit;
    }
  }
}
if (!function_exists('csrf_token')){
  function csrf_token(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
}
if (!function_exists('csrf_check')){
  function csrf_check($t){ $t=(string)$t; return !empty($t) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }
}
if (!function_exists('sanitize_string')){
  function sanitize_string($s,$max=255){ $s=trim((string)$s); if (strlen($s)>$max) $s=substr($s,0,$max); return $s; }
}
if (!function_exists('validate_email')){
  function validate_email($e){ return (bool)filter_var($e,FILTER_VALIDATE_EMAIL); }
}
$pdo = db();
require_admin();

$action = $_GET['action'] ?? 'list';
$canManageOrders = admin_can('manage_orders');
if ($action === 'update_status' && !$canManageOrders) {
  require_admin_capability('manage_orders');
}
$isSuperAdmin = is_super_admin();

function orders_flash(string $type, string $message): void {
  $_SESSION['orders_flash'] = ['type' => $type, 'message' => $message];
}

function orders_take_flash(): ?array {
  $flash = $_SESSION['orders_flash'] ?? null;
  unset($_SESSION['orders_flash']);
  return $flash;
}

function orders_table_columns(PDO $pdo, string $table): array {
  static $cache = [];
  if (isset($cache[$table])) {
    return $cache[$table];
  }
  if (!preg_match('/^[a-z0-9_]+$/i', $table)) {
    return $cache[$table] = [];
  }
  try {
    $stmt = $pdo->query('SHOW COLUMNS FROM `'.$table.'`');
    $cols = [];
    if ($stmt) {
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['Field'])) {
          $cols[] = $row['Field'];
        }
      }
    }
    return $cache[$table] = $cols;
  } catch (Throwable $e) {
    return $cache[$table] = [];
  }
}

function status_badge($s){
  if ($s==='paid') return '<span class="badge ok">Pago</span>';
  if ($s==='pending') return '<span class="badge warn">Pendente</span>';
  if ($s==='shipped') return '<span class="badge ok">Enviado</span>';
  if ($s==='canceled') return '<span class="badge danger">Cancelado</span>';
  return '<span class="badge">'.sanitize_html($s).'</span>';
}

if ($action === 'export') {
  if (!$isSuperAdmin) {
    require_super_admin();
  }
  $orderColumns = orders_table_columns($pdo, 'orders');
  if (!$orderColumns) {
    orders_flash('error', 'Não foi possível localizar as colunas da tabela de pedidos.');
    header('Location: orders.php');
    exit;
  }
  $quotedOrderColumns = array_map(fn($col) => '`'.$col.'`', $orderColumns);
  $ordersStmt = $pdo->query('SELECT '.implode(',', $quotedOrderColumns).' FROM orders ORDER BY id DESC');
  $ordersList = $ordersStmt ? $ordersStmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $customerIds = [];
  foreach ($ordersList as $row) {
    if (isset($row['customer_id'])) {
      $customerIds[] = (int)$row['customer_id'];
    }
  }
  $customerIds = array_values(array_unique(array_filter($customerIds)));
  $customersList = [];
  if ($customerIds) {
    $customerColumns = orders_table_columns($pdo, 'customers');
    if ($customerColumns) {
      $quotedCustomerColumns = array_map(fn($col) => '`'.$col.'`', $customerColumns);
      $in = implode(',', array_fill(0, count($customerIds), '?'));
      $custStmt = $pdo->prepare('SELECT '.implode(',', $quotedCustomerColumns).' FROM customers WHERE id IN ('.$in.')');
      $custStmt->execute($customerIds);
      $customersList = $custStmt->fetchAll(PDO::FETCH_ASSOC);
    }
  }
  $payload = [
    'generated_at' => date('c'),
    'orders_count' => count($ordersList),
    'customers_count' => count($customersList),
    'orders' => $ordersList,
    'customers' => $customersList,
  ];
  header('Content-Type: application/json; charset=utf-8');
  header('Content-Disposition: attachment; filename="pedidos-'.date('Ymd_His').'.json"');
  echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$isSuperAdmin) {
    require_super_admin();
  }
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('CSRF');
  }
  if (!isset($_FILES['orders_file']) || !is_uploaded_file($_FILES['orders_file']['tmp_name']) || $_FILES['orders_file']['error'] !== UPLOAD_ERR_OK) {
    orders_flash('error', 'Envie um arquivo JSON válido exportado anteriormente.');
    header('Location: orders.php?action=import');
    exit;
  }
  $raw = file_get_contents($_FILES['orders_file']['tmp_name']);
  if ($raw === false || trim($raw) === '') {
    orders_flash('error', 'Arquivo vazio ou ilegível.');
    header('Location: orders.php?action=import');
    exit;
  }
  $payload = json_decode($raw, true);
  if (!is_array($payload)) {
    orders_flash('error', 'Conteúdo inválido. Verifique se o arquivo é JSON.');
    header('Location: orders.php?action=import');
    exit;
  }
  $ordersInput = $payload['orders'] ?? [];
  $customersInput = $payload['customers'] ?? [];
  if (!is_array($ordersInput)) {
    orders_flash('error', 'Estrutura de pedidos inválida.');
    header('Location: orders.php?action=import');
    exit;
  }
  if ($customersInput !== null && !is_array($customersInput)) {
    orders_flash('error', 'Estrutura de clientes inválida.');
    header('Location: orders.php?action=import');
    exit;
  }

  $customerColumns = orders_table_columns($pdo, 'customers');
  $orderColumns = orders_table_columns($pdo, 'orders');
  if (!$orderColumns) {
    orders_flash('error', 'Tabela de pedidos indisponível.');
    header('Location: orders.php');
    exit;
  }
  if ($customerColumns && !in_array('id', $customerColumns, true)) {
    $customerColumns = [];
  }
  if ($orderColumns && !in_array('id', $orderColumns, true)) {
    $orderColumns = [];
  }

  $customersImported = 0;
  $ordersImported = 0;

  try {
    $pdo->beginTransaction();

    if ($customerColumns && $customersInput) {
      $customerColsQuoted = array_map(fn($col) => '`'.$col.'`', $customerColumns);
      $customerPlaceholders = implode(',', array_fill(0, count($customerColumns), '?'));
      $customerUpdates = [];
      foreach ($customerColumns as $col) {
        if ($col === 'id') {
          continue;
        }
        $customerUpdates[] = '`'.$col.'`=VALUES(`'.$col.'`)';
      }
      if (!$customerUpdates) {
        $customerUpdates[] = '`id`=`id`';
      }
      $customerSql = 'INSERT INTO customers ('.implode(',', $customerColsQuoted).') VALUES ('.$customerPlaceholders.') ON DUPLICATE KEY UPDATE '.implode(',', $customerUpdates);
      $customerStmt = $pdo->prepare($customerSql);
      foreach ($customersInput as $row) {
        if (!is_array($row) || !array_key_exists('id', $row)) {
          continue;
        }
        $values = [];
        foreach ($customerColumns as $col) {
          $values[] = array_key_exists($col, $row) ? $row[$col] : null;
        }
        $customerStmt->execute($values);
        $customersImported++;
      }
    }

    if ($orderColumns && $ordersInput) {
      $orderColsQuoted = array_map(fn($col) => '`'.$col.'`', $orderColumns);
      $orderPlaceholders = implode(',', array_fill(0, count($orderColumns), '?'));
      $orderUpdates = [];
      foreach ($orderColumns as $col) {
        if ($col === 'id') {
          continue;
        }
        $orderUpdates[] = '`'.$col.'`=VALUES(`'.$col.'`)';
      }
      if (!$orderUpdates) {
        $orderUpdates[] = '`id`=`id`';
      }
      $orderSql = 'INSERT INTO orders ('.implode(',', $orderColsQuoted).') VALUES ('.$orderPlaceholders.') ON DUPLICATE KEY UPDATE '.implode(',', $orderUpdates);
      $orderStmt = $pdo->prepare($orderSql);
      foreach ($ordersInput as $row) {
        if (!is_array($row) || !array_key_exists('id', $row)) {
          continue;
        }
        $values = [];
        foreach ($orderColumns as $col) {
          $values[] = array_key_exists($col, $row) ? $row[$col] : null;
        }
        $orderStmt->execute($values);
        $ordersImported++;
      }
    }

    $pdo->commit();
    orders_flash('success', 'Importação concluída: '.$ordersImported.' pedido(s) e '.$customersImported.' cliente(s) processados.');
    header('Location: orders.php');
    exit;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    orders_flash('error', 'Falha ao importar pedidos: '.$e->getMessage());
    header('Location: orders.php?action=import');
    exit;
  }
}

if ($action === 'import') {
  if (!$isSuperAdmin) {
    require_super_admin();
  }
  admin_header('Importar pedidos');
  echo '<div class="card"><div class="card-title">Importar pedidos</div><div class="card-body space-y-4">';
  $flash = orders_take_flash();
  if ($flash) {
    $class = $flash['type'] === 'error' ? 'alert alert-error' : ($flash['type'] === 'warning' ? 'alert alert-warning' : 'alert alert-success');
    $icon = $flash['type'] === 'error' ? 'fa-circle-exclamation' : ($flash['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check');
    echo '<div class="'.$class.'"><i class="fa-solid '.$icon.' mr-2"></i>'.sanitize_html($flash['message']).'</div>';
  }
  echo '<p class="text-sm text-gray-600">Envie o arquivo JSON gerado pela opção <strong>Exportar pedidos</strong>. A importação realiza um upsert (atualiza ou cria) de clientes e pedidos com base no ID.</p>';
  echo '<form method="post" enctype="multipart/form-data" class="space-y-3">';
  echo '  <input type="hidden" name="csrf" value="'.csrf_token().'">';
  echo '  <input class="input w-full" type="file" name="orders_file" accept=".json,application/json" required>';
  echo '  <div class="toolbar-actions">';
  echo '    <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-file-import mr-2"></i>Importar agora</button>';
  echo '    <a class="btn btn-ghost btn-sm" href="orders.php"><i class="fa-solid fa-arrow-left mr-2"></i>Voltar</a>';
  echo '  </div>';
  echo '</form>';
  echo '</div></div>';
  admin_footer(); exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  require_super_admin();
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('CSRF');
  }
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0) {
    $del = $pdo->prepare('DELETE FROM orders WHERE id=?');
    $del->execute([$id]);
    orders_flash('success', 'Pedido #'.$id.' excluído com sucesso.');
  } else {
    orders_flash('error', 'Pedido inválido.');
  }
  header('Location: orders.php');
  exit;
}

if ($action==='view') {
  $id=(int)($_GET['id'] ?? 0);
  $st=$pdo->prepare("SELECT o.*, c.first_name, c.last_name, c.name AS customer, c.email, c.phone, c.address, c.address2, c.city, c.state, c.zipcode, c.country FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.id=?");
  $st->execute([$id]);
  $o=$st->fetch();
  if (!$o){ header('Location: orders.php'); exit; }
  $displayName = trim(($o['first_name'] ?? '').' '.($o['last_name'] ?? '')) ?: ($o['customer'] ?? '');
  $addressParts = [];
  if (!empty($o['address'])) {
    $addressParts[] = sanitize_html($o['address']);
  }
  if (!empty($o['address2'])) {
    $addressParts[] = sanitize_html($o['address2']);
  }
  $cityStateZip = trim($o['city'] ?? '');
  $stateValue = trim($o['state'] ?? '');
  $zipValue = trim($o['zipcode'] ?? '');
  if ($stateValue !== '') {
    $cityStateZip = $cityStateZip ? $cityStateZip.' / '.$stateValue : $stateValue;
  }
  if ($zipValue !== '') {
    $cityStateZip = $cityStateZip ? $cityStateZip.' — '.$zipValue : $zipValue;
  }
  if ($cityStateZip !== '') {
    $addressParts[] = sanitize_html($cityStateZip);
  }
  if (!empty($o['country'])) {
    $addressParts[] = sanitize_html($o['country']);
  }
  $addressHtml = $addressParts ? implode('<br>', $addressParts) : '—';
  $items = json_decode($o['items_json'] ?? '[]', true) ?: [];
  $orderCurrency = strtoupper($o['currency'] ?? (cfg()['store']['currency'] ?? 'USD'));
  admin_header('Pedido #'.$id);

  echo '<div class="flex flex-wrap items-center justify-between gap-2 mb-4">';
  echo '  <a class="btn btn-ghost btn-sm" href="orders.php"><i class="fa-solid fa-arrow-left mr-1"></i>Voltar</a>';
  if ($isSuperAdmin) {
    echo '  <form method="post" action="orders.php?action=delete&id='.$id.'" style="margin:0;" onsubmit="return confirm(\'Confirma excluir o pedido #'.$id.'? Essa ação não pode ser desfeita.\');">';
    echo '    <input type="hidden" name="csrf" value="'.csrf_token().'">';
    echo '    <button class="btn btn-ghost btn-sm text-red-600 border border-red-200" type="submit"><i class="fa-solid fa-trash-can mr-1"></i>Excluir pedido</button>';
    echo '  </form>';
  }
  echo '</div>';

  echo '<div class="grid md:grid-cols-3 gap-3">';
  echo '<div class="card md:col-span-2"><div class="card-title">Itens do pedido</div><div class="p-3 overflow-x-auto">';
  echo '<table class="table"><thead><tr><th>SKU</th><th>Produto</th><th>Qtd</th><th>Preço</th><th>Total</th></tr></thead><tbody>';
  foreach($items as $it){
    $itemCurrency = $it['currency'] ?? $orderCurrency;
    $priceValue = (float)($it['price'] ?? 0);
    $line = $priceValue * (int)$it['qty'];
    echo '<tr>';
    echo '<td>'.sanitize_html($it['sku'] ?? '').'</td>';
    echo '<td>'.sanitize_html($it['name']).'</td>';
    echo '<td>'.(int)$it['qty'].'</td>';
    echo '<td>'.format_currency($priceValue, $itemCurrency).'</td>';
    echo '<td>'.format_currency($line, $itemCurrency).'</td>';
    echo '</tr>';
  }
  echo '</tbody></table></div></div>';

  $deliveryLabel = '';
  $deliveryDetailsText = trim((string)($o['delivery_method_details'] ?? ''));

  echo '<div class="card"><div class="card-title">Resumo</div><div class="p-3">';
  echo '<div class="mb-2">Subtotal: <strong>'.format_currency((float)$o['subtotal'], $orderCurrency).'</strong></div>';
  echo '<div class="mb-2">Frete: <strong>'.format_currency((float)$o['shipping_cost'], $orderCurrency).'</strong></div>';
  echo '<div class="mb-2">Total: <strong>'.format_currency((float)$o['total'], $orderCurrency).'</strong></div>';
  echo '<div class="mb-2">Pagamento: <strong>'.sanitize_html($o['payment_method']).'</strong></div>';
  if (!empty($o['payment_ref'])) echo '<div class="mb-2">Ref: <a class="text-blue-600 underline" href="'.sanitize_html($o['payment_ref']).'" target="_blank">abrir</a></div>';
  echo '<div class="mb-2">Status: '.status_badge($o['status']).'</div>';
  if (!empty($o['delivery_method_label']) || !empty($o['delivery_method_code'])) {
    $deliveryLabel = trim((string)($o['delivery_method_label'] ?? '')) ?: trim((string)($o['delivery_method_code'] ?? ''));
    echo '<div class="mb-2">Método de entrega: <strong>'.sanitize_html($deliveryLabel).'</strong></div>';
  }
  if ($deliveryDetailsText !== '') {
    echo '<div class="mb-2 text-xs text-gray-600">'.sanitize_html($deliveryDetailsText).'</div>';
  }
  if ($canManageOrders) {
    echo '<form class="mt-3" method="post" action="orders.php?action=update_status&id='.$id.'"><input type="hidden" name="csrf" value="'.csrf_token().'"><select class="select" name="status" required><option value="pending" '.($o['status']==='pending'?'selected':'').'>Pendente</option><option value="paid" '.($o['status']==='paid'?'selected':'').'>Pago</option><option value="shipped" '.($o['status']==='shipped'?'selected':'').'>Enviado</option><option value="canceled" '.($o['status']==='canceled'?'selected':'').'>Cancelado</option></select><button class="btn btn-alt btn-sm ml-2" type="submit"><i class="fa-solid fa-rotate"></i> Atualizar</button></form>';
  } else {
    echo '<div class="text-xs text-gray-500">Você não tem permissão para alterar o status.</div>';
  }
  if (!empty($o['zelle_receipt'])){
    echo '<div class="mt-3"><a class="btn btn-alt btn-sm" href="'.sanitize_html($o['zelle_receipt']).'" target="_blank"><i class="fa-solid fa-file"></i> Ver comprovante</a></div>';
  }
  echo '</div></div>';

  $checkoutFields = [
    'Nome' => $o['first_name'] ?? '',
    'Sobrenome' => $o['last_name'] ?? '',
    'E-mail' => $o['email'] ?? '',
    'Telefone' => $o['phone'] ?? '',
    'Rua e número' => $o['address'] ?? '',
    'Complemento' => $o['address2'] ?? '',
    'Cidade' => $o['city'] ?? '',
    'Estado' => $o['state'] ?? '',
    'CEP' => $o['zipcode'] ?? '',
    'País' => $o['country'] ?? '',
    'Método de entrega' => $deliveryLabel,
    'Detalhes da entrega' => $deliveryDetailsText,
  ];

  echo '<div class="card md:col-span-3"><div class="card-title">Cliente</div><div class="p-3">';
  echo '<div class="grid md:grid-cols-2 gap-3 text-sm">';
  foreach ($checkoutFields as $label => $value) {
    $valueStr = trim((string)$value);
    if ($label === 'E-mail' && $valueStr !== '') {
      $valueHtml = '<a class="text-blue-600 underline" href="mailto:'.sanitize_html($valueStr).'">'.sanitize_html($valueStr).'</a>';
    } else {
      $valueHtml = $valueStr !== '' ? sanitize_html($valueStr) : '—';
    }
    echo '<div><div class="text-xs uppercase tracking-wide text-gray-500">'.sanitize_html($label).'</div><div class="font-medium text-gray-900">'.$valueHtml.'</div></div>';
  }
  echo '</div>';
  echo '</div></div>';

  echo '</div>';
  admin_footer(); exit;
}

if ($action==='update_status' && $_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  $id=(int)($_GET['id'] ?? 0);
  $status = sanitize_string($_POST['status'] ?? '');
  $st=$pdo->prepare("UPDATE orders SET status=? WHERE id=?");
  $st->execute([$status,$id]);
  header('Location: orders.php?action=view&id='.$id); exit;
}

// listagem
admin_header('Pedidos');
if (!$canManageOrders) {
  echo '<div class="alert alert-warning mx-auto max-w-4xl mb-4"><i class="fa-solid fa-circle-info mr-2"></i>Alterações de status disponíveis apenas para administradores autorizados.</div>';
}
$flash = orders_take_flash();
if ($flash) {
  $class = $flash['type'] === 'error' ? 'alert alert-error' : ($flash['type'] === 'warning' ? 'alert alert-warning' : 'alert alert-success');
  $icon = $flash['type'] === 'error' ? 'fa-circle-exclamation' : ($flash['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check');
  echo '<div class="'.$class.' mx-auto max-w-4xl mb-4"><i class="fa-solid '.$icon.' mr-2"></i>'.sanitize_html($flash['message']).'</div>';
}
$q = trim((string)($_GET['q'] ?? ''));
$w=' WHERE 1=1 '; $p=[];
if ($q!==''){
  $w .= " AND (c.name LIKE ? OR o.id = ? ) ";
  $p = ["%$q%", (int)$q];
}
$sql="SELECT o.id,o.total,o.currency,o.status,o.created_at,c.name AS customer_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id $w ORDER BY o.id DESC LIMIT 200";
$st=$pdo->prepare($sql); $st->execute($p);

echo '<div class="card">';
echo '<div class="card-title">Pedidos</div>';
echo '<div class="card-toolbar">';
echo '  <form method="get" class="search-form">';
echo '    <input class="input" name="q" value="'.sanitize_html($q).'" placeholder="Buscar por cliente ou #id">';
echo '    <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-magnifying-glass mr-2"></i>Buscar</button>';
echo '  </form>';
echo '  <div class="toolbar-actions">';
if ($isSuperAdmin) {
  echo '    <a class="btn btn-alt btn-sm" href="orders.php?action=export"><i class="fa-solid fa-file-arrow-down mr-2"></i>Exportar pedidos</a>';
  echo '    <a class="btn btn-ghost btn-sm" href="orders.php?action=import"><i class="fa-solid fa-file-arrow-up mr-2"></i>Importar</a>';
}
echo '  </div>';
echo '</div>';
echo '<div class="p-3 overflow-x-auto"><table class="table"><thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Status</th><th>Quando</th><th></th></tr></thead><tbody>';
foreach($st as $r){
  $statusClass = 'status-'.$r['status'];
  echo '<tr class="'.sanitize_html($statusClass).'">';
  echo '<td>#'.(int)$r['id'].'</td>';
  echo '<td>'.sanitize_html($r['customer_name']).'</td>';
  $rowCurrency = strtoupper($r['currency'] ?? (cfg()['store']['currency'] ?? 'USD'));
  echo '<td>'.format_currency((float)$r['total'], $rowCurrency).'</td>';
  echo '<td>'.status_badge($r['status']).'</td>';
  echo '<td>'.sanitize_html($r['created_at'] ?? '').'</td>';
  echo '<td><div class="action-buttons"><a class="btn btn-alt btn-sm" href="orders.php?action=view&id='.(int)$r['id'].'"><i class="fa-solid fa-eye"></i> Ver</a>';
  if ($isSuperAdmin) {
    echo '<form method="post" action="orders.php?action=delete&id='.(int)$r['id'].'" style="margin:0;" onsubmit="return confirm(\'Excluir o pedido #'.(int)$r['id'].'?\');">';
    echo '<input type="hidden" name="csrf" value="'.csrf_token().'">';
    echo '<button class="btn btn-ghost btn-sm text-red-600" type="submit"><i class="fa-solid fa-trash-can"></i></button>';
    echo '</form>';
  }
  echo '</div></td>';
  echo '</tr>';
}
echo '</tbody></table></div></div>';

admin_footer();

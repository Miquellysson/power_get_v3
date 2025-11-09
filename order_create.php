<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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

require_admin();
require_admin_capability('manage_orders');

$pdo = db();
$cfg = cfg();
$storeCurrency = $cfg['store']['currency'] ?? 'USD';

$productsStmt = $pdo->query("SELECT id, name, price, currency, shipping_cost, sku FROM products WHERE active = 1 ORDER BY name ASC");
$productsList = $productsStmt ? $productsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$paymentMethodsList = load_payment_methods($pdo, $cfg);
if (!$paymentMethodsList) {
    $paymentMethodsList = [
        ['code' => 'manual', 'name' => 'Manual', 'settings' => []],
    ];
}

$countryOptions = checkout_get_countries();
$defaultCountry = setting_get('checkout_default_country', $countryOptions[0]['code'] ?? 'US');
$defaultCountry = strtoupper($defaultCountry ?: 'US');

$errors = [];
$old = $_POST ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize_string($_POST['first_name'] ?? '', 120);
    $lastName = sanitize_string($_POST['last_name'] ?? '', 120);
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = sanitize_string($_POST['phone'] ?? '', 60);
    $address1 = sanitize_string($_POST['address1'] ?? '', 255);
    $address2 = sanitize_string($_POST['address2'] ?? '', 255);
    $cityInput = sanitize_string($_POST['city'] ?? '', 120);
    $stateInput = sanitize_string($_POST['state'] ?? '', 80);
    $zipcode = sanitize_string($_POST['zipcode'] ?? '', 20);
    $country = strtoupper(trim((string)($_POST['country'] ?? $defaultCountry)));
    if ($country === '') {
        $country = $defaultCountry;
    }
    $paymentMethodSelected = trim((string)($_POST['payment_method'] ?? ''));
    $orderStatus = trim((string)($_POST['order_status'] ?? 'pending'));
    $paymentRef = sanitize_string($_POST['payment_ref'] ?? '', 255);
    $notes = pm_clip_text($_POST['order_notes'] ?? '', 1000);

    if ($firstName === '' || $lastName === '') {
        $errors[] = 'Informe o nome e sobrenome do cliente.';
    }
    if ($email === '' || !validate_email($email)) {
        $errors[] = 'Informe um e-mail válido.';
    }
    if ($phone === '') {
        $errors[] = 'Informe o telefone do cliente.';
    }
    if ($address1 === '' || $cityInput === '' || $stateInput === '' || $zipcode === '') {
        $errors[] = 'Preencha endereço completo.';
    }

    $itemsInput = $_POST['items'] ?? [];
    $preparedItems = [];
    if (is_array($itemsInput)) {
        foreach ($itemsInput as $row) {
            $productId = isset($row['product_id']) ? (int)$row['product_id'] : 0;
            $qty = isset($row['qty']) ? (int)$row['qty'] : 0;
            if ($productId > 0 && $qty > 0) {
                $preparedItems[] = ['product_id' => $productId, 'qty' => $qty];
            }
        }
    }
    if (!$preparedItems) {
        $errors[] = 'Adicione pelo menos um item ao pedido.';
    }

    $validPaymentCodes = array_column($paymentMethodsList, 'name', 'code');
    if ($paymentMethodSelected === '' || !isset($validPaymentCodes[$paymentMethodSelected])) {
        $paymentMethodSelected = 'manual';
    }

    $statusOptions = ['pending', 'paid', 'shipped', 'canceled'];
    if (!in_array($orderStatus, $statusOptions, true)) {
        $orderStatus = 'pending';
    }
    $paymentStatus = ($orderStatus === 'paid') ? 'paid' : 'pending';

    if (!$errors) {
        $productIds = array_unique(array_column($preparedItems, 'product_id'));
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $pdo->prepare("SELECT id, name, price, currency, shipping_cost, sku FROM products WHERE id IN ($placeholders) AND active=1");
        $stmt->execute($productIds);
        $productsMap = [];
        foreach ($stmt as $prod) {
            $productsMap[(int)$prod['id']] = $prod;
        }
        $itemsPayload = [];
        $subtotal = 0.0;
        $shippingTotal = 0.0;
        $orderCurrency = null;
        $costManagementActive = cost_management_enabled();
        $costAccumulator = 0.0;
        $profitAccumulator = 0.0;
        foreach ($preparedItems as $row) {
            $productId = $row['product_id'];
            if (!isset($productsMap[$productId])) {
                $errors[] = 'Produto inválido selecionado.';
                break;
            }
            $product = $productsMap[$productId];
            $qty = $row['qty'];
            $price = (float)$product['price'];
            $shippingCost = isset($product['shipping_cost']) ? (float)$product['shipping_cost'] : 0.0;
            $currency = strtoupper($product['currency'] ?? $storeCurrency);
            if ($orderCurrency === null) {
                $orderCurrency = $currency;
            } elseif ($orderCurrency !== $currency) {
                $errors[] = 'Itens com moedas diferentes não são suportados no mesmo pedido.';
                break;
            }
            $subtotal += $price * $qty;
            $shippingTotal += $shippingCost * $qty;
            $costUnit = null;
            $profitUnit = null;
            if ($costManagementActive) {
                $costUnit = $product['cost_price'] !== null ? (float)$product['cost_price'] : null;
                $profitOverride = $product['profit_amount'] !== null ? (float)$product['profit_amount'] : null;
                $profitUnit = product_profit_value($price, $costUnit, $profitOverride);
            }
            $itemsPayload[] = [
                'id' => (int)$product['id'],
                'name' => $product['name'],
                'price' => $price,
                'qty' => $qty,
                'sku' => $product['sku'] ?? '',
                'shipping_cost' => $shippingCost,
                'currency' => $currency,
                'cost_price' => $costUnit,
                'profit_value' => $profitUnit,
            ];
            if ($costManagementActive && $costUnit !== null) {
                $costAccumulator += $costUnit * $qty;
            }
            if ($costManagementActive && $profitUnit !== null) {
                $profitAccumulator += $profitUnit * $qty;
            }
        }
        if (!$errors) {
            $total = $subtotal + $shippingTotal;
            if ($orderCurrency === null) {
                $orderCurrency = $storeCurrency;
            }
            $costTotal = $costManagementActive ? $costAccumulator : 0.0;
            $profitTotal = $costManagementActive ? $profitAccumulator : 0.0;

            try {
                $pdo->beginTransaction();

                $fullName = trim($firstName.' '.$lastName);
                $customerStmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
                $customerStmt->execute([$email]);
                $customerId = (int)($customerStmt->fetchColumn() ?: 0);
                if ($customerId > 0) {
                    $updateCustomer = $pdo->prepare("UPDATE customers SET first_name=?, last_name=?, name=?, phone=?, address=?, address2=?, city=?, state=?, zipcode=?, country=?, updated_at=NOW() WHERE id=?");
                    $updateCustomer->execute([$firstName,$lastName,$fullName,$phone,$address1,$address2,$cityInput,$stateInput,$zipcode,$country,$customerId]);
                } else {
                    $insertCustomer = $pdo->prepare("INSERT INTO customers(first_name,last_name,name,email,phone,address,address2,city,state,zipcode,country,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
                    $insertCustomer->execute([$firstName,$lastName,$fullName,$email,$phone,$address1,$address2,$cityInput,$stateInput,$zipcode,$country]);
                    $customerId = (int)$pdo->lastInsertId();
                }

                $hasTrack = false;
                $hasDeliveryCols = false;
                try {
                    $chk = $pdo->query("SHOW COLUMNS FROM orders LIKE 'track_token'");
                    $hasTrack = (bool)($chk && $chk->fetch());
                } catch (Throwable $e) {}
                try {
                    $chkDelivery = $pdo->query("SHOW COLUMNS FROM orders LIKE 'delivery_method_code'");
                    $hasDeliveryCols = (bool)($chkDelivery && $chkDelivery->fetch());
                } catch (Throwable $e) {}

                $orderColumns = ['customer_id','items_json','subtotal','shipping_cost','total','cost_total','profit_total','currency','payment_method','payment_ref','status','payment_status','notes','admin_viewed'];
                $orderValues = [
                    $customerId,
                    json_encode($itemsPayload, JSON_UNESCAPED_UNICODE),
                    $subtotal,
                    $shippingTotal,
                    $total,
                    $costTotal,
                    $profitTotal,
                    $orderCurrency,
                    $paymentMethodSelected,
                    $paymentRef,
                    $orderStatus,
                    $paymentStatus,
                    $notes,
                    1,
                ];

                if ($hasDeliveryCols) {
                    $orderColumns[] = 'delivery_method_code';
                    $orderColumns[] = 'delivery_method_label';
                    $orderColumns[] = 'delivery_method_details';
                    $orderValues[] = '';
                    $orderValues[] = '';
                    $orderValues[] = '';
                }

                if ($hasTrack) {
                    $orderColumns[] = 'track_token';
                    $orderValues[] = bin2hex(random_bytes(16));
                }

                $placeholders = implode(',', array_fill(0, count($orderColumns), '?'));
                $ordersSql = 'INSERT INTO orders('.implode(',', $orderColumns).') VALUES('.$placeholders.')';
                $insertOrder = $pdo->prepare($ordersSql);
                $insertOrder->execute($orderValues);
                $orderId = (int)$pdo->lastInsertId();
                order_sync_items_table($pdo, $orderId, $itemsPayload);

                $pdo->commit();
                $_SESSION['orders_flash'] = ['type' => 'success', 'message' => 'Pedido #'.$orderId.' criado com sucesso.'];
                header('Location: orders.php?action=view&id='.$orderId);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Erro ao salvar pedido: '.$e->getMessage();
            }
        }
    }
}

if (empty($old['items']) || !is_array($old['items'])) {
    $old['items'] = [['product_id' => '', 'qty' => 1]];
}
if (empty($old['country'])) {
    $old['country'] = $defaultCountry;
}

admin_header('Novo Pedido');
?>
<div class="card p-6 space-y-6">
  <div class="card-title flex items-center gap-2">
    <i class="fa-solid fa-file-circle-plus text-brand-600"></i>
    <span>Criar pedido manualmente</span>
  </div>
  <?php if ($errors): ?>
    <div class="alert alert-error">
      <i class="fa-solid fa-circle-exclamation"></i>
      <span><?= sanitize_html(implode(' ', $errors)); ?></span>
    </div>
  <?php endif; ?>
  <form method="post" class="space-y-6">
    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
    <section class="space-y-4">
      <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dados do cliente</h3>
      <div class="grid md:grid-cols-2 gap-3">
        <input class="input" name="first_name" placeholder="Nome *" value="<?= sanitize_html($old['first_name'] ?? ''); ?>" required>
        <input class="input" name="last_name" placeholder="Sobrenome *" value="<?= sanitize_html($old['last_name'] ?? ''); ?>" required>
        <input class="input" name="email" type="email" placeholder="E-mail *" value="<?= sanitize_html($old['email'] ?? ''); ?>" required>
        <input class="input" name="phone" placeholder="Telefone *" value="<?= sanitize_html($old['phone'] ?? ''); ?>" required>
        <input class="input md:col-span-2" name="address1" placeholder="Endereço *" value="<?= sanitize_html($old['address1'] ?? ''); ?>" required>
        <input class="input md:col-span-2" name="address2" placeholder="Complemento" value="<?= sanitize_html($old['address2'] ?? ''); ?>">
        <input class="input" name="city" placeholder="Cidade *" value="<?= sanitize_html($old['city'] ?? ''); ?>" required>
        <input class="input" name="state" placeholder="Estado *" value="<?= sanitize_html($old['state'] ?? ''); ?>" required>
        <input class="input" name="zipcode" placeholder="CEP *" value="<?= sanitize_html($old['zipcode'] ?? ''); ?>" required>
        <select class="select" name="country" required>
          <?php foreach ($countryOptions as $country): ?>
            <?php $code = strtoupper($country['code']); ?>
            <option value="<?= $code; ?>" <?= $code === strtoupper($old['country'] ?? $defaultCountry) ? 'selected' : ''; ?>>
              <?= sanitize_html($country['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </section>

    <section class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Itens do pedido</h3>
        <button type="button" id="add-order-item" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plus mr-1"></i>Adicionar item</button>
      </div>
      <div id="order-items" class="space-y-3">
        <?php foreach ($old['items'] as $index => $item): ?>
          <div class="order-item-row grid md:grid-cols-3 gap-3 items-end border border-gray-200 rounded-lg p-3">
            <div class="md:col-span-2">
              <label class="block text-xs font-medium mb-1">Produto</label>
              <select class="select w-full" name="items[<?= $index; ?>][product_id]" required>
                <option value="">Selecione...</option>
                <?php foreach ($productsList as $product): ?>
                  <?php
                    $label = $product['name'].' — '.format_currency((float)$product['price'], $product['currency'] ?? $storeCurrency);
                    $selected = ((int)($item['product_id'] ?? 0) === (int)$product['id']) ? 'selected' : '';
                  ?>
                  <option value="<?= (int)$product['id']; ?>" <?= $selected; ?>><?= sanitize_html($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <div class="flex-1">
                <label class="block text-xs font-medium mb-1">Qtd</label>
                <input class="input w-full" type="number" min="1" name="items[<?= $index; ?>][qty]" value="<?= (int)($item['qty'] ?? 1); ?>" required>
              </div>
              <button type="button" class="btn btn-ghost text-red-500 btn-remove-item mt-5"><i class="fa-solid fa-xmark"></i></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="grid md:grid-cols-2 gap-4">
      <div class="space-y-4 card p-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Pagamento</h3>
        <div>
          <label class="block text-xs font-medium mb-1">Método</label>
          <select class="select w-full" name="payment_method">
            <?php foreach ($paymentMethodsList as $method): ?>
              <?php $code = $method['code']; ?>
              <option value="<?= sanitize_html($code); ?>" <?= $code === ($old['payment_method'] ?? '') ? 'selected' : ''; ?>>
                <?= sanitize_html($method['name']); ?>
              </option>
            <?php endforeach; ?>
            <option value="manual" <?= 'manual' === ($old['payment_method'] ?? '') ? 'selected' : ''; ?>>Manual</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Referência / Comprovante</label>
          <input class="input w-full" name="payment_ref" value="<?= sanitize_html($old['payment_ref'] ?? ''); ?>" placeholder="Ex.: nº transação">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Status do pedido</label>
          <select class="select w-full" name="order_status">
            <?php
              $statusLabels = [
                'pending' => 'Pendente',
                'paid' => 'Pago',
                'shipped' => 'Enviado',
                'canceled' => 'Cancelado',
              ];
              $currentStatus = $old['order_status'] ?? 'pending';
            ?>
            <?php foreach ($statusLabels as $code => $label): ?>
              <option value="<?= $code; ?>" <?= $code === $currentStatus ? 'selected' : ''; ?>><?= $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="space-y-4 card p-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Observações</h3>
        <textarea class="textarea w-full" name="order_notes" rows="6" placeholder="Observações internas"><?= sanitize_html($old['order_notes'] ?? ''); ?></textarea>
        <p class="hint">Essas notas ficam visíveis apenas no painel.</p>
      </div>
    </section>

    <div class="flex justify-end gap-3">
      <a href="orders.php" class="btn btn-ghost">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Criar pedido</button>
    </div>
  </form>
</div>

<template id="order-item-template">
  <div class="order-item-row grid md:grid-cols-3 gap-3 items-end border border-gray-200 rounded-lg p-3">
    <div class="md:col-span-2">
      <label class="block text-xs font-medium mb-1">Produto</label>
      <select class="select w-full" name="items[__INDEX__][product_id]" required>
        <option value="">Selecione...</option>
        <?php foreach ($productsList as $product): ?>
          <?php $label = $product['name'].' — '.format_currency((float)$product['price'], $product['currency'] ?? $storeCurrency); ?>
          <option value="<?= (int)$product['id']; ?>"><?= sanitize_html($label); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <label class="block text-xs font-medium mb-1">Qtd</label>
        <input class="input w-full" type="number" min="1" name="items[__INDEX__][qty]" value="1" required>
      </div>
      <button type="button" class="btn btn-ghost text-red-500 btn-remove-item mt-5"><i class="fa-solid fa-xmark"></i></button>
    </div>
  </div>
</template>

<script>
(function(){
  const container = document.getElementById('order-items');
  const addBtn = document.getElementById('add-order-item');
  const template = document.getElementById('order-item-template');
  if (!container || !addBtn || !template) return;
  let index = container.querySelectorAll('.order-item-row').length;

  function addRow() {
    const html = template.innerHTML.replace(/__INDEX__/g, index);
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    const row = wrapper.firstElementChild;
    container.appendChild(row);
    index++;
  }

  container.addEventListener('click', function (event) {
    const btn = event.target.closest('.btn-remove-item');
    if (btn) {
      const row = btn.closest('.order-item-row');
      if (row && container.children.length > 1) {
        row.remove();
      }
    }
  });

  addBtn.addEventListener('click', function () {
    addRow();
  });
})();
</script>

<?php
admin_footer();

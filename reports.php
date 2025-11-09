<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/utils.php';
require __DIR__.'/admin_layout.php';

if (!function_exists('require_admin')) {
  function require_admin(){
    if (empty($_SESSION['admin_id'])) {
      header('Location: admin.php?route=login');
      exit;
    }
  }
}
if (!function_exists('csrf_token')){
  function csrf_token(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
}

require_admin();
require_admin_capability('manage_orders');
$pdo = db();

function reports_resolve_range(string $range, string $startInput, string $endInput): array {
  $now = new DateTime('now');
  $end = clone $now;
  $label = 'Últimos 7 dias';
  $start = (clone $now)->modify('-7 days');
  $startInputValue = '';
  $endInputValue = '';

  switch ($range) {
    case '1d':
      $start = (clone $now)->modify('-1 day');
      $label = 'Últimas 24h';
      break;
    case '7d':
      $start = (clone $now)->modify('-7 days');
      $label = 'Últimos 7 dias';
      break;
    case '15d':
      $start = (clone $now)->modify('-15 days');
      $label = 'Últimos 15 dias';
      break;
    case '30d':
      $start = (clone $now)->modify('-30 days');
      $label = 'Últimos 30 dias';
      break;
    case 'custom':
      $customStart = DateTime::createFromFormat('Y-m-d', $startInput) ?: null;
      $customEnd = DateTime::createFromFormat('Y-m-d', $endInput) ?: null;
      if ($customStart && $customEnd) {
        $customStart->setTime(0, 0, 0);
        $customEnd->setTime(23, 59, 59);
        if ($customStart <= $customEnd) {
          $start = $customStart;
          $end = $customEnd;
          $label = 'Período personalizado';
          break;
        }
      }
      $range = '7d';
      $label = 'Últimos 7 dias';
      $start = (clone $now)->modify('-7 days');
      $end = clone $now;
      break;
  }

  $startInputValue = $start->format('Y-m-d');
  $endInputValue = $end->format('Y-m-d');

  return [$range, $start, $end, $label, $startInputValue, $endInputValue];
}

function reports_status_badge(string $status): string {
  $status = strtolower($status);
  if ($status === 'paid') return '<span class="badge ok">Pago</span>';
  if ($status === 'shipped') return '<span class="badge ok">Enviado</span>';
  if ($status === 'pending') return '<span class="badge warn">Pendente</span>';
  if ($status === 'canceled') return '<span class="badge danger">Cancelado</span>';
  return '<span class="badge">'.sanitize_html($status).'</span>';
}

$rangeParam = $_GET['range'] ?? '7d';
$customStart = trim((string)($_GET['start'] ?? ''));
$customEnd = trim((string)($_GET['end'] ?? ''));
[$rangeParam, $rangeStart, $rangeEnd, $rangeLabel, $startInputValue, $endInputValue] = reports_resolve_range($rangeParam, $customStart, $customEnd);

$startSql = $rangeStart->format('Y-m-d H:i:s');
$endSql = $rangeEnd->format('Y-m-d H:i:s');
$storeCurrency = cfg()['store']['currency'] ?? 'USD';

$summaryStmt = $pdo->prepare("
  SELECT
    COUNT(*) AS total_orders,
    SUM(CASE WHEN status IN ('paid','shipped') THEN 1 ELSE 0 END) AS paid_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
    SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) AS canceled_orders,
    SUM(total) AS gross_revenue,
    SUM(CASE WHEN status IN ('paid','shipped') THEN total ELSE 0 END) AS paid_revenue,
    SUM(CASE WHEN status = 'pending' THEN total ELSE 0 END) AS pending_value,
    SUM(CASE WHEN status = 'canceled' THEN total ELSE 0 END) AS canceled_value,
    SUM(cost_total) AS cost_total,
    SUM(profit_total) AS profit_total
  FROM orders
  WHERE created_at BETWEEN ? AND ?
");
$summaryStmt->execute([$startSql, $endSql]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalOrders = (int)($summary['total_orders'] ?? 0);
$paidOrders = (int)($summary['paid_orders'] ?? 0);
$pendingOrders = (int)($summary['pending_orders'] ?? 0);
$canceledOrders = (int)($summary['canceled_orders'] ?? 0);
$grossRevenue = (float)($summary['gross_revenue'] ?? 0);
$paidRevenue = (float)($summary['paid_revenue'] ?? 0);
$pendingValue = (float)($summary['pending_value'] ?? 0);
$canceledValue = (float)($summary['canceled_value'] ?? 0);
$ticketMedio = $paidOrders > 0 ? $paidRevenue / $paidOrders : 0.0;
$conversionRate = $totalOrders > 0 ? ($paidOrders / $totalOrders) * 100 : 0.0;
$costFeatureEnabled = cost_management_enabled();
$costSummary = $costFeatureEnabled ? (float)($summary['cost_total'] ?? 0) : 0.0;
$profitSummary = $costFeatureEnabled ? (float)($summary['profit_total'] ?? 0) : 0.0;
$marginSummary = ($paidRevenue > 0 && $costFeatureEnabled) ? ($profitSummary / $paidRevenue) * 100 : 0.0;

$statusStmt = $pdo->prepare("
  SELECT status, COUNT(*) AS total_orders, SUM(total) AS total_amount
  FROM orders
  WHERE created_at BETWEEN ? AND ?
  GROUP BY status
  ORDER BY total_orders DESC
");
$statusStmt->execute([$startSql, $endSql]);
$statusBreakdown = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

$paymentStmt = $pdo->prepare("
  SELECT COALESCE(NULLIF(TRIM(payment_method), ''), 'Indefinido') AS payment_method,
         COUNT(*) AS total_orders,
         SUM(total) AS total_amount
  FROM orders
  WHERE created_at BETWEEN ? AND ?
  GROUP BY payment_method
  ORDER BY total_amount DESC
");
$paymentStmt->execute([$startSql, $endSql]);
$paymentBreakdown = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

$recentStmt = $pdo->prepare("
  SELECT o.id, o.total, o.currency, o.status, o.payment_method, o.created_at, c.name AS customer_name
  FROM orders o
  LEFT JOIN customers c ON c.id = o.customer_id
  WHERE o.created_at BETWEEN ? AND ?
  ORDER BY o.created_at DESC
  LIMIT 20
");
$recentStmt->execute([$startSql, $endSql]);
$recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

$rangeButtons = [
  '1d' => 'Último dia',
  '7d' => '7 dias',
  '15d' => '15 dias',
  '30d' => '30 dias',
  'custom' => 'Personalizado',
];

admin_header('Relatórios');

echo '<div class="card mb-4">';
echo '  <div class="card-title">Relatórios de pedidos e financeiro</div>';
echo '  <div class="p-3">';
echo '    <div class="flex flex-wrap gap-2 mb-3">';
foreach ($rangeButtons as $key => $label) {
  $class = $rangeParam === $key ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
  $query = http_build_query(['range' => $key] + ($key === 'custom' ? ['start' => $startInputValue, 'end' => $endInputValue] : []));
  echo '<a class="'.$class.'" href="reports.php?'.$query.'">'.$label.'</a>';
}
echo '    </div>';
echo '    <form class="grid md:grid-cols-4 gap-3 items-end" method="get">';
echo '      <input type="hidden" name="range" value="custom">';
echo '      <div><label class="block text-xs font-semibold uppercase mb-1">Início</label><input class="input w-full" type="date" name="start" value="'.sanitize_html($startInputValue).'"></div>';
echo '      <div><label class="block text-xs font-semibold uppercase mb-1">Fim</label><input class="input w-full" type="date" name="end" value="'.sanitize_html($endInputValue).'"></div>';
echo '      <div class="md:col-span-2 flex gap-2">';
echo '        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-sliders mr-2"></i>Aplicar período</button>';
echo '        <a class="btn btn-ghost" href="reports.php"><i class="fa-solid fa-rotate-left mr-2"></i>Resetar</a>';
echo '      </div>';
echo '    </form>';
echo '    <p class="text-xs text-gray-500 mt-3">Período analisado: <strong>'.format_datetime($rangeStart->format('Y-m-d H:i:s'), 'd/m/Y H:i').'</strong> até <strong>'.format_datetime($rangeEnd->format('Y-m-d H:i:s'), 'd/m/Y H:i').'</strong> ('.$rangeLabel.').</p>';
echo '  </div>';
echo '</div>';

echo '<div class="grid md:grid-cols-2 gap-3 mb-4">';
echo '  <div class="card"><div class="card-title">Pedidos</div><div class="p-4 space-y-3">';
echo '    <div><span class="text-sm text-gray-500">Total de pedidos</span><div class="text-3xl font-semibold">'.$totalOrders.'</div></div>';
echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Pedidos pagos/enviados</span><span class="font-semibold">'.$paidOrders.' ('.number_format($conversionRate, 1, ',', '.').'% conversão)</span></div>';
echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Pedidos pendentes</span><span class="font-semibold">'.$pendingOrders.'</span></div>';
echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Pedidos cancelados</span><span class="font-semibold">'.$canceledOrders.'</span></div>';
echo '  </div></div>';

echo '  <div class="card"><div class="card-title">Financeiro</div><div class="p-4 space-y-3">';
echo '    <div><span class="text-sm text-gray-500">Faturamento bruto</span><div class="text-3xl font-semibold">'.format_currency($grossRevenue, $storeCurrency).'</div></div>';
echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Faturamento confirmado</span><span class="font-semibold">'.format_currency($paidRevenue, $storeCurrency).'</span></div>';
echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Ticket médio (pagos)</span><span class="font-semibold">'.format_currency($ticketMedio, $storeCurrency).'</span></div>';
echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Valor pendente</span><span class="font-semibold">'.format_currency($pendingValue, $storeCurrency).'</span></div>';
echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Valor cancelado</span><span class="font-semibold">'.format_currency($canceledValue, $storeCurrency).'</span></div>';
if ($costFeatureEnabled) {
  echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Custos do período</span><span class="font-semibold">'.format_currency($costSummary, $storeCurrency).'</span></div>';
  echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Lucro estimado</span><span class="font-semibold">'.format_currency($profitSummary, $storeCurrency).'</span></div>';
  echo '    <div class="flex justify-between text-sm"><span class="text-gray-500">Margem</span><span class="font-semibold">'.number_format($marginSummary, 1, ',', '.').'%</span></div>';
}
echo '  </div></div>';
echo '</div>';

echo '<div class="grid md:grid-cols-2 gap-3 mb-4">';
echo '  <div class="card"><div class="card-title">Por status</div><div class="p-3"><table class="table text-sm"><thead><tr><th>Status</th><th>Pedidos</th><th>Total</th></tr></thead><tbody>';
if ($statusBreakdown) {
  foreach ($statusBreakdown as $row) {
    $count = (int)$row['total_orders'];
    $amount = (float)($row['total_amount'] ?? 0);
    echo '<tr><td>'.reports_status_badge($row['status'] ?? '').'</td><td>'.$count.'</td><td>'.format_currency($amount, $storeCurrency).'</td></tr>';
  }
} else {
  echo '<tr><td colspan="3" class="text-center text-gray-400">Sem pedidos no período.</td></tr>';
}
echo '  </tbody></table></div></div>';

echo '  <div class="card"><div class="card-title">Por método de pagamento</div><div class="p-3"><table class="table text-sm"><thead><tr><th>Método</th><th>Pedidos</th><th>Total</th></tr></thead><tbody>';
if ($paymentBreakdown) {
  foreach ($paymentBreakdown as $row) {
    $method = $row['payment_method'] ?? 'Indefinido';
    $count = (int)$row['total_orders'];
    $amount = (float)($row['total_amount'] ?? 0);
    echo '<tr><td>'.sanitize_html($method).'</td><td>'.$count.'</td><td>'.format_currency($amount, $storeCurrency).'</td></tr>';
  }
} else {
  echo '<tr><td colspan="3" class="text-center text-gray-400">Sem registros no período.</td></tr>';
}
echo '  </tbody></table></div></div>';
echo '</div>';

echo '<div class="card mb-6"><div class="card-title">Pedidos recentes no período</div><div class="p-3 overflow-x-auto"><table class="table"><thead><tr><th>#</th><th>Cliente</th><th>Valor</th><th>Pagamento</th><th>Status</th><th>Data</th><th></th></tr></thead><tbody>';
if ($recentOrders) {
  foreach ($recentOrders as $row) {
    $orderId = (int)$row['id'];
    $customer = $row['customer_name'] ?: '—';
    $currency = strtoupper($row['currency'] ?? $storeCurrency);
    $amount = format_currency((float)$row['total'], $currency);
    echo '<tr>';
    echo '<td>#'.$orderId.'</td>';
    echo '<td>'.sanitize_html($customer).'</td>';
    echo '<td>'.$amount.'</td>';
    echo '<td>'.sanitize_html($row['payment_method'] ?? '-').'</td>';
    echo '<td>'.reports_status_badge($row['status'] ?? '').'</td>';
    echo '<td>'.sanitize_html(format_datetime($row['created_at'], 'd/m/Y H:i')).'</td>';
    echo '<td><a class="btn btn-ghost btn-sm" href="orders.php?action=view&id='.$orderId.'"><i class="fa-solid fa-eye"></i></a></td>';
    echo '</tr>';
  }
} else {
  echo '<tr><td colspan="7" class="text-center text-gray-400">Nenhum pedido encontrado no período.</td></tr>';
}
echo '</tbody></table></div></div>';

admin_footer();

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

echo '<section class="page-header">';
echo '  <div class="page-header__content">';
echo '    <p class="page-eyebrow">Inteligência</p>';
echo '    <h1>Relatórios de pedidos e financeiro</h1>';
echo '    <p class="page-subtitle">Combine filtros de período para analisar conversão, faturamento e métodos de pagamento.</p>';
echo '  </div>';
echo '  <div class="page-header__actions">';
echo '    <a class="btn btn-ghost" href="dashboard.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><span>Dashboard</span></a>';
echo '    <a class="btn btn-primary" href="orders.php"><i class="fa-solid fa-receipt" aria-hidden="true"></i><span>Pedidos</span></a>';
echo '  </div>';
echo '</section>';

echo '<div class="card filter-card">';
echo '  <div class="card-title">Período analisado</div>';
echo '  <div class="filter-group">';
foreach ($rangeButtons as $key => $label) {
  $class = $rangeParam === $key ? 'filter-chip active' : 'filter-chip';
  $query = http_build_query(['range' => $key] + ($key === 'custom' ? ['start' => $startInputValue, 'end' => $endInputValue] : []));
  echo '<a class="'.$class.'" href="reports.php?'.$query.'">'.$label.'</a>';
}
echo '  </div>';
echo '  <form class="field-grid two align-end" method="get">';
echo '    <input type="hidden" name="range" value="custom">';
echo '    <div class="form-field"><label>Início</label><input class="input" type="date" name="start" value="'.sanitize_html($startInputValue).'"></div>';
echo '    <div class="form-field"><label>Fim</label><input class="input" type="date" name="end" value="'.sanitize_html($endInputValue).'"></div>';
echo '    <div class="form-field field-span-2" style="margin-bottom:0;display:flex;gap:var(--space-3);flex-wrap:wrap;">';
echo '      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-sliders" aria-hidden="true"></i><span>Aplicar período</span></button>';
echo '      <a class="btn btn-ghost" href="reports.php"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Resetar</span></a>';
echo '    </div>';
echo '  </form>';
echo '  <p class="hint">Período: <strong>'.format_datetime($rangeStart->format('Y-m-d H:i:s'), 'd/m/Y H:i').'</strong> até <strong>'.format_datetime($rangeEnd->format('Y-m-d H:i:s'), 'd/m/Y H:i').'</strong> — '.$rangeLabel.'.</p>';
echo '</div>';

echo '<div class="stats-grid">';
echo '  <article class="stat-card">';
echo '    <div class="stat-card__label"><i class="fa-solid fa-receipt" aria-hidden="true"></i> Pedidos</div>';
echo '    <div class="stat-card__value">'.$totalOrders.'</div>';
echo '    <p class="stat-card__hint">Pagos/enviados: '.$paidOrders.' ('.number_format($conversionRate, 1, ',', '.').'% conversão)</p>';
echo '  </article>';
echo '  <article class="stat-card">';
echo '    <div class="stat-card__label"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i> Faturamento bruto</div>';
echo '    <div class="stat-card__value">'.format_currency($grossRevenue, $storeCurrency).'</div>';
echo '    <p class="stat-card__hint">Confirmado: '.format_currency($paidRevenue, $storeCurrency).'</p>';
echo '  </article>';
echo '  <article class="stat-card">';
echo '    <div class="stat-card__label"><i class="fa-solid fa-ticket" aria-hidden="true"></i> Ticket médio (pagos)</div>';
echo '    <div class="stat-card__value">'.format_currency($ticketMedio, $storeCurrency).'</div>';
echo '    <p class="stat-card__hint">Valor pendente: '.format_currency($pendingValue, $storeCurrency).'</p>';
echo '  </article>';
echo '  <article class="stat-card">';
echo '    <div class="stat-card__label"><i class="fa-solid fa-ban" aria-hidden="true"></i> Cancelamentos</div>';
echo '    <div class="stat-card__value">'.$canceledOrders.'</div>';
echo '    <p class="stat-card__hint">Valor: '.format_currency($canceledValue, $storeCurrency).'</p>';
echo '  </article>';
if ($costFeatureEnabled) {
  echo '  <article class="stat-card">';
  echo '    <div class="stat-card__label"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Custos do período</div>';
  echo '    <div class="stat-card__value">'.format_currency($costSummary, $storeCurrency).'</div>';
  echo '    <p class="stat-card__hint">Margem: '.number_format($marginSummary, 1, ',', '.').'%</p>';
  echo '  </article>';
  echo '  <article class="stat-card stat-card--success">';
  echo '    <div class="stat-card__label"><i class="fa-solid fa-sack-dollar" aria-hidden="true"></i> Lucro estimado</div>';
  echo '    <div class="stat-card__value">'.format_currency($profitSummary, $storeCurrency).'</div>';
  echo '    <p class="stat-card__hint">Considerando custos preenchidos.</p>';
  echo '  </article>';
}
echo '</div>';

echo '<div class="stats-grid">';
echo '  <article class="card">';
echo '    <div class="card-title">Por status</div>';
echo '    <div class="table-responsive"><table class="data-table"><thead><tr><th>Status</th><th>Pedidos</th><th>Total</th></tr></thead><tbody>';
if ($statusBreakdown) {
  foreach ($statusBreakdown as $row) {
    $count = (int)$row['total_orders'];
    $amount = (float)($row['total_amount'] ?? 0);
    echo '<tr><td>'.reports_status_badge($row['status'] ?? '').'</td><td>'.$count.'</td><td>'.format_currency($amount, $storeCurrency).'</td></tr>';
  }
} else {
  echo '<tr><td colspan="3" class="hint">Sem pedidos no período.</td></tr>';
}
echo '    </tbody></table></div>';
echo '  </article>';

echo '  <article class="card">';
echo '    <div class="card-title">Por método de pagamento</div>';
echo '    <div class="table-responsive"><table class="data-table"><thead><tr><th>Método</th><th>Pedidos</th><th>Total</th></tr></thead><tbody>';
if ($paymentBreakdown) {
  foreach ($paymentBreakdown as $row) {
    $method = $row['payment_method'] ?? 'Indefinido';
    $count = (int)$row['total_orders'];
    $amount = (float)($row['total_amount'] ?? 0);
    echo '<tr><td>'.sanitize_html($method).'</td><td>'.$count.'</td><td>'.format_currency($amount, $storeCurrency).'</td></tr>';
  }
} else {
  echo '<tr><td colspan="3" class="hint">Sem registros no período.</td></tr>';
}
echo '    </tbody></table></div>';
echo '  </article>';
echo '</div>';

echo '<div class="card">';
echo '  <div class="card-title">Pedidos recentes no período</div>';
echo '  <div class="table-responsive"><table class="data-table"><thead><tr><th>#</th><th>Cliente</th><th>Valor</th><th>Pagamento</th><th>Status</th><th>Data</th><th></th></tr></thead><tbody>';
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
    echo '<td><a class="btn btn-ghost btn-sm" href="orders.php?action=view&id='.$orderId.'"><i class="fa-solid fa-eye" aria-hidden="true"></i></a></td>';
    echo '</tr>';
  }
} else {
  echo '<tr><td colspan="7" class="hint">Nenhum pedido encontrado no período.</td></tr>';
}
echo '  </tbody></table></div>';
echo '</div>';

admin_footer();

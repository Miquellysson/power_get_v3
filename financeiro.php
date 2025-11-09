<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/utils.php';
require __DIR__ . '/admin_layout.php';

if (!function_exists('require_admin')) {
    function require_admin(): void {
        if (empty($_SESSION['admin_id'])) {
            header('Location: admin.php?route=login');
            exit;
        }
    }
}

require_admin();
$role = current_admin_role();
if (!in_array($role, ['admin', 'super_admin'], true)) {
    admin_forbidden('Acesso restrito ao time financeiro.');
}

admin_header('Financeiro');

$pdo = db();
$storeCurrency = cfg()['store']['currency'] ?? 'USD';
$costFeatureEnabled = cost_management_enabled();

function finance_number(PDO $pdo, string $sql, array $params = [], float $fallback = 0.0): float {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return $fallback;
    }
}

$totalRevenue = finance_number($pdo, "SELECT SUM(total) FROM orders WHERE status='paid'");
$last30Revenue = finance_number($pdo, "SELECT SUM(total) FROM orders WHERE status='paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$pendingTotal = finance_number($pdo, "SELECT SUM(total) FROM orders WHERE status='pending'");

$methodTotals = [];
try {
    $stmt = $pdo->query("SELECT payment_method, COUNT(*) AS total_orders, SUM(total) AS grand_total FROM orders GROUP BY payment_method ORDER BY grand_total DESC");
    $methodTotals = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $methodTotals = [];
}

$costTotalPaid = $costFeatureEnabled ? finance_number($pdo, "SELECT SUM(cost_total) FROM orders WHERE status IN ('paid','shipped')") : 0.0;
$profitTotalPaid = $costFeatureEnabled ? finance_number($pdo, "SELECT SUM(profit_total) FROM orders WHERE status IN ('paid','shipped')") : 0.0;
$last30Cost = $costFeatureEnabled ? finance_number($pdo, "SELECT SUM(cost_total) FROM orders WHERE status IN ('paid','shipped') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") : 0.0;
$last30Profit = $costFeatureEnabled ? finance_number($pdo, "SELECT SUM(profit_total) FROM orders WHERE status IN ('paid','shipped') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") : 0.0;
$overallMargin = ($totalRevenue > 0 && $costFeatureEnabled) ? ($profitTotalPaid / $totalRevenue) * 100 : 0.0;
$last30Margin = ($last30Revenue > 0 && $costFeatureEnabled) ? ($last30Profit / $last30Revenue) * 100 : 0.0;
$monthlySeries = [];
try {
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS bucket, SUM(total) AS grand_total FROM orders WHERE status='paid' GROUP BY bucket ORDER BY bucket ASC LIMIT 12");
    $monthlySeries = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $monthlySeries = [];
}
$monthlyLabels = [];
$monthlyValues = [];
foreach ($monthlySeries as $row) {
    $monthlyLabels[] = $row['bucket'];
    $monthlyValues[] = (float)$row['grand_total'];
}
?>
<section class="page-header">
  <div class="page-header__content">
    <p class="page-eyebrow">Financeiro</p>
    <h1>Saúde financeira da operação</h1>
    <p class="page-subtitle">Receita, custos, margem e métodos de pagamento em tempo real.</p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn-ghost" href="reports.php"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Relatórios</span></a>
    <a class="btn btn-primary" href="dashboard.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><span>Dashboard</span></a>
  </div>
</section>

<div class="stats-grid">
  <article class="stat-card">
    <div class="stat-card__label"><i class="fa-solid fa-money-bill-trend-up" aria-hidden="true"></i> Receita acumulada</div>
    <div class="stat-card__value"><?= format_currency($totalRevenue, $storeCurrency); ?></div>
    <p class="stat-card__hint">Total desde o início da operação (pedidos pagos).</p>
  </article>
  <article class="stat-card">
    <div class="stat-card__label"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Últimos 30 dias</div>
    <div class="stat-card__value"><?= format_currency($last30Revenue, $storeCurrency); ?></div>
    <p class="stat-card__hint">Faturamento de pedidos pagos nas últimas 4 semanas.</p>
  </article>
  <article class="stat-card stat-card--pending">
    <div class="stat-card__label"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Em aberto/pendente</div>
    <div class="stat-card__value"><?= format_currency($pendingTotal, $storeCurrency); ?></div>
    <p class="stat-card__hint">Pedidos aguardando confirmação de pagamento.</p>
  </article>
</div>

<?php if ($costFeatureEnabled): ?>
<div class="stats-grid">
  <article class="stat-card">
    <div class="stat-card__label"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Custo acumulado</div>
    <div class="stat-card__value"><?= format_currency($costTotalPaid, $storeCurrency); ?></div>
    <p class="stat-card__hint">Somatório de custos dos pedidos pagos/enviados.</p>
  </article>
  <article class="stat-card stat-card--success">
    <div class="stat-card__label"><i class="fa-solid fa-circle-dollar-to-slot" aria-hidden="true"></i> Lucro estimado</div>
    <div class="stat-card__value"><?= format_currency($profitTotalPaid, $storeCurrency); ?></div>
    <p class="stat-card__hint">Resultado bruto considerando os custos informados.</p>
  </article>
  <article class="stat-card">
    <div class="stat-card__label"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Margem acumulada</div>
    <div class="stat-card__value"><?= number_format($overallMargin, 1, ',', '.'); ?>%</div>
    <p class="stat-card__hint">Baseado na receita total registrada.</p>
  </article>
</div>

<div class="stats-grid">
  <article class="stat-card">
    <div class="stat-card__label"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Custos últimos 30 dias</div>
    <div class="stat-card__value"><?= format_currency($last30Cost, $storeCurrency); ?></div>
    <p class="stat-card__hint">Pedidos pagos ou enviados no período.</p>
  </article>
  <article class="stat-card">
    <div class="stat-card__label"><i class="fa-solid fa-sack-dollar" aria-hidden="true"></i> Lucro últimos 30 dias</div>
    <div class="stat-card__value"><?= format_currency($last30Profit, $storeCurrency); ?></div>
    <p class="stat-card__hint">Margem de <?= number_format($last30Margin, 1, ',', '.'); ?>%.</p>
  </article>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Atalhos financeiros</div>
  <div class="quick-links">
    <a href="dashboard.php" class="quick-link">
      <span class="icon"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i></span>
      <span>
        <span class="quick-link__title">Dashboard Operacional</span>
        <span class="quick-link__desc">Resumo diário de pedidos, ticket médio e logística.</span>
      </span>
    </a>
    <a href="reports.php" class="quick-link">
      <span class="icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
      <span>
        <span class="quick-link__title">Relatórios Financeiros</span>
        <span class="quick-link__desc">Exportação de pedidos, recibos e métodos de pagamento.</span>
      </span>
    </a>
  </div>
</div>

<div class="card">
  <div class="card-title">Checklist rápido</div>
  <ul class="checklist">
    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Concilie pagamentos confirmados em <a href="orders.php">Pedidos</a>.</li>
    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Baixe relatórios em <a href="reports.php">Relatórios</a> para auditoria.</li>
    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Use o Dashboard para monitorar ticket médio e status.</li>
  </ul>
</div>

<div class="card">
  <div class="card-title">Faturamento mensal (pedidos pagos)</div>
  <?php if ($monthlyValues): ?>
    <canvas id="finance-chart" height="120"></canvas>
  <?php else: ?>
    <p class="hint">Ainda não há dados suficientes para o gráfico.</p>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title">Métodos de pagamento</div>
  <?php if ($methodTotals): ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Método</th><th>Pedidos</th><th>Faturamento</th></tr>
        </thead>
        <tbody>
          <?php foreach ($methodTotals as $row): ?>
            <tr>
              <td><?= sanitize_html($row['payment_method'] ?: '-'); ?></td>
              <td><?= (int)$row['total_orders']; ?></td>
              <td><?= format_currency((float)$row['grand_total'], $storeCurrency); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="hint">Nenhum pedido registrado ainda.</p>
  <?php endif; ?>
</div>

<?php if ($monthlyValues): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const ctx = document.getElementById('finance-chart');
  if (ctx) {
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($monthlyLabels, JSON_UNESCAPED_UNICODE); ?>,
        datasets: [{
          label: 'Receita',
          data: <?= json_encode($monthlyValues); ?>,
          borderColor: '#2563eb',
          backgroundColor: 'rgba(37,99,235,0.15)',
          tension: 0.3,
          fill: true
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  }
</script>
<?php endif; ?>

<?php
admin_footer();

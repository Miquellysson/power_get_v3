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
<section class="space-y-6">
  <div class="grid md:grid-cols-3 gap-4">
    <div class="card p-5">
      <div class="text-xs uppercase text-gray-500 font-semibold mb-1">Receita acumulada</div>
      <div class="text-2xl font-bold"><?= format_currency($totalRevenue, $storeCurrency); ?></div>
      <p class="text-xs text-gray-500 mt-1">Total desde o início da operação (pedidos pagos).</p>
    </div>
    <div class="card p-5">
      <div class="text-xs uppercase text-gray-500 font-semibold mb-1">Receita últimos 30 dias</div>
      <div class="text-2xl font-bold"><?= format_currency($last30Revenue, $storeCurrency); ?></div>
      <p class="text-xs text-gray-500 mt-1">Indicador de faturamento recente (pedidos pagos).</p>
    </div>
    <div class="card p-5">
      <div class="text-xs uppercase text-gray-500 font-semibold mb-1">Em aberto / pendente</div>
      <div class="text-2xl font-bold text-amber-600"><?= format_currency($pendingTotal, $storeCurrency); ?></div>
      <p class="text-xs text-gray-500 mt-1">Pedidos aguardando confirmação de pagamento.</p>
    </div>
  </div>

  <?php if ($costFeatureEnabled): ?>
  <div class="grid md:grid-cols-3 gap-4">
    <div class="card p-5">
      <div class="text-xs uppercase text-gray-500 font-semibold mb-1">Custo acumulado</div>
      <div class="text-2xl font-bold"><?= format_currency($costTotalPaid, $storeCurrency); ?></div>
      <p class="text-xs text-gray-500 mt-1">Somatório de custos dos pedidos pagos/enviados.</p>
    </div>
    <div class="card p-5">
      <div class="text-xs uppercase text-gray-500 font-semibold mb-1">Lucro estimado</div>
      <div class="text-2xl font-bold text-emerald-700"><?= format_currency($profitTotalPaid, $storeCurrency); ?></div>
      <p class="text-xs text-gray-500 mt-1">Resultado bruto considerando custos informados.</p>
    </div>
    <div class="card p-5">
      <div class="text-xs uppercase text-gray-500 font-semibold mb-1">Margem acumulada</div>
      <div class="text-2xl font-bold"><?= number_format($overallMargin, 1, ',', '.'); ?>%</div>
      <p class="text-xs text-gray-500 mt-1">Baseado na receita total registrada.</p>
    </div>
  </div>

  <div class="grid md:grid-cols-2 gap-4">
    <div class="card p-5">
      <div class="text-xs uppercase text-gray-500 font-semibold mb-1">Custos últimos 30 dias</div>
      <div class="text-2xl font-semibold"><?= format_currency($last30Cost, $storeCurrency); ?></div>
      <p class="text-xs text-gray-500 mt-1">Pedidos pagos ou enviados no período.</p>
    </div>
    <div class="card p-5">
      <div class="text-xs uppercase text-gray-500 font-semibold mb-1">Lucro últimos 30 dias</div>
      <div class="text-2xl font-semibold text-emerald-600"><?= format_currency($last30Profit, $storeCurrency); ?></div>
      <p class="text-xs text-gray-500 mt-1">Margem: <?= number_format($last30Margin, 1, ',', '.'); ?>%.</p>
    </div>
  </div>
  <?php endif; ?>

  <div class="card p-6 space-y-4">
    <div class="card-title flex items-center gap-3">
      <i class="fa-solid fa-coins text-brand-600 text-xl"></i>
      <div>
        <div class="text-lg font-semibold">Visão Financeira</div>
        <p class="text-sm text-gray-500">Atalhos para acompanhar vendas, recebimentos e indicadores.</p>
      </div>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
      <a href="dashboard.php" class="quick-link" style="color:#0f172a;background:#f8fafc;border:1px solid rgba(15,23,42,.08)">
        <span class="w-10 h-10 rounded-full bg-brand-600/10 text-brand-700 grid place-items-center text-lg"><i class="fa-solid fa-gauge-high"></i></span>
        <div>
          <div class="font-semibold">Dashboard Operacional</div>
          <p class="text-sm text-gray-500">Resumo diário de pedidos, ticket médio e status logísticos.</p>
        </div>
      </a>
      <a href="reports.php" class="quick-link" style="color:#0f172a;background:#f8fafc;border:1px solid rgba(15,23,42,.08)">
        <span class="w-10 h-10 rounded-full bg-amber-500/15 text-amber-700 grid place-items-center text-lg"><i class="fa-solid fa-chart-line"></i></span>
        <div>
          <div class="font-semibold">Relatórios Financeiros</div>
          <p class="text-sm text-gray-500">Exportação de pedidos, recibos e acompanhamento por método de pagamento.</p>
        </div>
      </a>
    </div>
  </div>
  <div class="card p-6">
    <div class="card-title">Checklist rápido</div>
    <ul class="space-y-2 text-sm text-gray-600">
      <li><i class="fa-solid fa-circle-check text-green-500 mr-2"></i>Concilie pagamentos confirmados em <a class="text-brand-600 hover:underline" href="orders.php">Pedidos</a>.</li>
      <li><i class="fa-solid fa-circle-check text-green-500 mr-2"></i>Baixe o relatório detalhado em <a class="text-brand-600 hover:underline" href="reports.php">Relatórios</a> para auditoria.</li>
      <li><i class="fa-solid fa-circle-check text-green-500 mr-2"></i>Use o Dashboard para monitorar ticket médio e status de cada pedido.</li>
    </ul>
  </div>

  <div class="card p-6">
    <div class="card-title flex items-center gap-2">
      <i class="fa-solid fa-chart-line text-brand-600"></i>
      <span>Faturamento mensal (pedidos pagos)</span>
    </div>
    <?php if ($monthlyValues): ?>
      <canvas id="finance-chart" height="120"></canvas>
    <?php else: ?>
      <p class="text-sm text-gray-500">Ainda não há dados suficientes para o gráfico.</p>
    <?php endif; ?>
  </div>

  <div class="card p-6">
    <div class="card-title flex items-center gap-2">
      <i class="fa-solid fa-wallet text-brand-600"></i>
      <span>Métodos de pagamento</span>
    </div>
    <?php if ($methodTotals): ?>
      <div class="overflow-x-auto">
        <table class="table text-sm">
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
      <p class="text-sm text-gray-500">Nenhum pedido registrado ainda.</p>
    <?php endif; ?>
  </div>
</section>

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

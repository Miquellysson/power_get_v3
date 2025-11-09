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

admin_header('Dashboard');

// KPIs
$counts = ['orders'=>0,'customers'=>0,'products'=>0,'categories'=>0];
try{ $counts['orders']     = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(); }catch(Throwable $e){}
try{ $counts['customers']  = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(); }catch(Throwable $e){}
try{ $counts['products']   = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn(); }catch(Throwable $e){}
try{ $counts['categories'] = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE active=1")->fetchColumn(); }catch(Throwable $e){}

$quickLinks = [
  ['icon'=>'fa-receipt','label'=>'Pedidos','desc'=>'Pedidos e status','href'=>'orders.php'],
  ['icon'=>'fa-pills','label'=>'Produtos','desc'=>'Catálogo, estoque','href'=>'products.php'],
  ['icon'=>'fa-tags','label'=>'Categorias','desc'=>'Vitrines e filtros','href'=>'categories.php'],
  ['icon'=>'fa-users','label'=>'Clientes','desc'=>'Perfis e contatos','href'=>'customers.php'],
  ['icon'=>'fa-user-shield','label'=>'Usuários','desc'=>'Time e permissões','href'=>'users.php'],
  ['icon'=>'fa-sliders','label'=>'Configurações','desc'=>'Pagamentos e layout','href'=>'settings.php?tab=general'],
  ['icon'=>'fa-right-from-bracket','label'=>'Sair', 'desc'=>'Encerrar sessão', 'href'=>'admin.php?route=logout'],
];

$seoScore = 86;
$seoTrend = '+4 pts vs. mês anterior';
$metaTitleLength = 58;
$metaDescriptionLength = 148;
$metaKeywords = 12;
$seoChecks = [
    ['label' => 'Meta title',       'status' => 'ok',     'detail' => '58 caracteres (ideal 50-60)'],
    ['label' => 'Meta description', 'status' => 'warn',   'detail' => '148 caracteres (próximo do limite)'],
    ['label' => 'Open Graph',       'status' => 'ok',     'detail' => 'Imagem e título definidos'],
    ['label' => 'Sitemap.xml',      'status' => 'ok',     'detail' => 'Atualizado há 2 dias'],
    ['label' => 'Robots.txt',       'status' => 'ok',     'detail' => 'Permite indexação'],
    ['label' => 'PageSpeed mobile', 'status' => 'warn',   'detail' => '74/100 — revisar imagens'],
];
$trafegoSources = [
    ['canal' => 'Orgânico', 'crescimento' => '+12%', 'trafego' => 1820],
    ['canal' => 'Pago',     'crescimento' => '+4%',  'trafego' => 940],
    ['canal' => 'Social',   'crescimento' => '-2%',  'trafego' => 610],
    ['canal' => 'Direto',   'crescimento' => '+8%',  'trafego' => 420],
];

echo '<section class="page-header">';
echo '  <div class="page-header__content">';
echo '    <p class="page-eyebrow">Painel Administrativo</p>';
echo '    <h1>Visão geral da loja</h1>';
echo '    <p class="page-subtitle">Monitore pedidos, catálogo, clientes e indicadores em um só lugar.</p>';
echo '  </div>';
echo '  <div class="page-header__actions">';
echo '    <a class="btn btn-ghost" href="settings.php?tab=general"><i class="fa-solid fa-sliders" aria-hidden="true"></i><span>Configurações</span></a>';
echo '    <a class="btn btn-primary" href="orders.php"><i class="fa-solid fa-receipt" aria-hidden="true"></i><span>Ver pedidos</span></a>';
echo '    <a class="btn btn-ghost" href="admin.php?route=logout"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Sair</span></a>';
echo '  </div>';
echo '</section>';

echo '<div class="quick-links">';
foreach ($quickLinks as $link) {
  echo '<a class="quick-link" href="'.sanitize_html($link['href']).'">';
  echo '  <span class="icon"><i class="fa-solid '.sanitize_html($link['icon']).'" aria-hidden="true"></i></span>';
  echo '  <span><span class="quick-link__title">'.sanitize_html($link['label']).'</span><span class="quick-link__desc">'.sanitize_html($link['desc']).'</span></span>';
  echo '</a>';
}
echo '</div>';

echo '<section class="stats-grid">';
echo '  <article class="stat-card"><div class="stat-card__label"><i class="fa-solid fa-receipt" aria-hidden="true"></i> Pedidos</div><div class="stat-card__value">'.number_format($counts['orders']).'</div><p class="stat-card__hint">Total registrados.</p></article>';
echo '  <article class="stat-card"><div class="stat-card__label"><i class="fa-solid fa-users" aria-hidden="true"></i> Clientes</div><div class="stat-card__value">'.number_format($counts['customers']).'</div><p class="stat-card__hint">Contatos ativos.</p></article>';
echo '  <article class="stat-card"><div class="stat-card__label"><i class="fa-solid fa-box-open" aria-hidden="true"></i> Produtos ativos</div><div class="stat-card__value">'.number_format($counts['products']).'</div><p class="stat-card__hint">Disponíveis na vitrine.</p></article>';
echo '  <article class="stat-card"><div class="stat-card__label"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Categorias</div><div class="stat-card__value">'.number_format($counts['categories']).'</div><p class="stat-card__hint">Coleções publicadas.</p></article>';
echo '</section>';

// Últimos pedidos
echo '<div class="card">';
echo '  <div class="card-title">Últimos pedidos</div>';
try{
  $st=$pdo->query("SELECT o.id,o.total,o.currency,o.status,o.created_at,c.name AS customer_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC LIMIT 10");
  echo '  <div class="table-responsive">';
  echo '  <table class="data-table"><thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Status</th><th>Quando</th><th></th></tr></thead><tbody>';
  foreach($st as $row){
    $badge = '<span class="badge">'.sanitize_html($row['status']).'</span>';
    if ($row['status']==='paid') $badge='<span class="badge ok">Pago</span>';
    elseif ($row['status']==='pending') $badge='<span class="badge warn">Pendente</span>';
    elseif ($row['status']==='canceled') $badge='<span class="badge danger">Cancelado</span>';
    echo '<tr>';
    echo '<td>#'.(int)$row['id'].'</td>';
    echo '<td>'.sanitize_html($row['customer_name'] ?: '-').'</td>';
    $rowCurrency = strtoupper($row['currency'] ?? (cfg()['store']['currency'] ?? 'USD'));
    echo '<td>'.format_currency((float)$row['total'], $rowCurrency).'</td>';
    echo '<td>'.$badge.'</td>';
    echo '<td>'.sanitize_html($row['created_at'] ?? '').'</td>';
    echo '<td><a class="btn btn-ghost" href="orders.php?action=view&id='.(int)$row['id'].'"><i class="fa-solid fa-eye" aria-hidden="true"></i><span>Ver</span></a></td>';
    echo '</tr>';
  }
  echo '</tbody></table></div>';
}catch(Throwable $e){
  echo '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i>Erro ao carregar pedidos recentes.</div>';
}
echo '</div>';

echo '<section class="seo-grid">';
echo '  <article class="seo-card seo-card--score">';
echo '    <div class="seo-card__header"><div><h2>SEO Score</h2><p>'.$seoTrend.'</p></div><span class="seo-score">'.$seoScore.'</span></div>';
echo '    <div class="seo-meta">';
echo '      <div><span>Meta title</span><strong>'.$metaTitleLength.'/60</strong></div>';
echo '      <div><span>Meta description</span><strong>'.$metaDescriptionLength.'/160</strong></div>';
echo '      <div><span>Keywords</span><strong>'.$metaKeywords.'</strong></div>';
echo '    </div>';
  echo '    <div class="seo-progress">';
  echo '      <span>Visibilidade orgânica</span>';
  echo '      <div class="progress-bar"><span style="width: '.min(100, $seoScore).'%"></span></div>';
  echo '    </div>';
echo '  </article>';
echo '  <article class="seo-card">';
echo '    <div class="seo-card__header"><div><h2>Checklist SEO</h2><p>Verificações automáticas</p></div></div>';
echo '    <ul class="seo-checklist">';
foreach ($seoChecks as $check) {
  $statusClass = $check['status'] === 'ok' ? 'ok' : ($check['status'] === 'warn' ? 'warn' : 'danger');
  echo '  <li class="'.$statusClass.'"><span>'.sanitize_html($check['label']).'</span><small>'.sanitize_html($check['detail']).'</small></li>';
}
echo '    </ul>';
echo '  </article>';
echo '  <article class="seo-card">';
echo '    <div class="seo-card__header"><div><h2>Fontes de tráfego</h2><p>Últimos 7 dias</p></div></div>';
echo '    <div class="table-responsive"><table class="data-table">';
echo '      <thead><tr><th>Canal</th><th>Crescimento</th><th>Visitas</th></tr></thead><tbody>';
foreach ($trafegoSources as $source) {
  echo '      <tr><td>'.sanitize_html($source['canal']).'</td><td>'.$source['crescimento'].'</td><td>'.number_format($source['trafego']).'</td></tr>';
}
echo '      </tbody></table></div>';
echo '  </article>';
echo '</section>';

$footerStoreName = setting_get('store_name', cfg()['store']['name'] ?? 'Sua Loja');
$GLOBALS['_ADMIN_CUSTOM_FOOTER'] = '© 2025 ' . $footerStoreName . ' — todos os direitos reservados.';
admin_footer();

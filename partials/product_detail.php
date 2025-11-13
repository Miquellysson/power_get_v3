<?php
$mainImage = htmlspecialchars($galleryImages[0] ?? proxy_img('assets/no-image.png'), ENT_QUOTES, 'UTF-8');
$galleryCount = count($galleryImages);
$shippingFormattedJs = json_encode($shippingFormatted, JSON_UNESCAPED_UNICODE);
$productNameJs = json_encode($productName, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$checkoutUrlJs = json_encode('?route=checkout', JSON_UNESCAPED_UNICODE);
$starIcons = function (float $value): string {
  $html = '';
  for ($i = 1; $i <= 5; $i++) {
    if ($value >= $i) {
      $html .= '<i class="fa-solid fa-star text-amber-400"></i>';
    } elseif ($value + 0.25 >= $i) {
      $html .= '<i class="fa-solid fa-star-half-stroke text-amber-400"></i>';
    } else {
      $html .= '<i class="fa-regular fa-star text-gray-300"></i>';
    }
  }
  return $html;
};
?>
<style>
  .pdp-main-img{transition:transform .35s ease,opacity .3s ease;transform:scale(1);}
  .pdp-main-img.zoom-active{transform:scale(1.2);}
  .pdp-thumb-active{border-color:var(--brand-primary,#dc2626);box-shadow:0 0 0 2px rgba(32,96,200,.15);}
  .pdp-sticky{transition:transform .3s ease,opacity .3s ease;}
  .pdp-sticky.show{opacity:1;transform:translate(-50%,0);}
</style>

<section id="productDetail" class="relative bg-slate-50/60">
  <div class="max-w-6xl mx-auto px-4 py-10 space-y-10" id="pdpHero">
    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500">
      <a href="?route=home" class="inline-flex items-center gap-2 text-brand-600 hover:text-brand-700 font-semibold transition"><i class="fa-solid fa-arrow-left"></i> Voltar para a loja</a>
      <div class="flex items-center gap-3 text-xs uppercase tracking-wide">
        <?php if ($categoryNameSafe): ?>
          <span class="text-gray-400 hidden sm:inline">Categoria:</span><span class="font-semibold text-gray-700"><?= $categoryNameSafe ?></span>
        <?php endif; ?>
        <span class="text-gray-400">SKU:</span><span class="font-semibold text-gray-700"><?= $skuSafe ?></span>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-10 items-start">
      <div class="space-y-5">
        <div class="relative rounded-3xl bg-white shadow-lg overflow-hidden">
          <div class="aspect-square w-full bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center">
            <img id="product-main-image" src="<?= $mainImage ?>" alt="<?= $productNameSafe ?>" class="pdp-main-img max-h-full max-w-full object-contain cursor-zoom-in">
            <div class="absolute top-4 right-4 px-3 py-1 rounded-full bg-white/90 text-xs font-semibold text-gray-700 shadow-sm">Passe o mouse para zoom</div>
          </div>
        </div>
        <?php if ($galleryCount > 1): ?>
          <div class="flex gap-3 flex-wrap">
            <?php foreach ($galleryImages as $idx => $imgPath): ?>
              <?php $thumb = htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8'); ?>
              <button type="button" class="w-20 h-20 rounded-xl border <?= $idx === 0 ? 'pdp-thumb-active' : 'border-transparent' ?> overflow-hidden bg-white shadow-sm hover:border-brand-300 transition" data-gallery-image="<?= $thumb ?>">
                <img src="<?= $thumb ?>" alt="Miniatura" class="w-full h-full object-cover">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($videoUrl !== ''): ?>
          <?php $safeVideo = htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8'); ?>
          <div class="bg-white rounded-2xl shadow p-4 space-y-3">
            <h3 class="font-semibold flex items-center gap-2 text-gray-800"><i class="fa-solid fa-circle-play text-brand-600"></i> Vídeo demonstrativo</h3>
            <?php if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false): ?>
              <?php if (preg_match('~(youtu\.be/|v=)([\w-]+)~', $videoUrl, $match)): ?>
                <div class="relative aspect-video rounded-xl overflow-hidden">
                  <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?= htmlspecialchars($match[2], ENT_QUOTES, 'UTF-8') ?>" frameborder="0" allowfullscreen></iframe>
                </div>
              <?php else: ?>
                <a class="text-brand-600 underline" href="<?= $safeVideo ?>" target="_blank" rel="noopener">Assistir vídeo</a>
              <?php endif; ?>
            <?php else: ?>
              <a class="text-brand-600 underline" href="<?= $safeVideo ?>" target="_blank" rel="noopener">Assistir vídeo</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-3 text-xs font-semibold">
          <?php if ($inStock): ?>
            <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i> Em estoque</span>
          <?php else: ?>
            <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Esgotado</span>
          <?php endif; ?>
          <?php if ($isLowStock): ?>
            <span class="px-3 py-1 rounded-full bg-amber-50 text-amber-700"><i class="fa-solid fa-fire mr-1"></i> Poucas unidades</span>
          <?php endif; ?>
          <?php if ($shippingCost <= 0): ?>
            <span class="px-3 py-1 rounded-full bg-sky-50 text-sky-700"><i class="fa-solid fa-truck-fast mr-1"></i> Frete grátis</span>
          <?php endif; ?>
          <span class="px-3 py-1 rounded-full bg-gray-900 text-white"><i class="fa-solid fa-shield-halved mr-1"></i> Checkout seguro</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900"><?= $productNameSafe ?></h1>
        <?php if ($shortDescriptionSafe): ?>
          <p class="text-gray-600 text-lg"><?= $shortDescriptionSafe ?></p>
        <?php endif; ?>
        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
          <div class="flex items-center text-amber-400 text-base space-x-1"><?= $ratingStarsHtml ?></div>
          <div class="font-semibold text-gray-900"><?= $avgRatingDisplay ?> / 5</div>
          <div class="text-gray-400"><?= $reviewCount ?> avaliações reais</div>
          <button type="button" class="text-brand-600 hover:underline" data-scroll="#productTabs">Ver avaliações</button>
        </div>

        <div class="bg-white rounded-3xl shadow-lg border border-slate-100 p-5 space-y-3">
          <div class="flex items-center gap-3 flex-wrap">
            <?php if ($compareFormatted): ?>
              <span class="text-sm text-gray-400 line-through">De <?= $compareFormatted ?></span>
            <?php endif; ?>
            <span class="text-4xl font-black text-brand-700"><?= $priceFormatted ?></span>
            <?php if ($discountPercent): ?>
              <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-sm font-semibold"><?= $discountPercent ?>% OFF</span>
            <?php endif; ?>
          </div>
          <div class="text-sm text-gray-600 flex items-center gap-2"><i class="fa-solid fa-credit-card text-brand-500"></i> <?= $paymentConditionsSafe ?: 'Pagamentos aprovados em segundos via Square ou Stripe.' ?></div>
          <div class="text-sm text-gray-600 flex items-center gap-2"><i class="fa-solid fa-truck text-brand-500"></i> <?= $shippingHeadlineSafe ?></div>
          <?php if ($urgencySafe): ?>
            <div class="text-sm font-semibold text-amber-600 flex items-center gap-2"><i class="fa-solid fa-bolt"></i> <?= $urgencySafe ?></div>
          <?php endif; ?>
        </div>

        <div class="bg-white rounded-3xl shadow-lg border border-slate-100 p-5 space-y-4">
          <div class="flex items-center justify-between text-sm font-semibold text-gray-700">
            <span>Quantidade</span>
            <span class="text-xs text-gray-400">Disponibilidade imediata</span>
          </div>
          <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center border border-slate-200 rounded-full overflow-hidden">
              <button type="button" class="w-10 h-10 flex items-center justify-center text-lg text-gray-600" id="qtyDecrease"><i class="fa-solid fa-minus"></i></button>
              <input type="number" min="1" value="1" id="quantityInput" class="w-16 h-10 text-center border-x border-slate-200 focus:outline-none" />
              <button type="button" class="w-10 h-10 flex items-center justify-center text-lg text-gray-600" id="qtyIncrease"><i class="fa-solid fa-plus"></i></button>
            </div>
            <?php if ($inStock): ?>
              <div class="flex-1 grid sm:grid-cols-2 gap-3">
                <button type="button" id="btnAddToCart" class="px-5 py-3 rounded-2xl bg-brand-600 text-white hover:bg-brand-700 font-semibold flex items-center justify-center gap-2 shadow"><i class="fa-solid fa-cart-plus"></i> Adicionar ao carrinho</button>
                <button type="button" data-buy-now="primary" class="px-5 py-3 rounded-2xl border border-brand-200 text-brand-700 hover:bg-brand-50 font-semibold flex items-center justify-center gap-2"><i class="fa-solid fa-bolt"></i> Comprar agora</button>
              </div>
            <?php else: ?>
              <div class="flex-1 text-center text-gray-500 font-semibold">Avise-me quando disponível</div>
            <?php endif; ?>
          </div>
          <p class="text-xs text-gray-400 text-center">Você poderá revisar o pedido antes de finalizar.</p>
        </div>

        <?php if ($paymentButtons): ?>
          <div class="bg-white rounded-3xl shadow border border-slate-100 p-5 space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-3">
              <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-wallet text-brand-600"></i> Pagamentos rápidos</h3>
              <span class="text-xs text-gray-400 uppercase tracking-wide">Escolha sua forma favorita</span>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
              <?php foreach ($paymentButtons as $button): ?>
                <?php
                  $label = htmlspecialchars($button['label'], ENT_QUOTES, 'UTF-8');
                  $subtitle = htmlspecialchars($button['subtitle'], ENT_QUOTES, 'UTF-8');
                  $icon = htmlspecialchars($button['icon'], ENT_QUOTES, 'UTF-8');
                  $url = htmlspecialchars($button['url'], ENT_QUOTES, 'UTF-8');
                ?>
                <a href="<?= $url ?>" target="_blank" rel="noopener" class="group flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 hover:border-brand-200 hover:bg-brand-50 transition">
                  <span class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-brand-600 text-lg"><i class="<?= $icon ?>"></i></span>
                  <span>
                    <span class="block font-semibold text-gray-900"><?= $label ?></span>
                    <span class="block text-xs text-gray-500"><?= $subtitle ?></span>
                  </span>
                  <i class="fa-solid fa-arrow-up-right-from-square text-gray-300 group-hover:text-brand-600 ml-auto"></i>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5 space-y-4">
          <div class="flex items-center gap-2 font-semibold text-gray-900"><i class="fa-solid fa-truck-fast text-brand-600"></i> Estimativa de frete</div>
          <p class="text-sm text-gray-500">Calcule prazos e valores informando seu CEP. Utilizamos parceiros com rastreio em tempo real.</p>
          <div class="flex gap-3 flex-col sm:flex-row">
            <input type="text" maxlength="9" placeholder="00000-000" id="cepInput" class="flex-1 px-4 py-3 border rounded-xl focus:ring focus:ring-brand-200" />
            <button type="button" class="px-5 py-3 rounded-xl bg-gray-900 text-white hover:bg-gray-800 font-semibold" id="calcFreightBtn">Calcular frete</button>
          </div>
          <div id="freightResult" class="text-sm text-gray-600 hidden"></div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5 space-y-3">
          <div class="flex items-center gap-2 font-semibold text-gray-900"><i class="fa-solid fa-shield-check text-brand-600"></i> Compra segura</div>
          <ul class="text-sm text-gray-600 space-y-2">
            <li class="flex items-center gap-2"><i class="fa-solid fa-lock text-brand-600"></i> Servidores protegidos com criptografia SSL.</li>
            <li class="flex items-center gap-2"><i class="fa-solid fa-headset text-brand-600"></i> Suporte humano no WhatsApp do pedido ao pós-venda.</li>
            <li class="flex items-center gap-2"><i class="fa-solid fa-rotate-left text-brand-600"></i> Troca e devolução garantidas seguindo o CDC.</li>
          </ul>
          <div class="text-xs text-gray-400 flex flex-wrap gap-3 pt-2">
            <a class="underline hover:text-brand-600" href="?route=privacy" target="_blank">Política de privacidade</a>
            <a class="underline hover:text-brand-600" href="?route=refund" target="_blank">Política de reembolso</a>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-3xl shadow p-6 space-y-6 border border-slate-100">
      <nav class="flex flex-wrap gap-3 text-sm font-semibold text-gray-600 border-b pb-3" id="productTabs">
        <button type="button" data-tab="description" class="px-4 py-2 rounded-lg bg-brand-50 text-brand-700 shadow-sm">Descrição</button>
        <button type="button" data-tab="specs" class="px-4 py-2 rounded-lg hover:bg-gray-50">Especificações</button>
        <button type="button" data-tab="reviews" class="px-4 py-2 rounded-lg hover:bg-gray-50">Avaliações</button>
        <button type="button" data-tab="delivery" class="px-4 py-2 rounded-lg hover:bg-gray-50">Entrega</button>
      </nav>
      <div class="space-y-8" id="productTabPanels">
        <div data-panel="description">
          <div class="prose prose-sm sm:prose md:prose-lg max-w-none text-gray-700"><?= $detailedDescription ?></div>
          <?php if ($additionalInfo !== ''): ?>
            <div class="mt-6 p-4 rounded-2xl bg-brand-50 text-brand-800 text-sm"><?= $additionalInfo ?></div>
          <?php endif; ?>
          <div class="mt-6 text-center">
            <button type="button" data-buy-now="secondary" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-brand-600 text-white font-semibold shadow hover:bg-brand-700">Quero comprar agora <i class="fa-solid fa-arrow-right"></i></button>
          </div>
        </div>

        <div data-panel="specs" class="hidden">
          <?php if ($specs): ?>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <tbody>
                  <?php foreach ($specs as $entry): ?>
                    <tr class="border-b last:border-0">
                      <th class="text-left font-semibold py-3 pr-6 text-gray-600"><?= $entry['label'] ?></th>
                      <td class="py-3 text-gray-700"><?= $entry['value'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-sm text-gray-500">Este produto ainda não possui especificações detalhadas. Atualizaremos em breve.</p>
          <?php endif; ?>
        </div>

        <div data-panel="reviews" class="hidden">
          <div class="flex flex-wrap items-center gap-4">
            <div>
              <div class="text-4xl font-black text-brand-600"><?= $avgRatingDisplay ?></div>
              <div class="text-xs uppercase text-gray-400"><?= $reviewCount ?> avaliações</div>
            </div>
            <div class="flex-1">
              <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-amber-400" style="width: <?= $ratingPercent ?>%;"></div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Clientes destacam a rapidez na entrega e a qualidade do atendimento.</p>
            </div>
          </div>
          <div class="grid md:grid-cols-2 gap-4 mt-6">
            <?php foreach ($sampleReviews as $review): ?>
              <?php
                $reviewName = htmlspecialchars($review['name'], ENT_QUOTES, 'UTF-8');
                $reviewComment = htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8');
                $reviewDate = htmlspecialchars($review['date'], ENT_QUOTES, 'UTF-8');
                $reviewStars = $starIcons((float)$review['rating']);
              ?>
              <div class="rounded-2xl border border-slate-100 p-4 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                  <div class="font-semibold text-gray-900"><?= $reviewName ?></div>
                  <span class="text-xs text-gray-400"><?= $reviewDate ?></span>
                </div>
                <div class="flex items-center gap-1 text-sm text-amber-400 mb-2"><?= $reviewStars ?></div>
                <p class="text-sm text-gray-600"><?= $reviewComment ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div data-panel="delivery" class="hidden">
          <div class="prose prose-sm sm:prose md:prose-lg max-w-none text-gray-700"><?= $deliveryInfo ?></div>
          <div class="mt-6 grid sm:grid-cols-3 gap-3">
            <?php foreach ($shippingOptions as $option): ?>
              <?php
                $label = htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8');
                $detail = htmlspecialchars($option['detail'], ENT_QUOTES, 'UTF-8');
                $price = htmlspecialchars($option['price'], ENT_QUOTES, 'UTF-8');
              ?>
              <div class="rounded-2xl border border-slate-200 p-4 hover:border-brand-200 transition">
                <div class="text-sm font-semibold text-gray-900"><?= $label ?></div>
                <div class="text-xs text-gray-400"><?= $detail ?></div>
                <div class="text-lg font-bold text-brand-700 mt-2"><?= $price ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if ($relatedProducts): ?>
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-2xl font-bold text-gray-900">Produtos relacionados</h2>
          <a class="text-sm text-brand-600 hover:underline font-semibold" href="?route=home">Ver catálogo</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <?php foreach ($relatedProducts as $rel): ?>
            <?php
              $relImg = $rel['image_path'] ? proxy_img($rel['image_path']) : 'assets/no-image.png';
              $relImgSafe = htmlspecialchars($relImg, ENT_QUOTES, 'UTF-8');
              $relPrice = format_currency((float)$rel['price'], $currencyCode);
              $relCompare = isset($rel['price_compare']) && $rel['price_compare'] > $rel['price'] ? format_currency((float)$rel['price_compare'], $currencyCode) : '';
              $relDiscountBadge = '';
              if ($relCompare) {
                $relDiscountValue = max(1, round(100 - (((float)$rel['price'] / (float)$rel['price_compare']) * 100)));
                $relDiscountBadge = '<span class="text-xs text-emerald-600 font-semibold">'.$relDiscountValue.'% OFF</span>';
              }
              $relLink = $rel['slug'] ? '?route=product&slug='.urlencode($rel['slug']) : '?route=product&id='.$rel['id'];
              $relNameSafe = htmlspecialchars($rel['name'], ENT_QUOTES, 'UTF-8');
            ?>
            <article class="rounded-3xl bg-white border border-slate-100 shadow hover:-translate-y-1 transition group">
              <a href="<?= $relLink ?>" class="block p-4 space-y-3">
                <div class="aspect-square rounded-2xl bg-gray-50 flex items-center justify-center overflow-hidden">
                  <img src="<?= $relImgSafe ?>" alt="<?= $relNameSafe ?>" class="w-full h-full object-cover group-hover:scale-105 transition">
                </div>
                <div class="space-y-1">
                  <h3 class="font-semibold text-gray-900 text-base"><?= $relNameSafe ?></h3>
                  <div class="flex items-center gap-2">
                    <?php if ($relCompare): ?>
                      <span class="text-xs text-gray-400 line-through"><?= $relCompare ?></span>
                    <?php endif; ?>
                    <span class="text-lg font-bold text-brand-700"><?= $relPrice ?></span>
                  </div>
                  <?= $relDiscountBadge ?>
                </div>
              </a>
              <div class="px-4 pb-4">
                <button type="button" class="w-full px-4 py-2 rounded-2xl border border-brand-200 text-brand-700 hover:bg-brand-50 text-sm font-semibold flex items-center justify-center gap-2" data-quick-add data-product-id="<?= $rel['id'] ?>" data-product-name="<?= $relNameSafe ?>"><i class="fa-solid fa-bag-shopping"></i> Compra rápida</button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<div id="stickyBuyBar" class="pdp-sticky pointer-events-none fixed bottom-4 left-1/2 z-40 w-full max-w-3xl -translate-x-1/2 translate-y-full opacity-0">
  <div class="pointer-events-auto rounded-2xl bg-white shadow-2xl border border-slate-100 px-4 py-3 flex flex-wrap items-center gap-4">
    <div class="flex flex-col">
      <span class="text-xs uppercase text-gray-400">Total</span>
      <span class="text-2xl font-bold text-brand-700"><?= $priceFormatted ?></span>
    </div>
    <div class="flex items-center border border-slate-200 rounded-full overflow-hidden ml-auto">
      <button type="button" class="w-9 h-9 flex items-center justify-center text-sm text-gray-600" id="stickyQtyDecrease"><i class="fa-solid fa-minus"></i></button>
      <input type="number" min="1" value="1" id="stickyQtyInput" class="w-14 h-9 text-center border-x border-slate-200 text-sm" />
      <button type="button" class="w-9 h-9 flex items-center justify-center text-sm text-gray-600" id="stickyQtyIncrease"><i class="fa-solid fa-plus"></i></button>
    </div>
    <div class="flex flex-1 gap-3">
      <button type="button" id="stickyAddToCart" class="flex-1 px-4 py-2 rounded-xl border border-brand-200 text-brand-700 font-semibold hover:bg-brand-50 text-sm flex items-center justify-center gap-2"><i class="fa-solid fa-cart-plus"></i> Adicionar</button>
      <button type="button" data-buy-now="sticky" class="flex-1 px-4 py-2 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700 text-sm flex items-center justify-center gap-2"><i class="fa-solid fa-bolt"></i> Comprar</button>
    </div>
  </div>
</div>

<script>
  (function(){
    const galleryButtons = document.querySelectorAll('[data-gallery-image]');
    const mainImage = document.getElementById('product-main-image');
    galleryButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const newSrc = btn.getAttribute('data-gallery-image');
        if (!newSrc || !mainImage) return;
        mainImage.src = newSrc;
        galleryButtons.forEach((b) => b.classList.remove('pdp-thumb-active'));
        btn.classList.add('pdp-thumb-active');
      });
    });
    if (mainImage) {
      mainImage.addEventListener('mousemove', (event) => {
        const rect = mainImage.getBoundingClientRect();
        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;
        mainImage.style.transformOrigin = `${x}% ${y}%`;
        mainImage.classList.add('zoom-active');
      });
      mainImage.addEventListener('mouseleave', () => {
        mainImage.style.transformOrigin = 'center';
        mainImage.classList.remove('zoom-active');
      });
    }

    const qtyInput = document.getElementById('quantityInput');
    const stickyQtyInput = document.getElementById('stickyQtyInput');
    const syncQty = (source, target) => {
      if (!source || !target) return;
      target.value = source.value;
    };
    const adjustQty = (delta, input, other) => {
      if (!input) return;
      const value = Math.max(1, (parseInt(input.value, 10) || 1) + delta);
      input.value = value;
      syncQty(input, other);
    };
    document.getElementById('qtyDecrease')?.addEventListener('click', () => adjustQty(-1, qtyInput, stickyQtyInput));
    document.getElementById('qtyIncrease')?.addEventListener('click', () => adjustQty(1, qtyInput, stickyQtyInput));
    document.getElementById('stickyQtyDecrease')?.addEventListener('click', () => adjustQty(-1, stickyQtyInput, qtyInput));
    document.getElementById('stickyQtyIncrease')?.addEventListener('click', () => adjustQty(1, stickyQtyInput, qtyInput));
    qtyInput?.addEventListener('input', () => syncQty(qtyInput, stickyQtyInput));
    stickyQtyInput?.addEventListener('input', () => syncQty(stickyQtyInput, qtyInput));

    const productId = <?= (int)$productId ?>;
    const productName = <?= $productNameJs ?>;
    const checkoutUrl = <?= $checkoutUrlJs ?>;
    const handleAddToCart = () => {
      const qty = parseInt(qtyInput?.value || stickyQtyInput?.value || '1', 10) || 1;
      if (typeof addToCart === 'function') {
        addToCart(productId, productName, qty);
      }
    };
    document.getElementById('btnAddToCart')?.addEventListener('click', handleAddToCart);
    document.getElementById('stickyAddToCart')?.addEventListener('click', handleAddToCart);
    document.querySelectorAll('[data-buy-now]').forEach((btn) => {
      btn.addEventListener('click', () => {
        handleAddToCart();
        window.location.href = checkoutUrl;
      });
    });

    const tabs = document.querySelectorAll('#productTabs button[data-tab]');
    const panels = document.querySelectorAll('#productTabPanels [data-panel]');
    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const target = tab.getAttribute('data-tab');
        tabs.forEach((t) => t.classList.remove('bg-brand-50', 'text-brand-700', 'shadow-sm'));
        tab.classList.add('bg-brand-50', 'text-brand-700', 'shadow-sm');
        panels.forEach((panel) => {
          panel.classList.toggle('hidden', panel.getAttribute('data-panel') !== target);
        });
      });
    });

    document.querySelectorAll('[data-scroll]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = document.querySelector(btn.getAttribute('data-scroll'));
        target?.scrollIntoView({ behavior: 'smooth' });
      });
    });

    const freightBtn = document.getElementById('calcFreightBtn');
    const cepInputEl = document.getElementById('cepInput');
    const freightResult = document.getElementById('freightResult');
    freightBtn?.addEventListener('click', () => {
      if (!cepInputEl || !freightResult) return;
      const cep = (cepInputEl.value || '').replace(/\D+/g, '');
      if (cep.length !== 8) {
        freightResult.textContent = 'Informe um CEP válido com 8 dígitos.';
        freightResult.classList.remove('hidden');
        freightResult.classList.add('text-rose-600');
        return;
      }
      freightResult.classList.remove('text-rose-600');
      freightResult.classList.add('text-emerald-700');
      freightResult.textContent = 'Frete disponível para o CEP ' + cep.slice(0, 5) + '-' + cep.slice(5) + ' por ' + <?= $shippingFormattedJs ?> + '.';
      freightResult.classList.remove('hidden');
    });

    const stickyBar = document.getElementById('stickyBuyBar');
    const hero = document.getElementById('pdpHero');
    const toggleSticky = () => {
      if (!stickyBar || !hero) return;
      const rect = hero.getBoundingClientRect();
      if (rect.bottom < window.innerHeight * 0.6) {
        stickyBar.classList.add('show');
      } else {
        stickyBar.classList.remove('show');
      }
    };
    document.addEventListener('scroll', toggleSticky, { passive: true });
    toggleSticky();

    document.querySelectorAll('[data-quick-add]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-product-id') || '0', 10);
        const name = btn.getAttribute('data-product-name') || 'Produto';
        if (!id || typeof addToCart !== 'function') return;
        addToCart(id, name, 1);
      });
    });
  })();
</script>

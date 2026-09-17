<?php
$currentView = (string) ($_GET['view'] ?? 'all');
if (!in_array($currentView, ['all', 'open', 'settled', 'overdue'], true)) $currentView = 'all';
$hasAdvancedFilters = trim((string) ($_GET['q'] ?? '')) !== ''
    || (string) ($_GET['status'] ?? '') !== ''
    || (string) ($_GET['category'] ?? '') !== ''
    || (string) ($_GET['contact'] ?? '') !== ''
    || (string) ($_GET['payment_method'] ?? '') !== '';
$advancedId = 'advanced-filters-' . $listRoute;
?>
<form class="financial-filters card mb-5" method="get" action="<?=url()?>">
  <input type="hidden" name="route" value="<?=e($listRoute)?>">
  <input type="hidden" name="per_page" value="<?=$pagination['per_page']?>">

  <div class="financial-filter-topline">
    <fieldset>
      <legend>Filtros pré-definidos</legend>
      <div class="financial-status-filter" aria-label="Filtrar por situação">
        <?php foreach ([
          'all' => ['list', 'Todos', ''],
          'open' => ['clock-3', 'Em aberto', 'status-dot-open'],
          'settled' => ['circle-check', $settledFilterLabel, 'status-dot-settled'],
          'overdue' => ['circle-alert', 'Vencidos', 'status-dot-overdue'],
        ] as $value => [$icon, $label, $dotClass]): ?>
          <label class="financial-status-option <?=$currentView === $value ? 'is-active' : ''?>">
            <input class="sr-only" type="radio" name="view" value="<?=$value?>" <?=$currentView === $value ? 'checked' : ''?> onchange="this.form.requestSubmit()">
            <?php if ($dotClass): ?><span class="financial-status-dot <?=$dotClass?>"></span><?php else: ?><i data-lucide="<?=$icon?>"></i><?php endif; ?>
            <span><?=$label?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <button class="btn btn-light financial-filter-more" type="button" data-filter-toggle="<?=$advancedId?>" aria-expanded="<?=$hasAdvancedFilters ? 'true' : 'false'?>">
      <i data-lucide="sliders-horizontal"></i><span>Mais opções de busca</span><i class="financial-filter-chevron" data-lucide="chevron-down"></i>
    </button>
  </div>

  <div class="financial-date-grid">
    <?php foreach ([
      ['Data de emissão', 'issue_start', 'issue_end'],
      [$settlementDateLabel, 'settlement_start', 'settlement_end'],
      ['Data de vencimento', 'due_start', 'due_end'],
    ] as [$label, $startName, $endName]): ?>
      <div>
        <span class="label"><?=$label?></span>
        <div class="financial-date-range">
          <input type="date" name="<?=$startName?>" value="<?=e($_GET[$startName] ?? '')?>" aria-label="<?=$label?> inicial">
          <span aria-hidden="true">até</span>
          <input type="date" name="<?=$endName?>" value="<?=e($_GET[$endName] ?? '')?>" aria-label="<?=$label?> final">
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div id="<?=$advancedId?>" class="financial-advanced-filters <?=$hasAdvancedFilters ? '' : 'hidden'?>">
    <label class="financial-search-filter"><span class="label">Pesquisar</span><span class="financial-search-field"><i data-lucide="search"></i><input class="field" type="search" name="q" value="<?=e($search)?>" maxlength="100" placeholder="Descrição, <?=$partyLabel?>, documento, categoria ou observação"></span></label>
    <label><span class="label">Situação detalhada</span><select class="field" name="status"><option value="">Todas, exceto canceladas</option><?php foreach ($detailedStatuses as $value => $label): ?><option value="<?=$value?>" <?=($_GET['status'] ?? '') === $value ? 'selected' : ''?>><?=$label?></option><?php endforeach; ?></select></label>
    <label><span class="label"><?=$partyLabel?></span><select class="field" name="contact"><option value="">Todos</option><?php foreach ($contacts as $option): ?><option value="<?=$option['id']?>" <?=($_GET['contact'] ?? '') == $option['id'] ? 'selected' : ''?>><?=e($option['name'])?></option><?php endforeach; ?></select></label>
    <label><span class="label">Categoria</span><select class="field" name="category"><option value="">Todas</option><?php foreach ($categories as $option): ?><option value="<?=$option['id']?>" <?=($_GET['category'] ?? '') == $option['id'] ? 'selected' : ''?>><?=e($option['name'])?></option><?php endforeach; ?></select></label>
    <label><span class="label"><?=$methodLabel?></span><select class="field" name="payment_method"><option value="">Todas</option><?php foreach ($filterMethods as $value => $label): ?><option value="<?=$value?>" <?=($_GET['payment_method'] ?? '') === $value ? 'selected' : ''?>><?=$label?></option><?php endforeach; ?></select></label>
  </div>

  <div class="financial-filter-actions">
    <button class="btn btn-primary" type="submit"><i data-lucide="search"></i>Buscar</button>
    <a class="btn btn-light" href="<?=url($listRoute)?>"><i data-lucide="rotate-ccw"></i>Limpar filtros</a>
  </div>
</form>

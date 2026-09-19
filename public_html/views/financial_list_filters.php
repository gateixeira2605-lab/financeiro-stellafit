<?php
$currentView = (string) ($_GET['view'] ?? 'all');
if (!in_array($currentView, ['all', 'open', 'settled', 'overdue'], true)) $currentView = 'all';

$advancedFilterKeys = ['q', 'status', 'category', 'contact', 'payment_method'];
$hasAdvancedFilters = false;
foreach ($advancedFilterKeys as $filterKey) {
    if (trim((string) ($_GET[$filterKey] ?? '')) !== '') {
        $hasAdvancedFilters = true;
        break;
    }
}

$dateFilters = [
    ['Data de emissão', 'issue_start', 'issue_end'],
    [$settlementDateLabel, 'settlement_start', 'settlement_end'],
    ['Data de vencimento', 'due_start', 'due_end'],
];
$formatFilterDate = static function (string $date, bool $short = false): string {
    if ($date === '') return '';
    $parts = explode('-', $date);
    if (count($parts) !== 3) return $date;
    return $short ? $parts[2] . '/' . $parts[1] . '/' . substr($parts[0], -2) : $parts[2] . '/' . $parts[1] . '/' . $parts[0];
};
$dateSummary = static function (string $start, string $end) use ($formatFilterDate): string {
    if ($start === '' && $end === '') return 'Qualquer data';
    if ($start !== '' && $start === $end) return $formatFilterDate($start);
    if ($start !== '' && $end !== '') return $formatFilterDate($start, true) . ' a ' . $formatFilterDate($end, true);
    if ($start !== '') return 'A partir de ' . $formatFilterDate($start, true);
    return 'Até ' . $formatFilterDate($end, true);
};

$activeFilterCount = $currentView !== 'all' ? 1 : 0;
foreach ($advancedFilterKeys as $filterKey) {
    if (trim((string) ($_GET[$filterKey] ?? '')) !== '') $activeFilterCount++;
}
foreach ($dateFilters as [, $startName, $endName]) {
    if ((string) ($_GET[$startName] ?? '') !== '' || (string) ($_GET[$endName] ?? '') !== '') $activeFilterCount++;
}
$advancedId = 'advanced-filters-' . $listRoute;
?>
<form class="financial-filters card mb-5" method="get" action="<?=url()?>" data-auto-filter-form>
  <input type="hidden" name="route" value="<?=e($listRoute)?>">
  <input type="hidden" name="per_page" value="<?=$pagination['per_page']?>">
  <button class="sr-only" type="submit">Aplicar filtros</button>

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
            <input class="sr-only" type="radio" name="view" value="<?=$value?>" <?=$currentView === $value ? 'checked' : ''?> data-auto-submit>
            <?php if ($dotClass): ?><span class="financial-status-dot <?=$dotClass?>"></span><?php else: ?><i data-lucide="<?=$icon?>"></i><?php endif; ?>
            <span><?=$label?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <button class="btn btn-light financial-filter-more" type="button" data-filter-toggle="<?=$advancedId?>" aria-expanded="<?=$hasAdvancedFilters ? 'true' : 'false'?>">
      <i data-lucide="sliders-horizontal"></i><span>Mais opções de busca</span><?php if ($hasAdvancedFilters): ?><span class="financial-filter-count" aria-label="Filtros avançados ativos"><?=array_sum(array_map(static fn(string $key): int => trim((string) ($_GET[$key] ?? '')) !== '' ? 1 : 0, $advancedFilterKeys))?></span><?php endif; ?><i class="financial-filter-chevron" data-lucide="chevron-down"></i>
    </button>
  </div>

  <div class="financial-date-grid">
    <?php foreach ($dateFilters as $dateIndex => [$label, $startName, $endName]):
      $startValue = (string) ($_GET[$startName] ?? '');
      $endValue = (string) ($_GET[$endName] ?? '');
      $dateActive = $startValue !== '' || $endValue !== '';
      $datePopoverId = 'date-filter-' . $listRoute . '-' . $dateIndex;
    ?>
      <div class="financial-date-filter <?=$dateActive ? 'is-active' : ''?>" data-date-filter>
        <span class="label"><?=e($label)?></span>
        <button class="financial-date-trigger" type="button" data-date-trigger aria-expanded="false" aria-controls="<?=$datePopoverId?>">
          <i data-lucide="calendar-days"></i><span data-date-summary><?=e($dateSummary($startValue, $endValue))?></span><i class="financial-date-chevron" data-lucide="chevron-down"></i>
        </button>
        <div id="<?=$datePopoverId?>" class="financial-date-popover hidden" data-date-popover>
          <p class="financial-date-popover-title">Selecionar período</p>
          <div class="financial-date-presets">
            <button type="button" data-date-preset="any">Qualquer data</button>
            <button type="button" data-date-preset="today">Hoje</button>
            <button type="button" data-date-preset="week">Esta semana</button>
            <button type="button" data-date-preset="month">Este mês</button>
            <button type="button" data-date-preset="previous-month">Mês anterior</button>
            <button type="button" data-date-preset="next-30">Próximos 30 dias</button>
          </div>
          <button class="financial-custom-toggle" type="button" data-date-preset="custom" aria-expanded="false"><i data-lucide="calendar-range"></i>Período personalizado<i data-lucide="chevron-down"></i></button>
          <div class="financial-custom-range hidden" data-custom-range>
            <label><span>De</span><input class="field" type="date" name="<?=$startName?>" value="<?=e($startValue)?>" data-date-start></label>
            <label><span>Até</span><input class="field" type="date" name="<?=$endName?>" value="<?=e($endValue)?>" data-date-end></label>
            <div class="financial-custom-actions"><button class="btn btn-light" type="button" data-date-clear>Limpar</button><button class="btn btn-primary" type="button" data-date-apply>Aplicar período</button></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div id="<?=$advancedId?>" class="financial-advanced-filters <?=$hasAdvancedFilters ? '' : 'hidden'?>">
    <label class="financial-search-filter"><span class="label">Pesquisar</span><span class="financial-search-field"><i data-lucide="search"></i><input class="field" type="search" name="q" value="<?=e($search)?>" maxlength="100" placeholder="Descrição, <?=$partyLabel?>, documento, categoria ou observação" data-auto-search></span></label>
    <label><span class="label">Situação detalhada</span><select class="field" name="status" data-auto-submit><option value="">Todas, exceto canceladas</option><?php foreach ($detailedStatuses as $value => $label): ?><option value="<?=$value?>" <?=($_GET['status'] ?? '') === $value ? 'selected' : ''?>><?=$label?></option><?php endforeach; ?></select></label>
    <label><span class="label"><?=$partyLabel?></span><select class="field" name="contact" data-auto-submit><option value="">Todos</option><?php foreach ($contacts as $option): ?><option value="<?=$option['id']?>" <?=($_GET['contact'] ?? '') == $option['id'] ? 'selected' : ''?>><?=e($option['name'])?></option><?php endforeach; ?></select></label>
    <label><span class="label">Categoria</span><select class="field" name="category" data-auto-submit><option value="">Todas</option><?php foreach ($categories as $option): ?><option value="<?=$option['id']?>" <?=($_GET['category'] ?? '') == $option['id'] ? 'selected' : ''?>><?=e($option['name'])?></option><?php endforeach; ?></select></label>
    <label><span class="label"><?=$methodLabel?></span><select class="field" name="payment_method" data-auto-submit><option value="">Todas</option><?php foreach ($filterMethods as $value => $label): ?><option value="<?=$value?>" <?=($_GET['payment_method'] ?? '') === $value ? 'selected' : ''?>><?=$label?></option><?php endforeach; ?></select></label>
  </div>

  <div class="financial-filter-actions">
    <div class="financial-filter-state" aria-live="polite">
      <span class="financial-filter-spinner" aria-hidden="true"></span>
      <span data-filter-feedback><?=$activeFilterCount ? $activeFilterCount . ' filtro' . ($activeFilterCount === 1 ? '' : 's') . ' ativo' . ($activeFilterCount === 1 ? '' : 's') : 'A lista atualiza automaticamente'?></span>
    </div>
    <a class="btn btn-light" href="<?=url($listRoute)?>"><i data-lucide="rotate-ccw"></i>Limpar filtros</a>
    <noscript><button class="btn btn-primary" type="submit"><i data-lucide="search"></i>Aplicar filtros</button></noscript>
  </div>
</form>

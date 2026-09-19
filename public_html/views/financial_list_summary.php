<section class="mb-4 grid gap-3 md:grid-cols-3" aria-label="Resumo dos lançamentos filtrados">
  <article class="card p-4 text-right">
    <p class="text-sm text-slate-500">Total de títulos</p>
    <p class="mt-1 text-2xl font-bold"><?=money($totals['total_amount'])?></p>
  </article>
  <article class="rounded-2xl bg-emerald-500 p-4 text-right text-white shadow-sm">
    <p class="text-sm text-emerald-50"><?=e($totals['paid_label'])?></p>
    <p class="mt-1 text-2xl font-bold"><?=money($totals['paid_amount'])?></p>
  </article>
  <article class="rounded-2xl bg-red-500 p-4 text-right text-white shadow-sm">
    <p class="text-sm text-red-50">Total em aberto</p>
    <p class="mt-1 text-2xl font-bold"><?=money($totals['open_amount'])?></p>
  </article>
</section>

<div class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-3">
  <p class="text-sm text-slate-600 dark:text-slate-300">
    <?php if ($pagination['total_records']): ?>
      Mostrando <strong><?=$pagination['from']?></strong> a <strong><?=$pagination['to']?></strong> de <strong><?=$pagination['total_records']?></strong> registros
    <?php else: ?>
      Nenhum registro encontrado
    <?php endif; ?>
  </p>

  <div class="flex flex-wrap items-center gap-3">
    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
      <select class="field !w-auto !py-2" data-per-page aria-label="Quantidade de registros por página">
        <?php foreach ([10, 25, 50, 100] as $option): ?>
          <option value="<?=$option?>" <?=$pagination['per_page'] === $option ? 'selected' : ''?>><?=$option?></option>
        <?php endforeach; ?>
      </select>
      <span>por página</span>
    </label>

    <?php if ($pagination['total_pages'] > 1): ?>
      <nav class="flex overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700" aria-label="Paginação">
        <?php if ($pagination['page'] > 1): ?>
          <a class="px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800" href="<?=e(route_query_url($listRoute, ['page' => $pagination['page'] - 1, 'per_page' => $pagination['per_page']]))?>">Anterior</a>
        <?php else: ?>
          <span class="cursor-not-allowed px-3 py-2 text-sm text-slate-400">Anterior</span>
        <?php endif; ?>

        <?php foreach (pagination_pages($pagination['page'], $pagination['total_pages']) as $pageNumber): ?>
          <?php if ($pageNumber === null): ?>
            <span class="border-l border-slate-200 px-3 py-2 text-sm text-slate-400 dark:border-slate-700">…</span>
          <?php elseif ($pageNumber === $pagination['page']): ?>
            <span class="border-l border-teal-600 bg-teal-600 px-3 py-2 text-sm font-semibold text-white" aria-current="page"><?=$pageNumber?></span>
          <?php else: ?>
            <a class="border-l border-slate-200 px-3 py-2 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" href="<?=e(route_query_url($listRoute, ['page' => $pageNumber, 'per_page' => $pagination['per_page']]))?>"><?=$pageNumber?></a>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($pagination['page'] < $pagination['total_pages']): ?>
          <a class="border-l border-slate-200 px-3 py-2 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" href="<?=e(route_query_url($listRoute, ['page' => $pagination['page'] + 1, 'per_page' => $pagination['per_page']]))?>">Próximo</a>
        <?php else: ?>
          <span class="cursor-not-allowed border-l border-slate-200 px-3 py-2 text-sm text-slate-400 dark:border-slate-700">Próximo</span>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</div>

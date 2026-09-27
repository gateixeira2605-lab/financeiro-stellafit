<section class="mb-5 rounded-2xl bg-gradient-to-br from-teal-600 to-cyan-700 p-6 text-white shadow-lg">
  <div class="flex flex-wrap items-center justify-between gap-5">
    <div class="max-w-2xl"><div class="mb-2 flex items-center gap-2 text-sm font-semibold text-teal-100"><i data-lucide="wand-sparkles" class="h-4 w-4"></i>Classificação automática</div><h2 class="text-2xl font-bold">Você lança de forma simples. O sistema organiza os relatórios.</h2><p class="mt-2 text-sm text-teal-50">Nas receitas e despesas aparecem apenas as suas categorias do dia a dia. Contas técnicas, débitos e créditos trabalham em segundo plano.</p></div>
    <a class="btn border-white/30 bg-white text-teal-700 hover:bg-teal-50" href="<?=url('categories')?>"><i data-lucide="tags"></i>Organizar categorias</a>
  </div>
</section>

<div class="mb-5 grid gap-3 sm:grid-cols-3">
  <div class="card p-5"><div class="text-sm text-slate-500">Categorias prontas para usar</div><div class="mt-1 text-3xl font-bold text-teal-600"><?=e($stats['enabled_categories'])?></div></div>
  <div class="card p-5"><div class="text-sm text-slate-500">Lançamentos organizados</div><div class="mt-1 text-3xl font-bold"><?=e($stats['entries'])?></div></div>
  <div class="card p-5"><div class="text-sm text-slate-500">Conferência automática</div><div class="mt-2 flex items-center gap-2 font-semibold <?=$unbalanced?'text-red-600':'text-emerald-600'?>"><i data-lucide="<?=$unbalanced?'circle-alert':'circle-check'?>" class="h-5 w-5"></i><?=$unbalanced?e($unbalanced).' item(ns) para revisar':'Tudo conferido'?></div></div>
</div>

<div class="mb-6 grid gap-4 lg:grid-cols-2">
  <section class="card p-5"><div class="flex items-start gap-3"><div class="rounded-xl bg-teal-50 p-3 text-teal-600 dark:bg-teal-950/50"><i data-lucide="tags"></i></div><div><h2 class="font-semibold">Categorias dos lançamentos</h2><p class="mt-1 text-sm text-slate-500">Crie nomes simples como Aluguel, Energia, Mensalidade ou Material de limpeza. Você apenas informa o que representam.</p><a class="mt-3 inline-flex text-sm font-semibold text-teal-600" href="<?=url('categories')?>">Gerenciar categorias →</a></div></div></section>
  <section class="card p-5"><div class="flex items-start gap-3"><div class="rounded-xl bg-blue-50 p-3 text-blue-600 dark:bg-blue-950/50"><i data-lucide="building-2"></i></div><div><h2 class="font-semibold">Dados da empresa</h2><p class="mt-1 text-sm text-slate-500">Regime tributário: <strong><?=e(match($settings['tax_regime']){'SIMPLES'=>'Simples Nacional','PRESUMIDO'=>'Lucro Presumido','REAL'=>'Lucro Real',default=>'Ainda não definido'})?></strong></p><p class="mt-1 text-sm text-slate-500">Início dos relatórios: <strong><?=e($settings['accounting_start_date']?br_date($settings['accounting_start_date']):'a definir')?></strong></p><a class="mt-3 inline-flex text-sm font-semibold text-teal-600" href="<?=url('settings')?>">Atualizar dados →</a></div></div></section>
</div>

<details class="card overflow-hidden" <?=$q!==''?'open':''?>>
  <summary class="cursor-pointer p-5"><span class="font-semibold">Área técnica para contador ou implantação</span><span class="mt-1 block text-sm font-normal text-slate-500">Plano completo, migração do sistema antigo e modelos contábeis. Você não precisa acessar esta área para fazer lançamentos.</span></summary>
  <div class="space-y-6 border-t border-slate-200 p-5 dark:border-slate-700">
    <section id="contas" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
      <div class="border-b border-slate-200 p-4 dark:border-slate-700"><div class="flex flex-wrap items-end justify-between gap-3"><div><h3 class="font-semibold">Plano de contas completo</h3><p class="text-sm text-slate-500"><?=e($stats['accounts'])?> contas estruturadas; somente contas analíticas recebem movimentos.</p></div><form class="flex gap-2"><input type="hidden" name="route" value="accounting"><input class="field" name="q" value="<?=e($q)?>" placeholder="Buscar conta"><button class="btn btn-light">Buscar</button></form></div></div>
      <div class="table-wrap max-h-[620px]"><table class="data-table"><thead class="sticky top-0 bg-white dark:bg-slate-900"><tr><th>Código</th><th>Conta</th><th>Tipo</th><th>Relatório</th><th>Situação</th><th>Ação</th></tr></thead><tbody><?php foreach($accounts as $a):?><tr><td class="font-mono text-xs"><?=e($a['code'])?></td><td style="padding-left:<?=12+max(0,(int)$a['level']-1)*16?>px"><div class="font-medium"><?=e($a['name'])?></div></td><td><?=e($a['account_type']==='ANALITICA'?'Movimentação':'Agrupadora')?></td><td class="text-xs"><?=e($a['report_line']?:'—')?></td><td><?=$a['active']?'Ativa':'Inativa'?></td><td><form method="post" action="<?=url('accounting/toggle-account')?>"><?=csrf_field()?><input type="hidden" name="account_id" value="<?=e($a['account_id'])?>"><input type="hidden" name="field" value="active"><button class="text-xs font-semibold text-teal-600"><?=$a['active']?'Desativar':'Ativar'?></button></form></td></tr><?php endforeach;?></tbody></table></div>
    </section>

    <section id="legado" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
      <div class="border-b border-slate-200 p-4 dark:border-slate-700"><h3 class="font-semibold">Migração das categorias antigas</h3><p class="text-sm text-slate-500"><?=e($stats['pending_legacy'])?> vínculo(s) ainda precisam de revisão profissional. Aprovar um vínculo não importa valores antigos.</p></div>
      <form method="post" action="<?=url('accounting/review-legacy')?>" class="grid gap-3 border-b border-slate-200 p-4 dark:border-slate-700 md:grid-cols-3"><?=csrf_field()?>
        <label><span class="label">Categoria antiga</span><select class="field" name="mapping_id" required><option value="">Selecione</option><?php foreach($legacy as $l):if($l['automatic_import']||$l['review_status']!=='PENDENTE')continue;?><option value="<?=$l['id']?>"><?=e($l['legacy_name'])?></option><?php endforeach;?></select></label>
        <label><span class="label">Conta de destino</span><select class="field" name="account_id" required><option value="">Selecione</option><?php foreach($postingAccounts as $a):?><option value="<?=e($a['account_id'])?>"><?=e($a['name'])?></option><?php endforeach;?></select></label>
        <label><span class="label">Grupo do fluxo de caixa</span><select class="field" name="financial_category_id" required><option value="">Selecione</option><?php foreach($financialCategories as $f):?><option value="<?=e($f['category_id'])?>"><?=e($f['name'])?></option><?php endforeach;?></select></label>
        <div class="md:col-span-3"><button class="btn btn-primary">Confirmar vínculo</button></div>
      </form>
      <div class="table-wrap max-h-[420px]"><table class="data-table"><thead><tr><th>Categoria antiga</th><th>Destino</th><th>Revisão</th></tr></thead><tbody><?php foreach($legacy as $l):?><tr><td><div class="font-medium"><?=e($l['legacy_name'])?></div><div class="text-xs text-slate-500"><?=e($l['guidance'])?></div></td><td><?=e($l['target_name']?:'Ainda não definido')?></td><td><?=e(match($l['review_status']){'APROVADO'=>'Aprovado','BLOQUEADO'=>'Exige análise',default=>'Pendente'})?></td></tr><?php endforeach;?></tbody></table></div>
    </section>

    <details id="templates" class="rounded-xl border border-slate-200 dark:border-slate-700"><summary class="cursor-pointer p-4 font-semibold">Modelos técnicos de lançamentos (<?=count($templates)?>)</summary><div class="table-wrap border-t border-slate-200 dark:border-slate-700"><table class="data-table"><thead><tr><th>Evento</th><th>Débito</th><th>Crédito</th><th>Situação</th></tr></thead><tbody><?php foreach($templates as $t):?><tr><td><?=e($t['event_id'])?></td><td><?=e($t['debit_name'])?></td><td><?=e($t['credit_name'])?></td><td>Aguardando configuração</td></tr><?php endforeach;?></tbody></table></div></details>
  </div>
</details>

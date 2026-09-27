<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <div>
    <p class="font-medium"><?=count($categories)?> categorias cadastradas</p>
    <p class="text-sm text-slate-500">São estes nomes simples que aparecem ao registrar receitas e despesas.</p>
  </div>
  <a class="btn btn-primary" href="<?=url('categories/form')?>"><i data-lucide="plus"></i>Nova categoria</a>
</div>

<div class="mb-5 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-100">
  <div class="flex gap-3"><i data-lucide="sparkles" class="mt-0.5 h-5 w-5 shrink-0"></i><div><strong>Você escolhe o nome; o sistema faz a parte contábil.</strong><p class="mt-1 text-teal-800 dark:text-teal-200">Ao criar uma categoria, basta informar o que ela representa. A conta correta, a DRE e o fluxo de caixa são configurados automaticamente.</p></div></div>
</div>

<div class="card table-wrap">
  <table class="data-table">
    <thead><tr><th>Nome que aparece no lançamento</th><th>Usada para</th><th>Tratamento automático</th><th>Situação</th><th class="text-right">Ações</th></tr></thead>
    <tbody>
    <?php foreach($categories as $c):?>
      <tr>
        <td class="font-medium"><?=e($c['name'])?></td>
        <td><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"><?=e($c['purpose_group'])?></span></td>
        <td><div class="font-medium"><?=e($c['purpose_label'])?></div><div class="text-xs text-slate-500">Relatórios e contabilidade automáticos</div></td>
        <td><span class="rounded-full px-2 py-1 text-xs font-semibold <?=$c['active']&&$c['accounting_enabled']?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500'?>"><?=$c['active']&&$c['accounting_enabled']?'Disponível':'Oculta'?></span></td>
        <td><div class="flex justify-end gap-2"><a class="btn btn-light !p-2" href="<?=url('categories/form')?>&id=<?=$c['id']?>" aria-label="Editar <?=e($c['name'])?>"><i data-lucide="pencil"></i></a><form method="post" action="<?=url('categories/delete')?>" data-confirm="Excluir esta categoria?"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn btn-light !p-2 text-red-600" aria-label="Excluir <?=e($c['name'])?>"><i data-lucide="trash-2"></i></button></form></div></td>
      </tr>
    <?php endforeach;?>
    </tbody>
  </table>
</div>

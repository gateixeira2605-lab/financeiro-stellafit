<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <div><p class="text-sm text-slate-500"><?=count($accounts)?> conta(s) cadastrada(s)</p><p class="text-2xl font-bold text-teal-600"><?=money($totalBalance)?></p></div>
  <button id="newBankBtn" type="button" class="btn btn-primary"><i data-lucide="plus"></i>Nova conta bancária</button>
</div>

<?php if (!$accounts): ?>
  <section class="card grid place-items-center px-5 py-14 text-center"><div class="mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-teal-50 text-teal-600 dark:bg-teal-950/40"><i data-lucide="landmark"></i></div><h2 class="text-lg font-semibold">Cadastre sua primeira conta bancária</h2><p class="mt-1 max-w-md text-sm text-slate-500">Informe o banco e o saldo atual. As próximas baixas atualizarão esse saldo automaticamente.</p></section>
<?php else: ?>
  <section class="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($accounts as $account): $payload = json_encode(['id'=>$account['id'],'name'=>$account['name'],'opening_balance'=>$account['opening_balance'],'notes'=>$account['notes']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
      <article class="card p-5 <?=$account['active'] ? '' : 'opacity-60'?>">
        <div class="flex items-start justify-between gap-3"><div class="grid h-10 w-10 place-items-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-950/40"><i data-lucide="landmark"></i></div><span class="rounded-full px-2 py-1 text-xs <?=$account['active'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'?>"><?=$account['active'] ? 'Ativa' : 'Inativa'?></span></div>
        <h2 class="mt-4 font-semibold"><?=e($account['name'])?></h2>
        <p class="mt-1 text-2xl font-bold <?=((float)$account['balance']) < 0 ? 'text-red-600' : 'text-slate-900 dark:text-white'?>"><?=money($account['balance'])?></p>
        <p class="mt-1 text-xs text-slate-500">Saldo inicial: <?=money($account['opening_balance'])?></p>
        <div class="mt-4 flex flex-wrap gap-2">
          <?php if ($account['active']): ?><button type="button" class="btn btn-primary bank-adjust-btn" data-id="<?=$account['id']?>" data-name="<?=e($account['name'])?>" data-balance="<?=e($account['balance'])?>"><i data-lucide="scale"></i>Ajustar saldo</button><?php endif; ?>
          <button type="button" class="btn btn-light bank-edit-btn" data-bank="<?=e($payload)?>"><i data-lucide="pencil"></i>Editar</button>
          <form method="post" action="<?=url('banks/toggle')?>"><?=csrf_field()?><input type="hidden" name="id" value="<?=$account['id']?>"><button class="btn btn-light"><?=$account['active'] ? 'Desativar' : 'Ativar'?></button></form>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<section class="card table-wrap">
  <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold">Histórico de ajustes</h2><p class="text-xs text-slate-500">Ajustes manuais feitos para igualar o sistema ao saldo real do banco.</p></div>
  <table class="data-table"><thead><tr><th>Data</th><th>Banco</th><th>Categoria</th><th>Ajuste</th><th>Observação</th><th>Responsável</th></tr></thead><tbody>
    <?php foreach ($adjustments as $adjustment): ?><tr><td><?=br_date($adjustment['adjustment_date'])?></td><td class="font-medium"><?=e($adjustment['bank_name'])?></td><td><?=e($adjustment['category_name'])?></td><td class="font-semibold <?=((float)$adjustment['amount']) < 0 ? 'text-red-600' : 'text-emerald-600'?>"><?=((float)$adjustment['amount']) > 0 ? '+' : ''?><?=money($adjustment['amount'])?></td><td><?=e($adjustment['notes'] ?: '—')?></td><td><?=e($adjustment['user_name'])?></td></tr><?php endforeach; ?>
    <?php if (!$adjustments): ?><tr><td colspan="6" class="text-center text-slate-500">Nenhum ajuste realizado.</td></tr><?php endif; ?>
  </tbody></table>
</section>

<dialog id="bankFormDialog" class="w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form id="bankForm" method="post" action="<?=url('banks/save')?>" class="space-y-4 p-5"><?=csrf_field()?><input id="bankId" type="hidden" name="id"><div class="flex items-center justify-between"><h2 id="bankFormTitle" class="text-lg font-semibold">Nova conta bancária</h2><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="bankFormDialog"><i data-lucide="x"></i></button></div><label><span class="label">Nome da conta</span><input id="bankName" class="field" name="name" placeholder="Ex.: Itaú principal, Caixa, Nubank" maxlength="120" required></label><label><span class="label">Saldo inicial</span><input id="bankOpeningBalance" class="field" name="opening_balance" inputmode="decimal" value="0,00" required><span class="mt-1 block text-xs text-slate-500">Use o saldo real disponível no momento do cadastro.</span></label><label><span class="label">Observação</span><input id="bankNotes" class="field" name="notes" maxlength="255"></label><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="bankFormDialog">Cancelar</button><button class="btn btn-primary">Salvar conta</button></div></form>
</dialog>

<dialog id="bankAdjustDialog" class="w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form method="post" action="<?=url('banks/adjust')?>" class="space-y-4 p-5"><?=csrf_field()?><input id="adjustBankId" type="hidden" name="bank_account_id"><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Ajustar saldo</h2><p id="adjustBankName" class="text-xs text-slate-500"></p></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="bankAdjustDialog"><i data-lucide="x"></i></button></div><div class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800">Saldo calculado: <strong id="adjustCurrentBalance"></strong></div><label><span class="label">Saldo real no banco</span><input id="adjustTargetBalance" class="field" name="target_balance" inputmode="decimal" required><span class="mt-1 block text-xs text-slate-500">O sistema lançará automaticamente apenas a diferença.</span></label><label><span class="label">Categoria do ajuste</span><select class="field" name="category_id" required><option value="">Selecione</option><?php foreach ($adjustmentCategories as $category): ?><option value="<?=$category['id']?>"><?=e($category['name'])?></option><?php endforeach; ?></select><?php if (!$adjustmentCategories): ?><span class="mt-1 block text-xs text-red-600">Crie uma categoria classificada como “Ajuste de saldo bancário”.</span><?php endif; ?></label><label><span class="label">Data</span><input class="field" type="date" name="adjustment_date" value="<?=date('Y-m-d')?>" required></label><label><span class="label">Motivo ou observação</span><input class="field" name="notes" maxlength="255" placeholder="Ex.: tarifa bancária, correção de implantação"></label><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="bankAdjustDialog">Cancelar</button><button class="btn btn-primary">Aplicar ajuste</button></div></form>
</dialog>

<?php $pageScripts = <<<'HTML'
<script>
(() => {
  const formDialog=document.getElementById('bankFormDialog'),form=document.getElementById('bankForm');
  const id=document.getElementById('bankId'),name=document.getElementById('bankName'),opening=document.getElementById('bankOpeningBalance'),notes=document.getElementById('bankNotes'),title=document.getElementById('bankFormTitle');
  document.getElementById('newBankBtn').addEventListener('click',()=>{form.reset();id.value='';opening.value='0,00';opening.readOnly=false;title.textContent='Nova conta bancária';formDialog.showModal();});
  document.querySelectorAll('.bank-edit-btn').forEach(button=>button.addEventListener('click',()=>{const bank=JSON.parse(button.dataset.bank);form.reset();id.value=bank.id;name.value=bank.name;opening.value=String(bank.opening_balance).replace('.',',');opening.readOnly=true;notes.value=bank.notes||'';title.textContent='Editar conta bancária';formDialog.showModal();}));
  document.querySelectorAll('.bank-adjust-btn').forEach(button=>button.addEventListener('click',()=>{const dialog=document.getElementById('bankAdjustDialog'),balance=Number(button.dataset.balance);dialog.querySelector('form').reset();document.getElementById('adjustBankId').value=button.dataset.id;document.getElementById('adjustBankName').textContent=button.dataset.name;document.getElementById('adjustCurrentBalance').textContent=formatMoney(balance);document.getElementById('adjustTargetBalance').value=balance.toFixed(2).replace('.',',');dialog.showModal();}));
})();
</script>
HTML; ?>

<div class="mb-4 flex justify-end"><button id="newReceivableBtn" type="button" class="btn btn-primary"><i data-lucide="plus"></i>Nova receita</button></div>

<?php
$listRoute = 'receivables';
$settledFilterLabel = 'Recebido';
$settlementDateLabel = 'Data de recebimento';
$partyLabel = 'Cliente';
$methodLabel = 'Forma de recebimento';
$detailedStatuses = ['pendente' => 'Pendente', 'vencido' => 'Vencido', 'parcial' => 'Parcial', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado'];
$filterMethods = ['pix' => 'Pix', 'cartao_credito' => 'Cartão de crédito', 'cartao_debito' => 'Cartão de débito', 'boleto' => 'Boleto', 'dinheiro' => 'Dinheiro', 'transferencia' => 'Transferência'];
require __DIR__ . '/../financial_list_filters.php';
?>

<div data-bulk-selection>
<div class="card mb-3 flex flex-wrap items-center gap-2 p-3">
  <span class="mr-auto text-sm text-slate-500"><strong data-selected-count>0</strong> selecionado(s)</span>
  <button type="button" class="btn btn-primary" data-bulk-action data-bulk-open="receivablesBulkReceiveDialog" disabled><i data-lucide="banknote"></i>Baixar selecionados</button>
  <button type="button" class="btn btn-primary" data-bulk-action data-bulk-open="receivablesBulkEditDialog" disabled><i data-lucide="pencil"></i>Editar selecionados</button>
  <button type="button" class="btn bg-red-500 text-white hover:bg-red-600" data-bulk-action data-bulk-open="receivablesBulkDeleteDialog" disabled><i data-lucide="trash-2"></i>Cancelar selecionados</button>
</div>

<div class="card table-wrap">
  <table class="data-table">
    <thead><tr><th><input type="checkbox" class="h-4 w-4 accent-teal-600" data-select-all aria-label="Selecionar todos os lançamentos exibidos"></th><th>Situação</th><th>Categoria</th><th>Cliente</th><th>Vencimento</th><th>Total</th><th>Recebido</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach ($items as $i):
        $badge = status_badge($i['status'], $i['due_date']);
        $editData = json_encode(array_intersect_key($i, array_flip(['id', 'description', 'contact_id', 'category_id', 'expected_amount', 'due_date', 'receipt_method', 'notes'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      ?>
        <tr>
          <td><input type="checkbox" class="h-4 w-4 accent-teal-600" data-select-item value="<?=$i['id']?>" aria-label="Selecionar lançamento"></td>
          <td><span class="rounded-full px-2 py-1 text-xs font-semibold <?=$badge[1]?>"><?=$badge[0]?></span></td>
          <td class="font-medium"><?=e($i['category_name'] ?: 'Sem categoria')?></td>
          <td><?=e($i['contact_name'] ?: 'Sem cliente')?></td>
          <td><?=br_date($i['due_date'])?></td>
          <td class="font-semibold"><?=money($i['expected_amount'])?></td>
          <td class="text-teal-600"><?=money($i['received_amount'])?></td>
          <td>
            <div class="relative inline-block">
              <button type="button" class="btn btn-light !p-2" data-menu-toggle aria-label="Abrir ações"><i data-lucide="chevron-down"></i></button>
              <div class="fixed z-50 hidden w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900" data-action-menu>
                <button type="button" class="receivable-edit flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-item="<?=e($editData)?>"><i data-lucide="pencil"></i>Editar</button>
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-movement-details data-details-url="<?=url('movement/details')?>" data-entity="receivable" data-id="<?=$i['id']?>"><i data-lucide="search"></i>Detalhes</button>
                <?php if (!in_array($i['status'], ['recebido', 'cancelado'], true) && (float) $i['remaining_amount'] > 0): ?><button type="button" class="receivable-receive flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-id="<?=$i['id']?>" data-description="<?=e($i['description'])?>" data-remaining="<?=e($i['remaining_amount'])?>" data-method="<?=e($i['receipt_method'])?>"><i data-lucide="banknote"></i>Baixar</button><?php endif; ?>
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-movement-log data-details-url="<?=url('movement/details')?>" data-entity="receivable" data-id="<?=$i['id']?>"><i data-lucide="history"></i>Log de alterações</button>
                <?php if ($i['status'] !== 'cancelado'): ?><form method="post" action="<?=url('receivables/delete')?>" data-confirm="Cancelar esta conta? Ela sairá das listagens e dos cálculos, mas o histórico será preservado."><?=csrf_field()?><input type="hidden" name="id" value="<?=$i['id']?>"><button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"><i data-lucide="trash-2"></i>Cancelar</button></form><?php endif; ?>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="8" class="text-center text-slate-500">Nenhuma conta encontrada.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
</div>

<?php require __DIR__ . '/../financial_list_summary.php'; ?>

<dialog id="receivableFormDialog" class="w-[calc(100%-2rem)] max-w-2xl rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form id="receivableModalForm" method="post" action="<?=url('receivables/save')?>" class="flex max-h-[90vh] flex-col"><?=csrf_field()?>
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700"><div><h2 id="receivableFormTitle" class="text-lg font-semibold">Nova receita</h2><p class="text-xs text-slate-500">Cadastro rápido e objetivo.</p></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="receivableFormDialog"><i data-lucide="x"></i></button></div>
    <div class="grid gap-4 overflow-y-auto p-5 sm:grid-cols-2">
      <input id="rfId" type="hidden" name="id">
      <label class="sm:col-span-2"><span class="label">Descrição</span><input id="rfDescription" class="field" name="description" required></label>
      <label><span class="label">Cliente</span><select id="rfContact" class="field" name="contact_id"><option value="">Selecione</option><?php foreach ($contacts as $o): ?><option value="<?=$o['id']?>"><?=e($o['name'])?></option><?php endforeach; ?></select></label>
      <label><span class="label">Categoria</span><select id="rfCategory" class="field" name="category_id"><option value="">Selecione</option><?php foreach ($categories as $o): ?><option value="<?=$o['id']?>"><?=e($o['name'])?></option><?php endforeach; ?></select></label>
      <label><span class="label">Valor esperado</span><input id="rfAmount" class="field" name="expected_amount" inputmode="decimal" placeholder="0,00" required></label>
      <label><span class="label">Vencimento</span><input id="rfDue" class="field" type="date" name="due_date" value="<?=date('Y-m-d')?>" required></label>
      <label><span class="label">Forma de recebimento</span><select id="rfMethod" class="field" name="receipt_method"><?php foreach (['pix' => 'Pix', 'cartao_credito' => 'Cartão crédito', 'cartao_debito' => 'Cartão débito', 'boleto' => 'Boleto', 'dinheiro' => 'Dinheiro', 'transferencia' => 'Transferência'] as $v => $l): ?><option value="<?=$v?>"><?=$l?></option><?php endforeach; ?></select></label>
      <label class="sm:col-span-2"><span class="label">Observações (opcional)</span><textarea id="rfNotes" class="field" name="notes" rows="2"></textarea></label>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700"><button type="button" class="btn btn-light" data-modal-close="receivableFormDialog">Fechar</button><button class="btn btn-primary">Salvar</button></div>
  </form>
</dialog>

<dialog id="receiveDialog" class="w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form method="post" action="<?=url('receivables/receive')?>" class="space-y-4 p-5"><?=csrf_field()?><input id="receiveId" type="hidden" name="id"><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Baixar receita</h2><p id="receiveDescription" class="text-xs text-slate-500"></p></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="receiveDialog"><i data-lucide="x"></i></button></div><div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Saldo restante: <strong id="receiveRemaining"></strong></div><label><span class="label">Valor que será recebido</span><input id="receiveAmount" class="field" name="received_amount" inputmode="decimal" required></label><div class="grid gap-4 sm:grid-cols-2"><label><span class="label">Forma de recebimento</span><select id="receiveMethod" class="field" name="receipt_method" required><?php foreach (['pix'=>'Pix','cartao_credito'=>'Cartão de crédito','cartao_debito'=>'Cartão de débito','boleto'=>'Boleto','dinheiro'=>'Dinheiro','transferencia'=>'Transferência'] as $value=>$label): ?><option value="<?=$value?>"><?=$label?></option><?php endforeach; ?></select></label><label><span class="label">Entra na conta</span><select class="field" name="bank_account_id" required><option value="">Selecione o banco</option><?php foreach ($bankAccounts as $account): ?><option value="<?=$account['id']?>"><?=e($account['name'])?> · <?=money($account['balance'])?></option><?php endforeach; ?></select></label></div><?php if (!$bankAccounts): ?><a class="block text-sm font-medium text-teal-600" href="<?=url('banks')?>">Cadastre uma conta bancária antes de dar baixa.</a><?php endif; ?><label><span class="label">Data do recebimento</span><input class="field" type="date" name="receipt_date" value="<?=date('Y-m-d')?>" required></label><label><span class="label">Observação (opcional)</span><input class="field" name="receipt_notes" maxlength="255"></label><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="receiveDialog">Cancelar</button><button class="btn btn-primary">Registrar recebimento</button></div></form>
</dialog>

<dialog id="receivablesBulkReceiveDialog" class="w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form method="post" action="<?=url('receivables/bulk-receive')?>" class="space-y-4 p-5"><?=csrf_field()?><div data-bulk-ids></div><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Baixar receitas selecionadas</h2><p class="text-xs text-slate-500"><strong data-bulk-dialog-count></strong> lançamento(s) selecionado(s)</p></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="receivablesBulkReceiveDialog"><i data-lucide="x"></i></button></div><div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Cada receita será quitada pelo seu saldo restante. Contas recebidas ou canceladas serão ignoradas.</div><div class="grid gap-4 sm:grid-cols-2"><label><span class="label">Forma de recebimento</span><select class="field" name="receipt_method" required><?php foreach (['pix'=>'Pix','cartao_credito'=>'Cartão de crédito','cartao_debito'=>'Cartão de débito','boleto'=>'Boleto','dinheiro'=>'Dinheiro','transferencia'=>'Transferência'] as $value=>$label): ?><option value="<?=$value?>"><?=$label?></option><?php endforeach; ?></select></label><label><span class="label">Entra na conta</span><select class="field" name="bank_account_id" required><option value="">Selecione o banco</option><?php foreach ($bankAccounts as $account): ?><option value="<?=$account['id']?>"><?=e($account['name'])?> · <?=money($account['balance'])?></option><?php endforeach; ?></select></label></div><label><span class="label">Data do recebimento</span><input class="field" type="date" name="receipt_date" value="<?=date('Y-m-d')?>" required></label><label><span class="label">Observação (opcional)</span><input class="field" name="receipt_notes" maxlength="255"></label><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="receivablesBulkReceiveDialog">Voltar</button><button class="btn btn-primary">Confirmar baixas</button></div></form>
</dialog>

<dialog id="receivablesBulkEditDialog" class="w-[calc(100%-2rem)] max-w-xl rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form method="post" action="<?=url('receivables/bulk-edit')?>" class="space-y-4 p-5"><?=csrf_field()?><div data-bulk-ids></div><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Editar receitas selecionadas</h2><p class="text-xs text-slate-500"><strong data-bulk-dialog-count></strong> lançamento(s); campos sem alteração serão preservados</p></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="receivablesBulkEditDialog"><i data-lucide="x"></i></button></div><div class="grid gap-4 sm:grid-cols-2"><label><span class="label">Categoria</span><select class="field" name="category_id"><option value="__keep__">Não alterar</option><option value="0">Remover categoria</option><?php foreach ($categories as $o): ?><option value="<?=$o['id']?>"><?=e($o['name'])?></option><?php endforeach; ?></select></label><label><span class="label">Cliente</span><select class="field" name="contact_id"><option value="__keep__">Não alterar</option><option value="0">Remover cliente</option><?php foreach ($contacts as $o): ?><option value="<?=$o['id']?>"><?=e($o['name'])?></option><?php endforeach; ?></select></label><label><span class="label">Novo vencimento</span><input class="field" type="date" name="due_date"></label><label><span class="label">Forma de recebimento</span><select class="field" name="receipt_method"><option value="__keep__">Não alterar</option><?php foreach (['pix' => 'Pix', 'cartao_credito' => 'Cartão crédito', 'cartao_debito' => 'Cartão débito', 'boleto' => 'Boleto', 'dinheiro' => 'Dinheiro', 'transferencia' => 'Transferência'] as $v => $l): ?><option value="<?=$v?>"><?=$l?></option><?php endforeach; ?></select></label></div><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="receivablesBulkEditDialog">Voltar</button><button class="btn btn-primary">Aplicar alterações</button></div></form>
</dialog>

<dialog id="receivablesBulkDeleteDialog" class="w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form method="post" action="<?=url('receivables/bulk-delete')?>" class="space-y-4 p-5"><?=csrf_field()?><div data-bulk-ids></div><div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Cancelar receitas selecionadas</h2><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="receivablesBulkDeleteDialog"><i data-lucide="x"></i></button></div><p class="text-sm text-slate-600 dark:text-slate-300">Você está prestes a cancelar <strong data-bulk-dialog-count></strong> lançamento(s). Eles sairão dos cálculos, mas permanecerão no histórico.</p><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="receivablesBulkDeleteDialog">Voltar</button><button class="btn bg-red-500 text-white hover:bg-red-600">Confirmar cancelamento</button></div></form>
</dialog>

<?php $pageScripts = <<<'HTML'
<script>
(() => {
  const dialog=document.getElementById('receivableFormDialog'),form=document.getElementById('receivableModalForm');
  const fields={id:document.getElementById('rfId'),description:document.getElementById('rfDescription'),contact_id:document.getElementById('rfContact'),category_id:document.getElementById('rfCategory'),expected_amount:document.getElementById('rfAmount'),due_date:document.getElementById('rfDue'),receipt_method:document.getElementById('rfMethod'),notes:document.getElementById('rfNotes')};
  document.getElementById('newReceivableBtn').addEventListener('click',()=>{closeActionMenus();form.reset();fields.id.value='';document.getElementById('receivableFormTitle').textContent='Nova receita';dialog.showModal();});
  document.querySelectorAll('.receivable-edit').forEach(button=>button.addEventListener('click',()=>{closeActionMenus();const item=JSON.parse(button.dataset.item);form.reset();Object.entries(fields).forEach(([key,field])=>field.value=item[key]??'');document.getElementById('receivableFormTitle').textContent='Editar receita';dialog.showModal();}));
  document.querySelectorAll('.receivable-receive').forEach(button=>button.addEventListener('click',()=>{closeActionMenus();const receiveDialog=document.getElementById('receiveDialog');receiveDialog.querySelector('form').reset();document.getElementById('receiveId').value=button.dataset.id;document.getElementById('receiveDescription').textContent=button.dataset.description;document.getElementById('receiveRemaining').textContent=formatMoney(button.dataset.remaining);document.getElementById('receiveAmount').value=String(button.dataset.remaining).replace('.',',');document.getElementById('receiveMethod').value=button.dataset.method;receiveDialog.showModal();}));
})();
</script>
HTML; ?>

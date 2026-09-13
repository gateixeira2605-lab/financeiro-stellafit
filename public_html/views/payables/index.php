<div class="mb-4 flex justify-end"><button id="newPayableBtn" type="button" class="btn btn-primary"><i data-lucide="plus"></i>Nova despesa</button></div>

<form class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-9">
  <input type="hidden" name="route" value="payables">
  <input type="hidden" name="per_page" value="<?=$pagination['per_page']?>">
  <div class="relative sm:col-span-2 xl:col-span-2"><i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i><input class="field !pl-10" type="search" name="q" value="<?=e($search)?>" maxlength="100" aria-label="Pesquisar contas a pagar" placeholder="Pesquisar descrição, fornecedor, CPF/CNPJ, categoria ou observação"></div>
  <select class="field" name="status"><option value="">Status</option><?php foreach (['pendente' => 'Pendente', 'vencido' => 'Vencido', 'parcial' => 'Parcial', 'pago' => 'Pago', 'cancelado' => 'Cancelado'] as $v => $l): ?><option value="<?=$v?>" <?=($_GET['status'] ?? '') === $v ? 'selected' : ''?>><?=$l?></option><?php endforeach; ?></select>
  <select class="field" name="category"><option value="">Categoria</option><?php foreach ($categories as $o): ?><option value="<?=$o['id']?>" <?=($_GET['category'] ?? '') == $o['id'] ? 'selected' : ''?>><?=e($o['name'])?></option><?php endforeach; ?></select>
  <select class="field" name="contact"><option value="">Fornecedor</option><?php foreach ($contacts as $o): ?><option value="<?=$o['id']?>" <?=($_GET['contact'] ?? '') == $o['id'] ? 'selected' : ''?>><?=e($o['name'])?></option><?php endforeach; ?></select>
  <select class="field" name="payment_method"><option value="">Forma</option><?php foreach (['pix' => 'Pix', 'boleto' => 'Boleto', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro', 'debito_automatico' => 'Débito automático', 'transferencia' => 'Transferência'] as $v => $l): ?><option value="<?=$v?>" <?=($_GET['payment_method'] ?? '') === $v ? 'selected' : ''?>><?=$l?></option><?php endforeach; ?></select>
  <input class="field" type="date" name="start" value="<?=e($_GET['start'] ?? '')?>">
  <input class="field" type="date" name="end" value="<?=e($_GET['end'] ?? '')?>">
  <button class="btn btn-light"><i data-lucide="list-filter"></i>Filtrar</button>
</form>

<?php $listRoute = 'payables'; require __DIR__ . '/../financial_list_summary.php'; ?>

<div data-bulk-selection>
<div class="card mb-3 flex flex-wrap items-center gap-2 p-3">
  <span class="mr-auto text-sm text-slate-500"><strong data-selected-count>0</strong> selecionado(s)</span>
  <button type="button" class="btn btn-primary" data-bulk-action data-bulk-open="payablesBulkPayDialog" disabled><i data-lucide="banknote"></i>Baixar selecionados</button>
  <button type="button" class="btn btn-primary" data-bulk-action data-bulk-open="payablesBulkEditDialog" disabled><i data-lucide="pencil"></i>Editar selecionados</button>
  <button type="button" class="btn bg-red-500 text-white hover:bg-red-600" data-bulk-action data-bulk-open="payablesBulkDeleteDialog" disabled><i data-lucide="trash-2"></i>Cancelar selecionados</button>
</div>

<div class="card table-wrap">
  <table class="data-table">
    <thead><tr><th><input type="checkbox" class="h-4 w-4 accent-teal-600" data-select-all aria-label="Selecionar todos os lançamentos exibidos"></th><th>Situação</th><th>Categoria</th><th>Fornecedor</th><th>Parcela</th><th>Vencimento</th><th>Total</th><th>Pago</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach ($items as $i):
        $badge = status_badge($i['status'], $i['due_date']);
        $editData = json_encode(array_intersect_key($i, array_flip(['id', 'description', 'contact_id', 'category_id', 'amount', 'due_date', 'payment_method', 'recurrence', 'notes', 'installment_number', 'installment_count'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      ?>
        <tr>
          <td><input type="checkbox" class="h-4 w-4 accent-teal-600" data-select-item value="<?=$i['id']?>" aria-label="Selecionar lançamento"></td>
          <td><span class="rounded-full px-2 py-1 text-xs font-semibold <?=$badge[1]?>"><?=$badge[0]?></span><?php if ($i['is_scheduled']): ?><span class="ml-1 text-xs text-blue-600">Agendada</span><?php endif; ?></td>
          <td class="font-medium"><?=e($i['category_name'] ?: 'Sem categoria')?><?php if ($i['attachment_id']): ?><a class="ml-2 text-teal-600" title="Baixar anexo" href="<?=url('attachment')?>&id=<?=$i['attachment_id']?>"><i class="inline" data-lucide="paperclip"></i></a><?php endif; ?></td>
          <td><?=e($i['contact_name'] ?: 'Sem fornecedor')?></td>
          <td><?=!empty($i['installment_count']) ? e($i['installment_number']) . '/' . e($i['installment_count']) : '—'?></td>
          <td><?=br_date($i['due_date'])?></td>
          <td class="font-semibold"><?=money($i['amount'])?></td>
          <td class="text-teal-600"><?=money($i['paid_amount'])?></td>
          <td>
            <div class="relative inline-block">
              <button type="button" class="btn btn-light !p-2" data-menu-toggle aria-label="Abrir ações"><i data-lucide="chevron-down"></i></button>
              <div class="fixed z-50 hidden w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900" data-action-menu>
                <button type="button" class="payable-edit flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-item="<?=e($editData)?>"><i data-lucide="pencil"></i>Editar</button>
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-movement-details data-details-url="<?=url('movement/details')?>" data-entity="payable" data-id="<?=$i['id']?>"><i data-lucide="search"></i>Detalhes</button>
                <?php if (!in_array($i['status'], ['pago', 'cancelado'], true) && (float) $i['remaining_amount'] > 0): ?>
                  <button type="button" class="payable-pay flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-id="<?=$i['id']?>" data-description="<?=e($i['description'])?>" data-remaining="<?=e($i['remaining_amount'])?>"><i data-lucide="banknote"></i>Baixar</button>
                <?php endif; ?>
                <?php if (!$i['is_scheduled'] && !in_array($i['status'], ['pago', 'cancelado'], true)): ?>
                  <form method="post" action="<?=url('payables/schedule')?>"><?=csrf_field()?><input type="hidden" name="id" value="<?=$i['id']?>"><button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><i data-lucide="calendar-check"></i>Agendar</button></form>
                <?php endif; ?>
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-movement-log data-details-url="<?=url('movement/details')?>" data-entity="payable" data-id="<?=$i['id']?>"><i data-lucide="history"></i>Log de alterações</button>
                <?php if ($i['status'] !== 'cancelado'): ?><form method="post" action="<?=url('payables/delete')?>" data-confirm="Cancelar esta conta? Ela sairá das listagens e dos cálculos, mas o histórico será preservado."><?=csrf_field()?><input type="hidden" name="id" value="<?=$i['id']?>"><button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"><i data-lucide="trash-2"></i>Cancelar</button></form><?php endif; ?>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="9" class="text-center text-slate-500">Nenhuma conta encontrada.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
</div>

<dialog id="payableFormDialog" class="w-[calc(100%-2rem)] max-w-3xl rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form id="payableModalForm" method="post" enctype="multipart/form-data" action="<?=url('payables/save')?>" class="flex max-h-[90vh] flex-col"><?=csrf_field()?>
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700"><div><h2 id="payableFormTitle" class="text-lg font-semibold">Nova despesa</h2><p class="text-xs text-slate-500">Preencha somente os dados necessários.</p></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="payableFormDialog"><i data-lucide="x"></i></button></div>
    <div class="grid gap-4 overflow-y-auto p-5 sm:grid-cols-2">
      <input id="pfId" type="hidden" name="id">
      <label class="sm:col-span-2"><span class="label">Descrição</span><input id="pfDescription" class="field" name="description" required></label>
      <label><span class="label">Fornecedor</span><select id="pfContact" class="field" name="contact_id"><option value="">Selecione</option><?php foreach ($contacts as $o): ?><option value="<?=$o['id']?>"><?=e($o['name'])?></option><?php endforeach; ?></select></label>
      <label><span class="label">Categoria</span><select id="pfCategory" class="field" name="category_id"><option value="">Selecione</option><?php foreach ($categories as $o): ?><option value="<?=$o['id']?>"><?=e($o['name'])?></option><?php endforeach; ?></select></label>
      <label><span id="pfAmountLabel" class="label">Valor total</span><input id="pfAmount" class="field" name="amount" inputmode="decimal" placeholder="0,00" required></label>
      <label><span class="label">Primeiro vencimento</span><input id="pfDue" class="field" type="date" name="due_date" value="<?=date('Y-m-d')?>" required></label>
      <label><span class="label">Forma de pagamento</span><select id="pfMethod" class="field" name="payment_method"><?php foreach (['pix' => 'Pix', 'boleto' => 'Boleto', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro', 'debito_automatico' => 'Débito automático', 'transferencia' => 'Transferência'] as $v => $l): ?><option value="<?=$v?>"><?=$l?></option><?php endforeach; ?></select></label>
      <label><span class="label">Intervalo</span><select id="pfRecurrence" class="field" name="recurrence"><option value="nenhuma">Nenhum</option><option value="mensal" selected>Mensal</option><option value="quinzenal">Quinzenal</option><option value="semanal">Semanal</option></select></label>
      <label class="new-payable-only"><span class="label">Quantidade de parcelas</span><input id="pfCount" class="field" type="number" name="installment_count" value="1" min="1" max="600" required></label>
      <label class="new-payable-only"><span class="label">Pagamento recorrente?</span><span class="flex h-[42px] items-center gap-3"><input id="pfRecurring" class="h-5 w-5 accent-teal-600" type="checkbox" name="is_recurring" value="1"><span class="text-sm">Mesmo valor em todas</span></span></label>
      <div id="pfPreview" class="new-payable-only sm:col-span-2 rounded-xl bg-teal-50 px-4 py-3 text-sm text-teal-800 dark:bg-teal-950/40 dark:text-teal-200">Uma parcela será criada.</div>
      <div id="pfSeriesNotice" class="hidden sm:col-span-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"></div>
      <label class="sm:col-span-2"><span class="label">Comprovante (opcional)</span><input class="field" type="file" name="attachment" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
      <label class="sm:col-span-2"><span class="label">Observações (opcional)</span><textarea id="pfNotes" class="field" name="notes" rows="2"></textarea></label>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700"><button type="button" class="btn btn-light" data-modal-close="payableFormDialog">Fechar</button><button class="btn btn-primary">Salvar</button></div>
  </form>
</dialog>

<dialog id="payablePayDialog" class="w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form method="post" action="<?=url('payables/pay')?>" class="space-y-4 p-5"><?=csrf_field()?><input id="payId" type="hidden" name="id"><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Baixar despesa</h2><p id="payDescription" class="text-xs text-slate-500"></p></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="payablePayDialog"><i data-lucide="x"></i></button></div><div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Saldo restante: <strong id="payRemaining"></strong></div><label><span class="label">Valor que será pago</span><input id="payAmount" class="field" name="payment_amount" inputmode="decimal" required></label><label><span class="label">Data do pagamento</span><input class="field" type="date" name="payment_date" value="<?=date('Y-m-d')?>" required></label><label><span class="label">Observação (opcional)</span><input class="field" name="payment_notes" maxlength="255"></label><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="payablePayDialog">Cancelar</button><button class="btn btn-primary">Registrar pagamento</button></div></form>
</dialog>

<dialog id="payablesBulkPayDialog" class="w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form method="post" action="<?=url('payables/bulk-pay')?>" class="space-y-4 p-5"><?=csrf_field()?><div data-bulk-ids></div><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Baixar despesas selecionadas</h2><p class="text-xs text-slate-500"><strong data-bulk-dialog-count></strong> lançamento(s) selecionado(s)</p></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="payablesBulkPayDialog"><i data-lucide="x"></i></button></div><div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Cada despesa será quitada pelo seu saldo restante. Contas pagas ou canceladas serão ignoradas.</div><label><span class="label">Data do pagamento</span><input class="field" type="date" name="payment_date" value="<?=date('Y-m-d')?>" required></label><label><span class="label">Observação (opcional)</span><input class="field" name="payment_notes" maxlength="255"></label><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="payablesBulkPayDialog">Voltar</button><button class="btn btn-primary">Confirmar baixas</button></div></form>
</dialog>

<dialog id="payablesBulkEditDialog" class="w-[calc(100%-2rem)] max-w-xl rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form method="post" action="<?=url('payables/bulk-edit')?>" class="space-y-4 p-5"><?=csrf_field()?><div data-bulk-ids></div><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Editar despesas selecionadas</h2><p class="text-xs text-slate-500"><strong data-bulk-dialog-count></strong> lançamento(s); campos sem alteração serão preservados</p></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="payablesBulkEditDialog"><i data-lucide="x"></i></button></div><div class="grid gap-4 sm:grid-cols-2"><label><span class="label">Categoria</span><select class="field" name="category_id"><option value="__keep__">Não alterar</option><option value="0">Remover categoria</option><?php foreach ($categories as $o): ?><option value="<?=$o['id']?>"><?=e($o['name'])?></option><?php endforeach; ?></select></label><label><span class="label">Fornecedor</span><select class="field" name="contact_id"><option value="__keep__">Não alterar</option><option value="0">Remover fornecedor</option><?php foreach ($contacts as $o): ?><option value="<?=$o['id']?>"><?=e($o['name'])?></option><?php endforeach; ?></select></label><label><span class="label">Novo vencimento</span><input class="field" type="date" name="due_date"></label><label><span class="label">Forma de pagamento</span><select class="field" name="payment_method"><option value="__keep__">Não alterar</option><?php foreach (['pix' => 'Pix', 'boleto' => 'Boleto', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro', 'debito_automatico' => 'Débito automático', 'transferencia' => 'Transferência'] as $v => $l): ?><option value="<?=$v?>"><?=$l?></option><?php endforeach; ?></select></label></div><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="payablesBulkEditDialog">Voltar</button><button class="btn btn-primary">Aplicar alterações</button></div></form>
</dialog>

<dialog id="payablesBulkDeleteDialog" class="w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <form method="post" action="<?=url('payables/bulk-delete')?>" class="space-y-4 p-5"><?=csrf_field()?><div data-bulk-ids></div><div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Cancelar despesas selecionadas</h2><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="payablesBulkDeleteDialog"><i data-lucide="x"></i></button></div><p class="text-sm text-slate-600 dark:text-slate-300">Você está prestes a cancelar <strong data-bulk-dialog-count></strong> lançamento(s). Eles sairão dos cálculos, mas permanecerão no histórico.</p><div class="flex justify-end gap-2"><button type="button" class="btn btn-light" data-modal-close="payablesBulkDeleteDialog">Voltar</button><button class="btn bg-red-500 text-white hover:bg-red-600">Confirmar cancelamento</button></div></form>
</dialog>

<?php $pageScripts = <<<'HTML'
<script>
(() => {
  const formDialog=document.getElementById('payableFormDialog'),form=document.getElementById('payableModalForm');
  const pfId=document.getElementById('pfId'),pfDescription=document.getElementById('pfDescription'),pfContact=document.getElementById('pfContact'),pfCategory=document.getElementById('pfCategory'),pfAmount=document.getElementById('pfAmount'),pfDue=document.getElementById('pfDue'),pfMethod=document.getElementById('pfMethod'),pfRecurrence=document.getElementById('pfRecurrence'),pfNotes=document.getElementById('pfNotes'),pfCount=document.getElementById('pfCount'),pfRecurring=document.getElementById('pfRecurring'),pfAmountLabel=document.getElementById('pfAmountLabel'),pfPreview=document.getElementById('pfPreview'),pfSeriesNotice=document.getElementById('pfSeriesNotice');
  const payId=document.getElementById('payId'),payDescription=document.getElementById('payDescription'),payRemaining=document.getElementById('payRemaining'),payAmount=document.getElementById('payAmount');
  const fields={id:pfId,description:pfDescription,contact_id:pfContact,category_id:pfCategory,amount:pfAmount,due_date:pfDue,payment_method:pfMethod,recurrence:pfRecurrence,notes:pfNotes};
  const parseCents=value=>{let normalized=String(value).trim();if(normalized.includes(','))normalized=normalized.replaceAll('.','').replace(',','.');if(!/^\d+(?:\.\d{1,2})?$/.test(normalized))return null;const [whole,fraction='']=normalized.split('.');return Number(whole)*100+Number(fraction.padEnd(2,'0'));};
  const lastDate=(start,type,offset)=>{const [year,month,day]=start.split('-').map(Number);if(type==='mensal'){const target=month-1+offset,targetYear=year+Math.floor(target/12),targetMonth=((target%12)+12)%12,lastDay=new Date(Date.UTC(targetYear,targetMonth+1,0)).getUTCDate();return new Date(Date.UTC(targetYear,targetMonth,Math.min(day,lastDay)));}return new Date(Date.UTC(year,month-1,day+(type==='quinzenal'?15:7)*offset));};
  const renderPreview=()=>{const cents=parseCents(pfAmount.value),count=Math.max(1,Number.parseInt(pfCount.value,10)||1),recurring=pfRecurring.checked;pfAmountLabel.textContent=recurring?'Valor de cada parcela':'Valor total';if(!cents||!pfDue.value){pfPreview.textContent='Preencha valor e vencimento para visualizar a previsão.';return;}if(count>1&&pfRecurrence.value==='nenhuma'){pfPreview.textContent='Selecione um intervalo para gerar mais de uma parcela.';return;}if(!recurring&&cents<count){pfPreview.textContent='O valor total deve permitir pelo menos R$ 0,01 por parcela.';return;}const end=new Intl.DateTimeFormat('pt-BR',{timeZone:'UTC'}).format(lastDate(pfDue.value,pfRecurrence.value,count-1));const total=recurring?cents*count:cents;const each=recurring?cents:Math.floor(cents/count);pfPreview.textContent=`${count} parcela${count===1?'':'s'} · ${recurring?'valor por parcela':'valor aproximado por parcela'} ${formatMoney(each/100)} · último vencimento ${end} · total ${formatMoney(total/100)}`;};
  const showNew=()=>{closeActionMenus();form.reset();pfId.value='';pfRecurrence.value='mensal';pfCount.value='1';document.getElementById('payableFormTitle').textContent='Nova despesa';document.querySelectorAll('.new-payable-only').forEach(el=>el.classList.remove('hidden'));pfSeriesNotice.classList.add('hidden');pfCount.required=true;renderPreview();formDialog.showModal();};
  document.getElementById('newPayableBtn').addEventListener('click',showNew);
  document.querySelectorAll('.payable-edit').forEach(button=>button.addEventListener('click',()=>{closeActionMenus();const item=JSON.parse(button.dataset.item);form.reset();Object.entries(fields).forEach(([key,field])=>field.value=item[key]??'');pfAmountLabel.textContent='Valor desta conta';document.getElementById('payableFormTitle').textContent='Editar despesa';document.querySelectorAll('.new-payable-only').forEach(el=>el.classList.add('hidden'));pfCount.required=false;if(item.installment_count){pfSeriesNotice.textContent=`Editando somente a parcela ${item.installment_number} de ${item.installment_count}.`;pfSeriesNotice.classList.remove('hidden');}else pfSeriesNotice.classList.add('hidden');formDialog.showModal();}));
  [pfAmount,pfDue,pfRecurrence,pfCount,pfRecurring].forEach(field=>{field.addEventListener('input',renderPreview);field.addEventListener('change',renderPreview);});
  document.querySelectorAll('.payable-pay').forEach(button=>button.addEventListener('click',()=>{closeActionMenus();const payDialog=document.getElementById('payablePayDialog');payDialog.querySelector('form').reset();payId.value=button.dataset.id;payDescription.textContent=button.dataset.description;payRemaining.textContent=formatMoney(button.dataset.remaining);payAmount.value=String(button.dataset.remaining).replace('.',',');payDialog.showModal();}));
})();
</script>
HTML; ?>

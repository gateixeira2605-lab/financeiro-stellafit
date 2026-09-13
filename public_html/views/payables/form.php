<?php $editing = !empty($item['id']); ?>
<form class="card max-w-6xl space-y-6 p-6" method="post" enctype="multipart/form-data" action="<?=url('payables/save')?>">
  <?=csrf_field()?>
  <input type="hidden" name="id" value="<?=e($item['id'])?>">

  <?php if ($editing && !empty($item['installment_count'])): ?>
    <div class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-200">
      Editando somente a parcela <strong><?=e($item['installment_number'])?> de <?=e($item['installment_count'])?></strong>. As demais parcelas da série não serão alteradas.
    </div>
  <?php endif; ?>

  <div class="grid gap-4 sm:grid-cols-2">
    <label class="sm:col-span-2">
      <span class="label">Descrição</span>
      <input class="field" name="description" value="<?=e($item['description'])?>" required>
    </label>
    <label>
      <span class="label">Fornecedor</span>
      <select class="field" name="contact_id">
        <option value="">Selecione</option>
        <?php foreach ($contacts as $o): ?><option value="<?=$o['id']?>" <?=$item['contact_id'] == $o['id'] ? 'selected' : ''?>><?=e($o['name'])?></option><?php endforeach; ?>
      </select>
    </label>
    <label>
      <span class="label">Categoria</span>
      <select class="field" name="category_id">
        <option value="">Selecione</option>
        <?php foreach ($categories as $o): ?><option value="<?=$o['id']?>" <?=$item['category_id'] == $o['id'] ? 'selected' : ''?>><?=e($o['name'])?></option><?php endforeach; ?>
      </select>
    </label>

    <label>
      <span id="amountLabel" class="label"><?=$editing ? 'Valor desta parcela' : 'Valor total'?></span>
      <input id="payableAmount" class="field" name="amount" inputmode="decimal" value="<?=e($item['amount'])?>" placeholder="0,00" required>
      <?php if (!$editing): ?><span id="amountHint" class="mt-1 block text-xs text-slate-500">O valor será dividido pela quantidade de parcelas.</span><?php endif; ?>
    </label>
    <label>
      <span class="label"><?=$editing ? 'Data de vencimento' : 'Primeiro vencimento'?></span>
      <input id="payableDueDate" class="field" type="date" name="due_date" value="<?=e($item['due_date'])?>" required>
    </label>
    <label>
      <span class="label">Forma de pagamento</span>
      <select class="field" name="payment_method">
        <?php foreach (['pix' => 'Pix', 'boleto' => 'Boleto', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro', 'debito_automatico' => 'Débito automático', 'transferencia' => 'Transferência'] as $v => $l): ?>
          <option value="<?=$v?>" <?=$item['payment_method'] === $v ? 'selected' : ''?>><?=$l?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span class="label">Intervalo</span>
      <select id="payableRecurrence" class="field" name="recurrence">
        <?php if ($editing && $item['recurrence'] === 'nenhuma'): ?><option value="nenhuma" selected>Nenhum</option><?php endif; ?>
        <?php foreach (['mensal' => 'Mensal', 'quinzenal' => 'Quinzenal', 'semanal' => 'Semanal'] as $v => $l): ?>
          <option value="<?=$v?>" <?=$item['recurrence'] === $v ? 'selected' : ''?>><?=$l?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <?php if (!$editing): ?>
      <label>
        <span class="label">Quantidade de parcelas</span>
        <input id="installmentCount" class="field" type="number" name="installment_count" value="1" min="1" max="600" required>
      </label>
      <label>
        <span class="label">Pagamento recorrente?</span>
        <span class="flex h-[42px] items-center gap-3">
          <input id="isRecurring" class="h-5 w-5 accent-teal-600" type="checkbox" name="is_recurring" value="1">
          <span class="text-sm">Repetir o valor informado em todas as parcelas</span>
        </span>
      </label>
    <?php endif; ?>

    <label class="sm:col-span-2">
      <span class="label">Comprovante (PDF ou imagem, até 5 MB)</span>
      <input class="field" type="file" name="attachment" accept="application/pdf,image/jpeg,image/png,image/webp">
      <?php if (!$editing): ?><span class="mt-1 block text-xs text-slate-500">Em um parcelamento, o anexo ficará vinculado à primeira parcela.</span><?php endif; ?>
    </label>
    <label class="sm:col-span-2">
      <span class="label">Observações</span>
      <textarea class="field" name="notes" rows="4"><?=e($item['notes'])?></textarea>
    </label>
  </div>

  <?php if (!$editing): ?>
    <section class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
        <div>
          <h2 class="font-semibold">Previsão das parcelas</h2>
          <p id="installmentSummary" class="text-xs text-slate-500">Preencha valor, quantidade e vencimento.</p>
        </div>
      </div>
      <div class="max-h-96 overflow-auto">
        <table class="data-table">
          <thead class="sticky top-0"><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th></tr></thead>
          <tbody id="installmentPreview"><tr><td colspan="3" class="text-center text-slate-500">Preencha os dados para visualizar.</td></tr></tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <div class="flex gap-3">
    <button class="btn btn-primary">Salvar</button>
    <a class="btn btn-light" href="<?=url('payables')?>">Cancelar</a>
  </div>
</form>

<?php if (!$editing): ?>
<script>
(() => {
  const amount = document.getElementById('payableAmount');
  const dueDate = document.getElementById('payableDueDate');
  const interval = document.getElementById('payableRecurrence');
  const count = document.getElementById('installmentCount');
  const recurring = document.getElementById('isRecurring');
  const preview = document.getElementById('installmentPreview');
  const summary = document.getElementById('installmentSummary');
  const amountLabel = document.getElementById('amountLabel');
  const amountHint = document.getElementById('amountHint');
  const currency = new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'});

  const parseCents = value => {
    let normalized = value.trim();
    if (normalized.includes(',')) normalized = normalized.replaceAll('.', '').replace(',', '.');
    if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) return null;
    const [whole, fraction = ''] = normalized.split('.');
    return (Number(whole) * 100) + Number(fraction.padEnd(2, '0'));
  };

  const formatDate = date => new Intl.DateTimeFormat('pt-BR', {timeZone: 'UTC'}).format(date);
  const nextDate = (start, type, offset) => {
    const [year, month, day] = start.split('-').map(Number);
    if (type === 'mensal') {
      const targetMonth = (month - 1) + offset;
      const targetYear = year + Math.floor(targetMonth / 12);
      const normalizedMonth = ((targetMonth % 12) + 12) % 12;
      const lastDay = new Date(Date.UTC(targetYear, normalizedMonth + 1, 0)).getUTCDate();
      return new Date(Date.UTC(targetYear, normalizedMonth, Math.min(day, lastDay)));
    }
    const days = type === 'quinzenal' ? 15 : 7;
    return new Date(Date.UTC(year, month - 1, day + (days * offset)));
  };

  const render = () => {
    const totalCents = parseCents(amount.value);
    const installments = Number.parseInt(count.value, 10);
    const validDate = /^\d{4}-\d{2}-\d{2}$/.test(dueDate.value);
    amountLabel.textContent = recurring.checked ? 'Valor de cada parcela' : 'Valor total';
    amountHint.textContent = recurring.checked
      ? 'Este mesmo valor será lançado em todas as parcelas.'
      : 'O valor total será dividido pela quantidade de parcelas.';

    if (totalCents === null || totalCents <= 0 || !Number.isInteger(installments) || installments < 1 || installments > 600 || !validDate) {
      preview.innerHTML = '<tr><td colspan="3" class="text-center text-slate-500">Preencha valor, quantidade e vencimento para visualizar.</td></tr>';
      summary.textContent = 'Preencha valor, quantidade e vencimento.';
      return;
    }
    if (!recurring.checked && totalCents < installments) {
      preview.innerHTML = '<tr><td colspan="3" class="text-center text-red-600">O valor total deve permitir ao menos R$ 0,01 por parcela.</td></tr>';
      summary.textContent = 'Revise o valor total ou a quantidade.';
      return;
    }

    const base = recurring.checked ? totalCents : Math.floor(totalCents / installments);
    const remainder = recurring.checked ? 0 : totalCents % installments;
    const rows = [];
    for (let index = 0; index < installments; index++) {
      const installmentCents = base + (index < remainder ? 1 : 0);
      rows.push(`<tr><td>${index + 1} de ${installments}</td><td>${formatDate(nextDate(dueDate.value, interval.value, index))}</td><td class="font-semibold">${currency.format(installmentCents / 100)}</td></tr>`);
    }
    preview.innerHTML = rows.join('');
    const commitment = recurring.checked ? totalCents * installments : totalCents;
    summary.textContent = `${installments} parcela${installments === 1 ? '' : 's'} · compromisso total de ${currency.format(commitment / 100)}`;
  };

  [amount, dueDate, interval, count, recurring].forEach(element => {
    element.addEventListener('input', render);
    element.addEventListener('change', render);
  });
  render();
})();
</script>
<?php endif; ?>

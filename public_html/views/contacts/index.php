<?php
$exportQuery = array_filter(['q' => $q, 'type' => $type], static fn(string $value): bool => $value !== '');
$exportUrl = url('contacts/export') . ($exportQuery ? '&' . http_build_query($exportQuery) : '');
?>
<form class="card mb-5 flex flex-wrap items-end gap-3 p-4">
    <input type="hidden" name="route" value="contacts">
    <label class="grow">
        <span class="label">Pesquisar</span>
        <input class="field" name="q" value="<?=e($q)?>" placeholder="Nome do contato">
    </label>
    <label>
        <span class="label">Tipo</span>
        <select class="field" name="type">
            <option value="">Todos</option>
            <?php foreach(['fornecedor'=>'Fornecedor','cliente'=>'Cliente','ambos'=>'Ambos'] as $v=>$l):?>
                <option value="<?=$v?>" <?=$type===$v?'selected':''?>><?=$l?></option>
            <?php endforeach;?>
        </select>
    </label>
    <button class="btn btn-light"><i data-lucide="search"></i>Filtrar</button>
    <a class="btn btn-light" href="<?=e($exportUrl)?>"><i data-lucide="download"></i>Exportar CSV</a>
    <a class="btn btn-light" href="<?=url('contacts/import')?>"><i data-lucide="upload"></i>Importar CSV</a>
    <a class="btn btn-primary" href="<?=url('contacts/form')?>"><i data-lucide="plus"></i>Novo contato</a>
</form>

<div data-bulk-selection data-bulk-json>
<div class="card mb-3 flex flex-wrap items-center gap-2 p-3">
    <span class="mr-auto text-sm text-slate-500"><strong data-selected-count>0</strong> contato(s) selecionado(s)</span>
    <button type="button" class="btn bg-red-500 text-white hover:bg-red-600" data-bulk-action data-bulk-open="contactsBulkDeleteDialog" disabled><i data-lucide="trash-2"></i>Excluir selecionados</button>
</div>

<div class="card table-wrap">
    <table class="data-table">
        <thead><tr><th><label class="inline-flex cursor-pointer items-center gap-2"><input type="checkbox" class="h-4 w-4 accent-teal-600" data-select-all aria-label="Selecionar todos os contatos exibidos"><span>Todos</span></label></th><th>Nome</th><th>Documento</th><th>Contato</th><th>Tipo</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach($contacts as $c):?>
            <tr>
                <td><input type="checkbox" class="h-4 w-4 accent-teal-600" data-select-item value="<?=$c['id']?>" aria-label="Selecionar <?=e($c['name'])?>"></td>
                <td class="font-medium"><?=e($c['name'])?></td>
                <td><?=e($c['document']?:'—')?></td>
                <td><div><?=e($c['phone']?:'—')?></div><div class="text-xs text-slate-500"><?=e($c['email'])?></div></td>
                <td><?=e(ucfirst($c['type']))?></td>
                <td><div class="flex gap-2"><a class="btn btn-light !p-2" href="<?=url('contacts/form')?>&id=<?=$c['id']?>"><i data-lucide="pencil"></i></a><form method="post" action="<?=url('contacts/delete')?>" data-confirm="Excluir este contato?"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn btn-light !p-2 text-red-600"><i data-lucide="trash-2"></i></button></form></div></td>
            </tr>
        <?php endforeach;?>
        <?php if(!$contacts):?><tr><td colspan="6" class="text-center text-slate-500">Nenhum contato encontrado.</td></tr><?php endif;?>
        </tbody>
    </table>
</div>
</div>

<dialog id="contactsBulkDeleteDialog" class="w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
    <form method="post" action="<?=url('contacts/bulk-delete')?>" class="space-y-4 p-5">
        <?=csrf_field()?>
        <div data-bulk-ids></div>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Excluir contatos selecionados</h2>
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="contactsBulkDeleteDialog" aria-label="Fechar"><i data-lucide="x"></i></button>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-300">Você está prestes a excluir permanentemente <strong data-bulk-dialog-count></strong> contato(s). Contatos vinculados a contas serão preservados automaticamente.</p>
        <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Esta ação não poderá ser desfeita para os contatos que forem excluídos.</div>
        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-light" data-modal-close="contactsBulkDeleteDialog">Voltar</button>
            <button class="btn bg-red-500 text-white hover:bg-red-600">Confirmar exclusão</button>
        </div>
    </form>
</dialog>

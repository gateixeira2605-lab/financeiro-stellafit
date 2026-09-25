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

<div class="card table-wrap">
    <table class="data-table">
        <thead><tr><th>Nome</th><th>Documento</th><th>Contato</th><th>Tipo</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach($contacts as $c):?>
            <tr>
                <td class="font-medium"><?=e($c['name'])?></td>
                <td><?=e($c['document']?:'—')?></td>
                <td><div><?=e($c['phone']?:'—')?></div><div class="text-xs text-slate-500"><?=e($c['email'])?></div></td>
                <td><?=e(ucfirst($c['type']))?></td>
                <td><div class="flex gap-2"><a class="btn btn-light !p-2" href="<?=url('contacts/form')?>&id=<?=$c['id']?>"><i data-lucide="pencil"></i></a><form method="post" action="<?=url('contacts/delete')?>" data-confirm="Excluir este contato?"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn btn-light !p-2 text-red-600"><i data-lucide="trash-2"></i></button></form></div></td>
            </tr>
        <?php endforeach;?>
        <?php if(!$contacts):?><tr><td colspan="5" class="text-center text-slate-500">Nenhum contato encontrado.</td></tr><?php endif;?>
        </tbody>
    </table>
</div>

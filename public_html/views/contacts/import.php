<?php if (is_array($result)): ?>
<section class="card mb-5 p-5">
    <div class="flex items-start gap-3">
        <div class="mt-0.5 rounded-full bg-emerald-100 p-2 text-emerald-700"><i data-lucide="circle-check"></i></div>
        <div class="grow">
            <h2 class="font-semibold">Importação concluída</h2>
            <div class="mt-3 grid gap-3 text-sm sm:grid-cols-4">
                <div class="rounded-xl bg-emerald-50 p-3 text-emerald-800"><strong class="block text-xl"><?=(int)$result['created']?></strong>Criados</div>
                <div class="rounded-xl bg-blue-50 p-3 text-blue-800"><strong class="block text-xl"><?=(int)$result['updated']?></strong>Atualizados</div>
                <div class="rounded-xl bg-slate-100 p-3 text-slate-700"><strong class="block text-xl"><?=(int)$result['skipped']?></strong>Ignorados</div>
                <div class="rounded-xl bg-amber-50 p-3 text-amber-800"><strong class="block text-xl"><?=(int)$result['invalid']?></strong>Com erro</div>
            </div>
            <?php if (!empty($result['errors'])): ?>
                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer font-medium text-amber-700">Ver linhas que não foram importadas</summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-600 dark:text-slate-300">
                        <?php foreach ($result['errors'] as $error): ?><li><?=e($error)?></li><?php endforeach; ?>
                    </ul>
                    <?php if ((int)$result['invalid'] > count($result['errors'])): ?><p class="mt-2 text-slate-500">Exibindo somente os primeiros 20 erros.</p><?php endif; ?>
                </details>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
    <form class="card space-y-5 p-6" method="post" action="<?=url('contacts/import')?>" enctype="multipart/form-data">
        <?=csrf_field()?>
        <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
        <div>
            <h2 class="font-semibold">Selecione o arquivo do sistema antigo</h2>
            <p class="mt-1 text-sm text-slate-500">Use um arquivo CSV com a primeira linha contendo os nomes das colunas.</p>
        </div>
        <label>
            <span class="label">Arquivo CSV (máximo 5 MB)</span>
            <input class="field file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-1 file:font-medium file:text-teal-700" type="file" name="contacts_file" accept=".csv,.txt,text/csv" required>
        </label>
        <label>
            <span class="label">Quando já existir um contato com o mesmo CPF/CNPJ ou e-mail</span>
            <select class="field" name="duplicate_strategy">
                <option value="skip">Ignorar o contato repetido (mais seguro)</option>
                <option value="update">Atualizar o contato existente</option>
            </select>
        </label>
        <label>
            <span class="label">Tipo usado quando a coluna Tipo estiver vazia ou não existir</span>
            <select class="field" name="default_type">
                <option value="ambos">Ambos</option>
                <option value="cliente">Cliente</option>
                <option value="fornecedor">Fornecedor</option>
            </select>
        </label>
        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary"><i data-lucide="upload"></i>Importar contatos</button>
            <a class="btn btn-light" href="<?=url('contacts')?>">Voltar</a>
        </div>
    </form>

    <aside class="card p-6">
        <h2 class="font-semibold">Formato aceito</h2>
        <p class="mt-2 text-sm text-slate-500">O sistema reconhece arquivos separados por ponto e vírgula, vírgula ou tabulação.</p>
        <div class="mt-4 text-sm">
            <div class="font-medium">Colunas reconhecidas</div>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-600 dark:text-slate-300">
                <li><strong>Nome</strong> (obrigatória)</li>
                <li>CPF/CNPJ ou Documento</li>
                <li>Telefone, Celular ou WhatsApp</li>
                <li>E-mail</li>
                <li>Tipo: Fornecedor, Cliente ou Ambos</li>
                <li>Observações</li>
            </ul>
        </div>
        <a class="btn btn-light mt-5 w-full" href="<?=url('contacts/template')?>"><i data-lucide="file-down"></i>Baixar arquivo modelo</a>
    </aside>
</div>

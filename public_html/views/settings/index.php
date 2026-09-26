<div class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
    <form class="card space-y-5 p-6" method="post" action="<?=url('settings/save')?>" enctype="multipart/form-data">
        <?=csrf_field()?>
        <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        <div>
            <h2 class="text-lg font-semibold">Identidade visual</h2>
            <p class="mt-1 text-sm text-slate-500">Personalize o nome e o logotipo exibidos no menu lateral e no topo do sistema.</p>
        </div>

        <label>
            <span class="label">Nome da empresa</span>
            <input class="field" name="company_name" value="<?=e($branding['company_name'])?>" maxlength="120" required>
        </label>

        <label>
            <span class="label">Logotipo</span>
            <input class="field file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-1 file:font-medium file:text-teal-700" type="file" name="company_logo" accept="image/jpeg,image/png,image/webp">
            <span class="mt-2 block text-xs text-slate-500">JPG, PNG ou WEBP, com até 2 MB. Imagens quadradas funcionam melhor.</span>
        </label>

        <?php if ($branding['has_logo']): ?>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input class="h-4 w-4 accent-teal-600" type="checkbox" name="remove_logo" value="1">
                Remover o logotipo atual
            </label>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary"><i data-lucide="save"></i>Salvar personalização</button>
            <a class="btn btn-light" href="<?=url('dashboard')?>">Cancelar</a>
        </div>
    </form>

    <aside class="card overflow-hidden">
        <div class="bg-[#2f4558] p-6 text-white">
            <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-300">Prévia do menu</p>
            <div class="flex items-center gap-4">
                <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white p-1 text-teal-600 shadow-lg">
                    <?php if ($branding['has_logo']): ?>
                        <img class="h-full w-full rounded-xl object-contain" src="<?=e($branding['logo_url'])?>" alt="Logotipo atual">
                    <?php else: ?>
                        <i data-lucide="landmark"></i>
                    <?php endif; ?>
                </div>
                <div class="min-w-0"><div class="truncate font-bold"><?=e($branding['company_name'])?></div><div class="text-xs text-slate-300">Gestão financeira</div></div>
            </div>
        </div>
        <div class="p-5 text-sm text-slate-600 dark:text-slate-300">
            <p>A identidade será atualizada em todas as telas assim que você salvar.</p>
            <p class="mt-2 text-xs text-slate-500">O arquivo fica armazenado com segurança no banco de dados e permanece após novos deploys.</p>
        </div>
    </aside>
</div>

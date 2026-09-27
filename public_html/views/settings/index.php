<div class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
  <form class="card space-y-6 p-6" method="post" action="<?=url('settings/save')?>" enctype="multipart/form-data">
    <?=csrf_field()?><input type="hidden" name="MAX_FILE_SIZE" value="2097152"><input type="hidden" name="accounting_policy" value="<?=e($accounting['accounting_policy']?:'Regime de competência')?>">

    <div><h2 class="text-lg font-semibold">Identidade da empresa</h2><p class="mt-1 text-sm text-slate-500">Nome e imagem que aparecem no sistema.</p></div>
    <label><span class="label">Nome da empresa</span><input class="field" name="company_name" value="<?=e($branding['company_name'])?>" maxlength="120" required></label>
    <label><span class="label">Logotipo</span><input class="field file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-1 file:font-medium file:text-teal-700" type="file" name="company_logo" accept="image/jpeg,image/png,image/webp"><span class="mt-2 block text-xs text-slate-500">JPG, PNG ou WEBP, com até 2 MB.</span></label>
    <?php if($branding['has_logo']):?><label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input class="h-4 w-4 accent-teal-600" type="checkbox" name="remove_logo" value="1">Remover o logotipo atual</label><?php endif;?>

    <div class="border-t border-slate-200 pt-6 dark:border-slate-700"><h2 class="text-lg font-semibold">Informações para os relatórios</h2><p class="mt-1 text-sm text-slate-500">São os únicos dados contábeis que você normalmente precisa informar.</p></div>
    <div class="grid gap-4 sm:grid-cols-2">
      <label><span class="label">Regime tributário</span><select class="field" name="tax_regime" required><option value="NAO_CONFIGURADO" <?=$accounting['tax_regime']==='NAO_CONFIGURADO'?'selected':''?>>Ainda não definido</option><option value="SIMPLES" <?=$accounting['tax_regime']==='SIMPLES'?'selected':''?>>Simples Nacional</option><option value="PRESUMIDO" <?=$accounting['tax_regime']==='PRESUMIDO'?'selected':''?>>Lucro Presumido</option><option value="REAL" <?=$accounting['tax_regime']==='REAL'?'selected':''?>>Lucro Real</option></select></label>
      <label><span class="label">Começar os relatórios a partir de</span><input class="field" type="date" name="accounting_start_date" value="<?=e($accounting['accounting_start_date'])?>"><span class="mt-1 block text-xs text-slate-500">Movimentações antigas não serão importadas automaticamente.</span></label>
      <label class="sm:col-span-2"><span class="label">Atividade principal</span><input class="field" name="business_activity" maxlength="160" value="<?=e($accounting['business_activity'])?>" placeholder="Ex.: academia e serviços de condicionamento físico"></label>
    </div>

    <details class="rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50">
      <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">Configurações avançadas para o contador</summary>
      <div class="grid gap-4 border-t border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-3">
        <p class="sm:col-span-3 text-xs text-slate-500">Os padrões abaixo já estão corretos para o funcionamento normal. Altere somente com orientação contábil.</p>
        <label><span class="label">Fornecedores</span><select class="field" name="payable_control_account_id" required><?php foreach($accounts as $a):?><option value="<?=e($a['account_id'])?>" <?=$accounting['payable_control_account_id']===$a['account_id']?'selected':''?>><?=e($a['name'])?></option><?php endforeach;?></select></label>
        <label><span class="label">Clientes</span><select class="field" name="receivable_control_account_id" required><?php foreach($accounts as $a):?><option value="<?=e($a['account_id'])?>" <?=$accounting['receivable_control_account_id']===$a['account_id']?'selected':''?>><?=e($a['name'])?></option><?php endforeach;?></select></label>
        <label><span class="label">Bancos</span><select class="field" name="bank_control_account_id" required><?php foreach($accounts as $a):?><option value="<?=e($a['account_id'])?>" <?=$accounting['bank_control_account_id']===$a['account_id']?'selected':''?>><?=e($a['name'])?></option><?php endforeach;?></select></label>
      </div>
    </details>

    <div class="flex flex-wrap gap-3"><button class="btn btn-primary"><i data-lucide="save"></i>Salvar configurações</button><a class="btn btn-light" href="<?=url('dashboard')?>">Cancelar</a></div>
  </form>

  <aside class="card h-fit overflow-hidden">
    <div class="bg-[#2f4558] p-6 text-white"><p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-300">Prévia do menu</p><div class="flex items-center gap-4"><div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white p-1 text-teal-600 shadow-lg"><?php if($branding['has_logo']):?><img class="h-full w-full rounded-xl object-contain" src="<?=e($branding['logo_url'])?>" alt="Logotipo atual"><?php else:?><i data-lucide="landmark"></i><?php endif;?></div><div class="min-w-0"><div class="truncate font-bold"><?=e($branding['company_name'])?></div><div class="text-xs text-slate-300">Gestão financeira</div></div></div></div>
    <div class="p-5 text-sm text-slate-600 dark:text-slate-300"><p>A identidade será atualizada em todas as telas assim que você salvar.</p><p class="mt-2 text-xs text-slate-500">O arquivo permanece armazenado no banco após novos deploys.</p></div>
  </aside>
</div>

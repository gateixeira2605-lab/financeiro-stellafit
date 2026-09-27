<form class="card max-w-3xl space-y-6 p-6" method="post" action="<?=url('categories/save')?>">
  <?=csrf_field()?><input type="hidden" name="id" value="<?=e($category['id'])?>">
  <div>
    <h2 class="text-lg font-semibold"><?=$category['id']?'Editar categoria':'Criar categoria'?></h2>
    <p class="mt-1 text-sm text-slate-500">Use um nome fácil de reconhecer. A classificação dos relatórios será feita automaticamente.</p>
  </div>

  <label>
    <span class="label">Nome que você quer ver nos lançamentos</span>
    <input class="field" name="name" value="<?=e($category['name'])?>" maxlength="120" placeholder="Ex.: Aluguel da academia" required autofocus>
  </label>

  <label>
    <span class="label">O que esta categoria representa?</span>
    <?php if(!empty($category['purpose_key'])):?><input type="hidden" name="purpose" value="<?=e($category['purpose_key'])?>"><?php endif;?>
    <select class="field" name="purpose" required <?=!empty($category['purpose_key'])?'disabled':''?>>
      <option value="">Selecione uma opção simples</option>
      <?php if($category['id']&&$selectedPurpose===null):?><option value="keep_current" selected>Manter a classificação atual</option><?php endif;?>
      <?php $lastGroup=null;foreach($purposes as $key=>$purpose):?>
        <?php if($purpose['group']!==$lastGroup):?><?php if($lastGroup!==null):?></optgroup><?php endif;?><optgroup label="<?=e($purpose['group'])?>"><?php $lastGroup=$purpose['group'];endif;?>
        <option value="<?=e($key)?>" <?=$selectedPurpose===$key?'selected':''?>><?=e($purpose['label'])?></option>
      <?php endforeach;if($lastGroup!==null):?></optgroup><?php endif;?>
    </select>
    <span class="mt-2 block text-xs text-slate-500"><?=!empty($category['purpose_key'])?'Esta finalidade já está ligada aos relatórios e não precisa ser alterada.':'Exemplo: para “Aluguel da unidade Centro”, escolha “Aluguel”. Você não precisa selecionar contas ou códigos contábeis.'?></span>
  </label>

  <label class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800">
    <input type="hidden" name="active" value="0"><input class="h-5 w-5 accent-teal-600" type="checkbox" name="active" value="1" <?=$category['active']?'checked':''?>>
    <span><strong>Mostrar esta categoria nos lançamentos</strong><span class="mt-0.5 block text-xs text-slate-500">Desmarque somente quando não quiser mais utilizá-la.</span></span>
  </label>

  <div class="flex gap-3"><button class="btn btn-primary"><i data-lucide="check"></i>Salvar categoria</button><a class="btn btn-light" href="<?=url('categories')?>">Cancelar</a></div>
</form>

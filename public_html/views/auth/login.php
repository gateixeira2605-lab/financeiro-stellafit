<?php $branding = company_branding(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Entrar · <?=e($branding['company_name'])?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#e9edf1]">
<div class="grid min-h-screen lg:grid-cols-2">
  <section class="hidden flex-col justify-between bg-[#2f4558] p-14 text-white shadow-2xl lg:flex">
    <div class="flex items-center gap-4">
      <div class="grid h-16 w-16 place-items-center overflow-hidden rounded-2xl bg-white p-1 text-teal-600 shadow-xl">
        <?php if ($branding['has_logo']): ?><img class="h-full w-full rounded-xl object-contain" src="<?=e($branding['logo_url'])?>" alt="Logotipo"><?php else: ?><span class="text-2xl font-bold">$</span><?php endif; ?>
      </div>
      <div><div class="text-xl font-bold"><?=e($branding['company_name'])?></div><div class="text-sm text-slate-300">Gestão financeira</div></div>
    </div>
    <div><p class="mb-3 text-sm uppercase tracking-[.3em] text-teal-200">Gestão sem ruído</p><h1 class="max-w-xl text-5xl font-bold leading-tight">Veja o caixa de hoje e antecipe o de amanhã.</h1></div>
  </section>
  <main class="grid place-items-center p-6">
    <div class="w-full max-w-md rounded-2xl border border-slate-300 bg-[#f7f8fa] p-8 shadow-[0_18px_50px_rgba(15,23,42,.16)]">
      <div class="mb-8">
        <div class="mb-4 grid h-14 w-14 place-items-center overflow-hidden rounded-2xl bg-white p-1 text-teal-600 shadow-md lg:hidden">
          <?php if ($branding['has_logo']): ?><img class="h-full w-full rounded-xl object-contain" src="<?=e($branding['logo_url'])?>" alt="Logotipo"><?php else: ?><span class="text-xl font-bold">$</span><?php endif; ?>
        </div>
        <h2 class="text-2xl font-bold text-slate-900">Bem-vindo</h2>
        <p class="mt-1 text-sm text-slate-500">Entre para acessar o financeiro de <?=e($branding['company_name'])?>.</p>
      </div>
      <?php if($error):?><div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?=e($error)?></div><?php endif;?>
      <form method="post" class="space-y-4">
        <?=csrf_field()?>
        <label class="block"><span class="mb-1 block text-sm font-medium text-slate-700">Usuário</span><input class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/10" name="username" required autofocus></label>
        <label class="block"><span class="mb-1 block text-sm font-medium text-slate-700">Senha</span><input class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/10" type="password" name="password" required></label>
        <button class="w-full rounded-lg bg-teal-600 py-2.5 font-semibold text-white shadow-md shadow-teal-600/20 hover:bg-teal-700">Entrar</button>
      </form>
      <p class="mt-5 text-center text-xs text-slate-400">Acesso inicial: admin / admin123</p>
    </div>
  </main>
</div>
</body>
</html>

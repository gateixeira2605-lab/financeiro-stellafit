<?php
$current = trim((string) ($_GET['route'] ?? 'dashboard'), '/');
$navClass = fn(string $route): string => str_starts_with($current, $route) ? 'is-active' : '';
$branding = company_branding();
?>
<!doctype html>
<html lang="pt-BR" class="scroll-smooth">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($pageTitle)?> · <?=e($branding['company_name'])?></title>
<script>if(localStorage.theme==='dark'||(!('theme'in localStorage)&&matchMedia('(prefers-color-scheme:dark)').matches))document.documentElement.classList.add('dark');if(localStorage.sidebar==='collapsed')document.documentElement.classList.add('sidebar-collapsed')</script>
<script src="https://cdn.tailwindcss.com"></script><script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"><link rel="stylesheet" href="<?=e(asset('css/financial-filters.css'))?>"><script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
</head>
<body class="app-body text-slate-800 dark:bg-slate-950 dark:text-slate-100">
<div id="backdrop" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>
<aside id="sidebar" class="app-sidebar fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto p-4 transition-transform lg:translate-x-0">
  <div class="sidebar-brand mb-7 flex items-center gap-3 py-2">
    <a class="sidebar-identity flex min-w-0 items-center gap-3" href="<?=url('settings')?>" title="Personalizar empresa">
      <div class="sidebar-logo grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white p-1 text-teal-600 shadow-lg">
        <?php if ($branding['has_logo']): ?>
          <img class="h-full w-full rounded-xl object-contain" src="<?=e($branding['logo_url'])?>" alt="Logotipo de <?=e($branding['company_name'])?>">
        <?php else: ?>
          <i data-lucide="landmark"></i>
        <?php endif; ?>
      </div>
      <div class="sidebar-brand-copy min-w-0"><div class="truncate font-bold text-white"><?=e($branding['company_name'])?></div><div class="text-xs text-slate-300">Gestão financeira</div></div>
    </a>
    <button id="sidebarCollapseBtn" type="button" class="ml-auto hidden h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white lg:grid" title="Recolher ou expandir menu" aria-label="Recolher ou expandir menu"><i data-lucide="panel-left-close"></i></button>
  </div>
  <nav class="space-y-1 text-sm font-medium">
    <a class="nav-link <?=$navClass('dashboard')?>" title="Dashboard" href="<?=url('dashboard')?>"><i data-lucide="layout-dashboard"></i><span class="sidebar-label">Dashboard</span></a>
    <a class="nav-link <?=$navClass('payables')?>" title="Contas a pagar" href="<?=url('payables')?>"><i class="text-red-400" data-lucide="arrow-up-circle"></i><span class="sidebar-label">Contas a pagar</span></a>
    <a class="nav-link <?=$navClass('receivables')?>" title="Contas a receber" href="<?=url('receivables')?>"><i class="text-emerald-400" data-lucide="arrow-down-circle"></i><span class="sidebar-label">Contas a receber</span></a>
    <a class="nav-link <?=$navClass('banks')?>" title="Contas bancárias" href="<?=url('banks')?>"><i data-lucide="landmark"></i><span class="sidebar-label">Contas bancárias</span></a>
    <a class="nav-link <?=$navClass('reconciliation')?>" title="Conciliação" href="<?=url('reconciliation')?>"><i data-lucide="scale"></i><span class="sidebar-label">Conciliação</span></a>
    <div class="sidebar-section px-3 pt-5 pb-1 text-[11px] uppercase tracking-widest">Cadastros</div>
    <a class="nav-link <?=$navClass('categories')?>" title="Categorias" href="<?=url('categories')?>"><i data-lucide="tags"></i><span class="sidebar-label">Categorias</span></a>
    <a class="nav-link <?=$navClass('accounting')?>" title="Plano de contas" href="<?=url('accounting')?>"><i data-lucide="network"></i><span class="sidebar-label">Plano de contas</span></a>
    <a class="nav-link <?=$navClass('contacts')?>" title="Contatos" href="<?=url('contacts')?>"><i data-lucide="users"></i><span class="sidebar-label">Contatos</span></a>
    <div class="sidebar-section px-3 pt-5 pb-1 text-[11px] uppercase tracking-widest">Relatórios</div>
    <a class="nav-link <?=$navClass('reports')?>" title="Fluxo, DRE e mensal" href="<?=url('reports')?>"><i data-lucide="bar-chart-3"></i><span class="sidebar-label">Fluxo, DRE e mensal</span></a>
    <div class="sidebar-section px-3 pt-5 pb-1 text-[11px] uppercase tracking-widest">Sistema</div>
    <a class="nav-link <?=$navClass('settings')?>" title="Configurações da empresa" href="<?=url('settings')?>"><i data-lucide="settings"></i><span class="sidebar-label">Personalização</span></a>
  </nav>
</aside>
<div id="appShell" class="min-h-screen lg:pl-64">
<header class="app-header sticky top-0 z-20 flex h-16 items-center justify-between border-b px-4 backdrop-blur sm:px-7">
  <div class="flex items-center gap-3"><button id="menuBtn" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800 lg:hidden"><i data-lucide="menu"></i></button><div><h1 class="font-semibold"><?=e($pageTitle)?></h1><p class="hidden text-xs text-slate-500 sm:block"><?=e($branding['company_name'])?></p></div></div>
  <div class="flex items-center gap-2"><button id="themeBtn" title="Alternar modo escuro" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="moon"></i></button><span class="hidden text-sm text-slate-500 sm:inline"><?=e($_SESSION['user_name']??'')?></span><form method="post" action="<?=url('logout')?>"><?=csrf_field()?><button class="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" title="Sair"><i data-lucide="log-out"></i></button></form></div>
</header>
<main class="app-main p-4 sm:p-7">
<?php foreach($_SESSION['flash']??[] as $f):?><div class="flash mb-4 rounded-xl border px-4 py-3 text-sm <?=($f['type']==='success'?'border-emerald-200 bg-emerald-50 text-emerald-700':'border-red-200 bg-red-50 text-red-700')?>"><?=e($f['message'])?></div><?php endforeach; unset($_SESSION['flash']);?>

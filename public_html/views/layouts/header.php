<?php $current=trim((string)($_GET['route']??'dashboard'),'/'); $navClass=fn(string $route)=>str_starts_with($current,$route)?'bg-teal-600 text-white shadow':'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'; ?>
<!doctype html><html lang="pt-BR" class="scroll-smooth"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($pageTitle)?> · <?=e(config('app_name'))?></title>
<script>if(localStorage.theme==='dark'||(!('theme'in localStorage)&&matchMedia('(prefers-color-scheme:dark)').matches))document.documentElement.classList.add('dark');if(localStorage.sidebar==='collapsed')document.documentElement.classList.add('sidebar-collapsed')</script>
<script src="https://cdn.tailwindcss.com"></script><script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"><link rel="stylesheet" href="<?=e(asset('css/financial-filters.css'))?>"><script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
</head><body class="bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
<div id="backdrop" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-slate-200 bg-white p-4 transition-transform dark:border-slate-800 dark:bg-slate-900 lg:translate-x-0">
  <div class="sidebar-brand mb-7 flex items-center gap-3 px-2 py-2"><div class="sidebar-logo grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-teal-600 text-white"><i data-lucide="landmark"></i></div><div class="sidebar-brand-copy"><div class="font-bold">FinanControl</div><div class="text-xs text-slate-500">Gestão empresarial</div></div><button id="sidebarCollapseBtn" type="button" class="ml-auto hidden h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 lg:grid" title="Recolher ou expandir menu" aria-label="Recolher ou expandir menu"><i data-lucide="panel-left-close"></i></button></div>
  <nav class="space-y-1 text-sm font-medium">
    <a class="nav-link <?=$navClass('dashboard')?>" title="Dashboard" href="<?=url('dashboard')?>"><i data-lucide="layout-dashboard"></i><span class="sidebar-label">Dashboard</span></a>
    <a class="nav-link <?=$navClass('payables')?>" title="Contas a pagar" href="<?=url('payables')?>"><i class="text-red-500" data-lucide="arrow-up-circle"></i><span class="sidebar-label">Contas a pagar</span></a>
    <a class="nav-link <?=$navClass('receivables')?>" title="Contas a receber" href="<?=url('receivables')?>"><i class="text-emerald-500" data-lucide="arrow-down-circle"></i><span class="sidebar-label">Contas a receber</span></a>
    <a class="nav-link <?=$navClass('banks')?>" title="Contas bancárias" href="<?=url('banks')?>"><i data-lucide="landmark"></i><span class="sidebar-label">Contas bancárias</span></a>
    <a class="nav-link <?=$navClass('reconciliation')?>" title="Conciliação" href="<?=url('reconciliation')?>"><i data-lucide="scale"></i><span class="sidebar-label">Conciliação</span></a>
    <div class="sidebar-section px-3 pt-5 pb-1 text-[11px] uppercase tracking-widest text-slate-400">Cadastros</div>
    <a class="nav-link <?=$navClass('categories')?>" title="Categorias" href="<?=url('categories')?>"><i data-lucide="tags"></i><span class="sidebar-label">Categorias</span></a>
    <a class="nav-link <?=$navClass('contacts')?>" title="Contatos" href="<?=url('contacts')?>"><i data-lucide="users"></i><span class="sidebar-label">Contatos</span></a>
    <div class="sidebar-section px-3 pt-5 pb-1 text-[11px] uppercase tracking-widest text-slate-400">Relatórios</div>
    <a class="nav-link <?=$current==='reports'?'bg-teal-600 text-white shadow':'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'?>" title="Fluxo, DRE e mensal" href="<?=url('reports')?>"><i data-lucide="bar-chart-3"></i><span class="sidebar-label">Fluxo, DRE e mensal</span></a>
  </nav>
</aside>
<div id="appShell" class="min-h-screen lg:pl-64">
<header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90 sm:px-7">
  <div class="flex items-center gap-3"><button id="menuBtn" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800 lg:hidden"><i data-lucide="menu"></i></button><div><h1 class="font-semibold"><?=e($pageTitle)?></h1><p class="hidden text-xs text-slate-500 sm:block"><?=e(config('company_name'))?></p></div></div>
  <div class="flex items-center gap-2"><button id="themeBtn" title="Alternar modo escuro" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="moon"></i></button><span class="hidden text-sm text-slate-500 sm:inline"><?=e($_SESSION['user_name']??'')?></span><form method="post" action="<?=url('logout')?>"><?=csrf_field()?><button class="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" title="Sair"><i data-lucide="log-out"></i></button></form></div>
</header><main class="p-4 sm:p-7">
<?php foreach($_SESSION['flash']??[] as $f):?><div class="flash mb-4 rounded-xl border px-4 py-3 text-sm <?=($f['type']==='success'?'border-emerald-200 bg-emerald-50 text-emerald-700':'border-red-200 bg-red-50 text-red-700')?>"><?=e($f['message'])?></div><?php endforeach; unset($_SESSION['flash']);?>

<dialog id="movementDetailsDialog" class="w-[calc(100%-2rem)] max-w-2xl rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
  <div class="flex max-h-[88vh] flex-col">
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
      <h2 id="movementDetailsTitle" class="text-lg font-semibold">Detalhes</h2>
      <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" data-modal-close="movementDetailsDialog" aria-label="Fechar"><i data-lucide="x"></i></button>
    </div>
    <div id="movementDetailsContent" class="overflow-y-auto p-5"></div>
  </div>
</dialog>
</main></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script><script src="<?=e(asset('js/app.js'))?>"></script>
<?php if(!empty($pageScripts)) echo $pageScripts; ?>
</body></html>

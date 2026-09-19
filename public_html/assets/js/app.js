// A indisponibilidade do CDN de ícones não pode interromper as ações da tela.
if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
const sidebar=document.getElementById('sidebar'),backdrop=document.getElementById('backdrop');
document.getElementById('menuBtn')?.addEventListener('click',()=>{sidebar.classList.toggle('-translate-x-full');backdrop.classList.toggle('hidden')});
backdrop?.addEventListener('click',()=>{sidebar.classList.add('-translate-x-full');backdrop.classList.add('hidden')});
const sidebarCollapseBtn=document.getElementById('sidebarCollapseBtn');
const syncSidebarState=()=>sidebarCollapseBtn?.setAttribute('aria-expanded',String(!document.documentElement.classList.contains('sidebar-collapsed')));
sidebarCollapseBtn?.addEventListener('click',()=>{document.documentElement.classList.toggle('sidebar-collapsed');localStorage.sidebar=document.documentElement.classList.contains('sidebar-collapsed')?'collapsed':'expanded';syncSidebarState()});
syncSidebarState();
document.getElementById('themeBtn')?.addEventListener('click',()=>{document.documentElement.classList.toggle('dark');localStorage.theme=document.documentElement.classList.contains('dark')?'dark':'light'});
document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault()}));
document.querySelectorAll('[data-filter-toggle]').forEach(button=>button.addEventListener('click',()=>{
  const panel=document.getElementById(button.dataset.filterToggle);
  if(!panel)return;
  panel.classList.toggle('hidden');
  button.setAttribute('aria-expanded',String(!panel.classList.contains('hidden')));
}));

document.querySelectorAll('[data-auto-filter-form]').forEach(form=>{
  let submitting=false,searchTimer=null;
  const feedback=form.querySelector('[data-filter-feedback]');
  const closeDateFilters=except=>form.querySelectorAll('[data-date-filter]').forEach(filter=>{
    if(filter===except)return;
    filter.querySelector('[data-date-popover]')?.classList.add('hidden');
    filter.querySelector('[data-date-trigger]')?.setAttribute('aria-expanded','false');
  });
  const setLoading=()=>{
    submitting=true;
    form.classList.add('is-loading');
    form.setAttribute('aria-busy','true');
    if(feedback)feedback.textContent='Atualizando resultados…';
    closeDateFilters();
  };
  const submitFilters=()=>{
    if(submitting||!form.reportValidity())return;
    if(typeof form.requestSubmit==='function')form.requestSubmit();
    else{setLoading();form.submit();}
  };
  form.addEventListener('submit',event=>{
    if(submitting){event.preventDefault();return;}
    if(searchTimer)clearTimeout(searchTimer);
    setLoading();
  });

  form.querySelectorAll('[data-auto-submit]').forEach(field=>field.addEventListener('change',()=>{
    if(field.type==='radio'){
      const detailedStatus=form.querySelector('select[name="status"]');
      if(detailedStatus)detailedStatus.value='';
      form.querySelectorAll('.financial-status-option').forEach(option=>option.classList.toggle('is-active',option.contains(field)));
    }else if(field.name==='status'){
      const allView=form.querySelector('input[name="view"][value="all"]');
      if(allView)allView.checked=true;
      form.querySelectorAll('.financial-status-option').forEach(option=>option.classList.toggle('is-active',option.contains(allView)));
    }
    submitFilters();
  }));

  const search=form.querySelector('[data-auto-search]');
  if(search){
    let appliedSearch=search.value.trim();
    search.addEventListener('input',()=>{
      if(searchTimer)clearTimeout(searchTimer);
      const nextSearch=search.value.trim();
      if(nextSearch===appliedSearch)return;
      searchTimer=setTimeout(()=>{appliedSearch=nextSearch;submitFilters();},650);
    });
  }

  const pad=value=>String(value).padStart(2,'0');
  const toIso=date=>`${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}`;
  const shifted=(date,days)=>{const result=new Date(date);result.setDate(result.getDate()+days);return result;};
  const dateRange=preset=>{
    const today=new Date();today.setHours(0,0,0,0);
    if(preset==='any')return ['',''];
    if(preset==='today'){const value=toIso(today);return [value,value];}
    if(preset==='week'){
      const monday=shifted(today,-((today.getDay()+6)%7));
      return [toIso(monday),toIso(shifted(monday,6))];
    }
    if(preset==='month')return [toIso(new Date(today.getFullYear(),today.getMonth(),1)),toIso(new Date(today.getFullYear(),today.getMonth()+1,0))];
    if(preset==='previous-month')return [toIso(new Date(today.getFullYear(),today.getMonth()-1,1)),toIso(new Date(today.getFullYear(),today.getMonth(),0))];
    if(preset==='next-30')return [toIso(today),toIso(shifted(today,29))];
    return null;
  };
  const displayDate=value=>value?value.split('-').reverse().join('/'):'';
  const shortDate=value=>{const [year,month,day]=value.split('-');return `${day}/${month}/${year.slice(-2)}`;};
  const rangeSummary=(start,end)=>{
    if(!start&&!end)return 'Qualquer data';
    if(start&&start===end)return displayDate(start);
    if(start&&end)return `${shortDate(start)} a ${shortDate(end)}`;
    if(start)return `A partir de ${shortDate(start)}`;
    return `Até ${shortDate(end)}`;
  };

  form.querySelectorAll('[data-date-filter]').forEach(filter=>{
    const trigger=filter.querySelector('[data-date-trigger]');
    const popover=filter.querySelector('[data-date-popover]');
    const start=filter.querySelector('[data-date-start]');
    const end=filter.querySelector('[data-date-end]');
    const summary=filter.querySelector('[data-date-summary]');
    const customRange=filter.querySelector('[data-custom-range]');
    const customToggle=filter.querySelector('[data-date-preset="custom"]');
    let customStartedEmpty=false;
    const presetButtons=[...filter.querySelectorAll('[data-date-preset]:not([data-date-preset="custom"])')];
    const matchingPreset=()=>presetButtons.find(button=>{
      const range=dateRange(button.dataset.datePreset);
      return range&&start.value===range[0]&&end.value===range[1];
    });
    const refreshDateState=()=>{
      const matched=matchingPreset();
      presetButtons.forEach(button=>button.classList.toggle('is-active',button===matched));
      const customActive=!matched&&(start.value!==''||end.value!=='');
      customToggle?.classList.toggle('is-active',customActive);
      filter.classList.toggle('is-active',start.value!==''||end.value!=='');
      if(summary)summary.textContent=rangeSummary(start.value,end.value);
      return matched;
    };
    const showCustomRange=show=>{
      customRange?.classList.toggle('hidden',!show);
      customToggle?.classList.toggle('is-open',show);
      customToggle?.setAttribute('aria-expanded',String(show));
    };
    const initialMatch=refreshDateState();
    showCustomRange(!initialMatch&&(start.value!==''||end.value!==''));

    trigger?.addEventListener('click',event=>{
      event.stopPropagation();
      const opening=popover.classList.contains('hidden');
      closeDateFilters(filter);
      popover.classList.toggle('hidden',!opening);
      trigger.setAttribute('aria-expanded',String(opening));
    });
    popover?.addEventListener('click',event=>event.stopPropagation());
    presetButtons.forEach(button=>button.addEventListener('click',()=>{
      const [from,to]=dateRange(button.dataset.datePreset);
      start.value=from;end.value=to;
      refreshDateState();
      submitFilters();
    }));
    customToggle?.addEventListener('click',()=>{
      const opening=customRange.classList.contains('hidden');
      showCustomRange(opening);
      customStartedEmpty=opening&&!start.value&&!end.value;
      if(opening)start.focus();
    });
    [start,end].forEach(input=>input?.addEventListener('change',()=>{
      start.setCustomValidity('');end.setCustomValidity('');
      refreshDateState();
      if(customStartedEmpty&&start.value&&end.value){customStartedEmpty=false;filter.querySelector('[data-date-apply]')?.click();}
    }));
    filter.querySelector('[data-date-clear]')?.addEventListener('click',()=>{
      start.value='';end.value='';
      refreshDateState();
      submitFilters();
    });
    filter.querySelector('[data-date-apply]')?.addEventListener('click',()=>{
      start.setCustomValidity('');end.setCustomValidity('');
      if(start.value&&end.value&&start.value>end.value){end.setCustomValidity('A data final deve ser igual ou posterior à data inicial.');end.reportValidity();return;}
      if(!start.value&&!end.value){submitFilters();return;}
      submitFilters();
    });
  });

  document.addEventListener('click',()=>closeDateFilters());
  document.addEventListener('keydown',event=>{if(event.key==='Escape')closeDateFilters()});
});
setTimeout(()=>document.querySelectorAll('.flash').forEach(el=>el.remove()),5000);

document.querySelectorAll('[data-per-page]').forEach(select=>select.addEventListener('change',()=>{
  const target=new URL(window.location.href);
  target.searchParams.set('per_page',select.value);
  target.searchParams.set('page','1');
  window.location.assign(target.toString());
}));

const closeActionMenus=except=>document.querySelectorAll('[data-action-menu]').forEach(menu=>{if(menu!==except)menu.classList.add('hidden')});
document.addEventListener('click',event=>{
  const toggle=event.target.closest('[data-menu-toggle]');
  if(toggle){
    event.stopPropagation();
    const menu=toggle.parentElement.querySelector('[data-action-menu]');
    const willOpen=menu.classList.contains('hidden');
    closeActionMenus(menu);
    menu.classList.toggle('hidden',!willOpen);
    if(willOpen){
      const rect=toggle.getBoundingClientRect();
      menu.style.right=`${Math.max(8,window.innerWidth-rect.right)}px`;
      menu.style.left='auto';
      menu.style.top=`${rect.bottom+6}px`;
      if(rect.bottom+menu.offsetHeight+12>window.innerHeight)menu.style.top=`${Math.max(8,rect.top-menu.offsetHeight-6)}px`;
    }
    return;
  }
  if(!event.target.closest('[data-action-menu]'))closeActionMenus();
});
window.addEventListener('resize',()=>closeActionMenus());
window.addEventListener('scroll',()=>closeActionMenus(),true);

document.querySelectorAll('[data-modal-close]').forEach(button=>button.addEventListener('click',()=>document.getElementById(button.dataset.modalClose)?.close()));
document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close()}));

document.querySelectorAll('[data-bulk-selection]').forEach(scope=>{
  const selectAll=scope.querySelector('[data-select-all]');
  const items=[...scope.querySelectorAll('[data-select-item]')];
  const actions=[...scope.querySelectorAll('[data-bulk-action]')];
  const count=scope.querySelector('[data-selected-count]');
  const selected=()=>items.filter(item=>item.checked).map(item=>item.value);
  const update=()=>{
    const total=selected().length;
    count.textContent=String(total);
    actions.forEach(action=>action.disabled=total===0);
    if(selectAll){selectAll.checked=items.length>0&&total===items.length;selectAll.indeterminate=total>0&&total<items.length;}
  };
  selectAll?.addEventListener('change',()=>{items.forEach(item=>item.checked=selectAll.checked);update()});
  items.forEach(item=>item.addEventListener('change',update));
  actions.forEach(action=>action.addEventListener('click',()=>{
    const ids=selected();
    if(!ids.length)return;
    const dialog=document.getElementById(action.dataset.bulkOpen);
    const form=dialog?.querySelector('form');
    if(!dialog||!form)return;
    form.reset();
    const holder=form.querySelector('[data-bulk-ids]');
    holder.replaceChildren(...ids.map(id=>{const input=document.createElement('input');input.type='hidden';input.name='ids[]';input.value=id;return input;}));
    dialog.querySelectorAll('[data-bulk-dialog-count]').forEach(element=>element.textContent=String(ids.length));
    dialog.showModal();
  }));
  update();
});

const escapeHtml=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
const formatMoney=value=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(value||0));
const formatDate=value=>value?value.split('-').reverse().join('/'):'—';
const methodLabel=value=>String(value||'—').replaceAll('_',' ').replace(/\b\w/g,char=>char.toUpperCase());
const renderLogChanges=value=>{
  try{
    const changes=JSON.parse(value||'{}'),rows=Object.entries(changes);
    if(!rows.length)return '';
    return `<div class="mt-3 space-y-1 border-t border-slate-100 pt-2 text-xs dark:border-slate-700">${rows.map(([label,change])=>`<p><span class="font-medium">${escapeHtml(label)}:</span> ${escapeHtml(change.from??'—')} → ${escapeHtml(change.to??'—')}</p>`).join('')}</div>`;
  }catch{return '';}
};

async function openMovementDetails(button,mode){
  closeActionMenus();
  const dialog=document.getElementById('movementDetailsDialog');
  const title=document.getElementById('movementDetailsTitle');
  const content=document.getElementById('movementDetailsContent');
  title.textContent=mode==='log'?'Log de alterações':'Detalhes da movimentação';
  content.innerHTML='<p class="py-8 text-center text-sm text-slate-500">Carregando...</p>';
  dialog.showModal();
  try{
    const response=await fetch(`${button.dataset.detailsUrl}&entity=${encodeURIComponent(button.dataset.entity)}&id=${encodeURIComponent(button.dataset.id)}`,{headers:{Accept:'application/json'}});
    const data=await response.json();
    if(!response.ok)throw new Error(data.error||'Não foi possível carregar os dados.');
    const item=data.item;
    if(mode==='log'){
      content.innerHTML=data.logs.length?`<div class="space-y-3">${data.logs.map(log=>`<article class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"><div class="flex flex-wrap justify-between gap-2"><strong>${escapeHtml(log.description)}</strong><span class="text-xs text-slate-500">${escapeHtml(log.created_at)}</span></div><p class="mt-1 text-xs text-slate-500">${escapeHtml(log.user_name)}</p>${renderLogChanges(log.changes)}</article>`).join('')}</div>`:'<p class="py-8 text-center text-sm text-slate-500">Nenhuma alteração registrada.</p>';
      return;
    }
    const payable=data.entity_type==='payable';
    const total=payable?item.amount:item.expected_amount;
    const realized=payable?item.paid_amount:item.received_amount;
    const remaining=Number(item.remaining_amount);
    const contact=payable?'Fornecedor':'Cliente';
    const transactions=data.transactions.length?data.transactions.map(transaction=>`<tr><td>${formatDate(transaction.transaction_date)}</td><td class="font-semibold">${formatMoney(transaction.amount)}</td><td>${escapeHtml(transaction.bank_name)}</td><td>${escapeHtml(methodLabel(transaction.payment_method))}</td><td>${escapeHtml(transaction.notes||'—')}</td><td>${escapeHtml(transaction.user_name)}</td></tr>`).join(''):'<tr><td colspan="6" class="text-center text-slate-500">Nenhuma baixa registrada.</td></tr>';
    content.innerHTML=`<div class="grid gap-3 sm:grid-cols-2"><div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><span class="text-xs text-slate-500">Descrição</span><p class="font-medium">${escapeHtml(item.description)}</p></div><div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><span class="text-xs text-slate-500">Status</span><p class="font-medium">${escapeHtml(methodLabel(item.status))}</p></div><div><span class="text-xs text-slate-500">${contact}</span><p>${escapeHtml(item.contact_name||'—')}</p></div><div><span class="text-xs text-slate-500">Categoria</span><p>${escapeHtml(item.category_name||'—')}</p></div><div><span class="text-xs text-slate-500">Vencimento</span><p>${formatDate(item.due_date)}</p></div><div><span class="text-xs text-slate-500">Forma prevista</span><p>${escapeHtml(methodLabel(payable?item.payment_method:item.receipt_method))}</p></div></div><div class="my-5 grid grid-cols-3 gap-3"><div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"><span class="text-xs text-slate-500">Total</span><p class="font-bold">${formatMoney(total)}</p></div><div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"><span class="text-xs text-slate-500">${payable?'Pago':'Recebido'}</span><p class="font-bold text-teal-600">${formatMoney(realized)}</p></div><div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"><span class="text-xs text-slate-500">Restante</span><p class="font-bold ${remaining>0?'text-amber-600':''}">${formatMoney(remaining)}</p></div></div><div><span class="text-xs text-slate-500">Observações</span><p class="mt-1 text-sm">${escapeHtml(item.notes||'—')}</p></div><h3 class="mb-2 mt-6 font-semibold">Histórico de baixas</h3><div class="table-wrap rounded-xl border border-slate-200 dark:border-slate-700"><table class="data-table"><thead><tr><th>Data</th><th>Valor</th><th>Banco</th><th>Forma</th><th>Observação</th><th>Usuário</th></tr></thead><tbody>${transactions}</tbody></table></div>`;
  }catch(error){content.innerHTML=`<p class="rounded-xl bg-red-50 p-4 text-sm text-red-700">${escapeHtml(error.message)}</p>`;}
}

document.querySelectorAll('[data-movement-details]').forEach(button=>button.addEventListener('click',()=>openMovementDetails(button,'details')));
document.querySelectorAll('[data-movement-log]').forEach(button=>button.addEventListener('click',()=>openMovementDetails(button,'log')));

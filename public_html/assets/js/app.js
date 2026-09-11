lucide.createIcons();
const sidebar=document.getElementById('sidebar'),backdrop=document.getElementById('backdrop');
document.getElementById('menuBtn')?.addEventListener('click',()=>{sidebar.classList.toggle('-translate-x-full');backdrop.classList.toggle('hidden')});
backdrop?.addEventListener('click',()=>{sidebar.classList.add('-translate-x-full');backdrop.classList.add('hidden')});
document.getElementById('themeBtn')?.addEventListener('click',()=>{document.documentElement.classList.toggle('dark');localStorage.theme=document.documentElement.classList.contains('dark')?'dark':'light'});
document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault()}));
setTimeout(()=>document.querySelectorAll('.flash').forEach(el=>el.remove()),5000);

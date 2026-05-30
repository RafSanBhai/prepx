/* PrepX — main.js v3 (vanilla JS only) */

// ── Admin sidebar ──────────────────────────────────────────
(function(){
  var ham=document.getElementById('sidebarHam');
  var bar=document.getElementById('sidebar');
  var ov=document.getElementById('sidebarOverlay');
  if(!ham||!bar) return;
  function open(){bar.classList.add('open');ov&&ov.classList.add('open');document.body.style.overflow='hidden';}
  function close(){bar.classList.remove('open');ov&&ov.classList.remove('open');document.body.style.overflow='';}
  ham.addEventListener('click',function(){bar.classList.contains('open')?close():open();});
  ov&&ov.addEventListener('click',close);
  bar.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){if(window.innerWidth<900)close();});});
})();

// ── Student nav hamburger ──────────────────────────────────
(function(){
  var ham=document.getElementById('studentNavHam');
  var menu=document.getElementById('studentNavMobile');
  if(!ham||!menu) return;
  ham.addEventListener('click',function(){menu.classList.toggle('open');});
  document.addEventListener('click',function(e){
    if(!ham.contains(e.target)&&!menu.contains(e.target)) menu.classList.remove('open');
  });
})();

// ── Exam countdown timer ───────────────────────────────────
(function(){
  var el=document.getElementById('examTimer');
  var form=document.getElementById('examForm');
  if(!el||!form) return;
  var secs=parseInt(el.dataset.seconds||'0',10);
  function fmt(s){
    var h=Math.floor(s/3600),m=Math.floor((s%3600)/60),sc=s%60;
    var p=function(n){return String(n).padStart(2,'0');};
    return h>0?p(h)+':'+p(m)+':'+p(sc):p(m)+':'+p(sc);
  }
  el.textContent=fmt(secs);
  var iv=setInterval(function(){
    secs--;
    el.textContent=fmt(secs);
    if(secs<=300) el.classList.add('danger');
    if(secs<=0){
      clearInterval(iv);
      el.textContent='00:00';
      var h=document.createElement('input');
      h.type='hidden';h.name='auto_submit';h.value='1';
      form.appendChild(h);form.submit();
    }
  },1000);
})();

// ── Auto-dismiss flash ────────────────────────────────────
(function(){
  var f=document.getElementById('flashMsg');
  if(f) setTimeout(function(){f.style.transition='opacity .4s';f.style.opacity='0';setTimeout(function(){f.remove();},400);},5000);
})();

// ── Confirm dialogs ───────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(function(el){
  el.addEventListener('click',function(e){if(!confirm(el.dataset.confirm))e.preventDefault();});
});

// ── Modal open/close ──────────────────────────────────────
document.querySelectorAll('[data-modal-open]').forEach(function(btn){
  btn.addEventListener('click',function(){
    var m=document.getElementById(btn.dataset.modalOpen);
    if(m) m.classList.add('open');
  });
});
document.querySelectorAll('[data-modal-close]').forEach(function(btn){
  btn.addEventListener('click',function(){
    var m=btn.closest('.modal-backdrop');
    if(m) m.classList.remove('open');
  });
});
document.querySelectorAll('.modal-backdrop').forEach(function(bd){
  bd.addEventListener('click',function(e){if(e.target===bd)bd.classList.remove('open');});
});

/** TapCard Pro v1.5.4 FINAL — Created by Apostolis Karamichalis. */
(function(){'use strict';
const root=window.TapCardPro||{};
const api=(p,o={})=>fetch((root.apiRoot||'')+p,Object.assign({headers:{'Content-Type':'application/json','X-WP-Nonce':root.nonce||''}},o));
const esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const fields=['phone','mobile','email','website','google_service','maps_url','facebook','instagram','linkedin','tiktok','youtube','whatsapp','viber','telegram','efood','wolt','box'];
const labels={phone:'Τηλέφωνο',mobile:'Κινητό',email:'Email',website:'Site',google_service:'Google',maps_url:'Google Maps',facebook:'Facebook',instagram:'Instagram',linkedin:'LinkedIn',tiktok:'TikTok',youtube:'YouTube',whatsapp:'WhatsApp',viber:'Viber',telegram:'Telegram',efood:'eFood',wolt:'Wolt',box:'BOX'};
function togglePassword(e){const b=e.target.closest('.tcp-show-password');if(!b)return;const x=document.getElementById(b.dataset.target);if(!x)return;x.type=x.type==='password'?'text':'password';b.textContent=x.type==='password'?'Εμφάνιση':'Απόκρυψη';}
document.addEventListener('click',togglePassword,true);
function makeControls(p){
 const v=document.getElementById('tcp-visible-fields'),o=document.getElementById('tcp-field-order');if(!v||!o)return;
 const vis=p.visibility||{};let order=Array.isArray(p.field_order)?p.field_order.filter(x=>fields.includes(x)):fields.slice();order=order.concat(fields.filter(x=>!order.includes(x)));
 v.innerHTML=fields.map(k=>'<label class="tcp-visibility-item"><input type="checkbox" data-tcp-vis="'+k+'" '+(vis[k]!==false?'checked':'')+'> '+esc(labels[k])+'</label>').join('');
 o.innerHTML=order.map(k=>'<div class="tcp-sort-item" draggable="true" data-tcp-sort="'+k+'">☰ <span>'+esc(labels[k])+'</span></div>').join('');
 let drag=null;o.querySelectorAll('[data-tcp-sort]').forEach(el=>{el.addEventListener('dragstart',()=>drag=el.dataset.tcpSort);el.addEventListener('dragover',e=>e.preventDefault());el.addEventListener('drop',e=>{e.preventDefault();if(!drag||drag===el.dataset.tcpSort)return;const arr=[...o.querySelectorAll('[data-tcp-sort]')].map(x=>x.dataset.tcpSort).filter(x=>x!==drag);const idx=arr.indexOf(el.dataset.tcpSort);arr.splice(idx,0,drag);o.innerHTML=arr.map(k=>'<div class="tcp-sort-item" draggable="true" data-tcp-sort="'+k+'">☰ <span>'+esc(labels[k])+'</span></div>').join('');makeControls({visibility:Object.fromEntries(fields.map(k=>[k,v.querySelector('[data-tcp-vis="'+k+'"]')?.checked!==false])),field_order:arr});});});
}
function enhancedSave(){
 const f=document.getElementById('tcp-user-form');if(!f)return;
 f.addEventListener('submit',function(){
   setTimeout(async function(){
     try{
       const d={};f.querySelectorAll('[name]').forEach(x=>{if(x.type!=='file')d[x.name]=x.value});
       d.visibility={};fields.forEach(k=>{const x=f.querySelector('[data-tcp-vis="'+k+'"]');d.visibility[k]=x?x.checked:true});
       d.field_order=[...document.querySelectorAll('#tcp-field-order [data-tcp-sort]')].map(x=>x.dataset.tcpSort);
       const r=await api('/me',{method:'POST',body:JSON.stringify(d)});const p=await r.json();
       if(p.code){const out=document.getElementById('tcp-user-result');if(out)out.textContent=p.message||'Δεν αποθηκεύτηκαν οι αλλαγές.';return;}
       const out=document.getElementById('tcp-user-result');if(out)out.textContent='Οι αλλαγές αποθηκεύτηκαν.';
       const app=document.getElementById('tcp-user-form'); if(app&&p.profile){app.dataset.profileId=p.profile.id||'';}
       makeControls(p.profile||{});
     }catch(e){const out=document.getElementById('tcp-user-result');if(out)out.textContent='Δεν ήταν δυνατή η αποθήκευση.';}
   },400);
 });
}
async function init(){
 const f=document.getElementById('tcp-user-form');if(!f)return;
 try{const r=await api('/me');const d=await r.json();if(d.profile){makeControls(d.profile);}else makeControls({});}catch(e){makeControls({});}
 enhancedSave();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();

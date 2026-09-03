/** TapCard Pro v1.7.7 — Created by Apostolis Karamichalis. */
(function(){
'use strict';
const root=window.TapCardPro||{};
const api=(p,o={})=>fetch((root.apiRoot||'')+p,Object.assign({credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':root.nonce||''}},o));
const esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const norm=u=>!u?'':(/^[a-z][a-z0-9+.-]*:/i.test(u)||/^https?:\/\//i.test(u)?u:'https://'+u);
const ICONS={phone:'☎️',mobile:'📱',email:'✉️',website:'🌐',google_service:'⭐',maps_url:'📍',facebook:'f',instagram:'◎',linkedin:'in',tiktok:'♪',youtube:'▶️',whatsapp:'🟢'};
const LABELS={phone:'Τηλέφωνο',mobile:'Κινητό',email:'Email',website:'Site',google_service:'Google',maps_url:'Google Maps',facebook:'Facebook',instagram:'Instagram',linkedin:'LinkedIn',tiktok:'TikTok',youtube:'YouTube',whatsapp:'WhatsApp'};
const CAPTIONS={phone:v=>'Καλέστε μας στο '+v,mobile:v=>'Καλέστε μας στο '+v,email:()=> 'Στείλτε μας email',website:()=> 'Επισκεφτητε το site μας',google_service:()=>'Δείτε μας στο Google',maps_url:()=>'Βρείτε μας στο Google Maps',facebook:()=>'Δείτε μας στο Facebook',instagram:()=>'Ακολουθήστε μας',linkedin:()=>'Δείτε το LinkedIn μας',tiktok:()=>'Ακολουθήστε μας',youtube:()=>'Δείτε το YouTube κανάλι',whatsapp:v=>'Chat στο WhatsApp'};
function href(k,v){if(!v)return '';if(k==='phone'||k==='mobile')return 'tel:'+String(v).replace(/[^\d+]/g,'');if(k==='email')return 'mailto:'+v;if(k==='whatsapp'&&!/^https?:/i.test(v))return 'https://wa.me/'+String(v).replace(/[^\d+]/g,'');if(/^https?:/i.test(v))return v;return 'https://'+v;}
function iconFor(k,p){const c=p['icon_'+k];if(c&&/^https?:\/\//i.test(c))return '<img src="'+esc(c)+'" alt="" class="tcp-custom-icon">';return ICONS[c]||ICONS[k]||'•'}
function customer(){
 let t=window.TapCardInitialToken||'';if(!t){const m=location.pathname.match(/\/tapcard\/([^/]+)/i);if(m)t=decodeURIComponent(m[1])}if(!t)return;
 api('/profile/'+encodeURIComponent(t)).then(r=>r.json()).then(p=>{
  if(p.code)throw Error(p.message||'Profile not found');
  const loading=document.getElementById('tcp-customer-loading'),content=document.getElementById('tcp-customer-content');if(loading)loading.hidden=true;if(content)content.hidden=false;
  const card=document.querySelector('.tcp-customer-card');if(card){card.style.setProperty('--tcp-card-bg',p.card_background||'#fff');card.style.setProperty('--tcp-button-bg',p.button_background||'#007bff')}
  if(p.logo_url){const e=document.getElementById('tcp-logo');e.src=norm(p.logo_url);e.hidden=false}
  if(p.photo_url){const e=document.getElementById('tcp-photo');e.src=norm(p.photo_url);e.hidden=false}
  document.getElementById('tcp-name').textContent=p.full_name||'';document.getElementById('tcp-company').textContent=p.company||'';document.getElementById('tcp-title').textContent=p.job_title||'';
  const vis=p.visibility||{},order=Array.isArray(p.field_order)?p.field_order:Object.keys(ICONS),a=document.getElementById('tcp-actions');let h='';
  order.forEach(k=>{if(!ICONS[k]||vis[k]===false||!p[k])return;h+='<a class="tcp-action tcp-action-'+k+'" target="_blank" rel="noopener" href="'+esc(href(k,p[k]))+'"><span class="tcp-action-icon-s">'+iconFor(k,p)+'</span> '+esc(LABELS[k]||k)+'</a>'});
  a.innerHTML=h;
  const sh=document.getElementById('tcp-share'),modal=document.getElementById('tcp-share-modal'),url=p.profile_url||location.href;
  const closeShare=()=>{if(!modal)return;modal.hidden=true;modal.setAttribute('aria-hidden','true');document.body.classList.remove('tcp-modal-open');};
  function openShare(e){if(e){e.preventDefault();e.stopPropagation();}if(!modal)return;modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.classList.add('tcp-modal-open');
   const u=document.getElementById('tcp-share-url'),q=document.getElementById('tcp-share-qr');if(u)u.value=url;if(q)q.innerHTML='<img alt="QR Code" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='+encodeURIComponent(url)+'">';
   const email=document.getElementById('tcp-email-share'),sms=document.getElementById('tcp-sms-share'),wa=document.getElementById('tcp-whatsapp-share');
   if(email)email.href='mailto:?subject='+encodeURIComponent('Η ψηφιακή κάρτα')+'&body='+encodeURIComponent(url);if(sms)sms.href='sms:?body='+encodeURIComponent(url);if(wa)wa.href='https://wa.me/?text='+encodeURIComponent(url);
  }
  sh?.addEventListener('click',openShare);document.getElementById('tcp-share-close')?.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();closeShare();});modal?.addEventListener('click',e=>{if(e.target===modal)closeShare();});
  document.getElementById('tcp-copy-card-url')?.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(url);document.getElementById('tcp-share-result').textContent='Το URL αντιγράφηκε!';}catch(_){alert('Σφάλμα αντιγραφής');}});
  document.getElementById('tcp-download-card-qr')?.addEventListener('click',()=>{const img=document.querySelector('#tcp-share-qr img');if(!img)return;const a=document.createElement('a');a.href=img.src;a.download='tapcard-qr.png';a.click();});
  document.getElementById('tcp-native-share')?.addEventListener('click',async()=>{if(navigator.share){try{await navigator.share({title:p.full_name||p.company||'TapCard',text:'Η ψηφιακή κάρτα μου',url:url});}catch(e){if(e.name!=='AbortError')console.log(e);}}});

  document.getElementById('tcp-save-contact')?.addEventListener('click',async()=>{
   const escV=v=>String(v||'').replace(/\\/g,'\\\\').replace(/\r?\n/g,' ');const lines=['BEGIN:VCARD','VERSION:3.0','FN:'+escV(p.full_name),'ORG:'+escV(p.company),'TITLE:'+escV(p.job_title)];
   if(p.phone)lines.push('TEL;TYPE=WORK,VOICE:'+escV(p.phone));if(p.mobile)lines.push('TEL;TYPE=WORK,CELL:'+escV(p.mobile));if(p.email)lines.push('EMAIL;TYPE=WORK:'+escV(p.email));if(p.website)lines.push('URL:'+escV(norm(p.website)));
   const blob=new Blob([lines.join('\r\n')+'\r\n'],{type:'text/vcard'}),file=new File([blob],'tapcard-contact.vcf',{type:'text/vcard'});
   try{if(navigator.share&&navigator.canShare&&navigator.canShare({files:[file]})){await navigator.share({title:p.full_name||'Επαφή',files:[file]});return;}}catch(e){}
   try{const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='tapcard-contact.vcf';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1500);}catch(e){alert('Η αποθήκευση δεν είναι διαθέσιμη');}
  });
  document.getElementById('tcp-public-exit')?.addEventListener('click',e=>{e.preventDefault();try{window.close();}catch(_){}setTimeout(()=>{if(!document.hidden)history.back();},150);});
 }).catch(e=>{const x=document.getElementById('tcp-customer-loading');if(x)x.textContent=e.message||'Δεν ήταν δυνατή η φόρτωση του profile.'});
}
function mediaPicker(target){
 const field=document.querySelector('[name="'+String(target).replace(/"/g,'\\"')+'"]');
 if(!field){alert('Το πεδίο εικόνας δεν βρέθηκε.');return;}
 const apiRoot=String(root.apiRoot||'').replace(/\/$/,''); const nonce=String(root.nonce||''); const mediaToken=String(root.mediaToken||'');
 if(!apiRoot||!nonce){alert('Δεν είναι διαθέσιμη η σύνδεση με το WordPress. Κάντε ανανέωση της εφαρμογής.');return;}
 const old=document.getElementById('tcp-media-overlay');if(old)old.remove();
 const overlay=document.createElement('div');overlay.id='tcp-media-overlay';overlay.className='tcp-media-overlay';
 overlay.innerHTML='<div class="tcp-media-modal" role="dialog" aria-modal="true">'+
 '<button type="button" class="tcp-media-close" aria-label="Κλείσιμο">×</button><h2>Media Library</h2>'+ 
 '<div class="tcp-media-toolbar"><input class="tcp-media-search" type="search" placeholder="Αναζήτηση εικόνας..."><button type="button" class="tcp-button secondary tcp-media-search-btn">Αναζήτηση</button><input type="file" class="tcp-media-upload-input" accept="image/*" multiple style="display:none"><button type="button" class="tcp-button tcp-media-upload-btn">Ανέβασμα</button></div>'+
 '<div class="tcp-media-status">Φόρτωση εικόνων…</div><div class="tcp-media-grid"></div><div class="tcp-media-pagination" hidden><button type="button" class="tcp-button secondary tcp-media-prev">← Προηγούμενη</button><span class="tcp-media-page-label"></span><button type="button" class="tcp-button secondary tcp-media-next">Επόμενη →</button></div>'+
 '</div>';
 document.body.appendChild(overlay);document.body.classList.add('tcp-modal-open');
 const close=()=>{overlay.remove();document.body.classList.remove('tcp-modal-open');};
 overlay.querySelector('.tcp-media-close').onclick=close;overlay.onclick=e=>{if(e.target===overlay)close();};
 const status=overlay.querySelector('.tcp-media-status'),grid=overlay.querySelector('.tcp-media-grid'),pager=overlay.querySelector('.tcp-media-pagination'),label=overlay.querySelector('.tcp-media-page-label'),search=overlay.querySelector('.tcp-media-search'),uploadBtn=overlay.querySelector('.tcp-media-upload-btn'),uploadInput=overlay.querySelector('.tcp-media-upload-input');
 let page=1,pages=1,term='',controller=null;
 async function load(){
  if(controller)controller.abort();controller=new AbortController();const timer=setTimeout(()=>controller.abort(),15000);
  status.hidden=false;status.textContent='Φόρτωση εικόνων…';grid.innerHTML='';pager.hidden=true;
  try{
   const u=new URL(apiRoot+'/me/media',location.origin);u.searchParams.set('page',page);u.searchParams.set('per_page','36');if(term)u.searchParams.set('search',term);if(mediaToken)u.searchParams.set('token',mediaToken);
   const r=await fetch(u.toString(),{credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce,'X-TapCard-Media-Token':mediaToken,'Accept':'application/json'},signal:controller.signal});
   const text=await r.text();let d;try{d=JSON.parse(text)}catch(_){throw Error('Ο server επέστρεψε μη έγκυρη απάντηση ('+r.status+').')}
   if(!r.ok){throw Error(d.message||d.code||('Σφάλμα: HTTP '+r.status+'. '+String(d.message||'Δεν επιτρέπεται η πρόσβαση στη βιβλιοθήκη.')))}
   if(d.code){throw Error(d.message||'Σφάλμα: '+d.code)}
   if(!Array.isArray(d.items)){throw Error('Μη έγκυρη μορφή δεδομένων από το server.')}
   pages=Math.max(1,Number(d.pages||1));
   if(!d.items.length){status.textContent=term?'Δεν βρέθηκαν εικόνες.':'Δεν υπάρχουν αποθηκευμένες εικόνες.';return;}
   status.hidden=true;d.items.forEach(item=>{const b=document.createElement('button');b.type='button';b.className='tcp-media-item';b.innerHTML='<img src="'+esc(item.thumb||item.url)+'" alt=""><span>'+esc(item.name||'Εικόνα')+'</span>';b.onclick=async(e)=>{e.preventDefault();try{field.value=item.url;close();const ev=new Event('change',{bubbles:true});field.dispatchEvent(ev);}catch(err){alert('Σφάλμα επιλογής εικόνας');}};grid.appendChild(b);});
   pager.hidden=pages<=1;label.textContent='Σελίδα '+page+' από '+pages;overlay.querySelector('.tcp-media-prev').disabled=page<=1;overlay.querySelector('.tcp-media-next').disabled=page>=pages;
  }catch(err){status.hidden=false;status.textContent=err?.name==='AbortError'?'Η φόρτωση καθυστέρησε.':'❌ '+String(err.message||'Δεν ήταν δυνατή η φόρτωση της βιβλιοθήκης. Ελέγξτε τις άδειες προσβάσης.');}
  finally{clearTimeout(timer);controller=null;}
 }
 uploadBtn.onclick=()=>uploadInput.click();
 overlay.querySelector('.tcp-media-search-btn').onclick=()=>{term=search.value.trim();page=1;load();};search.onkeydown=e=>{if(e.key==='Enter'){e.preventDefault();term=search.value.trim();page=1;load();}};
 overlay.querySelector('.tcp-media-prev').onclick=()=>{if(page>1){page--;load();}};overlay.querySelector('.tcp-media-next').onclick=()=>{if(page<pages){page++;load();}};
 uploadInput.onchange=async e=>{const file=e.target.files?.[0];if(!file)return;const fd=new FormData();fd.append('file',file);status.hidden=false;status.textContent='Ανέβασμα αρχείου…';try{const r=await fetch(apiRoot+'/me/media',{method:'POST',headers:{'X-WP-Nonce':nonce,'X-TapCard-Media-Token':mediaToken},body:fd,credentials:'same-origin'}),d=await r.json();if(!r.ok||d.code)throw Error(d.message||'Σφάλμα ανεβάσματος');field.value=d.url||'';close();const ev=new Event('change',{bubbles:true});field.dispatchEvent(ev);}catch(err){status.textContent='❌ Σφάλμα ανεβάσματος: '+err.message;}};
 document.addEventListener('keydown',function escMedia(e){if(e.key==='Escape'&&document.getElementById('tcp-media-overlay')){document.removeEventListener('keydown',escMedia);close();}});load();
}
function user(){
 const f=document.getElementById('tcp-user-form');if(!f)return;
 let profileExists=false, current=null;
 const fields=Object.keys(ICONS);
 function controls(p){
  const vis=p.visibility||{},order=Array.isArray(p.field_order)?p.field_order.filter(x=>fields.includes(x)):fields.slice();const v=document.getElementById('tcp-visible-fields'),o=document.getElementById('tcp-field-order');if(!v||!o)return;
  v.innerHTML=fields.map(k=>'<label class="tcp-visibility-item"><input type="checkbox" data-tcp-vis="'+k+'" '+(vis[k]!==false?'checked':'')+'> '+esc(LABELS[k])+'</label>').join('');
  o.innerHTML=order.map(k=>'<div class="tcp-sort-item" draggable="true" data-tcp-sort="'+k+'">☰ '+esc(LABELS[k])+'</div>').join('');
  v.querySelectorAll('[data-tcp-vis]').forEach(x=>x.onchange=()=>{});
  let drag=null;o.querySelectorAll('[data-tcp-sort]').forEach(el=>{el.ondragstart=()=>drag=el.dataset.tcpSort;el.ondragover=e=>e.preventDefault();el.ondrop=e=>{e.preventDefault();if(!drag||drag===el.dataset.tcpSort)return;const all=[...o.querySelectorAll('[data-tcp-sort]')],i1=all.findIndex(x=>x.dataset.tcpSort===drag),i2=all.findIndex(x=>x===el);if(i1!==-1&&i2!==-1){[all[i1],all[i2]]=[all[i2],all[i1]];all.forEach(x=>o.appendChild(x));}};});
 }
 function setMode(exists){profileExists=exists;document.getElementById('tcp-profile-mode').textContent=exists?'Επεξεργασία Profile':'Δημιουργία Profile';document.getElementById('tcp-profile-exists').hidden=!exists;}
 function apply(p){current=p;Object.keys(p).forEach(k=>{const e=f.elements[k];if(e&&e.type!=='file')e.value=p[k]??''});controls(p);const prevBtn=document.getElementById('tcp-preview-btn');if(prevBtn)prevBtn.disabled=!p.profile_url;}
 api('/me').then(r=>r.json()).then(d=>{if(d.profile){apply(d.profile);setMode(true)}else{controls({});setMode(false)}}).catch(()=>setMode(false));
 f.addEventListener('click',e=>{const b=e.target.closest('.tcp-pwa-media,.tcp-media-library-box [data-media-target]');if(b){e.preventDefault();mediaPicker(b.dataset.iconTarget||b.dataset.mediaTarget);}});
 document.getElementById('tcp-preview-btn')?.addEventListener('click',function(){if(this.disabled||!current?.profile_url)return;window.open(current.profile_url,'_blank','noopener');});
f.addEventListener('submit',async e=>{e.preventDefault();const btn=f.querySelector('button[type=submit]');btn.disabled=true;const d={};[...f.elements].forEach(x=>{if(x.name&&x.type!=='file')d[x.name]=x.value||''});
  d.visibility={};fields.forEach(k=>{const x=document.querySelector('[data-tcp-vis="'+k+'"]');d.visibility[k]=x?x.checked:true});d.field_order=[...document.querySelectorAll('#tcp-field-order [data-tcp-sort]')].map(x=>x.dataset.tcpSort);
  try{for(const input of f.querySelectorAll('input[type=file][data-target]'))if(input.files[0]){const rr=await fetch(root.apiRoot+'/me/media',{method:'POST',headers:{'X-WP-Nonce':root.nonce||''},body:(()=>{const f=new FormData();f.append('file',input.files[0]);return f;})(),credentials:'same-origin'}),dd=await rr.json();if(!rr.ok||dd.code)throw Error(dd.message||'Σφάλμα ανεβάσματος εικόνας');d[input.dataset.target]=dd.url||'';}
   const r=await api('/me',{method:'POST',body:JSON.stringify(d)}),p=await r.json();if(!r.ok||p.code)throw Error(p.message||'Δεν ήταν δυνατή η αποθήκευση.');apply(p);setMode(true);document.getElementById('tcp-user-result').textContent='Αποθηκεύθηκε!';
   setTimeout(()=>document.getElementById('tcp-user-result').textContent='',3000);
  }
  catch(err){document.getElementById('tcp-user-result').textContent=err.message||'Δεν ήταν δυνατή η αποθήκευση.'}finally{btn.disabled=false}
 });
 f.querySelectorAll('input[type=url]').forEach(x=>x.addEventListener('input',()=>x.dataset.manual='1'));
}
function setupInstall(){
 const card=document.getElementById('tcp-install-card'),open=document.getElementById('tcp-install-app'),now=document.getElementById('tcp-install-now'),close=document.getElementById('tcp-install-close'),fallback=document.getElementById('tcp-install-fallback');
 let deferred=window.__tcpDeferredInstallPrompt||null;
 const dismissedKey='tcp_settings_install_dismissed_v159';
 const isStandalone=()=>window.matchMedia('(display-mode: standalone)').matches||window.matchMedia('(display-mode: fullscreen)').matches||window.navigator.standalone===true||document.referrer.includes('android-app://');
 const dismissed=()=>{try{return localStorage.getItem(dismissedKey)==='1'}catch(e){return false}};
 const remember=()=>{try{localStorage.setItem(dismissedKey,'1')}catch(e){}};
 const show=()=>{if(isStandalone()){card.hidden=true;return;}card.hidden=false;card.style.display='grid';card.setAttribute('aria-hidden','false');if(fallback)fallback.hidden=false;};
 const hide=()=>{card.hidden=true;card.style.display='none';card.setAttribute('aria-hidden','true');document.body.style.overflow='';};
 const install=async()=>{if(isStandalone()){hide();return;}const p=deferred||window.__tcpDeferredInstallPrompt;if(!p||typeof p.prompt!=='function'){show();return;}deferred=null;window.__tcpDeferredInstallPrompt=null;p.prompt();const r=await p.userChoice;if(r.outcome==='accepted'){remember();hide();}};
 if(!isStandalone()&&!dismissed())show();
 window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferred=e;window.__tcpDeferredInstallPrompt=e;show();});
 window.addEventListener('appinstalled',()=>{deferred=null;window.__tcpDeferredInstallPrompt=null;remember();hide();});
 open?.addEventListener('click',install);now?.addEventListener('click',install);close?.addEventListener('click',e=>{e.preventDefault();remember();hide();});
}
document.getElementById('tcp-settings-exit')?.addEventListener('click',function(){fetch((window.TapCardPro?.apiRoot||'')+'/me',{method:'DELETE',credentials:'same-origin',headers:{'X-WP-Nonce':window.TapCardPro?.nonce||''}});try{window.close();}catch(_){}});
function registerSW(){if(!('serviceWorker' in navigator))return;window.addEventListener('load',()=>{const base=new URL('settings-pwa/',location.href.replace(/settings-pwa\/index\.php.*$|tapcard-settings.*$/,''));navigator.serviceWorker.register(base.href+'../../sw.js',{scope:base.href.replace(/settings-pwa\/$/,'')}).catch(err=>console.log('SW registration failed:',err));});}
document.addEventListener('DOMContentLoaded',()=>{customer();user();setupInstall();registerSW()});
})();

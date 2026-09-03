/* TapCard Pro v1.7.0 — Created by Apostolis Karamichalis. Installation flow mirrors Brunch Customer PWA. */
(function(){'use strict';
var card=document.getElementById('tcp-install-card');
var installButton=document.getElementById('tcp-install-now');
var closeButton=document.getElementById('tcp-install-close');
var fallback=document.getElementById('tcp-install-fallback');
if(!card||!installButton||!closeButton)return;
var ua=navigator.userAgent||'';
var ios=/iphone|ipad|ipod/i.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
var safari=/safari/i.test(ua)&&!/crios|fxios|edgios|opios|gsa|instagram|fban|fbav/i.test(ua);
var standalone=function(){return window.matchMedia('(display-mode: standalone)').matches||window.matchMedia('(display-mode: fullscreen)').matches||navigator.standalone===true||document.referrer.indexOf('android-app://')===0;};
var onboarding=new URLSearchParams(location.search).get('onboarding')==='1';
var dismissedKey='tcp_settings_install_dismissed_v158';
var promptEvent=window.__tcpDeferredInstallPrompt||null;
function isDismissed(){try{return localStorage.getItem(dismissedKey)==='1';}catch(e){return false;}}
function close(){card.hidden=true;card.style.display='none';document.body.style.overflow='';card.setAttribute('aria-hidden','true');}
function showFallback(){
 if(ios){installButton.textContent='Δες οδηγίες εγκατάστασης';}
 else {if(fallback){fallback.hidden=false;fallback.style.display='block';}installButton.textContent='Εγκατάσταση εφαρμογής';}
}
function show(){if(standalone()){close();return;}card.hidden=false;card.style.display='grid';card.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';showFallback();}
function rememberLater(){try{localStorage.setItem(dismissedKey,'1');}catch(e){}close();}
if(!standalone() && (onboarding || !isDismissed())) show();
window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();promptEvent=e;window.__tcpDeferredInstallPrompt=e;show();});
window.addEventListener('appinstalled',function(){promptEvent=null;window.__tcpDeferredInstallPrompt=null;try{localStorage.setItem(dismissedKey,'1');}catch(e){}close();});
installButton.addEventListener('click',function(){
 if(ios){showFallback();return;}
 showFallback();
 var p=promptEvent||window.__tcpDeferredInstallPrompt||null;
 if(!p||typeof p.prompt!=='function')return;
 promptEvent=null;window.__tcpDeferredInstallPrompt=null;
 try{
  var result=p.prompt();
  if(result&&typeof result.then==='function')result.catch(function(){showFallback();});
  if(p.userChoice&&typeof p.userChoice.then==='function')p.userChoice.then(function(choice){
   if(choice&&choice.outcome==='accepted'){try{localStorage.setItem(dismissedKey,'1');}catch(e){}close();}
   else showFallback();
  }).catch(function(){showFallback();});
 }catch(e){showFallback();}
});
closeButton.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();rememberLater();});
})();
/* CLÉOPÂTRE — Auth & global state */
(function(){
  "use strict";
  const C = window.CLEO;
  const API = window.CLEO_API;

  // Attendre CLEO ready puis API me
  function initAuth(){
    const applyHeader = (user)=>{
      // header account icon -> montrer menu + mettre à jour le lien direct (mobile navigue, desktop dropdown)
      const btn = document.querySelector("[data-account]");
      if (!btn) return;
      // Met à jour le href direct selon l'état d'authentification (exigence: click -> login si logged out, -> account si logged in)
      // Utilise le même système d'auth existant (API.isAuthenticated / C.ROOT)
      (function updateAccountHref(){
        // btn peut être <a> (nouveau) ou <button> (legacy) - on gère les deux
        const isAnchor = btn.tagName === 'A';
        if (isAnchor){
          if (user){
            btn.setAttribute('href', C.ROOT + 'pages/account.html');
            btn.setAttribute('aria-label', 'Mon compte');
          } else {
            const redirect = encodeURIComponent(location.pathname + location.search);
            btn.setAttribute('href', C.ROOT + 'pages/login.html?redirect=' + redirect);
            btn.setAttribute('aria-label', 'Connexion');
          }
        } else {
          // fallback button: on garde data-href pour cohérence mais navigateur gère via JS
          btn.setAttribute('aria-label', user ? 'Mon compte' : 'Connexion');
        }
      })();
      // créer dropdown si non existant (desktop enrichissement, mobile garde navigation directe)
      let menu = document.querySelector("[data-account-menu]");
      if (!menu){
        menu = document.createElement("div");
        menu.setAttribute("data-account-menu","");
        menu.className = "account-menu";
        menu.style.cssText = "position:absolute;top:calc(100% + 8px);right:0;background:var(--surface);border:1px solid var(--line);border-radius:4px;box-shadow:0 12px 40px -12px rgba(0,0,0,.25);min-width:220px;padding:8px 0;z-index:130;display:none;";
        btn.style.position="relative";
        btn.parentElement.style.position="relative";
        btn.parentElement.appendChild(menu);
        // toggle: sur mobile on laisse la navigation directe (spec: click -> login/account), sur desktop on toggle le menu
        btn.addEventListener("click",(e)=>{
          const isMobile = window.matchMedia('(max-width: 768px)').matches || window.innerWidth <= 768;
          if (isMobile){
            // Sur mobile: laisser le <a> naviguer directement selon href mis à jour ci-dessus
            // Ne pas preventDefault => navigation directe vers login ou account
            // Fermer le menu si ouvert par hasard
            menu.style.display="none";
            return;
          }
          // Desktop: comportement dropdown existant (ne pas naviguer immédiatement, afficher menu)
          e.preventDefault();
          e.stopPropagation();
          const isOpen = menu.style.display !== "none";
          closeAll();
          if(!isOpen) menu.style.display="block";
        });
        document.addEventListener("click", closeAll);
        function closeAll(){ menu.style.display="none"; }
        // style items
        const style = document.createElement("style");
        style.textContent = `.account-menu a,.account-menu button{display:flex;align-items:center;width:100%;padding:10px 16px;font-size:.86rem;color:var(--ink);text-align:left;transition:background .2s}.account-menu a:hover,.account-menu button:hover{background:var(--bg)}.account-menu hr{border:0;height:1px;background:var(--line);margin:6px 0}.account-menu .am-head{padding:10px 16px 6px;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}}`;
        document.head.appendChild(style);
      } else {
        // si menu existe déjà, s'assurer que le click handler mobile/desktop est à jour (évite double binding)
        // on met à jour le href déjà fait ci-dessus, et on s'assure que le menu se ferme sur navigation mobile
        if (window.matchMedia('(max-width: 768px)').matches){
          menu.style.display="none";
        }
      }
      if (user){
        const isAdmin = ["admin","super_admin"].includes(user.role);
        const adminLabel = "Admin";
        menu.innerHTML = `
          <div class="am-head">Bonjour, ${C.esc(user.first_name)} · ${isAdmin?adminLabel:"Cliente"}</div>
          <a href="${C.ROOT}pages/account.html">Mon compte</a>
          <a href="${C.ROOT}pages/account.html#commandes">Mes commandes</a>
          <a href="${C.ROOT}pages/account.html#adresses">Mes adresses</a>
          <a href="${C.ROOT}pages/wishlist.html">Ma liste d’envies</a>
          ${isAdmin?`<hr><a href="${C.ROOT}admin/index.html" style="color:var(--gold);font-weight:500">→ Administration</a>`:""}
          <hr><button data-logout>Déconnexion</button>
        `;
        // Clic direct sur avatar admin -> panel (facilite la demande utilisateur)
        const btn = document.querySelector("[data-account]");
        if(isAdmin && btn && !btn.dataset.adminBound){
          btn.dataset.adminBound="1";
          btn.addEventListener("dblclick", ()=> location.href=C.ROOT+"admin/index.html");
        }
        const lo = menu.querySelector("[data-logout]");
        if(lo) lo.addEventListener("click", async ()=>{
          try{ await API.logout(); }catch(e){ console.warn("[auth] logout failed", e); }
          location.replace(C.ROOT + "index.html");
        });
      } else {
        const redirect = encodeURIComponent(location.pathname + location.search);
        menu.innerHTML = `
          <a href="${C.ROOT}pages/login.html?redirect=${redirect}">Connexion</a>
          <a href="${C.ROOT}pages/register.html?redirect=${redirect}">Créer un compte</a>
          <hr><a href="${C.ROOT}pages/wishlist.html">Favoris (invité)</a>
        `;
      }
    };

     // wishlist & cart merge on login — avec gestion d'erreur visible
    let lastUser = null;
    API.onAuthChange(async (user)=>{
      applyHeader(user);
      if(user && !lastUser){
        try{
          const guestWish = JSON.parse(localStorage.getItem("cleo_wishlist_v1")||"[]");
          if(guestWish.length){
            const ids = guestWish.map(v=> typeof v==="string"?v:v.id);
            await API.wishlist.sync(ids);
          }
        }catch(e){ console.warn("[auth] wishlist sync failed", e); }
      }
      lastUser = user;
      // expose globally
      window.CLEO_USER = user;
      document.dispatchEvent(new CustomEvent("cleo:auth",{detail:user}));
    });

    // initial apply if already fetched
    if(API.user) applyHeader(API.user);
    else {
      // poll until me resolved
      let tries=0;
      const iv=setInterval(()=>{
        if(API.user || tries>20){ clearInterval(iv); applyHeader(API.user); }
        tries++;
      },150);
      // also listen
      API.onAuthChange(applyHeader);
    }

    // wishlist delegate: si authentifié, utiliser API sinon local
    const origToggle = C.wish.toggle;
    // On intercepte clicks data-wish pour appeler API quand connecté
    document.addEventListener("click", async (e)=>{
      const btn = e.target.closest("[data-wish]");
      if(!btn) return;
      const pid = btn.dataset.wish;
      if(API.isAuthenticated()){
        // On NE stoppe PAS la propagation : global.js (phase bulle) met à jour le
        // store local, l'état du cœur et le badge de manière optimiste. On se
        // contente de synchroniser le serveur ici — sinon l'utilisateur connecté
        // ne voyait aucun retour visuel (cœur + badge figés jusqu'au reload).
        try{
          await API.wishlist.toggle(pid);
        }catch(err){
          // session probablement expirée en cours de clic — global.js a déjà
          // basculé localement ; la vérité est reconciliée au prochain me().
          console.warn("wishlist api",err);
        }
      }
    }, true);

    // global logout helper — utilise replace pour ne pas polluer l'historique
    window.CLEO_AUTH = {
      requireAuth: (redirectPath)=>{
        if(!API.isAuthenticated()){
          const r = redirectPath || (location.pathname+location.search);
          location.replace(C.ROOT + "pages/login.html?redirect=" + encodeURIComponent(r));
          return false;
        }
        return true;
      },
      getUser: ()=> API.user,
      isAdmin: ()=> API.isAdmin(),
      logout: async ()=>{
        try{ await API.logout(); }catch(e){ console.warn("[auth] logout", e); }
        location.replace(C.ROOT + "index.html");
      }
    };
    // Expose session-expired handler commun
    document.addEventListener("cleo:session-expired", ()=>{
      const banner=document.createElement("div");
      banner.style.cssText="position:fixed;top:0;left:0;right:0;z-index:400;background:#8A3A1F;color:#fff;padding:10px 16px;text-align:center;font-size:.84rem";
      banner.textContent="Votre session a expiré — veuillez vous reconnecter.";
      banner.addEventListener("click", ()=> location.href=C.ROOT+"pages/login.html");
      document.body.prepend(banner);
      setTimeout(()=> banner.remove(), 6000);
    });
  }

  if(window.CLEO && window.CLEO.onReady) C.onReady(initAuth);
  else document.addEventListener("DOMContentLoaded", initAuth);
})();

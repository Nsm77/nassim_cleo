/* CLÉOPÂTRE — API client centralisé · base-path aware + error handling */
(function(){
  "use strict";
  // Base-path détection robuste: supporte /, /cleopatre/, et tout sous-dossier via <base> ou chemin détecté
  function detectRoot(){
    const baseEl=document.querySelector('base[href]');
    if(baseEl){
      const href=baseEl.getAttribute('href');
      if(href) return href.endsWith('/')? href : href+'/';
    }
    // Détecte le préfixe projet en cherchant /api/ ou /pages/ ou /admin/ dans pathname
    const p=location.pathname;
    // Si on est dans /pages/, /admin/, /super-admin/ remonter d'un niveau depuis la racine projet
    if(p.indexOf('/pages/')!==-1){ return p.substring(0, p.indexOf('/pages/')) + '/'; }
    if(p.indexOf('/super-admin/')!==-1){ return p.substring(0, p.indexOf('/super-admin/')) + '/'; }
    if(p.indexOf('/admin/')!==-1){ return p.substring(0, p.indexOf('/admin/')) + '/'; }
    if(p.indexOf('/api/')!==-1){ return p.substring(0, p.indexOf('/api/')) + '/'; }
    // fallback: dossier du fichier courant
    const dir=p.substring(0, p.lastIndexOf('/')+1);
    // si on est à la racine HTML (index.html ou /), dir est le root projet
    return dir;
  }
  const ROOT = detectRoot();
  // API_BASE doit être absolu par rapport au root détecté
  const API_BASE = ROOT + "api/";
  let csrfToken = null;
  let user = null;
  let listeners = [];

  async function request(path, opts={}){
    opts = Object.assign({method:"GET", headers:{}}, opts);
    const isGet = opts.method==="GET";
    if (!isGet && csrfToken) {
      opts.headers["X-CSRF-Token"] = csrfToken;
    }
    opts.headers["Accept"] = "application/json";
    if (opts.body && typeof opts.body === "object" && !(opts.body instanceof FormData)) {
      opts.headers["Content-Type"] = "application/json";
      opts.body = JSON.stringify(opts.body);
    }
    opts.credentials = "same-origin";
    let res;
    try {
      res = await fetch(API_BASE + path, opts);
    } catch(e){
      throw { message:"Impossible de contacter le serveur. Veuillez réessayer.", status:0, network:true };
    }
    const ct = res.headers.get("content-type") || "";
    let data = null;
    if (ct.includes("application/json")) {
      try { data = await res.json(); } catch(e){ data=null; }
    } else {
      const clone = res.clone();
      try { data = await res.json(); } catch(e){ try { data = await clone.text(); } catch(e2){ data=null; } }
    }
    if (!res.ok) {
      const msg = (data && data.message) ? data.message : ("Erreur "+res.status);
      const err = new Error(msg);
      err.status = res.status;
      err.data = data;
      err.errors = data && data.errors ? data.errors : null;
      // Gestion centralisée session expirée / droits
      if(res.status===401){
        user=null; emit();
        // Ne pas rediriger automatiquement ici pour éviter boucle, mais notifier
        document.dispatchEvent(new CustomEvent("cleo:session-expired",{detail:{path}}));
        const isAdminPage=location.pathname.indexOf("/admin/")!==-1;
        if(isAdminPage && path!=="auth/me.php" && path!=="auth/login.php"){
          // Afficher message plutôt que redirect brutal — laisser la page décider, mais log
          console.warn("[CLEO_API] 401 session expirée sur", path);
        }
      } else if(res.status===403){
        console.warn("[CLEO_API] 403 forbidden sur", path, msg);
      } else if(res.status===404){
        console.warn("[CLEO_API] 404 endpoint manquant:", API_BASE+path);
      }
      throw err;
    }
    // capture csrf
    if (data && data.data && data.data.csrf_token) csrfToken=data.data.csrf_token;
    if (data && data.csrf_token) csrfToken=data.csrf_token;
    return data;
  }

  const api = {
    get: (p)=> request(p,{method:"GET"}),
    post: (p,body)=> request(p,{method:"POST", body}),
    put: (p,body)=> request(p,{method:"PUT", body}),
    del: (p,body)=> request(p,{method:"DELETE", body}),
    // auth
    csrf: ()=> request("auth/csrf.php",{method:"GET"}).then(d=>{ if(d.data) csrfToken=d.data.csrf_token; return d; }),
    me: ()=> request("auth/me.php",{method:"GET"}).then(d=>{
      if(d.authenticated){ user=d.user; if(d.csrf_token) csrfToken=d.csrf_token; }
      else { user=null; if(d.csrf_token) csrfToken=d.csrf_token; }
      emit();
      return d;
    }),
    register: (payload)=> request("auth/register.php",{method:"POST", body:payload}),
    login: (payload)=> request("auth/login.php",{method:"POST", body:payload}),
    logout: ()=> request("auth/logout.php",{method:"POST", body:{}}),
    changePassword: (payload)=> request("auth/change-password.php",{method:"POST", body:payload}),
    forgotPassword: (payload)=> request("auth/forgot-password.php",{method:"POST", body:payload}),
    resetPassword: (payload)=> request("auth/reset-password.php",{method:"POST", body:payload}),
    // account
    profile: {
      get: ()=> request("account/profile.php",{method:"GET"}),
      update: (payload)=> request("account/profile.php",{method:"PUT", body:payload})
    },
    addresses: {
      list: ()=> request("account/addresses.php",{method:"GET"}),
      create: (payload)=> request("account/addresses.php",{method:"POST", body:payload}),
      get: (id)=> request(`account/address.php?id=${id}`,{method:"GET"}),
      update: (id,payload)=> request(`account/address.php?id=${id}`,{method:"PUT", body:Object.assign({id},payload)}),
      remove: (id)=> request(`account/address.php?id=${id}`,{method:"DELETE"})
    },
    wishlist: {
      list: ()=> request("wishlist/index.php",{method:"GET"}),
      toggle: (productId)=> request("wishlist/index.php",{method:"POST", body:{product_id:productId}}),
      add: (productId)=> request("wishlist/index.php",{method:"POST", body:{product_id:productId, action:"add"}}),
      remove: (productId)=> request("wishlist/index.php",{method:"POST", body:{product_id:productId, action:"remove"}}),
      sync: (ids)=> request("wishlist/index.php",{method:"POST", body:{action:"sync", product_ids:ids}})
    },
    loyalty: {
      balance: ()=> request("loyalty/balance.php",{method:"GET"}),
      history: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`loyalty/history.php?${qs}`,{method:"GET"}); },
      rewards: ()=> request("loyalty/rewards.php",{method:"GET"}),
      redeemPreview: (rewardId, subtotal)=> request("loyalty/redeem-preview.php",{method:"POST", body:{reward_id:rewardId, subtotal}}),
    },
    reviews: {
      list: (productId, params={})=>{ const qs=new URLSearchParams(Object.assign({product_id:productId},params)).toString(); return request(`reviews/list.php?${qs}`,{method:"GET"}); },
      create: (payload)=> request("reviews/create.php",{method:"POST", body:payload}),
      helpful: (id)=> request("reviews/helpful.php",{method:"POST", body:{review_id:id}})
    },
    support: {
      tickets: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`support/tickets.php?${qs}`,{method:"GET"}); },
      create: (payload)=> request("support/tickets.php",{method:"POST", body:payload}),
      messages: (ticketId)=> request(`support/messages.php?ticket_id=${ticketId}`,{method:"GET"}),
      reply: (ticketId, message)=> request("support/messages.php",{method:"POST", body:{ticket_id:ticketId, message}})
    },
    notifications: {
      list: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`notifications/list.php?${qs}`,{method:"GET"}); },
      markRead: (id)=> request("notifications/mark-read.php",{method:"POST", body:{id}}),
      preferences: ()=> request("notifications/preferences.php",{method:"GET"}),
      updatePreferences: (prefs)=> request("notifications/preferences.php",{method:"POST", body:prefs})
    },
    recentlyViewed: {
      add: (productId)=> request("recently-viewed/add.php",{method:"POST", body:{product_id:productId}}),
      list: ()=> request("recently-viewed/list.php",{method:"GET"})
    },
    cart: {
      validatePromo: (code, subtotal)=> request("promotions/validate.php",{method:"POST", body:{code, subtotal}})
    },
    checkout: {
      create: (payload)=> request("checkout/create.php",{method:"POST", body:payload})
    },
    orders: {
      list: (params={})=>{
        const qs=new URLSearchParams(params).toString();
        return request(`orders/list.php?${qs}`,{method:"GET"});
      },
      detail: (idOrNumber)=>{
        const isNum = String(idOrNumber).startsWith("CLEO-");
        const key = isNum ? "order_number" : "id";
        return request(`orders/detail.php?${key}=${encodeURIComponent(idOrNumber)}`,{method:"GET"});
      },
      cancel: (id)=> request("orders/cancel.php",{method:"POST", body:{order_id:id}})
    },
    products: {
      list: (params={})=>{
        const qs=new URLSearchParams(params).toString();
        return request(`products/list.php?${qs}`,{method:"GET"});
      },
      detail: (id)=> request(`products/detail.php?id=${encodeURIComponent(id)}`,{method:"GET"})
    },
    contact: {
      submit: (payload)=> request("contact/submit.php",{method:"POST", body:payload})
    },
    // admin
    admin: {
      dashboard: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/dashboard.php?${qs}`,{method:"GET"}); },
      search: (q)=>{ const qs=new URLSearchParams({q}).toString(); return request(`admin/search.php?${qs}`,{method:"GET"}); },
      orders: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/orders.php?${qs}`,{method:"GET"}); },
      orderStatus: (orderId,status,note)=> request("admin/orders.php",{method:"POST", body:{order_id:orderId,status,note}}),
      orderNotes: (orderId)=>{ const qs=new URLSearchParams({order_id:orderId}).toString(); return request(`admin/order-notes.php?${qs}`,{method:"GET"}); },
      addOrderNote: (orderId,note)=> request("admin/order-notes.php",{method:"POST", body:{order_id:orderId,note}}),
      customers: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/customers.php?${qs}`,{method:"GET"}); },
      customer: (id)=> request(`admin/customers.php?id=${id}`,{method:"GET"}),
      customerStatus: (id,action)=> request("admin/customers.php",{method:"POST", body:{id,action}}),
      customerNotes: (userId)=>{ const qs=new URLSearchParams({user_id:userId}).toString(); return request(`admin/customer-notes.php?${qs}`,{method:"GET"}); },
      addCustomerNote: (userId,note)=> request("admin/customer-notes.php",{method:"POST", body:{user_id:userId,note}}),
      customerTags: (userId)=>{ const qs=userId? new URLSearchParams({user_id:userId}).toString() : ""; return request(`admin/customer-tags.php?${qs}`,{method:"GET"}); },
      addCustomerTag: (userId,tag)=> request("admin/customer-tags.php",{method:"POST", body:{user_id:userId,tag}}),
      removeCustomerTag: (userId,tag)=> request(`admin/customer-tags.php?user_id=${userId}&tag=${encodeURIComponent(tag)}`,{method:"DELETE"}),
      products: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/products.php?${qs}`,{method:"GET"}); },
      productUpdate: (payload)=> request("admin/products.php",{method:"POST", body:payload}),
      productCreate: (payload)=> request("admin/products.php",{method:"POST", body:Object.assign({action:"create"},payload)}),
      productDuplicate: (id)=> request("admin/products.php",{method:"POST", body:{action:"duplicate",id}}),
      productDelete: (id)=> { const qs=new URLSearchParams({id}).toString(); return request(`admin/products.php?${qs}`,{method:"DELETE"}); },
      productBulk: (ids,bulk_action,extra={})=> request("admin/products.php",{method:"POST", body:Object.assign({action:"bulk",ids,bulk_action},extra)}),
      inventory: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/inventory.php?${qs}`,{method:"GET"}); },
      inventoryAdjust: (payload)=> request("admin/inventory.php",{method:"POST", body:payload}),
      inventoryHistory: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/inventory-history.php?${qs}`,{method:"GET"}); },
      categories: ()=> request("admin/categories.php",{method:"GET"}),
      categoryCreate: (payload)=> request("admin/categories.php",{method:"POST", body:payload}),
      brands: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/brands.php?${qs}`,{method:"GET"}); },
      brandCreate: (payload)=> request("admin/brands.php",{method:"POST", body:payload}),
      settings: ()=> request("admin/settings.php",{method:"GET"}),
      settingsUpdate: (payload)=> request("admin/settings.php",{method:"POST", body:payload}),
      activity: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/activity.php?${qs}`,{method:"GET"}); },
      promotions: ()=> request("admin/promotions.php",{method:"GET"}),
      promotionCreate: (p)=> request("admin/promotions.php",{method:"POST", body:p}),
      promotionsDelete: (id)=> request(`admin/promotions.php?id=${id}`,{method:"DELETE"}),
      analytics: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/analytics.php?${qs}`,{method:"GET"}); },
      collections: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/collections.php?${qs}`,{method:"GET"}); },
      collectionCreate: (payload)=> request("admin/collections.php",{method:"POST", body:payload}),
      collectionAddProduct: (cid,pid)=> request("admin/collections.php",{method:"POST", body:{action:"add_product",collection_id:cid,product_id:pid}}),
      merchandising: (enrich)=> request(`admin/merchandising.php${enrich?'?enrich=1':''}`,{method:"GET"}),
      merchandisingUpdate: (slot,ids)=> request("admin/merchandising.php",{method:"POST", body:{slot,product_ids:ids}}),
      notificationsCenter: ()=> request("admin/notifications-center.php",{method:"GET"}),
      adminUsers: ()=> request("admin/admin-users.php",{method:"GET"}),
      adminUserCreate: (payload)=> request("admin/admin-users.php",{method:"POST", body:Object.assign({action:"create"},payload)}),
      loyalty: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/loyalty.php?${qs}`,{method:"GET"}); },
      loyaltyDetail: (userId)=> request(`admin/loyalty.php?user_id=${userId}`,{method:"GET"}),
      loyaltyAdjust: (payload)=> request("admin/loyalty.php",{method:"POST", body:payload}),
      loyaltyCampaigns: ()=> request("admin/loyalty-campaigns.php",{method:"GET"}),
      loyaltyRewards: ()=> request("admin/loyalty-rewards.php",{method:"GET"}),
      flashSales: ()=> request("admin/flash-sales.php",{method:"GET"}),
      flashSaleCreate: (payload)=> request("admin/flash-sales.php",{method:"POST", body:payload}),
      reviews: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/reviews.php?${qs}`,{method:"GET"}); },
      reviewModerate: (id,status)=> request("admin/reviews.php",{method:"POST", body:{id,status}}),
      supportTickets: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/support.php?${qs}`,{method:"GET"}); },
      supportReply: (payload)=> request("admin/support.php",{method:"POST", body:payload}),
      contactMessages: (params={})=>{ const qs=new URLSearchParams(params).toString(); return request(`admin/contact.php?${qs}`,{method:"GET"}); },
      systemHealth: ()=> request("admin/system.php",{method:"GET"}),
      security: ()=> request("admin/security.php",{method:"GET"}),
      setup: (payload)=> request("system/setup.php",{method:"POST", body:payload})
    },
    health: ()=> request("system/health.php",{method:"GET"}),

    // state
    get csrfToken(){ return csrfToken; },
    get user(){ return user; },
    setUser: (u)=>{ user=u; emit(); },
    setCsrf: (t)=>{ csrfToken=t; },
    onAuthChange: (fn)=>{ listeners.push(fn); },
    isAuthenticated: ()=> !!user,
    isAdmin: ()=> !!(user && ["admin","super_admin"].includes(user.role))
  };

  function emit(){ listeners.forEach(fn=>{ try{fn(user);}catch(e){ console.warn("[CLEO_API] listener error", e); } }); }

  // init: fetch csrf & me avec diagnostics
  (async ()=>{
    const MAX_RETRIES=1;
    for(let attempt=0; attempt<=MAX_RETRIES; attempt++){
      try{
        const r = await api.csrf();
        break;
      } catch(e){
        console.warn("[CLEO_API] csrf fetch failed (attempt "+attempt+")", e);
        if(attempt===MAX_RETRIES) document.dispatchEvent(new CustomEvent("cleo:api-error",{detail:{type:"csrf", message:e.message}}));
      }
    }
    try{
      await api.me();
    } catch(e){
      console.warn("[CLEO_API] me() failed", e);
      document.dispatchEvent(new CustomEvent("cleo:api-error",{detail:{type:"me", message:e.message, status:e.status}}));
    }
  })();

  // Expose helpers pour pages qui veulent gérer erreurs globales
  window.addEventListener("cleo:session-expired", (e)=>{
    const isAdmin=location.pathname.indexOf("/admin/")!==-1;
    if(isAdmin && !location.pathname.endsWith("login.html")){
      // Afficher bannière si existe, sinon rediriger après délai
      const banner=document.getElementById("sessionBanner");
      if(banner){ banner.textContent="Votre session a expiré — veuillez vous reconnecter."; banner.style.display="block"; }
    }
  });

  window.CLEO_API = api;
  // compat: expose as CLEO.api
  if(window.CLEO) window.CLEO.api = api;
  else window.CLEO = { api };
})();

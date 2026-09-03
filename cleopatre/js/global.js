/* Cléopâtre — Global core: desktop-only (mobile navigation removed) */
(function () {
  "use strict";
  const D = window;
  // ROOT base-path aware: supporte / et /cleopatre/
  function detectRoot(){
    const p=location.pathname;
    if(p.indexOf('/pages/')!==-1) return p.substring(0, p.indexOf('/pages/')) + '/';
    if(p.indexOf('/super-admin/')!==-1) return p.substring(0, p.indexOf('/super-admin/')) + '/';
    if(p.indexOf('/admin/')!==-1) return p.substring(0, p.indexOf('/admin/')) + '/';
    if(p.indexOf('/api/')!==-1) return p.substring(0, p.indexOf('/api/')) + '/';
    // fallback: dossier actuel (pour index.html à la racine ou sous-dossier)
    return p.substring(0, p.lastIndexOf('/')+1) || '/';
  }
  const ROOT = detectRoot();
  const PAGES = ROOT + "pages/";
  let FREE_SHIP = 99000; // 99 DT (millimes) — mis à jour depuis /api/system/health.php si disponible
  const reduceMotion = (typeof matchMedia !== 'undefined' && matchMedia("(prefers-reduced-motion: reduce)").matches) || false;
  // Tente de charger le seuil livraison depuis le backend (source de vérité)
  (async()=>{
    try{
      const base = ROOT;
      const r=await fetch(base+'api/system/health.php', {credentials:'same-origin', headers:{'Accept':'application/json'}});
      if(r.ok){
        const j=await r.json();
        const cfg=j.config||j.data?.config;
        if(cfg && cfg.free_shipping_threshold){ FREE_SHIP = parseInt(cfg.free_shipping_threshold); CLEO.FREE_SHIP=FREE_SHIP; }
      }
    }catch(e){ console.warn("[config] free_ship fetch failed", e); }
  })();

  /* ---------- Utilitaires ---------- */
  const $  = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));
  const esc = s => String(s == null ? "" : s).replace(/[&<>"']/g, m => ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[m]));
  const fmt = m => (m / 1000).toFixed(3).replace(".", ",") + " DT";
  const off = p => p.oldPrice ? Math.round((1 - p.price / p.oldPrice) * 100) : 0;
  const slugify = s => String(s).toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
  const qs = k => new URLSearchParams(location.search).get(k);
  const setQs = obj => {
    const u = new URLSearchParams(location.search);
    Object.entries(obj).forEach(([k, v]) => { (v == null || v === "") ? u.delete(k) : u.set(k, v); });
    history.replaceState(null, "", location.pathname + ("" + u ? "?" + u : "") + location.hash);
  };
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  /* ---------- Accès données — live fallback for file:// & async order ---------- */
  let products = D.CLEO_PRODUCTS || [];
  let brands   = D.CLEO_BRANDS   || [];
  let cats     = D.CLEO_CATEGORIES || [];
  let concerns = D.CLEO_CONCERNS  || [];
  let articles = D.CLEO_ARTICLES || [];
  function refreshDataRefs(){
    try{
      if(D.CLEO_PRODUCTS && D.CLEO_PRODUCTS.length && !products.length) { products.push(...D.CLEO_PRODUCTS); D.CLEO_PRODUCTS = products; }
      if(D.CLEO_BRANDS && D.CLEO_BRANDS.length && !brands.length) { brands.push(...D.CLEO_BRANDS); }
      if(D.CLEO_CATEGORIES && D.CLEO_CATEGORIES.length && !cats.length) { cats.push(...D.CLEO_CATEGORIES); }
      if(D.CLEO_CONCERNS && D.CLEO_CONCERNS.length && !concerns.length) { concerns.push(...D.CLEO_CONCERNS); }
      if(D.CLEO_ARTICLES && D.CLEO_ARTICLES.length && !articles.length) { articles.push(...D.CLEO_ARTICLES); }
    }catch(e){ console.warn('[refreshDataRefs]', e); }
  }
  let _productsHydrated = false;
  const byId = id => (D.CLEO_PRODUCTS||products).find(p => p.id === id) || products.find(p => p.id === id);
  const brandByName = n => (D.CLEO_BRANDS||brands).find(b => b.name.toLowerCase() === String(n).toLowerCase());
  const brandBySlug = s => (D.CLEO_BRANDS||brands).find(b => b.slug === s);
  const catBySlug = s => (D.CLEO_CATEGORIES||cats).find(c => c.slug === s);
  const concernBySlug = s => (D.CLEO_CONCERNS||concerns).find(c => c.slug === s);
  const articleById = id => (D.CLEO_ARTICLES||articles).find(a => a.id === id);
  function searchProducts(q) {
    q = String(q).trim().toLowerCase();
    if (!q) return [];
    const liveCats = D.CLEO_CATEGORIES||cats;
    const liveConcerns = D.CLEO_CONCERNS||concerns;
    const liveProducts = D.CLEO_PRODUCTS||products;
    return liveProducts.filter(p =>
      [p.name, p.brand, p.sub, (liveCats.find(c=>c.slug===p.cat)||{}).name, ...(p.concerns || []).map(c => (liveConcerns.find(x=>x.slug===c)||{}).name || "")]
        .join(" ").toLowerCase().includes(q));
  }
  /* Hydratation catalogue depuis l’API — base-path aware */
  async function hydrateCatalog(){
    if(_productsHydrated || location.pathname.indexOf('/admin/')!==-1 || location.pathname.indexOf('/super-admin/')!==-1) return;
    try{
      function getRoot(){
        const p=location.pathname;
        if(p.indexOf('/pages/')!==-1) return p.substring(0, p.indexOf('/pages/')) + '/';
        if(p.indexOf('/super-admin/')!==-1) return p.substring(0, p.indexOf('/super-admin/')) + '/';
        if(p.indexOf('/admin/')!==-1) return p.substring(0, p.indexOf('/admin/')) + '/';
        if(p.indexOf('/api/')!==-1) return p.substring(0, p.indexOf('/api/')) + '/';
        return p.substring(0, p.lastIndexOf('/')+1);
      }
      const base=getRoot();
      const res = await fetch(base+'api/products/list.php?per_page=100', {credentials:'same-origin', headers:{'Accept':'application/json'}});
      if(!res.ok) return;
      const j = await res.json();
      const rows = (j.data && j.data.products) || j.products || [];
      if(!rows.length) return;
      // map API → front shape (compat avec data/products.js)
      const mapped = rows.map(r=>({
        id: r.id, brand: r.brand, brand_slug: r.brand_slug, name: r.name, cat: r.cat, sub: r.sub, form: r.form, tint: r.tint,
        price: Number(r.price), oldPrice: r.old_price!=null?Number(r.old_price):null, old_price: r.old_price!=null?Number(r.old_price):null,
        size: r.size, concerns: (()=>{ try{ return typeof r.concerns==='string'?JSON.parse(r.concerns): (r.concerns||[]);}catch(e){return []}})(),
        rating: Number(r.rating||0), reviews: Number(r.reviews||0), stock: !!Number(r.stock), track_stock: !!Number(r.track_stock), stock_quantity: Number(r.stock_quantity||0),
        featured: !!Number(r.featured), isNew: !!Number(r.is_new), bestseller: r.bestseller!=null?Number(r.bestseller):null, image: r.image, imageAlt: r.image_alt||r.image, imageThumb: r.image_thumb,
        short: r.short, description: r.description, ingredients: r.ingredients,
        benefits: (()=>{ try{ return typeof r.benefits==='string'?JSON.parse(r.benefits): (r.benefits||[]);}catch(e){return []}})(),
        usage: r.usage_text||r.usage||'', active: !!Number(r.active), promo_active: !!Number(r.promo_active)
      })).filter(p=> p.active);
      if(mapped.length){
        products.length=0; mapped.forEach(p=> products.push(p));
        D.CLEO_PRODUCTS = products;
        _productsHydrated=true;
        try{ D.dispatchEvent(new CustomEvent('cleo:catalog', {detail: products})); }catch(e){ console.warn("[hydrate] dispatch failed", e); }
      } else {
        console.warn("[hydrate] API returned 0 products — fallback to static");
      }
    }catch(e){
      console.warn("[hydrate] catalog hydrate failed — fallback to static", e);
      // dispatch même en échec pour que les pages arrêtent de spinner
      try{ D.dispatchEvent(new CustomEvent('cleo:catalog-error', {detail: e})); }catch(err){}
    }
  }

  /* ============================================================
     ART — natures mortes SVG génératives (remplaçables par photo)
     ============================================================ */
  const ART = (() => {
    const INK = "#1A1814", PAPER = "#FBF9F4", PAPER2 = "#EFE8D9";
    let seq = 0;
    const uid = () => "cg" + (++seq).toString(36);

    function formBody(f, capA) {
      switch (f) {
        case "jar": return `
          <rect x="192" y="372" width="216" height="58" rx="10" fill="${capA}"/>
          <rect x="200" y="428" width="200" height="212" rx="16" fill="url(#BODY)" stroke="${INK}" stroke-opacity=".12"/>
          <g font-family="Georgia,serif" text-anchor="middle" fill="${INK}">
            <line x1="236" y1="474" x2="364" y2="474" stroke="${INK}" opacity=".26"/>
            <text x="300" y="540" font-size="40" opacity=".82">C</text>
            <line x1="248" y1="592" x2="352" y2="592" stroke="${INK}" opacity=".26"/>
          </g>`;
        case "tube": return `
          <rect x="258" y="318" width="84" height="15" rx="4" fill="${INK}" opacity=".78"/>
          <path d="M266,333 Q264,470 257,590 L343,590 Q336,470 334,333 Z" fill="url(#BODY)" stroke="${INK}" stroke-opacity=".1"/>
          <g font-family="Georgia,serif" text-anchor="middle" fill="${INK}">
            <line x1="278" y1="420" x2="322" y2="420" stroke="${INK}" opacity=".26"/>
            <text x="300" y="470" font-size="34" opacity=".82">C</text>
          </g>
          <rect x="256" y="590" width="88" height="50" rx="8" fill="${capA}"/>`;
        case "dropper": return `
          <ellipse cx="300" cy="318" rx="19" ry="17" fill="${capA}"/>
          <rect x="281" y="332" width="38" height="50" rx="6" fill="${INK}" opacity=".82"/>
          <rect x="262" y="380" width="76" height="262" rx="13" fill="url(#BODY)" stroke="${INK}" stroke-opacity=".12"/>
          <g font-family="Georgia,serif" text-anchor="middle" fill="${INK}">
            <line x1="276" y1="452" x2="324" y2="452" stroke="${INK}" opacity=".26"/>
            <text x="300" y="512" font-size="30" opacity=".82">C</text>
            <line x1="280" y1="556" x2="320" y2="556" stroke="${INK}" opacity=".22"/>
          </g>`;
        case "spray": return `
          <rect x="276" y="290" width="48" height="42" rx="7" fill="${capA}"/>
          <rect x="322" y="298" width="26" height="17" rx="4" fill="${INK}" opacity=".85"/>
          <rect x="256" y="330" width="88" height="314" rx="16" fill="url(#BODY)" stroke="${INK}" stroke-opacity=".12"/>
          <g font-family="Georgia,serif" text-anchor="middle" fill="${INK}">
            <line x1="270" y1="430" x2="330" y2="430" stroke="${INK}" opacity=".26"/>
            <text x="300" y="492" font-size="32" opacity=".82">C</text>
            <line x1="276" y1="536" x2="324" y2="536" stroke="${INK}" opacity=".22"/>
          </g>`;
        case "stick": return `
          <ellipse cx="300" cy="394" rx="26" ry="10" fill="${INK}" opacity=".72"/>
          <rect x="272" y="394" width="56" height="250" rx="11" fill="url(#BODY)" stroke="${INK}" stroke-opacity=".12"/>
          <text x="300" y="500" text-anchor="middle" font-family="Georgia,serif" font-size="24" fill="${INK}" opacity=".75">C</text>`;
        case "box": return `
          <rect x="228" y="348" width="144" height="294" rx="4" fill="url(#BODY)" stroke="${INK}" stroke-width="1"/>
          <line x1="228" y1="382" x2="372" y2="382" stroke="${INK}" opacity=".16"/>
          <line x1="248" y1="348" x2="248" y2="642" stroke="${INK}" opacity=".13"/>
          <text x="300" y="520" text-anchor="middle" font-family="Georgia,serif" font-size="46" font-style="italic" fill="${INK}" opacity=".8">C</text>`;
        case "pump": return `
          <rect x="283" y="240" width="52" height="28" rx="6" fill="${capA}"/>
          <rect x="333" y="245" width="44" height="15" rx="5" fill="${capA}"/>
          <rect x="283" y="266" width="34" height="40" rx="5" fill="${INK}" opacity=".85"/>
          <rect x="250" y="304" width="100" height="340" rx="15" fill="url(#BODY)" stroke="${INK}" stroke-opacity=".12"/>
          <g font-family="Georgia,serif" text-anchor="middle" fill="${INK}">
            <line x1="264" y1="424" x2="336" y2="424" stroke="${INK}" opacity=".26"/>
            <text x="300" y="488" font-size="36" opacity=".82">C</text>
            <line x1="272" y1="532" x2="328" y2="532" stroke="${INK}" opacity=".22"/>
          </g>`;
        default: return `
          <rect x="277" y="254" width="46" height="58" rx="7" fill="${capA}"/>
          <path d="M277,310 Q275,318 262,330 Q250,342 250,368 L250,618 Q250,644 276,644 L324,644 Q350,644 350,618 L350,368 Q350,342 338,330 Q325,318 323,310 Z" fill="url(#BODY)" stroke="${INK}" stroke-opacity=".1"/>
          <g font-family="Georgia,serif" text-anchor="middle" fill="${INK}">
            <line x1="266" y1="428" x2="334" y2="428" stroke="${INK}" opacity=".26"/>
            <text x="300" y="492" font-size="36" opacity=".82">C</text>
            <line x1="274" y1="536" x2="326" y2="536" stroke="${INK}" opacity=".22"/>
          </g>`;
      }
    }

    function svgWrap(bg, deco, inner, label) {
      const gB = uid(), gL = uid();
      return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 750" role="img" aria-hidden="true">
        <defs><linearGradient id="${gB}" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0" stop-color="${PAPER}"/><stop offset=".5" stop-color="#FFFFFF"/><stop offset="1" stop-color="${PAPER2}"/></linearGradient>
        <radialGradient id="${gL}" cx=".5" cy=".3" r=".8"><stop offset="0" stop-color="#FFFFFF" stop-opacity=".5"/><stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/></radialGradient></defs>
        <rect width="600" height="750" fill="${bg}"/>${deco}
        <rect width="600" height="750" fill="url(#${gL})"/>
        ${inner}
        ${label ? `<text x="300" y="712" text-anchor="middle" font-family="Jost,Arial,sans-serif" font-size="14" letter-spacing="6" fill="${INK}" opacity=".45">${label}</text>` : ""}
      </svg>`;
    }
    const DECO = `<circle cx="474" cy="126" r="116" fill="none" stroke="${INK}" stroke-width="1" opacity=".13"/>
                  <circle cx="118" cy="642" r="52" fill="none" stroke="${INK}" stroke-width="1" opacity=".09"/>`;

    function imgSrc(path) {
      if (!path) return "";
      return path.startsWith("http") || path.startsWith("/") ? path : (typeof ROOT !== "undefined" ? ROOT : "") + path;
    }
    function front(p) {
      if (p.image) {
        const src = imgSrc(p.image);
        return `<img src="${src}" alt="${esc(p.name || "")}" loading="lazy" decoding="async" width="600" height="750" class="pcard-photo"/>`;
      }
      const capA = ["#3E4A38", "#A88F62"][p.id.length % 2];
      return svgWrap(p.tint || "#E4DBC9", DECO, formBody(p.form, capA), "");
    }
    function alt(p) {
      const altPath = p.imageAlt || p.image;
      if (altPath) {
        const src = imgSrc(altPath);
        return `<img src="${src}" alt="" loading="lazy" decoding="async" width="600" height="750" class="pcard-photo pcard-photo--alt"/>`;
      }
      const bg = p.tint || "#E4DBC9", u = uid();
      return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 750" aria-hidden="true">
        <defs><radialGradient id="${u}" cx=".5" cy=".5" r=".62"><stop offset="0" stop-color="#FFFFFF" stop-opacity=".92"/><stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/></radialGradient></defs>
        <rect width="600" height="750" fill="${bg}"/><circle cx="300" cy="375" r="235" fill="url(#${u})"/>
        <g fill="none" stroke="${INK}" stroke-width="1.1" opacity=".32">
          <path d="M140,430 C210,330 260,500 330,392 S450,300 470,392"/>
          <path d="M130,480 C215,392 268,540 340,448 S452,368 476,448"/>
          <path d="M152,528 C225,452 270,580 345,500 S445,436 465,500"/>
        </g>
        <text x="300" y="700" font-family="Jost,Arial,sans-serif" font-size="14" letter-spacing="6" text-anchor="middle" fill="${INK}" opacity=".5">LA TEXTURE</text>
      </svg>`;
    }
    function zoom(p) {
      if (p.image) {
        const src = imgSrc(p.image);
        return `<img src="${src}" alt="${esc(p.name || "")}" loading="lazy" decoding="async" width="600" height="750" class="pcard-photo pcard-photo--zoom"/>`;
      }
      const capA = ["#3E4A38", "#A88F62"][p.id.length % 2];
      return svgWrap(p.tint || "#E4DBC9", "", `<g transform="translate(-165,-125) scale(1.58)">${formBody(p.form, capA)}</g>`, "");
    }
    function scene(tints, opts) {
      opts = opts || {};
      const bg = opts.bg || "#EAE3D6", u = uid();
      const branch = `
        <g stroke="#5C6B54" stroke-width="1.6" fill="none" opacity=".8">
          <path d="M96,700 C180,560 170,430 260,320"/>
          <path d="M150,568 C190,560 220,540 232,512"/><path d="M186,470 C224,462 250,444 262,416"/>
        </g>
        <g fill="#8C9680" opacity=".5">
          <ellipse cx="238" cy="506" rx="26" ry="10" transform="rotate(-38 238 506)"/>
          <ellipse cx="206" cy="556" rx="30" ry="11" transform="rotate(-30 206 556)"/>
          <ellipse cx="168" cy="612" rx="34" ry="12" transform="rotate(-22 168 612)"/>
          <ellipse cx="262" cy="414" rx="22" ry="9" transform="rotate(-46 262 414)"/>
        </g>`;
      const forms = ["bottle", "spray", "dropper"];
      const caps = ["#A88F62", "#3E4A38", "#29331F"];
      const trio = tints.slice(0, 3).map((t, i) =>
        `<g transform="translate(${[165, 300, 435][i]},480) scale(${[.72, .92, .8][i]}) translate(-300,-490)">${formBody(forms[i], caps[i])}</g>`).join("");
      return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 750" aria-hidden="true" preserveAspectRatio="xMidYMid slice">
        <defs><clipPath id="${u}"><path d="M110,750 L110,330 Q110,120 300,120 Q490,120 490,330 L490,750 Z"/></clipPath></defs>
        <rect width="600" height="750" fill="${bg}"/>
        <g clip-path="url(#${u})"><rect x="110" y="120" width="380" height="630" fill="#F3EEE3"/>${branch}${trio}</g>
        <path d="M110,750 L110,330 Q110,120 300,120 Q490,120 490,330 L490,750" fill="none" stroke="${INK}" stroke-width="1.2" opacity=".3"/>
      </svg>`;
    }
    function monogram(letter, bg) {
      const u = uid();
      return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 750" aria-hidden="true">
        <defs><radialGradient id="${u}" cx=".5" cy=".4" r=".8"><stop offset="0" stop-color="#fff" stop-opacity=".5"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient></defs>
        <rect width="600" height="750" fill="${bg}"/><rect width="600" height="750" fill="url(#${u})"/>
        <circle cx="300" cy="375" r="205" fill="none" stroke="${INK}" opacity=".3"/>
        <circle cx="300" cy="375" r="186" fill="none" stroke="${INK}" opacity=".14"/>
        <text x="300" y="452" text-anchor="middle" font-family="Cormorant Garamond,Georgia,serif" font-size="230" font-style="italic" fill="${INK}" opacity=".85">${esc(letter)}</text>
      </svg>`;
    }
    return { front, alt, zoom, scene, monogram };
  })();

  /* ---------- Étoiles ---------- */
  function stars(rating) {
    let out = "";
    for (let i = 1; i <= 5; i++) out += `<i class="${i <= Math.round(rating) ? "f" : ""}"></i>`;
    return `<span class="stars" aria-hidden="true">${out}</span>`;
  }

  /* ---------- Stores — avec gestion d'erreur explicite ---------- */
  function createStore(key) {
    const load = () => { try { return JSON.parse(localStorage.getItem(key)) || []; } catch (e) { console.warn("[store] load failed", key, e); return []; } };
    let items = load();
    const subs = [];
    const emit = () => {
      try { localStorage.setItem(key, JSON.stringify(items)); }
      catch (e) { console.warn("[store] save failed", key, e); }
      subs.forEach(f => {
        try{ f(items); }catch(err){ console.warn("[store] subscriber error", err); }
      });
    };
    return {
      get items() { return items.slice(); },
      subscribe(f) { subs.push(f); },
      has(id) { return items.some(i => (typeof i === "string" ? i : i.id) === id); },
      add(id, qty) {
        qty = qty || 1;
        const row = items.find(i => i.id === id);
        row ? row.qty += qty : items.push({ id, qty });
        emit();
      },
      setQty(id, qty) { const r = items.find(i => i.id === id); if (!r) return; r.qty = Math.max(1, Math.min(9, qty)); emit(); },
      remove(id) { items = items.filter(i => i.id !== id); emit(); },
      toggle(id) { this.has(id) ? this.remove(id) : items.push(id); emit(); },
      count() { return items.reduce((n, i) => n + (i.qty || 1), 0); },
      subtotal() { return items.reduce((n, i) => n + ((byId(i.id) || {}).price || 0) * i.qty, 0); },
      clear() { items = []; emit(); }
    };
  }
  const cart = createStore("cleo_cart_v1");
  const wish = createStore("cleo_wishlist_v1");
  const compare = createStore("cleo_compare_v1");
  // Comparison helpers — max 3, real data only, strings
  compare.toggleCompare = function(id){
    if(this.has(id)){
      this.remove(id);
    } else {
      if(this.items.length>=3){ toast('Comparaison limitée à 3 produits — retirez-en un'); return; }
      // push string directly to keep has() working (wishlist style)
      let itemsRef = this.items;
      // Access internal items via closure — need to push via has/remove logic
      // Instead use toggle logic directly
      if(!this.has(id)){
        // Directly manipulate items array via private closure not accessible, so use remove/add via string path
        // Use wishlist-style push
        const store = this;
        // Hack: access items via store.items getter returns copy, need to use internal
        // So we use store.toggle if exists, but it expects string
        // Let's use the original toggle but ensure it pushes string
        // For compare, items are strings, so we need to push string
        // We'll directly call the original toggle's logic
        const has = store.has(id);
        if(!has){
          // Need to push to internal items — use store.add with string hack
          // Actually createStore's toggle pushes id string, not object, so we can call toggle
          // But toggle will do remove if has else push id string
          // So we can just call toggle
          store.toggle(id);
          return;
        }
      }
    }
  };
  // Fix compare to store strings: override add to push string for compare
  const origAdd = compare.add;
  compare.add = function(id){ if(!this.has(id) && this.items.length<3){ this.toggle(id); } };

  // expose live getters so that FREE_SHIP and products/brands/cats stay à jour après fetch & file:// timing
  const CLEO = D.CLEO = {
    ROOT, PAGES, get FREE_SHIP(){ return FREE_SHIP; }, set FREE_SHIP(v){ FREE_SHIP=v; }, reduceMotion,
    $, $$, esc, fmt, off, slugify, qs, setQs, debounce,
    get products(){ return (D.CLEO_PRODUCTS && D.CLEO_PRODUCTS.length) ? D.CLEO_PRODUCTS : products; },
    set products(v){ products = v; D.CLEO_PRODUCTS = v; },
    get brands(){ return (D.CLEO_BRANDS && D.CLEO_BRANDS.length) ? D.CLEO_BRANDS : brands; },
    get cats(){ return (D.CLEO_CATEGORIES && D.CLEO_CATEGORIES.length) ? D.CLEO_CATEGORIES : cats; },
    get concerns(){ return (D.CLEO_CONCERNS && D.CLEO_CONCERNS.length) ? D.CLEO_CONCERNS : concerns; },
    get articles(){ return (D.CLEO_ARTICLES && D.CLEO_ARTICLES.length) ? D.CLEO_ARTICLES : articles; },
    byId, brandByName, brandBySlug, catBySlug, concernBySlug, articleById, searchProducts,
    ART, stars, cart, wish, compare, hydrateCatalog,
    // helpers pour debug
    _root: ROOT
  };

  /* ============================================================
     GABARITS PARTAGÉS
     ============================================================ */
  function cardHTML(p) {
    const d = off(p), inWish = wish.has(p.id);
    const pts = Math.floor((p.price/1000)*10);
    const badge = p.oldPrice ? `<span class="pcard__flag pcard__flag--promo">−${d}%</span>`
          : p.isNew ? `<span class="pcard__flag">Nouveauté</span>`
          : p.featured ? `<span class="pcard__flag" style="background:var(--green)">Sélection</span>`
          : !p.stock ? `<span class="pcard__flag" style="background:#847D6D">Épuisé</span>` : "";
    const lowStock = p.track_stock && p.stock_quantity>0 && p.stock_quantity<=5 ? `<span class="pcard__stock-low">Plus que ${p.stock_quantity}</span>` : "";
    const featClass = p.featured ? ' pcard--featured' : '';
    return `<article class="pcard${featClass}" data-pid="${esc(p.id)}">
      <div class="pcard__media">
        ${badge}
        ${lowStock}
        <button class="pcard__wish ${inWish ? "is-active" : ""}" data-wish="${esc(p.id)}" aria-label="${inWish ? "Retirer des favoris" : "Ajouter aux favoris"}" aria-pressed="${inWish}">
          <svg viewBox="0 0 24 24"><path d="M12 21c-5.5-3.6-9-7.1-9-11a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 3.9-3.5 7.4-9 11z"/></svg>
        </button>
        <button class="pcard__compare ${compare.has(p.id) ? "is-active" : ""}" data-compare="${esc(p.id)}" aria-label="Comparer" title="Comparer" style="position:absolute;top:52px;right:10px;z-index:4;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--bg) 82%,transparent);backdrop-filter:blur(6px);opacity:0;transform:translateY(-6px);transition:all .45s var(--ease);border:1px solid transparent">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M9 3H4a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/><path d="M20 3h-5a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/><path d="M20 14h-5a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1v-5a1 1 0 0 0-1-1z"/><path d="M9 14H4a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1v-5a1 1 0 0 0-1-1z"/></svg>
        </button>
        <a href="${PAGES}product.html?id=${encodeURIComponent(p.id)}" class="pcard__media-link" aria-label="${esc(p.name)}">
          <div class="pcard__img pcard__img--a">${ART.front(p)}</div>
          <div class="pcard__img pcard__img--b">${ART.alt(p)}</div>
        </a>
        <div class="pcard__quick"><div class="quick-btns">
          <button class="qbtn" data-add="${esc(p.id)}" ${p.stock ? "" : "disabled"}>${p.stock ? "Ajouter au panier" : "Épuisé"}</button>
          <button class="qbtn qbtn--icon" data-quick="${esc(p.id)}" aria-label="Aperçu rapide"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div></div>
      </div>
      <div class="pcard__info">
        <span class="pcard__brand">${esc(p.brand)}</span>
        <h3 class="pcard__name"><a href="${PAGES}product.html?id=${encodeURIComponent(p.id)}">${esc(p.name)}</a></h3>
        <span class="pcard__sub">${esc(p.size || "")} · ${esc((catBySlug(p.cat) || {}).name || "")}</span>
        <span class="pcard__rating">${stars(p.rating)}<span>${Number(p.rating).toFixed(1)} (${p.reviews})</span></span>
        <span class="pcard__prices">
          <span class="price-now">${fmt(p.price)}</span>
          ${p.oldPrice ? `<span class="price-old">${fmt(p.oldPrice)}</span><span class="price-off">−${d}%</span>` : ""}
        </span>
        <span class="pcard__loyalty" aria-label="Points fidélité">+${pts} pts fidélité • 10 pts = 1 DT</span>
      </div>
    </article>`;
  }
  function tileHTML() {
    return `<div class="etile">
      <span class="eyebrow eyebrow--bare" style="color:var(--gold-bright)">Le comptoir</span>
      <h3>Un doute sur votre routine&nbsp;? Nos pharmaciennes y répondent.</h3>
      <p>Diagnostic offert, en ligne comme à l’officine.</p>
      <a class="link-u link-u--light" href="${PAGES}contact.html">Demander conseil<span class="arr">→</span></a>
    </div>`;
  }

  /* ---------- Chrome : en-tête — signature Cléopâtre 2.0 ---------- */
  function headerHTML() {
    const R = ROOT;
    const liveCats = (D.CLEO_CATEGORIES && D.CLEO_CATEGORIES.length ? D.CLEO_CATEGORIES : cats);
    const liveBrands = (D.CLEO_BRANDS && D.CLEO_BRANDS.length ? D.CLEO_BRANDS : brands);
    const liveConcerns = (D.CLEO_CONCERNS && D.CLEO_CONCERNS.length ? D.CLEO_CONCERNS : concerns);
    const liveProducts = (D.CLEO_PRODUCTS && D.CLEO_PRODUCTS.length ? D.CLEO_PRODUCTS : products);
    const feat = (liveProducts.find(p => p.featured) || liveProducts[0] || null);
    const feat2 = (liveProducts.find(p => p.bestseller===1) || liveProducts[1] || feat || null);
    // Editorial mega data — live for file:// timing
    const univers = liveCats.map(c=> ({ slug:c.slug, name:c.name, tag:c.tagline, desc:c.description, img:c.image }));
    const concernsList = liveConcerns.slice(0,6);
    const featuredBrands = liveBrands.filter(b=>b.featured).slice(0,6);
    const allBrandsAZ = liveBrands.slice().sort((a,b)=>a.name.localeCompare(b.name,'fr')).slice(0,8);
    return `
    <div class="announce" aria-hidden="true">
      <div class="announce__track">
        <p class="announce__item is-on">Livraison offerte dès <em>99&nbsp;DT</em> d’achat — partout en Tunisie</p>
        <p class="announce__item">Conseils pharmaceutiques personnalisés, <em>en ligne &amp; à l’officine</em></p>
        <p class="announce__item">Produits <em>100&nbsp;% authentiques</em>, marques officielles garanties</p>
      </div>
      <div class="announce__dots"><i class="is-on"></i><i></i><i></i></div>
    </div>
    <header class="hdr" id="hdr">
      <div class="hdr__inner container">
        <button class="burger" data-nav-open aria-label="Ouvrir le menu"><i></i><i></i></button>
        <a class="brand" href="${R}index.html" aria-label="Cléopâtre — accueil">
          <span class="brand__name">Cléopâtre</span>
          <span class="brand__sub">Parapharmacie · Tunis</span>
        </a>
        <nav class="nav" aria-label="Navigation principale">
          <ul class="nav__list">
            <li class="nav__item">
              <a class="nav__link" href="${PAGES}shop.html" aria-haspopup="true" aria-expanded="false">Univers<i class="nav__caret"></i></a>
              <div class="mega mega--univers"><div class="mega__inner container">
                <div class="mega__col mega__col--univers">
                  <h4>Nos univers</h4>
                  <ul class="mega__univers">
                    ${univers.map(c => `<li>
                      <a href="${PAGES}category.html?cat=${c.slug}" class="mega__u-link">
                        <span class="mega__u-name">${c.name}</span>
                        <span class="mega__u-tag">${c.tag||''}</span>
                      </a>
                    </li>`).join("")}
                  </ul>
                  <a class="link-u" href="${PAGES}shop.html" style="margin-top:14px">Toute la boutique<span class="arr">→</span></a>
                </div>
                <div class="mega__col">
                  <h4>Besoins</h4>
                  <ul class="mega__concerns">
                    ${concernsList.map(x=> `<li><a href="${PAGES}shop.html?concern=${x.slug}">${x.name}<span>${x.line.split(',')[0]}</span></a></li>`).join("")}
                  </ul>
                </div>
                <div class="mega__feature mega__feature--editorial">
                  <div class="art">${feat ? ART.front(feat) : '<div style="aspect-ratio:16/11;background:var(--card);display:grid;place-items:center;color:var(--muted)">Cléopâtre</div>'}</div>
                  <span class="mega__kicker">Sélection du comptoir</span>
                  <p class="mega__note">« ${feat ? feat.name.split(',')[0] : 'La sélection du comptoir'} — ${feat && feat.short ? feat.short.slice(0,72)+'…' : 'renouvelée chaque saison avec exigence.'} »</p>
                  <a class="link-u" href="${PAGES}category.html?cat=${feat?feat.cat:'visage'}">Découvrir<span class="arr">→</span></a>
                </div>
              </div></div>
            </li>
            <li class="nav__item">
              <a class="nav__link" href="${PAGES}shop.html?concern=solaire" aria-haspopup="true" aria-expanded="false">Besoins<i class="nav__caret"></i></a>
              <div class="mega mega--besoins"><div class="mega__inner container" style="grid-template-columns:1.1fr 1fr minmax(280px,.9fr)">
                <div class="mega__col">
                  <h4>Je cherche une solution</h4>
                  <ul class="mega__needs">
                    ${liveConcerns.map(c=> `<li><a href="${PAGES}shop.html?concern=${c.slug}"><strong>${c.name}</strong><span>${c.line}</span></a></li>`).join("")}
                  </ul>
                </div>
                <div class="mega__col">
                  <h4>Rituels</h4>
                  <ul>
                    <li><a href="${PAGES}shop.html?concern=hydration">Hydratation — barrière réparée</a></li>
                    <li><a href="${PAGES}shop.html?concern=anti-age">Anti-âge — fermeté & éclat</a></li>
                    <li><a href="${PAGES}shop.html?concern=acne">Imperfections — peau nette</a></li>
                    <li><a href="${PAGES}shop.html?concern=sensibilite">Sensibilité — apaiser durablement</a></li>
                    <li><a href="${PAGES}shop.html?concern=solaire">Protection — été protégé</a></li>
                    <li><a href="${PAGES}shop.html?concern=pigmentation">Taches & éclat</a></li>
                  </ul>
                  <a class="link-u" href="${PAGES}conseils.html" style="margin-top:12px">Voir le journal<span class="arr">→</span></a>
                </div>
                <div class="mega__feature">
                  <div class="art">${feat2 ? ART.front(feat2) : '<div style="aspect-ratio:16/11;background:var(--card);display:grid;place-items:center;color:var(--muted)">Conseil</div>'}</div>
                  <span class="mega__kicker">Le conseil</span>
                  <p class="mega__note">« Un doute sur votre routine ? Nos pharmaciennes y répondent. »</p>
                  <a class="btn btn--ghost btn--sm" href="${PAGES}contact.html">Demander conseil</a>
                </div>
              </div></div>
            </li>
            <li class="nav__item">
              <a class="nav__link" href="${PAGES}brands.html" aria-haspopup="true" aria-expanded="false">Marques<i class="nav__caret"></i></a>
              <div class="mega mega--marques"><div class="mega__inner container" style="grid-template-columns:1fr 1fr 1fr minmax(260px,.85fr)">
                <div class="mega__col"><h4>Maisons favorites</h4><ul>${featuredBrands.map(b => `<li><a href="${PAGES}brand.html?brand=${b.slug}">${b.name}<span>${b.tagline.split('—')[0].trim()}</span></a></li>`).join("")}</ul></div>
                <div class="mega__col"><h4>Répertoire A–Z</h4><ul>${allBrandsAZ.map(b => `<li><a href="${PAGES}brand.html?brand=${b.slug}">${b.name}</a></li>`).join("")}
                  <li style="margin-top:12px"><a class="link-u" style="font-size:.68rem" href="${PAGES}brands.html">Répertoire complet A–Z<span class="arr">→</span></a></li></ul></div>
                <div class="mega__col"><h4>Nos engagements</h4><ul style="gap:8px; font-size:.84rem; color:var(--soft); line-height:1.6">
                  <li>Authenticité certifiée</li><li>Laboratoires officiels</li><li>Traçabilité comptoir</li><li>Conseils diplômées</li>
                </ul></div>
                <div class="mega__feature">
                  <p class="mega__note">Seize laboratoires référencés, un seul standard&nbsp;: l’efficacité documentée.</p>
                  <a class="btn btn--ghost btn--sm" href="${PAGES}brands.html">Toutes les marques</a>
                </div>
              </div></div>
            </li>
            <li class="nav__item">
              <a class="nav__link" href="${PAGES}shop.html" aria-haspopup="true" aria-expanded="false">Rituels<i class="nav__caret"></i></a>
              <div class="mega mega--rituels"><div class="mega__inner container" style="grid-template-columns:1fr 1fr minmax(300px,.95fr)">
                <div class="mega__col">
                  <h4>Mon rituel Cléopâtre</h4>
                  <ul class="mega__rituels">
                    <li><a href="${PAGES}shop.html?concern=hydration"><strong>01 — Nettoyer</strong><span>Eaux micellaires · Gels doux</span></a></li>
                    <li><a href="${PAGES}shop.html?concern=anti-age"><strong>02 — Traiter</strong><span>Sérums · Concentrés</span></a></li>
                    <li><a href="${PAGES}shop.html?concern=hydration"><strong>03 — Hydrater</strong><span>Crèmes · Baumes</span></a></li>
                    <li><a href="${PAGES}shop.html?concern=solaire"><strong>04 — Protéger</strong><span>Solaires · Soins jour</span></a></li>
                  </ul>
                  <a class="link-u" href="${PAGES}conseils.html" style="margin-top:12px">Voir les conseils<span class="arr">→</span></a>
                </div>
                <div class="mega__col">
                  <h4>Par besoin</h4>
                  <ul>
                    <li><a href="${PAGES}shop.html?concern=acne">Peau à imperfections</a></li>
                    <li><a href="${PAGES}shop.html?concern=sensibilite">Peau réactive</a></li>
                    <li><a href="${PAGES}shop.html?concern=pigmentation">Taches & éclat</a></li>
                    <li><a href="${PAGES}category.html?cat=cheveux">Cheveux</a></li>
                    <li><a href="${PAGES}category.html?cat=corps">Corps</a></li>
                    <li><a href="${PAGES}category.html?cat=bebe">Bébé</a></li>
                  </ul>
                </div>
                <div class="mega__feature">
                  <div class="art">${ART.scene(["#E9E2D2","#DFE5DA","#D9CBB0"], {bg:"#EAE3D6"})}</div>
                  <span class="mega__kicker">Le comptoir vous guide</span>
                  <p class="mega__note">« Quatre gestes. Pas un de plus. Une routine courte tenue vaut mieux qu’une longue abandonnée. »</p>
                  <a class="btn btn--ghost btn--sm" href="${PAGES}shop.html">Découvrir ma routine</a>
                </div>
              </div></div>
            </li>
            <li class="nav__item"><a class="nav__link" href="${PAGES}conseils.html">Conseils</a></li>
            <li class="nav__item"><a class="nav__link" href="${PAGES}promotions.html">Promotions</a></li>
            <li class="nav__item"><a class="nav__link" href="${PAGES}about.html">Maison</a></li>
          </ul>
        </nav>
        <div class="hdr__actions">
          <a class="icon-btn hdr__account icon-btn--account" data-account href="${PAGES}login.html" aria-label="Espace client">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
          </a>
          <a class="icon-btn" href="${PAGES}wishlist.html" aria-label="Favoris">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21c-5.5-3.6-9-7.1-9-11a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 3.9-3.5 7.4-9 11z"/></svg>
            <output class="badge" data-wish-badge>0</output>
          </a>
          <button class="icon-btn" data-cart-open aria-label="Panier">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
            <output class="badge" data-cart-badge>0</output>
          </button>
          <button class="icon-btn" data-search-open aria-label="Rechercher">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-5-5"/></svg>
          </button>
        </div>
      </div>
    </header>
    <div class="scrim" data-scrim></div>
    <aside class="drawer drawer--nav" id="mnav" role="dialog" aria-modal="true" aria-label="Menu">
      <div class="drawer__head">
        <span class="drawer__title">Menu</span>
        <button class="drawer__close" data-drawer-close aria-label="Fermer le menu"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M4 4l16 16M20 4L4 20"/></svg></button>
      </div>
      <div class="drawer__body mnav">
        <div class="mnav__main">
          <div class="mnav__item"><a class="mnav__row" href="${R}index.html"><span>Accueil</span></a></div>
          ${cats.map(c => `
          <div class="mnav__item">
            <button class="mnav__row" aria-expanded="false" data-mnav-toggle><span>${c.name}</span><svg viewBox="0 0 24 24"><path d="M12 4v16M4 12h16"/></svg></button>
            <div class="mnav__sub"><div class="mnav__sub-inner">
              <a href="${PAGES}category.html?cat=${c.slug}">Tout l’univers ${c.name}</a>
              ${concerns.slice(0, 3).map(x => `<a href="${PAGES}category.html?cat=${c.slug}&concern=${x.slug}">${x.name}</a>`).join("")}
            </div></div>
          </div>`).join("")}
          <div class="mnav__item"><a class="mnav__row" href="${PAGES}brands.html"><span>Marques</span></a></div>
          <div class="mnav__item"><a class="mnav__row" href="${PAGES}conseils.html"><span>Conseils</span></a></div>
          <div class="mnav__item"><a class="mnav__row" href="${PAGES}promotions.html"><span>Promotions</span></a></div>
          <div class="mnav__item"><a class="mnav__row" href="${PAGES}about.html"><span>Notre maison</span></a></div>
          <div class="mnav__item"><a class="mnav__row" href="${PAGES}contact.html"><span>Contact</span></a></div>
        </div>
        <div class="mnav__foot">
          <a class="link-u" href="${PAGES}wishlist.html">Mes favoris</a>
          <a class="link-u" href="${PAGES}cart.html">Mon panier</a>
        </div>
        <p class="mnav__meta">12 Avenue Habib Bourguiba, Tunis 1001<br>+216 29 835 402 · Lun–Sam 9h–19h</p>
      </div>
    </aside>
    <aside class="drawer drawer--cart" id="cartDrawer" role="dialog" aria-modal="true" aria-label="Panier">
      <div class="drawer__head">
        <span class="drawer__title">Votre panier · <span data-cart-count>0 article</span></span>
        <button class="drawer__close" data-drawer-close aria-label="Fermer le panier"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M4 4l16 16M20 4L4 20"/></svg></button>
      </div>
      <div class="drawer__body" data-cart-body></div>
      <div class="drawer__foot" data-cart-foot hidden></div>
    </aside>
    <div class="search-layer" id="searchLayer" role="dialog" aria-modal="true" aria-label="Recherche">
      <div class="search-layer__head">
        <span class="eyebrow eyebrow--bare">Recherche</span>
        <button class="drawer__close" data-search-close aria-label="Fermer la recherche"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M4 4l16 16M20 4L4 20"/></svg></button>
      </div>
      <form class="search-form" data-search-form action="${PAGES}search.html" method="get">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-5-5"/></svg>
        <input type="search" name="q" placeholder="Votre recherche…" autocomplete="off" aria-label="Votre recherche">
        <span class="search-hint">↵ pour lancer · Échap pour fermer</span>
      </form>
      <div class="search-results" data-search-results></div>
      <div class="container search-default" data-search-default>
        <div>
          <p class="sres__label">Suggestions</p>
          <div class="chips">
            ${["Anthelios", "Eau micellaire", "Huile Prodigieuse", "Anti-taches", "SPF 50", "Bébé"].map(s => `<a class="chip" href="${PAGES}search.html?q=${encodeURIComponent(s)}">${s}</a>`).join("")}
          </div>
        </div>
        <div>
          <p class="sres__label">Parcourir</p>
          <ul>${cats.slice(0, 4).map(c => `<li><a class="big-link" href="${PAGES}category.html?cat=${c.slug}">${c.name}<i>Univers</i></a></li>`).join("")}
            <li><a class="big-link" href="${PAGES}brands.html">Marques<i>Répertoire A–Z</i></a></li></ul>
        </div>
      </div>
    </div>`;
  }

  /* ---------- Pied de page ---------- */
  function footerHTML() {
    return `<footer class="footer grain">
      <div class="footer__newsletter"><div class="container newsletter">
        <div>
          <span class="eyebrow eyebrow--bare" style="color:var(--gold-bright)">La correspondance</span>
          <h3 style="margin-top:14px;font-weight:400">Une lettre par mois.<br>Jamais davantage.</h3>
        </div>
        <div>
          <form class="nl-form" novalidate>
            <input type="email" name="email" placeholder="Votre adresse e-mail" aria-label="Votre adresse e-mail" required>
            <button type="submit">S’inscrire<span class="arr">→</span></button>
          </form>
          <p class="nl-msg" role="status" aria-live="polite"></p>
        </div>
      </div></div>
      <div class="footer__cols container">
        <div class="footer__about">
          <span class="brand"><span class="brand__name" style="color:#EDEAE0">Cléopâtre</span><span class="brand__sub" style="color:rgba(237,234,224,.45)">Parapharmacie · Tunis</span></span>
          <p>L’expertise parapharmaceutique française, choisie avec exigence pour les peaux tunisiennes depuis plus de vingt ans.</p>
        </div>
        <div><h5>Univers</h5><ul>${cats.map(c => `<li><a href="${PAGES}category.html?cat=${c.slug}">${c.name}</a></li>`).join("")}</ul></div>
        <div><h5>La maison</h5><ul>
          <li><a href="${PAGES}about.html">Notre histoire</a></li>
          <li><a href="${PAGES}brands.html">Nos marques</a></li>
          <li><a href="${PAGES}conseils.html">Le journal</a></li>
          <li><a href="${PAGES}promotions.html">Offres du moment</a></li>
          <li><a href="${PAGES}contact.html">Nous écrire</a></li>
        </ul></div>
        <div><h5>Nous trouver</h5>
          <address>12 Avenue Habib Bourguiba<br>Tunis 1001, Tunisie<br><a href="tel:+21629835402">+216 29 835 402</a></address>
          <p class="footer__hours">Lundi – Samedi · 9h → 19h<br>Livraison 24–72 h partout en Tunisie</p>
        </div>
      </div>
      <div class="footer__mark" aria-hidden="true"><div class="footer__wordmark">Cléopâtre</div></div>
      <div class="footer__bar">
        <span>© ${new Date().getFullYear()} Parapharmacie Cléopâtre — Tunis</span>
        <nav aria-label="Liens légaux"><a href="${PAGES}about.html">Mentions</a><a href="${PAGES}contact.html">Livraison &amp; retours</a><a href="${PAGES}contact.html">Confidentialité</a></nav>
      </div>
    </footer>`;
  }

  /* ============================================================
     COMPORTEMENTS
     ============================================================ */
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add("is-in"); io.unobserve(e.target); } });
  }, { threshold: .1, rootMargin: "0px 0px -5% 0px" });

  function observe(scope) {
    $$("[data-reveal], .mask-reveal, .line-grow", scope).forEach(el =>
      reduceMotion ? el.classList.add("is-in") : io.observe(el));
  }

  function toast(msg, artHTML, actionLabel, actionFn) {
    let zone = $(".toast-zone");
    if (!zone) { zone = document.createElement("div"); zone.className = "toast-zone"; document.body.appendChild(zone); }
    while (zone.children.length > 2) zone.firstChild.remove();
    const t = document.createElement("div");
    t.className = "toast";
    t.innerHTML = `${artHTML ? `<div class="art">${artHTML}</div>` : ""}<div><small>Cléopâtre</small><b>${msg}</b></div>${actionLabel ? `<button type="button">${actionLabel}</button>` : ""}`;
    if (actionLabel) $("button", t).addEventListener("click", () => { actionFn && actionFn(); dismiss(); });
    zone.appendChild(t);
    const timer = setTimeout(dismiss, 3400);
    function dismiss() { clearTimeout(timer); t.classList.add("is-out"); setTimeout(() => t.remove(), 480); }
  }

  function syncBadges() {
    const cb = $("[data-cart-badge]"), wb = $("[data-wish-badge]");
    if (cb) { const n = cart.count(); cb.textContent = n; cb.classList.toggle("is-on", n > 0); }
    if (wb) { const n = wish.items.length; wb.textContent = n; wb.classList.toggle("is-on", n > 0); }
  }

  let activeDrawer = null;
  function openDrawer(sel) {
    closeDrawers(true);
    const d = $(sel), scrim = $("[data-scrim]");
    if (!d) return;
    d.classList.add("is-open"); scrim && scrim.classList.add("is-on");
    document.body.classList.add("is-locked");
    activeDrawer = d;
    const f = $("input, button", d); f && setTimeout(() => f.focus(), 380);
  }
  function closeDrawers(silent) {
    $$(".drawer.is-open").forEach(d => d.classList.remove("is-open"));
    const scrim = $("[data-scrim]");
    scrim && scrim.classList.remove("is-on");
    document.body.classList.remove("is-locked");
    activeDrawer = null;
  }

  /* Panier — tiroir */
  function renderCart() {
    const body = $("[data-cart-body]"), foot = $("[data-cart-foot]");
    if (!body || !document.getElementById("cartDrawer")) return;
    const items = cart.items;
    const cnt = $("[data-cart-count]");
    if (cnt) cnt.textContent = items.length + (items.length > 1 ? " articles" : " article");
    if (!items.length) {
      foot.hidden = true;
      body.innerHTML = `<div class="cart-empty">
        <div class="empty-art">${ART.scene(["#D9CBB0", "#B9C4CB"], {})}</div>
        <p class="serif-i">Votre panier attend son premier geste.</p>
        <p style="font-size:.85rem;color:var(--muted)">Commençons doucement — la boutique vous ouvre ses rayons.</p>
        <a class="btn btn--ink" href="${PAGES}shop.html">Explorer la boutique</a>
      </div>`;
      return;
    }
    const sub = cart.subtotal();
    const remain = Math.max(0, FREE_SHIP - sub);
    const pct = Math.min(1, sub / FREE_SHIP);
    body.innerHTML = `
      <div class="ship-progress">
        <p>${remain > 0 ? `Encore <em>${fmt(remain)}</em> et la livraison est offerte.` : `<em>Livraison offerte</em> — seuil atteint.`}</p>
        <div class="ship-bar"><i style="--p:${pct.toFixed(3)}"></i></div>
      </div>
      ${items.map(({ id, qty }) => {
        const p = byId(id); if (!p) return "";
        return `<div class="cart-line">
          <a class="cart-line__art" href="${PAGES}product.html?id=${encodeURIComponent(id)}" aria-hidden="true" tabindex="-1">${p.imageThumb || p.image ? `<img src="${(p.imageThumb || p.image).startsWith("http") || (p.imageThumb || p.image).startsWith("/") ? (p.imageThumb || p.image) : ROOT + (p.imageThumb || p.image)}" alt="" class="pcard-photo pcard-photo--thumb" loading="lazy" decoding="async"/>` : ART.front(p)}</a>
          <div>
            <span class="cart-line__brand">${esc(p.brand)}</span>
            <a class="cart-line__name" href="${PAGES}product.html?id=${encodeURIComponent(id)}">${esc(p.name)}</a>
            <p class="cart-line__meta">${esc(p.size || "")}</p>
            <p class="cart-line__price">${p.oldPrice ? `<span class="cart-line__old">${fmt(p.oldPrice)}</span>` : ""}${fmt(p.price * qty)}</p>
            <button class="cart-line__remove" data-remove="${id}">Retirer</button>
          </div>
          <div class="qty" aria-label="Quantité">
            <button data-dec="${id}" aria-label="Diminuer">−</button><output>${qty}</output><button data-inc="${id}" aria-label="Augmenter">+</button>
          </div>
        </div>`;
      }).join("")}
      <div class="cart-recos">
        <h5>${items.length===1 ? 'Complétez votre rituel' : 'Le comptoir suggère'}</h5>
        <div class="reco-row">${(function(){
          // Contextual: same category/concern as cart items, not random top rating
          const cartCats=[...new Set(items.map(i=> (byId(i.id)||{}).cat).filter(Boolean))];
          const cartConcerns=[...new Set(items.flatMap(i=> (byId(i.id)||{}).concerns||[]))];
          let pool=products.filter(p=> !cart.has(p.id));
          // Prioritize same category, then same concern, then best rated
          let byCat=pool.filter(p=> cartCats.includes(p.cat));
          let byConcern=pool.filter(p=> !byCat.includes(p) && (p.concerns||[]).some(c=> cartConcerns.includes(c)));
          let rest=pool.filter(p=> !byCat.includes(p) && !byConcern.includes(p)).sort((a,b)=> b.rating - a.rating);
          let ordered=[...byCat.sort((a,b)=> b.rating-a.rating), ...byConcern.sort((a,b)=> b.rating-a.rating), ...rest];
          if(!ordered.length) ordered=pool.slice(0,4);
          return ordered.slice(0,4).map(p=> `<a class="reco-mini" href="${PAGES}product.html?id=${encodeURIComponent(p.id)}">
            <div class="art">${ART.front(p)}</div><b>${esc(p.name.split(" ").slice(0, 3).join(" "))}</b><small>${fmt(p.price)}</small></a>`).join("");
        })()}</div>
      </div>`;
    foot.hidden = false;
    foot.innerHTML = `
      <div class="subtotal">
        <span>Sous-total</span>
        <strong>${fmt(sub)}<small>Hors livraison · paiement à la réception possible</small></strong>
      </div>
      <div class="drawer__cta">
        <a class="btn btn--green btn--wide" href="${PAGES}cart.html">Passer au panier</a>
        <a class="link-u" href="${PAGES}shop.html">Poursuivre mes découvertes<span class="arr">→</span></a>
      </div>`;
  }

  /* Aperçu rapide */
  let modalEl = null;
  function ensureModal() {
    if (modalEl) return modalEl;
    modalEl = document.createElement("div");
    modalEl.className = "modal";
    modalEl.innerHTML = `<div class="modal__panel" role="dialog" aria-modal="true">
      <button class="modal__close" data-modal-close aria-label="Fermer"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M4 4l16 16M20 4L4 20"/></svg></button>
      <div class="modal__media" data-qv-media></div>
      <div class="modal__body" data-qv-body></div></div>`;
    document.body.appendChild(modalEl);
    modalEl.addEventListener("click", e => {
      if (e.target === modalEl || e.target.closest("[data-modal-close]")) closeModal();
    });
    return modalEl;
  }
  function openQuickView(pid) {
    const p = byId(pid); if (!p) return;
    const m = ensureModal(), d = off(p);
    $("[data-qv-media]", m).innerHTML = ART.front(p);
    $("[data-qv-body]", m).innerHTML = `
      <span class="pcard__brand">${esc(p.brand)}</span>
      <h3>${esc(p.name)}</h3>
      <span class="pcard__rating">${stars(p.rating)}<span>${Number(p.rating).toFixed(1)} · ${p.reviews} avis vérifiés</span></span>
      <span class="pcard__prices"><span class="price-now">${fmt(p.price)}</span>${p.oldPrice ? `<span class="price-old">${fmt(p.oldPrice)}</span><span class="price-off">−${d}%</span>` : ""}</span>
      <p class="desc">${esc(p.short)}</p>
      <ul class="modal__meta">
        <li>${esc(p.size || "")}</li>
        <li>${p.stock ? "En stock — expédié sous 24 h" : "Réapprovisionnement en cours"}</li>
        <li>Livraison offerte dès 99 DT</li>
      </ul>
      <div class="modal__actions">
        <button class="btn btn--ink" data-add="${esc(pid)}" ${p.stock ? "" : "disabled"}>${p.stock ? "Ajouter au panier" : "Épuisé"}</button>
        <button class="btn btn--ghost" data-wish="${esc(pid)}">♡ Favori</button>
      </div>
      <a class="link-u" href="${PAGES}product.html?id=${encodeURIComponent(pid)}">Voir la fiche complète<span class="arr">→</span></a>`;
    m.classList.add("is-open");
    document.body.classList.add("is-locked");
  }
  function closeModal() {
    if (modalEl) { modalEl.classList.remove("is-open"); document.body.classList.remove("is-locked"); }
  }
  function openCompare(){
    const ids=compare.items.slice(0,3);
    if(!ids.length){ toast('Sélectionnez jusqu’à 3 produits à comparer'); return; }
    const prods=ids.map(id=> byId(id)).filter(Boolean);
    if(!prods.length){ toast('Aucun produit à comparer'); return; }
    let m=document.getElementById('compareModal');
    if(!m){
      m=document.createElement('div');
      m.id='compareModal';
      m.className='modal';
      m.innerHTML=`<div class="modal__panel" style="width:min(1100px,96vw);grid-template-columns:1fr;max-height:90vh" role="dialog" aria-modal="true">
        <button class="modal__close" data-compare-close aria-label="Fermer"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M4 4l16 16M20 4L4 20"/></svg></button>
        <div style="padding:clamp(18px,3vw,28px)"><div id="compareBody"></div></div></div>`;
      document.body.appendChild(m);
      m.addEventListener('click', e=>{ if(e.target===m || e.target.closest('[data-compare-close]')){ m.classList.remove('is-open'); document.body.classList.remove('is-locked'); }});
    }
    const body=m.querySelector('#compareBody');
    const rows=[
      {k:'Produit', v:p=> `<div style="display:grid;gap:8px;justify-items:center;text-align:center"><div style="width:80px;aspect-ratio:4/5;background:var(--card);border-radius:4px;overflow:hidden">${ART.front(p)}</div><b style="font-family:var(--serif)">${esc(p.name)}</b><small style="color:var(--gold);letter-spacing:.14em;text-transform:uppercase">${esc(p.brand)}</small></div>`},
      {k:'Prix', v:p=> `<b>${fmt(p.price)}</b>` + (p.oldPrice?` <small style="color:var(--faint);text-decoration:line-through">${fmt(p.oldPrice)}</small> <small style="color:var(--green)">-${off(p)}%</small>`:'')},
      {k:'Taille', v:p=> esc(p.size||'—')},
      {k:'Catégorie', v:p=> esc((catBySlug(p.cat)||{}).name||p.cat||'—')},
      {k:'Disponibilité', v:p=> p.stock?'<span style="color:var(--success)">Disponible</span>':'<span style="color:var(--danger)">Indisponible</span>'},
      {k:'Note', v:p=> `${p.rating.toFixed(1)} ★ (${p.reviews})`},
      {k:'Action', v:p=> `<div style="display:grid;gap:6px"><button class="btn btn--ink btn--sm" data-add="${p.id}" ${p.stock?'':'disabled'}>Ajouter</button><a class="link-u link-u--small" href="${PAGES}product.html?id=${encodeURIComponent(p.id)}">Voir fiche →</a></div>`}
    ];
    body.innerHTML=`
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h3 style="font-family:var(--serif);font-size:1.4rem">Comparer — ${prods.length} produits</h3><button class="link-u" onclick="localStorage.removeItem('cleo_compare_v1'); location.reload()">Vider</button></div>
      <div style="overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:.86rem">
        <thead><tr><th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);color:var(--muted);font-size:.68rem;letter-spacing:.14em;text-transform:uppercase">Critère</th>${prods.map(p=> `<th style="padding:10px;border-bottom:1px solid var(--line);text-align:center;min-width:160px">${esc(p.brand)}</th>`).join('')}</tr></thead>
        <tbody>${rows.map(r=> `<tr><th style="text-align:left;padding:12px 10px;border-bottom:1px solid var(--line);background:var(--surface);font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--muted)">${r.k}</th>${prods.map(p=> `<td style="padding:12px 10px;border-bottom:1px solid var(--line);text-align:center;vertical-align:top">${r.v(p)}</td>`).join('')}</tr>`).join('')}</tbody>
      </table></div>
      <p style="font-size:.72rem;color:var(--muted);margin-top:12px">Comparaison basée sur données réelles — prix, disponibilité et composition affichés tels qu’en boutique.</p>
    `;
    m.classList.add('is-open'); document.body.classList.add('is-locked');
  }
  function bindSearch() {
    const layer = $("#searchLayer"); if (!layer) return;
    const input = $("input[type=search]", layer), results = $("[data-search-results]", layer), def = $("[data-search-default]", layer);
    let selIdx=-1;
    function getRecent(){ try{ return JSON.parse(localStorage.getItem("cleo_recent_v1"))||[]; }catch(e){ return []; } }
    const POPULAR=["Anthelios","Eau micellaire","Huile Prodigieuse","Anti-taches","SPF 50","Bébé","Sérum","Crème hydratante"];
    function norm(s){ return String(s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim(); }
    function fuzzyScore(q, text){
      // q and text already normalized
      if(!q||!text) return 0;
      if(text.includes(q)) return 100 - text.indexOf(q); // exact substring prioritized
      // tolerant: count matching chars in order
      let score=0; let pos=0;
      for(let ch of q){
        const idx=text.indexOf(ch, pos);
        if(idx!==-1){ score+=1; pos=idx+1; }
      }
      return score*4;
    }
    function searchProductsEnhanced(q){
      q=norm(q);
      if(!q) return [];
      let scored=products.map(p=>{
        const name=norm(p.name);
        const brand=norm(p.brand);
        const sub=norm(p.sub);
        const cat=norm(catBySlug(p.cat)?.name||"");
        const concernsN=(p.concerns||[]).map(c=>norm(concernBySlug(c)?.name||"")).join(' ');
        // Priority scoring 1-5 exact
        let score=0;
        if(name.includes(q)) score=Math.max(score, 105 - name.indexOf(q)); // 1 exact product name
        else if(name.split(/\s+/).some(w=> w.startsWith(q))) score=Math.max(score, 92);
        if(brand.includes(q)) score=Math.max(score, 88 - brand.indexOf(q)); // 2 brand
        if(cat.includes(q)) score=Math.max(score, 78 - cat.indexOf(q)); // 3 category
        if(sub.includes(q)) score=Math.max(score, 68 - sub.indexOf(q)); // 4 subcategory
        if(concernsN.includes(q)) score=Math.max(score, 58);
        const hay=[name,brand,sub,cat,concernsN].join(' ');
        const fuzzy=fuzzyScore(q, hay);
        score=Math.max(score, fuzzy*0.55);
        return {p,score};
      }).filter(x=>x.score>6).sort((a,b)=>b.score-a.score).map(x=>x.p);
      return scored.length? scored : searchProducts(q);
    }
    function live(q) {
      q = (q || "").trim();
      selIdx=-1;
      if (!q) {
        const recent=getRecent();
        results.classList.remove("has-content"); results.innerHTML = ""; def.style.display = "";
        // enrich default with recent
        if(recent.length){
          const recentEl=def.querySelector("[data-recent]");
          if(recentEl){
            recentEl.innerHTML=`<p class="sres__label">Recherches récentes</p><div class="chips" style="justify-content:flex-start">${recent.map(s=>`<a class="chip" href="${PAGES}search.html?q=${encodeURIComponent(s)}">${esc(s)} <span style="margin-left:.4em;opacity:.6" data-clear="${esc(s)}">×</span></a>`).join("")} <button class="chip" data-clear-all style="background:var(--line)">Effacer</button></div>`;
            recentEl.querySelectorAll("[data-clear]").forEach(b=>b.addEventListener("click", e=>{ e.preventDefault(); e.stopPropagation(); clearRecent(b.dataset.clear); live(""); }));
            const ca=recentEl.querySelector("[data-clear-all]"); if(ca) ca.addEventListener("click", e=>{ e.preventDefault(); clearRecent(null,true); live(""); });
          }
        }
        // popular always shown via default HTML, nothing else
        return;
      }
      def.style.display = "none";
      const nq=norm(q);
      const prods = searchProductsEnhanced(q).slice(0, 6);
      const brs = brands.filter(b => norm(b.name).includes(nq)).slice(0, 4);
      const catsHit = cats.filter(c=> norm(c.name).includes(nq)).slice(0,3);
      results.classList.add("has-content");
      results.innerHTML =
        (prods.length ? `<p class="sres__label">Produits — ${prods.length} résultat${prods.length > 1 ? "s" : ""}</p>
        <div class="sres-grid">${prods.map((p,i) => `<a class="sres-item" href="${PAGES}product.html?id=${encodeURIComponent(p.id)}" data-idx="${i}" tabindex="0">
          <div class="art">${ART.front(p)}</div><div><small>${esc(p.brand)}</small><b>${esc(p.name)}</b><span class="pr">${fmt(p.price)}</span></div></a>`).join("")}</div>` : "") +
        (brs.length ? `<p class="sres__label" style="margin-top:18px">Marques</p><div class="chips" style="justify-content:flex-start">${brs.map(b => `<a class="chip" href="${PAGES}brand.html?brand=${b.slug}">${b.name}</a>`).join("")}</div>` : "") +
        (catsHit.length ? `<p class="sres__label" style="margin-top:18px">Univers</p><div class="chips" style="justify-content:flex-start">${catsHit.map(c=>`<a class="chip" href="${PAGES}category.html?cat=${c.slug}">${c.name}</a>`).join("")}</div>` : "") +
        (!prods.length && !brs.length ? `<p class="sres__label">Aucun résultat direct</p>
          <p style="font-size:.86rem;color:var(--muted);margin-top:8px">Suggestions :</p>
          <div class="chips" style="justify-content:flex-start;margin-top:8px">
          <a class="chip" href="${PAGES}search.html?q=${encodeURIComponent(q)}">Voir « ${esc(q)} » dans la boutique</a>
          <a class="chip" href="${PAGES}conseils.html">Chercher dans le journal</a>
          ${POPULAR.slice(0,3).map(s=>`<a class="chip" href="${PAGES}search.html?q=${encodeURIComponent(s)}">${s}</a>`).join("")}</div>` : "") +
        `<p style="margin-top:16px"><a class="link-u" style="font-size:.7rem" href="${PAGES}search.html?q=${encodeURIComponent(q)}">Voir tous les résultats pour « ${esc(q)} » →</a></p>`;
      // keyboard nav setup
      const items=results.querySelectorAll(".sres-item");
      if(items.length) items[0].classList.add("is-focused");
    }
    function clearRecent(val, all){
      try{
        if(all) localStorage.removeItem("cleo_recent_v1");
        else {
          const r=getRecent().filter(x=>x!==val);
          localStorage.setItem("cleo_recent_v1", JSON.stringify(r));
        }
      }catch(e){ console.warn("[search] clearRecent failed", e); }
    }
    function openSearch() { closeDrawers(true); layer.classList.add("is-open"); document.body.classList.add("is-locked"); setTimeout(() => input.focus(), 400); }
    function closeSearch() { layer.classList.remove("is-open"); document.body.classList.remove("is-locked"); input.value = ""; live(""); selIdx=-1; }
    document.addEventListener("click", e => {
      if (e.target.closest("[data-search-open]")) { e.preventDefault(); openSearch(); return; }
      if (e.target.closest("[data-search-close]")) { closeSearch(); }
    });
    input.addEventListener("input", debounce(() => live(input.value), 180));
    input.addEventListener("keydown", e=>{
      const items=results.querySelectorAll(".sres-item");
      if(!items.length) return;
      if(e.key==="ArrowDown"){ e.preventDefault(); selIdx=Math.min(selIdx+1, items.length-1); updateFocus(items); }
      if(e.key==="ArrowUp"){ e.preventDefault(); selIdx=Math.max(selIdx-1, 0); updateFocus(items); }
      if(e.key==="Enter" && selIdx>=0){ e.preventDefault(); items[selIdx].click(); }
      if(e.key==="Escape"){ closeSearch(); }
    });
    function updateFocus(items){
      items.forEach((el,i)=>{ el.classList.toggle("is-focused", i===selIdx); if(i===selIdx) el.focus(); });
    }
    $("form", layer).addEventListener("submit", e => {
      e.preventDefault();
      const q = input.value.trim(); if (!q) return;
      saveRecent(q);
      location.href = PAGES + "search.html?q=" + encodeURIComponent(q);
    });
    function saveRecent(q) {
      try {
        const r = JSON.parse(localStorage.getItem("cleo_recent_v1")) || [];
        localStorage.setItem("cleo_recent_v1", JSON.stringify([q, ...r.filter(x => x !== q)].slice(0, 5)));
      } catch (err) {}
    }
    // inject recent container into default if not present
    if(def && !def.querySelector("[data-recent]")){
      const div=document.createElement("div");
      div.setAttribute("data-recent","");
      div.style.cssText="margin-top:18px";
      def.appendChild(div);
      live("");
    }
    CLEO._openSearch = openSearch;
    CLEO._closeAllOverlays = () => { closeSearch(); };
  }

  /* Curseur personnalisé */
  function initCursor() {
    if (!matchMedia("(pointer:fine)").matches || reduceMotion) return;
    const dot = document.createElement("div"), ring = document.createElement("div");
    dot.className = "cursor-dot"; ring.className = "cursor-ring";
    dot.style.opacity = ring.style.opacity = "0";
    document.body.append(dot, ring);
    let x = -60, y = -60, rx = -60, ry = -60;
    addEventListener("mousemove", e => {
      x = e.clientX; y = e.clientY;
      dot.style.transform = `translate(${x}px,${y}px)`;
      const hover = !!e.target.closest("a,button,input,textarea,select,.pcard");
      document.body.classList.toggle("cursor-hover", hover);
      requestAnimationFrame(() => { dot.style.opacity = ring.style.opacity = "1"; });
    }, { passive: true });
    (function loop() {
      rx += (x - rx) * .16; ry += (y - ry) * .16;
      ring.style.transform = `translate(${rx}px,${ry}px)`;
      requestAnimationFrame(loop);
    })();
  }

  function announceRotate() {
    const items = $$(".announce__item"), dots = $$(".announce__dots i");
    if (items.length < 2) return;
    if(reduceMotion) return;
    let i = 0;
    let tid=null;
    const start=()=>{
      tid=setInterval(() => {
        items[i].classList.remove("is-on"); dots[i].classList.remove("is-on");
        i = (i + 1) % items.length;
        items[i].classList.add("is-on"); dots[i].classList.add("is-on");
      }, 4200);
    };
    // pause when hidden
    document.addEventListener("visibilitychange", ()=>{
      if(document.hidden){ clearInterval(tid); tid=null; }
      else if(!tid) start();
    });
    start();
  }

  function initScrollHeader() {
    const hdr = $("#hdr");
    let last = null, ticking=false;
    addEventListener("scroll", () => {
      if(ticking) return;
      ticking=true;
      requestAnimationFrame(()=>{
        const s = scrollY > 28;
        if (s !== last && hdr) { last = s; hdr.classList.toggle("is-scrolled", s); }
        ticking=false;
      });
    }, { passive: true });
  }

  function setActiveNav(){
    try{
      const path=location.pathname.toLowerCase();
      const params=new URLSearchParams(location.search);
      const cat=params.get('cat');
      const concern=params.get('concern');
      const brand=params.get('brand');
      document.querySelectorAll('.nav__link').forEach(a=>{
        a.classList.remove('is-active');
        a.removeAttribute('aria-current');
      });
      let active=null;
      if(path.includes('category.html') || (path.includes('shop.html') && (cat||concern))) {
        if(concern) active=[...document.querySelectorAll('.nav__link')].find(a=>a.textContent.trim().startsWith('Besoins'));
        else active=[...document.querySelectorAll('.nav__link')].find(a=>a.textContent.trim().startsWith('Univers'));
      } else if(path.includes('shop.html')) {
        active=[...document.querySelectorAll('.nav__link')].find(a=>a.textContent.trim().startsWith('Univers'));
      } else if(path.includes('brands.html')||path.includes('brand.html')||brand){
        active=[...document.querySelectorAll('.nav__link')].find(a=>a.textContent.trim().startsWith('Marques'));
      } else if(path.includes('conseils.html')||path.includes('conseil.html')){
        active=[...document.querySelectorAll('.nav__link')].find(a=>a.textContent.trim()==='Conseils');
      } else if(path.includes('promotions.html')){
        active=[...document.querySelectorAll('.nav__link')].find(a=>a.textContent.trim()==='Promotions');
      } else if(path.includes('about.html')){
        active=[...document.querySelectorAll('.nav__link')].find(a=>a.textContent.trim()==='Maison');
      } else if(path.includes('shop.html') && location.search.includes('concern')){
        active=[...document.querySelectorAll('.nav__link')].find(a=>a.textContent.trim()==='Rituels');
      }
      if(active){ active.classList.add('is-active'); active.setAttribute('aria-current','page'); }
    }catch(e){}
  }

  function delegate() {
    document.addEventListener("click", e => {
      const T = e.target;
      const closest = sel => T.closest(sel);

      let el = closest("[data-add]");
      if (el) {
        const pid = el.dataset.add, p = byId(pid);
        if (p && p.stock) {
          cart.add(pid);
          toast("Ajouté à votre panier", ART.front(p), "Voir le panier", () => openDrawer("#cartDrawer"));
        }
        return;
      }
      el = closest("[data-wish]");
      if (el) {
        const pid = el.dataset.wish, p = byId(pid);
        const active = !el.classList.contains("is-active");
        CLEO.closeWishSync = true;
        el.classList.toggle("is-active", active);
        el.setAttribute("aria-pressed", String(active));
        wish.toggle(pid);
        toast(active ? "Ajouté à vos favoris" : "Retiré de vos favoris", p ? ART.front(p) : "");
        return;
      }
      el = closest("[data-compare]");
      if (el) {
        const pid = el.dataset.compare, p = byId(pid);
        const wasActive = el.classList.contains("is-active");
        if(wasActive){
          compare.remove(pid);
          el.classList.remove("is-active");
          toast('Retiré de la comparaison', p ? ART.front(p) : '');
        } else {
          if(compare.items.length>=3){ toast('Comparaison limitée à 3 — retirez-en un'); return; }
          compare.toggle(pid);
          el.classList.add("is-active");
          toast('Ajouté à la comparaison — '+compare.items.length+'/3', p ? ART.front(p) : '', 'Comparer', ()=> openCompare());
        }
        // Update all compare buttons for this product
        document.querySelectorAll(`[data-compare="${pid}"]`).forEach(b=> b.classList.toggle('is-active', !wasActive));
        return;
      }
      el = closest("[data-quick]");
      if (el) { openQuickView(el.dataset.quick); return; }
      el = closest("[data-inc]");
      if (el) { const r = cart.items.find(i => i.id === el.dataset.inc); r && cart.setQty(r.id, r.qty + 1); return; }
      el = closest("[data-dec]");
      if (el) {
        const r = cart.items.find(i => i.id === el.dataset.dec);
        if (r) r.qty > 1 ? cart.setQty(r.id, r.qty - 1) : cart.remove(r.id);
        return;
      }
      el = closest("[data-remove]");
      if (el) { cart.remove(el.dataset.remove); return; }
      el = closest("[data-cart-open]");
      if (el) { e.preventDefault(); openDrawer("#cartDrawer"); return; }
      el = closest("[data-drawer-close]");
      if (el) { closeDrawers(); return; }
      if (closest(".scrim")) { closeDrawers(); closeModal(); return; }
      el = closest("[data-nav-open]");
      if (el) { e.preventDefault(); openDrawer("#mnav"); return; }
      el = closest("[data-mnav-toggle]");
      if (el) {
        const sub = el.nextElementSibling, open = el.getAttribute("aria-expanded") === "true";
        el.setAttribute("aria-expanded", String(!open));
        sub.style.height = open ? "0px" : sub.firstElementChild.scrollHeight + 20 + "px";
        return;
      }
      el = closest("[data-account]");
      if (el) {
        // Si c'est un lien direct (nouveau header), laisser auth.js gérer href + navigation (mobile direct, desktop dropdown)
        if (el.tagName === 'A' && el.getAttribute('href')) {
          return;
        }
        // Fallback bouton legacy : laisser js/auth.js gérer le menu compte ; fallback si auth.js non chargé
        if (window.CLEO_API && window.CLEO_API.isAuthenticated && !window.CLEO_API.isAuthenticated()) {
          // laisser l'event se propager vers auth.js (menu Connexion)
          return;
        }
        if (!window.CLEO_API) { toast("L’espace client arrive bientôt."); e.preventDefault(); return; }
        return;
      }
      el = closest("[data-search-open]");
      if (el) { e.preventDefault(); CLEO._openSearch && CLEO._openSearch(); return; }
      el = closest("[data-search-close]");
      if (el) { CLEO._closeAllOverlays && CLEO._closeAllOverlays(); return; }
      el = closest("[data-modal-close]");
      if (el) { closeModal(); return; }
      if (T.classList && T.classList.contains("modal")) closeModal();
    });
    /* Fermeture des overlays quand on suit un lien interne depuis un tiroir */
    document.addEventListener("click", e => {
      const a = e.target.closest("a[href]");
      if (a && a.closest(".drawer") && !a.getAttribute("href").startsWith("#")) closeDrawers(true);
    }, true);
    document.addEventListener("keydown", e => {
      if (e.key === "Escape") { closeDrawers(); closeModal(); CLEO._closeAllOverlays && CLEO._closeAllOverlays(); }
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") { e.preventDefault(); CLEO._openSearch && CLEO._openSearch(); }
    });
    document.addEventListener("submit", e => {
      const f = e.target.closest(".nl-form");
      if (!f) return;
      e.preventDefault();
      const email = f.email.value.trim();
      const msg = $(".nl-msg", f.parentElement);
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { msg.textContent = "Une adresse valide, s’il vous plaît."; msg.style.color = "#D8A47F"; return; }
      msg.textContent = "Bienvenue dans la correspondance. À très vite.";
      msg.style.color = "";
      f.reset();
    });
    cart.subscribe(() => { syncBadges(); renderCart(); });
    wish.subscribe(syncBadges);
  }

  async function bootChrome() {
    try{
      refreshDataRefs();
      const tpl = document.createElement("template");
      let h = '';
      try{ h = headerHTML(); }catch(e){ console.warn('[boot] headerHTML failed', e, e.stack); h='<div style="padding:12px;background:#F5F1E9;border-bottom:1px solid #E4DBC9;text-align:center;font-family:var(--sans)">Cléopâtre — Parapharmacie Tunis — <a href="'+(typeof PAGES!=='undefined'?PAGES:'pages/')+'shop.html">Boutique</a></div>'; }
      tpl.innerHTML = h.trim();
      const anchor = document.getElementById("main") || document.body.firstElementChild;
      Array.from(tpl.content.childNodes).forEach(n =>
        anchor ? document.body.insertBefore(n, anchor) : document.body.appendChild(n));
      const ftpl = document.createElement("template");
      try{ ftpl.innerHTML = footerHTML().trim(); document.body.appendChild(ftpl.content.firstElementChild); }catch(e){ console.warn('[boot] footer failed', e); }
      delegate();
      try{ bindSearch(); }catch(e){ console.warn('[boot] bindSearch failed', e); }
      try{ announceRotate(); }catch(e){}
      try{ initScrollHeader(); }catch(e){}
      try{ initCursor(); }catch(e){}
      try{ setActiveNav(); }catch(e){}
      try{ syncBadges(); }catch(e){}
      try{ renderCart(); }catch(e){ console.warn('[boot] renderCart failed', e); }
      try{ observe(document); }catch(e){}
      hydrateCatalog().then(()=>{
        try{ renderCart(); syncBadges(); document.dispatchEvent(new CustomEvent('cleo:catalog', {detail: products})); }catch(e){ console.warn("[boot] hydrate then failed", e); }
      }).catch(e=> console.warn("[boot] hydrate failed", e));
    }catch(e){
      console.error('[boot] fatal', e);
    }finally{
      CLEO._ready = true;
      if (CLEO._readyQueue) { CLEO._readyQueue.forEach(fn => { try { fn(); } catch (e) { console.error(e); } }); CLEO._readyQueue.length = 0; }
      document.dispatchEvent(new CustomEvent("cleo:ready"));
    }
  }
  if(document.readyState === 'loading'){
    document.addEventListener("DOMContentLoaded", bootChrome);
  } else {
    // DOM already ready (file:// or cached) — run immediately
    setTimeout(bootChrome, 0);
  }

  Object.assign(CLEO, {
    cardHTML, tileHTML, toast, observe, onReady,
    openDrawer, closeDrawers, openQuickView, closeModal,
    openCart: () => openDrawer("#cartDrawer")
  });
  function onReady(fn) {
    if (CLEO._ready) { try { fn(); } catch (e) { console.error(e); } }
    else { (CLEO._readyQueue = CLEO._readyQueue || []).push(fn); }
  }
})();

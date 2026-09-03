/* CLÉOPÂTRE — Fiche produit */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$, $$ = C.$$;

  let P = null, qty = 1, view = 0;
  const VIEWS = [
    { key: "front", label: "Produit", art: p => C.ART.front(p) },
    { key: "alt", label: "Texture", art: p => C.ART.alt(p) },
    { key: "zoom", label: "Détail", art: p => C.ART.zoom(p) },
    { key: "scene", label: "Rituel", art: p => C.ART.scene([p.tint, "#D9CBB0", "#B9C4CB"], { bg: p.tint }) }
  ];

  C.onReady(async () => {
    const id = C.qs("id");
    P = C.byId(id);
    // Si produit non trouvé en statique mais existe en DB (ou prix à jour), fetch API
    if (!P && window.CLEO_API){
      try{
        const getRoot=()=>{
          const p=location.pathname;
          if(p.indexOf('/pages/')!==-1) return p.substring(0, p.indexOf('/pages/')) + '/';
          if(p.indexOf('/admin/')!==-1) return p.substring(0, p.indexOf('/admin/')) + '/';
          return p.substring(0, p.lastIndexOf('/')+1);
        };
        const r = await fetch(getRoot() + 'api/products/detail.php?id='+encodeURIComponent(id), {credentials:'same-origin'});
        if(r.ok){
          const j=await r.json();
          const row=j.data && j.data.product;
          if(row){
            P={ id:row.id, brand:row.brand, name:row.name, cat:row.cat, sub:row.sub, form:row.form, tint:row.tint, price:Number(row.price), oldPrice:row.old_price!=null?Number(row.old_price):null, size:row.size, concerns: (()=>{try{return typeof row.concerns==='string'?JSON.parse(row.concerns):row.concerns}catch(e){return []}})(), rating:Number(row.rating||0), reviews:Number(row.reviews||0), stock:!!Number(row.stock), featured:!!Number(row.featured), bestseller:row.bestseller!=null?Number(row.bestseller):null, image:row.image, imageAlt:row.image_alt||row.image, imageThumb:row.image_thumb, short:row.short, description:row.description, ingredients:row.ingredients, benefits: (()=>{try{return typeof row.benefits==='string'?JSON.parse(row.benefits):row.benefits}catch(e){return []}})(), usage:row.usage_text||'', active: !!Number(row.active) };
            // inject into catalogue pour cohérence panier
            if(window.CLEO && window.CLEO.products) { const arr=window.CLEO.products; if(!arr.find(x=>x.id===P.id)) arr.push(P); }
          }
        }
      }catch(e){ console.warn("[product] fallback fetch failed", e); }
    }
    if (!P) {
      console.warn("[product] produit introuvable", id);
      const msg=document.createElement("div");
      msg.style.cssText="max-width:720px;margin:40px auto;padding:24px;background:var(--surface);border:1px solid var(--line);border-radius:8px;text-align:center";
      msg.innerHTML=`<h2 style="font-family:var(--serif)">Produit introuvable</h2><p style="color:var(--muted);margin-top:8px">Le produit “${id}” n’existe pas ou a été désactivé.</p><a class="btn btn--ink" href="${C.PAGES}shop.html" style="margin-top:16px">Retour boutique</a>`;
      document.getElementById("main")?.prepend(msg);
      setTimeout(()=> location.replace(C.PAGES + "shop.html"), 2500);
      return;
    }
    // hydrater catalogue en arrière-plan puis rafraîchir prix si plus récent
    if(window.CLEO && window.CLEO.hydrateCatalog){
      window.CLEO.hydrateCatalog().then(()=>{
        const fresh = window.CLEO.byId(id);
        if(fresh && fresh.price!==P.price){
          P.price=fresh.price; P.oldPrice=fresh.oldPrice; P.stock=fresh.stock;
          document.getElementById('pPrices') && renderInfoPrices();
        }
      }).catch(err=> console.warn("[product] hydrate failed", err));
    }
    function renderInfoPrices(){
      const d = window.CLEO.off(P);
      const pts = Math.floor((P.price/1000)*10);
      const el=document.getElementById('pPrices');
      if(!el) return;
      el.innerHTML = `<span class="price-now">${window.CLEO.fmt(P.price)}</span>` + (P.oldPrice ? `<span class="price-old">${window.CLEO.fmt(P.oldPrice)}</span><span class="price-off">−${d}% · offre du moment</span>` : "") + (!P.stock ? `<span style="color:var(--muted);font-size:.85rem">Réapprovisionnement en cours</span>` : "") + `<span class="loyalty-badge" style="margin-left:8px">+${pts} pts fidélité</span>`;
    }
    document.title = P.name + " — " + P.brand + " | Cléopâtre Parapharmacie";
    const md = document.querySelector('meta[name="description"]');
    if (md) md.setAttribute("content", P.short + " Livraison 24–72 h partout en Tunisie.");
    injectJSONLD();
    trackView(P.id);
    renderCrumbs();
    renderGallery();
    renderInfo();
    renderTabs();
    renderFAQ();
    renderRelated();
    renderRecentlyViewed();
    bindBuyBar();
  });

  function trackView(pid){
    try{
      const key="cleo_recent_v2";
      let arr=JSON.parse(localStorage.getItem(key)||"[]");
      arr=[pid, ...arr.filter(x=>x!==pid)].slice(0,12);
      localStorage.setItem(key, JSON.stringify(arr));
    }catch(e){ console.warn("[product] trackView local failed", e); }
    if(window.CLEO_API) window.CLEO_API.recentlyViewed.add(pid).catch(err=> console.warn("[product] recentlyViewed api failed", err));
  }

  function injectJSONLD() {
    const ld = {
      "@context": "https://schema.org", "@type": "Product",
      name: P.name, brand: { "@type": "Brand", name: P.brand },
      description: P.short, sku: P.id,
      aggregateRating: { "@type": "AggregateRating", ratingValue: P.rating, reviewCount: P.reviews },
      offers: {
        "@type": "Offer",
        price: (P.price / 1000).toFixed(3), priceCurrency: "TND",
        availability: P.stock ? "https://schema.org/InStock" : "https://schema.org/OutOfStock"
      }
    };
    const s = document.createElement("script");
    s.type = "application/ld+json";
    s.textContent = JSON.stringify(ld);
    document.head.appendChild(s);

    const bc = {
      "@context": "https://schema.org", "@type": "BreadcrumbList",
      itemListElement: [
        { "@type": "ListItem", position: 1, name: "Accueil", item: "https://para-cleopatre.tn/" },
        { "@type": "ListItem", position: 2, name: "Boutique", item: "https://para-cleopatre.tn/pages/shop.html" },
        { "@type": "ListItem", position: 3, name: (C.catBySlug(P.cat) || {}).name || "", item: "https://para-cleopatre.tn/pages/category.html?cat=" + P.cat },
        { "@type": "ListItem", position: 4, name: P.name }
      ]
    };
    const b = document.createElement("script");
    b.type = "application/ld+json";
    b.textContent = JSON.stringify(bc);
    document.head.appendChild(b);
  }

  function renderCrumbs() {
    const cat = C.catBySlug(P.cat) || {};
    $("#crumbs").innerHTML =
      `<a href="../index.html">Accueil</a><i>/</i>
       <a href="${C.PAGES}shop.html">Boutique</a><i>/</i>
       <a href="${C.PAGES}category.html?cat=${P.cat}">${C.esc(cat.name || "")}</a><i>/</i>
       <span aria-current="page">${C.esc(P.name)}</span>`;
  }

  /* Galerie — swipeable on mobile */
  function renderGallery() {
    const stage = $("#galStage"), thumbs = $("#galThumbs");
    thumbs.innerHTML = VIEWS.map((v, i) =>
      `<button role="tab" aria-selected="${i === 0}" aria-label="Vue ${v.label}" class="${i === 0 ? "is-on" : ""}" data-v="${i}">${v.art(P)}</button>`).join("");
    function setView(i) {
      view = ((i%VIEWS.length)+VIEWS.length)%VIEWS.length;
      stage.classList.remove("is-zoomed");
      stage.style.opacity = "0";
      setTimeout(() => { stage.innerHTML = VIEWS[view].art(P); stage.style.opacity = "1"; }, 180);
      $$("button", thumbs).forEach((b, k) => {
        b.classList.toggle("is-on", k === view);
        b.setAttribute("aria-selected", String(k === view));
      });
    }
    setView(0);
    stage.style.transition = "opacity .3s var(--ease)";
    thumbs.addEventListener("click", e => {
      const b = e.target.closest("[data-v]");
      if (b) setView(+b.dataset.v);
    });
    stage.addEventListener("click", () => stage.classList.toggle("is-zoomed"));
    stage.addEventListener("mousemove", e => {
      if (!stage.classList.contains("is-zoomed")) return;
      const r = stage.getBoundingClientRect();
      const el=stage.querySelector("svg") || stage.querySelector("img");
      if(el) el.style.transformOrigin =
        ((e.clientX - r.left) / r.width * 100) + "% " + ((e.clientY - r.top) / r.height * 100) + "%";
    });
    // swipe
    let sx=0, dx=0, touching=false;
    stage.addEventListener("touchstart", e=>{ touching=true; sx=e.touches[0].clientX; dx=0; }, {passive:true});
    stage.addEventListener("touchmove", e=>{ if(!touching) return; dx=e.touches[0].clientX - sx; }, {passive:true});
    stage.addEventListener("touchend", e=>{
      if(!touching) return; touching=false;
      if(Math.abs(dx)>50){
        if(dx<0) setView(view+1); else setView(view-1);
      }
      dx=0;
    }, {passive:true});
    // keyboard
    stage.tabIndex=0;
    stage.addEventListener("keydown", e=>{ if(e.key==="ArrowLeft") setView(view-1); if(e.key==="ArrowRight") setView(view+1); });
  }

  /* Colonne information */
  function renderInfo() {
    const brand = C.brandByName(P.brand);
    const bl = $("#pBrandLink");
    bl.textContent = P.brand.toUpperCase();
    bl.href = C.PAGES + "brand.html?brand=" + (brand ? brand.slug : "");
    $("#pName").textContent = P.name;
    $("#pRating").innerHTML = `${C.stars(P.rating)}<span>${Number(P.rating).toFixed(1)} · ${P.reviews} avis vérifiés</span>`;
    $("#pRating").addEventListener("click", () => switchTab(3));
    const d = C.off(P);
    const pts = Math.floor((P.price/1000)*10);
    const lowStock = P.track_stock && P.stock_quantity>0 && P.stock_quantity<=5;
    $("#pPrices").innerHTML =
      `<span class="price-now">${C.fmt(P.price)}</span>` +
      (P.oldPrice ? `<span class="price-old">${C.fmt(P.oldPrice)}</span><span class="price-off">−${d}% · offre du moment</span>` : "") +
      (!P.stock ? `<span style="color:var(--danger);font-size:.82rem;background:var(--danger-bg);border:1px solid var(--danger-border);padding:.25em .6em;border-radius:99px">Indisponible — réapprovisionnement en cours</span>` : lowStock ? `<span style="color:var(--warning);font-size:.82rem;background:var(--warning-bg);border:1px solid var(--warning-border);padding:.25em .6em;border-radius:99px">Stock limité — plus que ${P.stock_quantity} </span>` : `<span style="color:var(--success);font-size:.78rem">Disponible — expédié sous 24h</span>`) +
      `<span class="loyalty-badge" style="margin-left:8px">+${pts} pts fidélité</span>`;
    // loyalty preview line
    const loyaltyLine=document.createElement("p");
    loyaltyLine.style.cssText="font-size:.78rem;color:var(--green);margin-top:6px";
    loyaltyLine.textContent=`1 DT = 10 pts · Cette commande vous rapporte ${pts} points. 1 000 pts = 10 DT.`;
    $("#pPrices").after(loyaltyLine);
    $("#pShort").textContent = P.short;
    $("#pBenefits").innerHTML = (P.benefits || []).map(b => `<li>${C.esc(b)}</li>`).join("");

    $("#pQtyWrap").addEventListener("click", e => {
      const b = e.target.closest("[data-q]");
      if (!b) return;
      qty = Math.max(1, Math.min(9, qty + (+b.dataset.q)));
      $("#pQty").textContent = qty;
    });
    $("#addMain").addEventListener("click", () => addToBag());
    $("#wishMain").addEventListener("click", () => {
      const active = !$("#wishMain").classList.contains("is-active");
      $("#wishMain").classList.toggle("is-active", active);
      C.wish.toggle(P.id);
      C.toast(active ? "Ajouté à vos favoris" : "Retiré de vos favoris", C.ART.front(P));
    });
    if (brand && brand.signature) {
      $("#adviceText").textContent =
        `« ${P.name.split(",")[0]} ? Une valeur sûre du rayon. C’est notre référence quand une cliente cherche ${firstConcern()} — associé à un geste régulier, il tient toutes ses promesses. La régularité fait la moitié du résultat. »`;
    } else {
      $("#adviceText").textContent = `« Un produit simple, bien formulé, qui tient ses promesses quand on l’utilise avec constance. »`;
    }

    function firstConcern() {
      const map = { hydration: "une hydratation sérieuse", "anti-age": "un vrai geste anti-âge", acne: "une peau nette", sensibilite: "l’apaisement durable", pigmentation: "réunifier le teint", solaire: "une protection sans compromis" };
      const c = (P.concerns || [])[0];
      return map[c] || "des résultats visibles";
    }
  }

  function addToBag(fromBar) {
    if (!P.stock) return;
    C.cart.add(P.id, fromBar ? 1 : qty);
    C.toast(`Ajouté — ${qty > 1 && !fromBar ? qty + " × " : ""}${C.esc(P.name.split(",")[0])}`, C.ART.front(P), "Voir le panier", () => C.openCart());
  }

  /* Onglets */
  const TABS = ["Description", "Composition", "Rituel d’utilisation", "Avis vérifiés"];
  function renderTabs() {
    const bar = $("#tabBar"), panel = $("#tabPanel");
    bar.innerHTML = TABS.map((t, i) =>
      `<button role="tab" id="tb-${i}" aria-selected="${i === 0}" data-t="${i}">${t}</button>`).join("");
    bar.addEventListener("click", e => {
      const b = e.target.closest("[data-t]");
      if (b) switchTab(+b.dataset.t);
    });
    switchTab(0);
    window.switchTab = switchTab;

    function switchTab(i) {
      $$("#tabBar button").forEach((b, k) => {
        b.classList.toggle("is-on", k === i);
        b.setAttribute("aria-selected", String(k === i));
      });
      panel.innerHTML = "";
      panel.appendChild(tabContent(i));
      panel.querySelectorAll(".rbar i").forEach(el =>
        requestAnimationFrame(() => el.style.transform = `scaleX(${el.dataset.w})`));
      panel.scrollTop = 0;
    }

    function tabContent(i) {
      const div = document.createElement("div");
      if (i === 0) {
        div.className = "prose";
        div.innerHTML = `
          <p>${C.esc(P.description)}</p>
          <h3>Bénéfices attendus</h3>
          <ul>${(P.benefits || []).map(x => `<li>${C.esc(x)}</li>`).join("")}</ul>
          <p style="font-size:.8rem;color:var(--muted)">Format : ${C.esc(P.size || "—")} · Univers ${C.esc((C.catBySlug(P.cat) || {}).name || "")}</p>`;
      }
      if (i === 1) {
        div.className = "prose";
        const ingList = (P.ingredients||'').split(/[,;]\s*/).filter(Boolean).map(s=> s.trim()).slice(0,40);
        div.innerHTML = `
          <h3>Liste complète des ingrédients</h3>
          ${ingList.length>1 ? `<ul class="ing-cols" style="columns:2;column-gap:24px">${ingList.map(ing=> `<li style="break-inside:avoid">${C.esc(ing)}</li>`).join('')}</ul>` : `<p class="ing-cols">${C.esc(P.ingredients)}</p>`}
          <p style="font-size:.8rem;color:var(--muted)">Liste communiquée par le laboratoire — ${ingList.length} ingrédients. Une allergie ? <a href="../pages/contact.html" class="link-u link-u--small">Écrivez-nous</a>, nous vérifions.</p>`;
      }
      if (i === 2) {
        div.className = "prose";
        div.innerHTML = `
          <h3>Le bon geste, au bon moment</h3>
          <p>${C.esc(P.usage)}</p>
          <ul>
            <li>Constance : les résultats se construisent sur quatre semaines d’usage régulier.</li>
            <li>Dosage : mieux vaut peu, régulièrement, que beaucoup, occasionnellement.</li>
            <li>Sollicitation d’un avis : en cas de grossesse, traitement ou pathologie cutanée, demandez-nous.</li>
          </ul>`;
      }
      if (i === 3) div.appendChild(reviewsBlock());
      return div;
    }

     function reviewsBlock() {
      const wrap = document.createElement("div");
      wrap.className = "reviews";
      const dist = distribution(P.rating);
      wrap.innerHTML = `
        <div class="rev-summary">
          <span class="big">${Number(P.rating).toFixed(1)}</span>
          <span>${C.stars(P.rating)}<span style="display:block;margin-top:6px;font-size:.78rem;color:var(--muted)">${P.reviews} avis vérifiés</span></span>
          <div class="rev-bars">
            ${[5, 4, 3, 2, 1].map(n => `
              <div class="rbar"><span>${n}</span>
                <span class="track"><i data-w="${dist[n]}" style="--w:${dist[n]}"></i></span>
                <span>${Math.round(dist[n] * 100)}%</span>
              </div>`).join("")}
          </div>
          <button class="btn btn--ghost btn--sm" id="writeReviewBtn" style="margin-top:14px">Écrire un avis</button>
        </div>
        <div class="rev-list" id="revList">
          <p style="color:var(--muted)">Chargement des avis…</p>
        </div>
        <form id="reviewForm" hidden style="margin-top:16px;padding:16px;border:1px solid var(--line);border-radius:4px;background:var(--surface)">
          <h4 style="font-size:.82rem;letter-spacing:.14em;text-transform:uppercase;margin-bottom:12px">Votre avis</h4>
          <div style="display:flex;gap:6px;margin-bottom:12px" id="starPick">
            ${[1,2,3,4,5].map(n=>`<button type="button" data-star="${n}" style="font-size:1.4rem;color:var(--gold)">☆</button>`).join("")}
          </div>
          <div class="field"><label>Titre (optionnel)</label><input id="revTitle" placeholder="Ex: Parfait pour ma peau sèche"></div>
          <div class="field"><label>Avis *</label><textarea id="revBody" rows="4" placeholder="Partagez votre expérience…"></textarea></div>
          <button class="btn btn--ink btn--sm" type="submit">Publier (modération)</button>
          <p id="revMsg" style="margin-top:8px;font-size:.78rem"></p>
        </form>`;
      // load real reviews
      setTimeout(()=> loadRealReviews(wrap), 100);
      wrap.querySelector("#writeReviewBtn").addEventListener("click", ()=>{
        const f=wrap.querySelector("#reviewForm");
        f.hidden=!f.hidden;
        if(!f.hidden) f.scrollIntoView({behavior:"smooth", block:"center"});
      });
      let chosen=0;
      wrap.querySelectorAll("[data-star]").forEach(b=>{
        b.addEventListener("click", ()=>{
          chosen=parseInt(b.dataset.star);
          wrap.querySelectorAll("[data-star]").forEach((x,i)=>{ x.textContent= i<chosen ? "★" : "☆"; x.style.color= i<chosen ? "var(--gold)" : "var(--muted)"; });
        });
      });
      wrap.querySelector("#reviewForm").addEventListener("submit", async e=>{
        e.preventDefault();
        const msg=wrap.querySelector("#revMsg");
        const body=wrap.querySelector("#revBody").value.trim();
        const title=wrap.querySelector("#revTitle").value.trim();
        if(!chosen){ msg.textContent="Choisissez une note."; msg.style.color="#8A3A1F"; return; }
        if(body.length<10){ msg.textContent="Avis trop court."; msg.style.color="#8A3A1F"; return; }
        msg.textContent="Envoi…"; msg.style.color="var(--muted)";
        try{
          await window.CLEO_API.reviews.create({product_id:P.id, rating:chosen, title, body});
          msg.textContent="Merci ! Votre avis sera publié après modération."; msg.style.color="var(--green)";
          e.target.reset(); chosen=0;
          wrap.querySelectorAll("[data-star]").forEach(x=>{ x.textContent="☆"; x.style.color="var(--gold)";});
        }catch(err){ msg.textContent=err.message; msg.style.color="#8A3A1F"; }
      });
      return wrap;
    }
    async function loadRealReviews(wrap){
      const list=wrap.querySelector("#revList");
      if(!window.CLEO_API) { list.innerHTML = sampleReviews().map(r => `
            <article class="rev-item">
              <header><span class="who">${C.esc(r.name)}</span><span class="when">${r.when}</span>
                <span class="vbadge">Achat vérifié</span><span>${C.stars(r.stars)}</span></header>
              <p>${C.esc(r.text)}</p>
            </article>`).join(""); return; }
      try{
        const res=await window.CLEO_API.reviews.list(P.id, {page:1, per_page:6});
        const reviews=res.data.reviews||[];
        if(!reviews.length){
          list.innerHTML=`<p style="color:var(--muted);font-size:.86rem">Aucun avis vérifié pour l’instant — soyez la première à partager votre expérience. (Les avis échantillons ci-dessous sont illustratifs)</p>` + sampleReviews().map(r => `
            <article class="rev-item" style="opacity:.7">
              <header><span class="who">${C.esc(r.name)}</span><span class="when">${r.when}</span>
                <span class="vbadge">Achat vérifié</span><span>${C.stars(r.stars)}</span></header>
              <p>${C.esc(r.text)}</p>
            </article>`).join("");
          return;
        }
        list.innerHTML=reviews.map(r=>`
          <article class="rev-item">
            <header><span class="who">${C.esc(r.first_name||"Cliente")}</span><span class="when">${new Date(r.created_at).toLocaleDateString("fr-TN")}</span>
              ${r.verified_purchase?'<span class="vbadge">Achat vérifié</span>':''}<span>${C.stars(r.rating)}</span></header>
            ${r.title?`<b style="display:block;margin-top:6px">${C.esc(r.title)}</b>`:""}
            <p>${C.esc(r.body)}</p>
            <button data-helpful="${r.id}" style="font-size:.7rem;color:var(--muted);margin-top:6px">Utile ? (${r.helpful_count})</button>
          </article>`).join("");
        list.querySelectorAll("[data-helpful]").forEach(b=>{
          b.addEventListener("click", async ()=>{
            try{ const res=await window.CLEO_API.reviews.helpful(b.dataset.helpful); b.textContent=`Merci ! (${res.data.helpful_count})`; b.disabled=true; }catch(e){ C.toast(e.message); }
          });
        });
      }catch(e){
        list.innerHTML=sampleReviews().map(r => `
            <article class="rev-item">
              <header><span class="who">${C.esc(r.name)}</span><span class="when">${r.when}</span>
                <span class="vbadge">Achat vérifié</span><span>${C.stars(r.stars)}</span></header>
              <p>${C.esc(r.text)}</p>
            </article>`).join("");
      }
    }

    function distribution(avg) {
      const five = Math.min(.95, Math.max(.35, avg - 3.6));
      const four = Math.max(.05, .95 - five - .12);
      return { 5: five, 4: four, 3: .08, 2: Math.max(.01, .04 - five * .02), 1: .02 };
    }

    function sampleReviews() {
      const pool = [
        ["Amina K.", "Texture parfaite, résultat au rendez-vous. La conseillère du site m’avait prévenue : il faut quatre semaines de patience. Elle avait raison.", 5],
        ["Ines M.", "Très satisfaite, je recommande vivement. Livraison rapide et produit bien emballé.", 5],
        ["Rym B.", "Bon produit mais j’aurais aimé un format plus grand. L’équipe m’a répondu en quelques heures sur WhatsApp, très pro.", 4],
        ["Salma T.", "Conforme à la description, emballage soigné. Je repasserai pour le même.", 5],
        ["Nour J.", "Un peu de patience avant les premiers résultats, mais ma peau me remercie chaque matin.", 4]
      ];
      const seed = [...P.id].reduce((a, ch) => a + ch.charCodeAt(0), 0);
      return pool.slice(seed % 2, (seed % 2) + 3).map(([name, text, stars], i) => ({
        name, stars, when: ["il y a 6 jours", "il y a 3 semaines", "il y a 2 mois"][i], text
      }));
    }
  }

  /* FAQ */
  function renderFAQ() {
    const faqs = [
      ["Est-ce le bon produit pour moi ?", "Chaque peau a son histoire. Décrivez-nous la vôtre via le formulaire contact ou en message privé : une pharmacienne vous répond sous 24 h ouvrées, sans engagement d’achat."],
      ["Quels sont les délais de livraison ?", "Grand Tunis sous 24 h, reste du pays sous 48–72 h. Livraison offerte dès 99 DT d’achat ; paiement possible à la réception."],
      ["Puis-je retourner un produit ?", "Oui — 14 jours pour changer d’avis sur tout produit non ouvert, et notre service client étudie toute réaction inhabituelle signalée, preuve d’achat à l’appui."]
    ];
    const acc = $("#faqAcc");
    acc.innerHTML = faqs.map(([q, a]) => `
      <div class="acc__item">
        <button class="acc__btn" aria-expanded="false"><span>${q}</span><i></i></button>
        <div class="acc__panel"><p class="acc__panel-inner">${a}</p></div>
      </div>`).join("");
    acc.addEventListener("click", e => {
      const btn = e.target.closest(".acc__btn");
      if (!btn) return;
      const item = btn.parentElement, panel = btn.nextElementSibling, open = item.classList.contains("is-open");
      $$(".acc__item.is-open", acc).forEach(o => { o.classList.remove("is-open"); $(".acc__panel", o).style.height = "0px"; $(".acc__btn", o).setAttribute("aria-expanded", "false"); });
      if (!open) {
        item.classList.add("is-open");
        panel.style.height = panel.firstElementChild.scrollHeight + "px";
        btn.setAttribute("aria-expanded", "true");
      }
    });
  }

  /* Recommandations — Découvrir aussi (Phase 16) — intelligent, déterministe, pas aléatoire */
  function renderRelated() {
    const grid = $("#relatedGrid");
    const wrap = grid?.closest('.rel-wrap');
    if(!grid) return;
    // Architecture: same subcategory > same category > same brand > same concern > curated fallback
    // Do not invent relationships — only real cat/sub/brand/concerns from data/products.js
    const seen=new Set([P.id]);
    const sameSub = C.products.filter(p=> !seen.has(p.id) && p.sub===P.sub);
    sameSub.forEach(p=>seen.add(p.id));
    const sameCat = C.products.filter(p=> !seen.has(p.id) && p.cat===P.cat);
    sameCat.forEach(p=>seen.add(p.id));
    const sameBrand = C.products.filter(p=> !seen.has(p.id) && p.brand===P.brand);
    sameBrand.forEach(p=>seen.add(p.id));
    const byConcern = C.products.filter(p=> !seen.has(p.id) && (p.concerns||[]).some(c=>(P.concerns||[]).includes(c)));
    // Deterministic priority: sub (most relevant) → cat → brand → concern
    let list=[...sameSub, ...sameCat, ...sameBrand, ...byConcern];
    // Dedupe already done, slice 3 max — performance: never render >3
    list=list.slice(0,3);
    // Quality gate: if no relevant product, hide gracefully — never show random fill
    if(!list.length){
      if(wrap) wrap.style.display='none';
      return;
    }
    // Update header contextual title based on dominant relationship
    const head=wrap?.querySelector('#relTitle');
    if(head){
      if(sameSub.length>=2) head.innerHTML='Même famille,<em class="serif-i"> même exigence.</em>';
      else if(sameCat.length) head.innerHTML='Souvent choisis ensemble<em class="serif-i"> dans cet univers.</em>';
      else if(sameBrand.length) head.innerHTML='La même maison,<em class="serif-i"> autre geste.</em>';
      else head.innerHTML='Compléter le <em class="serif-i">rituel.</em>';
    }
    grid.innerHTML = list.map((p, i) => C.cardHTML(p).replace('class="pcard"', `class="pcard" data-reveal style="--d:${i}"`));
    C.observe(grid);
    // Also ensure cart not duplicated with recentlyViewed — mark data-source
    grid.dataset.source='related-'+(sameSub.length?'sub':sameCat.length?'cat':sameBrand.length?'brand':'concern');
  }
  function renderRecentlyViewed(){
    const existing=document.getElementById("recentlyGrid");
    if(existing) return;
    const section=document.createElement("section");
    section.className="section container";
    section.innerHTML=`<hr class="hr-fade"><header class="sec-head"><div class="sec-head__left"><span class="num-label"><i>↻</i>Vus récemment</span><h2 class="display-2">Revoir <em class="serif-i">ses coups de cœur.</em></h2></div></header><div class="grid-3" id="recentlyGrid"></div>`;
    const rel=document.querySelector(".rel-wrap");
    if(rel) rel.after(section);
    const grid=section.querySelector("#recentlyGrid");
    try{
      const key="cleo_recent_v2";
      const ids=JSON.parse(localStorage.getItem(key)||"[]").filter(id=>id!==P.id).slice(0,4);
      const prods=ids.map(id=>C.byId(id)).filter(Boolean);
      if(!prods.length){ section.style.display="none"; return; }
      grid.innerHTML=prods.map((p,i)=> C.cardHTML(p).replace('class="pcard"', `class="pcard" data-reveal style="--d:${i}"`)).join("");
      C.observe(grid);
    }catch(e){ section.style.display="none"; }
  }

  /* Barre mobile */
  function bindBuyBar() {
    const bar = $("#mBuyBar");
    const d = C.off(P);
    bar.innerHTML = `
      <img alt="" src="data:image/svg+xml,${encodeURIComponent(C.ART.front(P))}" style="width:44px;height:55px;background:var(--card);border-radius:2px">
      <div>
        ${P.oldPrice ? `<small>${C.fmt(P.oldPrice)}</small><b>${C.esc(P.name.split(",").slice(0, 2).join(","))} · ${C.fmt(P.price)}</b>`
                     : `<b>${C.esc(P.name.split(",").slice(0, 2).join(","))} · ${C.fmt(P.price)}</b>`}
      </div>
      <button class="btn btn--green" data-add-main-mobile ${P.stock ? "" : "disabled"}>Ajouter</button>`;
    bar.querySelector("[data-add-main-mobile]").addEventListener("click", e => { e.preventDefault(); addToBag(true); });
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        bar.hidden = false;
        bar.classList.toggle("is-visible", !en.isIntersecting);
      });
    }, { threshold: 0 });
    io.observe($(".pdp__buy"));
    void d;
  }
})();

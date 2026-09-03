/* CLÉOPÂTRE — Page d’accueil */
(function () {
  "use strict";
  const C = window.CLEO;

  C.onReady(() => {
    try{
      renderHero();
      renderUnivers();
      renderBeautyVideos();
      renderBrands();
      renderRail();
      renderCampaign();
      renderNeeds();
      renderPourVous();
      renderRituel();
      renderPromos();
      startCountdown();
      renderJournal();
      renderTrust();
      counters();
      heroParallax();
    }catch(e){ console.warn('C.onReady', e.message); }
    // re-render après hydratation catalogue
    document.addEventListener('cleo:catalog', ()=> { try{ renderPourVous(); renderRituel(); renderRail(); renderPromos(); }catch(e){} });
    if(C.wish && C.wish.subscribe) C.wish.subscribe(()=> { try{ renderPourVous(); }catch(e){} });
  });

  /* 00 — Nature morte du héros */
  function renderHero() {
    const art = document.getElementById("heroArt");
    if (art) art.innerHTML = C.ART.scene(["#E9E2D2", "#DFE5DA", "#D9CBB0"], { bg: "#EAE3D6" });
  }

  /* 01 — Univers — images éditoriales avec breathing room à gauche */
  function renderUnivers() {
    const grid = document.getElementById("uniGrid");
    if (!grid) return;
    const sizes = ["xl", "lg", "lg", "sm", "sm", "sm"];
    const pos = {
      "visage": "60% center",
      "corps": "55% center",
      "cheveux": "62% 30%",
      "bebe": "48% 35%",
      "sante": "52% center",
      "bien-etre": "58% center"
    };
    grid.innerHTML = C.cats.map((c, i) => {
      const img = c.image || `assets/images/univers/${c.slug}.webp`;
      const src = (C.ROOT || "") + img;
      const alt = `Univers ${c.name}`;
      const p = pos[c.slug] || "50% center";
      return `
      <a class="uni-tile uni-tile--${sizes[i]}" href="${C.PAGES}category.html?cat=${c.slug}" data-reveal style="--d:${i}">
        <span class="art" aria-hidden="true">
          <img src="${src}" alt="${C.esc(alt)}" loading="${i<2 ? "eager" : "lazy"}" decoding="async" style="object-position:${p}" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
          <span style="display:none;width:100%;height:100%">${C.ART.scene([c.surface, "#D9CBB0", c.accent], { bg: c.surface })}</span>
        </span>
        <span class="uni-num">${String(i + 1).padStart(2, "0")}</span>
        <span class="uni-body">
          <h3>${c.name}</h3>
          <span class="uni-tag">${c.tagline}</span>
          <span class="uni-go">Explorer<span class="arr">→</span></span>
        </span>
      </a>`;
    }).join("");
    C.observe(grid);
  }

  /* 01b — Vidéos éditoriales — Regardez. Craquez. Achetez. */
  function renderBeautyVideos() {
    const grid = document.getElementById("beautyGrid");
    if (!grid) return;
    const fmt = m => (m/1000).toFixed(3).replace(".",",")+" DT";
    const beautyVideos = [
      {
        video: "assets/videos/beauty/hyaluro-sun-family.mp4",
        productImage: "assets/products/svr-blur.webp",
        productName: "SVR SUN SECURE BLUR",
        details: "SPF50+",
        price: 61400,
        oldPrice: 72690,
        url: "pages/product.html?id=svr-sun-secure-blur",
        objectPosition: "50% center"
      },
      {
        video: "assets/videos/beauty/avene-beach-chill.mp4",
        productImage: "assets/products/avene-eau.webp",
        productName: "AVÈNE EAU THERMALE",
        details: "150ML LOT DE 2",
        price: 45600,
        oldPrice: 60200,
        url: "pages/product.html?id=avene-eau-thermale-300",
        objectPosition: "55% center"
      },
      {
        video: "assets/videos/beauty/nuxe-sun-stick-a.mp4",
        productImage: "assets/products/nuxe-huile-or.webp",
        productName: "NUXE STICK SOLAIRE",
        details: "SPF50+",
        price: 75195,
        oldPrice: 83538,
        url: "pages/product.html?id=nuxe-huile-prodigieuse-or",
        objectPosition: "45% center"
      },
      {
        video: "assets/videos/beauty/nuxe-hair-prodigieux.mp4",
        productImage: "assets/products/nuxe-hair-serum.webp",
        productName: "NUXE HAIR PRODIGIEUX",
        details: "SÉRUM 50ML",
        price: 118000,
        oldPrice: 137700,
        url: "pages/product.html?id=nuxe-hair-prodigieux-serum",
        objectPosition: "50% center"
      }
    ];
    grid.innerHTML = beautyVideos.map(v => `
      <a class="beauty-card" href="${C.ROOT}${v.url}" aria-label="${C.esc(v.productName)}">
        <video src="${C.ROOT}${v.video}" poster="${C.ROOT}${v.productImage}" autoplay muted loop playsinline preload="metadata" style="object-position:${v.objectPosition}" aria-hidden="true"></video>
        <span class="beauty-card__overlay">
          <span class="beauty-card__product">
            <img class="beauty-card__thumb" src="${C.ROOT}${v.productImage}" alt="" loading="lazy" decoding="async">
            <span class="beauty-card__info">
              <span class="beauty-card__name">${C.esc(v.productName)}</span>
              ${v.details ? `<span class="beauty-card__details">${C.esc(v.details)}</span>` : ""}
              <span class="beauty-card__prices">
                <span class="beauty-card__price">${fmt(v.price)}</span>
                ${v.oldPrice ? `<span class="beauty-card__old">${fmt(v.oldPrice)}</span>` : ""}
              </span>
            </span>
          </span>
        </span>
      </a>
    `).join("");
    const videos = grid.querySelectorAll("video");
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        const vid = en.target;
        if (en.isIntersecting) { try{ vid.play()?.catch(()=>{}); }catch(e){} }
        else try{ vid.pause(); }catch(e){}
      });
    }, { threshold: 0.3 });
    videos.forEach(v => {
      v.controls = false;
      v.muted = true;
      v.playsInline = true;
      v.loop = true;
      v.autoplay = true;
      try{ v.play()?.catch(()=>{}); }catch(e){}
      io.observe(v);
    });
    C.observe(grid);
  }

  /* 02 — Marques */
  function renderBrands() {
    const mq = document.querySelector("[data-marquee]");
    if (mq) {
      const names = C.brands.map(b => `<span>${b.name}</span>`).join("");
      mq.innerHTML = `<div class="marquee__track">${names}${names}</div>`;
    }
    const wrap = document.getElementById("brandCards");
    if (wrap) {
      const feat = C.brands.filter(b => b.featured).slice(0, 4);
      wrap.innerHTML = feat.map((b, i) => `
        <a class="bcard" href="${C.PAGES}brand.html?brand=${b.slug}" data-reveal style="--d:${i + 1}">
          <div class="bcard__art">${C.ART.monogram(b.letter, b.tint)}</div>
          <small>${b.country} · depuis ${b.est}</small>
          <h3>${b.name}</h3>
          <p>${b.tagline}</p>
          <span class="link-u">La maison<span class="arr">→</span></span>
        </a>`).join("");
      C.observe(wrap);
    }
  }

  /* 03 — Rail best-sellers (rebuilt) */
  function renderRail() {
    const rail = document.getElementById("bestRail");
    if (!rail) return;
    const best = C.products.filter(p => p.bestseller || p.featured)
      .sort((a, b) => (a.bestseller || 99) - (b.bestseller || 99)).slice(0, 8);
    rail.innerHTML = best.map(p => C.cardHTML(p)).join("");
    const cards = rail.querySelectorAll(".pcard");
    cards.forEach((card, i) => setTimeout(() => card.classList.add("is-in"), 90 + i * 85));
    const step = () => (rail.firstElementChild ? rail.firstElementChild.getBoundingClientRect().width + 28 : 300);
    const prev = document.querySelector("[data-rail-prev]"), next = document.querySelector("[data-rail-next]");
    prev && prev.addEventListener("click", () => rail.scrollBy({ left: -step() * 2, behavior: "smooth" }));
    next && next.addEventListener("click", () => rail.scrollBy({ left: step() * 2, behavior: "smooth" }));
  }

  /* 04 — Campagne */
  function renderCampaign() {
    const art = document.getElementById("campArt");
    if (art) art.innerHTML = C.ART.scene(["#EFE0BE", "#EAD9A6", "#F2E7C9"], { bg: "#3A4632" });
    const mini = document.getElementById("campMini");
    const p = C.byId("la-roche-posay-anthelios-uvmune400");
    if (mini && p) mini.innerHTML = `
      <div class="art">${C.ART.front(p)}</div>
      <div><small>Le geste n°1</small><b>${C.esc(p.name.split(",")[0])}</b></div>`;
  }

  /* 05 — Sélecteur de besoin */
  function renderNeeds() {
    const chips = document.getElementById("needChips");
    const panel = document.getElementById("needPanel");
    const line = document.getElementById("needLine");
    if (!chips || !panel) return;
    chips.innerHTML = C.concerns.map((c, i) =>
      `<button class="chip" role="tab" aria-selected="${i === 0}" id="tab-${c.slug}" aria-controls="needPanel" data-need="${c.slug}">${c.name}</button>`).join("");
    function select(slug) {
      const con = C.concernBySlug(slug); if (!con) return;
      chips.querySelectorAll(".chip").forEach(ch => ch.setAttribute("aria-selected", String(ch.dataset.need === slug)));
      line.textContent = "« " + con.line + " »";
      const prods = con.productIds.map(C.byId).filter(Boolean).slice(0, 3);
      panel.style.opacity = "0";
      setTimeout(() => {
        panel.innerHTML = prods.map(p => C.cardHTML(p)).join("") ||
          `<p style="grid-column:1/-1;color:var(--muted)">Sélection en cours de composition — écrivez-nous, nous la complétons pour vous.</p>`;
        panel.style.transition = "opacity .5s var(--ease)";
        panel.style.opacity = "1";
      }, 160);
    }
    chips.addEventListener("click", e => {
      const b = e.target.closest("[data-need]");
      b && select(b.dataset.need);
    });
    select(C.concerns[0].slug);
  }

  /* 05b — Pour vous / Recently Viewed / Complétez votre rituel (Phase 05) */
  function renderPourVous(){
    const sec=document.getElementById('pourVousSec');
    const rail=document.getElementById('pourVousRail');
    const label=document.getElementById('pourVousLabel');
    const title=document.getElementById('pourvous-title');
    const lead=document.getElementById('pourVousLead');
    const link=document.getElementById('pourVousLink');
    const empty=document.getElementById('pourVousEmpty');
    if(!sec||!rail) return;

    // Real data only: wishlist + recentlyViewed (cleo_recent_v2 12 ids) + recentlyViewed API fallback via localStorage
    let wishIds=[];
    let recentIds=[];
    try{ wishIds=(C.wish && C.wish.items||[]).slice(0,8).map(x=> typeof x==='string'?x:x.id); }catch(e){}
    try{ recentIds=JSON.parse(localStorage.getItem('cleo_recent_v2')||'[]'); }catch(e){}
    // fallback cleo_recent_v1 search history not needed

    let source='curated';
    let titleText='Votre sélection <em class="serif-i">Cléopâtre.</em>';
    let leadText='Reprenons là où vous vous êtes arrêtée — vos coups de cœur, vos dernières visites.';
    let labelText='Pour vous';
    let linkHref='pages/wishlist.html';
    let linkText='Voir ma sélection<span class="arr">→</span>';
    let ids=[];

    if(wishIds.length>=2){
      source='wishlist';
      labelText='Votre sélection';
      titleText='Vos coups de cœur,<em class="serif-i"> prêts à vous retrouver.</em>';
      leadText='Vous les avez aimés — ils vous attendent. Complétez votre rituel sans les chercher.';
      linkHref='pages/wishlist.html';
      ids=wishIds;
    } else if(recentIds.length>=2){
      source='recent';
      labelText='Repris récemment';
      titleText='Revoir vos <em class="serif-i">dernières visites.</em>';
      leadText='Votre parcours, repris exactement là où vous l’aviez laissé.';
      linkHref='pages/shop.html';
      linkText='Continuer l’exploration<span class="arr">→</span>';
      ids=recentIds;
    } else if(wishIds.length===1 && recentIds.length>=1){
      // mix 1 wish + recents
      source='mix';
      ids=[...wishIds, ...recentIds.filter(x=>!wishIds.includes(x))];
    } else {
      // Graceful curated fallback — no fake personalization
      // Use real relationships: same category as most recent or bestsellers if no history
      const fallbackCat = recentIds.length ? (C.byId(recentIds[0])||{}).cat : null;
      if(fallbackCat){
        labelText='À découvrir';
        titleText='Dans le même univers,<em class="serif-i"> à découvrir.</em>';
        leadText='Parce que vous avez exploré '+ (C.catBySlug(fallbackCat)?.name||'nos soins') +' — voici la suite logique, choisie au comptoir.';
        linkHref='pages/category.html?cat='+fallbackCat;
        linkText='Explorer l’univers<span class="arr">→</span>';
        ids=C.products.filter(p=>p.cat===fallbackCat).slice(0,6).map(p=>p.id);
      } else {
        labelText='À découvrir';
        titleText='Complétez votre <em class="serif-i">rituel.</em>';
        leadText='Une sélection courte, pensée comme une consultation — pas de hasard, seulement du conseil.';
        linkHref='pages/shop.html';
        linkText='Explorer la boutique<span class="arr">→</span>';
        // curated: featured + bestsellers + hydration concern
        ids=C.products.filter(p=>p.featured||p.bestseller).slice(0,4).map(p=>p.id).concat(
          (C.concernBySlug('hydration')?.productIds||[]).slice(0,2)
        );
      }
      source='curated';
    }

    // Unique, existing products only, max 8
    ids=[...new Set(ids)].map(id=>C.byId(id)).filter(Boolean).slice(0,8).map(p=>p.id);
    if(!ids.length){
      sec.hidden=true;
      return;
    }
    sec.hidden=false;
    if(label) label.textContent=labelText;
    if(title) title.innerHTML=titleText;
    if(lead) lead.textContent=leadText;
    if(link){ link.href=C.ROOT?C.ROOT+linkHref:linkHref; link.innerHTML=linkText; }

    const prods=ids.map(id=>C.byId(id)).filter(Boolean);
    rail.innerHTML=prods.map(p=>C.cardHTML(p)).join('');
    rail.querySelectorAll('.pcard').forEach((card,i)=> setTimeout(()=>card.classList.add('is-in'), 60+i*70));
    // subtle rail nav via scroll
    C.observe(sec);
    // Empty state never forced — graceful curated already handles new visitor
    if(empty) empty.hidden=true;
  }

  /* 07c — Mon Rituel — Routine Builder Premium · Real implementation + Repair (Phase 1) */
  function renderRituel(){
    const uWrap=document.getElementById('rituelUnivers');
    const bWrap=document.getElementById('rituelBesoin');
    const result=document.getElementById('rituelResult');
    const actions=document.getElementById('rituelActions');
    const totalEl=document.getElementById('rituelTotal');
    const addAll=document.getElementById('rituelAddAll');
    const explore=document.getElementById('rituelExplore');
    const progress=document.getElementById('rituelProgress');
    const finder=document.getElementById('rituelFinder');
    if(!uWrap||!bWrap||!result) return;

    const STORE_KEY='cleo_rituel_state_v2';
    const SAVED_KEY='cleo_routine_saved';

    // ---- State + persistence (refresh + back/forward) ----
    function validCat(s){ return C.cats.some(c=>c.slug===s) ? s : null; }
    function validConcern(s){ return C.concerns.some(c=>c.slug===s) ? s : null; }
    function validMoment(s){ return ['tout','matin','soir'].includes(s) ? s : null; }

    function parseHashVal(val){
      if(!val) return {};
      try{
        if(val.indexOf('~')!==-1){
          const parts=val.split('~').map(p=>{ try{ return decodeURIComponent(p);}catch(e){return p;}});
          return {cat: validCat(parts[0]), concern: validConcern(parts[1]), moment: validMoment(parts[2])};
        }
        // legacy '-' format — cat and concern may contain hyphen (bien-etre, anti-age)
        const segments=val.split('-');
        const momentCand=segments[segments.length-1];
        const moment=validMoment(momentCand) ? momentCand : null;
        let remaining=segments.slice(0, moment? segments.length-1: segments.length);
        let concern=null;
        for(let n=2; n>=1; n--){
          if(remaining.length < n) continue;
          const cand=remaining.slice(-n).join('-');
          if(validConcern(cand)){ concern=cand; remaining=remaining.slice(0, -n); break; }
        }
        let cat=null;
        if(remaining.length){
          const cand=remaining.join('-');
          if(validCat(cand)) cat=cand;
          else if(remaining.length>=1 && validCat(remaining[0])) cat=remaining[0];
        }
        return {cat, concern, moment};
      }catch(e){ return {}; }
    }
    function readPersisted(){
      // priority: URL hash (#rituel=visage~hydration~tout or legacy #rituel=visage-hydration-tout) > URL ?r_cat= > localStorage > defaults
      try{
        const hash=location.hash||'';
        if(hash.indexOf('rituel=')!==-1){
          const m=hash.match(/rituel=([^&#]+)/);
          if(m){
            const parsed=parseHashVal(m[1]);
            if(parsed.cat||parsed.concern||parsed.moment) return parsed;
          }
        }
      }catch(e){}
      try{
        const sp=new URLSearchParams(location.search);
        const c=validCat(sp.get('r_cat')), co=validConcern(sp.get('r_need')), mo=validMoment(sp.get('r_m'));
        if(c||co||mo) return {cat:c, concern:co, moment:mo};
      }catch(e){}
      try{
        const raw=localStorage.getItem(STORE_KEY);
        if(raw){
          const o=JSON.parse(raw);
          return {cat:validCat(o.cat), concern:validConcern(o.concern), moment:validMoment(o.moment)};
        }
      }catch(e){}
      return {};
    }
    const persisted=readPersisted();
    let selCat=persisted.cat || C.cats[0]?.slug || 'visage';
    let selConcern=persisted.concern || C.concerns[0]?.slug || 'hydration';
    let selMoment=persisted.moment || 'tout';
    // sanitize
    if(!validCat(selCat)) selCat=C.cats[0]?.slug||'visage';
    if(!validConcern(selConcern)) selConcern=C.concerns[0]?.slug||'hydration';
    if(!validMoment(selMoment)) selMoment='tout';

    const mWrap=document.getElementById('rituelMoment');
    let _pushTimer=null;
    function persistState(push){
      try{ localStorage.setItem(STORE_KEY, JSON.stringify({cat:selCat, concern:selConcern, moment:selMoment})); }catch(e){}
      const hashVal=`rituel=${encodeURIComponent(selCat)}~${encodeURIComponent(selConcern)}~${encodeURIComponent(selMoment)}`;
      const url=`${location.pathname}${location.search}#${hashVal}`;
      try{
        if(push){
          history.pushState({rituel:{cat:selCat, concern:selConcern, moment:selMoment}}, '', url);
        } else {
          history.replaceState({rituel:{cat:selCat, concern:selConcern, moment:selMoment}}, '', url);
        }
      }catch(e){
        try{ history.replaceState(null,'', url);}catch(e2){}
      }
    }
    // initial sync without push
    persistState(false);

    // handle popstate / hashchange (back/forward, manual hash edit) — support both ~ and legacy - formats
    let handlingPop=false;
    window.addEventListener('popstate', ()=>{
      if(handlingPop) return;
      handlingPop=true;
      try{
        const st=history.state && history.state.rituel;
        if(st && validCat(st.cat)) selCat=st.cat;
        if(st && validConcern(st.concern)) selConcern=st.concern;
        if(st && validMoment(st.moment)) selMoment=st.moment;
        // also check hash (authoritative for manual edits)
        const h=location.hash;
        if(h.indexOf('rituel=')!==-1){
          const m=h.match(/rituel=([^&#]+)/);
          if(m){
            const parsed=parseHashVal(m[1]);
            if(parsed.cat) selCat=parsed.cat;
            if(parsed.concern) selConcern=parsed.concern;
            if(parsed.moment) selMoment=parsed.moment;
          }
        }
        renderChips(); renderProgress(); findRoutine();
      }finally{ setTimeout(()=> handlingPop=false, 0); }
    });
    window.addEventListener('hashchange', ()=>{
      if(handlingPop) return;
      try{
        const h=location.hash;
        if(h.indexOf('rituel=')!==-1){
          const m=h.match(/rituel=([^&#]+)/);
          if(m){
            const parsed=parseHashVal(m[1]);
            let changed=false;
            if(parsed.cat && parsed.cat!==selCat){ selCat=parsed.cat; changed=true; }
            if(parsed.concern && parsed.concern!==selConcern){ selConcern=parsed.concern; changed=true; }
            if(parsed.moment && parsed.moment!==selMoment){ selMoment=parsed.moment; changed=true; }
            if(changed){ renderChips(); renderProgress(); findRoutine(); }
          }
        }
      }catch(e){}
    });

    function renderProgress(){
      if(!progress) return;
      // 01 Univers — done, 02 Besoin — done, 03 Moment — current, 04 Votre rituel
      const steps=[
        {k:'01', label:'Univers', done:true},
        {k:'02', label:'Besoin', done:true},
        {k:'03', label:'Moment', done:true},
        {k:'04', label:'Votre rituel', done:false}
      ];
      // all three selectors are always selected, so first three are done
      const p=1; // progress 0-1 for lines
      progress.innerHTML=`
        <span class="rituel-progress__step is-done" aria-current="false"><span class="rituel-progress__dot">✓</span><span class="rituel-progress__label">${C.esc(C.catBySlug(selCat)?.name||selCat)}</span></span>
        <span class="rituel-progress__line" aria-hidden="true"><i style="--p:1"></i></span>
        <span class="rituel-progress__step is-done"><span class="rituel-progress__dot">✓</span><span class="rituel-progress__label">${C.esc(C.concernBySlug(selConcern)?.name||selConcern)}</span></span>
        <span class="rituel-progress__line" aria-hidden="true"><i style="--p:1"></i></span>
        <span class="rituel-progress__step is-done"><span class="rituel-progress__dot">${selMoment==='tout'?'4': '3'}</span><span class="rituel-progress__label">${selMoment==='matin'?'Matin': selMoment==='soir'?'Soir':'Complète'}</span></span>
        <span class="rituel-progress__line" aria-hidden="true"><i style="--p:1"></i></span>
        <span class="rituel-progress__step is-on"><span class="rituel-progress__dot">✦</span><span class="rituel-progress__label">Résultat</span></span>
      `;
    }

    function renderChips(){
      // univers + besoin with premium selected state + aria + keyboard
      const catFrag=C.cats.map(c=> {
        const on=c.slug===selCat;
        return `<button class="chip ${on?'is-on':''}" data-cat="${C.esc(c.slug)}" role="tab" aria-selected="${on}" tabindex="${on?0:-1}">${C.esc(c.name)}</button>`;
      }).join('');
      const concernFrag=C.concerns.map(c=> {
        const on=c.slug===selConcern;
        return `<button class="chip ${on?'is-on':''}" data-concern="${C.esc(c.slug)}" role="tab" aria-selected="${on}" tabindex="${on?0:-1}">${C.esc(c.name)}</button>`;
      }).join('');
      uWrap.innerHTML=catFrag;
      bWrap.innerHTML=concernFrag;
      if(mWrap){
        // update counts dynamically: how many products would routine contain?
        const catProds=C.products.filter(p=>p.cat===selCat);
        const concernProds=catProds.filter(p=> (p.concerns||[]).includes(selConcern));
        const pool=concernProds.length>=2?concernProds:catProds;
        // estimate steps for each moment
        function estimate(m){
          const all=[/nettoyage|démaquillage|lavant|lavante|gel|micellaire|shampoing/i, /sérum|anti-âge|anti-age|soin.*cibl|traitant|sérums/i, /crème|hydratant|lait|baume|huile|quotidien/i, /protection|solaire|spf/i];
          let steps=m==='matin'?[all[0],all[2],all[3]]: m==='soir'?[all[0],all[1],all[2]]: all;
          const used=new Set();
          let cnt=0;
          for(let re of steps){
            let f=pool.find(p=> !used.has(p.id) && re.test(p.sub||''));
            if(!f) f=pool.find(p=> !used.has(p.id));
            if(f){ cnt++; used.add(f.id); }
          }
          return cnt;
        }
        const cTout=estimate('tout'), cMatin=estimate('matin'), cSoir=estimate('soir');
        mWrap.innerHTML=`
          <button class="chip ${selMoment==='tout'?'is-on':''}" data-moment="tout" role="tab" aria-selected="${selMoment==='tout'}" tabindex="${selMoment==='tout'?0:-1}">Complète · ${cTout} gestes</button>
          <button class="chip ${selMoment==='matin'?'is-on':''}" data-moment="matin" role="tab" aria-selected="${selMoment==='matin'}" tabindex="${selMoment==='matin'?0:-1}">Matin — ${cMatin} étapes</button>
          <button class="chip ${selMoment==='soir'?'is-on':''}" data-moment="soir" role="tab" aria-selected="${selMoment==='soir'}" tabindex="${selMoment==='soir'?0:-1}">Soir — ${cSoir} étapes</button>
        `;
      }
      renderProgress();
    }

    // ---- Routine logic (real data only, deterministic, no fake AI) ----
    function scoreProduct(p){
      // meaningful scoring from real fields: concern match primary, then rating, then reviews
      let s=0;
      if((p.concerns||[]).includes(selConcern)) s+=10;
      // secondary: sub keyword overlap with concern name
      const concernName=(C.concernBySlug(selConcern)?.name||'').toLowerCase();
      if(concernName && (p.sub||'').toLowerCase().includes(concernName.split(' ')[0])) s+=2;
      s+= (p.rating||0)*0.2;
      s+= Math.log10((p.reviews||1)+1)*0.3;
      if(p.featured) s+=1.5;
      if(p.bestseller) s+=1.2;
      if(p.stock===false) s-=5; // de-prioritize but still show with badge
      return s;
    }

    function findRoutine(){
      try{
        const catProds=C.products.filter(p=>p.cat===selCat);
        // sort by meaningful score before pooling — real data only
        catProds.sort((a,b)=> scoreProduct(b)-scoreProduct(a));
        const concernProds=catProds.filter(p=> (p.concerns||[]).includes(selConcern));
        // primary pool: concern-focused if enough, else broad cat pool — never fabricate
        const pool=concernProds.length>=2 ? concernProds.slice() : catProds;
        // keep fullCat for fallback per step (e.g., needing solaire even if not hydration-tagged)
        const fullCat=catProds;
        // edge: no products at all for this cat (should not happen, but handle)
        if(!pool.length){
          result.classList.remove('is-switching');
          result.innerHTML=`
            <div class="rituel-empty" role="status">
              <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" style="color:var(--gold)"><circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>
              <h4>Aucune référence pour cet univers</h4>
              <p>La catégorie “${C.esc(C.catBySlug(selCat)?.name||selCat)}” ne contient pas encore de produits — explorez l’ensemble de la boutique.</p>
              <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center"><a class="btn btn--ink btn--sm" href="${C.PAGES}shop.html">Explorer la boutique</a><a class="link-u" href="${C.PAGES}contact.html">Demander conseil</a></div>
            </div>`;
          if(actions) actions.hidden=true;
          if(explore) explore.href=C.PAGES+'shop.html';
          persistState(false);
          return;
        }
        // Step definitions — use existing real sub + form + concerns, never invent properties
        const allSteps=[
          {k:'01', label:'Nettoyer', purpose:'Préparer', test: p=> /nettoyage|démaquillage|lavant|lavante|gel|micellaire|shampoing/i.test(p.sub||'')},
          {k:'02', label:'Traiter', purpose:'Corriger', test: p=> /sérum|anti-âge|anti-age|soin.*cibl|traitant|sérums/i.test(p.sub||'')},
          {k:'03', label:'Hydrater', purpose:'Nourrir', test: p=> /crème|hydratant|lait|baume|huile|quotidien|réparateur/i.test(p.sub||'')},
          {k:'04', label:'Protéger', purpose:'Préserver', test: p=> /protection|solaire|spf|isotonique|thermale/i.test(p.sub||'') || (p.concerns||[]).includes('solaire') },
        ];
        let steps=allSteps;
        if(selMoment==='matin') steps=[allSteps[0], allSteps[2], allSteps[3]];
        else if(selMoment==='soir') steps=[allSteps[0], allSteps[1], allSteps[2]];

        const chosen=[];
        const used=new Set();
        for(let s of steps){
          // Prefer concern-matching product for step, else broad cat product, else any remaining — deterministic, no invention
          let found=pool.find(p=> !used.has(p.id) && s.test(p));
          if(!found && pool!==fullCat) found=fullCat.find(p=> !used.has(p.id) && s.test(p));
          if(!found) found=pool.find(p=> !used.has(p.id));
          if(!found && pool!==fullCat) found=fullCat.find(p=> !used.has(p.id));
          if(found){ chosen.push({step:s, prod:found}); used.add(found.id); }
        }
        // If insufficient to build meaningful routine (<2), show premium fallback with real alternatives, never fake
        if(chosen.length<2){
          // Provide curated real fallback: top rated from same cat or same concern
          const fallbackPool=C.products.filter(p=> p.cat===selCat).sort((a,b)=> b.rating-a.rating).slice(0,3);
          const list=fallbackPool.length? fallbackPool : C.products.filter(p=> (p.concerns||[]).includes(selConcern)).slice(0,3);
          result.classList.remove('is-switching');
          if(list.length){
            result.innerHTML=`
              <div class="rituel-empty" role="status" style="grid-column:1/-1">
                <h4>Routine en cours de composition</h4>
                <p>Pas assez de références distinctes pour “${C.esc(C.catBySlug(selCat)?.name||selCat)} × ${C.esc(C.concernBySlug(selConcern)?.name||selConcern)}” avec le moment “${selMoment}”. Voici la sélection du comptoir la plus proche — tous produits réels, choisis pour vous.</p>
                <a class="link-u" href="${C.PAGES}category.html?cat=${selCat}">Explorer l’univers ${C.esc(C.catBySlug(selCat)?.name||'')}</a>
              </div>` + list.map(p=> miniFallbackCard(p)).join('');
            // embed fallback as cards? Simpler: show fallback cards below empty
            // Actually render fallback cards inline after empty: we already appended via string, but need proper grid
            // Re-render as grid with empty on top: we did, it will occupy one row, others will fill columns. Better to render fallback grid separately.
            // Let's create dedicated fallback grid rendering:
            const grid=document.createElement('div');
            grid.style.cssText='display:contents';
            // Remove previous innerHTML empty and re-append cards? For now keep simple: show fallback products as rituel cards
            result.innerHTML=`
              <div class="rituel-empty" role="status">
                <h4>Routine trop courte ici</h4>
                <p>Pour ${C.esc(C.catBySlug(selCat)?.name||selCat)} × ${C.esc(C.concernBySlug(selConcern)?.name||selConcern)} en mode ${selMoment}, nous n’avons que ${chosen.length} geste${chosen.length>1?'s':''} distinct${chosen.length>1?'s':''} — voici le plus proche, sans invention.</p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center"><a class="link-u" href="${C.PAGES}shop.html?cat=${selCat}&concern=${selConcern}">Voir tout ${C.esc(selConcern)}</a><a class="link-u" href="${C.PAGES}shop.html?cat=${selCat}">Tout l’univers</a></div>
              </div>`;
            // append fallback product cards
            list.forEach((p,i)=>{
              const d=C.off(p);
              const card=document.createElement('div');
              card.className='rituel-card';
              card.innerHTML=`
                <div class="rituel-card__top"><span class="rituel-card__step"><b>•</b> ${C.esc(p.sub||'Soin')} <small>· ${C.esc(p.brand)}</small></span></div>
                <div class="rituel-card__media">${C.ART.front(p)} ${p.oldPrice?`<span class="rituel-card__badge rituel-card__badge--promo">-${d}%</span>`:''}</div>
                <div class="rituel-card__info">
                  <span class="rituel-card__brand">${C.esc(p.brand)}</span>
                  <span class="rituel-card__name">${C.esc(p.name)}</span>
                  <span class="rituel-card__meta">${C.esc(p.size||'')} · ${C.esc((C.catBySlug(p.cat)||{}).name||'')}</span>
                  <span class="rituel-card__prices"><span class="price-now">${C.fmt(p.price)}</span>${p.oldPrice?`<span class="price-old">${C.fmt(p.oldPrice)}</span>`:''}</span>
                  <div class="rituel-card__actions"><a class="link-u" href="${C.PAGES}product.html?id=${encodeURIComponent(p.id)}">Voir le produit<span class="arr">→</span></a><button class="btn btn--ink btn--sm" data-add="${C.esc(p.id)}" ${p.stock?'':'disabled'}>${p.stock?'Ajouter':'Épuisé'}</button></div>
                </div>`;
              result.appendChild(card);
            });
            C.observe(result);
          } else {
            result.innerHTML=`<div class="rituel-empty" role="status"><h4>Sélection en cours de composition</h4><p>Nous n’avons pas assez de produits pour cette combinaison — écrivez-nous, nous la complétons pour vous.</p><a class="btn btn--ink btn--sm" href="${C.PAGES}shop.html">Explorer la boutique</a></div>`;
          }
          if(actions) actions.hidden=true;
          if(explore) explore.href=C.PAGES+'shop.html?cat='+selCat+'&concern='+selConcern;
          persistState(false);
          return;
        }

        // Builder state — mutable
        let routine=chosen.slice(); // [{step, prod}]

        function miniFallbackCard(p){
          const d=C.off(p);
          return `<div class="rituel-card"><div class="rituel-card__media">${C.ART.front(p)}</div><div class="rituel-card__info"><span class="rituel-card__brand">${C.esc(p.brand)}</span><span class="rituel-card__name">${C.esc(p.name)}</span><span class="rituel-card__prices"><span class="price-now">${C.fmt(p.price)}</span></span><a class="link-u link-u--small" href="${C.PAGES}product.html?id=${encodeURIComponent(p.id)}">Voir le produit →</a></div></div>`;
        }

        function renderRoutineGrid(){
          if(!routine.length){
            result.innerHTML=`<div class="rituel-empty" role="status"><h4>Routine vide</h4><p>Vous avez retiré tous les gestes — ajoutez un produit depuis la boutique ou réinitialisez.</p><div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center"><button class="btn btn--ink btn--sm" id="rituelResetEmpty">Recomposer</button><a class="link-u" href="${C.PAGES}shop.html">Boutique</a></div></div>`;
            result.querySelector('#rituelResetEmpty')?.addEventListener('click', ()=> findRoutine());
            if(actions) actions.hidden=true;
            C.observe(result);
            return;
          }
          // fade transition
          result.classList.add('is-switching');
          setTimeout(()=>{
          result.innerHTML=routine.map(({step,prod}, idx)=>{
            const d=C.off(prod);
            const inWish=C.wish && C.wish.has(prod.id);
            const pts=Math.floor((prod.price/1000)*10);
            const stockBadge=!prod.stock ? `<span class="rituel-card__badge rituel-card__badge--out">Épuisé</span>` : prod.track_stock && prod.stock_quantity<=5 ? `<span class="rituel-card__badge rituel-card__badge--low">Plus que ${prod.stock_quantity}</span>` : prod.oldPrice ? `<span class="rituel-card__badge rituel-card__badge--promo">-${d}%</span>` : '';
            const ratingLine= prod.rating ? `<span class="rituel-card__rating">${C.stars(prod.rating)}<span>${Number(prod.rating).toFixed(1)} (${prod.reviews||0})</span></span>` : '';
            return `
          <div class="rituel-card ${!prod.stock?'is-out':''}" data-idx="${idx}" style="--d:${idx}">
            <div class="rituel-card__top">
              <span class="rituel-card__step"><b>${C.esc(step.k)}</b> · ${C.esc(step.label)} <small>— ${C.esc(step.purpose)}</small></span>
              <span style="display:flex;gap:4px">
                <button class="rituel-mini" data-up="${idx}" aria-label="Monter" ${idx===0?'disabled':''}>↑</button>
                <button class="rituel-mini" data-down="${idx}" aria-label="Descendre" ${idx===routine.length-1?'disabled':''}>↓</button>
                <button class="rituel-mini" data-replace="${idx}" aria-label="Remplacer ce geste" title="Remplacer">↻</button>
              </span>
            </div>
            <div class="rituel-card__media">
              ${stockBadge}
              <button class="rituel-card__wish ${inWish?'is-active':''}" data-wish="${C.esc(prod.id)}" aria-label="${inWish?'Retirer des favoris':'Ajouter aux favoris'}" aria-pressed="${inWish}">
                <svg viewBox="0 0 24 24"><path d="M12 21c-5.5-3.6-9-7.1-9-11a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 3.9-3.5 7.4-9 11z"/></svg>
              </button>
              <a href="${C.PAGES}product.html?id=${encodeURIComponent(prod.id)}" class="pcard__media-link" aria-label="${C.esc(prod.name)}" style="position:absolute;inset:0;z-index:1"></a>
              ${C.ART.front(prod)}
            </div>
            <div class="rituel-card__info">
              <span class="rituel-card__brand">${C.esc(prod.brand)}</span>
              <span class="rituel-card__name">${C.esc(prod.name)}</span>
              <span class="rituel-card__meta">${C.esc(prod.size||'')} · ${C.esc(prod.sub||'Soin')} · ${C.esc((C.catBySlug(prod.cat)||{}).name||'')}</span>
              <span class="rituel-card__purpose">${C.esc(step.purpose)} · ${C.esc(prod.sub||'')}</span>
              <span class="rituel-card__prices">
                <span class="price-now">${C.fmt(prod.price)}</span>
                ${prod.oldPrice?`<span class="price-old">${C.fmt(prod.oldPrice)}</span><span class="price-off">-${d}%</span>`:''}
              </span>
              ${ratingLine}
              <span style="font-size:.64rem;color:var(--sage);letter-spacing:.02em">+${pts} pts fidélité</span>
              <div class="rituel-card__actions">
                <a class="link-u link-u--small" href="${C.PAGES}product.html?id=${encodeURIComponent(prod.id)}">Voir le produit<span class="arr">→</span></a>
                <button class="link-u link-u--small" data-rituel-remove="${idx}" style="color:var(--muted)">Retirer</button>
                <button class="btn btn--ink btn--sm" data-add="${C.esc(prod.id)}" ${prod.stock?'':'disabled'}>${prod.stock?'Ajouter':'Épuisé'}</button>
              </div>
            </div>
            <div class="rituel-card__foot">
              <b>${C.esc(C.concernBySlug(selConcern)?.name||'')} · ${C.esc(C.catBySlug(selCat)?.name||'')}</b>
              <span>${C.esc(prod.brand)}</span>
            </div>
          </div>`;
          }).join('');
          // add slot if routine <4 and pool has alternatives (real data only)
          const remaining=C.products.filter(p=> p.cat===selCat && !routine.some(r=> r.prod.id===p.id));
          if(routine.length<4 && remaining.length){
            const addSlot=document.createElement('div');
            addSlot.className='rituel-card rituel-card--add';
            addSlot.innerHTML=`
              <div style="display:grid;justify-items:center;gap:8px">
                <span style="width:42px;height:42px;border-radius:50%;border:1px dashed var(--line-strong);display:grid;place-items:center;font-size:1.2rem;color:var(--gold)">+</span>
                <b style="font-family:var(--serif);font-weight:500">Ajouter un geste</b>
                <p>Complétez votre rituel avec une alternative du même univers — produit réel, jamais inventé.</p>
                <button class="btn btn--ghost btn--sm" data-add-step>Choisir un produit</button>
              </div>`;
            result.appendChild(addSlot);
            addSlot.querySelector('[data-add-step]')?.addEventListener('click', ()=> openPicker(routine.length));
          }
          result.classList.remove('is-switching');
          C.observe(result);
          // total + actions
          const total=routine.reduce((s,x)=> s+ (x.prod.stock? x.prod.price:0),0);
          const totalCount=routine.filter(x=> x.prod.stock).length;
          const hasOut=routine.some(x=> !x.prod.stock);
          if(totalEl){
            const free=C.FREE_SHIP || 99000;
            const remain=Math.max(0, free - total);
            totalEl.innerHTML=`${C.fmt(total)} <small>· ${routine.length} gestes · ${totalCount} disponible${totalCount>1?'s':''}</small> ${hasOut?'<i>Certains indisponibles</i>': remain>0 ? `<i>Encore ${C.fmt(remain)} et livraison offerte</i>` : '<i>Livraison offerte ✓</i>'}`;
          }
          if(actions){
            actions.hidden=false;
            try{
              const saved=JSON.parse(localStorage.getItem(SAVED_KEY)||'null');
              const isSaved=saved && saved.length===routine.length && saved.every((id,i)=> id===routine[i].prod.id);
              const saveBtn=document.getElementById('rituelSave');
              if(saveBtn){ saveBtn.textContent=isSaved?'✓ Routine enregistrée':'Enregistrer la routine'; saveBtn.disabled=!!isSaved; saveBtn.style.opacity=isSaved?'0.7':''; }
            }catch(e){}
            // disable addAll if none available
            if(addAll) addAll.disabled = totalCount===0;
            if(addAll) addAll.style.opacity = totalCount===0 ? '0.5' : '';
          }
          if(explore) explore.href=`${C.PAGES}shop.html?cat=${selCat}&concern=${selConcern}`;
          bindRoutineActions();
          persistState(false);
          }, 140);
        }

        function bindRoutineActions(){
          result.querySelectorAll('[data-up]').forEach(b=> b.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation();
            const i=parseInt(b.dataset.up);
            if(i>0){ const t=routine[i]; routine[i]=routine[i-1]; routine[i-1]=t; renderRoutineGrid(); }
          }));
          result.querySelectorAll('[data-down]').forEach(b=> b.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation();
            const i=parseInt(b.dataset.down);
            if(i<routine.length-1){ const t=routine[i]; routine[i]=routine[i+1]; routine[i+1]=t; renderRoutineGrid(); }
          }));
          result.querySelectorAll('[data-replace]').forEach(b=> b.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); openPicker(parseInt(b.dataset.replace)); }));
          result.querySelectorAll('[data-rituel-remove]').forEach(b=> b.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation();
            const i=parseInt(b.dataset.rituelRemove);
            routine.splice(i,1);
            // re-number steps after removal to keep 01.. consistent
            routine.forEach((r, idx)=> r.step.k=String(idx+1).padStart(2,'0'));
            renderRoutineGrid();
          }));
          result.querySelectorAll('[data-wish]').forEach(b=> b.addEventListener('click', (e)=>{
            e.preventDefault(); e.stopPropagation();
            const pid=b.dataset.wish;
            const p=C.byId(pid);
            const active=!b.classList.contains('is-active');
            b.classList.toggle('is-active', active);
            b.setAttribute('aria-pressed', String(active));
            if(C.wish) C.wish.toggle(pid);
            C.toast(active?'Ajouté à vos favoris':'Retiré de vos favoris', p?C.ART.front(p):'');
            // sync all same pid buttons (no CSS.escape for compat)
            result.querySelectorAll('[data-wish]').forEach(x=>{
              if(x.dataset.wish===pid){
                x.classList.toggle('is-active', active);
                x.setAttribute('aria-pressed', String(active));
              }
            });
          }));
          result.querySelectorAll('[data-add]').forEach(b=> b.addEventListener('click', (e)=>{
            e.preventDefault(); e.stopPropagation();
            const pid=b.dataset.add;
            const p=C.byId(pid);
            if(!p || !p.stock){ C.toast('Produit indisponible — réapprovisionnement en cours'); return; }
            // debounce double click
            if(b.dataset.busy==='1') return;
            b.dataset.busy='1';
            const prev=b.textContent;
            b.textContent='Ajouté ✓';
            C.cart.add(pid);
            C.toast('Ajouté — '+ (p.name.split(',')[0] || p.name), C.ART.front(p), 'Voir le panier', ()=> C.openCart());
            setTimeout(()=>{ b.textContent=prev; b.dataset.busy='0'; }, 900);
          }));
        }

        function openPicker(idx){
          const catProds=C.products.filter(p=>p.cat===selCat).sort((a,b)=> scoreProduct(b)-scoreProduct(a));
          const pool=catProds.filter(p=> !routine.some(r=> r.prod.id===p.id)).slice(0,14);
          if(!pool.length){ C.toast('Plus d’alternatives dans cet univers — explorez la boutique', '', 'Boutique', ()=> location.href=C.PAGES+'shop.html?cat='+selCat); return; }
          const picker=document.createElement('div');
          picker.className='rituel-picker';
          picker.setAttribute('role','dialog');
          picker.setAttribute('aria-modal','true');
          picker.setAttribute('aria-label','Choisir un produit pour votre rituel');
          const inWishIds=new Set(C.wish ? C.wish.items.map(x=> typeof x==='string'?x:x.id) : []);
          picker.innerHTML=`
            <div class="rituel-picker__panel">
              <div class="rituel-picker__head">
                <div>
                  <b style="font-family:var(--serif);font-size:1.05rem">Choisir un produit — ${C.esc(C.catBySlug(selCat)?.name||selCat)}</b>
                  <p style="font-size:.78rem;color:var(--muted);margin-top:4px">${pool.length} alternatives réelles · même univers, jamais inventé</p>
                </div>
                <button class="btn btn--ghost btn--sm" data-close-picker aria-label="Fermer">Fermer</button>
              </div>
              <div class="rituel-picker__grid">
                ${pool.map(p=>{
                  const d=C.off(p);
                  const badge=p.stock ? (p.oldPrice?`<span class="rituel-card__badge rituel-card__badge--promo" style="position:absolute;top:8px;left:8px">-${d}%</span>`:'') : `<span class="rituel-card__badge rituel-card__badge--out" style="position:absolute;top:8px;left:8px">Épuisé</span>`;
                  return `<button class="rituel-picker__opt" data-pick="${C.esc(p.id)}" ${p.stock?'':'disabled'} aria-label="${C.esc(p.name)}">
                    <div class="art" style="position:relative">${badge}${C.ART.front(p)}</div>
                    <div style="padding:10px;display:grid;gap:4px">
                      <small style="font-size:.56rem;letter-spacing:.18em;text-transform:uppercase;color:var(--gold);font-weight:500">${C.esc(p.brand)}</small>
                      <div style="font-family:var(--serif);font-size:.92rem;line-height:1.2;font-weight:500">${C.esc(p.name.split(',')[0])}</div>
                      <small style="font-size:.68rem;color:var(--muted)">${C.esc(p.sub||'')} · ${C.esc(p.size||'')}</small>
                      <div style="display:flex;gap:6px;align-items:baseline;margin-top:4px"><b style="font-size:.84rem">${C.fmt(p.price)}</b>${p.oldPrice?`<small style="color:var(--faint);text-decoration:line-through">${C.fmt(p.oldPrice)}</small>`:''}</div>
                    </div>
                  </button>`;
                }).join('')}
              </div>
              <p style="padding:0 16px 16px;font-size:.72rem;color:var(--muted);border-top:1px solid var(--line);margin-top:12px;padding-top:12px">Tous les produits affichés existent réellement dans le catalogue Cléopâtre — prix et disponibilité à jour.</p>
            </div>`;
          document.body.appendChild(picker);
          document.body.classList.add('is-locked');
          const close=()=>{
            picker.style.opacity='0';
            setTimeout(()=>{ picker.remove(); document.body.classList.remove('is-locked'); }, 180);
          };
          picker.querySelector('[data-close-picker]')?.addEventListener('click', close);
          picker.addEventListener('click', e=>{ if(e.target===picker) close(); });
          const onEsc=(e)=>{ if(e.key==='Escape'){ close(); document.removeEventListener('keydown', onEsc); }};
          document.addEventListener('keydown', onEsc);
          picker.querySelectorAll('[data-pick]').forEach(b=> b.addEventListener('click', ()=>{
            const prod=C.byId(b.dataset.pick);
            if(!prod) return;
            const step= (idx < routine.length && routine[idx]) ? routine[idx].step : {k:String(routine.length+1).padStart(2,'0'), label:['Nettoyer','Traiter','Hydrater','Protéger'][routine.length]||'Soin', purpose:['Préparer','Corriger','Nourrir','Préserver'][routine.length]||'Soin'};
            const entry={step, prod};
            if(idx < routine.length) routine[idx]=entry;
            else routine.push(entry);
            // reindex ks after push to keep sequential
            routine.forEach((r,i)=> r.step.k=String(i+1).padStart(2,'0'));
            picker.remove(); document.body.classList.remove('is-locked');
            document.removeEventListener('keydown', onEsc);
            renderRoutineGrid();
          }));
        }

        renderRoutineGrid();

        if(addAll && !addAll._bound){
          addAll._bound=true;
          addAll.addEventListener('click', ()=>{
            let added=0, skipped=0;
            routine.forEach(({prod})=>{
              if(prod.stock){ C.cart.add(prod.id); added++; } else skipped++;
            });
            if(added){
              const msg= added>1 ? `${added} produits ajoutés — routine complète` : 'Produit ajouté';
              const detail=skipped? ` (${skipped} indisponible${skipped>1?'s':''})` : '';
              C.toast(msg+detail, routine[0]?.prod?C.ART.front(routine[0].prod):'', 'Voir le panier', ()=> C.openCart());
            } else {
              C.toast('Aucun produit disponible dans cette routine — essayez une autre combinaison');
            }
          });
        }
        const saveBtn=document.getElementById('rituelSave');
        if(saveBtn && !saveBtn._bound){
          saveBtn._bound=true;
          saveBtn.addEventListener('click', ()=>{
            try{
              localStorage.setItem(SAVED_KEY, JSON.stringify(routine.map(r=>r.prod.id)));
              C.toast('Routine enregistrée', '', 'Voir ma sélection', ()=> location.href=C.PAGES+'wishlist.html');
              renderRoutineGrid();
            }catch(e){ C.toast('Impossible d’enregistrer — stockage indisponible'); }
          });
        }
      }catch(err){
        console.error('[rituel] findRoutine error', err);
        result.innerHTML=`<div class="rituel-empty" role="alert"><h4>Une erreur est survenue</h4><p>Impossible de composer la routine — veuillez réessayer ou explorer la boutique.</p><a class="btn btn--ink btn--sm" href="${C.PAGES}shop.html">Explorer la boutique</a></div>`;
        if(actions) actions.hidden=true;
      }
    }

    renderChips(); findRoutine();

    // --- Interactions (chips) with pushState for back/forward ---
    let chipBusy=false;
    function onUnivers(e){
      const b=e.target.closest('[data-cat]');
      if(!b||chipBusy) return;
      const next=b.dataset.cat;
      if(next===selCat) return;
      chipBusy=true;
      selCat=next;
      renderChips();
      // animate transition
      result.classList.add('is-switching');
      setTimeout(()=>{ findRoutine(); persistState(true); chipBusy=false; }, 120);
    }
    function onBesoin(e){
      const b=e.target.closest('[data-concern]');
      if(!b||chipBusy) return;
      const next=b.dataset.concern;
      if(next===selConcern) return;
      chipBusy=true;
      selConcern=next;
      renderChips();
      result.classList.add('is-switching');
      setTimeout(()=>{ findRoutine(); persistState(true); chipBusy=false; }, 120);
    }
    function onMoment(e){
      const b=e.target.closest('[data-moment]');
      if(!b||chipBusy) return;
      const next=b.dataset.moment;
      if(next===selMoment) return;
      chipBusy=true;
      selMoment=next;
      renderChips();
      result.classList.add('is-switching');
      setTimeout(()=>{ findRoutine(); persistState(true); chipBusy=false; }, 120);
    }
    uWrap.addEventListener('click', onUnivers);
    bWrap.addEventListener('click', onBesoin);
    if(mWrap) mWrap.addEventListener('click', onMoment);

    // keyboard navigation for chips (arrow keys + Home/End)
    function bindChipKeyboard(wrap){
      wrap.addEventListener('keydown', e=>{
        const btns=[...wrap.querySelectorAll('.chip')];
        const idx=btns.indexOf(document.activeElement);
        if(idx===-1) return;
        let next=-1;
        if(e.key==='ArrowRight' || e.key==='ArrowDown') next=(idx+1)%btns.length;
        else if(e.key==='ArrowLeft' || e.key==='ArrowUp') next=(idx-1+btns.length)%btns.length;
        else if(e.key==='Home') next=0;
        else if(e.key==='End') next=btns.length-1;
        if(next!==-1){ e.preventDefault(); btns[next].focus(); }
        if(e.key==='Enter' || e.key===' '){ e.preventDefault(); document.activeElement.click(); }
      });
    }
    bindChipKeyboard(uWrap); bindChipKeyboard(bWrap); if(mWrap) bindChipKeyboard(mWrap);

    // also listen for catalog hydration (API products arrive after initial render)
    let hydratedOnce=false;
    document.addEventListener('cleo:catalog', ()=>{
      if(hydratedOnce) return;
      hydratedOnce=true;
      // re-validate persisted cat/concern still valid
      if(!validCat(selCat)) selCat=C.cats[0]?.slug||selCat;
      if(!validConcern(selConcern)) selConcern=C.concerns[0]?.slug||selConcern;
      // re-render with fresh catalogue (scores may change, prices update)
      renderChips(); findRoutine();
    });
    // also handle wish changes to sync hearts
    if(C.wish && C.wish.subscribe){
      C.wish.subscribe(()=>{
        // update all wish buttons without full re-render
        result.querySelectorAll('[data-wish]').forEach(b=>{
          const has=C.wish.has(b.dataset.wish);
          b.classList.toggle('is-active', has);
          b.setAttribute('aria-pressed', String(has));
        });
      });
    }

    C.observe(finder || document.getElementById('rituelSec'));
  }

  /* 06 — Offres du moment (rebuilt) */
  function renderPromos() {
    const grid = document.getElementById("promoGrid");
    if (!grid) return;
    const top = C.products.filter(p => p.oldPrice).sort((a, b) => C.off(b) - C.off(a)).slice(0, 3);
    grid.innerHTML = top.map(p => C.cardHTML(p)).join("");
    const cards = grid.querySelectorAll(".pcard");
    cards.forEach((card, i) => setTimeout(() => card.classList.add("is-in"), 120 + i * 110));
  }
  // 17.8 Countdown — ONLY real promotion end_date, never fake urgency (Phase 17)
  function startCountdown() {
    const el = document.getElementById("countdown");
    if (!el) return;
    const spans = el.querySelectorAll("span");
    // Try to find a real promo end date from products (promo_end) or promotions API
    // If none, hide countdown gracefully — no fake urgency
    let realEnd=null;
    // Check products with promo_end (from DB is_new/promo_end)
    const withEnd = C.products.filter(p=>p.promo_end||p.promoEnd).map(p=> new Date(p.promo_end||p.promoEnd)).filter(d=>!isNaN(d) && d>Date.now());
    if(withEnd.length) realEnd=new Date(Math.min(...withEnd));
    // Fallback: try promotions API silently (no fake if fails)
    if(!realEnd && window.CLEO_API){
      // Attempt async fetch — if fails, hide
      C.products.filter(p=>p.oldPrice).length && window.CLEO_API.admin && window.CLEO_API.admin.promotions && window.CLEO_API.admin.promotions().then(res=>{
        const promos=res.data?.promotions||res.data||[];
        const dates=promos.map(p=>p.end_date||p.endDate).map(d=> d?new Date(d):null).filter(d=>d && d>Date.now());
        if(dates.length){
          const end=new Date(Math.min(...dates));
          startTick(end);
        } else {
          el.style.display='none';
          const note=el.parentElement?.querySelector('.promo-head__right .countdown')||el;
          // Show graceful message instead of fake countdown
          const msg=document.createElement('p');
          msg.style.cssText='font-size:.72rem;color:var(--muted);letter-spacing:.08em';
          msg.textContent='Prix doux — jusqu’à épuisement du stock';
          el.replaceWith(msg);
        }
      }).catch(()=>{ el.style.display='none'; });
      // If no API, hide immediately
      if(!withEnd.length){
        // Temporarily hide until API resolves, then either show or replace
        el.style.visibility='hidden';
        setTimeout(()=>{ if(el.parentElement && !realEnd) el.style.display='none'; },1200);
        return;
      }
    }
    if(!realEnd){
      // No real end date — hide countdown, no fake urgency
      el.style.display='none';
      const parent=el.parentElement;
      if(parent && !parent.querySelector('.promo-no-countdown')){
        const p=document.createElement('p');
        p.className='promo-no-countdown';
        p.style.cssText='font-size:.72rem;color:var(--muted);letter-spacing:.05em;line-height:1.4';
        p.textContent='Sélection à prix doux — jusqu’à épuisement';
        parent.insertBefore(p, el);
      }
      return;
    }
    startTick(realEnd);
    function startTick(end){
      el.style.visibility='visible';
      el.style.display='flex';
      setInterval(tick,1000); tick();
      function tick(){
        let s=Math.max(0, Math.floor((end - Date.now())/1000));
        const d=Math.floor(s/86400); s-=d*86400;
        const h=Math.floor(s/3600); s-=h*3600;
        const m=Math.floor(s/60); s-=m*60;
        [d,h,m,s].forEach((v,i)=>{ if(spans[i]) spans[i].textContent=String(v).padStart(2,'0'); });
        if(s<=0 && d===0 && h===0 && m===0) { el.style.opacity='.55'; }
      }
    }
  }

  /* 07 — Journal */
  function articleArt(a) {
    return C.ART.scene(["#EAE3D6", a.tint, "#D9CBB0"], { bg: a.tint });
  }
  function fmtDate(iso) {
    return new Date(iso).toLocaleDateString("fr-FR", { day: "2-digit", month: "long", year: "numeric" });
  }
  function renderJournal() {
    const grid = document.getElementById("journalGrid");
    if (!grid) return;
    const [feat, ...rest] = C.articles.slice(0, 4);
    grid.innerHTML = `
      <a class="jfeat" href="${C.PAGES}conseil.html?id=${feat.id}" data-reveal>
        <span class="art" aria-hidden="true">${articleArt(feat)}</span>
        <span class="jfeat__body">
          <span class="jfeat__meta">${feat.rubrique} · ${fmtDate(feat.date)} · ${feat.readTime} min</span>
          <h3>${feat.title}</h3>
        </span>
      </a>
      <div class="jlist">
        ${rest.map((a, i) => `
        <a class="jrow" href="${C.PAGES}conseil.html?id=${a.id}" data-reveal style="--d:${i + 1}">
          <span class="art" aria-hidden="true">${articleArt(a)}</span>
          <span><span class="jrow__meta">${a.rubrique} · ${a.readTime} min</span><h4>${a.title}</h4></span>
        </a>`).join("")}
      </div>`;
    C.observe(grid);
  }

  /* 08 — Confiance — 5 piliers service (Phase 33) */
  function renderTrust() {
    const g = document.getElementById("trustGrid");
    if (!g) return;
    const items = [
      ["M12 3l7 4v5c0 4.4-3 8-7 9-4-1-7-4.6-7-9V7z", "Authenticité certifiée", "Chaque référence provient directement des laboratoires officiels. Traçabilité comptoir."],
      ["M3 12h13M13 6l6 6-6 6M21 5v14", "Livraison 24–72 h", "Partout en Tunisie, offerte dès 99 DT. Préparée au comptoir."],
      ["M12 8v5m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z", "Conseils de pharmaciennes", "Diplômées, disponibles en ligne et à l’officine. Réponse sous 24h."],
      ["M4 7h16v11H4zM4 10h16M8 15h4", "Paiement flexible", "À la réception sans frais, bientôt carte sécurisée."],
      ["M12 2.5l5 2 5-2v7c0 4-2.5 6-5 7.5-2.5-1.5-5-3.5-5-7.5v-7z", "Officine depuis 2003", "12 Avenue Habib Bourguiba, Tunis · Lun–Sam 9h–19h. Visitez-nous."]
    ];
    g.innerHTML = items.map(([path, t, d], i) => `
      <div class="titem" data-reveal style="--d:${i}">
        <svg viewBox="0 0 24 24"><path d="${path}"/></svg>
        <b>${t}</b><p>${d}</p>
      </div>`).join("");
    C.observe(g);
  }

  /* Compteurs animés */
  function counters() {
    const els = C.$$("[data-count]");
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        io.unobserve(en.target);
        const el = en.target, target = +el.dataset.count, suffix = el.querySelector("i");
        const t0 = performance.now(), dur = 1400;
        (function frame(now) {
          const k = Math.min(1, (now - t0) / dur), eased = 1 - Math.pow(1 - k, 3);
          el.firstChild.nodeValue = Math.round(target * eased);
          if (k < 1) requestAnimationFrame(frame);
        })(t0);
      });
    }, { threshold: .6 });
    els.forEach(el => io.observe(el));
  }

  /* Parallaxe douce du héros — compositor-friendly (transform only, rAF, passive) */
  function heroParallax() {
    if (C.reduceMotion) return;
    const art = document.getElementById("heroArt");
    if (!art) return;
    let ticking=false, latestY=0;
    addEventListener("scroll", () => {
      latestY = Math.min(scrollY, 700);
      if(!ticking){
        ticking=true;
        requestAnimationFrame(()=>{
          art.style.transform = `translate3d(0,${latestY * -0.06}px,0)`;
          art.style.willChange = 'transform';
          ticking=false;
        });
      }
    }, { passive: true });
  }
})();

/* CLÉOPÂTRE — Page maison (marque) */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$;

  C.onReady(() => {
    const slug = C.qs("brand");
    const b = C.brandBySlug(slug) || C.brands[0];
    if (!C.brandBySlug(slug)) { location.replace(C.PAGES + "brand.html?brand=" + b.slug); return; }

    document.title = b.name + " — Histoire & collections | Cléopâtre Parapharmacie";
    document.body.style.setProperty("--brand-tint", b.tint);
    $("#bCrumb").textContent = b.name;
    $("#bMeta").textContent = `${b.country} · depuis ${b.est}`;
    $("#bName").textContent = b.name;
    $("#bTagline").textContent = "« " + b.tagline + " »";
    $("#bArt").innerHTML = C.ART.monogram(b.letter, b.tint);
    const artWrap = document.querySelector(".bhero__art");
    if (artWrap) requestAnimationFrame(() => artWrap.classList.add("is-in"));
    $("#bStory").innerHTML = (b.story || []).map(p => `<p>${C.esc(p)}</p>`).join("");
    const roman = ["i.", "ii.", "iii."];
    $("#bValues").innerHTML = (b.values || []).slice(0, 3).map((v, i) =>
      `<div class="bpillar" data-reveal style="--d:${i}"><i>${roman[i]}</i><p>${C.esc(v)}</p></div>`
    ).join("");
    $("#bSignature").textContent = b.signature ? "Collections : " + b.signature : "";
    $("#bQuote").textContent =
      `« Quand une cliente nous demande “quel produit de chez ${b.name} ?”, c’est rarement une question — c’est déjà un choix. Notre travail se limite à confirmer qu’elle a raison. »`;
    $("#bShopAll").href = "shop.html?brand=" + b.slug;

    // Brand collections & filtering — premium (Phase 23)
    const allProds = C.products.filter(p => p.brand === b.name);
    const grid = $("#bProducts");
    const countEl=$("#bCount");
    const collWrap=$("#bCollections");
    const sortSel=$("#bSort");
    let selCat=''; let sort='featured';
    function catsForBrand(){
      const catSlugs=[...new Set(allProds.map(p=>p.cat).filter(Boolean))];
      return catSlugs.map(s=> C.catBySlug(s)).filter(Boolean);
    }
    function renderCollections(){
      if(!collWrap) return;
      const cats=catsForBrand();
      if(!cats.length){ collWrap.style.display='none'; return; }
      collWrap.innerHTML=`<button class="chip ${!selCat?'is-on':''}" data-bcat="">Tous les univers</button>` + cats.map(c=> `<button class="chip ${selCat===c.slug?'is-on':''}" data-bcat="${c.slug}">${c.name}</button>`).join('');
      collWrap.querySelectorAll('[data-bcat]').forEach(btn=> btn.addEventListener('click', ()=>{
        selCat=btn.dataset.bcat;
        renderCollections(); renderGrid();
      }));
    }
    function getFiltered(){
      let list=allProds.slice();
      if(selCat) list=list.filter(p=>p.cat===selCat);
      if(sort==='price-asc') list.sort((a,b)=>a.price-b.price);
      else if(sort==='price-desc') list.sort((a,b)=>b.price-a.price);
      else if(sort==='rating') list.sort((a,b)=>b.rating-a.rating);
      else list.sort((a,b)=> (b.featured?1:0)-(a.featured?1:0) || (b.bestseller||99)-(a.bestseller||99));
      return list;
    }
    function renderGrid(){
      const list=getFiltered();
      if(countEl) countEl.textContent=list.length + (list.length>1?' produits':' produit') + (selCat? ` · ${C.catBySlug(selCat)?.name||selCat}`:'');
      grid.innerHTML = list.length
        ? list.map((p, i) => C.cardHTML(p).replace('class="pcard"', `class="pcard" data-reveal style="--d:${i % 3}"`)).join("")
        : `<p style="grid-column:1/-1;color:var(--muted);border:1px dashed var(--line);border-radius:8px;padding:24px;text-align:center">Aucun produit pour cet univers dans la maison ${C.esc(b.name)} — <a class="link-u" href="shop.html?brand=${b.slug}">voir ${C.esc(b.name)} dans toute la boutique</a></p>`;
      C.observe(grid);
    }
    sortSel?.addEventListener('change', e=>{ sort=e.target.value; renderGrid(); });
    renderCollections(); renderGrid();
    C.observe(document.querySelector(".bstory"));
    C.observe($("#bValues"));
    C.observe(grid.parentElement);
  });
})();

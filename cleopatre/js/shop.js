/* CLÉOPÂTRE — Boutique : filtres, tri, état d’URL */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$, $$ = C.$$;

  const state = {
    cat: C.qs("cat") || "",
    brand: (C.qs("brand") || "").split(",").filter(Boolean),
    concern: C.qs("concern") || "",
    collection: C.qs("collection") || "",
    q: C.qs("q") || "",
    sort: C.qs("sort") || "featured",
    stock: C.qs("stock") === "1",
    min: C.qs("min") || "",
    max: C.qs("max") || "",
    view: localStorage.getItem("cleo_view") || "grid"
  };

  C.onReady(() => {
    renderCatBar();
    renderCollectionBar();
    renderFilters();
    bindToolbar();
    bindDrawer();
    syncControls();
    // apply once with static, then re-apply when catalog hydraté depuis API (prix/stock à jour)
    apply();
    // si hydratation apporte prix plus récent, on re-rend
    document.addEventListener('cleo:catalog', ()=>{ renderFilters(); syncControls(); apply(); });
    // tentative d’hydratation immédiate si global.js expose hydrateCatalog
    if(window.CLEO && window.CLEO.hydrateCatalog){
      window.CLEO.hydrateCatalog().then(()=>{ renderFilters(); syncControls(); apply(); });
    }
    document.querySelector(".catbar").addEventListener("scroll", () => {}, { passive: true });
  });

  /* Barre collections — merchandising (Phase 37) */
  function renderCollectionBar(){
    const bar=document.getElementById('collectionBar');
    if(!bar) return;
    bar.querySelectorAll('[data-collection]').forEach(btn=>{
      btn.classList.toggle('is-on', btn.dataset.collection===state.collection);
      btn.addEventListener('click', ()=>{
        state.collection = btn.dataset.collection;
        bar.querySelectorAll('[data-collection]').forEach(b=> b.classList.toggle('is-on', b===btn));
        apply(true);
      });
    });
  }

  /* Barre des univers */
  function renderCatBar() {
    const bar = $("#catBar");
    const links = [{ slug: "", name: "Tout" }].concat(C.cats);
    bar.innerHTML = links.map(c =>
      `<a class="catlink ${state.cat === c.slug ? "is-on" : ""}" href="#"
        data-cat="${c.slug}" ${c.slug ? `aria-pressed="${state.cat === c.slug}"` : ""}>${c.name}</a>`).join("");
    bar.addEventListener("click", e => {
      const a = e.target.closest("[data-cat]");
      if (!a) return;
      e.preventDefault();
      state.cat = a.dataset.cat;
      bar.querySelectorAll(".catlink").forEach(l => l.classList.toggle("is-on", l.dataset.cat === state.cat));
      apply(true);
    });
  }

  /* Formulaire de filtres */
  function renderFilters() {
    const form = $("#filterForm");
    const brandCounts = {};
    C.products.forEach(p => brandCounts[p.brand] = (brandCounts[p.brand] || 0) + 1);
    form.innerHTML = `
      <fieldset class="fgroup">
        <h4>Univers</h4>
        ${C.cats.map(c => optRow("f-cat", c.slug, c.name)).join("")}
      </fieldset>
      <fieldset class="fgroup">
        <h4>Besoins</h4>
        ${C.concerns.map(c => optRow("f-concern", c.slug, c.name)).join("")}
      </fieldset>
      <fieldset class="fgroup">
        <h4>Marques</h4>
        ${C.brands.filter(b => brandCounts[b.name]).map(b =>
          `<label class="fopt"><input type="checkbox" data-brand="${b.slug}"><span class="box"></span>${C.esc(b.name)}<em>${brandCounts[b.name]}</em></label>`).join("")}
      </fieldset>
      <fieldset class="fgroup">
        <h4>Budget <small>DT</small></h4>
        <div class="price-row">
          <input type="number" inputmode="numeric" id="priceMin" placeholder="min" min="0" step="1" aria-label="Prix minimum en dinars">
          <span>—</span>
          <input type="number" inputmode="numeric" id="priceMax" placeholder="max" min="0" step="1" aria-label="Prix maximum en dinars">
        </div>
      </fieldset>
      <fieldset class="fgroup">
        <h4>Disponibilité</h4>
        <label class="fopt"><input type="checkbox" id="stockOnly"><span class="box"></span>En stock uniquement</label>
      </fieldset>`;
    form.addEventListener("change", () => {
      state.brand = $$("[data-brand]:checked", form).map(i => i.dataset.brand);
      const cats = $$("[data-fcat]:checked", form).map(i => i.value);
      if (cats.length) { state.cat = cats[0]; markCatBar(); }
      const cons = $$("[data-fconcern]:checked", form).map(i => i.value);
      state.concern = cons[0] || "";
      state.stock = $("#stockOnly", form).checked;
      state.min = $("#priceMin", form).value;
      state.max = $("#priceMax", form).value;
      apply(true);
    });
    $("#resetFilters").addEventListener("click", resetAll);
    $("#resetFromEmpty").addEventListener("click", resetAll);

    function optRow(kind, val, label) {
      const attr = kind === "f-cat" ? `data-fcat value="${val}"` : `data-fconcern value="${val}"`;
      return `<label class="fopt"><input type="checkbox" ${attr}><span class="box"></span>${C.esc(label)}</label>`;
    }
  }

  function markCatBar() {
    $$("#catBar .catlink").forEach(l => l.classList.toggle("is-on", l.dataset.cat === state.cat));
  }

  /* Toolbar */
  function bindToolbar() {
    $("#sortSel").addEventListener("change", e => { state.sort = e.target.value; apply(true); });
    $$(".viewtoggle button").forEach(b => b.addEventListener("click", () => {
      state.view = b.dataset.view;
      localStorage.setItem("cleo_view", state.view);
      $$(".viewtoggle button").forEach(x => x.classList.toggle("is-on", x === b));
      $("#productGrid").classList.toggle("is-list", state.view === "list");
    }));
  }

  /* Tiroir filtres mobile */
  function bindDrawer() {
    const panel = $("#filtersPanel"), scrim = $("[data-scrim]"), btn = $("[data-filters-open]");
    const open = () => { panel.classList.add("is-open"); scrim.classList.add("is-on"); btn.setAttribute("aria-expanded", "true"); };
    const close = () => { panel.classList.remove("is-open"); scrim.classList.remove("is-on"); btn.setAttribute("aria-expanded", "false"); };
    btn.addEventListener("click", open);
    $(".filters__close", panel).addEventListener("click", close);
    scrim.addEventListener("click", close);
    addEventListener("keydown", e => e.key === "Escape" && close());
  }

  function syncControls() {
    $$("#filterForm [data-fcat]").forEach(i => i.checked = i.value === state.cat);
    $$("#filterForm [data-fconcern]").forEach(i => i.checked = i.value === state.concern);
    $$("#filterForm [data-brand]").forEach(i => i.checked = state.brand.includes(i.dataset.brand));
    const st = $("#stockOnly"); if (st) st.checked = state.stock;
    if ($("#priceMin")) $("#priceMin").value = state.min;
    if ($("#priceMax")) $("#priceMax").value = state.max;
    $("#sortSel").value = state.sort;
    $$(".viewtoggle button").forEach(x => x.classList.toggle("is-on", x.dataset.view === state.view));
    $("#productGrid").classList.toggle("is-list", state.view === "list");
    markCatBar();
    const cbar=document.getElementById('collectionBar');
    if(cbar) cbar.querySelectorAll('[data-collection]').forEach(b=> b.classList.toggle('is-on', b.dataset.collection===state.collection));
  }

  function resetAll() {
    Object.assign(state, { cat: "", brand: [], concern: "", collection:"", q: "", sort: "featured", stock: false, min: "", max: "" });
    syncControls();
    apply(true);
  }

  /* Filtrage + rendu — collections incluses (Phase 37) */
  function activeFilterCount() {
    let n = 0;
    if (state.cat) n++;
    if (state.concern) n++;
    if (state.collection) n++;
    if (state.stock) n++;
    n += state.brand.length;
    if (state.min) n++;
    if (state.max) n++;
    if (state.q) n++;
    return n;
  }

  function apply(pushUrl) {
    let list = C.products.slice();
    if (state.cat) list = list.filter(p => p.cat === state.cat);
    if (state.brand.length) list = list.filter(p => state.brand.includes(C.slugify(p.brand)));
    if (state.concern) list = list.filter(p => (p.concerns || []).includes(state.concern));
    if (state.collection){
      if(state.collection==='routine-hydratation') list = list.filter(p=> (p.concerns||[]).includes('hydration') || p.featured);
      else if(state.collection==='essentiels-spf') list = list.filter(p=> (p.concerns||[]).includes('solaire'));
      else if(state.collection==='incontournables') list = list.filter(p=> p.featured || p.bestseller);
    }
    if (state.stock) list = list.filter(p => p.stock);
    if (state.q) list = C.searchProducts(state.q).filter(p => list.includes(p));
    const toMm = v => Math.round(parseFloat(v) * 1000);
    if (state.min !== "") list = list.filter(p => p.price >= toMm(state.min));
    if (state.max !== "") list = list.filter(p => p.price <= toMm(state.max));

    switch (state.sort) {
      case "price-asc": list.sort((a, b) => a.price - b.price); break;
      case "price-desc": list.sort((a, b) => b.price - a.price); break;
      case "rating": list.sort((a, b) => b.rating - a.rating); break;
      case "promo": list.sort((a, b) => C.off(b) - C.off(a)); break;
      case "name": list.sort((a, b) => a.name.localeCompare(b.name, "fr")); break;
      default: list.sort((a, b) => ((b.featured ? 1 : 0) - (a.featured ? 1 : 0)) || ((b.bestseller || 99) - (a.bestseller || 99)));
    }

    const filtered = !!(state.cat || state.concern || state.collection || state.brand.length || state.stock || state.min || state.max || state.q);

    const grid = $("#productGrid");
    const frag = [];
    list.forEach((p, i) => {
      if (!filtered && i === 6) frag.push(C.tileHTML());
      frag.push(C.cardHTML(p).replace('class="pcard"', `class="pcard" style="--i:${i % 12}"`));
    });
    grid.innerHTML = frag.join("");
    $("#noResult").hidden = list.length > 0;

    const cnt = $("#resultCount") || $("[data-result-count]");
    cnt.textContent = list.length + " référence" + (list.length > 1 ? "s" : "");
    const fc = $("[data-filter-count]");
    const nf = activeFilterCount();
    fc.hidden = nf === 0; fc.textContent = nf;

    if (pushUrl) C.setQs({
      cat: state.cat, brand: state.brand.join(",") || null, concern: state.concern || null, collection: state.collection || null,
      sort: state.sort === "featured" ? null : state.sort,
      stock: state.stock ? "1" : null, min: state.min || null, max: state.max || null, q: state.q || null
    });
  }
})();

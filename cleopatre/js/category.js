/* CLÉOPÂTRE — Univers : direction artistique propre à chaque catégorie */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$, $$ = C.$$;

  /* Manifestes éditoriaux par univers */
  const MANIFESTO = {
    visage: "Le visage ne ment jamais sur la régularité d’une routine. Nous privilégions les formules courtes, les actifs documentés et les textures que l’on garde envie de réappliquer.",
    corps: "Une peau de corps entretenue est une mémoire tactile du quotidien. Huiles qui se fondent, baumes qui tiennent, parfums qui restent proches : le luxe discret.",
    cheveux: "Cheveux traités comme une matière noble : diagnostic avant produit, douceur avant performance, botanique quand elle est prouvée.",
    bebe: "La peau d’un nourrisson ne se risque pas. Formules minimales, contrôle pédiatrique, gestes appris auprès des sages-femmes — rien d’autre.",
    sante: "Un complément sérieux annonce ce qu’il contient, à quelle dose, et pour quoi faire. Nous refusons tout le reste.",
    "bien-etre": "Prendre soin n’est pas une dépense : c’est une hygiène. Huiles, eaux et rituels choisis pour transformer dix minutes en parenthèse."
  };
  const SEL_TITLES = {
    visage: ["Les essentiels du", "visage."],
    corps: ["Le corps, entretenu", "comme un jardin."],
    cheveux: ["La fibre capillaire,", "traitée en clinique."],
    bebe: ["Les premiers soins,", "les plus doux."],
    sante: ["L’essentiel santé,", "rigoureusement."],
    "bien-etre": ["Les rituels du calme,", "en flacons."]
  };

  let CAT = null, CONCERN = "", SORT = C.qs("sort") || "featured";

  C.onReady(() => {
    const slug = C.qs("cat");
    CAT = C.catBySlug(slug) || C.cats[0];
    if (!C.catBySlug(slug)) {
      location.replace(C.PAGES + "category.html?cat=" + CAT.slug);
      return;
    }
    CONCERN = C.qs("concern") || "";
    document.title = CAT.name + " — Sélection experte | Cléopâtre Parapharmacie";
    render();
    bindChips();
    bindSort();
  });

  function render() {
    const root = document.body;
    root.style.setProperty("--cat-bg", CAT.surface);
    root.style.setProperty("--cat-accent", CAT.accent);

    $("#crumbCat").textContent = CAT.name;
    $("#catEyebrow").textContent = CAT.eyebrow + " · Univers";
    $("#catName").innerHTML = `${CAT.name},<br><em class="serif-i">${CAT.tagline}</em>`;
    $("#catIntro").textContent = CAT.intro;
    $("#catTagline").textContent = "« " + CAT.description + " »";
    $("#catManifesto").textContent = MANIFESTO[CAT.slug] || CAT.intro;
    $("#selTitle").innerHTML = SEL_TITLES[CAT.slug]
      ? `${SEL_TITLES[CAT.slug][0]} <em class="serif-i">${SEL_TITLES[CAT.slug][1]}</em>`
      : "Notre sélection.";
    (function(){
      const pos={ "visage":"60% center","corps":"55% center","cheveux":"62% 30%","bebe":"48% 35%","sante":"52% center","bien-etre":"58% center" };
      const img = CAT.image || `assets/images/univers/${CAT.slug}.webp`;
      const src = (C.ROOT || "") + img;
      const alt = `Univers ${CAT.name}`;
      const p = pos[CAT.slug] || "50% center";
      const el=document.getElementById("catArt");
      if(el) el.innerHTML = `<img src="${src}" alt="${C.esc(alt)}" loading="eager" decoding="async" style="object-position:${p}" onerror="this.style.display='none';this.nextElementSibling.style.display='block'"><span style="display:none;width:100%;height:100%">${C.ART.scene([CAT.surface, "#D9CBB0", CAT.accent], { bg: CAT.surface })}</span>`;
    })();

    /* Chips besoins */
    const chips = $("#concernChips");
    chips.innerHTML =
      `<button class="chip ${!CONCERN ? "is-on" : ""}" data-concern="" aria-pressed="${!CONCERN}">Tout</button>` +
      C.concerns.map(c => `<button class="chip ${CONCERN === c.slug ? "is-on" : ""}" data-concern="${c.slug}" aria-pressed="${CONCERN === c.slug}">${c.name}</button>`).join("");
    $("#sortSel").value = SORT;
    apply();
  }

  function bindChips() {
    $("#concernChips").addEventListener("click", e => {
      const b = e.target.closest("[data-concern]");
      if (!b) return;
      CONCERN = b.dataset.concern;
      $$("#concernChips .chip").forEach(x => {
        const on = x.dataset.concern === CONCERN;
        x.classList.toggle("is-on", on);
        x.setAttribute("aria-pressed", String(on));
      });
      C.setQs({ concern: CONCERN || null });
      apply();
    });
    $("#clearConcern").addEventListener("click", () => {
      CONCERN = "";
      $$("#concernChips .chip").forEach(x => x.classList.toggle("is-on", !x.dataset.concern));
      C.setQs({ concern: null });
      apply();
    });
  }

  function bindSort() {
    $("#sortSel").addEventListener("change", e => { SORT = e.target.value; apply(); });
  }

  function apply() {
    let list = C.products.filter(p => p.cat === CAT.slug);
    if (CONCERN) list = list.filter(p => (p.concerns || []).includes(CONCERN));
    switch (SORT) {
      case "price-asc": list.sort((a, b) => a.price - b.price); break;
      case "price-desc": list.sort((a, b) => b.price - a.price); break;
      case "rating": list.sort((a, b) => b.rating - a.rating); break;
      default: list.sort((a, b) => ((b.featured ? 1 : 0) - (a.featured ? 1 : 0)) || ((b.bestseller || 99) - (a.bestseller || 99)));
    }
    const grid = $("#catGrid");
    grid.innerHTML = list.map((p, i) =>
      C.cardHTML(p).replace('class="pcard"', `class="pcard${i === 0 && !CONCERN && list.length > 3 ? " pcard--lead" : ""}" style="--i:${i % 10}"`)).join("");
    $("#noResult").hidden = list.length > 0;
  }
})();

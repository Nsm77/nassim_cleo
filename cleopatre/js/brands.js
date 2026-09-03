/* CLÉOPÂTRE — Répertoire des marques */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$, $$ = C.$$;

  let letter = "", query = "";

  C.onReady(() => {
    renderFeatured();
    renderAZ();
    renderDir();
    $("#brandSearch").addEventListener("input", C.debounce(e => {
      query = e.target.value.trim().toLowerCase();
      renderDir();
    }, 140));
  });

  function countFor(b) {
    return C.products.filter(p => p.brand === b.name).length;
  }

  function renderFeatured() {
    const strip = $("#featStrip");
    if (!strip) return;
    strip.innerHTML = C.brands.filter(b => b.featured).slice(0, 4).map(b => `
      <a class="fcard" href="brand.html?brand=${b.slug}" style="--brand-tint:${b.tint}" aria-label="${C.esc(b.name)}">
        <span class="art">${C.ART.monogram(b.letter, b.tint)}</span>
        <small>${b.country} · ${b.est}</small>
        <h3>${C.esc(b.name)}</h3>
        <p>${C.esc(b.tagline)}</p>
      </a>`).join("");
    strip.querySelectorAll(".fcard").forEach((el, i) => setTimeout(() => el.classList.add("is-in"), 80 + i * 100));
  }

  function renderAZ() {
    const letters = [...new Set(C.brands.map(b => b.letter))].sort();
    const nav = $("#azNav");
    nav.innerHTML =
      `<button class="az-btn is-on" data-letter="" aria-pressed="true">Tous</button>` +
      "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("").map(l => {
        const has = letters.includes(l);
        return `<button class="az-btn" data-letter="${l}" ${has ? "" : "disabled"} aria-pressed="false">${l}</button>`;
      }).join("");
    nav.addEventListener("click", e => {
      const b = e.target.closest("[data-letter]");
      if (!b || b.disabled) return;
      letter = b.dataset.letter;
      $$(".az-btn", nav).forEach(x => {
        const on = x === b;
        x.classList.toggle("is-on", on);
        x.setAttribute("aria-pressed", String(on));
      });
      renderDir();
    });
  }

  function renderDir() {
    const listEl = $("#dirList");
    let brands = C.brands.slice();
    if (query) brands = brands.filter(b => (b.name + " " + b.tagline + " " + b.country).toLowerCase().includes(query));
    else if (letter) brands = brands.filter(b => b.letter === letter);

    if (!brands.length) { listEl.innerHTML = ""; $("#noBrand").hidden = false; return; }
    $("#noBrand").hidden = true;

    const groups = {};
    brands.forEach(b => (groups[b.letter] = groups[b.letter] || []).push(b));

    listEl.innerHTML = Object.keys(groups).sort().map(l => `
      <section class="lgroup" data-reveal>
        <span class="lletter" aria-hidden="true">${l}</span>
        <div class="lrows">
          ${groups[l].map(b => `
            <a class="brow" href="brand.html?brand=${b.slug}">
              <span class="brow__mono">${C.ART.monogram(b.letter, b.tint)}</span>
              <span><small>${b.country} · depuis ${b.est} · ${countFor(b)} référence${countFor(b) > 1 ? "s" : ""}</small>
                <h3>${C.esc(b.name)}</h3>
                <p>${C.esc(b.tagline)}</p></span>
              <span class="link-u">Découvrir<span class="arr">→</span></span>
            </a>`).join("")}
        </div>
      </section>`).join("");
    C.observe(listEl);
  }
})();

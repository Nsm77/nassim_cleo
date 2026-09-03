/* CLÉOPÂTRE — Recherche */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$;

  function highlight(text, q) {
    if (!q) return C.esc(text);
    const safe = q.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    return C.esc(text).replace(new RegExp("(" + safe + ")", "gi"), "<mark>$1</mark>");
  }

  C.onReady(() => {
    const input = $("#qInput");
    const q = (C.qs("q") || "").trim();
    input.value = q;
    document.title = (q ? "« " + q + " » — recherche" : "Recherche") + " | Cléopâtre Parapharmacie";

    let recent = [];
    try { recent = JSON.parse(localStorage.getItem("cleo_recent_v1")) || []; } catch (e) { console.warn("[search] recent parse failed", e); }
    if (q && !recent.includes(q)) {
      recent.unshift(q);
      try { localStorage.setItem("cleo_recent_v1", JSON.stringify(recent.slice(0, 5))); } catch (e) { console.warn("[search] recent save failed", e); }
    }

    run(q);

    let t;
    input.addEventListener("input", () => {
      clearTimeout(t);
      t = setTimeout(() => {
        const v = input.value.trim();
        C.setQs({ q: v || null });
        run(v);
      }, 220);
    });
  });

  function run(q) {
    const res = $("#sResults"), empty = $("#sEmpty");
    $("#sCount").textContent = "";
    if (!q) {
      res.innerHTML = ""; empty.hidden = false; renderBrowse(); return;
    }
    const prods = C.searchProducts(q);
    const arts = C.articles.filter(a => (a.title + a.excerpt + a.rubrique).toLowerCase().includes(q.toLowerCase()));
    const brs = C.brands.filter(b => b.name.toLowerCase().includes(q.toLowerCase()));

    if (!prods.length && !arts.length && !brs.length) {
      res.innerHTML = ""; empty.hidden = false; renderBrowse(); return;
    }
    empty.hidden = true;

    let html = `<p class="count" style="margin-bottom:clamp(24px,3vw,38px)">
      ${prods.length} produit${prods.length > 1 ? "s" : ""}${arts.length ? ` · ${arts.length} article${arts.length > 1 ? "s" : ""}` : ""}${brs.length ? ` · ${brs.length} marque${brs.length > 1 ? "s" : ""}` : ""}
    </p>`;
    if (prods.length) {
      html += `<div class="pgrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(250px,100%),1fr));gap:clamp(24px,3vw,40px) clamp(18px,2vw,28px)">` +
        prods.map((p, i) =>
          C.cardHTML(p).replace('class="pcard"', `class="pcard" data-reveal style="--d:${i % 8}"`)
        ).join("") + `</div>`;
    }
    if (brs.length) {
      html += `<div style="margin-top:clamp(30px,4vw,48px)" data-reveal><h4 class="num-label"><i>M</i>Marques</h4>
        <div class="chips" style="justify-content:flex-start;margin-top:14px">${brs.map(b =>
          `<a class="chip" href="${C.PAGES}brand.html?brand=${b.slug}">${highlight(b.name, q)}</a>`).join("")}</div></div>`;
    }
    if (arts.length) {
      html += `<div style="margin-top:clamp(30px,4vw,48px)" data-reveal><h4 class="num-label"><i>J</i>Le journal</h4>
        <ul style="margin-top:14px;display:grid;gap:14px">${arts.map(a =>
          `<li><a href="${C.PAGES}conseil.html?id=${a.id}" style="font-family:var(--serif);font-size:1.3rem">${highlight(a.title, q)}</a></li>`).join("")}</ul></div>`;
    }
    res.innerHTML = html;
    C.observe(res);
    $("#sCount").textContent = `${prods.length + brs.length + arts.length} résultat${prods.length + brs.length + arts.length > 1 ? "s" : ""}`;
  }

  function renderBrowse() {
    $("#sBrowse").innerHTML = `
      <div><h4>Univers</h4><ul>${C.cats.map(c =>
        `<li><a href="${C.PAGES}category.html?cat=${c.slug}">${c.name}</a></li>`).join("")}</ul></div>
      <div><h4>Suggestions</h4><ul>${["Anthelios", "Eau micellaire", "Huile Prodigieuse", "Anti-taches", "Bébé"].map(s =>
        `<li><a href="${C.PAGES}search.html?q=${encodeURIComponent(s)}">${s}</a></li>`).join("")}</ul></div>`;
  }
})();

/* CLÉOPÂTRE — Journal */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$;

  let rub = "";

  const art = a => C.ART.scene(["#EAE3D6", a.tint, "#D9CBB0"], { bg: a.tint });
  const fmtDate = iso => new Date(iso).toLocaleDateString("fr-FR", { day: "2-digit", month: "long", year: "numeric" });

  C.onReady(() => {
    renderFeature();
    renderChips();
    renderGrid();
  });

  function renderFeature() {
    const f = C.articles[0];
    $("#featureWrap").innerHTML = `
      <a class="jfeat" href="conseil.html?id=${f.id}" data-reveal>
        <span class="art" aria-hidden="true">${art(f)}</span>
        <span class="jfeat__body">
          <span class="jfeat__meta">À la une · ${f.rubrique} · ${fmtDate(f.date)} · ${f.readTime} min de lecture</span>
          <h3>${f.title}</h3>
          <p style="color:rgba(245,241,233,.85);max-width:52ch;margin-top:12px">${f.excerpt}</p>
        </span>
      </a>`;
    C.observe($("#featureWrap"));
  }

  function renderChips() {
    const rubs = ["Tout"].concat([...new Set(C.articles.map(a => a.rubrique))]);
    $("#rubChips").innerHTML = rubs.map(r =>
      `<button class="chip ${r === "Tout" ? "is-on" : ""}" data-rub="${r === "Tout" ? "" : r}" aria-pressed="${r === "Tout"}">${r}</button>`).join("");
    $("#rubChips").addEventListener("click", e => {
      const b = e.target.closest("[data-rub]");
      if (!b) return;
      rub = b.dataset.rub;
      $$("#rubChips .chip").forEach(x => {
        const on = x.dataset.rub === rub;
        x.classList.toggle("is-on", on);
        x.setAttribute("aria-pressed", String(on));
      });
      renderGrid();
    });
  }

  function renderGrid() {
    let list = C.articles.slice(1);
    if (rub) list = list.filter(a => a.rubrique === rub);
    if (!list.length) list = C.articles.filter(a => a.rubrique === rub);
    const countEl = $("#jCount");
    if (countEl) countEl.textContent = list.length + (list.length > 1 ? " lectures" : " lecture");
    const grid = $("#jGrid");
    if (!grid) return;
    grid.innerHTML = list.map(a => `
      <article class="ccard">
        <a class="ccard__media" href="conseil.html?id=${a.id}" aria-hidden="true" tabindex="-1"><span class="art">${art(a)}</span></a>
        <div class="ccard__body">
          <span class="ccard__meta">${a.rubrique} · ${fmtDate(a.date)} · ${a.readTime} min</span>
          <h3><a href="conseil.html?id=${a.id}">${a.title}</a></h3>
          <p>${a.excerpt}</p>
          <a class="link-u" href="conseil.html?id=${a.id}">Lire l’article<span class="arr">→</span></a>
        </div>
      </article>`).join("");
    grid.querySelectorAll(".ccard").forEach((el, i) => setTimeout(() => el.classList.add("is-in"), 60 + i * 90));
  }
})();

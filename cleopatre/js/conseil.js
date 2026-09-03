/* CLÉOPÂTRE — Article */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$;

  C.onReady(() => {
    const a = C.articleById(C.qs("id")) || C.articles[0];
    if (!C.articleById(C.qs("id"))) { location.replace(C.PAGES + "conseils.html"); return; }
    document.title = a.title + " — Le journal Cléopâtre";
    const md = document.querySelector('meta[name="description"]');
    if (md) md.setAttribute("content", a.excerpt);

    injectJSONLD(a);
    renderHead(a);
    renderBody(a);
    renderProducts(a);
    renderNext(a);
    readProgress();
  });

  function injectJSONLD(a) {
    const ld = {
      "@context": "https://schema.org", "@type": "Article",
      headline: a.title, description: a.excerpt,
      datePublished: a.date, author: { "@type": "Organization", name: "Parapharmacie Cléopâtre" },
      publisher: { "@type": "Organization", name: "Parapharmacie Cléopâtre" }
    };
    const s = document.createElement("script");
    s.type = "application/ld+json";
    s.textContent = JSON.stringify(ld);
    document.head.appendChild(s);
  }

  const fmtDate = iso => new Date(iso).toLocaleDateString("fr-FR", { day: "2-digit", month: "long", year: "numeric" });

  function renderHead(a) {
    $("#aHead").innerHTML = `
      <nav class="crumbs" aria-label="Fil d’Ariane"><a href="../index.html">Accueil</a><i>/</i>
        <a href="conseils.html">Le journal</a><i>/</i><span aria-current="page">${a.rubrique}</span></nav>
      <div class="ahead__meta"><span>${a.rubrique}</span><span>${fmtDate(a.date)}</span><span>${a.readTime} min de lecture</span></div>
      <h1 class="display-1">${a.title}</h1>
      <p class="standfirst">${a.excerpt}</p>
      <div class="ahead__art" data-reveal>${C.ART.scene(["#EAE3D6", a.tint, "#D9CBB0"], { bg: a.tint })}</div>`;
    C.observe($("#aHead"));
  }

  function renderBody(a) {
    const body = $("#aBody");
    let html = "";
    a.body.forEach((p, i) => {
      html += `<p>${p}</p>`;
      if (i === 1 && a.points.length) {
        html += `<div class="keybox"><h4>À retenir</h4><ul>${a.points.map(pt => `<li>${pt}</li>`).join("")}</ul></div>`;
      }
      if (i === Math.floor(a.body.length / 2)) {
        html += `<blockquote class="pullquote serif">« En cas de doute, revenez au simple : nettoyer, protéger, observer. La peau raconte tout si on l’écoute. »</blockquote>`;
      }
    });
    html += `
      <div class="byline">
        <span class="mono" aria-hidden="true">A</span>
        <span><b>Rédigé par l’équipe des pharmaciennes</b><span>Cléopâtre — 12 Av. Habib Bourguiba, Tunis · relu avant publication</span></span>
      </div>`;
    body.innerHTML = html;
  }

  function renderProducts(a) {
    const grid = $("#aProducts");
    grid.innerHTML = (a.related || []).map(C.byId).filter(Boolean).slice(0, 3)
      .map((p, i) => C.cardHTML(p).replace('class="pcard"', `class="pcard" data-reveal style="--d:${i}"`)).join("");
    C.observe(grid);
  }

  function renderNext(cur) {
    const others = C.articles.filter(x => x.id !== cur.id).sort((x, y) => (x.rubrique === cur.rubrique ? -1 : 1)).slice(0, 3);
    $("#nextReads").innerHTML =
      `<span class="num-label" style="grid-column:1/-1;margin-bottom:6px" data-reveal><i>→</i>Lire ensuite</span>` +
      others.map((x, i) => `
        <a class="nr-item" href="conseil.html?id=${x.id}" data-reveal style="--d:${i}">
          <span class="art" aria-hidden="true">${C.ART.scene(["#EAE3D6", x.tint, "#D9CBB0"], { bg: x.tint })}</span>
          <span><small>${x.rubrique} · ${x.readTime} min</small><h4>${x.title}</h4></span>
        </a>`).join("");
    C.observe($("#nextReads"));
  }

  function readProgress() {
    const bar = $("#readBar");
    if(!bar) return;
    let ticking=false;
    addEventListener("scroll", () => {
      if(ticking) return;
      ticking=true;
      requestAnimationFrame(()=>{
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const p = max>0 ? (window.scrollY / max) : 0;
        bar.style.transform = `scaleX(${p.toFixed(4)})`;
        ticking=false;
      });
    }, { passive: true });
  }
})();

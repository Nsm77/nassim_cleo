/* CLÉOPÂTRE — Favoris */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$;

  C.onReady(() => {
    render();
    C.wish.subscribe(render);
  });

  function render() {
    const zone = $("#wishZone");
    const ids = C.wish.items.map(id => C.byId(id)).filter(Boolean);

    if (!ids.length) {
      zone.innerHTML = `
        <div class="wempty" data-reveal>
          <div class="wempty__art" aria-hidden="true">${C.ART.scene(["#EBE4D5", "#D9CBB0", "#B39B72"], { bg: "#EFE9DC" })}</div>
          <div>
            <span class="num-label"><i>♡</i>Une collection en germe</span>
            <h2 style="margin-top:16px">Chaque rituel commence<br><em class="serif-i">par un coup de cœur.</em></h2>
            <p>Parcourez la boutique et touchez le cœur des produits qui vous parlent.
            Ils patienteront ici, tranquillement, jusqu’à votre prochaine visite.</p>
            <a class="btn btn--ink" href="${C.PAGES}shop.html">Trouver mon premier coup de cœur<span class="arr">→</span></a>
          </div>
        </div>`;
      document.getElementById("inspoSection").style.display = "";
      renderInspo();
    } else {
      document.getElementById("inspoSection").style.display = "none";
      zone.innerHTML =
        `<div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:clamp(22px,3vw,34px)">
           <span class="count">${ids.length} favori${ids.length > 1 ? "s" : ""}</span>
           <button class="link-u" id="wishAllAdd">Tout ajouter au panier<span class="arr">→</span></button>
         </div>
         <div class="wgrid">${ids.map(p => C.cardHTML(p).replace('class="pcard"', `class="pcard"`)).join("")}</div>`;
      $("#wishAllAdd").addEventListener("click", () => {
        ids.filter(p => p.stock).forEach(p => C.cart.add(p.id));
        C.toast(ids.length + (ids.length > 1 ? " favoris ajoutés au panier" : " favori ajouté au panier"), "", "Voir le panier", () => C.openCart());
      });
    }
    C.observe(zone);
  }

  function renderInspo() {
    const grid = $("#inspoGrid");
    grid.innerHTML = C.products.filter(p => p.bestseller).slice(0, 3)
      .map((p, i) => C.cardHTML(p).replace('class="pcard"', `class="pcard" data-reveal style="--d:${i}"`)).join("");
    C.observe(grid);
  }
})();

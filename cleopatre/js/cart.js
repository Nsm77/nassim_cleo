/* CLÉOPÂTRE — Page panier */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$;

  let promo = null; // {code, pct}
  const CODES = { "CLEO10": { pct: .10, label: "−10 % première commande" }, "BIENVENUE": { pct: .05, label: "−5 % bienvenue" } };

  C.onReady(() => {
    render();
    C.cart.subscribe(render);
    renderCross();
  });

  function items() {
    return C.cart.items.map(({ id, qty }) => ({ p: C.byId(id), qty })).filter(x => x.p);
  }

  function render() {
    const list = $("#cartList"), sum = $("#cartSummary");
    const rows = items();

    if (!rows.length) {
      list.innerHTML = `
        <div class="cart-hero-empty">
          <div style="width:150px">${C.ART.scene(["#E4DBC9", "#D9CBB0", "#B39B72"], {})}</div>
          <p class="serif-i">Votre sélection est encore une page blanche.</p>
          <p style="color:var(--muted);font-size:.92rem">Nos rayons regorgent de rituels à composer — commencez par le commencement.</p>
          <a class="btn btn--ink" href="${C.PAGES}shop.html">Explorer la boutique<span class="arr">→</span></a>
        </div>`;
      sum.innerHTML = "";
      document.getElementById("crossTitle").closest(".section").style.display = "";
      renderCross(true);
      return;
    }

    list.innerHTML = rows.map(({ p, qty }) => {
      const d = C.off(p);
      return `<article class="cline">
        <a class="cline__art" href="${C.PAGES}product.html?id=${encodeURIComponent(p.id)}" aria-hidden="true" tabindex="-1">${(p.imageThumb || p.image) ? `<img src="${C.ROOT + (p.imageThumb || p.image)}" alt="" class="pcard-photo pcard-photo--thumb" loading="lazy"/>` : C.ART.front(p)}</a>
        <div>
          <span class="cline__brand">${C.esc(p.brand)}</span>
          <h3 class="cline__name"><a href="${C.PAGES}product.html?id=${encodeURIComponent(p.id)}">${C.esc(p.name)}</a></h3>
          <p class="cline__meta">${C.esc(p.size || "")} · ${C.esc((C.catBySlug(p.cat) || {}).name || "")}</p>
          <p class="cline__price"><span>${C.fmt(p.price * qty)}</span>
            ${p.oldPrice ? `<s class="cline__old">${C.fmt(p.oldPrice * qty)}</s><span class="cline__off">−${d}%</span>` : ""}</p>
          <button class="cline__remove" data-remove="${p.id}">Retirer</button>
        </div>
        <div class="cline__right">
          <div class="qty qty--lg" aria-label="Quantité de ${C.esc(p.name)}">
            <button data-dec="${p.id}" aria-label="Diminuer">−</button><output>${qty}</output><button data-inc="${p.id}" aria-label="Augmenter">+</button>
          </div>
        </div>
      </article>`;
    }).join("");

    /* Résumé */
    const sub = C.cart.subtotal();
    const ship = sub >= C.FREE_SHIP ? 0 : 8000;
    const discount = promo ? Math.round(sub * promo.pct) : 0;
    const total = Math.max(0, sub - discount + (ship ? ship - 0 : 0));
    const remain = Math.max(0, C.FREE_SHIP - sub);

    sum.innerHTML = `
      <h3>Récapitulatif</h3>
      <div class="srow"><span>Sous-total (${rows.reduce((n, r) => n + r.qty, 0)} article${rows.length > 1 ? "s" : ""})</span><b>${C.fmt(sub)}</b></div>
      ${discount ? `<div class="srow"><span>Code <em>${promo.code}</em></span><b style="color:var(--green)">−${C.fmt(discount)}</b></div>` : ""}
      <div class="srow"><span>Livraison</span><b>${ship === 0 ? "<em style='color:var(--green);font-style:normal'>Offerte</em>" : C.fmt(ship)}</b></div>
      <div class="srow total"><span>Total</span><b>${C.fmt(total)}</b></div>
      <form class="promo-row" id="promoForm">
        <input type="text" placeholder="Code promotionnel" aria-label="Code promotionnel" value="${promo ? promo.code : ""}">
        <button type="submit">Appliquer</button>
      </form>
      <p class="promo-msg ${promo ? "ok" : ""}" id="promoMsg">${promo ? promo.label + " appliqué." : remain > 0 ? `Plus que ${C.fmt(remain)} pour la livraison offerte.` : "Livraison offerte débloquée."}</p>
      <button class="btn btn--green" id="checkoutBtn">Finaliser la commande<span class="arr">→</span></button>
      <p class="after-pay">Paiement à la réception disponible · échanges sous 14 jours</p>`;

    bindList(list);
    bindSummary(sum);
  }

  function bindList(scope) {
    scope.addEventListener("click", e => {
      const inc = e.target.closest("[data-inc]");
      if (inc) { const r = C.cart.items.find(i => i.id === inc.dataset.inc); r && C.cart.setQty(r.id, r.qty + 1); return; }
      const dec = e.target.closest("[data-dec]");
      if (dec) { const r = C.cart.items.find(i => i.id === dec.dataset.dec); if (r) r.qty > 1 ? C.cart.setQty(r.id, r.qty - 1) : C.cart.remove(r.id); return; }
      const rem = e.target.closest("[data-remove]");
      if (rem) C.cart.remove(rem.dataset.remove);
    });
  }

  function bindSummary(sum) {
    $("#promoForm", sum).addEventListener("submit", async e => {
      e.preventDefault();
      const code = e.target.querySelector("input").value.trim().toUpperCase();
      const msg = $("#promoMsg");
      if (!code) { promo = null; render(); return; }
      // essayer validation serveur d'abord
      const sub = C.cart.subtotal();
      try {
        if (window.CLEO_API) {
          const res = await window.CLEO_API.cart.validatePromo(code, sub);
          const p = res.data.promotion;
          // convertir en pct pour compatibilité
          let pct = 0, label = "Code appliqué";
          if (p.type === "percentage") { pct = p.value/100; label = `−${p.value}%`; }
          else { pct = p.discount_preview / sub; label = `−${C.fmt(p.discount_preview)}`; }
          promo = { code: p.code, pct, label, server: p };
          render();
          return;
        }
      } catch (err) {
        msg.textContent = err.message || "Code invalide.";
        msg.className = "promo-msg ko";
        return;
      }
      const found = CODES[code];
      if (found) { promo = { code, ...found }; }
      else { promo = null; msg.textContent = "Ce code n’est pas reconnu — vérifiez l’orthographe."; msg.className = "promo-msg ko"; setTimeout(render, 1600); return; }
      render();
    });
    $("#checkoutBtn", sum).addEventListener("click", async () => {
      if (!C.cart.items.length) return;
      if (window.CLEO_API && !window.CLEO_API.isAuthenticated()) {
        const redirect = encodeURIComponent(C.PAGES + "checkout.html");
        location.replace(C.PAGES + "login.html?redirect=" + redirect);
        return;
      }
      location.href = C.PAGES + "checkout.html";
    });
  }

  function renderCross(empty) {
    const grid = $("#crossGrid");
    const inCart = new Set(C.cart.items.map(i => i.id));
    const pool = empty
      ? C.products.filter(p => p.bestseller)
      : C.products.filter(p => !inCart.has(p.id) && !p.bestseller && (p.concerns || []).length);
    grid.innerHTML = pool.slice(0, 3).map((p, i) =>
      C.cardHTML(p).replace('class="pcard"', `class="pcard" data-reveal style="--d:${i}"`)).join("");
    C.observe(grid);
  }
})();

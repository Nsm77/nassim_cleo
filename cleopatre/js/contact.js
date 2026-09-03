/* CLÉOPÂTRE — Contact */
(function () {
  "use strict";
  const C = window.CLEO;
  const $ = C.$, $$ = C.$$;

  C.onReady(() => {
    bindForm();
    renderShipFAQ();
  });

  function bindForm() {
    const form = $("#contactForm");
    form.addEventListener("submit", async e => {
      e.preventDefault();
      let ok = true;
      ok &= check("cfName", v => v.trim().length >= 2, "Votre nom nous manque.");
      ok &= check("cfEmail", v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()), "Cette adresse semble incomplète.");
      ok &= check("cfMsg", v => v.trim().length >= 10, "Dites-nous en un peu plus (10 caractères au moins).");
      if (!ok) return;
      const btn = form.querySelector('button[type="submit"]');
      const orig = btn ? btn.textContent : "";
      if (btn) { btn.disabled = true; btn.textContent = "Envoi…"; }
      try {
        if (window.CLEO_API) {
          await window.CLEO_API.contact.submit({
            name: $("#cfName").value.trim(),
            email: $("#cfEmail").value.trim(),
            phone: $("#cfPhone").value.trim(),
            subject: $("#cfSubject").value,
            message: $("#cfMsg").value.trim()
          });
        }
        form.hidden = true;
        $("#formDone").hidden = false;
      } catch (ex) {
        const msg = ex.message || "Erreur lors de l’envoi. Veuillez réessayer.";
        // afficher erreur sous le formulaire
        let box = form.querySelector(".auth-error");
        if (!box) {
          box = document.createElement("div");
          box.className = "auth-error is-on";
          box.style.cssText = "background:#FBE9E1;color:#8A3A1F;padding:10px 14px;border-radius:4px;font-size:.84rem;margin-bottom:12px";
          form.prepend(box);
        }
        box.textContent = msg;
        box.classList.add("is-on");
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = orig; }
      }
    });
    $("#formAgain").addEventListener("click", () => {
      form.reset();
      $$(".field", form).forEach(f => f.classList.remove("is-invalid"));
      $("#formDone").hidden = true;
      form.hidden = false;
    });

    function check(id, fn, msg) {
      const input = $("#" + id), field = input.closest(".field"), err = $(".err", field);
      const valid = fn(input.value);
      field.classList.toggle("is-invalid", !valid);
      err.textContent = valid ? "" : msg;
      return !!valid;
    }
  }

  function renderShipFAQ() {
    const faqs = [
      ["Où livrez-vous, et en combien de temps ?", "Partout en Tunisie. Grand Tunis sous 24 h, villes principales sous 48 h, régions sous 72 h ouvrées. Livraison offerte dès 99 DT d’achat."],
      ["Comment puis-je payer ?", "À la réception (espèces), par carte bancaire en ligne, ou par virement. Aucun frais caché n’est ajouté au moment du paiement."],
      ["Et si un produit ne me convient pas ?", "14 jours pour changer d’avis sur tout produit non ouvert. Une réaction cutanée inhabituelle ? Contactez-nous immédiatement : nous étudions chaque cas et faisons le nécessaire auprès du laboratoire."],
      ["Puis-je réserver en boutique ?", "Oui — appelez avant 16h, votre commande est préparée et mise de côté à votre nom pour un retrait le jour même ou le lendemain."]
    ];
    const acc = $("#shipAcc");
    acc.innerHTML = faqs.map(([q, a]) => `
      <div class="acc__item">
        <button class="acc__btn" aria-expanded="false"><span>${q}</span><i></i></button>
        <div class="acc__panel"><p class="acc__panel-inner">${a}</p></div>
      </div>`).join("");
    acc.addEventListener("click", e => {
      const btn = e.target.closest(".acc__btn");
      if (!btn) return;
      const item = btn.parentElement, panel = btn.nextElementSibling, open = item.classList.contains("is-open");
      $$(".acc__item.is-open", acc).forEach(o => {
        o.classList.remove("is-open");
        $(".acc__panel", o).style.height = "0px";
        $(".acc__btn", o).setAttribute("aria-expanded", "false");
      });
      if (!open) {
        item.classList.add("is-open");
        panel.style.height = panel.firstElementChild.scrollHeight + "px";
        btn.setAttribute("aria-expanded", "true");
      }
    });
  }
})();

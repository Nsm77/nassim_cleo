/* CLÉOPÂTRE — Notre maison */
(function () {
  "use strict";
  const C = window.CLEO;

  C.onReady(() => {
    document.getElementById("aboutArt").innerHTML =
      C.ART.scene(["#E4DBC9", "#D9CBB0", "#B39B72"], { bg: "#E7DFD0" });

    /* Chiffres */
    const stats = [
      [22, "années au comptoir de la beauté tunisienne"],
      [2500, "références examinées, une sélection retenue"],
      [16, "laboratoires partenaires officiels"],
      [6, "pharmaciennes diplômées d’État"]
    ];
    document.getElementById("stats").innerHTML = stats.map(([n, label], i) => `
      <div class="stat" data-reveal style="--d:${i}">
        <b data-target="${n}">0</b><span>${label}</span>
      </div>`).join("");
    animateCounters();

    /* Standards */
    const stds = [
      ["I.", "Pas d’allégation sans preuve", "Chaque promesse affichée en rayon s’appuie sur une étude publiée. Si le laboratoire ne peut pas la montrer, le produit n’entre pas."],
      ["II.", "Pas de prix fictifs", "Nos remises partent du prix réel du comptoir, jamais d’un prix gonflé pour l’occasion. La confiance ne se décrète pas : elle se prouve."],
      ["III.", "Pas de conseil automatisé", "Un message reçu est lu par une pharmacienne diplômée — jamais par un robot. Le temps de réponse est celui qu’il faut."],
      ["IV.", "Pas de contrefaçon possible", "Chaîne d’approvisionnement directe avec les laboratoires. Traçabilité complète, lot par lot, sans exception."]
    ];
    document.getElementById("standards").innerHTML = stds.map(([n, t, d], i) => `
      <div class="std" data-reveal style="--d:${i}">
        <i>${n}</i><h3>${t}</h3><p>${d}</p>
      </div>`).join("");

    /* Équipe */
    const team = [
      ["Dr Amel Ben Salah", "Pharmacienne fondatrice", "#EAE3D6", "A"],
      ["Sonia Trabelsi", "Pharmacienne · peau sensible", "#E3E8DE", "S"],
      ["Mariem Gharbi", "Conseillère beauté & rituels", "#F0E4CF", "M"],
      ["Yosr Ben Aïssa", "Responsable bébé & maternité", "#EDE6DA", "Y"]
    ];
    document.getElementById("teamGrid").innerHTML = team.map(([name, role, tint, letter], i) => `
      <article class="tcard" data-reveal style="--d:${i}">
        <div class="art">${C.ART.monogram(letter, tint)}</div>
        <small>${role}</small><b>${name}</b>
        <p>Au comptoir et sur nos conseils en ligne.</p>
      </article>`).join("");

    C.observe(document.body);
  });

  function animateCounters() {
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        io.unobserve(en.target);
        const el = en.target, target = +el.dataset.target;
        if (C.reduceMotion) { el.textContent = target.toLocaleString("fr-FR"); return; }
        const t0 = performance.now(), dur = 1500;
        (function frame(now) {
          const k = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - k, 3);
          el.textContent = Math.round(target * e).toLocaleString("fr-FR");
          if (k < 1) requestAnimationFrame(frame);
        })(t0);
      });
    }, { threshold: .5 });
    document.querySelectorAll("[data-target]").forEach(el => io.observe(el));
  }
})();

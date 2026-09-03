/* CLÉOPÂTRE — 404 */
(function () {
  "use strict";
  window.CLEO.onReady(() => {
    const el = document.getElementById("lostArt");
    if (el) el.innerHTML = window.CLEO.ART.scene(["#E4DBC9", "#D9CBB0", "#B39B72"], { bg: "#E7DFD0" });
  });
})();

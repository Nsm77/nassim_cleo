/* CLÉOPÂTRE — Promotions */
(function () {
  "use strict";
  const C = window.CLEO;

  C.onReady(() => {
    const grid = document.getElementById("promoGrid");
    const list = C.products.filter(p => p.oldPrice).sort((a, b) => C.off(b) - C.off(a));
    // Campaign hierarchy: FEATURED (top discount) + SECONDARY 2 + TERTIAIRE rest
    if(list.length){
      const feat=list[0];
      const secondary=list.slice(1,3);
      const rest=list.slice(3);
      grid.innerHTML = `
        ${feat ? `<div class="promo-featured" style="grid-column:1/-1;display:grid;grid-template-columns:minmax(280px,.9fr) 1.1fr;gap:clamp(20px,3vw,40px);align-items:center;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius-md);padding:clamp(16px,2vw,24px);margin-bottom:12px">
          <div style="aspect-ratio:4/5;background:var(--card);border-radius:var(--radius);overflow:hidden">${C.ART.front(feat)}</div>
          <div>
            <span class="num-label" style="color:var(--gold)"><i>-%</i> Offre du comptoir</span>
            <h3 style="font-family:var(--serif);font-size:clamp(1.4rem,2vw,1.9rem);margin-top:12px">${C.esc(feat.name)}</h3>
            <p style="color:var(--soft);margin-top:8px">${C.esc(feat.short)}</p>
            <div style="display:flex;gap:12px;align-items:baseline;margin-top:12px;flex-wrap:wrap"><span class="price-now" style="font-size:1.3rem">${C.fmt(feat.price)}</span><span class="price-old">${C.fmt(feat.oldPrice)}</span><span class="price-off">-${C.off(feat)}%</span></div>
            <a class="btn btn--ink" href="${C.PAGES}product.html?id=${encodeURIComponent(feat.id)}" style="margin-top:16px">Découvrir<span class="arr">→</span></a>
          </div>
        </div>`:''}
        ${secondary.map((p,i)=> C.cardHTML(p).replace('class="pcard"', `class="pcard promo-secondary" style="--i:${i}"`)).join('')}
        ${rest.map((p,i)=> C.cardHTML(p).replace('class="pcard"', `class="pcard" style="--i:${i+2}"`)).join('')}
      `;
    } else {
      grid.innerHTML='';
    }
    document.getElementById("noPromo").hidden = list.length > 0;
    const art = document.getElementById("promoArt");
    if (art) art.innerHTML = C.ART.scene(["#EFE0BE", "#EAD9A6", "#D9CBB0"], { bg: "#3A4632" });
    countdown();
  });

  // Countdown ONLY real promotion end_date (Phase 17.8) — never fake urgency
  function countdown() {
    const el = document.getElementById("countdown");
    if(!el) return;
    const spans = el.querySelectorAll("b");
    let realEnd=null;
    const withEnd=C.products.filter(p=>p.promo_end||p.promoEnd).map(p=> new Date(p.promo_end||p.promoEnd)).filter(d=>!isNaN(d)&&d>Date.now());
    if(withEnd.length) realEnd=new Date(Math.min(...withEnd));
    if(!realEnd){
      // Try promotions API silently
      if(window.CLEO_API && C.products.filter(p=>p.oldPrice).length){
        try{
          window.CLEO_API.admin && window.CLEO_API.admin.promotions && window.CLEO_API.admin.promotions().then(res=>{
            const promos=res.data?.promotions||res.data||[];
            const dates=promos.map(p=>p.end_date||p.endDate).map(d=> d?new Date(d):null).filter(d=>d&&d>Date.now());
            if(dates.length){
              const end=new Date(Math.min(...dates));
              start(end);
            } else {
              // No real end — hide countdown, show ethic message
              el.style.display='none';
              if(!document.querySelector('.promo-no-countdown')){
                const p=document.createElement('p');
                p.className='promo-no-countdown';
                p.style.cssText='font-size:.78rem;color:rgba(237,234,224,.72);margin-top:16px';
                p.textContent='Prix doux jusqu’à épuisement — pas de compte à rebours artificiel.';
                el.parentElement?.appendChild(p);
              }
            }
          }).catch(()=>{ el.style.display='none'; });
        }catch(e){ el.style.display='none'; }
        if(!withEnd.length){
          el.style.visibility='hidden';
          setTimeout(()=>{ if(el.parentElement && !realEnd) el.style.display='none'; },1100);
          return;
        }
      }
    }
    if(!realEnd){
      el.style.display='none';
      const p=document.createElement('p');
      p.style.cssText='font-size:.78rem;color:rgba(237,234,224,.72);margin-top:16px';
      p.textContent='Prix doux jusqu’à épuisement du stock.';
      el.parentElement?.appendChild(p);
      return;
    }
    start(realEnd);
    function start(end){
      el.style.visibility='visible';
      el.style.display='flex';
      function tick(){
        let s=Math.max(0, Math.floor((end - Date.now())/1000));
        const d=Math.floor(s/86400); s-=d*86400;
        const h=Math.floor(s/3600); s-=h*3600;
        const m=Math.floor(s/60); s-=m*60;
        [d,h,m,s].forEach((v,i)=>{ if(spans[i]) spans[i].textContent=String(v).padStart(2,'0'); });
      }
      tick(); setInterval(tick,1000);
    }
  }
})();

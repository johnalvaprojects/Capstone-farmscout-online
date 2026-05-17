(function(){const a=document.createElement("link").relList;if(a&&a.supports&&a.supports("modulepreload"))return;for(const n of document.querySelectorAll('link[rel="modulepreload"]'))t(n);new MutationObserver(n=>{for(const s of n)if(s.type==="childList")for(const c of s.addedNodes)c.tagName==="LINK"&&c.rel==="modulepreload"&&t(c)}).observe(document,{childList:!0,subtree:!0});function r(n){const s={};return n.integrity&&(s.integrity=n.integrity),n.referrerPolicy&&(s.referrerPolicy=n.referrerPolicy),n.crossOrigin==="use-credentials"?s.credentials="include":n.crossOrigin==="anonymous"?s.credentials="omit":s.credentials="same-origin",s}function t(n){if(n.ep)return;n.ep=!0;const s=r(n);fetch(n.href,s)}})();const be="modulepreload",we=function(e){return"/farmscout_online/app/"+e},J={},H=function(a,r,t){let n=Promise.resolve();if(r&&r.length>0){let p=function(v){return Promise.all(v.map(m=>Promise.resolve(m).then(u=>({status:"fulfilled",value:u}),u=>({status:"rejected",reason:u}))))};document.getElementsByTagName("link");const c=document.querySelector("meta[property=csp-nonce]"),d=c?.nonce||c?.getAttribute("nonce");n=p(r.map(v=>{if(v=we(v),v in J)return;J[v]=!0;const m=v.endsWith(".css"),u=m?'[rel="stylesheet"]':"";if(document.querySelector(`link[href="${v}"]${u}`))return;const i=document.createElement("link");if(i.rel=m?"stylesheet":be,m||(i.as="script"),i.crossOrigin="",i.href=v,d&&i.setAttribute("nonce",d),document.head.appendChild(i),m)return new Promise((l,g)=>{i.addEventListener("load",l),i.addEventListener("error",()=>g(new Error(`Unable to preload CSS for ${v}`)))})}))}function s(c){const d=new Event("vite:preloadError",{cancelable:!0});if(d.payload=c,window.dispatchEvent(d),!d.defaultPrevented)throw c}return n.then(c=>{for(const d of c||[])d.status==="rejected"&&s(d.reason);return a().catch(s)})};function _e(){return`
    <div class="fs-sf-wrap fs-home">
      <!-- ── NAV: ThinkingGifts-inspired ── -->
      <nav class="fs-sf-nav" aria-label="Primary">
        <div class="fs-sf-nav-inner">

          <div class="fs-sf-nav-left">
            <button class="fs-sf-menu-btn" type="button" aria-label="Menu" title="Menu">
              <span class="fs-sf-menu-lines" aria-hidden="true"></span>
            </button>
            <div class="fs-sf-nav-links">
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/" aria-label="Home" data-fs-scroll-home-top>
                <span class="fs-draw-text">Home</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/products">
                <span class="fs-draw-text">Products</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/nearest">
                <span class="fs-draw-text">Markets</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
            </div>
          </div>

          <div class="fs-sf-nav-right">
            <form class="fs-sf-search" action="/farmscout_online/app/products" method="get" role="search" aria-label="Search products">
              <input name="q" type="search" placeholder="Search products…" autocomplete="off" />
            </form>
            <div class="fs-sf-nav-icons" aria-label="Quick actions">
              <a class="fs-sf-pill" id="fsNavAuthPill" href="/farmscout_online/app/account" style="text-decoration:none;">Login</a>
            </div>
          </div>

        </div>
      </nav>

      <main class="fs-fa-home" aria-label="FarmScout homepage">
        <section class="fs-fa-hero" aria-label="FarmScout landing">
          <video class="fs-fa-bg" autoplay muted loop playsinline preload="auto" aria-hidden="true">
            <source src="/farmscout_online/assets/video/leafbg.mp4" type="video/mp4" />
          </video>
          <div class="fs-fa-shade" aria-hidden="true"></div>

          <div class="fs-fa-title-wrap">
            <h1 class="fs-fa-title">FARMSCOUT</h1>
            <p class="fs-fa-subtitle">Compare prices. Find nearby markets. Shop smarter.</p>
          </div>

          <div class="fs-fa-actions" aria-label="Main actions">
            <a class="fs-fa-btn fs-fa-btn--light fs-card-animate" data-card-animate href="/farmscout_online/app/products">
              Browse Products
              <span aria-hidden="true">→</span>
            </a>
            <a class="fs-fa-btn fs-fa-btn--dark fs-card-animate" data-card-animate href="/farmscout_online/app/nearest">
              Find Nearest Market
              <span aria-hidden="true">→</span>
            </a>
          </div>

          <div class="fs-fa-footerline" aria-hidden="true">
            <span>La Union</span>
            <span>Fresh Produce</span>
            <span>© FarmScout ${new Date().getFullYear()}</span>
            <span>Prices</span>
            <span>Markets</span>
            <span>Nearby</span>
          </div>
        </section>
      </main>
    </div>
  `}function z({label:e,href:a}){return`
    <div class="fs-redirect">
      <div class="fs-redirect-card">
        <div class="fs-redirect-title">${e||"Redirecting…"}</div>
        <a class="fs-redirect-link" href="${a||"/farmscout_online/app/"}">Continue</a>
      </div>
    </div>
  `}function le({markets:e,selectedMarketId:a}={}){const r=Array.isArray(e)?e:[];return`
    <div class="fs-gsap-markets" data-gsap-markets>
      <nav class="fs-sf-nav fs-sf-nav--static" aria-label="Primary">
        <div class="fs-sf-nav-inner">

          <div class="fs-sf-nav-left">
            <button class="fs-sf-menu-btn" type="button" aria-label="Menu" title="Menu">
              <span class="fs-sf-menu-lines" aria-hidden="true"></span>
            </button>
            <div class="fs-sf-nav-links">
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/" aria-label="Home">
                <span class="fs-draw-text">Home</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/products">
                <span class="fs-draw-text">Products</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/nearest" aria-current="page">
                <span class="fs-draw-text">Markets</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
            </div>
          </div>

          <div class="fs-sf-nav-right">
            <form class="fs-sf-search" action="/farmscout_online/app/products" method="get" role="search" aria-label="Search products">
              <input name="q" type="search" placeholder="Search products…" autocomplete="off" />
            </form>
            <div class="fs-sf-nav-icons" aria-label="Quick actions">
              <a class="fs-sf-pill" id="fsNavAuthPill" href="/farmscout_online/app/account" style="text-decoration:none;">Login</a>
            </div>
          </div>

        </div>
      </nav>

      <video class="fs-gsap-video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
        <source src="/farmscout_online/assets/video/leafbg.mp4" type="video/mp4" />
      </video>
      <div class="fs-gsap-video-overlay" aria-hidden="true"></div>

      <div class="bottom-ui-container">
        <div class="slide-section">FARMSCOUT MARKETS</div>
        <div class="slide-counter">
          <div class="counter-nav prev-slide" role="button" tabindex="0" aria-label="Previous slide">⟪</div>
          <div class="counter-display">
            <span class="current-slide">01</span>
            <span class="counter-divider">//</span>
            <span class="total-slides">${String(Math.max(1,r.length)).padStart(2,"0")}</span>
          </div>
          <div class="counter-nav next-slide" role="button" tabindex="0" aria-label="Next slide">⟫</div>
        </div>
        <div class="slide-title-container">
          <div class="slide-title">${r[0]?.name||"Markets"}</div>
        </div>
        <div class="drag-indicator" aria-hidden="true"></div>
        <div class="thumbs-container" aria-label="Slide thumbnails">
          <div class="frost-bg"></div>
          <div class="slide-thumbs"></div>
        </div>
      </div>

      <div class="slides" aria-label="Market slides">
        ${r.map((t,n)=>`
              <div class="slide${n===0?" slide--current":""}">
                <div class="slide__img"></div>
              </div>
            `).join("")}
      </div>
    </div>
  `}function de({markets:e}={}){return`
    <div class="fs-sf-wrap fs-nearest-page">
      <nav class="fs-sf-nav fs-sf-nav--static" aria-label="Primary">
        <div class="fs-sf-nav-inner">

          <div class="fs-sf-nav-left">
            <button class="fs-sf-menu-btn" type="button" aria-label="Menu" title="Menu">
              <span class="fs-sf-menu-lines" aria-hidden="true"></span>
            </button>
            <div class="fs-sf-nav-links">
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/" aria-label="Home">
                <span class="fs-draw-text">Home</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/products">
                <span class="fs-draw-text">Products</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/nearest" aria-current="page">
                <span class="fs-draw-text">Markets</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
            </div>
          </div>

          <div class="fs-sf-nav-right">
            <form class="fs-sf-search" action="/farmscout_online/app/products" method="get" role="search" aria-label="Search products">
              <input name="q" type="search" placeholder="Search products…" autocomplete="off" />
            </form>
            <div class="fs-sf-nav-icons" aria-label="Quick actions">
              <a class="fs-sf-pill" id="fsNavAuthPill" href="/farmscout_online/app/account" style="text-decoration:none;">Login</a>
            </div>
          </div>

        </div>
      </nav>

      <main class="fs-sf-main">
        <div class="fs-sf-container">
          <header class="fs-page-head fs-nearest-head">
            <p class="fs-nearest-kicker">FarmScout · Markets</p>
            <h1 class="fs-page-title fs-nearest-title">Find Nearest Market</h1>
            <p class="fs-page-subtitle fs-nearest-lede">We’ll detect your location and plot nearby markets—tap the map or choose from the list.</p>
          </header>

          <section class="fs-nearest-layout" aria-label="Nearest market map">
            <div class="fs-nearest-map" id="nearestMap" aria-label="Map"></div>
            <aside class="fs-nearest-aside" aria-label="Nearby markets">
              <div class="fs-nearest-reco" data-nearest-recommendation>
                <div class="fs-nearest-reco-label">Recommended market</div>
                <div class="fs-nearest-reco-title">Finding the best nearby market...</div>
                <div class="fs-nearest-reco-meta">We’ll use your location when available, or a default nearby area if not.</div>
              </div>
              <div class="fs-nearest-list-head">
                <div class="fs-nearest-aside-title">Markets near you</div>
                <p class="fs-nearest-list-hint">Select a market to view available products and prices. You can also tap a map pin.</p>
              </div>
              <div class="fs-nearest-list" aria-label="Markets list">
                ${(Array.isArray(e)?e:[]).map(r=>`
                    <div class="fs-nearest-item">
                      <div class="fs-nearest-item-name">
                        ${r.name}
                        ${typeof r.distance_km=="number"?`<span class="fs-nearest-item-dist">${r.distance_km.toFixed(1)} km</span>`:""}
                      </div>
                      <div class="fs-nearest-item-meta">${r.address}</div>
                    </div>
                  `).join("")}
              </div>
            </aside>
          </section>
        </div>
      </main>
    </div>
  `}function ue({categories:e,selectedCategoryId:a,selectedMarketName:r=""}={}){const t=Array.isArray(e)?e:[],n=t.find(u=>String(u.id)===String(a||""))||null,s=(u="")=>{const i=String(u).toLowerCase().trim();return i==="grains / rice"||i==="grains/rice"?"Rice":i==="poultry / eggs"||i==="poultry/eggs"?"Poultry":u},c=(u="")=>{const i=String(u).toLowerCase();return i.includes("vegetable")?"vegetables.mp4":i.includes("fruit")?"Fruits.mp4":i.includes("meat")?"meat.mp4":i.includes("fish")?"fish.mp4":i.includes("rice")||i.includes("grain")?"rice.mp4":i.includes("poultry")||i.includes("egg")?"egg.mp4":"vegetables.mp4"},d="market vids",p=()=>`/farmscout_online/app/assets/video/${encodeURIComponent(d)}/`,v=(u="")=>{const i=p(),l=[],g=b=>{const y=String(b||"").trim();if(!y.toLowerCase().endsWith(".mp4"))return;const w=i+encodeURIComponent(y);l.includes(w)||l.push(w)},f=String(u||"").trim();if(f){const b=f.replace(/\s+/g," ").trim(),y=b.toLowerCase(),w=y.replace(/\b\w/g,M=>M.toUpperCase()),L=b.replace(/\s+public\s+/gi," ").replace(/\s+city\s+/gi," ").trim(),$=b.split(/[\s,]+/)[0]||"";if(g(`${b}.mp4`),g(`${y}.mp4`),g(`${w}.mp4`),g(`${L}.mp4`),g(`${L.toLowerCase()}.mp4`),$.length>1&&(g(`${$} Market.mp4`),g(`${$.toLowerCase()} market.mp4`),g(`${$.charAt(0).toUpperCase()+$.slice(1).toLowerCase()} Market.mp4`),g(`${$.charAt(0).toUpperCase()+$.slice(1).toLowerCase()} market.mp4`)),y.endsWith(" market")){const C=y.replace(/\s+market$/i,"").trim().split(/\s+/).filter(Boolean);if(C.length){const h=C[0].charAt(0).toUpperCase()+C[0].slice(1),S=C.slice(1).join(" ");g(`${h}${S?` ${S}`:""} Market.mp4`)}}}return l.push("/farmscout_online/app/assets/video/vegetables.mp4"),l},m=!n&&!!r;return`
    <div class="fs-sf-wrap fs-products-page">
      <nav class="fs-sf-nav fs-sf-nav--static" aria-label="Primary">
        <div class="fs-sf-nav-inner">

          <div class="fs-sf-nav-left">
            <button class="fs-sf-menu-btn" type="button" aria-label="Menu" title="Menu">
              <span class="fs-sf-menu-lines" aria-hidden="true"></span>
            </button>
            <div class="fs-sf-nav-links">
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/" aria-label="Home">
                <span class="fs-draw-text">Home</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/products" aria-current="page">
                <span class="fs-draw-text">Products</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
              <a class="fs-draw-link" data-draw-line href="/farmscout_online/app/nearest">
                <span class="fs-draw-text">Markets</span>
                <span class="fs-draw-box" data-draw-line-box aria-hidden="true"></span>
              </a>
            </div>
          </div>

          <div class="fs-sf-nav-right">
            <form class="fs-sf-search" action="/farmscout_online/app/products" method="get" role="search" aria-label="Search products">
              <input name="q" type="search" placeholder="Search products…" autocomplete="off" />
            </form>
            <div class="fs-sf-nav-icons" aria-label="Quick actions">
              <a class="fs-sf-pill" id="fsNavAuthPill" href="/farmscout_online/app/account" style="text-decoration:none;">Login</a>
            </div>
          </div>

        </div>
      </nav>

      <main class="fs-sf-main">
        <div class="fs-sf-container">
          ${n||m?`
                <!-- detail view handles its own hero -->
              `:`
                <header class="fs-cat-hero" aria-label="Products categories header">
                  <div class="fs-cat-section-head" aria-hidden="true">
                    <span class="fs-cat-head-kicker">Choose a path</span>
                    <h2 class="fs-cat-head-title">Categories</h2>
                    <p class="fs-cat-section-sub fs-cat-head-sub">Browse categories first, then compare prices across markets.</p>
                  </div>
                </header>
              `}

          ${n||m?`
                <section class="fs-prod-category-hero${n||m?" has-video":""}" aria-label="${n?`${s(n.title)} category`:`${r} market`}">
                  ${n?(()=>{const u=c(n.title),i=`/farmscout_online/app/assets/video/${u}`,l=`/farmscout_online/app/assets/video/${String(u).toLowerCase()}`,g=`/farmscout_online/app/assets/video/${u.replace(/vegetables/i,"vegetable").replace(/fruits/i,"fruit")}`;return`
                            <video class="fs-prod-hero-video" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
                              <source src="${i}" type="video/mp4" />
                              <source src="${l}" type="video/mp4" />
                              <source src="${g}" type="video/mp4" />
                              <source src="/farmscout_online/app/assets/video/poultry.mp4" type="video/mp4" />
                            </video>
                          `})():m?`
                            <video class="fs-prod-hero-video" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
                              ${v(r).map(i=>`<source src="${i}" type="video/mp4" />`).join("")}
                            </video>
                          `:""}
                  <h2>${n?s(n.title):r}</h2>
                  <p class="fs-prod-category-sub">${n?`Browse ${s(n.title)} products, compare prices, and find the best market.`:`Browse products available in ${r}.`}</p>
                  <div class="fs-prod-crumb">${n?`Category / ${s(n.title)}`:`Market / ${r}`}</div>
                </section>

                <a class="fs-prod-floating-back" href="/farmscout_online/app/products" aria-label="Back to categories">← Back to categories</a>

                <section class="fs-prod-shop" data-products-shop aria-label="${n?s(n.title):r} products">
                  <aside class="fs-prod-filter-panel" aria-label="Product filters">
                    <div class="fs-prod-filter-summary">
                      <strong data-products-count>0 Products</strong>
                      <button type="button" data-products-reset>Clear all</button>
                    </div>

                    <form class="fs-prod-filter-search" role="search" aria-label="Filter products" data-products-filter-form>
                      <input data-products-search type="search" placeholder="Search products..." autocomplete="off" />
                    </form>

                    <div class="fs-prod-filter-group">
                      <h3>Filter</h3>
                      <h4>Price Range</h4>
                      <div class="fs-prod-price-line">
                        <span>₱0</span>
                        <span data-products-price-label>₱1000+</span>
                      </div>
                      <input data-products-price type="range" min="0" max="1000" value="1000" aria-label="Maximum price" />
                    </div>

                    <div class="fs-prod-filter-group">
                      <h4>${m?"Category":"Market"}</h4>
                      <div data-products-market-filters data-products-filter-kind="${m?"category":"market"}">
                        <div class="fs-prod-filter-note">Loading ${m?"categories":"markets"}...</div>
                      </div>
                    </div>
                  </aside>

                  <div class="fs-prod-results">
                    <div class="fs-prod-toolbar">
                      <div>
                        <strong data-products-count>0 Products</strong>
                        <span>Active filters: Price ₱0 - ₱1000+</span>
                      </div>
                      <label class="fs-prod-sort-wrap">
                        <span class="fs-prod-sort-label-text">Sort</span>
                        <select class="fs-prod-sort-select" data-products-sort aria-label="Sort products">
                          <option value="original" selected>As listed</option>
                          <option value="price-asc">Price ↑</option>
                          <option value="price-desc">Price ↓</option>
                          <option value="name-asc">A → Z</option>
                        </select>
                      </label>
                    </div>

                    <div class="fs-prod-grid" data-products-grid aria-live="polite">
                      <div class="fs-prod-loading">Loading products...</div>
                    </div>
                    <div class="fs-prod-empty" data-products-empty hidden>
                      <strong>No products match your filters.</strong>
                      <span>Try clearing filters or increasing the price range.</span>
                      <button type="button" data-products-reset>Reset filters</button>
                    </div>
                  </div>
                </section>
              `:`
                <section class="fs-cat-grid" aria-label="Product categories">
                  ${t.map(u=>{const i=Number(u.product_count??u.products?.length??0),l=s(u.title),g=c(u.title),f=`/farmscout_online/app/assets/video/${g}`,b=`/farmscout_online/app/assets/video/${String(g).toLowerCase()}`,y=`/farmscout_online/app/assets/video/${g.replace(/vegetables/i,"vegetable").replace(/fruits/i,"fruit")}`;return`
                        <a class="fs-cat-card" href="/farmscout_online/app/products?category=${encodeURIComponent(u.id)}" aria-label="${l}">
                          <div class="fs-cat-copy">
                            <span class="fs-cat-count" aria-label="${i} products">${i} PRODUCTS</span>
                            <div class="fs-cat-title">${l}</div>
                            <div class="fs-cat-desc">Compare prices across markets.</div>
                            <span class="fs-cat-arrow" aria-hidden="true">→</span>
                          </div>
                          <div class="fs-cat-media" aria-hidden="true">
                            <video autoplay muted loop playsinline preload="metadata">
                              <source src="${f}" type="video/mp4" />
                              <source src="${b}" type="video/mp4" />
                              <source src="${y}" type="video/mp4" />
                              <source src="/farmscout_online/app/assets/video/poultry.mp4" type="video/mp4" />
                            </video>
                          </div>
                        </a>
                      `}).join("")}
                </section>
              `}
        </div>
      </main>
    </div>
  `}function D(e){if(!e)return"/";const a="/farmscout_online/app";if(e.startsWith(a)){const r=e.slice(a.length);return r===""?"/":r}return e}function ke(e){return!e||!e.href||e.target==="_blank"||e.hasAttribute("download")?!1:new URL(e.href,window.location.href).origin===window.location.origin}function Y(e,{replace:a=!1}={}){const r=new URL(e,window.location.href),t=D(r.pathname),n=`/farmscout_online/app${t==="/"?"/":t}${r.search}${r.hash}`;a?window.history.replaceState({},"",n):window.history.pushState({},"",n),G()}let o=null,X=[],W=null;function pe(){typeof W=="function"&&(W(),W=null)}function Se(){if(pe(),!o)return;const e=o.querySelector(".fs-sf-wrap.fs-home"),a=o.querySelector("#farmscout-editorial");if(!e||!a)return;const r=72,t=()=>{const n=a.getBoundingClientRect().top;e.classList.toggle("fs-home-nav-on-light",n<=r)};t(),window.addEventListener("scroll",t,{passive:!0}),W=()=>{window.removeEventListener("scroll",t),e.classList.remove("fs-home-nav-on-light")}}let ee=!1,Q=!1,te=!1,V=null,ae=!1;function E(){if(!o)return;Array.from(o.querySelectorAll("form.fs-sf-search")).forEach(a=>{if(a.dataset.navAcBound==="1")return;a.dataset.navAcBound="1";const r=a.querySelector('input[type="search"][name="q"]');if(!r)return;const t=document.createElement("div");t.className="fs-nav-ac",t.hidden=!0,t.setAttribute("role","listbox"),a.appendChild(t);let n=null,s=-1,c=[],d="";const p=()=>{t.hidden=!0,t.innerHTML="",s=-1,c=[]},v=()=>{t.innerHTML=c.map((i,l)=>{const g=k(String(i.product_name||i.text||"")),f=[i.market_name,i.category].filter(Boolean).join(" · ");return`
            <button type="button" class="fs-nav-ac__item${l===s?" is-active":""}" role="option" data-idx="${l}">
              <div class="fs-nav-ac__title">${g}</div>
              ${f?`<div class="fs-nav-ac__meta">${k(f)}</div>`:""}
            </button>
          `}).join(""),t.hidden=c.length===0},m=i=>{s=i;const l=Array.from(t.querySelectorAll(".fs-nav-ac__item"));l.forEach((f,b)=>f.classList.toggle("is-active",b===s)),l[s]?.scrollIntoView?.({block:"nearest"})};async function u(i){const l=String(i||"").trim();if(l.length<2)return[];try{const f=await(await fetch(`/farmscout_online/api/search_autocomplete_v2.php?q=${encodeURIComponent(l)}&limit=8`,{headers:{Accept:"application/json"}})).json();return Array.isArray(f.suggestions)?f.suggestions:[]}catch{return[]}}r.addEventListener("input",()=>{const i=String(r.value||"").trim();if(d=i,n&&window.clearTimeout(n),i.length<2){p();return}n=window.setTimeout(async()=>{const l=await u(i);String(r.value||"").trim()===d&&(c=l,s=l.length?0:-1,v())},160)}),r.addEventListener("keydown",i=>{if(!(t.hidden||c.length===0))if(i.key==="ArrowDown")i.preventDefault(),m(Math.min(c.length-1,s+1));else if(i.key==="ArrowUp")i.preventDefault(),m(Math.max(0,s-1));else if(i.key==="Enter"){if(s>=0&&c[s]){i.preventDefault();const l=c[s],g=String(l.product_name||l.text||r.value||"").trim();p(),r.value=g,B(`/products?q=${encodeURIComponent(g)}`)}}else i.key==="Escape"&&p()}),t.addEventListener("click",i=>{const l=i.target.closest(".fs-nav-ac__item");if(!l)return;const g=Number(l.getAttribute("data-idx")||-1),f=c[g];if(!f)return;const b=String(f.product_name||f.text||r.value||"").trim();p(),r.value=b,B(`/products?q=${encodeURIComponent(b)}`)}),r.addEventListener("blur",()=>{window.setTimeout(()=>p(),120)}),r.addEventListener("focus",()=>{String(r.value||"").trim().length>=2&&c.length&&(t.hidden=!1)})}),ae||(ae=!0,document.addEventListener("click",a=>{a.target.closest?.(".fs-sf-search")||document.querySelectorAll(".fs-nav-ac").forEach(t=>t.hidden=!0)}))}let $e=!1;async function N(){const e=o?.querySelector?.("#fsNavAuthPill");if(e){e.textContent="Login",e.setAttribute("href","/farmscout_online/app/account");try{const r=await(await fetch("/farmscout_online/api/auth_session.php",{credentials:"include",cache:"no-store",headers:{Accept:"application/json"}})).text();let t=null;try{t=JSON.parse(r)}catch{/"logged_in"\s*:\s*true/.test(r)?t={logged_in:!0}:/"logged_in"\s*:\s*false/.test(r)&&(t={logged_in:!1})}t&&t.logged_in?(e.textContent="Account",e.setAttribute("href","/farmscout_online/app/account")):(e.textContent="Login",e.setAttribute("href","/farmscout_online/app/account")),$e=!0}catch{}}}function q(){if(window.innerWidth>720)return;if(!document.getElementById("fsMobOverlay")){const t=document.createElement("div");t.id="fsMobOverlay",t.className="fs-mob-overlay",t.setAttribute("aria-hidden","true"),document.body.appendChild(t);const n=document.createElement("div");n.id="fsMobDrawer",n.className="fs-mob-drawer",n.setAttribute("role","dialog"),n.setAttribute("aria-modal","true"),n.setAttribute("aria-label","Navigation"),n.innerHTML=`
      <div class="fs-mob-drawer__head">
        <a class="fs-mob-drawer__logo" href="/farmscout_online/app/">FARMSCOUT</a>
        <button class="fs-mob-drawer__close" id="fsMobDrawerClose" aria-label="Close menu">&#x2715;</button>
      </div>
      <div class="fs-mob-drawer__search">
        <form class="fs-sf-search" action="/farmscout_online/app/products" method="get" role="search">
          <input name="q" type="search" placeholder="Search products…" autocomplete="off" />
        </form>
      </div>
      <nav class="fs-mob-drawer__nav" aria-label="Mobile navigation">
        <a href="/farmscout_online/app/">Home</a>
        <a href="/farmscout_online/app/products">Products</a>
        <a href="/farmscout_online/app/nearest">Markets</a>
        <a id="fsMobAuthLink" class="fs-mob-drawer__auth" href="/farmscout_online/app/account">Login</a>
      </nav>
      <div class="fs-mob-drawer__foot">
        <span>La Union &mdash; FarmScout</span>
        <span>&copy;${new Date().getFullYear()}</span>
      </div>
    `,document.body.appendChild(n),t.addEventListener("click",K),document.getElementById("fsMobDrawerClose")?.addEventListener("click",K),document.addEventListener("keydown",s=>{s.key==="Escape"&&K()})}const e=o?.querySelector?.("#fsNavAuthPill"),a=document.getElementById("fsMobAuthLink");a&&e&&(a.textContent=e.textContent||"Login",a.href=e.getAttribute("href")||"/farmscout_online/app/account");const r=D(window.location.pathname);document.querySelectorAll("#fsMobDrawer .fs-mob-drawer__nav a[href]").forEach(t=>{const n=D(new URL(t.href,window.location.href).pathname);t.setAttribute("aria-current",n===r?"page":"false")}),document.querySelectorAll(".fs-sf-menu-btn").forEach(t=>{const n=t.cloneNode(!0);t.parentNode?.replaceChild(n,t),n.addEventListener("click",Le)})}function Le(){document.getElementById("fsMobOverlay")?.classList.add("is-open");const e=document.getElementById("fsMobDrawer");e&&(e.classList.add("is-open"),document.body.style.overflow="hidden")}function K(){document.getElementById("fsMobOverlay")?.classList.remove("is-open");const e=document.getElementById("fsMobDrawer");e&&(e.classList.remove("is-open"),document.body.style.overflow="")}function se(){if(te)return;te=!0;const e=document.createElement("script"),r=(window.location.pathname||"/").includes("/farmscout_online/")?"/farmscout_online":"";e.src=`${r}/app/js/fs-market-reservation.js?v=20260215`,e.defer=!0,e.onerror=()=>{console.error("[FarmScout] Failed to load reservation script:",e.src)},document.head.appendChild(e)}async function B(e,a){const{runSvgPathTransition:r}=await H(async()=>{const{runSvgPathTransition:t}=await import("./svgPathTransition-CqkVcTN1.js");return{runSvgPathTransition:t}},[]);await r("cover"),Y(e,a),await r("uncover")}function Ae(e){e&&(e.classList.remove("fs-card-click"),e.offsetWidth,e.classList.add("fs-card-click"),window.setTimeout(()=>e.classList.remove("fs-card-click"),260))}function Me({animateCards:e=!1}={}){if(document.body.classList.remove("fs-animating","fs-wave-active"),document.body.classList.add("fs-nav-visible","fs-tagline-visible"),!o)return;const a=o.querySelector(".hero-title");a&&a.classList.add("shrink");const r=Array.from(o.querySelectorAll(".card"));if(!e){document.body.classList.remove("fs-home-return","fs-home-return-active"),document.body.classList.add("fs-cards-active"),r.forEach(t=>{t.classList.add("fs-visible"),t.style.opacity="1",t.style.animation=""});return}document.body.classList.add("fs-home-return"),document.body.classList.remove("fs-home-return-active"),document.body.classList.remove("fs-cards-active"),r.forEach(t=>{t.classList.remove("fs-visible"),t.style.opacity="0",t.style.animation=""}),requestAnimationFrame(()=>{document.body.classList.add("fs-home-return-active"),document.body.classList.add("fs-cards-active"),r.forEach(t=>{t.addEventListener("animationend",()=>{t.classList.add("fs-visible"),t.style.opacity="1"},{once:!0})})}),window.setTimeout(()=>{document.body.classList.remove("fs-home-return","fs-home-return-active")},700)}function Ce(){if(!o)return;o.querySelectorAll("[data-card-animate]").forEach(a=>{a.dataset.cardAnimBound!=="1"&&(a.dataset.cardAnimBound="1")})}function R(){if(!o||ee)return;const e=['<svg viewBox="0 0 310 40" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M5 20.9999C26.7762 16.2245 49.5532 11.5572 71.7979 14.6666C84.9553 16.5057 97.0392 21.8432 109.987 24.3888C116.413 25.6523 123.012 25.5143 129.042 22.6388C135.981 19.3303 142.586 15.1422 150.092 13.3333C156.799 11.7168 161.702 14.6225 167.887 16.8333C181.562 21.7212 194.975 22.6234 209.252 21.3888C224.678 20.0548 239.912 17.991 255.42 18.3055C272.027 18.6422 288.409 18.867 305 17.9999"/></svg>','<svg viewBox="0 0 310 40" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M4.99805 20.9998C65.6267 17.4649 126.268 13.845 187.208 12.8887C226.483 12.2723 265.751 13.2796 304.998 13.9998"/></svg>','<svg viewBox="0 0 310 40" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M5 29.8857C52.3147 26.9322 99.4329 21.6611 146.503 17.1765C151.753 16.6763 157.115 15.9505 162.415 15.6551C163.28 15.6069 165.074 15.4123 164.383 16.4275C161.704 20.3627 157.134 23.7551 153.95 27.4983C153.209 28.3702 148.194 33.4751 150.669 34.6605C153.638 36.0819 163.621 32.6063 165.039 32.2029C178.55 28.3608 191.49 23.5968 204.869 19.5404C231.903 11.3436 259.347 5.83254 288.793 5.12258C294.094 4.99476 299.722 4.82265 305 5.45025"/></svg>','<svg viewBox="0 0 310 40" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M17.0039 32.6826C32.2307 32.8412 47.4552 32.8277 62.676 32.8118C67.3044 32.807 96.546 33.0555 104.728 32.0775C113.615 31.0152 104.516 28.3028 102.022 27.2826C89.9573 22.3465 77.3751 19.0254 65.0451 15.0552C57.8987 12.7542 37.2813 8.49399 44.2314 6.10216C50.9667 3.78422 64.2873 5.81914 70.4249 5.96641C105.866 6.81677 141.306 7.58809 176.75 8.59886C217.874 9.77162 258.906 11.0553 300 14.4892"/></svg>'];let a=Math.floor(Math.random()*e.length);o.addEventListener("mouseenter",r=>{const t=r.target.closest?.("[data-draw-line]");if(!t)return;const n=t.querySelector("[data-draw-line-box]");if(!n||n.querySelector("svg"))return;n.innerHTML=e[a],a=(a+1)%e.length;const s=n.querySelector("path");if(!s)return;s.setAttribute("stroke","currentColor"),s.setAttribute("fill","none"),s.setAttribute("stroke-linecap","round"),s.setAttribute("stroke-width","10");const c=Math.max(1,Math.ceil(s.getTotalLength()));s.style.strokeDasharray=`${c}`,s.style.strokeDashoffset=`${c}`,s.style.transition="none",requestAnimationFrame(()=>{s.style.transition="stroke-dashoffset 520ms ease-in-out",s.style.strokeDashoffset="0"})},{capture:!0}),o.addEventListener("mouseleave",r=>{const t=r.target.closest?.("[data-draw-line]");if(!t)return;const n=t.querySelector("[data-draw-line-box]"),s=n?.querySelector("path");if(!n||!s)return;const c=Number.parseFloat(s.style.strokeDasharray||"0")||300;s.style.transition="stroke-dashoffset 520ms ease-in-out",s.style.strokeDashoffset=`${c}`;const d=()=>{n.innerHTML="",s.removeEventListener("transitionend",d)};s.addEventListener("transitionend",d,{once:!0})},{capture:!0}),ee=!0}function xe(){if(X.forEach(clearTimeout),X=[],document.body.classList.remove("fs-animating","fs-wave-active","fs-cards-active","fs-nav-visible","fs-tagline-visible"),!o)return;const e=o.querySelector(".hero-title");e&&e.classList.remove("shrink"),o.querySelectorAll(".card").forEach(a=>{a.classList.remove("fs-visible"),a.style.animation="",a.style.opacity=""})}function G(){if(!o)return;pe();const e=D(window.location.pathname),a=window.location.search||"",r=new URLSearchParams(a);if(e==="/"||e===""){o.innerHTML=_e(),N(),R(),E(),q(),Me({animateCards:!1}),Q=!0,Ce(),Se();try{window.sessionStorage.setItem("fsHomeIntroPlayed","1")}catch{}return}if(xe(),e==="/markets"){const t=r.get("market")||"";o.innerHTML=le({markets:[],selectedMarketId:t}),N(),R(),E(),q(),Ne({marketId:t});return}if(e==="/products"){const t=r.get("category")||"",n=r.get("market")||"";o.innerHTML=ue({categories:[],selectedCategoryId:t}),N(),R(),E(),q(),qe({categoryId:t,marketId:n});return}if(e==="/nearest"){o.innerHTML=de({markets:[]}),N(),R(),E(),q(),Te();return}if(e==="/alerts"){o.innerHTML=z({label:"Opening Price Alerts…",href:"/farmscout_online/user-account.php?section=price_alerts"}),N(),E(),q(),window.location.assign("/farmscout_online/user-account.php?section=price_alerts");return}if(e==="/account"){o.innerHTML=z({label:"Opening My Account…",href:"/farmscout_online/user-account.php"}),N(),E(),q(),window.location.assign("/farmscout_online/user-account.php");return}o.innerHTML=z({label:"Page not found. Going home…",href:"/farmscout_online/app/"}),setTimeout(()=>Y("/",{replace:!0}),400)}async function F(e){const a=await fetch(e,{headers:{Accept:"application/json"}});if(!a.ok)throw new Error(`HTTP ${a.status}`);return await a.json()}function k(e){return String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;")}function P(e){const a=Number(e||0);return Number.isFinite(a)?`₱${a.toLocaleString("en-PH",{maximumFractionDigits:2})}`:"₱0"}function I(e){return String(e??"").toLowerCase().trim()}async function Ne({marketId:e}){if(o)try{const a=await F("/farmscout_online/api/get_markets.php"),r=Array.isArray(a.markets)?a.markets:[];o.innerHTML=le({markets:r.map(t=>({id:String(t.id),name:t.market_name,address:t.address,product_count:t.product_count})),selectedMarketId:e?String(e):""}),N(),R(),E(),q(),Pe({titles:r.map(t=>t.market_name),images:r.map((t,n)=>n%2===0?"/farmscout_online/assets/images/market.png":"/farmscout_online/assets/images/map.png")})}catch{}}let j=null;async function Pe({titles:e}={}){if(!o)return;typeof j=="function"&&(j(),j=null);const a=o.querySelector("[data-gsap-markets]");if(!a)return;const r=o.querySelector(".slides"),t=Array.from(o.querySelectorAll(".slide"));if(t.length<=1)return;const n=o.querySelector(".slide-title"),s=o.querySelector(".current-slide"),c=o.querySelector(".total-slides");c&&(c.textContent=String(t.length).padStart(2,"0"));const d=await H(()=>import("./index-D4-8ALNT.js"),[]),p=d.gsap||d.default||d,v=await H(()=>import("./Observer-XCqE_2Rl.js"),[]),m=v.Observer||v.default||v;let u=0,i=!1;const l=C=>{if(s&&(s.textContent=String(C+1).padStart(2,"0")),!n)return;const h=o.querySelector(".slide-title-container"),S=o.querySelector(".slide-title");if(!h||!S)return;const _=document.createElement("div");_.className="slide-title enter-up",_.textContent=e?.[C]||"Markets",h.appendChild(_),S.classList.add("exit-up"),_.offsetWidth,setTimeout(()=>_.classList.remove("enter-up"),10),setTimeout(()=>S.remove(),500)};n&&(n.textContent=e?.[0]||"Markets");const g=(C,h)=>{if(i)return;i=!0;const S=u;u=(C+t.length)%t.length;const _=t[S],x=t[u],U=_.querySelector(".slide__img"),O=x.querySelector(".slide__img");l(u),p.set(x,{opacity:1,zIndex:99}),p.set(_,{zIndex:1}),p.set(O,{scale:1.12,yPercent:h>0?14:-14}),p.timeline({defaults:{duration:.85,ease:"power3.out"},onComplete:()=>{_.classList.remove("slide--current"),x.classList.add("slide--current"),p.set(_,{opacity:0,zIndex:1}),p.set(x,{zIndex:2}),i=!1}}).to(U,{scale:1.06,yPercent:h>0?-10:10},0).to(_,{opacity:0,duration:.6,ease:"power2.out"},.15).to(O,{scale:1,yPercent:0},.05)},f=()=>g(u+1,1),b=()=>g(u-1,-1),y=o.querySelector(".prev-slide"),w=o.querySelector(".next-slide"),L=()=>b(),$=()=>f();y?.addEventListener("click",L),w?.addEventListener("click",$);const M=m.create({target:r||a,type:"wheel,touch,pointer",wheelSpeed:-1,tolerance:8,preventDefault:!0,onDown:()=>f(),onUp:()=>b()});j=()=>{try{M?.kill?.()}catch{}y?.removeEventListener("click",L),w?.removeEventListener("click",$);try{p.killTweensOf("*")}catch{}}}function Ee(e){const a=String(e||"").trim();return a?/^https?:\/\//i.test(a)||a.startsWith("/")?a:`/farmscout_online/${a.replace(/^\.\//,"")}`:""}function re(e,{compareMode:a=!0,cheapest:r=1/0}={}){return e.map((t,n)=>{const s=a?t.best||{}:t||{},c=s.unit||"",d=k(a?t.product_name:s.filipino_name||s.name||"Product"),p=k(s.market_name||"Market"),v=s.product_image||s.image_url||"",m=(s.product_description||s.description||t.product_description||"").trim(),u=Ee(v)||"/farmscout_online/assets/images/products.png",i=m?`<div class="fs-prod-card-desc">${k(m)}</div>`:"",l=k(I(a?t.product_name:s.filipino_name||s.name||"")),g=k(I(s.market_name||"")),f=k(I(s.category_filipino||s.category||"")),b=Number(a?t.min||0:s.raw_price||String(s.current_price||"").replace(/[^\d.]/g,"")||0),y=Number(s.product_id||s.id||0),w=Number(s.market_id||0),L=Number(s.farmer_id||0),$=String(s.is_available??"true")!=="false"&&!!(s.is_available??!0),M=a?`${P(t.min)}${c?`/${k(c)}`:""}`:`${P(b)}${c?`/${k(c)}`:""}`,C=a?Number(t.min||0)===Number(t.max||0)?`Range ${P(t.min)}`:`Range ${P(t.min)} - ${P(t.max)}`:k(s.category_filipino||s.category||"Available today"),h=$?"":'<div class="fs-prod-oos" aria-label="Not available">Not available</div>';return`
        <article
          class="fs-prod-card${$?"":" is-oos"}"
          data-product-card
          data-product-index="${n}"
          data-product-name="${l}"
          data-product-market="${g}"
          data-product-category="${f}"
          data-product-price="${b}"
          data-product-id="${Number.isFinite(y)?y:0}"
          data-market-id="${Number.isFinite(w)?w:0}"
          data-farmer-id="${Number.isFinite(L)?L:0}"
          data-product-unit="${k(c||"kg")}"
        >
          <div class="fs-prod-image">
            <img src="${u}" alt="" loading="lazy" onerror="this.onerror=null;this.src='/farmscout_online/assets/images/products.png';" />
            ${h}
            <button
              type="button"
              class="fs-prod-alert js-price-alert"
              title="Add price alert"
              aria-label="Add price alert"
              data-product-id="${Number.isFinite(y)?y:0}"
              aria-disabled="${Number.isFinite(y)&&y>0?"false":"true"}"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2Zm-2 .17 1 1V18H6v-.83l1-1V11a5 5 0 1 1 10 0v5.17Z"/>
              </svg>
            </button>
          </div>
          <div class="fs-prod-card-body">
            <div class="fs-prod-card-name">${d}</div>
            <div class="fs-prod-card-market">${p}</div>
            ${i}
            <div class="fs-prod-card-price">${M}</div>
            <div class="fs-prod-card-range">${C}</div>
          </div>
          <div class="product-unit-options" style="display:none">
            <div class="product-unit-option">${k(c||"kg")} - ₱${Number(b||0).toFixed(2)}</div>
          </div>
          <div class="fs-prod-card-actions">
            <button
              type="button"
              class="fs-prod-card-cta js-mf-reserve"
              data-product-id="${Number.isFinite(y)?y:0}"
              data-market-id="${Number.isFinite(w)?w:0}"
              data-farmer-id="${Number.isFinite(L)?L:0}"
              data-product-name="${d}"
              data-product-price="${P(b)}"
              data-product-unit="${k(c||"kg")}"
              aria-disabled="${Number.isFinite(y)&&y>0&&$?"false":"true"}"
              data-out-of-stock="${$?"0":"1"}"
            >Reserve</button>
            <a class="fs-prod-card-cta" href="/farmscout_online/app/nearest?product=${encodeURIComponent(String(a?t.product_name||"":s.filipino_name||s.name||""))}">See on map</a>
          </div>
        </article>
      `}).join("")}async function qe({categoryId:e,marketId:a}){if(o)try{const r=new URLSearchParams(window.location.search||""),t=String(r.get("q")||"").trim(),n=await F("/farmscout_online/api/get_categories_all.php"),s=Array.isArray(n.categories)?n.categories:[];if(!e&&!a&&t){const d=s.map(m=>({id:String(m.id),name:String(m.name||"")})).filter(m=>m.id),p=await Promise.all(d.map(async m=>{try{const u=await F(`/farmscout_online/api/get_category_compare.php?category_id=${encodeURIComponent(String(m.id))}`),i=Array.isArray(u.products)?u.products:[],l=I(t),g=i.filter(f=>I(f.product_name||"").includes(l)).length;return{id:m.id,hits:g}}catch{return{id:m.id,hits:0}}}));p.sort((m,u)=>u.hits-m.hits);const v=p[0]?.id||d[0]?.id||"";if(v){Y(`/products?category=${encodeURIComponent(v)}&q=${encodeURIComponent(t)}`,{replace:!0});return}}let c="";if(!e&&a)try{const d=await F("/farmscout_online/api/get_markets.php");c=(Array.isArray(d.markets)?d.markets:[]).find(v=>String(v.id)===String(a))?.market_name||""}catch{}if(o.innerHTML=ue({categories:s.map(d=>({id:String(d.id),title:d.name,products:Array.from({length:d.product_count||0}).map((p,v)=>`p${v}`)})),selectedCategoryId:e?String(e):"",selectedMarketName:c}),N(),R(),E(),q(),e){se();const d=await F(`/farmscout_online/api/get_category_compare.php?category_id=${encodeURIComponent(String(e))}`),p=Array.isArray(d.products)?d.products:[],v=Math.min(...p.map(l=>Number(l.min||1/0)).filter(l=>Number.isFinite(l)&&l>0),1/0),m=o.querySelector("[data-products-grid]");if(o.querySelectorAll("[data-products-count]").forEach(l=>{l.textContent=`${p.length} Product${p.length===1?"":"s"}`}),m){const l=re(p,{compareMode:!0,cheapest:v});l?m.innerHTML=l:m.innerHTML='<div class="fs-prod-loading">No products found for this category yet.</div>'}const i=new URLSearchParams(window.location.search).get("q")||"";ne(p,{initialQuery:i,initialMarketId:a,filterMode:"market"}),ie();return}if(a){se();const d=await F(`/farmscout_online/api/get_market_products.php?market_id=${encodeURIComponent(String(a))}`),p=Array.isArray(d.products)?d.products:[],v=Math.min(...p.map(l=>Number(l.raw_price||String(l.current_price||"").replace(/[^\d.]/g,"")||1/0)).filter(l=>Number.isFinite(l)&&l>0),1/0),m=o.querySelector("[data-products-grid]");if(o.querySelectorAll("[data-products-count]").forEach(l=>{l.textContent=`${p.length} Product${p.length===1?"":"s"}`}),m){const l=re(p,{compareMode:!1,cheapest:v});m.innerHTML=l||'<div class="fs-prod-loading">No products found for this market yet.</div>'}const i=new URLSearchParams(window.location.search).get("q")||"";ne(p,{initialQuery:i,initialMarketId:a,filterMode:"category"}),ie()}}catch{}}function ne(e,{initialQuery:a="",initialMarketId:r="",filterMode:t="market"}={}){if(!o||!o.querySelector("[data-products-shop]"))return;const s=Array.from(o.querySelectorAll("[data-product-card]")),c=o.querySelector("[data-products-search]"),d=o.querySelector("[data-products-price]"),p=o.querySelector("[data-products-price-label]"),v=o.querySelector("[data-products-sort]"),m=o.querySelector("[data-products-market-filters]"),u=o.querySelectorAll("[data-products-count]"),i=o.querySelector(".fs-prod-toolbar span"),l=o.querySelector("[data-products-empty]"),g=o.querySelector("[data-products-grid]"),f=o.querySelectorAll("[data-products-reset]");if(!s.length){g&&!g.querySelector(".fs-prod-loading")&&(g.innerHTML='<div class="fs-prod-loading">No products found for this category yet.</div>'),u.forEach(h=>{h.textContent="0 Products"}),i&&(i.textContent="Active filters: None"),l&&(l.hidden=!0);return}c&&a&&!c.value&&(c.value=String(a));const b=e.map(h=>h&&typeof h=="object"&&"min"in h?Number(h.min||0):Number(h.raw_price||String(h.current_price||"").replace(/[^\d.]/g,"")||0)).filter(h=>Number.isFinite(h)),y=Math.max(100,...b),w=Math.ceil(y/50)*50;d&&(d.max=String(w),d.value=String(w)),p&&(p.textContent=`${P(w)}+`);const L=Array.from(new Set(e.map(h=>t==="category"?h.category_filipino||h.category||"":h.best?.market_name||h.market_name||"").filter(Boolean).map(h=>String(h)))).sort((h,S)=>h.localeCompare(S));if(m&&(m.innerHTML=L.length?L.map(h=>`
              <label>
                <input data-products-market type="checkbox" value="${k(I(h))}" />
                ${k(h)}
              </label>
            `).join(""):`<div class="fs-prod-filter-note">No ${t==="category"?"category":"market"} filters available.</div>`),r&&t==="market"){const h=e.find(_=>String(_.best?.market_id||_.market_id||"")===String(r))?.best?.market_name||e.find(_=>String(_.market_id||"")===String(r))?.market_name||"",S=I(h);S&&o.querySelectorAll("[data-products-market]").forEach(_=>{_.checked=_.value===S})}const $=()=>Array.from(o.querySelectorAll("[data-products-market]:checked")).map(h=>h.value),M=()=>{const h=I(c?.value||""),S=Number(d?.value||w),_=$();let x=s.filter(A=>{const T=A.dataset.productName||"",Z=A.dataset.productMarket||"",me=A.dataset.productCategory||"",ve=Number(A.dataset.productPrice||0),he=!h||T.includes(h)||Z.includes(h),ge=!Number.isFinite(S)||ve<=S,ye=_.length===0||_.includes(t==="category"?me:Z);return he&&ge&&ye});const U=v?.value||"original";x=x.slice().sort((A,T)=>U==="price-asc"?Number(A.dataset.productPrice)-Number(T.dataset.productPrice):U==="price-desc"?Number(T.dataset.productPrice)-Number(A.dataset.productPrice):U==="name-asc"?(A.dataset.productName||"").localeCompare(T.dataset.productName||""):Number(A.dataset.productIndex)-Number(T.dataset.productIndex)),s.forEach(A=>{A.hidden=!0}),x.forEach(A=>{A.hidden=!1,g?.appendChild(A)});const O=`${x.length} Product${x.length===1?"":"s"}`;if(u.forEach(A=>{A.textContent=O}),p&&(p.textContent=S>=w?`${P(w)}+`:P(S)),i){const A=_.length?` · ${_.length} ${t==="category"?`categor${_.length===1?"y":"ies"}`:`market${_.length===1?"":"s"}`}`:"",T=h?` · Search "${c.value}"`:"";i.textContent=`Active filters: Price ₱0 - ${S>=w?`${P(w)}+`:P(S)}${A}${T}`}l&&(l.hidden=x.length!==0)},C=()=>{c&&(c.value=""),d&&(d.value=String(w)),v&&(v.value="original"),o.querySelectorAll("[data-products-market]").forEach(h=>{h.checked=!1}),o.querySelector?.("[data-products-market-filters]"),M()};c?.addEventListener("input",M),d?.addEventListener("input",M),v?.addEventListener("change",M),m?.addEventListener("change",M),f.forEach(h=>h.addEventListener("click",S=>{S.preventDefault?.(),C()})),o.querySelector("[data-products-filter-form]")?.addEventListener("submit",h=>h.preventDefault()),M()}async function ie(){if(!o)return;const e=o.querySelector("[data-products-shop]"),a=o.querySelector(".fs-page-head"),r=o.querySelector(".fs-prod-category-hero"),t=Array.from(o.querySelectorAll(".fs-prod-card"));if(e){if(!window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches){const n=Date.now()+2500;for(;document.body.classList.contains("fs-transitioning")&&Date.now()<n;)await new Promise(s=>requestAnimationFrame(s))}try{const n=await H(()=>import("./index-D4-8ALNT.js"),[]),s=n.gsap||n.default||n,c=await H(()=>import("./ScrollToPlugin-DYP6cBsH.js"),[]),d=c.ScrollToPlugin||c.default||c;s.registerPlugin?.(d);const p=[a,r,e].filter(Boolean);s.fromTo(p,{opacity:0,y:34,filter:"blur(6px)"},{opacity:1,y:0,filter:"blur(0px)",duration:.72,stagger:.12,ease:"power3.out"}),t.length&&s.fromTo(t,{opacity:0,y:24},{opacity:1,y:0,duration:.55,stagger:.045,ease:"power3.out",delay:.12}),s.to(window,{duration:.8,ease:"power3.inOut",scrollTo:{y:e,offsetY:18}})}catch{e.scrollIntoView({behavior:"smooth",block:"start"})}}}async function Te(){if(o)try{const e=await F("/farmscout_online/api/get_markets.php"),r=(Array.isArray(e.markets)?e.markets:[]).slice().sort((t,n)=>Number(t.id)-Number(n.id)).slice(0,5);o.innerHTML=de({markets:r.map(t=>({id:String(t.id),name:t.market_name,address:t.address}))}),N(),R(),E(),q(),await De({markets:r})}catch{}}function fe(){return window.FarmScoutAppConfig||{}}function Ie(){return window.google?.maps?Promise.resolve(window.google):V||(V=new Promise((e,a)=>{const r=String(fe().googleMapsApiKey||"").trim();if(!r){a(new Error("Google Maps API key missing"));return}const t=document.getElementById("fs-google-maps-api");if(t){t.addEventListener("load",()=>e(window.google),{once:!0}),t.addEventListener("error",()=>a(new Error("Failed to load Google Maps")),{once:!0});return}const n="__fsGoogleMapsInit";window[n]=()=>{e(window.google);try{delete window[n]}catch{}};const s=document.createElement("script");s.id="fs-google-maps-api",s.async=!0,s.defer=!0,s.src=`https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(r)}&callback=${n}&loading=async`,s.onerror=()=>a(new Error("Failed to load Google Maps")),document.head.appendChild(s)}),V)}function Fe(){const e=fe().mapDefaults||{};return{lat:Number(e.lat||16.8219),lng:Number(e.lng||120.4042),zoom:Number(e.zoom||11)}}function Re(e,a){return e.map(r=>{const t=k(r.market_name||r.name||"Market"),n=Number.isFinite(r.distance_km)?r.distance_km<.05?"~0 km":r.distance_km<.5?`${r.distance_km.toFixed(1)} km`:`${r.distance_km.toFixed(1)} km`:"",s=typeof r.is_open=="boolean"?r.is_open?"open":"closed":"",c=`/farmscout_online/app/products?market=${encodeURIComponent(String(r.id||""))}`;return`
        <div class="fs-nearest-item${String(r.id)===String(a)?" is-active is-recommended":""}" data-nearest-market-id="${k(r.id)}" role="button" tabindex="0">
          <div class="fs-nearest-item-name">
            ${t}
            ${n?`<span class="fs-nearest-item-dist">${n}</span>`:""}
          </div>
          <div class="fs-nearest-item-meta">${k(r.address||"Address unavailable")}</div>
          <div class="fs-nearest-item-foot">
            ${s?`<span class="fs-nearest-status fs-nearest-status--${s}">${s==="open"?"Open":"Closed"}</span>`:""}
            <a class="fs-nearest-item-btn" href="${c}" data-nearest-products-link>View Products</a>
          </div>
          ${String(r.id)===String(a)?'<div class="fs-nearest-item-badge">Nearest match</div>':""}
        </div>
      `}).join("")}function oe({market:e,usedFallback:a,distanceKm:r}){if(!o)return;const t=o.querySelector("[data-nearest-recommendation]");if(!t)return;if(!e){t.innerHTML=`
      <div class="fs-nearest-reco-label">Recommended market</div>
      <div class="fs-nearest-reco-title">No nearby market available</div>
      <div class="fs-nearest-reco-meta">We couldn’t find any market with valid map coordinates yet.</div>
    `;return}const n=k(e.market_name||e.name||"Market"),s=k(e.address||"Address unavailable"),c=a?"Closest market to the map area (we couldn’t read your device location).":"Closest market to your location.",d=Number.isFinite(r)?r<.05?"~0 km — same area as the pin":`${r.toFixed(1)} km away`:"Distance unavailable",v=typeof e.is_open=="boolean"?e.is_open?'<span class="fs-nearest-reco-pill fs-nearest-reco-pill--open">Open now</span>':'<span class="fs-nearest-reco-pill fs-nearest-reco-pill--closed">Closed now</span>':'<span class="fs-nearest-reco-pill fs-nearest-reco-pill--na">Hours N/A</span>',u=`/farmscout_online/app/products?market=${encodeURIComponent(String(e.id||""))}`;t.innerHTML=`
    <div class="fs-nearest-reco-label">Recommended market</div>
    <div class="fs-nearest-reco-title">${n}</div>
    <p class="fs-nearest-reco-reason">${k(c)}</p>
    <div class="fs-nearest-reco-stats">
      <span class="fs-nearest-reco-dist">${k(d)}</span>
      ${v}
    </div>
    <p class="fs-nearest-reco-address">${s}</p>
    <a class="fs-nearest-reco-btn" href="${u}">View Products</a>
  `}function ce(e){const a=[Number.isFinite(Number(e.distance_km))?`${Number(e.distance_km).toFixed(1)} km away`:"",e.product_count?`${Number(e.product_count)} products`:"Products unavailable",typeof e.is_open=="boolean"?e.is_open?"Open today":"Closed":""].filter(Boolean);return`
    <div class="fs-gm-info">
      <div class="fs-gm-info__title">${k(e.market_name||e.name||"Market")}</div>
      <div class="fs-gm-info__meta">${k(e.address||"Address unavailable")}</div>
      <div class="fs-gm-info__chips">
        ${a.map(r=>`<span class="fs-gm-info__chip">${k(r)}</span>`).join("")}
      </div>
      <a class="fs-gm-info__link" href="/farmscout_online/app/products?market=${encodeURIComponent(String(e.id||""))}">View Products</a>
    </div>
  `}async function He(){return await new Promise(e=>{if(!navigator.geolocation)return e(null);navigator.geolocation.getCurrentPosition(a=>e({lat:a.coords.latitude,lng:a.coords.longitude}),()=>e(null),{enableHighAccuracy:!0,timeout:9e3,maximumAge:3e4})})}async function De({markets:e}){if(!o)return;const a=o.querySelector("#nearestMap");if(!a)return;try{await Ie()}catch{return a.innerHTML='<div class="fs-nearest-map-empty">Google Maps could not load right now.</div>',oe({market:null,usedFallback:!1,distanceKm:NaN}),null}const r=Fe(),t=await He(),n=t||{lat:r.lat,lng:r.lng},s=!t,c=s?Math.max(12,Number(r.zoom||11)):14,d=(e||[]).map(f=>{const b=Number(f.latitude||0),y=Number(f.longitude||0);return{...f,latitude:b,longitude:y,distance_km:Ue(n.lat,n.lng,b,y)}}).filter(f=>Number.isFinite(f.latitude)&&Number.isFinite(f.longitude)&&f.latitude!==0&&f.longitude!==0).sort((f,b)=>{const y=Number.isFinite(f.distance_km)?f.distance_km:Number.POSITIVE_INFINITY,w=Number.isFinite(b.distance_km)?b.distance_km:Number.POSITIVE_INFINITY;return y-w}),p=d[0]||null;oe({market:p,usedFallback:s,distanceKm:p?.distance_km});const v=new google.maps.Map(a,{center:n,zoom:c,mapTypeId:google.maps.MapTypeId.ROADMAP,mapTypeControl:!1,streetViewControl:!1,fullscreenControl:!0}),m=new google.maps.LatLngBounds;m.extend(n);const u=new google.maps.InfoWindow,i=new Map,l=new google.maps.Marker({map:v,position:n,title:s?"Default nearby area":"You are here",icon:{url:"https://maps.google.com/mapfiles/ms/icons/blue-dot.png"}});l.addListener("click",()=>{u.setContent(`<div class="fs-gm-info"><div class="fs-gm-info__title">${s?"Default nearby area":"You are here"}</div><div class="fs-gm-info__meta">${s?"Location permission was unavailable, so we used the default market area.":"We used your current location to suggest the nearest market."}</div></div>`),u.open({anchor:l,map:v})}),d.forEach(f=>{const b=p&&String(p.id)===String(f.id),y=new google.maps.Marker({map:v,position:{lat:f.latitude,lng:f.longitude},title:f.market_name||f.name||"Market",animation:b?google.maps.Animation.DROP:void 0,icon:{url:b?"https://maps.google.com/mapfiles/ms/icons/green-dot.png":"https://maps.google.com/mapfiles/ms/icons/red-dot.png"}});y.addListener("click",()=>{u.setContent(ce(f)),u.open({anchor:y,map:v}),o.querySelectorAll("[data-nearest-market-id]").forEach(L=>{const $=L.getAttribute("data-nearest-market-id")===String(f.id);if(L.classList.toggle("is-active",$),$)try{L.scrollIntoView({block:"nearest",behavior:"smooth"})}catch{L.scrollIntoView({block:"nearest"})}})}),i.set(String(f.id),y),m.extend({lat:f.latitude,lng:f.longitude})}),v.setCenter(n),v.setZoom(c);const g=o.querySelector(".fs-nearest-list");if(g&&(g.innerHTML=Re(d,p?.id),g.querySelectorAll("[data-nearest-products-link]").forEach(f=>{f.addEventListener("click",b=>b.stopPropagation())}),g.querySelectorAll("[data-nearest-market-id]").forEach(f=>{const b=()=>{const y=f.getAttribute("data-nearest-market-id")||"",w=i.get(y);w&&(v.panTo(w.getPosition()),google.maps.event.trigger(w,"click"))};f.addEventListener("click",b),f.addEventListener("keydown",y=>{(y.key==="Enter"||y.key===" ")&&(y.preventDefault(),b())})})),p){const f=i.get(String(p.id));f&&window.setTimeout(()=>{u.setContent(ce(p)),u.open({anchor:f,map:v})},280)}return{user:t,recommended:p,usedFallback:s}}function Ue(e,a,r,t){if(!Number.isFinite(e)||!Number.isFinite(a)||!Number.isFinite(r)||!Number.isFinite(t))return NaN;if(r===0||t===0)return NaN;const n=6371,s=(r-e)*Math.PI/180,c=(t-a)*Math.PI/180,d=Math.sin(s/2)*Math.sin(s/2)+Math.cos(e*Math.PI/180)*Math.cos(r*Math.PI/180)*Math.sin(c/2)*Math.sin(c/2),p=2*Math.atan2(Math.sqrt(d),Math.sqrt(1-d));return n*p}function Be({mountEl:e}){o=e;try{Q=window.sessionStorage.getItem("fsHomeIntroPlayed")==="1"}catch{}window.addEventListener("pageshow",a=>{if(a&&a.persisted){window.location.reload();return}document.body.classList.remove("fs-transitioning"),N()}),document.addEventListener("visibilitychange",()=>{document.visibilityState==="visible"&&(document.body.classList.remove("fs-transitioning"),N())}),document.addEventListener("click",a=>{if(a.defaultPrevented||a.button!==0||a.metaKey||a.ctrlKey||a.shiftKey||a.altKey)return;const r=a.target.closest?.(".js-price-alert");if(r){if(r.getAttribute("aria-disabled")==="true")return;a.preventDefault(),a.stopPropagation();const u=Number(r.getAttribute("data-product-id")||0);if(!Number.isFinite(u)||u<=0)return;(async()=>{try{if((await fetch("/farmscout_online/api/create_price_alert.php",{method:"POST",credentials:"include",headers:{"Content-Type":"application/json",Accept:"application/json"},body:JSON.stringify({product_id:u,alert_type:"change",target_price:0})})).status===401){window.location.href="/farmscout_online/login.php";return}}catch{}window.location.href="/farmscout_online/user-account.php?section=price_alerts&message="+encodeURIComponent("Price alert added.")})();return}const t=a.target.closest("a");if(!t||!ke(t)||(t.getAttribute("href")||"").trim()==="#")return;const s=new URL(t.href,window.location.href),c=D(s.pathname),d=["/","/markets","/nearest","/products","/alerts","/account"];if(!d.includes(c))return;const p=D(window.location.pathname);if(s.hash&&s.hash.length>1&&c===p&&d.includes(c)){a.preventDefault();const m=document.querySelector(s.hash),u=!window.matchMedia("(prefers-reduced-motion: reduce)").matches;m?.scrollIntoView({behavior:u?"smooth":"auto",block:"start"});try{history.pushState({},"",`${window.location.pathname}${s.search}${s.hash}`)}catch{}return}if(t.hasAttribute("data-fs-scroll-home-top")&&c==="/"){a.preventDefault();const m=!window.matchMedia("(prefers-reduced-motion: reduce)").matches;window.scrollTo({top:0,behavior:m?"smooth":"auto"});return}if(a.preventDefault(),t.hasAttribute("data-card-animate")){const m=t.closest("[data-card-animate]");Ae(m),window.setTimeout(()=>{B(c+s.search+s.hash)},120);return}B(c+s.search+s.hash)},{capture:!0}),document.addEventListener("submit",a=>{const r=a.target;if(!(r instanceof HTMLFormElement)||!r.classList.contains("fs-sf-search"))return;a.preventDefault();const t=new FormData(r),n=String(t.get("q")||"").trim();n&&B(`/products?q=${encodeURIComponent(n)}`)},!0),window.addEventListener("popstate",async()=>{const{runSvgPathTransition:a}=await H(async()=>{const{runSvgPathTransition:r}=await import("./svgPathTransition-CqkVcTN1.js");return{runSvgPathTransition:r}},[]);await a("cover"),G(),await a("uncover")}),G();try{Q&&window.sessionStorage.setItem("fsHomeIntroPlayed","1")}catch{}}Be({mountEl:document.getElementById("app")});export{H as _};

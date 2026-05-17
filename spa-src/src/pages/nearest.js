export function renderNearestMarket({ markets } = {}) {
  const safeMarkets = Array.isArray(markets) ? markets : [];
  return `
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
                ${safeMarkets
                  .map(
                    (m) => `
                    <div class="fs-nearest-item">
                      <div class="fs-nearest-item-name">
                        ${m.name}
                        ${
                          typeof m.distance_km === "number"
                            ? `<span class="fs-nearest-item-dist">${m.distance_km.toFixed(1)} km</span>`
                            : ""
                        }
                      </div>
                      <div class="fs-nearest-item-meta">${m.address}</div>
                    </div>
                  `
                  )
                  .join("")}
              </div>
            </aside>
          </section>
        </div>
      </main>
    </div>
  `;
}


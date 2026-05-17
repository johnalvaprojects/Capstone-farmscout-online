export function renderMarkets({ markets, selectedMarketId } = {}) {
  const safeMarkets = Array.isArray(markets) ? markets : [];

  return `
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
            <span class="total-slides">${String(Math.max(1, safeMarkets.length)).padStart(2, "0")}</span>
          </div>
          <div class="counter-nav next-slide" role="button" tabindex="0" aria-label="Next slide">⟫</div>
        </div>
        <div class="slide-title-container">
          <div class="slide-title">${safeMarkets[0]?.name || "Markets"}</div>
        </div>
        <div class="drag-indicator" aria-hidden="true"></div>
        <div class="thumbs-container" aria-label="Slide thumbnails">
          <div class="frost-bg"></div>
          <div class="slide-thumbs"></div>
        </div>
      </div>

      <div class="slides" aria-label="Market slides">
        ${safeMarkets
          .map((m, idx) => {
            return `
              <div class="slide${idx === 0 ? " slide--current" : ""}">
                <div class="slide__img"></div>
              </div>
            `;
          })
          .join("")}
      </div>
    </div>
  `;
}


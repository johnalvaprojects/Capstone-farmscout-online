export function renderHome() {
  return `
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
  `;
}

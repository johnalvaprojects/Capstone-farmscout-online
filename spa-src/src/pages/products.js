export function renderProducts({ categories, selectedCategoryId, selectedMarketName = "" } = {}) {
  const safeCategories = Array.isArray(categories) ? categories : [];
  const selected = safeCategories.find((c) => String(c.id) === String(selectedCategoryId || "")) || null;
  const displayCategoryTitle = (title = "") => {
    const key = String(title).toLowerCase().trim();
    if (key === "grains / rice" || key === "grains/rice") return "Rice";
    if (key === "poultry / eggs" || key === "poultry/eggs") return "Poultry";
    return title;
  };
  const getCategoryVideo = (title = "") => {
    const key = String(title).toLowerCase();
    if (key.includes("vegetable")) return "vegetables.mp4";
    if (key.includes("fruit")) return "Fruits.mp4";
    if (key.includes("meat")) return "meat.mp4";
    if (key.includes("fish")) return "fish.mp4";
    if (key.includes("rice") || key.includes("grain")) return "rice.mp4";
    if (key.includes("poultry") || key.includes("egg")) return "egg.mp4";
    return "vegetables.mp4";
  };

  /** Per-market looped banners in `app/assets/video/market vids/` — filenames can differ slightly from DB names. */
  const MARKET_VIDS_DIR = "market vids";
  const marketVidsBase = () => `/farmscout_online/app/assets/video/${encodeURIComponent(MARKET_VIDS_DIR)}/`;
  const getMarketVideoSrcSet = (marketName = "") => {
    const base = marketVidsBase();
    const urls = [];
    const addFile = (filename) => {
      const f = String(filename || "").trim();
      if (!f.toLowerCase().endsWith(".mp4")) return;
      const u = base + encodeURIComponent(f);
      if (!urls.includes(u)) urls.push(u);
    };
    const name = String(marketName || "").trim();
    if (name) {
      const norm = name.replace(/\s+/g, " ").trim();
      const lower = norm.toLowerCase();
      const titleCase = lower.replace(/\b\w/g, (c) => c.toUpperCase());
      const noPublicCity = norm
        .replace(/\s+public\s+/gi, " ")
        .replace(/\s+city\s+/gi, " ")
        .trim();
      const firstWord = norm.split(/[\s,]+/)[0] || "";

      addFile(`${norm}.mp4`);
      addFile(`${lower}.mp4`);
      addFile(`${titleCase}.mp4`);
      addFile(`${noPublicCity}.mp4`);
      addFile(`${noPublicCity.toLowerCase()}.mp4`);

      if (firstWord.length > 1) {
        addFile(`${firstWord} Market.mp4`);
        addFile(`${firstWord.toLowerCase()} market.mp4`);
        addFile(`${firstWord.charAt(0).toUpperCase() + firstWord.slice(1).toLowerCase()} Market.mp4`);
        addFile(`${firstWord.charAt(0).toUpperCase() + firstWord.slice(1).toLowerCase()} market.mp4`);
      }

      /* Matches files like "San juan Market.mp4" (only the first word title-cased). */
      if (lower.endsWith(" market")) {
        const core = lower.replace(/\s+market$/i, "").trim();
        const bits = core.split(/\s+/).filter(Boolean);
        if (bits.length) {
          const head = bits[0].charAt(0).toUpperCase() + bits[0].slice(1);
          const tail = bits.slice(1).join(" ");
          addFile(`${head}${tail ? ` ${tail}` : ""} Market.mp4`);
        }
      }
    }
    urls.push("/farmscout_online/app/assets/video/vegetables.mp4");
    return urls;
  };

  const isMarketMode = !selected && !!selectedMarketName;
  return `
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
          ${
            (selected || isMarketMode)
              ? `
                <!-- detail view handles its own hero -->
              `
              : `
                <header class="fs-cat-hero" aria-label="Products categories header">
                  <div class="fs-cat-section-head" aria-hidden="true">
                    <span class="fs-cat-head-kicker">Choose a path</span>
                    <h2 class="fs-cat-head-title">Categories</h2>
                    <p class="fs-cat-section-sub fs-cat-head-sub">Browse categories first, then compare prices across markets.</p>
                  </div>
                </header>
              `
          }

          ${
            (selected || isMarketMode)
              ? `
                <section class="fs-prod-category-hero${selected || isMarketMode ? " has-video" : ""}" aria-label="${selected ? `${displayCategoryTitle(selected.title)} category` : `${selectedMarketName} market`}">
                  ${
                    selected
                      ? (() => {
                          const primary = getCategoryVideo(selected.title);
                          const videoA = `/farmscout_online/app/assets/video/${primary}`;
                          const videoB = `/farmscout_online/app/assets/video/${String(primary).toLowerCase()}`;
                          const videoC = `/farmscout_online/app/assets/video/${primary
                            .replace(/vegetables/i, "vegetable")
                            .replace(/fruits/i, "fruit")}`;
                          const videoD = `/farmscout_online/app/assets/video/poultry.mp4`;
                          return `
                            <video class="fs-prod-hero-video" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
                              <source src="${videoA}" type="video/mp4" />
                              <source src="${videoB}" type="video/mp4" />
                              <source src="${videoC}" type="video/mp4" />
                              <source src="${videoD}" type="video/mp4" />
                            </video>
                          `;
                        })()
                      : isMarketMode
                        ? (() => {
                            const srcs = getMarketVideoSrcSet(selectedMarketName);
                            return `
                            <video class="fs-prod-hero-video" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
                              ${srcs.map((src) => `<source src="${src}" type="video/mp4" />`).join("")}
                            </video>
                          `;
                          })()
                        : ``
                  }
                  <h2>${selected ? displayCategoryTitle(selected.title) : selectedMarketName}</h2>
                  <p class="fs-prod-category-sub">${selected ? `Browse ${displayCategoryTitle(selected.title)} products, compare prices, and find the best market.` : `Browse products available in ${selectedMarketName}.`}</p>
                  <div class="fs-prod-crumb">${selected ? `Category / ${displayCategoryTitle(selected.title)}` : `Market / ${selectedMarketName}`}</div>
                </section>

                <a class="fs-prod-floating-back" href="/farmscout_online/app/products" aria-label="Back to categories">← Back to categories</a>

                <section class="fs-prod-shop" data-products-shop aria-label="${selected ? displayCategoryTitle(selected.title) : selectedMarketName} products">
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
                      <h4>${isMarketMode ? "Category" : "Market"}</h4>
                      <div data-products-market-filters data-products-filter-kind="${isMarketMode ? "category" : "market"}">
                        <div class="fs-prod-filter-note">Loading ${isMarketMode ? "categories" : "markets"}...</div>
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
              `
              : `
                <section class="fs-cat-grid" aria-label="Product categories">
                  ${safeCategories
                    .map((c) => {
                      const count = Number(c.product_count ?? c.products?.length ?? 0);
                      const title = displayCategoryTitle(c.title);
                      const primary = getCategoryVideo(c.title);
                      const videoA = `/farmscout_online/app/assets/video/${primary}`;
                      const videoB = `/farmscout_online/app/assets/video/${String(primary).toLowerCase()}`;
                      const videoC = `/farmscout_online/app/assets/video/${primary.replace(/vegetables/i, "vegetable").replace(/fruits/i, "fruit")}`;
                      const videoD = `/farmscout_online/app/assets/video/poultry.mp4`;
                      return `
                        <a class="fs-cat-card" href="/farmscout_online/app/products?category=${encodeURIComponent(c.id)}" aria-label="${title}">
                          <div class="fs-cat-copy">
                            <span class="fs-cat-count" aria-label="${count} products">${count} PRODUCTS</span>
                            <div class="fs-cat-title">${title}</div>
                            <div class="fs-cat-desc">Compare prices across markets.</div>
                            <span class="fs-cat-arrow" aria-hidden="true">→</span>
                          </div>
                          <div class="fs-cat-media" aria-hidden="true">
                            <video autoplay muted loop playsinline preload="metadata">
                              <source src="${videoA}" type="video/mp4" />
                              <source src="${videoB}" type="video/mp4" />
                              <source src="${videoC}" type="video/mp4" />
                              <source src="${videoD}" type="video/mp4" />
                            </video>
                          </div>
                        </a>
                      `;
                    })
                    .join("")}
                </section>
              `
          }
        </div>
      </main>
    </div>
  `;
}


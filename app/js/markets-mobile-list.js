/**
 * Market Finder SPA — hub (SELECT A MARKET + categories) on all viewports.
 * Market detail on small screens still uses the wireframe panes when ?market= is set.
 */
(function () {
  "use strict";

  var MQ = "(max-width: 768px)";
  var ATTR = "data-fs-markets-mobile";

  function phpBase() {
    var p = window.location.pathname || "";
    var i = p.indexOf("/farmscout_online");
    if (i !== -1) return "/farmscout_online";
    return "";
  }

  function api(path) {
    var b = phpBase();
    var x = path.charAt(0) === "/" ? path : "/" + path;
    return b + x;
  }

  function appBasePath() {
    var p = window.location.pathname || "";
    var m = p.match(/^(.*\/app)(?:\/|$)/i);
    return m ? m[1] : "";
  }

  /** Legacy wire-detail back link (pathname only, no query). */
  function marketsHubHref() {
    var p = window.location.pathname || "";
    if (p) return p;
    var base = appBasePath();
    if (base) return base + "/markets";
    return "/markets";
  }

  function getSelectedMarketId() {
    var q = new URLSearchParams(window.location.search || "").get("market");
    var n = parseInt(q, 10);
    return n > 0 ? n : 0;
  }

  function placeholderImageUrl() {
    return api("/assets/images/placeholder-product.svg");
  }

  /** Bumped when mobile hub CSS/JS changes so Hostinger/browser caches refresh */
  var MOBILE_HUB_ASSET_VER = "hub2";

  function withCacheBust(assetPath) {
    var p = String(assetPath || "");
    if (!p) return p;
    return p + (p.indexOf("?") >= 0 ? "&" : "?") + "v=" + MOBILE_HUB_ASSET_VER;
  }

  function mobileCssHref() {
    var ap = appBasePath();
    var base = ap ? ap + "/css/markets-mobile-list.css" : api("/app/css/markets-mobile-list.css");
    return withCacheBust(base);
  }

  function mobileHubCssHref() {
    var ap = appBasePath();
    var base = ap ? ap + "/css/markets-mobile-hub.css" : api("/app/css/markets-mobile-hub.css");
    return withCacheBust(base);
  }

  function loadMobileHubCss() {
    var href = mobileHubCssHref();
    var link = document.getElementById("fs-markets-mobile-hub-css");
    if (link) {
      if (link.getAttribute("href") !== href) link.setAttribute("href", href);
      return;
    }
    link = document.createElement("link");
    link.id = "fs-markets-mobile-hub-css";
    link.rel = "stylesheet";
    link.href = href;
    document.head.appendChild(link);
  }

  function loadCss() {
    var href = mobileCssHref();
    var link = document.getElementById("fs-markets-mobile-list-css");
    if (link) {
      if (link.getAttribute("href") !== href) link.setAttribute("href", href);
    } else {
      link = document.createElement("link");
      link.id = "fs-markets-mobile-list-css";
      link.rel = "stylesheet";
      link.href = href;
      document.head.appendChild(link);
    }
    loadMobileHubCss();
  }

  /** Separate mobile hub layout (see markets-mobile-hub.css); toggled by viewport width. */
  function syncMobileHubLayout() {
    var shell = document.getElementById("mfMarketsMobileShell");
    if (!shell) return;
    shell.classList.toggle("mf-mm-hub--mobile", window.matchMedia(MQ).matches);
  }

  function isMobile() {
    return window.matchMedia(MQ).matches;
  }

  /**
   * Bundled hero uses huge inline font-size (clamp 147px…378px) + GSAP reveals.
   * If external CSS is cached/missing, force-hide on small viewports with inline
   * !important (beats non-!important inline). Backup style attr for desktop restore.
   */
  function applyZHeroMobileMode() {
    var nodes = document.querySelectorAll(".mf-zhero-wrap");
    var i;
    var el;
    var bak;
    if (isMobile()) {
      for (i = 0; i < nodes.length; i++) {
        el = nodes[i];
        if (!el.getAttribute("data-fs-zhero-stylebak")) {
          bak = el.getAttribute("style");
          el.setAttribute("data-fs-zhero-stylebak", bak == null ? "" : bak);
        }
        el.style.setProperty("display", "none", "important");
        el.style.setProperty("visibility", "hidden", "important");
        el.style.setProperty("height", "0", "important");
        el.style.setProperty("min-height", "0", "important");
        el.style.setProperty("overflow", "hidden", "important");
        el.style.setProperty("padding", "0", "important");
        el.style.setProperty("margin", "0", "important");
        el.setAttribute("aria-hidden", "true");
      }
    } else {
      for (i = 0; i < nodes.length; i++) {
        el = nodes[i];
        bak = el.getAttribute("data-fs-zhero-stylebak");
        /* Only restore if we hid this hero on mobile — never strip inline styles
           from a fresh hero (removeProperty would delete display:flex from bundle). */
        if (bak !== null) {
          el.setAttribute("style", bak);
          el.removeAttribute("data-fs-zhero-stylebak");
          el.removeAttribute("aria-hidden");
        }
      }
    }
  }

  var syncDebounceTimer = null;
  function scheduleSync() {
    if (syncDebounceTimer !== null) {
      window.clearTimeout(syncDebounceTimer);
    }
    syncDebounceTimer = window.setTimeout(function () {
      syncDebounceTimer = null;
      sync();
    }, 48);
  }

  function findMarketsPage(root) {
    var byAria = root.querySelector('.mf-page[aria-label="Market Finder"]');
    if (byAria) return byAria;
    var pages = root.querySelectorAll(".mf-page");
    for (var i = 0; i < pages.length; i++) {
      if (
        pages[i].querySelector(".mf-layout--markets-hub") ||
        pages[i].querySelector("#mfMarketsCarouselHost")
      ) {
        return pages[i];
      }
    }
    return null;
  }

  function hasCarouselLayout(mfPage) {
    if (!mfPage) return false;
    return !!(
      mfPage.querySelector(".mf-layout--markets-hub") ||
      mfPage.querySelector("#mfMarketsCarouselHost")
    );
  }

  function isMarketsHubView() {
    var q = new URLSearchParams(window.location.search || "").get("market");
    if (q === null || String(q).trim() === "") return true;
    var n = parseInt(q, 10);
    return !n;
  }

  function formatMarketId(id) {
    var n = String(Math.max(0, parseInt(id, 10) || 0));
    return n.length >= 8 ? n : "00000000".slice(n.length) + n;
  }

  function ensureShell(mfPage) {
    var carousel =
      mfPage.querySelector(".mf-layout--markets-hub") ||
      mfPage.querySelector("#mfMarketsHubAnchor");
    if (!carousel || !carousel.parentNode) return null;
    var existing = mfPage.querySelector("#mfMarketsMobileShell");
    if (existing) {
      if (
        !existing.querySelector(".mf-mm-list--hub") ||
        existing.querySelector("#mfMarketsMobileFilter") ||
        !existing.querySelector("#mfHubMarketsSearchWrap") ||
        !existing.querySelector("#mfHubBackMarkets") ||
        !existing.querySelector("#mfHubCategoriesBlock") ||
        !existing.querySelector("#mfHubCategoriesSection") ||
        !existing.querySelector("#mfHubProductsCountBar")
      ) {
        try {
          existing.remove();
        } catch (e) {}
      } else {
        syncMobileHubLayout();
        return existing;
      }
    }
    var shell = document.createElement("div");
    shell.id = "mfMarketsMobileShell";
    shell.className = "mf-mm-hub mf-markets-mobile";
    shell.setAttribute("aria-label", "Select a market");
    shell.innerHTML =
      '<header class="mf-mm-head">' +
      '<h2 class="mf-mm-head__title">SELECT A MARKET</h2>' +
      '<p class="mf-mm-head__sub">Choose a market to browse available products.</p>' +
      "</header>" +
      '<div class="mf-mm-markets-search" id="mfHubMarketsSearchWrap">' +
      '<label class="visually-hidden" for="mfHubMarketsSearch">Search markets</label>' +
      '<input type="search" id="mfHubMarketsSearch" class="mf-mm-markets-search__input" placeholder="Search markets..." autocomplete="off" />' +
      '<button type="button" class="mf-mm-markets-search__clear" id="mfHubMarketsSearchClear">CLEAR</button>' +
      "</div>" +
      '<ul id="mfMarketsMobileList" class="mf-mm-list mf-mm-list--hub"></ul>' +
      '<section id="mfHubCategoriesSection" class="mf-mm-hub-categories mf-mm-hub-categories--collapsed" aria-hidden="true">' +
      '<button type="button" id="mfHubBackMarkets" class="mf-mm-back mf-mm-back--all-markets" hidden>← ALL MARKETS</button>' +
      '<div id="mfHubCategoriesBlock" class="mf-mm-hub-categories-block">' +
      '<p class="mf-mm-cat-eyebrow">PRODUCT CATEGORIES</p>' +
      '<h2 class="mf-mm-market-heading" id="mfHubMarketHeading"></h2>' +
      '<p class="mf-mm-legacy-sub mf-mm-market-sub">Browse products by category.</p>' +
      '<div id="mfHubCategoryGrid" class="mf-mm-hub-category-grid"></div>' +
      "</div>" +
      '<div id="mfHubProductsPanel" class="mf-mm-hub-products-panel" hidden>' +
      '<button type="button" class="mf-mm-back mf-mm-back--secondary" id="mfHubBackCategories">← Categories</button>' +
      '<div id="mfHubProductsCountBar" class="mf-mm-hub-products-countbar" hidden aria-live="polite"></div>' +
      '<h2 class="mf-mm-legacy-title">PRODUCTS</h2>' +
      '<p class="mf-mm-legacy-sub" id="mfHubProductsSubtitle"></p>' +
      '<div class="mf-mm-products-search">' +
      '<input type="search" id="mfHubProductsSearch" class="mf-mm-legacy-input" placeholder="Search products..." autocomplete="off" />' +
      '<button type="button" class="mf-mm-legacy-clear" id="mfHubProductsClear">CLEAR</button>' +
      "</div>" +
      '<div id="mfHubProductsWrap" class="mf-mm-products-grid-legacy"></div>' +
      "</div>" +
      '<p id="mfHubDetailStatus" class="mf-mm-status mf-mm-hub-cat-status" hidden></p>' +
      "</section>" +
      '<p id="mfMarketsMobileStatus" class="mf-mm-status" hidden></p>';
    carousel.parentNode.insertBefore(shell, carousel);
    if (!document.getElementById("fs-markets-mobile-visually-hidden")) {
      var st = document.createElement("style");
      st.id = "fs-markets-mobile-visually-hidden";
      st.textContent =
        ".visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}";
      document.head.appendChild(st);
    }
    syncMobileHubLayout();
    return shell;
  }

  var allMarkets = [];
  /** Inline hub flow (no ?market= URL). */
  var hubSelectedMarketId = null;
  /* Canonical English labels (shown uppercase); match API category_name when present. */
  var HUB_CATEGORY_ORDER = [
    { match: /vegetable/i, name: "Vegetables", desc: "Leafy greens, roots, and seasonal produce." },
    { match: /fruit/i, name: "Fruits", desc: "Fresh local and seasonal fruit." },
    { match: /fish|seafood/i, name: "Fish and Seafood", desc: "Daily catch and coastal staples." },
    { match: /meat|poultry/i, name: "Meat and Poultry", desc: "Cuts, chicken, and market-fresh protein." },
    { match: /rice|grain/i, name: "Rice and Grains", desc: "Staples, rice varieties, and dry goods." },
    { match: /egg|dairy|milk/i, name: "Eggs and Dairy", desc: "Eggs, milk, and chilled essentials." },
  ];

  function stripMarketQueryIfOnMarketsPage() {
    var p = window.location.pathname || "";
    if (p.indexOf("markets") === -1) return;
    if (!window.location.search || window.location.search.indexOf("market=") === -1) return;
    try {
      history.replaceState(null, "", p);
    } catch (e) {}
  }

  function buildHubSlotsFromApi(apiCategories) {
    var slots = HUB_CATEGORY_ORDER.map(function (def) {
      return {
        apiName: "",
        displayName: def.name,
        desc: def.desc,
        product_count: 0,
      };
    });
    var used = {};
    (apiCategories || []).forEach(function (c) {
      var apiName = String(c.category_name || c.name || "").trim();
      if (!apiName) return;
      for (var i = 0; i < HUB_CATEGORY_ORDER.length; i++) {
        if (used[i]) continue;
        if (HUB_CATEGORY_ORDER[i].match.test(apiName)) {
          slots[i].apiName = apiName;
          var enName = String(c.category_name || "").trim();
          slots[i].displayName = enName || HUB_CATEGORY_ORDER[i].name;
          slots[i].product_count = Number(c.product_count || 0);
          var d = String(c.description || "").trim();
          if (d) slots[i].desc = d;
          used[i] = true;
          break;
        }
      }
    });
    for (var j = 0; j < slots.length; j++) {
      if (!slots[j].apiName) {
        slots[j].apiName = slots[j].displayName;
      }
    }
    return slots;
  }

  function appendCategoryCard(grid, c) {
    var apiName = String(c.apiName || c.category_name || "").trim();
    if (!apiName) return;
    var label = String(c.displayName || c.category_name || apiName).trim();
    var desc = String(c.desc || c.description || "").trim();
    if (!desc) desc = "Browse products in this category.";

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "mf-mm-category-card";
    btn.setAttribute("data-category", apiName);
    btn.setAttribute("data-subtitle", label);

    var nm = document.createElement("span");
    nm.className = "mf-mm-category-name";
    nm.textContent = label.toUpperCase();
    btn.appendChild(nm);

    var d = document.createElement("span");
    d.className = "mf-mm-category-desc";
    d.textContent = desc;
    btn.appendChild(d);

    var meta = document.createElement("div");
    meta.className = "mf-mm-category-card__meta";
    var pc = Number(c.product_count || 0);
    var ct = document.createElement("span");
    ct.className = "mf-mm-category-count";
    ct.textContent = pc + (pc === 1 ? " product" : " products");
    meta.appendChild(ct);
    var icons = document.createElement("span");
    icons.className = "mf-mm-category-card__icons";
    icons.setAttribute("aria-hidden", "true");
    for (var iq = 0; iq < 3; iq++) {
      var sq = document.createElement("span");
      sq.className = "mf-mm-category-card__icon-sq";
      icons.appendChild(sq);
    }
    meta.appendChild(icons);
    btn.appendChild(meta);

    grid.appendChild(btn);
  }

  var hubOsmoDispose = null;

  function loadHubCategoryCarouselCss() {
    var id = "fs-mf-hub-category-carousel-css";
    if (document.getElementById(id)) return;
    var ap = appBasePath();
    var href = ap ? ap + "/css/category-carousel.css" : api("/app/css/category-carousel.css");
    var link = document.createElement("link");
    link.id = id;
    link.rel = "stylesheet";
    link.href = href;
    document.head.appendChild(link);
  }

  function setHtmlFsProductSlider(on) {
    if (on) {
      document.documentElement.classList.add("mf-fs-product-slider");
      document.body.classList.add("mf-fs-product-slider");
    } else {
      document.documentElement.classList.remove("mf-fs-product-slider");
      document.body.classList.remove("mf-fs-product-slider");
    }
  }

  function closeHubOsmoFullscreen() {
    if (typeof hubOsmoDispose === "function") {
      try {
        hubOsmoDispose();
      } catch (e) {}
    }
    hubOsmoDispose = null;
    var root = document.getElementById("mfHubOsmoRoot");
    if (root && root.parentNode) {
      try {
        root.parentNode.removeChild(root);
      } catch (e2) {}
    }
    setHtmlFsProductSlider(false);
  }

  /**
   * Mobile: list + search in #mfHubProductsPanel (matches Hostinger market-finder.php).
   * Desktop uses openHubOsmoFullscreen (cinematic slider).
   */
  function openHubProductsInlineList(
    shell,
    products,
    emptyOrErrMsg,
    catLabel,
    mktName
  ) {
    closeHubOsmoFullscreen();
    var panel = shell.querySelector("#mfHubProductsPanel");
    var sub = shell.querySelector("#mfHubProductsSubtitle");
    var countBar = shell.querySelector("#mfHubProductsCountBar");
    var cl = String(catLabel || "").trim();
    var mk = String(mktName || "").trim();
    if (sub) {
      sub.textContent = cl && mk ? cl + " - " + mk : cl || mk || "";
    }
    if (countBar) {
      setHubProductsCountBar(shell, products && products.length ? products.length : 0);
      countBar.hidden = false;
    }
    shell.classList.add("mf-mm-hub--products-view");
    if (panel) panel.hidden = false;
    renderHubProductCards(
      shell,
      products || [],
      emptyOrErrMsg || "No products in this category yet."
    );
    if (panel) {
      window.requestAnimationFrame(function () {
        panel.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    }
  }

  function setHubProductsCountBar(shell, n) {
    var countBar = shell && shell.querySelector("#mfHubProductsCountBar");
    if (!countBar) return;
    var num = Math.max(0, parseInt(n, 10) || 0);
    countBar.innerHTML =
      '<span class="mf-mm-hub-products-countbar__head">PROCESSED</span> ' +
      '<span class="mf-mm-hub-products-countbar__sub">' +
      num +
      (num === 1 ? " product" : " products") +
      "</span>";
  }

  function pad2(n) {
    var x = Math.max(0, parseInt(n, 10) || 0);
    return x < 10 ? "0" + x : String(x);
  }

  function buildHubOsmoChipsInner(p) {
    var opts = p.unit_options;
    var priceStr = String(p.current_price || p.price || "₱0.00");
    if (!Array.isArray(opts) || !opts.length) {
      var u = String(p.unit || "").trim() || "unit";
      var line = u.toLowerCase().indexOf("per") === 0 ? u : "per " + u;
      var sp = document.createElement("span");
      sp.className = "mf-osmo-prod-chip";
      sp.textContent = line + " · " + priceStr;
      return sp;
    }
    var frag = document.createDocumentFragment();
    opts.slice(0, 6).forEach(function (opt) {
      var label = String(opt.label || opt.unit_label || "");
      var pr = opt.price != null ? Number(opt.price) : NaN;
      var text =
        label && !isNaN(pr) ? label + " - ₱" + pr.toFixed(2) : label;
      var sp = document.createElement("span");
      sp.className = "mf-osmo-prod-chip";
      sp.textContent = text;
      frag.appendChild(sp);
    });
    return frag;
  }

  function appendHubOsmoProductSlide(listEl, p, ph) {
    var slide = document.createElement("div");
    slide.setAttribute("data-mf-slider-slide", "");
    slide.className = "mf-osmo-slide";

    var inner = document.createElement("div");
    inner.className = "mf-osmo-slide-inner mf-osmo-slide-inner--product";

    var article = document.createElement("article");
    article.className = "mf-osmo-prod-card";
    var id = String(p.id != null ? p.id : "");
    var dispName = String(p.name || p.filipino_name || "Product");
    var priceStr = String(p.current_price || p.price || "₱0.00");
    var numeric = stripPriceNum(priceStr);
    article.setAttribute("data-product-id", id);
    article.setAttribute("data-product-name", dispName);
    article.setAttribute("data-product-price", String(numeric));
    article.setAttribute(
      "data-product-unit",
      String(p.unit || "")
        .replace(/^per\s+/i, "")
        .trim() || "unit"
    );
    article.setAttribute("aria-label", dispName);

    var media = document.createElement("div");
    media.className = "mf-osmo-prod-media mf-osmo-prod-media--wrap";

    var bell = document.createElement("button");
    bell.type = "button";
    bell.className = "mf-osmo-prod-bell js-mf-price-alert";
    bell.title = "Price alert";
    bell.setAttribute("aria-label", "Price alert");
    bell.innerHTML =
      '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">' +
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />' +
      "</svg>";

    var img = document.createElement("img");
    img.className = "mf-osmo-prod-image";
    img.alt = dispName;
    img.loading = "lazy";
    img.decoding = "async";
    img.src = resolveProductImageUrl(p.image_url);
    img.onerror = function () {
      img.onerror = null;
      img.src = ph;
    };

    media.appendChild(bell);
    media.appendChild(img);
    article.appendChild(media);

    var body = document.createElement("div");
    body.className = "mf-osmo-prod-body";

    var title = document.createElement("h3");
    title.className = "mf-osmo-prod-title";
    title.textContent = dispName;
    body.appendChild(title);

    var desc = String(p.description || "").trim();
    if (!desc) desc = "Fresh market product from local farmers.";
    if (desc.length > 110) desc = desc.slice(0, 110);
    var descEl = document.createElement("p");
    descEl.className = "mf-osmo-prod-desc";
    descEl.textContent = desc;
    body.appendChild(descEl);

    var priceEl = document.createElement("div");
    priceEl.className = "mf-osmo-prod-price";
    priceEl.textContent = priceStr;
    body.appendChild(priceEl);

    var unitEl = document.createElement("div");
    unitEl.className = "mf-osmo-prod-unit";
    var uShow = String(p.unit || "").trim() || "unit";
    unitEl.textContent =
      uShow.toLowerCase().indexOf("per") === 0 ? uShow : "per " + uShow;
    body.appendChild(unitEl);

    var div = document.createElement("div");
    div.className = "mf-osmo-prod-divider";
    body.appendChild(div);

    var optLab = document.createElement("div");
    optLab.className = "mf-osmo-prod-opt-label";
    optLab.textContent = "Other Options:";
    body.appendChild(optLab);

    var chips = document.createElement("div");
    chips.className = "mf-osmo-prod-chips";
    chips.appendChild(buildHubOsmoChipsInner(p));
    body.appendChild(chips);

    var actions = document.createElement("div");
    actions.className = "mf-osmo-prod-actions";
    var res = document.createElement("button");
    res.type = "button";
    res.className = "mf-osmo-prod-btn js-mf-reserve";
    res.textContent = "RESERVE";
    var hBtn = document.createElement("button");
    hBtn.type = "button";
    hBtn.className = "mf-osmo-prod-btn mf-osmo-prod-btn--ghost js-mf-history";
    hBtn.textContent = "HISTORY";
    actions.appendChild(res);
    actions.appendChild(hBtn);
    body.appendChild(actions);

    article.appendChild(body);
    inner.appendChild(article);
    slide.appendChild(inner);
    listEl.appendChild(slide);
  }

  function appendHubOsmoEmptySlide(listEl, msg) {
    var slide = document.createElement("div");
    slide.setAttribute("data-mf-slider-slide", "");
    slide.className = "mf-osmo-slide";
    var inner = document.createElement("div");
    inner.className = "mf-osmo-slide-inner mf-osmo-slide-inner--empty";
    var p = document.createElement("p");
    p.className = "mf-osmo-empty-msg";
    p.textContent = msg || "No products in this category yet.";
    inner.appendChild(p);
    slide.appendChild(inner);
    listEl.appendChild(slide);
  }

  function openHubOsmoFullscreen(shell, products, headingLine, emptyOrErrMsg) {
    closeHubOsmoFullscreen();
    loadHubCategoryCarouselCss();

    var page = shell && shell.closest ? shell.closest(".mf-page") : null;
    var mount = page || document.body;

    var root = document.createElement("section");
    root.id = "mfHubOsmoRoot";
    root.className = "mf-category-slider-section mf-slider-fullscreen is-open";
    root.setAttribute("aria-hidden", "false");

    var toolbar = document.createElement("div");
    toolbar.className = "mf-slider-section-toolbar";
    var backBtn = document.createElement("button");
    backBtn.type = "button";
    backBtn.id = "mfHubOsmoClose";
    backBtn.className = "mf-slider-back";
    backBtn.textContent = "← Categories";
    var h2 = document.createElement("h2");
    h2.id = "mfHubOsmoHeading";
    h2.className = "mf-slider-heading";
    h2.textContent = headingLine || "Products";
    toolbar.appendChild(backBtn);
    toolbar.appendChild(h2);

    var cloneable = document.createElement("div");
    cloneable.className = "mf-osmo-cloneable";

    var overlay = document.createElement("div");
    overlay.className = "mf-osmo-overlay";
    var overlayInner = document.createElement("div");
    overlayInner.className = "mf-osmo-overlay-inner";

    var countRow = document.createElement("div");
    countRow.className = "mf-osmo-count-row";
    var stepCol = document.createElement("div");
    stepCol.className = "mf-osmo-count-column mf-osmo-count-column--steps";
    stepCol.setAttribute("data-mf-step-col", "");
    var stepNum = document.createElement("h2");
    stepNum.className = "mf-osmo-count-heading";
    stepNum.id = "mfHubOsmoStepNum";
    stepNum.textContent = "01";
    stepCol.appendChild(stepNum);

    var divider = document.createElement("div");
    divider.className = "mf-osmo-count-divider";

    var totalCol = document.createElement("div");
    totalCol.className = "mf-osmo-count-column";
    var totalNum = document.createElement("h2");
    totalNum.className = "mf-osmo-count-heading";
    totalNum.setAttribute("data-mf-slide-total", "");
    totalNum.id = "mfHubOsmoTotalNum";
    totalNum.textContent = "01";

    totalCol.appendChild(totalNum);
    countRow.appendChild(stepCol);
    countRow.appendChild(divider);
    countRow.appendChild(totalCol);

    var navRow = document.createElement("div");
    navRow.className = "mf-osmo-nav-row";
    var prevBtn = document.createElement("button");
    prevBtn.type = "button";
    prevBtn.setAttribute("data-mf-slider-prev", "");
    prevBtn.className = "mf-osmo-btn";
    prevBtn.setAttribute("aria-label", "Previous slide");
    prevBtn.innerHTML =
      '<svg xmlns="http://www.w3.org/2000/svg" width="100%" viewBox="0 0 17 12" fill="none" aria-hidden="true"><path d="M6.28871 12L7.53907 10.9111L3.48697 6.77778H16.5V5.22222H3.48697L7.53907 1.08889L6.28871 0L0.5 6L6.28871 12Z" fill="currentColor"/></svg>';
    var nextBtn = document.createElement("button");
    nextBtn.type = "button";
    nextBtn.setAttribute("data-mf-slider-next", "");
    nextBtn.className = "mf-osmo-btn mf-osmo-btn--next";
    nextBtn.setAttribute("aria-label", "Next slide");
    nextBtn.innerHTML =
      '<svg xmlns="http://www.w3.org/2000/svg" width="100%" viewBox="0 0 17 12" fill="none" aria-hidden="true"><path d="M6.28871 12L7.53907 10.9111L3.48697 6.77778H16.5V5.22222H3.48697L7.53907 1.08889L6.28871 0L0.5 6L6.28871 12Z" fill="currentColor"/></svg>';
    navRow.appendChild(prevBtn);
    navRow.appendChild(nextBtn);

    overlayInner.appendChild(countRow);
    overlayInner.appendChild(navRow);
    overlay.appendChild(overlayInner);

    var main = document.createElement("div");
    main.className = "mf-osmo-main";
    var sliderWrap = document.createElement("div");
    sliderWrap.className = "mf-osmo-slider-wrap mf-hub-osmo-slider-scroll";
    var listEl = document.createElement("div");
    listEl.setAttribute("data-mf-slider-list", "");
    listEl.className = "mf-osmo-slider-list";

    var ph = placeholderImageUrl();
    var prods = products && products.length ? products : [];
    if (prods.length) {
      prods.forEach(function (p) {
        appendHubOsmoProductSlide(listEl, p, ph);
      });
    } else {
      appendHubOsmoEmptySlide(
        listEl,
        emptyOrErrMsg || "No products in this category yet."
      );
    }

    sliderWrap.appendChild(listEl);
    main.appendChild(sliderWrap);

    cloneable.appendChild(overlay);
    cloneable.appendChild(main);

    root.appendChild(toolbar);
    root.appendChild(cloneable);
    mount.appendChild(root);

    setHtmlFsProductSlider(true);

    var slides = listEl.querySelectorAll("[data-mf-slider-slide]");
    var n = slides.length;
    totalNum.textContent = pad2(n);
    stepNum.textContent = pad2(1);

    var i;
    function setActive(idx) {
      for (i = 0; i < slides.length; i++) {
        slides[i].classList.toggle("active", i === idx);
      }
      stepNum.textContent = pad2(idx + 1);
    }

    function scrollToIndex(idx) {
      if (!slides[idx]) return;
      var slide = slides[idx];
      var wrapRect = sliderWrap.getBoundingClientRect();
      var slideRect = slide.getBoundingClientRect();
      var delta =
        slideRect.left - wrapRect.left - (wrapRect.width - slideRect.width) / 2;
      var maxScroll = Math.max(0, sliderWrap.scrollWidth - sliderWrap.clientWidth);
      var next = sliderWrap.scrollLeft + delta;
      if (next < 0) next = 0;
      if (next > maxScroll) next = maxScroll;
      sliderWrap.scrollTo({ left: next, behavior: "smooth" });
    }

    function nearestIndex() {
      if (!slides.length) return 0;
      var wrapRect = sliderWrap.getBoundingClientRect();
      var mid = wrapRect.left + wrapRect.width / 2;
      var best = 0;
      var bestDist = 1e9;
      for (i = 0; i < slides.length; i++) {
        var r = slides[i].getBoundingClientRect();
        var c = r.left + r.width / 2;
        var d = Math.abs(c - mid);
        if (d < bestDist) {
          bestDist = d;
          best = i;
        }
      }
      return best;
    }

    var scrollScheduled = false;
    function onScroll() {
      if (scrollScheduled) return;
      scrollScheduled = true;
      window.requestAnimationFrame(function () {
        scrollScheduled = false;
        setActive(nearestIndex());
      });
    }

    function go(delta) {
      if (n <= 1) return;
      var cur = nearestIndex();
      var idx = cur + delta;
      if (idx < 0) idx = 0;
      if (idx >= n) idx = n - 1;
      scrollToIndex(idx);
      setActive(idx);
    }

    sliderWrap.addEventListener("scroll", onScroll, { passive: true });
    prevBtn.addEventListener("click", function () {
      go(-1);
    });
    nextBtn.addEventListener("click", function () {
      go(1);
    });

    var multi = n > 1;
    prevBtn.disabled = !multi;
    nextBtn.disabled = !multi;
    if (multi) {
      prevBtn.removeAttribute("aria-disabled");
      nextBtn.removeAttribute("aria-disabled");
    } else {
      prevBtn.setAttribute("aria-disabled", "true");
      nextBtn.setAttribute("aria-disabled", "true");
    }

    function onClose() {
      showHubCategoriesOnly(shell);
      var gridTop = shell.querySelector("#mfHubCategoryGrid");
      if (gridTop) gridTop.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }

    backBtn.addEventListener("click", onClose);

    function onKey(ev) {
      if (ev.key === "Escape") {
        ev.preventDefault();
        onClose();
      }
    }
    document.addEventListener("keydown", onKey);

    var onResize = function () {
      setActive(nearestIndex());
    };
    window.addEventListener("resize", onResize);

    hubOsmoDispose = function () {
      sliderWrap.removeEventListener("scroll", onScroll);
      document.removeEventListener("keydown", onKey);
      window.removeEventListener("resize", onResize);
      backBtn.removeEventListener("click", onClose);
    };

    setActive(0);
    window.requestAnimationFrame(function () {
      scrollToIndex(0);
      setActive(0);
    });
  }

  function showHubCategoriesOnly(shell) {
    if (!shell) return;
    closeHubOsmoFullscreen();
    shell.classList.remove("mf-mm-hub--products-view");
    var countBar = shell.querySelector("#mfHubProductsCountBar");
    if (countBar) {
      countBar.hidden = true;
      countBar.innerHTML = "";
    }
    var panel = shell.querySelector("#mfHubProductsPanel");
    if (panel) panel.hidden = true;
    wireCurrentProducts = [];
    var wrap = shell.querySelector("#mfHubProductsWrap");
    if (wrap) wrap.innerHTML = "";
    var search = shell.querySelector("#mfHubProductsSearch");
    if (search) search.value = "";
    var st = shell.querySelector("#mfHubDetailStatus");
    var sub = shell.querySelector("#mfHubProductsSubtitle");
    if (sub) sub.textContent = "";
    setStatus(st, "", false);
  }

  function loadHubCategoryProducts(shell, marketId, categoryApiName, categorySubtitle) {
    var statusEl = shell.querySelector("#mfHubDetailStatus");
    var sub = shell.querySelector("#mfHubProductsSubtitle");
    var panel = shell.querySelector("#mfHubProductsPanel");
    closeHubOsmoFullscreen();
    if (panel) panel.hidden = true;
    setStatus(statusEl, "Loading products…", false);
    var catLabel = String(categorySubtitle || categoryApiName || "").trim();
    var mkt = String(wireMarketName || "Market").trim();
    var headingLine =
      (catLabel ? catLabel.toUpperCase() : "") +
      (catLabel ? " • " : "") +
      mkt.toUpperCase();
    if (sub) sub.textContent = headingLine;
    var searchInput = shell.querySelector("#mfHubProductsSearch");
    if (searchInput) searchInput.value = "";

    var url =
      api("/api/get_market_products_by_category.php") +
      "?market_id=" +
      encodeURIComponent(String(marketId)) +
      "&category=" +
      encodeURIComponent(categoryApiName);

    fetch(url, { credentials: "include" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        setStatus(statusEl, "", false);
        var products =
          data && data.success && Array.isArray(data.products) ? data.products : [];
        wireCurrentProducts = products.slice();
        if (isMobile()) {
          if (wireCurrentProducts.length) {
            openHubProductsInlineList(
              shell,
              wireCurrentProducts,
              null,
              catLabel,
              mkt
            );
          } else {
            openHubProductsInlineList(
              shell,
              [],
              "No products in this category yet.",
              catLabel,
              mkt
            );
          }
        } else if (wireCurrentProducts.length) {
          openHubOsmoFullscreen(shell, wireCurrentProducts, headingLine);
        } else {
          openHubOsmoFullscreen(
            shell,
            [],
            headingLine,
            "No products in this category yet."
          );
        }
      })
      .catch(function () {
        setStatus(statusEl, "Could not load products.", true);
        wireCurrentProducts = [];
        if (isMobile()) {
          openHubProductsInlineList(
            shell,
            [],
            "Could not load products.",
            catLabel,
            mkt
          );
        } else {
          openHubOsmoFullscreen(
            shell,
            [],
            headingLine,
            "Could not load products."
          );
        }
      });
  }

  function renderHubProductCards(shell, products, emptyMsg) {
    var wrap = shell.querySelector("#mfHubProductsWrap");
    if (!wrap) return;
    wrap.innerHTML = "";
    if (!products || !products.length) {
      var empty = document.createElement("p");
      empty.className = "mf-mm-empty";
      empty.style.textAlign = "center";
      empty.style.padding = "2rem";
      empty.textContent = emptyMsg || "No products found.";
      wrap.appendChild(empty);
      return;
    }
    var ph = placeholderImageUrl();
    products.forEach(function (p) {
      var id = String(p.id != null ? p.id : "");
      var dispName = String(p.filipino_name || p.name || "Product");
      var priceStr = String(p.current_price || p.price || "₱0.00");
      var numeric = stripPriceNum(priceStr);

      var card = document.createElement("article");
      card.className = "mf-mm-product-card";
      card.setAttribute("data-product-id", id);
      card.setAttribute("data-product-name", dispName);
      card.setAttribute("data-product-price", String(numeric));
      card.setAttribute(
        "data-product-unit",
        String(p.unit || "")
          .replace(/^per\s+/i, "")
          .trim() || "unit"
      );

      var bell = document.createElement("button");
      bell.type = "button";
      bell.className = "mf-mm-prod-alert-btn js-mf-price-alert";
      bell.title = "Price alert";
      bell.setAttribute("aria-label", "Price alert");
      bell.innerHTML =
        '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />' +
        "</svg>";

      var img = document.createElement("img");
      img.className = "mf-mm-prod-image";
      img.alt = dispName;
      img.loading = "lazy";
      img.decoding = "async";
      img.src = resolveProductImageUrl(p.image_url);
      img.onerror = function () {
        img.onerror = null;
        img.src = ph;
      };

      var media = document.createElement("div");
      media.className = "mf-mm-prod-media";
      media.appendChild(bell);
      media.appendChild(img);
      card.appendChild(media);

      var body = document.createElement("div");
      body.className = "mf-mm-prod-body";

      var nameEl = document.createElement("div");
      nameEl.className = "mf-mm-prod-name";
      nameEl.textContent = dispName;
      body.appendChild(nameEl);

      var desc = String(p.description || "").trim();
      if (desc) {
        var descEl = document.createElement("div");
        descEl.className = "mf-mm-prod-desc";
        descEl.textContent = desc;
        body.appendChild(descEl);
      }

      var priceEl = document.createElement("div");
      priceEl.className = "mf-mm-prod-price";
      priceEl.textContent = priceStr;
      body.appendChild(priceEl);

      var unitEl = document.createElement("div");
      unitEl.className = "mf-osmo-prod-unit mf-mm-prod-unit";
      unitEl.textContent = "per " + (String(p.unit || "").trim() || "unit");
      body.appendChild(unitEl);

      card.appendChild(body);

      var opts = p.unit_options;
      if (Array.isArray(opts) && opts.length) {
        var optWrap = document.createElement("div");
        optWrap.className = "product-unit-options";
        var optTitle = document.createElement("div");
        optTitle.className = "product-unit-options-title";
        optTitle.textContent = "Other Options:";
        optWrap.appendChild(optTitle);
        var chipRow = document.createElement("div");
        chipRow.className = "product-unit-options-chips";
        opts.forEach(function (opt) {
          var label = String(opt.label || opt.unit_label || "");
          var pr = opt.price != null ? Number(opt.price) : NaN;
          var line =
            label && !isNaN(pr) ? label + " - ₱" + pr.toFixed(2) : label;
          var sp = document.createElement("span");
          sp.className =
            "product-unit-option mf-mm-prod-chip" +
            (opt.is_default ? " is-default" : "");
          sp.textContent = line;
          chipRow.appendChild(sp);
        });
        optWrap.appendChild(chipRow);
        card.appendChild(optWrap);
      }

      var actions = document.createElement("div");
      actions.className = "mf-mm-prod-actions";
      var res = document.createElement("button");
      res.type = "button";
      res.className = "mf-mm-prod-reserve js-mf-reserve";
      res.textContent = "RESERVE";
      actions.appendChild(res);
      var hBtn = document.createElement("button");
      hBtn.type = "button";
      hBtn.className = "mf-mm-prod-history js-mf-history";
      hBtn.textContent = "HISTORY";
      actions.appendChild(hBtn);
      card.appendChild(actions);

      wrap.appendChild(card);
    });
  }

  function applyHubProductSearch(shell) {
    var input = shell.querySelector("#mfHubProductsSearch");
    if (!input) return;
    var q = String(input.value || "").trim().toLowerCase();
    if (!q) {
      renderHubProductCards(
        shell,
        wireCurrentProducts,
        "No products available in this category."
      );
      setHubProductsCountBar(shell, wireCurrentProducts.length);
      return;
    }
    var filtered = wireCurrentProducts.filter(function (p) {
      return getProductSearchText(p).indexOf(q) !== -1;
    });
    renderHubProductCards(shell, filtered, "No matching products found.");
    setHubProductsCountBar(shell, filtered.length);
  }

  function expandHubCategoriesSection(shell) {
    var sec = shell.querySelector("#mfHubCategoriesSection");
    if (!sec) return;
    sec.classList.remove("mf-mm-hub-categories--collapsed");
    sec.classList.add("is-expanded");
    sec.setAttribute("aria-hidden", "false");
    /* Mobile hub CSS keys off this + .mf-mm-hub--mobile; on wide viewports shell is not --mobile, so safe. */
    shell.classList.add("mf-mm-hub--categories-view");
    if (typeof window.matchMedia === "function" && window.matchMedia("(max-width: 768px)").matches) {
      var bm = shell.querySelector("#mfHubBackMarkets");
      if (bm) bm.hidden = false;
    }
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        sec.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    });
  }

  var HUB_MARKET_SWITCH_MS = 280;

  function hubMarketSwitchDelayMs() {
    if (
      typeof window.matchMedia === "function" &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches
    ) {
      return 0;
    }
    return HUB_MARKET_SWITCH_MS;
  }

  function loadHubMarketCategories(shell, marketId) {
    var grid = shell.querySelector("#mfHubCategoryGrid");
    var heading = shell.querySelector("#mfHubMarketHeading");
    var statusEl = shell.querySelector("#mfHubDetailStatus");
    var block = shell.querySelector("#mfHubCategoriesBlock");
    if (!grid || !statusEl) return;

    var prevHubId = hubSelectedMarketId;
    var hadGrid = grid.children.length > 0;
    var switchingMarkets =
      hadGrid &&
      prevHubId != null &&
      Number(prevHubId) !== Number(marketId);

    showHubCategoriesOnly(shell);

    function clearBlockAnim() {
      if (!block) return;
      block.classList.remove("mf-mm-hub-categories--switch-out");
    }

    function revealHubCategoriesBlock() {
      if (!block) return;
      window.requestAnimationFrame(function () {
        block.classList.remove("mf-mm-hub-categories--switch-out");
      });
    }

    function proceedLoad() {
      hubSelectedMarketId = marketId;
      grid.innerHTML = "";
      if (!switchingMarkets && block) {
        clearBlockAnim();
      }

      var m = null;
      for (var i = 0; i < allMarkets.length; i++) {
        if (Number(allMarkets[i].id) === marketId) {
          m = allMarkets[i];
          break;
        }
      }
      wireMarketName = m ? String(m.market_name || "Market") : "Market #" + marketId;
      if (heading) heading.textContent = wireMarketName.toUpperCase();

      setStatus(statusEl, "Loading categories…", false);

      fetch(
        api("/api/get_market_categories.php?market_id=" + encodeURIComponent(String(marketId))),
        { credentials: "include" }
      )
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          var apiCats =
            data && data.success && Array.isArray(data.categories) ? data.categories : [];
          var slots = buildHubSlotsFromApi(apiCats);
          setStatus(statusEl, "", false);
          slots.forEach(function (slot) {
            appendCategoryCard(grid, slot);
          });
          expandHubCategoriesSection(shell);
          revealHubCategoriesBlock();
        })
        .catch(function () {
          setStatus(statusEl, "Could not load categories.", true);
          var slots = buildHubSlotsFromApi([]);
          slots.forEach(function (slot) {
            appendCategoryCard(grid, slot);
          });
          expandHubCategoriesSection(shell);
          revealHubCategoriesBlock();
        });
    }

    if (switchingMarkets && block) {
      block.classList.add("mf-mm-hub-categories--switch-out");
      window.setTimeout(function () {
        proceedLoad();
      }, hubMarketSwitchDelayMs());
    } else {
      proceedLoad();
    }
  }

  var HUB_SHELL_BOUND = "data-fs-hub-shell-bound";

  function bindHubShell(shell) {
    if (!shell || shell.getAttribute(HUB_SHELL_BOUND)) return;
    shell.setAttribute(HUB_SHELL_BOUND, "1");
    shell.addEventListener(
      "click",
      function (ev) {
        var row = ev.target.closest && ev.target.closest(".mf-mm-row");
        if (row && shell.contains(row)) {
          ev.preventDefault();
          var mid = parseInt(row.getAttribute("data-market-id") || "0", 10);
          if (!mid) return;
          var rows = shell.querySelectorAll(".mf-mm-row");
          for (var i = 0; i < rows.length; i++) {
            rows[i].classList.remove("mf-mm-row--selected");
          }
          row.classList.add("mf-mm-row--selected");
          loadHubMarketCategories(shell, mid);
          return;
        }
        var catBtn = ev.target.closest && ev.target.closest("#mfHubCategoryGrid .mf-mm-category-card");
        if (catBtn && shell.contains(catBtn)) {
          var cat = catBtn.getAttribute("data-category") || "";
          var sub = catBtn.getAttribute("data-subtitle") || cat;
          if (!cat || !hubSelectedMarketId) return;
          loadHubCategoryProducts(shell, hubSelectedMarketId, cat, sub);
          return;
        }
        if (ev.target.closest && ev.target.closest("#mfHubBackCategories")) {
          var bc = ev.target.closest("#mfHubBackCategories");
          if (bc && shell.contains(bc)) {
            showHubCategoriesOnly(shell);
            var gridTop = shell.querySelector("#mfHubCategoryGrid");
            if (gridTop)
              gridTop.scrollIntoView({ behavior: "smooth", block: "nearest" });
          }
          return;
        }
        if (ev.target.closest && ev.target.closest("#mfHubBackMarkets")) {
          var bkm = ev.target.closest("#mfHubBackMarkets");
          if (bkm && shell.contains(bkm)) {
            resetHubCategoriesUi(shell);
            var head = shell.querySelector(".mf-mm-head");
            if (head) head.scrollIntoView({ behavior: "smooth", block: "start" });
          }
          return;
        }
      },
      false
    );
    shell.addEventListener(
      "input",
      function (ev) {
        if (ev.target && ev.target.id === "mfHubMarketsSearch") {
          applyHubMarketsSearch(shell);
          return;
        }
        if (ev.target && ev.target.id === "mfHubProductsSearch") {
          applyHubProductSearch(shell);
        }
      },
      { passive: true }
    );
    var clr = shell.querySelector("#mfHubProductsClear");
    if (clr) {
      clr.addEventListener("click", function () {
        var inp = shell.querySelector("#mfHubProductsSearch");
        if (inp) inp.value = "";
        applyHubProductSearch(shell);
        if (inp) inp.focus();
      });
    }
    var mClr = shell.querySelector("#mfHubMarketsSearchClear");
    if (mClr) {
      mClr.addEventListener("click", function () {
        var minp = shell.querySelector("#mfHubMarketsSearch");
        if (minp) minp.value = "";
        applyHubMarketsSearch(shell);
        if (minp) minp.focus();
      });
    }
  }

  function hoursLine(m) {
    var h = String(m.operating_hours || "").trim();
    if (!h) h = "Monday–Sunday: hours at market";
    var open = m.is_open === true || m.is_open === 1;
    if (m.is_open === undefined || m.is_open === null) {
      return h;
    }
    return h + " — " + (open ? "OPEN" : "CLOSED");
  }

  function getFilteredMarketsForSearch(query) {
    var q = String(query || "").trim().toLowerCase();
    if (!q) return allMarkets.slice();
    return allMarkets.filter(function (m) {
      var name = String(m.market_name || "").toLowerCase();
      var addr = String(m.address || "").toLowerCase();
      var hours = String(m.operating_hours || "").toLowerCase();
      return (
        name.indexOf(q) !== -1 ||
        addr.indexOf(q) !== -1 ||
        hours.indexOf(q) !== -1
      );
    });
  }

  function applyHubMarketsSearch(shell) {
    var inp = shell.querySelector("#mfHubMarketsSearch");
    if (!inp) return;
    renderList(getFilteredMarketsForSearch(inp.value));
  }

  function renderList(markets) {
    var ul = document.getElementById("mfMarketsMobileList");
    if (!ul) return;
    var source = Array.isArray(markets) ? markets : allMarkets;
    ul.innerHTML = "";
    if (!source.length) {
      var emptyLi = document.createElement("li");
      emptyLi.className = "mf-mm-list__item mf-mm-list__item--empty";
      emptyLi.innerHTML =
        '<p class="mf-mm-empty">' +
        (allMarkets.length
          ? "No markets match your search."
          : "No markets available.") +
        "</p>";
      ul.appendChild(emptyLi);
      return;
    }
    source.forEach(function (m) {
      var li = document.createElement("li");
      li.className = "mf-mm-list__item mf-mm-list__item--stretch";
      var a = document.createElement("button");
      a.type = "button";
      a.className = "mf-mm-row";
      a.setAttribute("data-market-id", String(m.id));
      a.setAttribute(
        "aria-label",
        "View categories for " + (m.market_name || "market")
      );
      var count = Number(m.product_count || 0);
      a.innerHTML =
        '<div class="mf-mm-row__lead">' +
        '<span class="mf-mm-row__id"></span>' +
        '<span class="mf-mm-row__name"></span>' +
        "</div>" +
        '<div class="mf-mm-row__meta">' +
        '<span class="mf-mm-row__addr"></span>' +
        '<span class="mf-mm-row__hours"></span>' +
        "</div>" +
        '<div class="mf-mm-row__stat">' +
        '<div class="mf-mm-row__stat-inner">' +
        '<span class="mf-mm-row__stat-val"></span>' +
        '<span class="mf-mm-row__stat-lbl"></span>' +
        "</div>" +
        '<span class="mf-mm-row__arrow" aria-hidden="true">' +
        '<svg class="mf-mm-row__arrow-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" focusable="false">' +
        '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>' +
        "</svg></span>" +
        "</div>";
      a.querySelector(".mf-mm-row__id").textContent = formatMarketId(m.id);
      a.querySelector(".mf-mm-row__name").textContent = m.market_name || "Market";
      a.querySelector(".mf-mm-row__addr").textContent = m.address || "La Union";
      a.querySelector(".mf-mm-row__hours").textContent = hoursLine(m);
      a.querySelector(".mf-mm-row__stat-val").textContent = String(count);
      a.querySelector(".mf-mm-row__stat-lbl").textContent =
        count === 1 ? "PRODUCT" : "PRODUCTS";
      li.appendChild(a);
      ul.appendChild(li);
    });
  }

  function resetHubCategoriesUi(shell) {
    if (!shell) return;
    shell.classList.remove("mf-mm-hub--categories-view");
    var bm = shell.querySelector("#mfHubBackMarkets");
    if (bm) bm.hidden = true;
    var sec = shell.querySelector("#mfHubCategoriesSection");
    if (sec) {
      sec.classList.add("mf-mm-hub-categories--collapsed");
      sec.classList.remove("is-expanded");
      sec.setAttribute("aria-hidden", "true");
    }
    var h = shell.querySelector("#mfHubMarketHeading");
    if (h) h.textContent = "";
    var g = shell.querySelector("#mfHubCategoryGrid");
    if (g) g.innerHTML = "";
    var blk = shell.querySelector("#mfHubCategoriesBlock");
    if (blk) blk.classList.remove("mf-mm-hub-categories--switch-out");
    var ms = shell.querySelector("#mfHubMarketsSearch");
    if (ms) ms.value = "";
    showHubCategoriesOnly(shell);
    hubSelectedMarketId = null;
    shell.querySelectorAll(".mf-mm-row--selected").forEach(function (el) {
      el.classList.remove("mf-mm-row--selected");
    });
    renderList();
  }

  function setStatus(el, msg, isErr) {
    if (!el) return;
    if (!msg) {
      el.hidden = true;
      el.textContent = "";
      el.classList.remove("mf-mm-status--err");
      return;
    }
    el.hidden = false;
    el.textContent = msg;
    el.classList.toggle("mf-mm-status--err", !!isErr);
  }

  function fetchAndRender(statusEl) {
    setStatus(statusEl, "Loading markets…", false);
    fetch(api("/api/get_markets.php"), { credentials: "include" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.success || !Array.isArray(data.markets)) {
          setStatus(statusEl, "Could not load markets. Try again.", true);
          return;
        }
        var raw = data.markets.filter(function (m) {
          return m && Number(m.id) > 0;
        });
        allMarkets = raw.sort(function (a, b) {
          return String(a.market_name || "").localeCompare(String(b.market_name || ""));
        });
        setStatus(statusEl, "", false);
        renderList();
        resetHubCategoriesUi(document.getElementById("mfMarketsMobileShell"));
      })
      .catch(function () {
        setStatus(statusEl, "Network error. Check your connection.", true);
      });
  }

  function activate(mfPage) {
    loadCss();
    var shell = ensureShell(mfPage);
    if (!shell) return;
    mfPage.classList.add("fs-markets-mobile--active");
    /* Tear down 3D markets carousel if it already ran (hub uses list UI only). */
    try {
      if (typeof window.mfCategoryCarouselDestroy === "function") {
        window.mfCategoryCarouselDestroy();
      }
    } catch (e) {}
    var host = document.getElementById("mfMarketsCarouselHost");
    if (host) host.innerHTML = "";
    var statusEl = document.getElementById("mfMarketsMobileStatus");
    bindHubShell(shell);
    if (!mfPage.getAttribute(ATTR)) {
      mfPage.setAttribute(ATTR, "1");
      fetchAndRender(statusEl);
    } else if (allMarkets.length) {
      var ulCheck = document.getElementById("mfMarketsMobileList");
      if (ulCheck && !ulCheck.querySelector("li")) {
        renderList();
      }
    }
    syncMobileHubLayout();
  }

  function deactivate(mfPage) {
    if (!mfPage) return;
    mfPage.classList.remove("fs-markets-mobile--active");
  }

  var currentDetailMarketId = null;
  var wireMarketName = "";
  var wireCurrentProducts = [];
  var DETAIL_BOUND = "data-fs-mm-detail-bound";
  var SEARCH_BOUND = "data-fs-mm-search-bound";
  /** When set, hub + hero hide and #mfMobileWireDetail shows (all viewports). */
  var MF_PAGE_DETAIL_CLASS = "fs-mf-market-detail-active";

  function resolveProductImageUrl(u) {
    var s = String(u || "").trim();
    if (!s) return placeholderImageUrl();
    if (/^https?:\/\//i.test(s)) return s;
    if (s.charAt(0) === "/") {
      return (
        (window.location && window.location.origin ? window.location.origin : "") + s
      );
    }
    return api("/" + s.replace(/^\//, ""));
  }

  function stripPriceNum(str) {
    var raw = String(str || "0").replace(/[₱P$,\s]/g, "").trim();
    var n = parseFloat(raw);
    return isNaN(n) ? 0 : n;
  }

  function showMobileCategoriesPane(root) {
    var cat = root.querySelector("#mfMobileCategoriesPane");
    var prod = root.querySelector("#mfMobileProductsPane");
    if (cat) cat.hidden = false;
    if (prod) prod.hidden = true;
  }

  function showMobileProductsPane(root) {
    var cat = root.querySelector("#mfMobileCategoriesPane");
    var prod = root.querySelector("#mfMobileProductsPane");
    if (cat) cat.hidden = true;
    if (prod) prod.hidden = false;
  }

  function removeMobileWireDetail(mfPage) {
    if (!mfPage) return;
    mfPage.classList.remove(MF_PAGE_DETAIL_CLASS);
    var el = mfPage.querySelector("#mfMobileWireDetail");
    if (el) el.remove();
    currentDetailMarketId = null;
    wireMarketName = "";
    wireCurrentProducts = [];
  }

  function getProductSearchText(p) {
    return [
      p.filipino_name,
      p.name,
      p.description,
      p.unit,
      p.current_price,
      p.price,
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();
  }

  function renderMobileProductCards(root, products, emptyMsg) {
    var wrap = root.querySelector("#mfMobileProductsWrap");
    if (!wrap) return;
    wrap.innerHTML = "";
    if (!products || !products.length) {
      var empty = document.createElement("p");
      empty.className = "mf-mm-empty";
      empty.style.textAlign = "center";
      empty.style.padding = "2rem";
      empty.textContent = emptyMsg || "No products found.";
      wrap.appendChild(empty);
      return;
    }
    var ph = placeholderImageUrl();
    products.forEach(function (p) {
      var id = String(p.id != null ? p.id : "");
      var dispName = String(p.filipino_name || p.name || "Product");
      var priceStr = String(p.current_price || p.price || "₱0.00");
      var numeric = stripPriceNum(priceStr);

      var card = document.createElement("article");
      card.className = "mf-mm-product-card";
      card.setAttribute("data-product-id", id);
      card.setAttribute("data-product-name", dispName);
      card.setAttribute("data-product-price", String(numeric));
      card.setAttribute(
        "data-product-unit",
        String(p.unit || "")
          .replace(/^per\s+/i, "")
          .trim() || "unit"
      );

      var bell = document.createElement("button");
      bell.type = "button";
      bell.className = "mf-mm-prod-alert-btn js-mf-price-alert";
      bell.title = "Price alert";
      bell.setAttribute("aria-label", "Price alert");
      bell.innerHTML =
        '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />' +
        "</svg>";

      var img = document.createElement("img");
      img.className = "mf-mm-prod-image";
      img.alt = dispName;
      img.loading = "lazy";
      img.decoding = "async";
      img.src = resolveProductImageUrl(p.image_url);
      img.onerror = function () {
        img.onerror = null;
        img.src = ph;
      };

      var media = document.createElement("div");
      media.className = "mf-mm-prod-media";
      media.appendChild(bell);
      media.appendChild(img);
      card.appendChild(media);

      var body = document.createElement("div");
      body.className = "mf-mm-prod-body";

      var nameEl = document.createElement("div");
      nameEl.className = "mf-mm-prod-name";
      nameEl.textContent = dispName;
      body.appendChild(nameEl);

      var desc = String(p.description || "").trim();
      if (desc) {
        var descEl = document.createElement("div");
        descEl.className = "mf-mm-prod-desc";
        descEl.textContent = desc;
        body.appendChild(descEl);
      }

      var priceEl = document.createElement("div");
      priceEl.className = "mf-mm-prod-price";
      priceEl.textContent = priceStr;
      body.appendChild(priceEl);

      var unitEl = document.createElement("div");
      unitEl.className = "mf-osmo-prod-unit mf-mm-prod-unit";
      unitEl.textContent = "per " + (String(p.unit || "").trim() || "unit");
      body.appendChild(unitEl);

      card.appendChild(body);

      var opts = p.unit_options;
      if (Array.isArray(opts) && opts.length) {
        var optWrap = document.createElement("div");
        optWrap.className = "product-unit-options";
        var optTitle = document.createElement("div");
        optTitle.className = "product-unit-options-title";
        optTitle.textContent = "Other Options:";
        optWrap.appendChild(optTitle);
        var chipRow = document.createElement("div");
        chipRow.className = "product-unit-options-chips";
        opts.forEach(function (opt) {
          var label = String(opt.label || opt.unit_label || "");
          var pr = opt.price != null ? Number(opt.price) : NaN;
          var line =
            label && !isNaN(pr) ? label + " - ₱" + pr.toFixed(2) : label;
          var sp = document.createElement("span");
          sp.className =
            "product-unit-option mf-mm-prod-chip" +
            (opt.is_default ? " is-default" : "");
          sp.textContent = line;
          chipRow.appendChild(sp);
        });
        optWrap.appendChild(chipRow);
        card.appendChild(optWrap);
      }

      var actions = document.createElement("div");
      actions.className = "mf-mm-prod-actions";
      var res = document.createElement("button");
      res.type = "button";
      res.className = "mf-mm-prod-reserve js-mf-reserve";
      res.textContent = "RESERVE";
      actions.appendChild(res);
      var hBtn = document.createElement("button");
      hBtn.type = "button";
      hBtn.className = "mf-mm-prod-history js-mf-history";
      hBtn.textContent = "HISTORY";
      actions.appendChild(hBtn);
      card.appendChild(actions);

      wrap.appendChild(card);
    });
  }

  function applyMobileProductSearch(root) {
    var input = root.querySelector("#mfMobileProductsSearch");
    if (!input) return;
    var q = String(input.value || "").trim().toLowerCase();
    if (!q) {
      renderMobileProductCards(
        root,
        wireCurrentProducts,
        "No products available in this category."
      );
      return;
    }
    var filtered = wireCurrentProducts.filter(function (p) {
      return getProductSearchText(p).indexOf(q) !== -1;
    });
    renderMobileProductCards(root, filtered, "No matching products found.");
  }

  function loadMobileCategoryProducts(root, marketId, categoryApiName, categorySubtitle) {
    var statusEl = root.querySelector("#mfMobileDetailStatus");
    var sub = root.querySelector("#mfMobileProductsSubtitle");
    setStatus(statusEl, "Loading products…", false);
    showMobileProductsPane(root);
    if (sub) {
      sub.textContent =
        (categorySubtitle || categoryApiName) + " - " + (wireMarketName || "Market");
    }
    var searchInput = root.querySelector("#mfMobileProductsSearch");
    if (searchInput) searchInput.value = "";

    var url =
      api("/api/get_market_products_by_category.php") +
      "?market_id=" +
      encodeURIComponent(String(marketId)) +
      "&category=" +
      encodeURIComponent(categoryApiName);

    fetch(url, { credentials: "include" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        setStatus(statusEl, "", false);
        var products =
          data && data.success && Array.isArray(data.products) ? data.products : [];
        wireCurrentProducts = products.slice();
        renderMobileProductCards(
          root,
          wireCurrentProducts,
          "No products in this category yet."
        );
      })
      .catch(function () {
        setStatus(statusEl, "Could not load products.", true);
        wireCurrentProducts = [];
        renderMobileProductCards(root, [], "Could not load products.");
      });
  }

  function loadMobileMarketDetail(root, marketId) {
    var grid = root.querySelector("#mfMobileCategoryGrid");
    var tag = root.querySelector("#mfMobileMarketHeading");
    var statusEl = root.querySelector("#mfMobileDetailStatus");
    var search = root.querySelector("#mfMobileProductsSearch");
    if (search) search.value = "";
    wireCurrentProducts = [];
    if (!grid || !statusEl) return;
    showMobileCategoriesPane(root);
    var pw = root.querySelector("#mfMobileProductsWrap");
    if (pw) pw.innerHTML = "";
    var sub = root.querySelector("#mfMobileProductsSubtitle");
    if (sub) sub.textContent = "";

    setStatus(statusEl, "Loading…", false);
    grid.innerHTML = "";

    fetch(api("/api/get_markets.php"), { credentials: "include" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        var markets = data && data.success && Array.isArray(data.markets) ? data.markets : [];
        var m = null;
        for (var i = 0; i < markets.length; i++) {
          if (Number(markets[i].id) === marketId) {
            m = markets[i];
            break;
          }
        }
        wireMarketName = m ? String(m.market_name || "Market") : "Market #" + marketId;
        if (tag) {
          tag.textContent = wireMarketName.toUpperCase();
        }
        return fetch(
          api(
            "/api/get_market_categories.php?market_id=" +
              encodeURIComponent(String(marketId))
          ),
          { credentials: "include" }
        );
      })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.success || !Array.isArray(data.categories)) {
          setStatus(statusEl, "Could not load categories.", true);
          return;
        }
        if (!data.categories.length) {
          setStatus(statusEl, "No categories at this market yet.", false);
          return;
        }
        setStatus(statusEl, "", false);
        data.categories.forEach(function (c) {
          var apiName = String(c.category_name || c.name || "").trim();
          if (!apiName) return;
          var label = String(c.category_name || c.name || apiName).trim();
          var desc = String(c.description || "").trim();
          if (!desc) {
            desc = "Browse products in this category.";
          }

          var btn = document.createElement("button");
          btn.type = "button";
          btn.className = "mf-mm-category-card";
          btn.setAttribute("data-category", apiName);
          btn.setAttribute("data-subtitle", label);

          var nm = document.createElement("span");
          nm.className = "mf-mm-category-name";
          nm.textContent = label.toUpperCase();
          btn.appendChild(nm);

          var d = document.createElement("span");
          d.className = "mf-mm-category-desc";
          d.textContent = desc;
          btn.appendChild(d);

          var meta = document.createElement("div");
          meta.className = "mf-mm-category-card__meta";
          var pc = Number(c.product_count || 0);
          var ct = document.createElement("span");
          ct.className = "mf-mm-category-count";
          ct.textContent = pc + (pc === 1 ? " PRODUCT" : " PRODUCTS");
          meta.appendChild(ct);
          var icons = document.createElement("span");
          icons.className = "mf-mm-category-card__icons";
          icons.setAttribute("aria-hidden", "true");
          for (var iq = 0; iq < 3; iq++) {
            var sq = document.createElement("span");
            sq.className = "mf-mm-category-card__icon-sq";
            icons.appendChild(sq);
          }
          meta.appendChild(icons);
          btn.appendChild(meta);

          grid.appendChild(btn);
        });
      })
      .catch(function () {
        setStatus(statusEl, "Network error.", true);
      });
  }

  function bindMobileWireDetailRoot(root) {
    if (!root.getAttribute(DETAIL_BOUND)) {
      root.setAttribute(DETAIL_BOUND, "1");
      root.addEventListener("click", function (ev) {
        var catBtn = ev.target.closest && ev.target.closest(".mf-mm-category-card");
        if (catBtn && root.contains(catBtn)) {
          var cat = catBtn.getAttribute("data-category") || "";
          var sub = catBtn.getAttribute("data-subtitle") || cat;
          var mid = getSelectedMarketId();
          if (!cat || !mid) return;
          loadMobileCategoryProducts(root, mid, cat, sub);
          return;
        }
        if (ev.target.closest && ev.target.closest("#mfMobileBackCategories")) {
          var bc = ev.target.closest("#mfMobileBackCategories");
          if (bc && root.contains(bc)) {
            ev.preventDefault();
            showMobileCategoriesPane(root);
            wireCurrentProducts = [];
            var pi = root.querySelector("#mfMobileProductsSearch");
            if (pi) pi.value = "";
          }
        }
      });
    }

    if (!root.getAttribute(SEARCH_BOUND)) {
      root.setAttribute(SEARCH_BOUND, "1");
      root.addEventListener(
        "input",
        function (ev) {
          if (ev.target.id !== "mfMobileProductsSearch") return;
          applyMobileProductSearch(root);
        },
        { passive: true }
      );
      var clr = root.querySelector("#mfMobileProductsClear");
      if (clr) {
        clr.addEventListener("click", function () {
          var inp = root.querySelector("#mfMobileProductsSearch");
          if (inp) inp.value = "";
          applyMobileProductSearch(root);
          if (inp) inp.focus();
        });
      }
    }
  }

  function ensureMobileWireDetail(mfPage) {
    if (!mfPage) return;
    if (isMarketsHubView()) {
      removeMobileWireDetail(mfPage);
      return;
    }
    var marketId = getSelectedMarketId();
    if (!marketId) {
      removeMobileWireDetail(mfPage);
      return;
    }
    loadCss();
    mfPage.classList.add(MF_PAGE_DETAIL_CLASS);
    var root = mfPage.querySelector("#mfMobileWireDetail");
    if (!root) {
      root = document.createElement("div");
      root.id = "mfMobileWireDetail";
      root.className = "mf-mm-detail mf-mm-detail--legacy";
      root.setAttribute("aria-label", "Market categories and products");
      root.innerHTML =
        '<a class="mf-mm-back" id="mfMobileBackLink">← All markets</a>' +
        '<div id="mfMobileCategoriesPane">' +
        '<p class="mf-mm-cat-eyebrow">PRODUCT CATEGORIES</p>' +
        '<h2 class="mf-mm-market-heading" id="mfMobileMarketHeading"></h2>' +
        '<p class="mf-mm-legacy-sub mf-mm-market-sub">Browse products by category.</p>' +
        '<div id="mfMobileCategoryGrid" class="mf-mm-category-grid mf-mm-category-grid--mosaic"></div>' +
        "</div>" +
        '<div id="mfMobileProductsPane" hidden>' +
        '<button type="button" class="mf-mm-back mf-mm-back--secondary" id="mfMobileBackCategories">← Categories</button>' +
        '<h2 class="mf-mm-legacy-title">PRODUCTS</h2>' +
        '<p class="mf-mm-legacy-sub" id="mfMobileProductsSubtitle"></p>' +
        '<div class="mf-mm-products-search">' +
        '<input type="search" id="mfMobileProductsSearch" class="mf-mm-legacy-input" placeholder="Search products..." autocomplete="off" />' +
        '<button type="button" class="mf-mm-legacy-clear" id="mfMobileProductsClear">CLEAR</button>' +
        "</div>" +
        '<div id="mfMobileProductsWrap" class="mf-mm-products-grid-legacy"></div>' +
        "</div>" +
        '<p id="mfMobileDetailStatus" class="mf-mm-status" hidden></p>';
      var back = root.querySelector("#mfMobileBackLink");
      if (back) back.setAttribute("href", marketsHubHref());
      var nav = mfPage.querySelector(".fs-nav");
      if (nav && nav.parentNode) {
        if (nav.nextSibling) nav.parentNode.insertBefore(root, nav.nextSibling);
        else nav.parentNode.appendChild(root);
      } else {
        mfPage.insertBefore(root, mfPage.firstChild);
      }
      bindMobileWireDetailRoot(root);
    } else {
      var bk = root.querySelector("#mfMobileBackLink");
      if (bk) bk.setAttribute("href", marketsHubHref());
      bindMobileWireDetailRoot(root);
    }
    if (currentDetailMarketId !== marketId) {
      currentDetailMarketId = marketId;
      loadMobileMarketDetail(root, marketId);
    }
  }

  function sync() {
    var root = document.getElementById("page_content");
    if (!root) return;
    var mfPage = findMarketsPage(root);
    if (!mfPage || !hasCarouselLayout(mfPage)) {
      deactivate(document.querySelector(".mf-page.fs-markets-mobile--active"));
      var prevDetail = document.querySelector(".mf-page." + MF_PAGE_DETAIL_CLASS);
      if (prevDetail) removeMobileWireDetail(prevDetail);
      root.classList.remove("fs-mf-markets-mobile-root");
      return;
    }
    loadCss();
    root.classList.add("fs-mf-markets-mobile-root");
    activate(mfPage);
    removeMobileWireDetail(mfPage);
    stripMarketQueryIfOnMarketsPage();
    applyZHeroMobileMode();
  }

  var mq = window.matchMedia(MQ);
  if (mq.addEventListener) {
    mq.addEventListener("change", sync);
  } else {
    mq.addListener(sync);
  }

  function hookHistoryForSpa() {
    if (window.__fsMarketsMobileHistoryHook) return;
    window.__fsMarketsMobileHistoryHook = true;
    function emit() {
      window.setTimeout(scheduleSync, 0);
      /* pushState runs before the async transition creates a new #page_content; a few
       * delayed syncs catch the swap even if MutationObserver missed an edge case. */
      window.setTimeout(scheduleSync, 160);
      window.setTimeout(scheduleSync, 420);
    }
    var ps = history.pushState;
    var rs = history.replaceState;
    history.pushState = function () {
      var r = ps.apply(history, arguments);
      emit();
      return r;
    };
    history.replaceState = function () {
      var r = rs.apply(history, arguments);
      emit();
      return r;
    };
    window.addEventListener("popstate", emit);
  }

  /** Broken product images → site placeholder (fixes 404 relative paths). */
  function hookImageFallback() {
    if (window.__fsMfImageFallback) return;
    window.__fsMfImageFallback = true;
    var ph = placeholderImageUrl();
    document.addEventListener(
      "error",
      function (ev) {
        var t = ev.target;
        if (!t || t.tagName !== "IMG") return;
        if (!t.closest || !t.closest(".mf-page")) return;
        if (t.dataset.fsImgFallback === "1") return;
        if (t.src.indexOf("placeholder-product") !== -1) return;
        t.dataset.fsImgFallback = "1";
        t.src = ph;
      },
      true
    );
  }

  function boot() {
    hookHistoryForSpa();
    hookImageFallback();
    applyZHeroMobileMode();
    /* SPA transitions clone a new [data-transition="container"] and a new main#page_content,
     * then remove the old container — observing the old #page_content leaves a dead observer.
     * Watch a stable ancestor so every route swap is seen. */
    var stableRoot =
      document.querySelector('[data-transition="wrapper"]') ||
      document.getElementById("app") ||
      document.body;
    if (!stableRoot) return;
    var obs = new MutationObserver(function () {
      applyZHeroMobileMode();
      scheduleSync();
    });
    obs.observe(stableRoot, { childList: true, subtree: true });
    scheduleSync();
    /* Hostinger/CDN: shell can mount after first paint — retry layout class for mobile hub CSS */
    window.setTimeout(syncMobileHubLayout, 0);
    window.setTimeout(syncMobileHubLayout, 120);
    window.setTimeout(syncMobileHubLayout, 400);
    window.addEventListener("resize", applyZHeroMobileMode, { passive: true });
    window.addEventListener("resize", syncMobileHubLayout, { passive: true });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();

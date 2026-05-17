import { renderHome } from "./pages/home.js";
import { renderRedirectPage } from "./pages/redirect.js";
import { renderMarkets } from "./pages/markets.js";
import { renderNearestMarket } from "./pages/nearest.js";
import { renderProducts } from "./pages/products.js";

function normalizePath(pathname) {
  if (!pathname) return "/";
  // strip base if app is served under /farmscout_online/app/
  const base = "/farmscout_online/app";
  if (pathname.startsWith(base)) {
    const sliced = pathname.slice(base.length);
    return sliced === "" ? "/" : sliced;
  }
  return pathname;
}

function isInternalLink(a) {
  if (!a || !a.href) return false;
  if (a.target === "_blank") return false;
  if (a.hasAttribute("download")) return false;
  const url = new URL(a.href, window.location.href);
  return url.origin === window.location.origin;
}

function navigate(to, { replace = false } = {}) {
  const url = new URL(to, window.location.href);
  const nextPath = normalizePath(url.pathname);
  const nextFull = `/farmscout_online/app${nextPath === "/" ? "/" : nextPath}${url.search}${url.hash}`;
  if (replace) window.history.replaceState({}, "", nextFull);
  else window.history.pushState({}, "", nextFull);
  render();
}

let mountEl = null;
let entranceTimers = [];

/** Homepage scroll: swap nav contrast when editorial (cream) sits under fixed nav */
let homeNavToneCleanup = null;

function teardownHomeNavTone() {
  if (typeof homeNavToneCleanup === "function") {
    homeNavToneCleanup();
    homeNavToneCleanup = null;
  }
}

function bindHomeNavTone() {
  teardownHomeNavTone();
  if (!mountEl) return;
  const wrap = mountEl.querySelector(".fs-sf-wrap.fs-home");
  const editorial = mountEl.querySelector("#farmscout-editorial");
  if (!wrap || !editorial) return;

  const threshold = 72;
  const onScroll = () => {
    const top = editorial.getBoundingClientRect().top;
    wrap.classList.toggle("fs-home-nav-on-light", top <= threshold);
  };
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });
  homeNavToneCleanup = () => {
    window.removeEventListener("scroll", onScroll);
    wrap.classList.remove("fs-home-nav-on-light");
  };
}

let navUnderlineBound = false;
let homeIntroPlayed = false;
let nearestMapBound = false;
let reservationModalScriptLoaded = false;
let googleMapsApiPromise = null;

let navSearchBound = false;
function bindNavSearchAutocomplete() {
  if (!mountEl) return;
  const forms = Array.from(mountEl.querySelectorAll("form.fs-sf-search"));
  forms.forEach((form) => {
    if (form.dataset.navAcBound === "1") return;
    form.dataset.navAcBound = "1";

    const input = form.querySelector('input[type="search"][name="q"]');
    if (!input) return;

    const dropdown = document.createElement("div");
    dropdown.className = "fs-nav-ac";
    dropdown.hidden = true;
    dropdown.setAttribute("role", "listbox");
    form.appendChild(dropdown);

    let timer = null;
    let activeIndex = -1;
    let items = [];
    let lastQuery = "";

    const close = () => {
      dropdown.hidden = true;
      dropdown.innerHTML = "";
      activeIndex = -1;
      items = [];
    };

    const render = () => {
      dropdown.innerHTML = items
        .map((s, idx) => {
          const title = escapeHtml(String(s.product_name || s.text || ""));
          const meta = [s.market_name, s.category].filter(Boolean).join(" · ");
          return `
            <button type="button" class="fs-nav-ac__item${idx === activeIndex ? " is-active" : ""}" role="option" data-idx="${idx}">
              <div class="fs-nav-ac__title">${title}</div>
              ${meta ? `<div class="fs-nav-ac__meta">${escapeHtml(meta)}</div>` : ``}
            </button>
          `;
        })
        .join("");
      dropdown.hidden = items.length === 0;
    };

    const setActive = (idx) => {
      activeIndex = idx;
      const btns = Array.from(dropdown.querySelectorAll(".fs-nav-ac__item"));
      btns.forEach((b, i) => b.classList.toggle("is-active", i === activeIndex));
      const active = btns[activeIndex];
      active?.scrollIntoView?.({ block: "nearest" });
    };

    async function fetchSuggestions(q) {
      const qs = String(q || "").trim();
      if (qs.length < 2) return [];
      try {
        const res = await fetch(
          `/farmscout_online/api/search_autocomplete_v2.php?q=${encodeURIComponent(qs)}&limit=8`,
          { headers: { Accept: "application/json" } }
        );
        const data = await res.json();
        return Array.isArray(data.suggestions) ? data.suggestions : [];
      } catch {
        return [];
      }
    }

    input.addEventListener("input", () => {
      const q = String(input.value || "").trim();
      lastQuery = q;
      if (timer) window.clearTimeout(timer);
      if (q.length < 2) {
        close();
        return;
      }
      timer = window.setTimeout(async () => {
        const results = await fetchSuggestions(q);
        // stale response guard
        if (String(input.value || "").trim() !== lastQuery) return;
        items = results;
        activeIndex = results.length ? 0 : -1;
        render();
      }, 160);
    });

    input.addEventListener("keydown", (e) => {
      if (dropdown.hidden || items.length === 0) return;
      if (e.key === "ArrowDown") {
        e.preventDefault();
        setActive(Math.min(items.length - 1, activeIndex + 1));
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        setActive(Math.max(0, activeIndex - 1));
      } else if (e.key === "Enter") {
        if (activeIndex >= 0 && items[activeIndex]) {
          e.preventDefault();
          const pick = items[activeIndex];
          const q = String(pick.product_name || pick.text || input.value || "").trim();
          close();
          input.value = q;
          navigateWithTransition(`/products?q=${encodeURIComponent(q)}`);
        }
      } else if (e.key === "Escape") {
        close();
      }
    });

    dropdown.addEventListener("click", (e) => {
      const btn = e.target.closest(".fs-nav-ac__item");
      if (!btn) return;
      const idx = Number(btn.getAttribute("data-idx") || -1);
      const pick = items[idx];
      if (!pick) return;
      const q = String(pick.product_name || pick.text || input.value || "").trim();
      close();
      input.value = q;
      navigateWithTransition(`/products?q=${encodeURIComponent(q)}`);
    });

    input.addEventListener("blur", () => {
      window.setTimeout(() => close(), 120);
    });
    input.addEventListener("focus", () => {
      const q = String(input.value || "").trim();
      if (q.length >= 2 && items.length) dropdown.hidden = false;
    });
  });

  if (!navSearchBound) {
    navSearchBound = true;
    document.addEventListener("click", (e) => {
      const inside = e.target.closest?.(".fs-sf-search");
      if (inside) return;
      document.querySelectorAll(".fs-nav-ac").forEach((d) => (d.hidden = true));
    });
  }
}

let authNavFetchedOnce = false;
async function syncAuthNav() {
  const pill = mountEl?.querySelector?.("#fsNavAuthPill");
  if (!pill) return;

  // default state
  pill.textContent = "Login";
  pill.setAttribute("href", "/farmscout_online/app/account");

  try {
    const res = await fetch("/farmscout_online/api/auth_session.php", {
      credentials: "include",
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const raw = await res.text();
    let data = null;
    try {
      data = JSON.parse(raw);
    } catch {
      // Some environments echo warnings/notices before JSON; try a best-effort detect.
      if (/"logged_in"\s*:\s*true/.test(raw)) data = { logged_in: true };
      else if (/"logged_in"\s*:\s*false/.test(raw)) data = { logged_in: false };
    }
    if (data && data.logged_in) {
      pill.textContent = "Account";
      pill.setAttribute("href", "/farmscout_online/app/account");
    } else {
      pill.textContent = "Login";
      pill.setAttribute("href", "/farmscout_online/app/account");
    }
    authNavFetchedOnce = true;
  } catch {
    // keep default label
  }
}

// ─────────────────────────────────── SPA MOBILE DRAWER
let drawerBound = false;

function bindMobileDrawer() {
  // Only wire on mobile; desktop keeps the full nav.
  if (window.innerWidth > 720) return;

  // Ensure overlay + drawer elements exist once in the DOM.
  if (!document.getElementById("fsMobOverlay")) {
    const overlay = document.createElement("div");
    overlay.id = "fsMobOverlay";
    overlay.className = "fs-mob-overlay";
    overlay.setAttribute("aria-hidden", "true");
    document.body.appendChild(overlay);

    const drawer = document.createElement("div");
    drawer.id = "fsMobDrawer";
    drawer.className = "fs-mob-drawer";
    drawer.setAttribute("role", "dialog");
    drawer.setAttribute("aria-modal", "true");
    drawer.setAttribute("aria-label", "Navigation");
    drawer.innerHTML = `
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
    `;
    document.body.appendChild(drawer);

    overlay.addEventListener("click", closeMobDrawer);
    document.getElementById("fsMobDrawerClose")?.addEventListener("click", closeMobDrawer);
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeMobDrawer();
    });
  }

  // Update auth link label after every navigation.
  const authPill = mountEl?.querySelector?.("#fsNavAuthPill");
  const mobAuthLink = document.getElementById("fsMobAuthLink");
  if (mobAuthLink && authPill) {
    mobAuthLink.textContent = authPill.textContent || "Login";
    mobAuthLink.href = authPill.getAttribute("href") || "/farmscout_online/app/account";
  }

  // Highlight current route.
  const path = normalizePath(window.location.pathname);
  document.querySelectorAll("#fsMobDrawer .fs-mob-drawer__nav a[href]").forEach((a) => {
    const aPath = normalizePath(new URL(a.href, window.location.href).pathname);
    a.setAttribute("aria-current", aPath === path ? "page" : "false");
  });

  // Wire hamburger buttons (re-bind after each page renders new DOM).
  document.querySelectorAll(".fs-sf-menu-btn").forEach((btn) => {
    const clone = btn.cloneNode(true);
    btn.parentNode?.replaceChild(clone, btn);
    clone.addEventListener("click", openMobDrawer);
  });

  drawerBound = true;
}

function openMobDrawer() {
  document.getElementById("fsMobOverlay")?.classList.add("is-open");
  const d = document.getElementById("fsMobDrawer");
  if (d) { d.classList.add("is-open"); document.body.style.overflow = "hidden"; }
}
function closeMobDrawer() {
  document.getElementById("fsMobOverlay")?.classList.remove("is-open");
  const d = document.getElementById("fsMobDrawer");
  if (d) { d.classList.remove("is-open"); document.body.style.overflow = ""; }
}

function ensureReservationModalScript() {
  if (reservationModalScriptLoaded) return;
  reservationModalScriptLoaded = true;
  const s = document.createElement("script");
  // Derive base prefix so "Reserve" works in both:
  // - domain root installs: /app/...
  // - subfolder installs: /farmscout_online/app/...
  const p = window.location.pathname || "/";
  const basePrefix = p.includes("/farmscout_online/") ? "/farmscout_online" : "";
  s.src = `${basePrefix}/app/js/fs-market-reservation.js?v=20260215`;
  s.defer = true;
  s.onerror = () => {
    // If this fails, the Reserve buttons will look clickable but do nothing.
    // Keep a console error so we can debug with users quickly.
    console.error("[FarmScout] Failed to load reservation script:", s.src);
  };
  document.head.appendChild(s);
}

function playPageTransition({ immediate = false } = {}) {
  if (!mountEl) return Promise.resolve();
  if (window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches) {
    return Promise.resolve();
  }
  if (immediate) {
    mountEl.classList.add("fs-page-enter");
    requestAnimationFrame(() => {
      requestAnimationFrame(() => mountEl.classList.remove("fs-page-enter"));
    });
    return Promise.resolve();
  }

  mountEl.classList.add("fs-page-leave");
  return new Promise((resolve) => {
    window.setTimeout(() => {
      mountEl.classList.remove("fs-page-leave");
      resolve();
    }, 260);
  });
}

async function navigateWithTransition(to, opts) {
  const { runSvgPathTransition } = await import("./transitions/svgPathTransition.js");
  await runSvgPathTransition("cover");
  navigate(to, opts);
  await runSvgPathTransition("uncover");
}

function pulseCard(cardEl) {
  if (!cardEl) return;
  cardEl.classList.remove("fs-card-click");
  // reflow to restart animation deterministically
  void cardEl.offsetWidth;
  cardEl.classList.add("fs-card-click");
  window.setTimeout(() => cardEl.classList.remove("fs-card-click"), 260);
}

function setHomeSettledState({ animateCards = false } = {}) {
  document.body.classList.remove("fs-animating", "fs-wave-active");
  document.body.classList.add("fs-nav-visible", "fs-tagline-visible");
  if (!mountEl) return;
  const title = mountEl.querySelector(".hero-title");
  if (title) title.classList.add("shrink");

  const cards = Array.from(mountEl.querySelectorAll(".card"));
  if (!animateCards) {
    document.body.classList.remove("fs-home-return", "fs-home-return-active");
    document.body.classList.add("fs-cards-active");
    cards.forEach((card) => {
      card.classList.add("fs-visible");
      card.style.opacity = "1";
      card.style.animation = "";
    });
    return;
  }

  // Replay a lightweight card "show up" animation when returning Home.
  document.body.classList.add("fs-home-return");
  document.body.classList.remove("fs-home-return-active");
  document.body.classList.remove("fs-cards-active");
  cards.forEach((card) => {
    card.classList.remove("fs-visible");
    card.style.opacity = "0";
    card.style.animation = "";
  });

  requestAnimationFrame(() => {
    // Ensure hero/nav/tagline fade in + cards slide in.
    document.body.classList.add("fs-home-return-active");
    document.body.classList.add("fs-cards-active");
    cards.forEach((card) => {
      card.addEventListener(
        "animationend",
        () => {
          card.classList.add("fs-visible");
          card.style.opacity = "1";
        },
        { once: true }
      );
    });
  });

  window.setTimeout(() => {
    document.body.classList.remove("fs-home-return", "fs-home-return-active");
  }, 700);
}

function initHomeCardClickAnimation() {
  if (!mountEl) return;
  const cards = mountEl.querySelectorAll("[data-card-animate]");
  cards.forEach((card) => {
    if (card.dataset.cardAnimBound === "1") return;
    card.dataset.cardAnimBound = "1";
    // keep binding so we can extend later (e.g., keyboard Space behavior)
  });
}

async function initHomeBestDeals() {
  if (!mountEl) return;
  const grid = mountEl.querySelector("[data-home-best-deals]");
  if (!grid) return;

  try {
    const cats = await fetchJson("/farmscout_online/api/get_categories_all.php");
    const list = Array.isArray(cats.categories) ? cats.categories : [];
    const candidates = list.filter((c) => Number(c.product_count || 0) > 0).slice(0, 6);

    const results = await Promise.all(
      candidates.map(async (c) => {
        try {
          const cmp = await fetchJson(
            `/farmscout_online/api/get_category_compare.php?category_id=${encodeURIComponent(String(c.id))}`
          );
          const products = Array.isArray(cmp.products) ? cmp.products : [];
          const best = products
            .map((p) => ({
              product_name: p.product_name || "",
              price: Number(p.min || 0),
              market_name: p.best?.market_name || "",
              unit: p.best?.unit || "",
            }))
            .filter((p) => p.product_name && Number.isFinite(p.price) && p.price > 0 && p.market_name);

          best.sort((a, b) => a.price - b.price);
          return best.slice(0, 3);
        } catch {
          return [];
        }
      })
    );

    const all = results.flat();
    all.sort((a, b) => a.price - b.price);
    const top = all.slice(0, 4);

    if (!top.length) {
      grid.innerHTML = `<div class="fs-home-deals-empty">No deals available right now.</div>`;
      return;
    }

    grid.innerHTML = top
      .map((d) => {
        const unit = d.unit ? `/${escapeHtml(d.unit)}` : "";
        return `
          <article class="fs-home-deal">
            <div class="fs-home-deal-badge">Best Price</div>
            <div class="fs-home-deal-name">${escapeHtml(d.product_name)}</div>
            <div class="fs-home-deal-meta">
              <span class="fs-home-deal-price">${formatPeso(d.price)}${unit}</span>
              <span class="fs-home-deal-market">${escapeHtml(d.market_name)}</span>
            </div>
          </article>
        `;
      })
      .join("");
  } catch {
    grid.innerHTML = `<div class="fs-home-deals-empty">No deals available right now.</div>`;
  }
}

function initNavDrawUnderline() {
  if (!mountEl) return;
  if (navUnderlineBound) return;

  const svgVariants = [
    `<svg viewBox="0 0 310 40" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M5 20.9999C26.7762 16.2245 49.5532 11.5572 71.7979 14.6666C84.9553 16.5057 97.0392 21.8432 109.987 24.3888C116.413 25.6523 123.012 25.5143 129.042 22.6388C135.981 19.3303 142.586 15.1422 150.092 13.3333C156.799 11.7168 161.702 14.6225 167.887 16.8333C181.562 21.7212 194.975 22.6234 209.252 21.3888C224.678 20.0548 239.912 17.991 255.42 18.3055C272.027 18.6422 288.409 18.867 305 17.9999"/></svg>`,
    `<svg viewBox="0 0 310 40" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M4.99805 20.9998C65.6267 17.4649 126.268 13.845 187.208 12.8887C226.483 12.2723 265.751 13.2796 304.998 13.9998"/></svg>`,
    `<svg viewBox="0 0 310 40" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M5 29.8857C52.3147 26.9322 99.4329 21.6611 146.503 17.1765C151.753 16.6763 157.115 15.9505 162.415 15.6551C163.28 15.6069 165.074 15.4123 164.383 16.4275C161.704 20.3627 157.134 23.7551 153.95 27.4983C153.209 28.3702 148.194 33.4751 150.669 34.6605C153.638 36.0819 163.621 32.6063 165.039 32.2029C178.55 28.3608 191.49 23.5968 204.869 19.5404C231.903 11.3436 259.347 5.83254 288.793 5.12258C294.094 4.99476 299.722 4.82265 305 5.45025"/></svg>`,
    `<svg viewBox="0 0 310 40" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M17.0039 32.6826C32.2307 32.8412 47.4552 32.8277 62.676 32.8118C67.3044 32.807 96.546 33.0555 104.728 32.0775C113.615 31.0152 104.516 28.3028 102.022 27.2826C89.9573 22.3465 77.3751 19.0254 65.0451 15.0552C57.8987 12.7542 37.2813 8.49399 44.2314 6.10216C50.9667 3.78422 64.2873 5.81914 70.4249 5.96641C105.866 6.81677 141.306 7.58809 176.75 8.59886C217.874 9.77162 258.906 11.0553 300 14.4892"/></svg>`,
  ];

  let nextIndex = Math.floor(Math.random() * svgVariants.length);

  mountEl.addEventListener(
    "mouseenter",
    (e) => {
      const link = e.target.closest?.("[data-draw-line]");
      if (!link) return;
      const box = link.querySelector("[data-draw-line-box]");
      if (!box) return;

      // don't restart if something is already there
      if (box.querySelector("svg")) return;

      box.innerHTML = svgVariants[nextIndex];
      nextIndex = (nextIndex + 1) % svgVariants.length;

      const path = box.querySelector("path");
      if (!path) return;

      // ensure styling matches our CSS (currentColor)
      path.setAttribute("stroke", "currentColor");
      path.setAttribute("fill", "none");
      path.setAttribute("stroke-linecap", "round");
      path.setAttribute("stroke-width", "10");

      const len = Math.max(1, Math.ceil(path.getTotalLength()));
      path.style.strokeDasharray = `${len}`;
      path.style.strokeDashoffset = `${len}`;
      path.style.transition = "none";

      // kick animation next frame
      requestAnimationFrame(() => {
        path.style.transition = "stroke-dashoffset 520ms ease-in-out";
        path.style.strokeDashoffset = "0";
      });
    },
    { capture: true }
  );

  mountEl.addEventListener(
    "mouseleave",
    (e) => {
      const link = e.target.closest?.("[data-draw-line]");
      if (!link) return;
      const box = link.querySelector("[data-draw-line-box]");
      const path = box?.querySelector("path");
      if (!box || !path) return;

      // draw out
      const len = Number.parseFloat(path.style.strokeDasharray || "0") || 300;
      path.style.transition = "stroke-dashoffset 520ms ease-in-out";
      path.style.strokeDashoffset = `${len}`;

      const cleanup = () => {
        box.innerHTML = "";
        path.removeEventListener("transitionend", cleanup);
      };
      path.addEventListener("transitionend", cleanup, { once: true });
    },
    { capture: true }
  );

  navUnderlineBound = true;
}

function clearEntranceState() {
  entranceTimers.forEach(clearTimeout);
  entranceTimers = [];
  document.body.classList.remove("fs-animating", "fs-wave-active", "fs-cards-active", "fs-nav-visible", "fs-tagline-visible");
  if (!mountEl) return;
  const title = mountEl.querySelector(".hero-title");
  if (title) {
    title.classList.remove("shrink");
  }
  mountEl.querySelectorAll(".card").forEach((card) => {
    card.classList.remove("fs-visible");
    card.style.animation = "";
    card.style.opacity = "";
  });
}

function triggerHomeEntrance() {
  clearEntranceState();
  if (!mountEl) return;
  document.body.classList.add("fs-animating");
  document.body.classList.add("fs-wave-active");
  const title = mountEl.querySelector(".hero-title");
  const cards = mountEl.querySelectorAll(".card");

  // Phase 1: wavy intro runs via CSS (fs-wave-active)

  // Phase 2: shrink title
  entranceTimers.push(setTimeout(() => {
    document.body.classList.remove("fs-wave-active");
    if (title) title.classList.add("shrink");
  }, 2200));

  // Phase 3: navbar
  entranceTimers.push(setTimeout(() => {
    document.body.classList.add("fs-nav-visible");
  }, 2800));

  // Phase 4: tagline
  entranceTimers.push(setTimeout(() => {
    document.body.classList.add("fs-tagline-visible");
  }, 3000));

  // Phase 5: cards
  entranceTimers.push(setTimeout(() => {
    document.body.classList.add("fs-cards-active");
    cards.forEach((card) => {
      card.addEventListener(
        "animationend",
        () => {
          card.classList.add("fs-visible");
          card.style.opacity = "1";
        },
        { once: true }
      );
    });
  }, 3200));

  // End: stop clipping after intro
  entranceTimers.push(setTimeout(() => {
    document.body.classList.remove("fs-animating");
  }, 3800));
}

function render() {
  if (!mountEl) return;

  teardownHomeNavTone();

  const currentPath = normalizePath(window.location.pathname);
  const search = window.location.search || "";
  const params = new URLSearchParams(search);

  // Routes: keep the SPA minimal + professional.
  if (currentPath === "/" || currentPath === "") {
    mountEl.innerHTML = renderHome();
    syncAuthNav();
    initNavDrawUnderline();
    bindNavSearchAutocomplete();
    bindMobileDrawer();
    // Intro disabled — always render the home in its settled state.
    setHomeSettledState({ animateCards: false });
    homeIntroPlayed = true;
    initHomeCardClickAnimation();
    bindHomeNavTone();
    try {
      window.sessionStorage.setItem("fsHomeIntroPlayed", "1");
    } catch {}
    return;
  }

  clearEntranceState();

  // Redirect routes to your existing working PHP pages (safe for defense)
  if (currentPath === "/markets") {
    const marketId = params.get("market") || "";
    mountEl.innerHTML = renderMarkets({ markets: [], selectedMarketId: marketId });
    syncAuthNav();
    initNavDrawUnderline();
    bindNavSearchAutocomplete();
    bindMobileDrawer();
    initMarketsPage({ marketId });
    return;
  }

  if (currentPath === "/products") {
    const categoryId = params.get("category") || "";
    const marketId = params.get("market") || "";
    mountEl.innerHTML = renderProducts({ categories: [], selectedCategoryId: categoryId });
    syncAuthNav();
    initNavDrawUnderline();
    bindNavSearchAutocomplete();
    bindMobileDrawer();
    initProductsPage({ categoryId, marketId });
    return;
  }

  if (currentPath === "/nearest") {
    mountEl.innerHTML = renderNearestMarket({ markets: [] });
    syncAuthNav();
    initNavDrawUnderline();
    bindNavSearchAutocomplete();
    bindMobileDrawer();
    initNearestMarketPage();
    return;
  }

  if (currentPath === "/alerts") {
    mountEl.innerHTML = renderRedirectPage({
      label: "Opening Price Alerts…",
      href: "/farmscout_online/user-account.php?section=price_alerts",
    });
    syncAuthNav();
    bindNavSearchAutocomplete();
    bindMobileDrawer();
    window.location.assign("/farmscout_online/user-account.php?section=price_alerts");
    return;
  }

  if (currentPath === "/account") {
    mountEl.innerHTML = renderRedirectPage({
      label: "Opening My Account…",
      href: "/farmscout_online/user-account.php",
    });
    syncAuthNav();
    bindNavSearchAutocomplete();
    bindMobileDrawer();
    window.location.assign("/farmscout_online/user-account.php");
    return;
  }

  // Fallback
  mountEl.innerHTML = renderRedirectPage({
    label: "Page not found. Going home…",
    href: "/farmscout_online/app/",
  });
  setTimeout(() => navigate("/", { replace: true }), 400);
}

async function fetchJson(url) {
  const res = await fetch(url, { headers: { Accept: "application/json" } });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return await res.json();
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function formatPeso(value) {
  const amount = Number(value || 0);
  if (!Number.isFinite(amount)) return "₱0";
  return `₱${amount.toLocaleString("en-PH", { maximumFractionDigits: 2 })}`;
}

function normalizeFilterValue(value) {
  return String(value ?? "").toLowerCase().trim();
}

async function initMarketsPage({ marketId }) {
  if (!mountEl) return;
  try {
    const data = await fetchJson("/farmscout_online/api/get_markets.php");
    const list = Array.isArray(data.markets) ? data.markets : [];
    mountEl.innerHTML = renderMarkets({
      markets: list.map((m) => ({
        id: String(m.id),
        name: m.market_name,
        address: m.address,
        product_count: m.product_count,
      })),
      selectedMarketId: marketId ? String(marketId) : "",
    });
    syncAuthNav();
    initNavDrawUnderline();
    bindNavSearchAutocomplete();
    bindMobileDrawer();
    initMarketsFullscreenSlideshow({
      titles: list.map((m) => m.market_name),
      images: list.map((m, idx) => (idx % 2 === 0 ? "/farmscout_online/assets/images/market.png" : "/farmscout_online/assets/images/map.png")),
    });
  } catch (e) {
    // leave skeleton
  }
}

let marketsSlideshowCleanup = null;
async function initMarketsFullscreenSlideshow({ titles } = {}) {
  if (!mountEl) return;
  if (typeof marketsSlideshowCleanup === "function") {
    marketsSlideshowCleanup();
    marketsSlideshowCleanup = null;
  }
  const root = mountEl.querySelector("[data-gsap-markets]");
  if (!root) return;
  const slidesWrap = mountEl.querySelector(".slides");
  const slides = Array.from(mountEl.querySelectorAll(".slide"));
  if (slides.length <= 1) return;

  const uiTitle = mountEl.querySelector(".slide-title");
  const curEl = mountEl.querySelector(".current-slide");
  const totalEl = mountEl.querySelector(".total-slides");
  if (totalEl) totalEl.textContent = String(slides.length).padStart(2, "0");

  const mod = await import("gsap");
  const gsap = mod.gsap || mod.default || mod;
  const obs = await import("gsap/Observer");
  const Observer = obs.Observer || obs.default || obs;

  let current = 0;
  let animating = false;

  const setUi = (idx) => {
    if (curEl) curEl.textContent = String(idx + 1).padStart(2, "0");
    if (!uiTitle) return;

    const titleContainer = mountEl.querySelector(".slide-title-container");
    const currentTitle = mountEl.querySelector(".slide-title");
    if (!titleContainer || !currentTitle) return;

    const newTitle = document.createElement("div");
    newTitle.className = "slide-title enter-up";
    newTitle.textContent = titles?.[idx] || "Markets";
    titleContainer.appendChild(newTitle);

    currentTitle.classList.add("exit-up");
    void newTitle.offsetWidth;
    setTimeout(() => newTitle.classList.remove("enter-up"), 10);
    setTimeout(() => currentTitle.remove(), 500);
  };
  if (uiTitle) uiTitle.textContent = titles?.[0] || "Markets";

  const show = (next, dir) => {
    if (animating) return;
    animating = true;
    const prev = current;
    current = (next + slides.length) % slides.length;

    const prevSlide = slides[prev];
    const nextSlide = slides[current];
    const prevImg = prevSlide.querySelector(".slide__img");
    const nextImg = nextSlide.querySelector(".slide__img");

    setUi(current);

    gsap.set(nextSlide, { opacity: 1, zIndex: 99 });
    gsap.set(prevSlide, { zIndex: 1 });
    gsap.set(nextImg, { scale: 1.12, yPercent: dir > 0 ? 14 : -14 });

    gsap
      .timeline({
        defaults: { duration: 0.85, ease: "power3.out" },
        onComplete: () => {
          prevSlide.classList.remove("slide--current");
          nextSlide.classList.add("slide--current");
          gsap.set(prevSlide, { opacity: 0, zIndex: 1 });
          gsap.set(nextSlide, { zIndex: 2 });
          animating = false;
        },
      })
      .to(prevImg, { scale: 1.06, yPercent: dir > 0 ? -10 : 10 }, 0)
      .to(prevSlide, { opacity: 0, duration: 0.6, ease: "power2.out" }, 0.15)
      .to(nextImg, { scale: 1, yPercent: 0 }, 0.05);
  };

  const next = () => show(current + 1, 1);
  const prev = () => show(current - 1, -1);

  const prevBtn = mountEl.querySelector(".prev-slide");
  const nextBtn = mountEl.querySelector(".next-slide");
  const onPrev = () => prev();
  const onNext = () => next();
  prevBtn?.addEventListener("click", onPrev);
  nextBtn?.addEventListener("click", onNext);

  const observer = Observer.create({
    target: slidesWrap || root,
    type: "wheel,touch,pointer",
    wheelSpeed: -1,
    tolerance: 8,
    preventDefault: true,
    onDown: () => next(),
    onUp: () => prev(),
  });

  marketsSlideshowCleanup = () => {
    try {
      observer?.kill?.();
    } catch {}
    prevBtn?.removeEventListener("click", onPrev);
    nextBtn?.removeEventListener("click", onNext);
    try {
      gsap.killTweensOf("*");
    } catch {}
  };
}

function normalizeImg(v) {
  const s = String(v || "").trim();
  if (!s) return "";
  if (/^https?:\/\//i.test(s)) return s;
  if (s.startsWith("/")) return s;
  return `/farmscout_online/${s.replace(/^\.\//, "")}`;
}

function renderProductCards(items, { compareMode = true, cheapest = Infinity } = {}) {
  return items
    .map((row, index) => {
      const best = compareMode ? row.best || {} : row || {};
      const unit = best.unit || "";
      const productName = escapeHtml(compareMode ? row.product_name : best.filipino_name || best.name || "Product");
      const marketName = escapeHtml(best.market_name || "Market");
      const rawImg = best.product_image || best.image_url || "";
      const rawDesc = (best.product_description || best.description || row.product_description || "").trim();
      const imageSrc = normalizeImg(rawImg) || "/farmscout_online/assets/images/products.png";
      const descHtml = rawDesc ? `<div class="fs-prod-card-desc">${escapeHtml(rawDesc)}</div>` : ``;
      const rawName = escapeHtml(normalizeFilterValue(compareMode ? row.product_name : best.filipino_name || best.name || ""));
      const rawMarket = escapeHtml(normalizeFilterValue(best.market_name || ""));
      const rawCategory = escapeHtml(
        normalizeFilterValue(compareMode ? (best.category_filipino || best.category || "") : (best.category_filipino || best.category || ""))
      );
      const rawPrice = compareMode ? Number(row.min || 0) : Number(best.raw_price || String(best.current_price || "").replace(/[^\d.]/g, "") || 0);
      const productId = Number(best.product_id || best.id || 0);
      const marketIdNum = Number(best.market_id || 0);
      const farmerIdNum = Number(best.farmer_id || 0);
      const isAvailable = String(best.is_available ?? "true") !== "false" && Boolean(best.is_available ?? true);
      const priceText = compareMode
        ? `${formatPeso(row.min)}${unit ? `/${escapeHtml(unit)}` : ""}`
        : `${formatPeso(rawPrice)}${unit ? `/${escapeHtml(unit)}` : ""}`;
      const rangeText = compareMode
        ? (Number(row.min || 0) === Number(row.max || 0) ? `Range ${formatPeso(row.min)}` : `Range ${formatPeso(row.min)} - ${formatPeso(row.max)}`)
        : escapeHtml(best.category_filipino || best.category || "Available today");
      const oosBadge = isAvailable ? `` : `<div class="fs-prod-oos" aria-label="Not available">Not available</div>`;
      return `
        <article
          class="fs-prod-card${isAvailable ? "" : " is-oos"}"
          data-product-card
          data-product-index="${index}"
          data-product-name="${rawName}"
          data-product-market="${rawMarket}"
          data-product-category="${rawCategory}"
          data-product-price="${rawPrice}"
          data-product-id="${Number.isFinite(productId) ? productId : 0}"
          data-market-id="${Number.isFinite(marketIdNum) ? marketIdNum : 0}"
          data-farmer-id="${Number.isFinite(farmerIdNum) ? farmerIdNum : 0}"
          data-product-unit="${escapeHtml(unit || "kg")}"
        >
          <div class="fs-prod-image">
            <img src="${imageSrc}" alt="" loading="lazy" onerror="this.onerror=null;this.src='/farmscout_online/assets/images/products.png';" />
            ${oosBadge}
            <button
              type="button"
              class="fs-prod-alert js-price-alert"
              title="Add price alert"
              aria-label="Add price alert"
              data-product-id="${Number.isFinite(productId) ? productId : 0}"
              aria-disabled="${Number.isFinite(productId) && productId > 0 ? "false" : "true"}"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2Zm-2 .17 1 1V18H6v-.83l1-1V11a5 5 0 1 1 10 0v5.17Z"/>
              </svg>
            </button>
          </div>
          <div class="fs-prod-card-body">
            <div class="fs-prod-card-name">${productName}</div>
            <div class="fs-prod-card-market">${marketName}</div>
            ${descHtml}
            <div class="fs-prod-card-price">${priceText}</div>
            <div class="fs-prod-card-range">${rangeText}</div>
          </div>
          <div class="product-unit-options" style="display:none">
            <div class="product-unit-option">${escapeHtml(unit || "kg")} - ₱${Number(rawPrice || 0).toFixed(2)}</div>
          </div>
          <div class="fs-prod-card-actions">
            <button
              type="button"
              class="fs-prod-card-cta js-mf-reserve"
              data-product-id="${Number.isFinite(productId) ? productId : 0}"
              data-market-id="${Number.isFinite(marketIdNum) ? marketIdNum : 0}"
              data-farmer-id="${Number.isFinite(farmerIdNum) ? farmerIdNum : 0}"
              data-product-name="${productName}"
              data-product-price="${formatPeso(rawPrice)}"
              data-product-unit="${escapeHtml(unit || "kg")}"
              aria-disabled="${Number.isFinite(productId) && productId > 0 && isAvailable ? "false" : "true"}"
              data-out-of-stock="${isAvailable ? "0" : "1"}"
            >Reserve</button>
            <a class="fs-prod-card-cta" href="/farmscout_online/app/nearest?product=${encodeURIComponent(String(compareMode ? row.product_name || "" : best.filipino_name || best.name || ""))}">See on map</a>
          </div>
        </article>
      `;
    })
    .join("");
}

async function initProductsPage({ categoryId, marketId }) {
  if (!mountEl) return;
  try {
    const urlParams = new URLSearchParams(window.location.search || "");
    const navQuery = String(urlParams.get("q") || "").trim();

    const cats = await fetchJson("/farmscout_online/api/get_categories_all.php");
    const list = Array.isArray(cats.categories) ? cats.categories : [];

    // Navbar search (market/category not specified): auto-pick best category and show results.
    // This keeps "Browse Products" category-first while still making the global search feel responsive.
    if (!categoryId && !marketId && navQuery) {
      const categories = list.map((c) => ({ id: String(c.id), name: String(c.name || "") })).filter((c) => c.id);
      const tests = await Promise.all(
        categories.map(async (c) => {
          try {
            const cmp = await fetchJson(
              `/farmscout_online/api/get_category_compare.php?category_id=${encodeURIComponent(String(c.id))}`
            );
            const products = Array.isArray(cmp.products) ? cmp.products : [];
            const q = normalizeFilterValue(navQuery);
            const hits = products.filter((p) => normalizeFilterValue(p.product_name || "").includes(q)).length;
            return { id: c.id, hits };
          } catch {
            return { id: c.id, hits: 0 };
          }
        })
      );
      tests.sort((a, b) => b.hits - a.hits);
      const best = tests[0]?.id || categories[0]?.id || "";
      if (best) {
        navigate(`/products?category=${encodeURIComponent(best)}&q=${encodeURIComponent(navQuery)}`, { replace: true });
        return;
      }
    }

    let selectedMarketName = "";
    if (!categoryId && marketId) {
      try {
        const marketsData = await fetchJson("/farmscout_online/api/get_markets.php");
        const markets = Array.isArray(marketsData.markets) ? marketsData.markets : [];
        selectedMarketName = markets.find((m) => String(m.id) === String(marketId))?.market_name || "";
      } catch {}
    }
    mountEl.innerHTML = renderProducts({
      categories: list.map((c) => ({
        id: String(c.id),
        title: c.name,
        products: Array.from({ length: c.product_count || 0 }).map((_, i) => `p${i}`), // count only for UI
      })),
      selectedCategoryId: categoryId ? String(categoryId) : "",
      selectedMarketName,
    });
    syncAuthNav();
    initNavDrawUnderline();
    bindNavSearchAutocomplete();
    bindMobileDrawer();

    if (categoryId) {
      ensureReservationModalScript();
      const cmp = await fetchJson(`/farmscout_online/api/get_category_compare.php?category_id=${encodeURIComponent(String(categoryId))}`);
      const products = Array.isArray(cmp.products) ? cmp.products : [];
      const cheapest = Math.min(
        ...products.map((p) => Number(p.min || Infinity)).filter((n) => Number.isFinite(n) && n > 0),
        Infinity
      );
      const grid = mountEl.querySelector("[data-products-grid]");
      const counters = mountEl.querySelectorAll("[data-products-count]");
      counters.forEach((el) => {
        el.textContent = `${products.length} Product${products.length === 1 ? "" : "s"}`;
      });
      if (grid) {
        const cards = renderProductCards(products, { compareMode: true, cheapest });
        if (!cards) {
          grid.innerHTML = `<div class="fs-prod-loading">No products found for this category yet.</div>`;
        } else {
          grid.innerHTML = cards;
        }
      }
      const urlQ = new URLSearchParams(window.location.search).get("q") || "";
      initProductsFiltering(products, { initialQuery: urlQ, initialMarketId: marketId, filterMode: "market" });
      animateProductsShop();
      return;
    }

    if (marketId) {
      ensureReservationModalScript();
      const data = await fetchJson(`/farmscout_online/api/get_market_products.php?market_id=${encodeURIComponent(String(marketId))}`);
      const products = Array.isArray(data.products) ? data.products : [];
      const cheapest = Math.min(
        ...products.map((p) => Number(p.raw_price || String(p.current_price || "").replace(/[^\d.]/g, "") || Infinity)).filter((n) => Number.isFinite(n) && n > 0),
        Infinity
      );
      const grid = mountEl.querySelector("[data-products-grid]");
      const counters = mountEl.querySelectorAll("[data-products-count]");
      counters.forEach((el) => {
        el.textContent = `${products.length} Product${products.length === 1 ? "" : "s"}`;
      });
      if (grid) {
        const cards = renderProductCards(products, { compareMode: false, cheapest });
        grid.innerHTML = cards || `<div class="fs-prod-loading">No products found for this market yet.</div>`;
      }
      const urlQ = new URLSearchParams(window.location.search).get("q") || "";
      initProductsFiltering(products, { initialQuery: urlQ, initialMarketId: marketId, filterMode: "category" });
      animateProductsShop();
    }
  } catch (e) {
    // leave skeleton
  }
}

function initProductsFiltering(products, { initialQuery = "", initialMarketId = "", filterMode = "market" } = {}) {
  if (!mountEl) return;
  const shop = mountEl.querySelector("[data-products-shop]");
  if (!shop) return;

  const cards = Array.from(mountEl.querySelectorAll("[data-product-card]"));
  const search = mountEl.querySelector("[data-products-search]");
  const price = mountEl.querySelector("[data-products-price]");
  const priceLabel = mountEl.querySelector("[data-products-price-label]");
  const sortSelect = mountEl.querySelector("[data-products-sort]");
  const marketWrap = mountEl.querySelector("[data-products-market-filters]");
  const countEls = mountEl.querySelectorAll("[data-products-count]");
  const activeText = mountEl.querySelector(".fs-prod-toolbar span");
  const empty = mountEl.querySelector("[data-products-empty]");
  const grid = mountEl.querySelector("[data-products-grid]");
  const resetButtons = mountEl.querySelectorAll("[data-products-reset]");

  // If there are no product cards, keep a clean state and avoid binding filters.
  if (!cards.length) {
    if (grid && !grid.querySelector(".fs-prod-loading")) {
      grid.innerHTML = `<div class="fs-prod-loading">No products found for this category yet.</div>`;
    }
    countEls.forEach((el) => {
      el.textContent = `0 Products`;
    });
    if (activeText) activeText.textContent = `Active filters: None`;
    if (empty) empty.hidden = true;
    return;
  }

  // Seed initial search from navbar query (?q=...).
  if (search && initialQuery && !search.value) {
    search.value = String(initialQuery);
  }

  const prices = products
    .map((p) => {
      const v =
        p && typeof p === "object" && "min" in p
          ? Number(p.min || 0)
          : Number(p.raw_price || String(p.current_price || "").replace(/[^\d.]/g, "") || 0);
      return v;
    })
    .filter((n) => Number.isFinite(n));
  const maxPrice = Math.max(100, ...prices);
  const roundedMax = Math.ceil(maxPrice / 50) * 50;
  if (price) {
    price.max = String(roundedMax);
    price.value = String(roundedMax);
  }
  if (priceLabel) priceLabel.textContent = `${formatPeso(roundedMax)}+`;

  const filterValues = Array.from(
    new Set(
      products
        .map((p) => {
          if (filterMode === "category") {
            return p.category_filipino || p.category || "";
          }
          return p.best?.market_name || p.market_name || "";
        })
        .filter(Boolean)
        .map((m) => String(m))
    )
  ).sort((a, b) => a.localeCompare(b));

  if (marketWrap) {
    marketWrap.innerHTML = filterValues.length
      ? filterValues
          .map(
            (value) => `
              <label>
                <input data-products-market type="checkbox" value="${escapeHtml(normalizeFilterValue(value))}" />
                ${escapeHtml(value)}
              </label>
            `
          )
          .join("")
      : `<div class="fs-prod-filter-note">No ${filterMode === "category" ? "category" : "market"} filters available.</div>`;
  }

  if (initialMarketId && filterMode === "market") {
    const marketName = products.find((p) => String(p.best?.market_id || p.market_id || "") === String(initialMarketId))?.best?.market_name
      || products.find((p) => String(p.market_id || "") === String(initialMarketId))?.market_name
      || "";
    const normalized = normalizeFilterValue(marketName);
    if (normalized) {
      mountEl.querySelectorAll("[data-products-market]").forEach((el) => {
        el.checked = el.value === normalized;
      });
    }
  }

  const getSelectedMarkets = () =>
    Array.from(mountEl.querySelectorAll("[data-products-market]:checked")).map((el) => el.value);

  const render = () => {
    const q = normalizeFilterValue(search?.value || "");
    const max = Number(price?.value || roundedMax);
    const selectedMarkets = getSelectedMarkets();

    let visible = cards.filter((card) => {
      const name = card.dataset.productName || "";
      const market = card.dataset.productMarket || "";
      const category = card.dataset.productCategory || "";
      const cardPrice = Number(card.dataset.productPrice || 0);
      const matchesSearch = !q || name.includes(q) || market.includes(q);
      const matchesPrice = !Number.isFinite(max) || cardPrice <= max;
      const matchesFilter =
        selectedMarkets.length === 0 ||
        selectedMarkets.includes(filterMode === "category" ? category : market);
      return matchesSearch && matchesPrice && matchesFilter;
    });

    const sortValue = sortSelect?.value || "original";
    visible = visible.slice().sort((a, b) => {
      if (sortValue === "price-asc") return Number(a.dataset.productPrice) - Number(b.dataset.productPrice);
      if (sortValue === "price-desc") return Number(b.dataset.productPrice) - Number(a.dataset.productPrice);
      if (sortValue === "name-asc") return (a.dataset.productName || "").localeCompare(b.dataset.productName || "");
      /* original / default: preserve server render order */
      return Number(a.dataset.productIndex) - Number(b.dataset.productIndex);
    });

    cards.forEach((card) => {
      card.hidden = true;
    });
    visible.forEach((card) => {
      card.hidden = false;
      grid?.appendChild(card);
    });

    const label = `${visible.length} Product${visible.length === 1 ? "" : "s"}`;
    countEls.forEach((el) => {
      el.textContent = label;
    });
    if (priceLabel) priceLabel.textContent = max >= roundedMax ? `${formatPeso(roundedMax)}+` : formatPeso(max);
    if (activeText) {
      const marketLabel = selectedMarkets.length
        ? ` · ${selectedMarkets.length} ${filterMode === "category" ? `categor${selectedMarkets.length === 1 ? "y" : "ies"}` : `market${selectedMarkets.length === 1 ? "" : "s"}`}`
        : "";
      const searchLabel = q ? ` · Search "${search.value}"` : "";
      activeText.textContent = `Active filters: Price ₱0 - ${max >= roundedMax ? `${formatPeso(roundedMax)}+` : formatPeso(max)}${marketLabel}${searchLabel}`;
    }
    if (empty) empty.hidden = visible.length !== 0;
  };

  const reset = () => {
    // Make sure reset never submits/scrolls the page.
    // (Some buttons can live inside forms depending on markup changes.)
    if (search) search.value = "";
    if (price) price.value = String(roundedMax);
    if (sortSelect) sortSelect.value = "original";
    mountEl.querySelectorAll("[data-products-market]").forEach((el) => {
      el.checked = false;
    });
    const gcash = mountEl.querySelector?.("[data-products-market-filters]"); // touch DOM to ensure it exists
    void gcash;
    render();
  };

  search?.addEventListener("input", render);
  price?.addEventListener("input", render);
  sortSelect?.addEventListener("change", render);
  marketWrap?.addEventListener("change", render);
  resetButtons.forEach((btn) =>
    btn.addEventListener("click", (e) => {
      e.preventDefault?.();
      reset();
    })
  );
  mountEl.querySelector("[data-products-filter-form]")?.addEventListener("submit", (e) => e.preventDefault());
  render();
}

async function animateProductsShop() {
  if (!mountEl) return;
  const shop = mountEl.querySelector("[data-products-shop]");
  const pageHead = mountEl.querySelector(".fs-page-head");
  const categoryHero = mountEl.querySelector(".fs-prod-category-hero");
  const cards = Array.from(mountEl.querySelectorAll(".fs-prod-card"));
  if (!shop) return;

  // Ensure route transition (SVG wipe) finishes before animating content.
  if (!window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches) {
    const waitUntil = Date.now() + 2500;
    while (document.body.classList.contains("fs-transitioning") && Date.now() < waitUntil) {
      await new Promise((r) => requestAnimationFrame(r));
    }
  }

  try {
    const mod = await import("gsap");
    const gsap = mod.gsap || mod.default || mod;
    const scrollMod = await import("gsap/ScrollToPlugin");
    const ScrollToPlugin = scrollMod.ScrollToPlugin || scrollMod.default || scrollMod;
    gsap.registerPlugin?.(ScrollToPlugin);

    const introEls = [pageHead, categoryHero, shop].filter(Boolean);
    gsap.fromTo(
      introEls,
      { opacity: 0, y: 34, filter: "blur(6px)" },
      {
        opacity: 1,
        y: 0,
        filter: "blur(0px)",
        duration: 0.72,
        stagger: 0.12,
        ease: "power3.out",
      }
    );
    if (cards.length) {
      gsap.fromTo(
        cards,
        { opacity: 0, y: 24 },
        { opacity: 1, y: 0, duration: 0.55, stagger: 0.045, ease: "power3.out", delay: 0.12 }
      );
    }
    gsap.to(window, {
      duration: 0.8,
      ease: "power3.inOut",
      scrollTo: { y: shop, offsetY: 18 },
    });
  } catch {
    shop.scrollIntoView({ behavior: "smooth", block: "start" });
  }
}

async function initNearestMarketPage() {
  if (!mountEl) return;
  try {
    const data = await fetchJson("/farmscout_online/api/get_markets.php");
    const raw = Array.isArray(data.markets) ? data.markets : [];
    // Use the 5 markets from DB (id order), in case more exist.
    const list = raw
      .slice()
      .sort((a, b) => Number(a.id) - Number(b.id))
      .slice(0, 5);

    mountEl.innerHTML = renderNearestMarket({
      markets: list.map((m) => ({
        id: String(m.id),
        name: m.market_name,
        address: m.address,
      })),
    });
    syncAuthNav();
    initNavDrawUnderline();
    bindNavSearchAutocomplete();
    bindMobileDrawer();
    await initNearestMarketMap({ markets: list });
  } catch (e) {
    // leave skeleton
  }
}
function getAppConfig() {
  return window.FarmScoutAppConfig || {};
}

function loadGoogleMapsApi() {
  if (window.google?.maps) return Promise.resolve(window.google);
  if (googleMapsApiPromise) return googleMapsApiPromise;

  googleMapsApiPromise = new Promise((resolve, reject) => {
    const key = String(getAppConfig().googleMapsApiKey || "").trim();
    if (!key) {
      reject(new Error("Google Maps API key missing"));
      return;
    }

    const existing = document.getElementById("fs-google-maps-api");
    if (existing) {
      existing.addEventListener("load", () => resolve(window.google), { once: true });
      existing.addEventListener("error", () => reject(new Error("Failed to load Google Maps")), { once: true });
      return;
    }

    const callbackName = "__fsGoogleMapsInit";
    window[callbackName] = () => {
      resolve(window.google);
      try {
        delete window[callbackName];
      } catch {}
    };

    const script = document.createElement("script");
    script.id = "fs-google-maps-api";
    script.async = true;
    script.defer = true;
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&callback=${callbackName}&loading=async`;
    script.onerror = () => reject(new Error("Failed to load Google Maps"));
    document.head.appendChild(script);
  });

  return googleMapsApiPromise;
}

function getNearestDefaults() {
  const cfg = getAppConfig().mapDefaults || {};
  return {
    lat: Number(cfg.lat || 16.8219),
    lng: Number(cfg.lng || 120.4042),
    zoom: Number(cfg.zoom || 11),
  };
}

function buildNearestMarketRows(markets, recommendedId) {
  return markets
    .map((m) => {
      const displayName = escapeHtml(m.market_name || m.name || "Market");
      const distanceText = Number.isFinite(m.distance_km)
        ? (m.distance_km < 0.05 ? "~0 km" : m.distance_km < 0.5 ? `${m.distance_km.toFixed(1)} km` : `${m.distance_km.toFixed(1)} km`)
        : "";
      const status = typeof m.is_open === "boolean" ? (m.is_open ? "open" : "closed") : "";
      const productsHref = `/farmscout_online/app/products?market=${encodeURIComponent(String(m.id || ""))}`;
      return `
        <div class="fs-nearest-item${String(m.id) === String(recommendedId) ? " is-active is-recommended" : ""}" data-nearest-market-id="${escapeHtml(m.id)}" role="button" tabindex="0">
          <div class="fs-nearest-item-name">
            ${displayName}
            ${distanceText ? `<span class="fs-nearest-item-dist">${distanceText}</span>` : ""}
          </div>
          <div class="fs-nearest-item-meta">${escapeHtml(m.address || "Address unavailable")}</div>
          <div class="fs-nearest-item-foot">
            ${status ? `<span class="fs-nearest-status fs-nearest-status--${status}">${status === "open" ? "Open" : "Closed"}</span>` : ""}
            <a class="fs-nearest-item-btn" href="${productsHref}" data-nearest-products-link>View Products</a>
          </div>
          ${String(m.id) === String(recommendedId) ? `<div class="fs-nearest-item-badge">Nearest match</div>` : ""}
        </div>
      `;
    })
    .join("");
}

function updateNearestRecommendation({ market, usedFallback, distanceKm }) {
  if (!mountEl) return;
  const panel = mountEl.querySelector("[data-nearest-recommendation]");
  if (!panel) return;

  if (!market) {
    panel.innerHTML = `
      <div class="fs-nearest-reco-label">Recommended market</div>
      <div class="fs-nearest-reco-title">No nearby market available</div>
      <div class="fs-nearest-reco-meta">We couldn’t find any market with valid map coordinates yet.</div>
    `;
    return;
  }

  const mname = escapeHtml(market.market_name || market.name || "Market");
  const addr = escapeHtml(market.address || "Address unavailable");
  const reason = usedFallback
    ? "Closest market to the map area (we couldn’t read your device location)."
    : "Closest market to your location.";
  const distLine = Number.isFinite(distanceKm)
    ? distanceKm < 0.05
      ? "~0 km — same area as the pin"
      : `${distanceKm.toFixed(1)} km away`
    : "Distance unavailable";
  const openKnown = typeof market.is_open === "boolean";
  const openHtml = openKnown
    ? market.is_open
      ? `<span class="fs-nearest-reco-pill fs-nearest-reco-pill--open">Open now</span>`
      : `<span class="fs-nearest-reco-pill fs-nearest-reco-pill--closed">Closed now</span>`
    : `<span class="fs-nearest-reco-pill fs-nearest-reco-pill--na">Hours N/A</span>`;
  const mid = encodeURIComponent(String(market.id || ""));
  const productsHref = `/farmscout_online/app/products?market=${mid}`;

  panel.innerHTML = `
    <div class="fs-nearest-reco-label">Recommended market</div>
    <div class="fs-nearest-reco-title">${mname}</div>
    <p class="fs-nearest-reco-reason">${escapeHtml(reason)}</p>
    <div class="fs-nearest-reco-stats">
      <span class="fs-nearest-reco-dist">${escapeHtml(distLine)}</span>
      ${openHtml}
    </div>
    <p class="fs-nearest-reco-address">${addr}</p>
    <a class="fs-nearest-reco-btn" href="${productsHref}">View Products</a>
  `;
}

function getNearestInfoWindowHtml(market) {
  const chips = [
    Number.isFinite(Number(market.distance_km)) ? `${Number(market.distance_km).toFixed(1)} km away` : "",
    market.product_count ? `${Number(market.product_count)} products` : "Products unavailable",
    typeof market.is_open === "boolean" ? (market.is_open ? "Open today" : "Closed") : "",
  ].filter(Boolean);

  return `
    <div class="fs-gm-info">
      <div class="fs-gm-info__title">${escapeHtml(market.market_name || market.name || "Market")}</div>
      <div class="fs-gm-info__meta">${escapeHtml(market.address || "Address unavailable")}</div>
      <div class="fs-gm-info__chips">
        ${chips.map((chip) => `<span class="fs-gm-info__chip">${escapeHtml(chip)}</span>`).join("")}
      </div>
      <a class="fs-gm-info__link" href="/farmscout_online/app/products?market=${encodeURIComponent(String(market.id || ""))}">View Products</a>
    </div>
  `;
}

async function getUserCoordinates() {
  return await new Promise((resolve) => {
    if (!navigator.geolocation) return resolve(null);
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 9000, maximumAge: 30_000 }
    );
  });
}

async function initNearestMarketMap({ markets }) {
  if (!mountEl) return;
  const mapEl = mountEl.querySelector("#nearestMap");
  if (!mapEl) return;

  try {
    await loadGoogleMapsApi();
  } catch {
    mapEl.innerHTML = `<div class="fs-nearest-map-empty">Google Maps could not load right now.</div>`;
    updateNearestRecommendation({ market: null, usedFallback: false, distanceKm: NaN });
    return null;
  }

  const defaults = getNearestDefaults();
  const userCoords = await getUserCoordinates();
  const referencePoint = userCoords || { lat: defaults.lat, lng: defaults.lng };
  const usedFallback = !userCoords;
  const zoom = usedFallback ? Math.max(12, Number(defaults.zoom || 11)) : 14;

  const validMarkets = (markets || [])
    .map((m) => {
      const lat = Number(m.latitude || 0);
      const lng = Number(m.longitude || 0);
      return {
        ...m,
        latitude: lat,
        longitude: lng,
        distance_km: haversineKm(referencePoint.lat, referencePoint.lng, lat, lng),
      };
    })
    .filter((m) => Number.isFinite(m.latitude) && Number.isFinite(m.longitude) && m.latitude !== 0 && m.longitude !== 0)
    .sort((a, b) => {
      const da = Number.isFinite(a.distance_km) ? a.distance_km : Number.POSITIVE_INFINITY;
      const db = Number.isFinite(b.distance_km) ? b.distance_km : Number.POSITIVE_INFINITY;
      return da - db;
    });

  const recommended = validMarkets[0] || null;
  updateNearestRecommendation({
    market: recommended,
    usedFallback,
    distanceKm: recommended?.distance_km,
  });

  const map = new google.maps.Map(mapEl, {
    center: referencePoint,
    zoom,
    mapTypeId: google.maps.MapTypeId.ROADMAP,
    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: true,
  });

  const bounds = new google.maps.LatLngBounds();
  bounds.extend(referencePoint);

  const infoWindow = new google.maps.InfoWindow();
  const markersById = new Map();

  const userMarker = new google.maps.Marker({
    map,
    position: referencePoint,
    title: usedFallback ? "Default nearby area" : "You are here",
    icon: {
      url: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png",
    },
  });
  userMarker.addListener("click", () => {
    infoWindow.setContent(`<div class="fs-gm-info"><div class="fs-gm-info__title">${usedFallback ? "Default nearby area" : "You are here"}</div><div class="fs-gm-info__meta">${usedFallback ? "Location permission was unavailable, so we used the default market area." : "We used your current location to suggest the nearest market."}</div></div>`);
    infoWindow.open({ anchor: userMarker, map });
  });

  validMarkets.forEach((market) => {
    const isRecommended = recommended && String(recommended.id) === String(market.id);
    const marker = new google.maps.Marker({
      map,
      position: { lat: market.latitude, lng: market.longitude },
      title: market.market_name || market.name || "Market",
      animation: isRecommended ? google.maps.Animation.DROP : undefined,
      icon: {
        url: isRecommended
          ? "https://maps.google.com/mapfiles/ms/icons/green-dot.png"
          : "https://maps.google.com/mapfiles/ms/icons/red-dot.png",
      },
    });

    marker.addListener("click", () => {
      infoWindow.setContent(getNearestInfoWindowHtml(market));
      infoWindow.open({ anchor: marker, map });
      const listEls = mountEl.querySelectorAll("[data-nearest-market-id]");
      listEls.forEach((el) => {
        const on = el.getAttribute("data-nearest-market-id") === String(market.id);
        el.classList.toggle("is-active", on);
        if (on) {
          try {
            el.scrollIntoView({ block: "nearest", behavior: "smooth" });
          } catch {
            el.scrollIntoView({ block: "nearest" });
          }
        }
      });
    });

    markersById.set(String(market.id), marker);
    bounds.extend({ lat: market.latitude, lng: market.longitude });
  });

  // Keep the map focused near the user's location (or default area).
  // We still render all markets, but avoid zooming out to fit all markers.
  map.setCenter(referencePoint);
  map.setZoom(zoom);

  const aside = mountEl.querySelector(".fs-nearest-list");
  if (aside) {
    aside.innerHTML = buildNearestMarketRows(validMarkets, recommended?.id);
    aside.querySelectorAll("[data-nearest-products-link]").forEach((link) => {
      link.addEventListener("click", (e) => e.stopPropagation());
    });
    aside.querySelectorAll("[data-nearest-market-id]").forEach((el) => {
      const openRow = () => {
        const marketId = el.getAttribute("data-nearest-market-id") || "";
        const marker = markersById.get(marketId);
        if (marker) {
          map.panTo(marker.getPosition());
          google.maps.event.trigger(marker, "click");
        }
      };
      el.addEventListener("click", openRow);
      el.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          openRow();
        }
      });
    });
  }

  if (recommended) {
    const marker = markersById.get(String(recommended.id));
    if (marker) {
      window.setTimeout(() => {
        infoWindow.setContent(getNearestInfoWindowHtml(recommended));
        infoWindow.open({ anchor: marker, map });
      }, 280);
    }
  }

  nearestMapBound = true;
  return {
    user: userCoords,
    recommended,
    usedFallback,
  };
}

function haversineKm(lat1, lon1, lat2, lon2) {
  if (!Number.isFinite(lat1) || !Number.isFinite(lon1) || !Number.isFinite(lat2) || !Number.isFinite(lon2)) return NaN;
  if (lat2 === 0 || lon2 === 0) return NaN;
  const R = 6371;
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLon = ((lon2 - lon1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

export function initRouter({ mountEl: el }) {
  mountEl = el;
  try {
    homeIntroPlayed = window.sessionStorage.getItem("fsHomeIntroPlayed") === "1";
  } catch {}

  // Fix Safari/Chrome bfcache issues after logout/login + back button:
  // - Old DOM (e.g. "Featured" link) can reappear
  // - Nav can become unclickable due to stale overlay/page state
  // Strategy: on bfcache restore, reload to a clean, current build.
  window.addEventListener("pageshow", (e) => {
    if (e && e.persisted) {
      window.location.reload();
      return;
    }
    // Also clear any stuck transition state and re-sync auth label.
    document.body.classList.remove("fs-transitioning");
    syncAuthNav();
  });

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      document.body.classList.remove("fs-transitioning");
      syncAuthNav();
    }
  });

  // intercept internal SPA links inside app
  document.addEventListener(
    "click",
    (e) => {
      if (e.defaultPrevented) return;
      if (e.button !== 0) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

      const alertBtn = e.target.closest?.(".js-price-alert");
      if (alertBtn) {
        const disabled = alertBtn.getAttribute("aria-disabled") === "true";
        if (disabled) return;
        e.preventDefault();
        e.stopPropagation();
        const pid = Number(alertBtn.getAttribute("data-product-id") || 0);
        if (!Number.isFinite(pid) || pid <= 0) return;

        (async () => {
          try {
            const res = await fetch("/farmscout_online/api/create_price_alert.php", {
              method: "POST",
              credentials: "include",
              headers: { "Content-Type": "application/json", Accept: "application/json" },
              body: JSON.stringify({ product_id: pid, alert_type: "change", target_price: 0 }),
            });
            if (res.status === 401) {
              window.location.href = "/farmscout_online/login.php";
              return;
            }
          } catch {}

          // Always take user to the modern Account page view of alerts.
          window.location.href = "/farmscout_online/user-account.php?section=price_alerts&message=" + encodeURIComponent("Price alert added.");
        })();
        return;
      }

      const a = e.target.closest("a");
      if (!a) return;
      if (!isInternalLink(a)) return;

      const rawHref = (a.getAttribute("href") || "").trim();
      if (rawHref === "#") return;

      const url = new URL(a.href, window.location.href);
      const path = normalizePath(url.pathname);

      // Only handle routes under the SPA base
      const allowed = ["/", "/markets", "/nearest", "/products", "/alerts", "/account"];
      if (!allowed.includes(path)) return;

      const currentPath = normalizePath(window.location.pathname);
      if (url.hash && url.hash.length > 1 && path === currentPath && allowed.includes(path)) {
        e.preventDefault();
        const target = document.querySelector(url.hash);
        const smooth = !window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        target?.scrollIntoView({ behavior: smooth ? "smooth" : "auto", block: "start" });
        try {
          history.pushState({}, "", `${window.location.pathname}${url.search}${url.hash}`);
        } catch {}
        return;
      }

      if (a.hasAttribute("data-fs-scroll-home-top") && path === "/") {
        e.preventDefault();
        const smooth = !window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        window.scrollTo({ top: 0, behavior: smooth ? "smooth" : "auto" });
        return;
      }

      e.preventDefault();
      const isAnimatedCard = a.hasAttribute("data-card-animate");
      if (isAnimatedCard) {
        const card = a.closest("[data-card-animate]");
        pulseCard(card);
        window.setTimeout(() => {
          navigateWithTransition(path + url.search + url.hash);
        }, 120);
        return;
      }

      navigateWithTransition(path + url.search + url.hash);
    },
    { capture: true }
  );

  // Intercept navbar search submits (SPA navigation).
  document.addEventListener(
    "submit",
    (e) => {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (!form.classList.contains("fs-sf-search")) return;
      e.preventDefault();
      const fd = new FormData(form);
      const q = String(fd.get("q") || "").trim();
      if (!q) return;
      navigateWithTransition(`/products?q=${encodeURIComponent(q)}`);
    },
    true
  );

  window.addEventListener("popstate", async () => {
    const { runSvgPathTransition } = await import("./transitions/svgPathTransition.js");
    await runSvgPathTransition("cover");
    render();
    await runSvgPathTransition("uncover");
  });

  render();
  try {
    if (homeIntroPlayed) window.sessionStorage.setItem("fsHomeIntroPlayed", "1");
  } catch {}
}


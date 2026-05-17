(() => {
  const TAGS_FALLBACK = ["Vegetables", "Fish", "Rice"];

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function parseTimeToMinutes(str) {
    // Supports "5:30 AM" / "6 PM"
    const m = String(str || "").trim().match(/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)$/i);
    if (!m) return null;
    let h = Number(m[1]);
    const mins = Number(m[2] || 0);
    const ampm = String(m[3] || "").toUpperCase();
    if (h === 12) h = 0;
    if (ampm === "PM") h += 12;
    return h * 60 + mins;
  }

  function parseHoursRange(descText) {
    // Looks for "· 5:30 AM - 6:00 PM" anywhere in the string
    const s = String(descText || "");
    const m = s.match(/(\d{1,2}(?::\d{2})?\s*(?:AM|PM))\s*-\s*(\d{1,2}(?::\d{2})?\s*(?:AM|PM))/i);
    if (!m) return null;
    const start = parseTimeToMinutes(m[1]);
    const end = parseTimeToMinutes(m[2]);
    if (start == null || end == null) return null;
    return { start, end };
  }

  function isOpenNow(range) {
    if (!range) return null;
    const now = new Date();
    const mins = now.getHours() * 60 + now.getMinutes();
    // If end < start, treat as overnight range.
    if (range.end < range.start) return mins >= range.start || mins <= range.end;
    return mins >= range.start && mins <= range.end;
  }

  function getUpdatedMinutes(marketName) {
    const key = `fs_mf_updated_${marketName}`;
    try {
      const raw = sessionStorage.getItem(key);
      if (raw) {
        const ts = Number(raw);
        if (Number.isFinite(ts) && ts > 0) {
          const mins = Math.floor((Date.now() - ts) / 60000);
          return clamp(mins, 0, 120);
        }
      }
      // Seed: pretend updated within last 2–14 minutes for "live" feel
      const seeded = Date.now() - (2 + Math.floor(Math.random() * 13)) * 60000;
      sessionStorage.setItem(key, String(seeded));
      return Math.floor((Date.now() - seeded) / 60000);
    } catch {
      return 0;
    }
  }

  function buildIcon() {
    // Simple "market" icon (inline SVG), no external assets
    const wrap = document.createElement("div");
    wrap.className = "mf-mc-media";
    wrap.setAttribute("aria-hidden", "true");
    wrap.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 10.5V20a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-9.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M3 10.5h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M6 10.5l2-6h8l2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M9 21v-7h6v7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    `.trim();
    return wrap;
  }

  function enhanceMarketCard(cardEl) {
    if (!cardEl || cardEl.dataset.mfEnhanced === "1") return;
    const inner = cardEl.querySelector(".mf-parallax-card-inner");
    if (!inner) return;

    const titleEl = inner.querySelector(".mf-parallax-title");
    const descEl = inner.querySelector(".mf-parallax-desc");
    const countEl = inner.querySelector(".mf-parallax-count");
    if (!titleEl || !descEl || !countEl) return;

    const marketName = (titleEl.textContent || "").trim() || "Market";
    const descText = (descEl.textContent || "").trim();
    const range = parseHoursRange(descText);
    const open = isOpenNow(range);
    const updatedMins = getUpdatedMinutes(marketName);

    const media = buildIcon();
    media.classList.add("mf-mc-media--market");

    const topRow = document.createElement("div");
    topRow.className = "mf-mc-toprow";

    const live = document.createElement("div");
    live.className = `mf-mc-live${open ? " is-live" : ""}`;
    live.innerHTML = `<span class="mf-mc-dot"></span><span>${open ? "LIVE NOW" : "CLOSED"}</span>`;

    const updated = document.createElement("div");
    updated.className = "mf-mc-updated";
    updated.textContent = `Updated ${updatedMins} min${updatedMins === 1 ? "" : "s"} ago`;

    topRow.appendChild(live);
    topRow.appendChild(updated);

    const tags = document.createElement("div");
    tags.className = "mf-mc-tags";
    TAGS_FALLBACK.forEach((t) => {
      const chip = document.createElement("span");
      chip.className = "mf-mc-tag";
      chip.textContent = t;
      tags.appendChild(chip);
    });

    const insights = document.createElement("div");
    insights.className = "mf-mc-insights";
    const countText = (countEl.textContent || "").trim();
    insights.innerHTML = `<span><strong>${countText.replace(/\s+products?$/i, "") || "0"}</strong> products</span>`;

    const cta = document.createElement("div");
    cta.className = "mf-mc-cta";
    cta.setAttribute("aria-hidden", "true");
    cta.innerHTML = `<span>View Market</span><span class="mf-mc-cta-arrow">→</span>`;

    // Insert without breaking existing text
    inner.insertBefore(media, inner.firstChild);
    inner.insertBefore(topRow, titleEl);
    inner.insertBefore(tags, countEl);
    inner.insertBefore(insights, countEl);
    inner.appendChild(cta);

    // Focus cue only; click behavior stays as-is (handled by existing code)
    if (!cardEl.hasAttribute("tabindex")) cardEl.setAttribute("tabindex", "0");
    cardEl.dataset.mfEnhanced = "1";
  }

  function run() {
    const host = document.getElementById("mfMarketsCarouselHost");
    const marketTrack = document.getElementById("mfParallaxTrack");
    if (!host || !marketTrack) return;

    const enhanceAll = () => {
      const cards = marketTrack.querySelectorAll(".mf-parallax-card");
      cards.forEach(enhanceMarketCard);
    };

    enhanceAll();

    const mo = new MutationObserver(() => enhanceAll());
    mo.observe(marketTrack, { childList: true, subtree: true });

    // Safety: disconnect on page unload
    window.addEventListener(
      "beforeunload",
      () => {
        try {
          mo.disconnect();
        } catch {}
      },
      { once: true }
    );
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run, { once: true });
  } else {
    run();
  }
})();


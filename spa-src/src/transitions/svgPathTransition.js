let overlayEl = null;
let overlayPathEl = null;
let isAnimating = false;

// From Codrops "021-svg-path-page-transition-vertical"
// Edit the paths here: https://yqnn.github.io/svg-path-editor/
const paths = {
  step1: {
    unfilled: "M 0 100 V 100 Q 50 100 100 100 V 100 z",
    inBetween: {
      curve1: "M 0 100 V 50 Q 50 0 100 50 V 100 z",
      curve2: "M 0 100 V 50 Q 50 100 100 50 V 100 z",
    },
    filled: "M 0 100 V 0 Q 50 0 100 0 V 100 z",
  },
  step2: {
    filled: "M 0 0 V 100 Q 50 100 100 100 V 0 z",
    inBetween: {
      curve1: "M 0 0 V 50 Q 50 0 100 50 V 0 z",
      curve2: "M 0 0 V 50 Q 50 100 100 50 V 0 z",
    },
    unfilled: "M 0 0 V 0 Q 50 0 100 0 V 0 z",
  },
};

function ensureOverlay() {
  if (overlayEl && overlayPathEl) return;

  overlayEl = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  overlayEl.setAttribute("class", "fs-svg-overlay");
  overlayEl.setAttribute("width", "100%");
  overlayEl.setAttribute("height", "100%");
  overlayEl.setAttribute("viewBox", "0 0 100 100");
  overlayEl.setAttribute("preserveAspectRatio", "none");

  overlayPathEl = document.createElementNS("http://www.w3.org/2000/svg", "path");
  overlayPathEl.setAttribute("class", "fs-svg-overlay__path");
  overlayPathEl.setAttribute("vector-effect", "non-scaling-stroke");
  overlayPathEl.setAttribute("d", paths.step1.unfilled);

  overlayEl.appendChild(overlayPathEl);
  document.body.appendChild(overlayEl);
}

async function getGsap() {
  const mod = await import("gsap");
  return mod.gsap || mod.default || mod;
}

/**
 * Runs the SVG overlay transition.
 * - phase "cover": animate overlay to cover the page, resolve when fully covered
 * - phase "uncover": animate overlay away, resolve when gone
 */
export async function runSvgPathTransition(phase) {
  if (window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches) {
    document.body.classList.remove("fs-transitioning");
    return;
  }
  ensureOverlay();
  if (!overlayPathEl) return;
  if (isAnimating) return;
  isAnimating = true;

  const gsap = await getGsap();

  return new Promise((resolve) => {
    const tl = gsap.timeline({
      onComplete: () => {
        isAnimating = false;
        if (phase === "uncover") document.body.classList.remove("fs-transitioning");
        resolve();
      },
    });

    if (phase === "cover") {
      document.body.classList.add("fs-transitioning");
      tl.set(overlayPathEl, { attr: { d: paths.step1.unfilled } })
        .to(
          overlayPathEl,
          { duration: 0.8, ease: "power4.in", attr: { d: paths.step1.inBetween.curve1 } },
          0
        )
        .to(overlayPathEl, {
          duration: 0.2,
          ease: "power1",
          attr: { d: paths.step1.filled },
          onComplete: () => resolve(), // page swap moment: fully covered
        });
      // keep animating to a stable "filled top" state so uncover can start cleanly
      tl.set(overlayPathEl, { attr: { d: paths.step2.filled } });
      return;
    }

    // uncover
    tl.set(overlayPathEl, { attr: { d: paths.step2.filled } })
      .to(overlayPathEl, {
        duration: 0.2,
        ease: "sine.in",
        attr: { d: paths.step2.inBetween.curve1 },
      })
      .to(overlayPathEl, {
        duration: 1,
        ease: "power4",
        attr: { d: paths.step2.unfilled },
      });
  });
}


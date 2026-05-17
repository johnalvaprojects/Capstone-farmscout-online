export function renderRedirectPage({ label, href }) {
  const safeHref = href || "/farmscout_online/app/";
  const safeLabel = label || "Redirecting…";
  return `
    <div class="fs-redirect">
      <div class="fs-redirect-card">
        <div class="fs-redirect-title">${safeLabel}</div>
        <a class="fs-redirect-link" href="${safeHref}">Continue</a>
      </div>
    </div>
  `;
}


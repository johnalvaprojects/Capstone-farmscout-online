/**
 * Market Finder fullscreen slider — HISTORY (price history), bell (price alert), chip → main price
 */
(function () {
  "use strict";

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

  function loadCss() {
    if (document.getElementById("fs-mf-product-ui-css")) return;
    var link = document.createElement("link");
    link.id = "fs-mf-product-ui-css";
    link.rel = "stylesheet";
    link.href = api("/app/css/fs-mf-product-ui.css");
    document.head.appendChild(link);
  }

  function ensureHistoryModal() {
    loadCss();
    if (document.getElementById("fsMfHistoryModal")) return;
    var wrap = document.createElement("div");
    wrap.id = "fsMfHistoryModal";
    wrap.className = "fs-mf-ui-modal";
    wrap.setAttribute("aria-hidden", "true");
    wrap.innerHTML =
      '<div class="fs-mf-ui-modal__box" role="dialog" aria-modal="true" aria-labelledby="fsMfHistTitle">' +
      '<div class="fs-mf-ui-modal__head">' +
      '<h2 id="fsMfHistTitle">Price history</h2>' +
      '<button type="button" class="fs-mf-ui-modal__close" data-fs-mf-hist-close aria-label="Close">&times;</button>' +
      "</div>" +
      '<div class="fs-mf-ui-modal__body" id="fsMfHistBody"></div>' +
      "</div>";
    document.body.appendChild(wrap);
    wrap.addEventListener("click", function (e) {
      if (e.target === wrap) closeHistory();
    });
    wrap.querySelector("[data-fs-mf-hist-close]").addEventListener("click", closeHistory);
  }

  function closeHistory() {
    var w = document.getElementById("fsMfHistoryModal");
    if (!w) return;
    w.classList.remove("fs-mf-ui-modal--open");
    w.setAttribute("aria-hidden", "true");
  }

  function openHistory(productId, productName) {
    ensureHistoryModal();
    var body = document.getElementById("fsMfHistBody");
    var modal = document.getElementById("fsMfHistoryModal");
    document.getElementById("fsMfHistTitle").textContent = "Price history — " + (productName || "Product");
    body.innerHTML = '<p class="fs-mf-ui-msg">Loading…</p>';
    modal.classList.add("fs-mf-ui-modal--open");
    modal.setAttribute("aria-hidden", "false");

    fetch(api("/api/get_price_history.php?product_id=" + encodeURIComponent(String(productId)) + "&days=90"), {
      credentials: "include",
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.success) {
          body.innerHTML =
            '<p class="fs-mf-ui-msg fs-mf-ui-msg--err">' +
            (data && data.message ? String(data.message) : "Could not load price history.") +
            "</p>";
          return;
        }
        var stats = data.stats || {};
        var rows = data.price_data || [];
        var statsHtml =
          '<div class="fs-mf-ui-stats">' +
          "<div><span>Min</span><strong>₱" +
          Number(stats.min_price || 0).toFixed(2) +
          "</strong></div>" +
          "<div><span>Avg</span><strong>₱" +
          Number(stats.avg_price || 0).toFixed(2) +
          "</strong></div>" +
          "<div><span>Max</span><strong>₱" +
          Number(stats.max_price || 0).toFixed(2) +
          "</strong></div>" +
          "</div>";

        if (!rows.length) {
          body.innerHTML =
            statsHtml +
            '<p class="fs-mf-ui-msg">No recorded changes yet. Current price: ₱' +
            Number(stats.current_price || 0).toFixed(2) +
            "</p>";
          return;
        }

        var table =
          '<div class="fs-mf-ui-table-wrap"><table class="fs-mf-ui-table"><thead><tr><th>Date</th><th>Price</th></tr></thead><tbody>';
        rows.forEach(function (row) {
          table +=
            "<tr><td>" +
            String(row.formatted_date || row.date || "—") +
            "</td><td>₱" +
            Number(row.price || 0).toFixed(2) +
            "</td></tr>";
        });
        table += "</tbody></table></div>";
        body.innerHTML = statsHtml + table;
      })
      .catch(function () {
        body.innerHTML =
          '<p class="fs-mf-ui-msg fs-mf-ui-msg--err">Network error. Please try again.</p>';
      });
  }

  function ensureAlertModal() {
    loadCss();
    if (document.getElementById("fsMfAlertModal")) return;
    var wrap = document.createElement("div");
    wrap.id = "fsMfAlertModal";
    wrap.className = "fs-mf-ui-modal";
    wrap.setAttribute("aria-hidden", "true");
    wrap.innerHTML =
      '<div class="fs-mf-ui-modal__box" role="dialog" aria-modal="true" aria-labelledby="fsMfAlertTitle">' +
      '<div class="fs-mf-ui-modal__head">' +
      '<h2 id="fsMfAlertTitle">Price alert</h2>' +
      '<button type="button" class="fs-mf-ui-modal__close" data-fs-mf-alert-close aria-label="Close">&times;</button>' +
      "</div>" +
      '<div class="fs-mf-ui-modal__body">' +
      '<p id="fsMfAlertLead" class="fs-mf-ui-msg"></p>' +
      '<input type="hidden" id="fsMfAlertProductId" />' +
      '<div class="fs-mf-ui-modal__group">' +
      '<label for="fsMfAlertTarget">Notify me when price is at or below (₱)</label>' +
      '<input type="number" id="fsMfAlertTarget" step="0.01" min="0.01" />' +
      "</div>" +
      '<div id="fsMfAlertMsg" class="fs-mf-ui-msg"></div>' +
      '<div class="fs-mf-ui-modal__actions">' +
      '<button type="button" class="fs-mf-ui-btn-secondary" data-fs-mf-alert-cancel>CANCEL</button>' +
      '<button type="button" class="fs-mf-ui-btn-primary" id="fsMfAlertSubmit">SET ALERT</button>' +
      "</div>" +
      "</div>" +
      "</div>";
    document.body.appendChild(wrap);
    wrap.addEventListener("click", function (e) {
      if (e.target === wrap) closeAlert();
    });
    wrap.querySelector("[data-fs-mf-alert-close]").addEventListener("click", closeAlert);
    wrap.querySelector("[data-fs-mf-alert-cancel]").addEventListener("click", closeAlert);
    document.getElementById("fsMfAlertSubmit").addEventListener("click", submitAlert);
  }

  function closeAlert() {
    var w = document.getElementById("fsMfAlertModal");
    if (!w) return;
    w.classList.remove("fs-mf-ui-modal--open");
    w.setAttribute("aria-hidden", "true");
    document.getElementById("fsMfAlertMsg").innerHTML = "";
  }

  function parsePrice(str) {
    if (!str) return 0;
    var n = parseFloat(String(str).replace(/[₱P$,\s]/g, ""));
    return isNaN(n) ? 0 : n;
  }

  function openAlertFromCard(card) {
    var pid = card.getAttribute("data-product-id");
    if (!pid) return;
    fetch(api("/api/auth_session.php"), { credentials: "include" })
      .then(function (r) {
        return r.json();
      })
      .then(function (auth) {
        if (!auth || !auth.logged_in) {
          var here = window.location.pathname + window.location.search + window.location.hash;
          window.location.href =
            api("/login.php") +
            "?redirect=" +
            encodeURIComponent(here) +
            "&message=" +
            encodeURIComponent("Log in to set price alerts.");
          return;
        }
        ensureAlertModal();
        var name = card.getAttribute("data-product-name") || "Product";
        var priceStr = card.getAttribute("data-product-price") || "";
        var def = parsePrice(priceStr);
        document.getElementById("fsMfAlertProductId").value = pid;
        document.getElementById("fsMfAlertLead").textContent =
          "Get notified for " + name + " when the price drops to your target.";
        var inp = document.getElementById("fsMfAlertTarget");
        inp.value = def > 0 ? def.toFixed(2) : "";
        document.getElementById("fsMfAlertMsg").innerHTML = "";
        var modal = document.getElementById("fsMfAlertModal");
        modal.classList.add("fs-mf-ui-modal--open");
        modal.setAttribute("aria-hidden", "false");
      })
      .catch(function () {
        window.location.href =
          api("/login.php") +
          "?redirect=" +
          encodeURIComponent(window.location.pathname + window.location.search + window.location.hash);
      });
  }

  function submitAlert() {
    var msg = document.getElementById("fsMfAlertMsg");
    var btn = document.getElementById("fsMfAlertSubmit");
    msg.innerHTML = "";
    btn.disabled = true;

    var productId = document.getElementById("fsMfAlertProductId").value;
    var target = parseFloat(document.getElementById("fsMfAlertTarget").value || "0");

    if (!productId || target <= 0) {
      msg.innerHTML = '<span class="fs-mf-ui-msg--err">Enter a valid target price.</span>';
      btn.disabled = false;
      return;
    }

    fetch(api("/api/get_csrf_token.php"), { credentials: "include" })
      .then(function (r) {
        return r.json();
      })
      .then(function (tok) {
        if (!tok || !tok.success || !tok.csrf_token) {
          msg.innerHTML = '<span class="fs-mf-ui-msg--err">Could not load security token.</span>';
          btn.disabled = false;
          return;
        }
        return fetch(api("/api/create_price_alert.php"), {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "include",
          body: JSON.stringify({
            csrf_token: tok.csrf_token,
            product_id: parseInt(productId, 10),
            target_price: target,
            alert_type: "below",
          }),
        }).then(function (r) {
          return r.json();
        });
      })
      .then(function (res) {
        if (!res) return;
        if (res.success) {
          msg.textContent = res.message || "Alert saved.";
          setTimeout(closeAlert, 1600);
        } else {
          msg.innerHTML =
            '<span class="fs-mf-ui-msg--err">' + (res.message || "Request failed.") + "</span>";
        }
      })
      .catch(function () {
        msg.innerHTML = '<span class="fs-mf-ui-msg--err">Network error.</span>';
      })
      .finally(function () {
        btn.disabled = false;
      });
  }

  document.addEventListener(
    "click",
    function (e) {
      var hist = e.target.closest && e.target.closest(".js-mf-history");
      if (hist) {
        e.preventDefault();
        e.stopPropagation();
        var card =
          hist.closest(".mf-osmo-prod-card") || hist.closest(".mf-mm-product-card");
        if (!card) return;
        var id = card.getAttribute("data-product-id");
        var name = card.getAttribute("data-product-name") || "";
        if (!id) return;
        openHistory(id, name);
        return;
      }

      var bell = e.target.closest && e.target.closest(".js-mf-price-alert");
      if (bell) {
        e.preventDefault();
        e.stopPropagation();
        var cardB =
          bell.closest(".mf-osmo-prod-card") || bell.closest(".mf-mm-product-card");
        if (!cardB) return;
        openAlertFromCard(cardB);
        return;
      }

      var chip = e.target.closest && e.target.closest(".mf-osmo-prod-chip");
      if (chip && chip.closest(".mf-osmo-prod-card")) {
        var cardC = chip.closest(".mf-osmo-prod-card");
        var text = chip.textContent || "";
        var m = text.match(/₱[\d.,]+/);
        if (m) {
          var priceEl = cardC.querySelector(".mf-osmo-prod-price");
          if (priceEl) priceEl.textContent = m[0];
        }
      }

      var chipMm = e.target.closest && e.target.closest(".mf-mm-prod-chip");
      if (chipMm && chipMm.closest(".mf-mm-product-card")) {
        var cardM = chipMm.closest(".mf-mm-product-card");
        var textM = chipMm.textContent || "";
        var m2 = textM.match(/₱[\d.,]+/);
        if (m2) {
          var pe = cardM.querySelector(".mf-mm-prod-price");
          if (pe) pe.textContent = m2[0];
        }
      }
    },
    true
  );
})();

/**
 * Fullscreen Market Finder (SPA) — RESERVE button → same flow as market-finder.php
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

  function ensureModal() {
    if (document.getElementById("fsReservationModal")) return;

    var link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = api("/app/css/fs-reservation-modal.css?v=20260213");
    document.head.appendChild(link);

    var wrap = document.createElement("div");
    wrap.id = "fsReservationModal";
    wrap.className = "fs-res-modal";
    wrap.setAttribute("aria-hidden", "true");
    wrap.innerHTML =
      '<div class="fs-res-modal__box" role="dialog" aria-modal="true" aria-labelledby="fsResTitle">' +
      '<div class="fs-res-modal__head">' +
      '<h2 id="fsResTitle">RESERVE PRODUCT</h2>' +
      '<button type="button" class="fs-res-modal__close" id="fsResClose" aria-label="Close">&times;</button>' +
      "</div>" +
      '<div class="fs-res-modal__body">' +
      '<div id="fsResMessage"></div>' +
      '<div id="fsResProductInfo" class="fs-res-modal__product"></div>' +
      '<div id="fsResCartWrap" class="fs-res-modal__cart-wrap">' +
      '<div id="fsResCartWarn" class="fs-res-modal__msg fs-res-modal__msg--err" style="display:none;margin-bottom:8px"></div>' +
      '<div id="fsResCart" class="fs-res-modal__cart" style="display:none">' +
      "<strong>In this reservation</strong>" +
      '<ul id="fsResCartList" class="fs-res-modal__cart-list"></ul>' +
      '<button type="button" class="fs-res-modal__btn-secondary" id="fsResCartClear" style="margin-top:8px">Clear cart</button>' +
      "</div></div>" +
      '<div id="fsResPay" class="fs-res-modal__pay" style="display:none">' +
      '<h3 class="fs-res-modal__pay-title">Proceed to payment</h3>' +
      '<p class="fs-res-modal__pay-sub">Pay cash when you pick up at the market. <strong>GCash checkout</strong> will be added in a future update.</p>' +
      '<div class="fs-res-modal__pay-actions">' +
      '<button type="button" class="fs-res-modal__btn-primary" id="fsPayCash">Pay at Market (Cash)</button>' +
      '<button type="button" class="fs-res-modal__btn-gcash-upcoming" id="fsPayGcash" disabled aria-disabled="true" title="GCash payment isn’t available yet">' +
      '<span class="fs-res-modal__gcash-soon-pill">Coming soon</span>' +
      '<span class="fs-res-modal__gcash-btn-text">Pay with GCash</span>' +
      "</button>" +
      "</div>" +
      '<p class="fs-res-modal__pay-gcash-hint" role="status">GCash feature is coming soon — not available yet.</p>' +
      "</div>" +
      '<form id="fsResForm">' +
      '<input type="hidden" id="fsResProductId" name="product_id" />' +
      '<input type="hidden" id="fsResMarketId" value="" />' +
      '<input type="hidden" id="fsResFarmerId" value="" />' +
      '<input type="hidden" id="fsResCsrf" name="csrf_token" />' +
      '<input type="hidden" id="fsResReservationId" name="reservation_id" />' +
      '<div class="fs-res-modal__group">' +
      '<label for="fsResQty">Quantity</label>' +
      '<input type="number" id="fsResQty" name="quantity" step="0.01" min="0.01" value="1" placeholder="1" required />' +
      "</div>" +
      '<div class="fs-res-modal__group">' +
      '<label for="fsResUnit">Unit</label>' +
      '<select id="fsResUnit" name="unit" required><option value="">Select unit…</option></select>' +
      "</div>" +
      '<div class="fs-res-modal__group">' +
      '<label for="fsResDate">Preferred pickup date</label>' +
      '<input type="date" id="fsResDate" name="preferred_pickup_date" />' +
      "<small>You can reserve up to 30 days in advance</small>" +
      "</div>" +
      '<div class="fs-res-modal__group">' +
      '<label for="fsResTime">Preferred pickup time</label>' +
      '<input type="time" id="fsResTime" name="preferred_pickup_time" />' +
      "</div>" +
      '<div class="fs-res-modal__group">' +
      '<label for="fsResNotes">Notes (optional)</label>' +
      '<textarea id="fsResNotes" name="notes" rows="3" placeholder="Special requests…"></textarea>' +
      "</div>" +
      '<div class="fs-res-modal__actions">' +
      '<button type="button" class="fs-res-modal__btn-secondary" id="fsResCancel">CANCEL</button>' +
      '<button type="button" class="fs-res-modal__btn-secondary" id="fsResAddCart">ADD TO CART</button>' +
      '<button type="submit" class="fs-res-modal__btn-primary" id="fsResSubmit">SUBMIT RESERVATION</button>' +
      "</div>" +
      "</form>" +
      "</div>" +
      "</div>";

    document.body.appendChild(wrap);

    function close() {
      wrap.classList.remove("fs-res-modal--open");
      wrap.setAttribute("aria-hidden", "true");
      document.getElementById("fsResMessage").innerHTML = "";
      var pay = document.getElementById("fsResPay");
      var form = document.getElementById("fsResForm");
      if (pay) pay.style.display = "none";
      if (form) form.style.display = "";
    }

    wrap.addEventListener("click", function (e) {
      if (e.target === wrap) close();
    });
    document.getElementById("fsResClose").addEventListener("click", close);
    document.getElementById("fsResCancel").addEventListener("click", close);

    document.getElementById("fsResForm").addEventListener("submit", function (e) {
      e.preventDefault();
      submitReservation();
    });

    document.getElementById("fsPayCash").addEventListener("click", function () {
      proceedToPayment("cash");
    });

    document.getElementById("fsResAddCart").addEventListener("click", function () {
      addCurrentToCart();
    });
    document.getElementById("fsResCartClear").addEventListener("click", function () {
      clearReservationCart();
      renderCartPanel();
      clearResMessage();
    });
  }

  var FS_RES_CART_KEY = "fsReservationCartV1";
  var FS_RES_CART_MAX = 10;

  function readReservationCart() {
    try {
      var raw = sessionStorage.getItem(FS_RES_CART_KEY);
      if (!raw) return { marketId: 0, farmerId: 0, marketName: "", items: [] };
      var o = JSON.parse(raw);
      if (!o || !Array.isArray(o.items)) return { marketId: 0, farmerId: 0, marketName: "", items: [] };
      return o;
    } catch (e) {
      return { marketId: 0, farmerId: 0, marketName: "", items: [] };
    }
  }

  function writeReservationCart(c) {
    try {
      sessionStorage.setItem(FS_RES_CART_KEY, JSON.stringify(c));
    } catch (e) {}
  }

  function clearReservationCart() {
    try {
      sessionStorage.removeItem(FS_RES_CART_KEY);
    } catch (e) {}
  }

  function resetSubmitBtnLabel() {
    var submitBtn = document.getElementById("fsResSubmit");
    if (!submitBtn) return;
    var c = readReservationCart();
    submitBtn.textContent = c.items.length ? "SUBMIT RESERVATION (" + c.items.length + " items)" : "SUBMIT RESERVATION";
  }

  function updateAddToCartCapState() {
    var addBtn = document.getElementById("fsResAddCart");
    if (!addBtn) return;
    var cart = readReservationCart();
    var atMax = cart.items.length >= FS_RES_CART_MAX;
    addBtn.disabled = atMax;
    addBtn.title = atMax ? "A reservation can include at most 10 products." : "";
  }

  function renderCartPanel() {
    var cartEl = document.getElementById("fsResCart");
    var listEl = document.getElementById("fsResCartList");
    var submitBtn = document.getElementById("fsResSubmit");
    if (!cartEl || !listEl || !submitBtn) return;
    var cart = readReservationCart();
    if (!cart.items.length) {
      cartEl.style.display = "none";
      listEl.innerHTML = "";
      resetSubmitBtnLabel();
      updateAddToCartCapState();
      return;
    }
    cartEl.style.display = "block";
    var html = "";
    cart.items.forEach(function (it, idx) {
      html +=
        '<li class="fs-res-modal__cart-item"><span>' +
        escapeHtml(String(it.product_name || "Product")) +
        " — " +
        escapeHtml(String(it.quantity)) +
        " " +
        escapeHtml(String(it.unit || "")) +
        ' <button type="button" class="fs-res-modal__cart-remove" data-idx="' +
        idx +
        '">×</button></span></li>';
    });
    listEl.innerHTML = html;
    listEl.querySelectorAll(".fs-res-modal__cart-remove").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var i = parseInt(btn.getAttribute("data-idx"), 10);
        var c2 = readReservationCart();
        if (!isNaN(i) && c2.items[i]) {
          c2.items.splice(i, 1);
          if (!c2.items.length) {
            clearReservationCart();
          } else {
            writeReservationCart(c2);
          }
          renderCartPanel();
        }
      });
    });
    resetSubmitBtnLabel();
    updateAddToCartCapState();
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  /**
   * After submit, replace the single-card product strip with everything that was reserved
   * (cart items). Legacy single-product submit leaves #fsResProductInfo unchanged.
   */
  function renderProductInfoSuccessSummary(submittedItems) {
    var el = document.getElementById("fsResProductInfo");
    if (!el || !submittedItems || !submittedItems.length) return;
    if (submittedItems.length === 1) {
      var it = submittedItems[0];
      el.innerHTML =
        '<div class="fs-res-modal__product-name"></div><div class="fs-res-modal__product-price"></div>';
      el.querySelector(".fs-res-modal__product-name").textContent = String(it.product_name || "Product");
      el.querySelector(".fs-res-modal__product-price").textContent =
        String(it.quantity != null ? it.quantity : "").trim() + " " + String(it.unit || "").trim();
      return;
    }
    var html =
      '<div class="fs-res-modal__success-items"><strong class="fs-res-modal__success-items-label">Reserved items</strong><ul class="fs-res-modal__success-items-list">';
    submittedItems.forEach(function (it) {
      html +=
        "<li><span class=\"fs-res-modal__product-name\">" +
        escapeHtml(String(it.product_name || "Product")) +
        "</span> — " +
        escapeHtml(String(it.quantity != null ? it.quantity : "")) +
        " " +
        escapeHtml(String(it.unit || "").trim()) +
        "</li>";
    });
    html += "</ul></div>";
    el.innerHTML = html;
  }

  function addCurrentToCart() {
    var msg = document.getElementById("fsResMessage");
    var warn = document.getElementById("fsResCartWarn");
    if (warn) {
      warn.style.display = "none";
      warn.textContent = "";
    }
    clearResMessage();
    var pid = parseInt(document.getElementById("fsResProductId").value, 10) || 0;
    var qty = parseFloat(document.getElementById("fsResQty").value);
    var unit = (document.getElementById("fsResUnit").value || "").trim();
    var mid = parseInt(document.getElementById("fsResMarketId").value, 10) || 0;
    var fid = parseInt(document.getElementById("fsResFarmerId").value, 10) || 0;
    var nameEl = document.querySelector("#fsResProductInfo .fs-res-modal__product-name");
    var pname = nameEl ? nameEl.textContent.trim() : "Product";
    if (!validatePickup(true)) return;
    if (pid <= 0 || !qty || qty <= 0 || !unit) {
      showResError("Enter quantity and unit before adding to cart.");
      return;
    }
    var cart = readReservationCart();
    if (cart.items.length) {
      if (mid && cart.marketId && mid !== cart.marketId) {
        showResError(
          "This product is from a different market. Finish or clear your cart first — one reservation is for one market only."
        );
        return;
      }
      if (fid && cart.farmerId && fid !== cart.farmerId) {
        showResError(
          "This product is from a different seller. Finish or clear your cart first — one reservation is for one seller only."
        );
        return;
      }
    } else {
      cart.marketId = mid;
      cart.farmerId = fid;
      var wrapM = document.getElementById("fsReservationModal");
      cart.marketName = (wrapM && wrapM.getAttribute("data-fs-market-name")) || "";
    }
    if (cart.items.length >= FS_RES_CART_MAX) {
      showResError("A reservation can include at most 10 products. Submit this cart or remove a line before adding more.");
      return;
    }
    cart.items.push({ product_id: pid, quantity: qty, unit: unit, product_name: pname });
    writeReservationCart(cart);
    renderCartPanel();
    if (msg) {
      msg.innerHTML = '<div class="fs-res-modal__msg fs-res-modal__msg--ok">Added to your reservation cart.</div>';
    }
  }

  function parsePrice(str) {
    if (!str) return 0;
    var cleaned = String(str).replace(/[₱P$,\s]/g, "").trim();
    var n = parseFloat(cleaned);
    return isNaN(n) ? 0 : n;
  }

  function openModal(card, csrfToken) {
    ensureModal();
    var wrap = document.getElementById("fsReservationModal");
    var productId = card.getAttribute("data-product-id") || "";
    var productName = card.getAttribute("data-product-name") || "Product";
    var productPrice = card.getAttribute("data-product-price") || "0";
    var defaultUnit =
      (card.querySelector(".mf-osmo-prod-unit") && card.querySelector(".mf-osmo-prod-unit").textContent) ||
      card.getAttribute("data-product-unit") ||
      "kg";
    defaultUnit = defaultUnit.replace(/^per\s+/i, "").trim();

    var numericPrice = parsePrice(productPrice);

    document.getElementById("fsResProductInfo").innerHTML =
      '<div class="fs-res-modal__product-name"></div><div class="fs-res-modal__product-price"></div>';
    document.querySelector("#fsResProductInfo .fs-res-modal__product-name").textContent = productName;
    document.querySelector("#fsResProductInfo .fs-res-modal__product-price").textContent =
      "₱" + numericPrice.toFixed(2);

    var unitOptions = [];
    card.querySelectorAll(".product-unit-options .product-unit-option").forEach(function (opt) {
      var text = opt.textContent.trim();
      var match = text.match(/^(.+?)\s*-\s*₱?([\d.]+)/);
      if (match) {
        unitOptions.push({ label: match[1].trim(), price: parseFloat(match[2]) });
      }
    });

    if (unitOptions.length === 0) {
      unitOptions.push({ label: defaultUnit, price: numericPrice });
    }

    var unitSelect = document.getElementById("fsResUnit");
    unitSelect.innerHTML = '<option value="">Select unit…</option>';
    unitOptions.forEach(function (o) {
      var el = document.createElement("option");
      el.value = o.label;
      el.textContent = o.label + " — ₱" + o.price.toFixed(2);
      unitSelect.appendChild(el);
    });

    var today = new Date().toISOString().split("T")[0];
    var maxD = new Date();
    maxD.setDate(maxD.getDate() + 30);
    var maxStr = maxD.toISOString().split("T")[0];
    var dateEl = document.getElementById("fsResDate");
    dateEl.setAttribute("min", today);
    dateEl.setAttribute("max", maxStr);

    document.getElementById("fsResForm").reset();
    document.getElementById("fsResQty").value = "1";
    document.getElementById("fsResMessage").innerHTML = "";
    document.getElementById("fsResProductId").value = productId;
    var marketId = parseInt(card.getAttribute("data-market-id") || "0", 10) || 0;
    var farmerId = parseInt(card.getAttribute("data-farmer-id") || "0", 10) || 0;
    var mIdEl = document.getElementById("fsResMarketId");
    var fIdEl = document.getElementById("fsResFarmerId");
    if (mIdEl) mIdEl.value = marketId ? String(marketId) : "";
    if (fIdEl) fIdEl.value = farmerId ? String(farmerId) : "";
    var marketName =
      (card.querySelector(".fs-prod-card-market") && card.querySelector(".fs-prod-card-market").textContent.trim()) ||
      card.getAttribute("data-product-market") ||
      "";
    wrap.setAttribute("data-fs-market-name", marketName);
    document.getElementById("fsResReservationId").value = "";
    if (csrfToken) {
      document.getElementById("fsResCsrf").value = csrfToken;
    }
    document.getElementById("fsResPay").style.display = "none";
    document.getElementById("fsResForm").style.display = "";

    var cartPre = readReservationCart();
    var warnEl = document.getElementById("fsResCartWarn");
    if (warnEl) {
      warnEl.style.display = "none";
      warnEl.textContent = "";
      if (cartPre.items.length && marketId && cartPre.marketId && marketId !== cartPre.marketId) {
        warnEl.style.display = "block";
        warnEl.textContent =
          "Your cart has items from another market. Clear the cart or finish that reservation before adding products from this market.";
      } else if (cartPre.items.length && farmerId && cartPre.farmerId && farmerId !== cartPre.farmerId) {
        warnEl.style.display = "block";
        warnEl.textContent =
          "Your cart is for a different seller. Clear the cart or finish that reservation before adding this product.";
      }
    }
    renderCartPanel();

    // Load market schedule (days + opening/closing hours) for validation
    loadMarketSchedule(productId).then(function () {
      applyDateTimeConstraints();
    });

    wrap.classList.add("fs-res-modal--open");
    wrap.setAttribute("aria-hidden", "false");
  }

  // ── Market schedule validation ────────────────────────────────────────────
  var _marketSchedule = null;

  function showResError(message) {
    var msg = document.getElementById("fsResMessage");
    if (!msg) return;
    msg.innerHTML = '<div class="fs-res-modal__msg fs-res-modal__msg--err">' + message + "</div>";
  }

  function clearResMessage() {
    var msg = document.getElementById("fsResMessage");
    if (msg) msg.innerHTML = "";
  }

  function loadMarketSchedule(productId) {
    _marketSchedule = null;
    var pid = parseInt(productId, 10) || 0;
    if (pid <= 0) return Promise.resolve(null);

    return fetch(api("/api/get_market_schedule.php?product_id=" + encodeURIComponent(pid)), {
      credentials: "include",
    })
      .then(function (r) {
        return r.json().catch(function () {
          return { success: false };
        });
      })
      .then(function (data) {
        if (data && data.success && data.market) {
          _marketSchedule = data.market;
        }
        return _marketSchedule;
      })
      .catch(function () {
        return null;
      });
  }

  function dayStrToIndex(s) {
    var map = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
    var k = String(s || "").trim().slice(0, 3);
    return map[k] !== undefined ? map[k] : null;
  }

  function parseHHMM(t) {
    if (!t) return null;
    var m = String(t).match(/^(\d{1,2}):(\d{2})/);
    if (!m) return null;
    return { h: parseInt(m[1], 10), m: parseInt(m[2], 10) };
  }

  function toMinutes(hhmm) {
    var p = parseHHMM(hhmm);
    if (!p) return null;
    return p.h * 60 + p.m;
  }

  function roundUpToStepMinutes(date, stepMin) {
    var d = new Date(date.getTime());
    var mins = d.getMinutes();
    var rem = mins % stepMin;
    if (rem !== 0) d.setMinutes(mins + (stepMin - rem));
    d.setSeconds(0, 0);
    return d;
  }

  function applyDateTimeConstraints() {
    var dateEl = document.getElementById("fsResDate");
    var timeEl = document.getElementById("fsResTime");
    if (!dateEl || !timeEl) return;

    // Date/time listeners (idempotent)
    if (!dateEl._fsBound) {
      dateEl.addEventListener("change", function () {
        validatePickup(true);
      });
      timeEl.addEventListener("change", function () {
        validatePickup(false);
      });
      timeEl.addEventListener("input", function () {
        validatePickup(false);
      });
      dateEl._fsBound = true;
    }

    validatePickup(false);
  }

  function validatePickup(clearOnInvalid) {
    var dateEl = document.getElementById("fsResDate");
    var timeEl = document.getElementById("fsResTime");
    if (!dateEl || !timeEl) return true;

    clearResMessage();

    var dateVal = (dateEl.value || "").trim();
    var timeVal = (timeEl.value || "").trim();

    // If only one is set, require both (keeps backend behavior but clearer UX)
    if ((dateVal && !timeVal) || (!dateVal && timeVal)) {
      showResError("Please select both pickup date and pickup time.");
      if (clearOnInvalid) timeEl.value = "";
      return false;
    }
    if (!dateVal && !timeVal) {
      // optional fields
      timeEl.removeAttribute("min");
      timeEl.removeAttribute("max");
      return true;
    }

    // Validate date format
    var d = new Date(dateVal + "T00:00:00");
    if (isNaN(d.getTime())) {
      showResError("Invalid pickup date.");
      if (clearOnInvalid) dateEl.value = "";
      return false;
    }

    // Past dates are already blocked via min= today, but enforce anyway
    var now = new Date();
    var today0 = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var picked0 = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    if (picked0 < today0) {
      showResError("Pickup date cannot be in the past.");
      if (clearOnInvalid) {
        dateEl.value = "";
        timeEl.value = "";
      }
      return false;
    }

    // Operating days validation
    if (_marketSchedule && _marketSchedule.operating_days && _marketSchedule.operating_days.length) {
      var allowed = {};
      _marketSchedule.operating_days.forEach(function (s) {
        var idx = dayStrToIndex(s);
        if (idx !== null) allowed[idx] = true;
      });
      if (!allowed[d.getDay()]) {
        showResError("Selected market is closed on that day. Please choose an open day.");
        if (clearOnInvalid) {
          dateEl.value = "";
          timeEl.value = "";
        }
        return false;
      }
    }

    // Time range validation
    var openT = (_marketSchedule && _marketSchedule.opening_time) || "06:00";
    var closeT = (_marketSchedule && _marketSchedule.closing_time) || "18:00";
    var openMin = toMinutes(openT);
    var closeMin = toMinutes(closeT);
    if (openMin === null || closeMin === null) {
      openMin = 360;
      closeMin = 1080;
    }

    var isToday = picked0.getTime() === today0.getTime();
    var minTime = openT;
    if (isToday) {
      var step = 5;
      var roundedNow = roundUpToStepMinutes(now, step);
      var nowMin = roundedNow.getHours() * 60 + roundedNow.getMinutes();
      if (nowMin > openMin) {
        minTime = String(roundedNow.getHours()).padStart(2, "0") + ":" + String(roundedNow.getMinutes()).padStart(2, "0");
      }
    }

    timeEl.setAttribute("min", minTime);
    timeEl.setAttribute("max", closeT);

    var pickedTimeMin = toMinutes(timeVal);
    if (pickedTimeMin === null) {
      showResError("Invalid pickup time.");
      if (clearOnInvalid) timeEl.value = "";
      return false;
    }

    // Must be within market hours
    var minAllowedMin = toMinutes(minTime);
    if (minAllowedMin === null) minAllowedMin = openMin;
    if (pickedTimeMin < minAllowedMin || pickedTimeMin > closeMin) {
      if (isToday && minAllowedMin > closeMin) {
        showResError("The market is already closed for today. Please choose another day.");
      } else {
        showResError("Pickup time must be within market hours (" + openT + "–" + closeT + ").");
      }
      if (clearOnInvalid) timeEl.value = "";
      return false;
    }

    // Same-day: cannot pick a time that already passed
    if (isToday) {
      var nowM = now.getHours() * 60 + now.getMinutes();
      if (pickedTimeMin < nowM) {
        showResError("That pickup time has already passed. Please choose a later time today.");
        if (clearOnInvalid) timeEl.value = "";
        return false;
      }
    }

    return true;
  }

  function submitReservation() {
    var msg = document.getElementById("fsResMessage");
    var submitBtn = document.getElementById("fsResSubmit");
    msg.innerHTML = "";
    submitBtn.disabled = true;
    submitBtn.textContent = "SUBMITTING…";

    var tokenEl = document.getElementById("fsResCsrf");
    var csrf = tokenEl.value;

    var cart = readReservationCart();
    var formData = {
      preferred_pickup_date: document.getElementById("fsResDate").value || "",
      preferred_pickup_time: document.getElementById("fsResTime").value || "",
      notes: document.getElementById("fsResNotes").value || "",
      csrf_token: csrf,
    };

    if (cart.items && cart.items.length) {
      formData.items = cart.items.map(function (it) {
        return { product_id: it.product_id, quantity: it.quantity, unit: it.unit };
      });
    } else {
      formData.product_id = document.getElementById("fsResProductId").value;
      formData.quantity = document.getElementById("fsResQty").value;
      formData.unit = document.getElementById("fsResUnit").value;
    }

    if (formData.items && formData.items.length > FS_RES_CART_MAX) {
      msg.innerHTML =
        '<div class="fs-res-modal__msg fs-res-modal__msg--err">A reservation can include at most 10 products. Remove items from your cart or place another reservation.</div>';
      submitBtn.disabled = false;
      resetSubmitBtnLabel();
      return;
    }

    if (formData.items) {
      /* ok */
    } else {
      if (!formData.quantity || parseFloat(formData.quantity) <= 0) {
        msg.innerHTML =
          '<div class="fs-res-modal__msg fs-res-modal__msg--err">Please enter a valid quantity or add items to your cart.</div>';
        submitBtn.disabled = false;
        resetSubmitBtnLabel();
        return;
      }
      if (!formData.unit) {
        msg.innerHTML = '<div class="fs-res-modal__msg fs-res-modal__msg--err">Please select a unit.</div>';
        submitBtn.disabled = false;
        resetSubmitBtnLabel();
        return;
      }
    }

    // Pickup date/time validation (market schedule + same-day rules)
    if (!validatePickup(true)) {
      submitBtn.disabled = false;
      resetSubmitBtnLabel();
      return;
    }

    fetch(api("/api/create_reservation.php"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(formData),
    })
      .then(function (r) {
        return r.text().then(function (text) {
          try {
            return { ok: r.ok, data: JSON.parse(text), status: r.status };
          } catch (e) {
            return {
              ok: false,
              data: { success: false, message: "Invalid server response" },
              status: r.status,
            };
          }
        });
      })
      .then(function (result) {
        if (result.data && result.data.success) {
          msg.innerHTML =
            '<div class="fs-res-modal__msg fs-res-modal__msg--ok">' +
            (result.data.message || "Reservation submitted.") +
            "</div>";
          if (result.data.public_ref) {
            msg.innerHTML +=
              '<div class="fs-res-modal__msg" style="margin-top:8px;font-size:13px">Reservation ID: <strong>' +
              String(result.data.public_ref).replace(/</g, "") +
              "</strong>" +
              (result.data.chat_public_ref
                ? " · Chat: <strong>" + String(result.data.chat_public_ref).replace(/</g, "") + "</strong>"
                : "") +
              "</div>";
          }
          var itemsSnapshot = [];
          if (cart.items && cart.items.length) {
            itemsSnapshot = cart.items.map(function (it) {
              return {
                product_name: it.product_name,
                quantity: it.quantity,
                unit: it.unit,
              };
            });
            renderProductInfoSuccessSummary(itemsSnapshot);
          }
          clearReservationCart();
          renderCartPanel();
          document.getElementById("fsResReservationId").value = result.data.reservation_id || "";
          document.getElementById("fsResPay").style.display = "block";
          document.getElementById("fsResForm").style.display = "none";
        } else {
          msg.innerHTML =
            '<div class="fs-res-modal__msg fs-res-modal__msg--err">' +
            (result.data && result.data.message ? result.data.message : "Request failed.") +
            "</div>";
        }
      })
      .catch(function () {
        msg.innerHTML =
          '<div class="fs-res-modal__msg fs-res-modal__msg--err">Network error. Please try again.</div>';
      })
      .finally(function () {
        submitBtn.disabled = false;
        resetSubmitBtnLabel();
      });
  }

  function proceedToPayment(method) {
    var msg = document.getElementById("fsResMessage");
    var csrf = (document.getElementById("fsResCsrf").value || "").trim();
    var reservationId = document.getElementById("fsResReservationId").value;

    if (!reservationId) {
      msg.innerHTML = '<div class="fs-res-modal__msg fs-res-modal__msg--err">Missing reservation ID.</div>';
      return;
    }
    fetch(api("/api/proceed_to_payment.php"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({
        reservation_id: reservationId,
        payment_method: method,
        gcash_reference: "",
        csrf_token: csrf,
      }),
    })
      .then(function (r) {
        return r.json().catch(function () {
          return { success: false, message: "Invalid server response" };
        });
      })
      .then(function (data) {
        if (data && data.success) {
          msg.innerHTML =
            '<div class="fs-res-modal__msg fs-res-modal__msg--ok">' +
            (data.message || "Updated.") +
            "</div>";
          setTimeout(function () {
            var wrap = document.getElementById("fsReservationModal");
            wrap.classList.remove("fs-res-modal--open");
            wrap.setAttribute("aria-hidden", "true");
            document.getElementById("fsResMessage").innerHTML = "";
            document.getElementById("fsResForm").reset();
            document.getElementById("fsResForm").style.display = "";
            document.getElementById("fsResPay").style.display = "none";
          }, 1400);
        } else {
          msg.innerHTML =
            '<div class="fs-res-modal__msg fs-res-modal__msg--err">' +
            (data && data.message ? data.message : "Request failed.") +
            "</div>";
        }
      })
      .catch(function () {
        msg.innerHTML = '<div class="fs-res-modal__msg fs-res-modal__msg--err">Network error.</div>';
      });
  }

  document.addEventListener(
    "click",
    function (e) {
      var btn = e.target.closest && e.target.closest(".js-mf-reserve");
      if (!btn) return;

      // If reserve is not available for this card, show a friendly message.
      var rawPid = btn.getAttribute("data-product-id") || "";
      var pid = parseInt(rawPid, 10) || 0;
      var ariaDisabled = (btn.getAttribute("aria-disabled") || "").toLowerCase() === "true";
      if (ariaDisabled || pid <= 0) {
        var oos = (btn.getAttribute("data-out-of-stock") || "") === "1";
        alert(oos ? "This item is currently not available." : "Reserve is not available for this item yet.");
        e.preventDefault();
        e.stopPropagation();
        return;
      }

      var card =
        btn.closest(".mf-osmo-prod-card") ||
        btn.closest(".mf-mm-product-card") ||
        btn.closest(".fs-prod-card");
      if (!card) return;

      e.preventDefault();
      e.stopPropagation();

      fetch(api("/api/auth_session.php"), { credentials: "include" })
        .then(function (r) {
          return r.json();
        })
        .then(function (auth) {
          if (!auth || !auth.logged_in) {
            var here =
              window.location.pathname +
              window.location.search +
              window.location.hash;
            window.location.href =
              api("/login.php") +
              "?redirect=" +
              encodeURIComponent(here) +
              "&message=" +
              encodeURIComponent("Please log in to reserve products.");
            return;
          }
          return fetch(api("/api/get_csrf_token.php"), { credentials: "include" })
            .then(function (r) {
              return r.json();
            })
            .then(function (tok) {
              if (!tok || !tok.success || !tok.csrf_token) {
                alert("Could not load security token. Please refresh and try again.");
                return;
              }
              openModal(card, tok.csrf_token);
            });
        })
        .catch(function () {
          window.location.href =
            api("/login.php") +
            "?redirect=" +
            encodeURIComponent(window.location.pathname + window.location.search + window.location.hash);
        });
    },
    true
  );
})();

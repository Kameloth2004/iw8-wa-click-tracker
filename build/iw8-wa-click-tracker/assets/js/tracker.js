/**
 * IW8 – Rastreador de Cliques WhatsApp (Frontend)
 * @package IW8_WaClickTracker
 * @version 1.3.0-rev3
 */
(function () {
  if (!window || !document) return;

  const cfg = window.iw8WaData || {};
  const dbg = !!cfg.debug;
  const noBeacon = !!cfg.noBeacon;
  const ajaxUrl = cfg.ajax_url || window.ajaxurl || "";
  const nonce = cfg.nonce || "";
  const userId = parseInt(cfg.user_id || 0, 10) || 0;

  // log helpers
  const log = (...a) => {
    if (dbg) console.log("[IW8-Track]", ...a);
  };
  const warn = (...a) => {
    if (dbg) console.warn("[IW8-Track]", ...a);
  };

  // detectar links WhatsApp
  const isWhatsAppLink = (href) => {
    if (!href) return false;
    return (
      /(^|\b)(https?:\/\/)?(api\.whatsapp\.com|wa\.me)\//i.test(href) ||
      /^whatsapp:\/\//i.test(href)
    );
  };

  // construir rótulo amigável
  const getLabel = (el, url) => {
    if (!el) return "";
    const txt =
      (el.getAttribute && el.getAttribute("aria-label")) ||
      el.title ||
      el.alt ||
      el.innerText ||
      el.textContent ||
      "";
    const cleaned = (txt || "").trim().replace(/\s+/g, " ").substring(0, 500);
    if (cleaned) return cleaned;
    try {
      const u = new URL(url, window.location.href);
      return (u.searchParams.get("text") || u.pathname || "").substring(0, 500);
    } catch (_) {
      return "";
    }
  };

  // dedupe por hash simples da URL
  const seen = new Set();
  const keyFor = (href) => (href || "").replace(/[#?].*$/, "").toLowerCase();

  // envio com fallback
  const sendClick = (payload) => {
    const body = new FormData();
    body.append("action", "iw8_wa_click");
    body.append("_ajax_nonce", nonce);
    body.append("data", JSON.stringify(payload));

    const url = ajaxUrl || window.ajaxurl || "";
    if (!url) {
      warn("ajax_url ausente");
      return;
    }

    // beacon
    if (!noBeacon && navigator && typeof navigator.sendBeacon === "function") {
      try {
        const b = new URLSearchParams();
        b.append("action", "iw8_wa_click");
        b.append("_ajax_nonce", nonce);
        b.append("data", JSON.stringify(payload));
        const ok = navigator.sendBeacon(url, b);
        if (ok) {
          log("enviado via beacon");
          return;
        }
      } catch (e) {
        /* continua */
      }
    }

    // fetch keepalive
    if (window.fetch) {
      fetch(url, {
        method: "POST",
        body,
        credentials: "same-origin",
        keepalive: true,
      })
        .then(() => log("enviado via fetch"))
        .catch(() => warn("falha fetch"));
      return;
    }

    // XHR
    try {
      const xhr = new XMLHttpRequest();
      xhr.open("POST", url, true);
      xhr.onload = function () {
        log("enviado via xhr");
      };
      xhr.onerror = function () {
        warn("falha xhr");
      };
      xhr.send(body);
    } catch (e) {
      warn("falha geral envio");
    }
  };

  // delegação
  const handler = (evt) => {
    try {
      const clickable = evt.target.closest("a, area");
      if (!clickable) return;

      const href = clickable.getAttribute("href") || "";
      if (!isWhatsAppLink(href)) return;

      const key = keyFor(href);
      if (seen.has(key)) {
        log("dedupe", key);
        return;
      }
      seen.add(key);

      const url = href;
      const payload = {
        url,
        page_url: window.location.href,
        element_tag: clickable.tagName
          ? clickable.tagName.toUpperCase()
          : "UNKNOWN",
        element_text: getLabel(clickable, url),
        user_agent: navigator.userAgent || "",
        user_id: userId,
      };

      sendClick(payload);
    } catch (e) {
      warn("erro handler", e);
    }
  };

  document.addEventListener("click", handler, true);
  document.addEventListener("auxclick", handler, true);

  // patch window.open (melhora compat)
  const _open = window.open;
  window.open = function (...args) {
    try {
      const u = args && args[0] ? String(args[0]) : "";
      if (isWhatsAppLink(u)) {
        // dispara um evento synthetic para aproveitar mesma lógica
        const a = document.createElement("a");
        a.href = u;
        document.body.appendChild(a);
        a.click();
        a.remove();
      }
    } catch (e) {
      /* ignore */
    }
    return _open ? _open.apply(window, args) : null;
  };

  log("delegation pronta (click/auxclick) + patch window.open");
})();

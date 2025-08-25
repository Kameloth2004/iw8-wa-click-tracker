/**
 * JavaScript para rastreamento de cliques no WhatsApp
 *
 * @package IW8_WaClickTracker
 * @version 1.3.0-rev
 */

(function () {
  "use strict";

  const hasData = typeof window.iw8WaData !== "undefined";
  const config = hasData ? window.iw8WaData : {};
  const dbg = !!config.debug;

  // helpers de log (ficam mudos quando debug=false)
  const log = (...a) => {
    if (dbg) console.log(...a);
  };
  const warn = (...a) => {
    if (dbg) console.warn(...a);
  };
  const err = (...a) => {
    if (dbg) console.error(...a);
  };

  // se não tiver dados, saia em silêncio
  if (!hasData) return;

  // --- utils ---
  const onlyDigits = (s) =>
    typeof s === "string" ? s.replace(/\D+/g, "") : "";
  const phoneDigits = onlyDigits(config.phone || "");

  // dedupe por URL (2s)
  const sentUrls = Object.create(null);

  // Verificação robusta de alvo WhatsApp para o telefone configurado
  const isTargetWhatsAppUrl = (rawUrl) => {
    if (!rawUrl || typeof rawUrl !== "string" || !phoneDigits) return false;

    try {
      const u = new URL(rawUrl, window.location.href);
      const scheme = (u.protocol || "").replace(":", "").toLowerCase(); // http/https/whatsapp
      const host = (u.hostname || "").toLowerCase(); // api.whatsapp.com / wa.me
      const path = u.pathname || ""; // /send /message/xxx /<phone>
      const q = u.searchParams;

      // whatsapp://send?phone=...
      if (scheme === "whatsapp") {
        const qp = onlyDigits(q.get("phone") || "");
        return qp === phoneDigits;
      }

      // api.whatsapp.com
      if (host === "api.whatsapp.com") {
        // /send?phone=...
        if (path === "/send") {
          const qp = onlyDigits(q.get("phone") || "");
          return qp === phoneDigits;
        }
        // /message/<code> (aceito)
        if (path.startsWith("/message/")) {
          return true;
        }
        return false;
      }

      // wa.me
      if (host === "wa.me") {
        // /<phone>
        const m = path.match(/^\/(?:\+|%2B)?(\d{6,20})$/i);
        if (m) {
          return m[1] === phoneDigits;
        }
        // /message/<code> (aceito)
        if (path.startsWith("/message/")) {
          return true;
        }
        return false;
      }
    } catch (_) {
      // fallback com regex se URL() falhar (URLs malformadas)
      const p = phoneDigits.replace(/[-\/\\^$*+?.()|[\]{}]/g, "\\$&");
      const re = new RegExp(
        "^(" +
          // api.whatsapp.com/send com phone em qualquer ordem da query
          "https?:\\/\\/api\\.whatsapp\\.com\\/send(?:\\?(?=[^#]*\\bphone=(?:\\+|%2B)?" +
          p +
          ")[^#]*)?" +
          "|" +
          // api.whatsapp.com/message/<code>
          "https?:\\/\\/api\\.whatsapp\\.com\\/message\\/[^#\\s]+" +
          "|" +
          // wa.me/<phone>
          "https?:\\/\\/wa\\.me\\/(?:\\+|%2B)?" +
          p +
          "(?:\\?[^#]*)?" +
          "|" +
          // wa.me/message/<code>
          "https?:\\/\\/wa\\.me\\/message\\/[^#\\s]+" +
          "|" +
          // whatsapp://send?phone=...
          "whatsapp:\\/\\/send(?:\\?(?=[^#]*\\bphone=(?:\\+|%2B)?" +
          p +
          ")[^#]*)?" +
          ")$",
        "i"
      );
      return re.test(rawUrl);
    }

    return false;
  };

  // Extrai URL de diversos tipos de elementos
  const extractUrl = (element) => {
    if (!element) return null;
    if (element.href) return element.href;

    const onclick = element.getAttribute && element.getAttribute("onclick");
    if (onclick) {
      const m = onclick.match(/https?:\/\/[^'"\s)]+/i);
      if (m) return m[0];
    }

    const dataHref =
      element.getAttribute &&
      (element.getAttribute("data-href") || element.getAttribute("data-url"));
    return dataHref || null;
  };

  // Envia o clique (fire-and-forget)
  const sendClick = (payload) => {
    const now = Date.now();
    const url = payload.url;

    // dedupe simples por 2s
    if (sentUrls[url] && now - sentUrls[url] < 2000) {
      log("[IW8 Track] Clique ignorado (duplicado):", url);
      return;
    }
    sentUrls[url] = now;

    // guarda obrigatórios
    if (!config.ajax_url || !config.action || !config.nonce) {
      warn("[IW8 Track] Config incompleta para envio", {
        ajax_url: config.ajax_url,
        action: config.action,
        nonce: !!config.nonce,
      });
      return;
    }

    const data = {
      ...payload,
      action: config.action,
      nonce: config.nonce,
    };

    const params = new URLSearchParams();
    Object.keys(data).forEach((k) => {
      const v = data[k];
      if (v !== null && v !== undefined) params.append(k, v);
    });

    fetch(config.ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: params.toString(),
      keepalive: true,
      cache: "no-store",
      credentials: "same-origin",
    }).catch(() => {
      /* silencioso */
    });

    log("[IW8 Track] Disparo de clique enviado:", url);
  };

  // Captura cliques (anchor, button, data-href/url, onclick)
  const captureClick = (event) => {
    const target = event.target;
    const clickable =
      target &&
      target.closest &&
      target.closest(
        'a, button, [role="button"], [onclick], [data-href], [data-url]'
      );
    if (!clickable) return;

    const url = extractUrl(clickable);
    if (!url) return;

    if (!isTargetWhatsAppUrl(url)) return;

    const payload = {
      url,
      page_url: window.location.href,
      element_tag: clickable.tagName
        ? clickable.tagName.toLowerCase()
        : "unknown",
      element_text: (clickable.textContent || "").trim().substring(0, 500),
    };

    sendClick(payload);
  };

  // Rastreamento de aberturas programáticas (window.open)
  const trackWindowOpen = (url, context) => {
    if (!url || typeof url !== "string") return;
    if (!isTargetWhatsAppUrl(url)) return;

    sendClick({
      url,
      page_url: window.location.href,
      element_tag: context || "WINDOW.OPEN",
      element_text: "Abertura de janela",
    });
  };

  // Patch leve de window.open (NÃO tocamos em location.assign/replace)
  const originalWindowOpen = window.open;
  window.open = function (url, target, features) {
    if (typeof url === "string") trackWindowOpen(url, "WINDOW.OPEN");
    return originalWindowOpen.call(this, url, target, features);
  };

  // Delegation de cliques (inclui middle-click via auxclick)
  document.addEventListener("click", captureClick, true);
  document.addEventListener("auxclick", captureClick, true);

  log("[IW8 Track] Inicializado com telefone:", config.phone);
  log("[IW8 Track] Delegation ativo (click/auxclick) + patch window.open");
})();

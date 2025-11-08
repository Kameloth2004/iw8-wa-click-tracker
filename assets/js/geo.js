// assets/js/geo.js — v3: injeta city/region/country no CORPO do request (fetch/XHR/jQuery)
(function () {
  var GEO_CACHE_KEY = 'iw8wa_geo_cache_v1';
  var GEO_TTL_MS = 24 * 60 * 60 * 1000; // 24h
  var FETCH_TIMEOUT_MS = 800;
  var geoCache = null;

  function loadCache() {
    try {
      var c = JSON.parse(localStorage.getItem(GEO_CACHE_KEY) || 'null');
      if (c && (Date.now() - (c._ts || 0)) < GEO_TTL_MS) return c;
    } catch(e){}
    return null;
  }
  function saveCache(obj){ try{ obj._ts=Date.now(); localStorage.setItem(GEO_CACHE_KEY, JSON.stringify(obj)); }catch(e){} }
  function normalizeGeo(d){ return { city:(d&&d.city)||'', region:(d&&(d.region||d.regionName))||'', country:(d&&d.country)||'' }; }

  function fetchWithTimeout(url, timeoutMs) {
    var ctrl = new AbortController();
    var id = setTimeout(function(){ try{ctrl.abort();}catch(e){} }, timeoutMs);
    return fetch(url, { signal: ctrl.signal, credentials: 'omit' }).finally(function(){ clearTimeout(id); });
  }

  function prefetchGeo(){
    var cached = loadCache();
    if (cached) { geoCache = cached; return Promise.resolve(cached); }
    return fetchWithTimeout('https://ipwho.is/?fields=city,region,country', FETCH_TIMEOUT_MS)
      .then(function(r){ if(!r.ok) throw 0; return r.json(); })
      .then(function(j){ geoCache = normalizeGeo(j); saveCache(geoCache); return geoCache; })
      .catch(function(){ geoCache = {city:'',region:'',country:''}; return geoCache; });
  }
  function getGeoNow(){ return geoCache || loadCache() || {city:'',region:'',country:''}; }

  function isAjaxClick(url, bodyOrParams){
    if (!url) return false;
    if (url.indexOf('admin-ajax.php') === -1) return false;
    if (!bodyOrParams) return false;
    // String (x-www-form-urlencoded)
    if (typeof bodyOrParams === 'string') return bodyOrParams.indexOf('action=iw8_wa_click') !== -1;
    // URLSearchParams
    if (typeof URLSearchParams !== 'undefined' && bodyOrParams instanceof URLSearchParams) {
      return bodyOrParams.get('action') === 'iw8_wa_click';
    }
    // FormData
    if (typeof FormData !== 'undefined' && bodyOrParams instanceof FormData) {
      return (bodyOrParams.get && bodyOrParams.get('action') === 'iw8_wa_click');
    }
    // Objeto simples (jQuery)
    try { return (bodyOrParams.action === 'iw8_wa_click'); } catch(e){ return false; }
  }

  function appendGeoToBody(body, geo) {
    // 1) String (x-www-form-urlencoded)
    if (typeof body === 'string') {
      var extra = '';
      var enc = encodeURIComponent;
      if (geo.country) extra += '&country=' + enc(geo.country);
      if (geo.region)  extra += '&region='  + enc(geo.region);
      if (geo.city)    extra += '&city='    + enc(geo.city);
      return body + extra;
    }
    // 2) URLSearchParams
    if (typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) {
      if (geo.country) body.set('country', geo.country);
      if (geo.region)  body.set('region',  geo.region);
      if (geo.city)    body.set('city',    geo.city);
      return body;
    }
    // 3) FormData
    if (typeof FormData !== 'undefined' && body instanceof FormData) {
      if (geo.country && !body.get('country')) body.append('country', geo.country);
      if (geo.region  && !body.get('region'))  body.append('region',  geo.region);
      if (geo.city    && !body.get('city'))    body.append('city',    geo.city);
      return body;
    }
    // 4) Objeto (jQuery data)
    if (body && typeof body === 'object') {
      if (geo.country && !body.country) body.country = geo.country;
      if (geo.region  && !body.region)  body.region  = geo.region;
      if (geo.city    && !body.city)    body.city    = geo.city;
      return body;
    }
    return body;
  }

  // === Hook fetch() ===
  if (typeof window.fetch === 'function' && !window.fetch.__iw8waWrapped3) {
    var _fetch = window.fetch;
    window.fetch = function(input, init){
      try {
        var url = (typeof input === 'string') ? input : (input && input.url) || '';
        if (init && isAjaxClick(url, init.body)) {
          init.body = appendGeoToBody(init.body, getGeoNow());
        }
      } catch(e){}
      return _fetch(input, init);
    };
    window.fetch.__iw8waWrapped3 = true;
  }

  // === Hook XMLHttpRequest ===
  if (window.XMLHttpRequest && !window.XMLHttpRequest.__iw8waWrapped3) {
    var XHR = window.XMLHttpRequest;
    var open = XHR.prototype.open, send = XHR.prototype.send;
    XHR.prototype.open = function(method, url){ this.__iw8waUrl = url || ''; return open.apply(this, arguments); };
    XHR.prototype.send = function(body){
      try {
        if (isAjaxClick(this.__iw8waUrl, body)) body = appendGeoToBody(body, getGeoNow());
      } catch(e){}
      return send.call(this, body);
    };
    window.XMLHttpRequest.__iw8waWrapped3 = true;
  }

  // === Hook jQuery.ajax (se existir) ===
  function hookJq($){
    $(document).ajaxSend(function (_evt, jqXHR, settings) {
      try {
        if (!settings) return;
        if (!isAjaxClick(settings.url, settings.data)) return;
        settings.data = appendGeoToBody(settings.data, getGeoNow());
      } catch(e){}
    });
  }
  if (window.jQuery) hookJq(window.jQuery); else {
    var tries=0, iv=setInterval(function(){ if(window.jQuery){ hookJq(window.jQuery); clearInterval(iv); } else if(++tries>100){ clearInterval(iv); } },100);
  }

  // Prefetch em background
  prefetchGeo();
})();

(function () {
  function isFiniteCoord(value, min, max) {
    return typeof value === "number" && Number.isFinite(value) && value >= min && value <= max;
  }

  function setHidden(lat, lng) {
    const latEl = document.getElementById("pm_lat");
    const lngEl = document.getElementById("pm_lng");
    if (latEl) latEl.value = lat.toFixed(6);
    if (lngEl) lngEl.value = lng.toFixed(6);
  }

  function initCreateMap() {
    const cfg = window.PM_MAP_CREATE || null;
    const mapId = (cfg && cfg.selector) || "pm-map";
    const mapEl = document.getElementById(mapId);
    if (!mapEl || typeof L === "undefined") return;

    const startLat = cfg ? Number(cfg.defaultLat) : -34.6630;
    const startLng = cfg ? Number(cfg.defaultLng) : -58.3660;
    const startZoom = (cfg && Number(cfg.defaultZoom)) || 13;
    if (!isFiniteCoord(startLat, -90, 90) || !isFiniteCoord(startLng, -180, 180)) {
      if (window.console) console.warn("[PetMatch] Invalid create map config", cfg);
      return;
    }
    if (mapEl.dataset.pmMapInited === "1") return;
    mapEl.dataset.pmMapInited = "1";

    const map = L.map(mapEl).setView([startLat, startLng], startZoom);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);
    setHidden(startLat, startLng);

    marker.on("dragend", function () {
      const pos = marker.getLatLng();
      setHidden(pos.lat, pos.lng);
    });

    map.on("click", function (e) {
      marker.setLatLng(e.latlng);
      setHidden(e.latlng.lat, e.latlng.lng);
    });

    const btn = document.getElementById("pm_use_my_location");
    if (btn && navigator.geolocation) {
      btn.addEventListener("click", function (e) {
        if (e && e.preventDefault) e.preventDefault();
        if (window.console) console.log("[PetMatch] Using browser geolocation...");
        navigator.geolocation.getCurrentPosition(
          function (pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 16);
            setHidden(lat, lng);
          },
          function (err) {
            if (window.console) console.warn("[PetMatch] Geolocation failed", err);
            alert((cfg && cfg.i18n && cfg.i18n.geoFail) || "No pudimos obtener tu ubicación.");
          },
          { enableHighAccuracy: true, timeout: 8000 }
        );
      });
    }
  }

  function initSingleMap() {
    const cfg = window.PM_MAP_SINGLE || null;
    if (!cfg) return;
    const mapId = cfg.selector || "pm-map-single";
    const mapEl = document.getElementById(mapId);
    if (!mapEl || typeof L === "undefined") return;
    if (mapEl.dataset.pmMapInited === "1") return;

    const lat = Number(cfg.lat);
    const lng = Number(cfg.lng);
    const zoom = Number(cfg.defaultZoom) || 15;
    if (!isFiniteCoord(lat, -90, 90) || !isFiniteCoord(lng, -180, 180)) {
      if (window.console) console.warn("[PetMatch] Invalid single map config", cfg);
      return;
    }
    mapEl.dataset.pmMapInited = "1";

    const map = L.map(mapEl, {
      dragging: true,
      scrollWheelZoom: false
    }).setView([lat, lng], zoom);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng], { draggable: false }).addTo(map);
  }

    function boot() {
    try { initCreateMap(); } catch(e){ if(window.console) console.error('[PetMatch] initCreateMap error', e); }
    try { initSingleMap(); } catch(e){ if(window.console) console.error('[PetMatch] initSingleMap error', e); }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();

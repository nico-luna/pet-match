(function () {
  function setHidden(lat, lng) {
    const latEl = document.getElementById("pm_lat");
    const lngEl = document.getElementById("pm_lng");
    if (latEl) latEl.value = lat.toFixed(6);
    if (lngEl) lngEl.value = lng.toFixed(6);
  }

  function initCreateMap() {
    const mapEl = document.getElementById("pm-map");
    if (!mapEl || typeof L === "undefined") return;

    const startLat = (window.PM_MAP && PM_MAP.defaultLat) || -34.6630;
    const startLng = (window.PM_MAP && PM_MAP.defaultLng) || -58.3660;
    const startZoom = (window.PM_MAP && PM_MAP.defaultZoom) || 13;

    const map = L.map("pm-map").setView([startLat, startLng], startZoom);

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
            alert((window.PM_MAP && PM_MAP.i18n && PM_MAP.i18n.geoFail) || "No pudimos obtener tu ubicación.");
          },
          { enableHighAccuracy: true, timeout: 8000 }
        );
      });
    }
  }

  function initSingleMap() {
    if (!(window.PM_MAP && PM_MAP.single)) return;
    const mapEl = document.getElementById("pm-map-single");
    if (!mapEl || typeof L === "undefined") return;

    const lat = PM_MAP.singleLat;
    const lng = PM_MAP.singleLng;
    const zoom = PM_MAP.defaultZoom || 15;

    const map = L.map("pm-map-single", {
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
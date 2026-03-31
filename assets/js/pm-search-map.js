(function(){
  function parseNumber(value){
    var parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function init(){
    document.querySelectorAll('.pm-search-map').forEach(function(el){
      if (el.__pm_inited) return;
      el.__pm_inited = true;
      if (typeof L === 'undefined') return;

      var form = el.closest('.pm-search') ? el.closest('.pm-search').querySelector('form.pm-search-form') : null;
      if (!form) return;

      var latInput = form.querySelector('input[name="pm_lat"]');
      var lngInput = form.querySelector('input[name="pm_lng"]');
      var radiusInput = form.querySelector('input[name="pm_radius"]');

      var casesJson = el.getAttribute('data-cases') || '[]';
      var cases = [];
      try { cases = JSON.parse(casesJson); } catch(e){ cases = []; }

      var cLat = parseNumber(el.getAttribute('data-center-lat'));
      var cLng = parseNumber(el.getAttribute('data-center-lng'));
      var radiusKm = parseNumber(el.getAttribute('data-radius-km')) || 10;
      var hasCenter = cLat !== null && cLng !== null;

      var first = cases.find(function(x){
        return parseNumber(x.lat) !== null && parseNumber(x.lng) !== null;
      });
      var startLat = hasCenter ? cLat : (first ? parseFloat(first.lat) : -34.662);
      var startLng = hasCenter ? cLng : (first ? parseFloat(first.lng) : -58.365);

      var map = L.map(el).setView([startLat, startLng], hasCenter ? 13 : 12);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(map);

      var markers = [];
      cases.forEach(function(c){
        var caseLat = parseNumber(c.lat);
        var caseLng = parseNumber(c.lng);
        if (caseLat === null || caseLng === null) return;
        var marker = L.marker([caseLat, caseLng]).addTo(map);
        var popup = '<strong>' + (c.title || 'Caso') + '</strong>';
        if (c.distance !== undefined) {
          popup += '<br>' + c.distance + ' km';
        }
        popup += '<br><a href="' + c.url + '">Ver</a>';
        marker.bindPopup(popup);
        markers.push(marker);
      });

      var centerMarker = null;
      var radiusCircle = null;

      function updateCenter(lat, lng){
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);

        if (centerMarker) {
          centerMarker.setLatLng([lat, lng]);
        } else {
          centerMarker = L.circleMarker([lat, lng], { radius: 8 }).addTo(map);
        }

        updateRadiusCircle(lat, lng);
      }

      function updateRadiusCircle(lat, lng){
        var radiusValue = radiusInput ? parseNumber(radiusInput.value) : radiusKm;
        radiusValue = radiusValue || radiusKm;
        if (radiusCircle) {
          radiusCircle.setLatLng([lat, lng]);
          radiusCircle.setRadius(radiusValue * 1000);
        } else {
          radiusCircle = L.circle([lat, lng], {
            radius: radiusValue * 1000,
            color: '#0f766e',
            weight: 1,
            fillColor: '#99f6e4',
            fillOpacity: 0.15
          }).addTo(map);
        }
      }

      if (hasCenter) {
        updateCenter(cLat, cLng);
      } else if (markers.length > 1) {
        var group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.15));
      }

      map.on('click', function(e){
        updateCenter(e.latlng.lat, e.latlng.lng);
      });

      function syncFromInputs(){
        var inputLat = latInput ? parseNumber(latInput.value) : null;
        var inputLng = lngInput ? parseNumber(lngInput.value) : null;
        if (inputLat === null || inputLng === null) return;
        updateCenter(inputLat, inputLng);
        map.panTo([inputLat, inputLng]);
      }

      if (latInput) latInput.addEventListener('change', syncFromInputs);
      if (lngInput) lngInput.addEventListener('change', syncFromInputs);
      if (radiusInput) {
        radiusInput.addEventListener('change', function(){
          if (!centerMarker) return;
          var center = centerMarker.getLatLng();
          updateRadiusCircle(center.lat, center.lng);
        });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();

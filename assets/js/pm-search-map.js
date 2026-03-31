(function(){
  function init(){
    document.querySelectorAll('.pm-search-map').forEach(function(el){
      if (el.__pm_inited) return;
      el.__pm_inited = true;
      if (typeof L === 'undefined') return;

      var casesJson = el.getAttribute('data-cases') || '[]';
      var cases = [];
      try { cases = JSON.parse(casesJson); } catch(e){ cases=[]; }

      var cLat = parseFloat(el.getAttribute('data-center-lat'));
      var cLng = parseFloat(el.getAttribute('data-center-lng'));
      var hasCenter = !isNaN(cLat) && !isNaN(cLng);

      var first = cases.find(function(x){ return x.lat && x.lng; });
      var startLat = hasCenter ? cLat : (first ? first.lat : -34.662);
      var startLng = hasCenter ? cLng : (first ? first.lng : -58.365);

      var map = L.map(el).setView([startLat, startLng], hasCenter ? 13 : 12);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);

      var markers = [];
      cases.forEach(function(c){
        if (!c.lat || !c.lng) return;
        var m = L.marker([c.lat, c.lng]).addTo(map);
        m.bindPopup('<strong>'+(c.title||'Caso')+'</strong><br><a href="'+c.url+'">Ver</a>');
        markers.push(m);
      });

      if (markers.length > 1 && !hasCenter){
        var group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.15));
      }

      map.on('click', function(e){
        var lat = e.latlng.lat.toFixed(6);
        var lng = e.latlng.lng.toFixed(6);
        var form = el.closest('.pm-search') ? el.closest('.pm-search').querySelector('form.pm-search-form') : null;
        if (!form) return;
        var latInput = form.querySelector('input[name="pm_lat"]');
        var lngInput = form.querySelector('input[name="pm_lng"]');
        if (latInput) latInput.value = lat;
        if (lngInput) lngInput.value = lng;

        if (el.__pm_center_marker){
          el.__pm_center_marker.setLatLng(e.latlng);
        } else {
          el.__pm_center_marker = L.circleMarker(e.latlng, {radius:8}).addTo(map);
        }
      });
    });
  }
  document.addEventListener('DOMContentLoaded', init);
})();
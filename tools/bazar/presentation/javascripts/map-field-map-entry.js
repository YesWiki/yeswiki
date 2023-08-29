window.addEventListener('load', function(event) {
  let entryMaps = Array();
  const options = {
    root: document.body,
    rootMargin: '20px',
    threshold: 0
  }

  function observerCallback(entries) {
    entries.forEach(entry => {
      let id = entry.target.getAttribute('id')

      // Lazyload leaflet map when intersecting
      if (entry.isIntersecting && !entryMaps[id]) {
        let mapData = JSON.parse(entry.target.getAttribute('data-map-field'));
        // Init leaflet map
        entryMaps[id] = new L.Map(id, {
          scrollWheelZoom: mapData.bazWheelZoom,
          zoomControl: mapData.bazShowNav
        });
        var provider = L.tileLayer.provider(
          mapData.mapProvider,
          mapData.mapProviderCredentials
        );
        entryMaps[id].addLayer(provider);

        let point = new L.LatLng(mapData.latitude, mapData.longitude);
        entryMaps[id].setView(
          point,
          mapData.bazMapZoom
        );
        L.marker(point).addTo(entryMaps[id] );
      }
    });
  }

  let observer = new IntersectionObserver(observerCallback, options);
  document.querySelectorAll('.map-entry').forEach(function (map) {
    observer.observe(map);
  });
});

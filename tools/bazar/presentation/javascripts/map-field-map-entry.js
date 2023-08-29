// cache the initialized entry maps
let entryMaps = Array();

// observer's options
const observerOptions = {
  root: document.body,
  rootMargin: '20px',
  threshold: 0
}

// on first time seen, load the leaflet entry map
function lazyloadMaps(entries) {
  entries.forEach(entry => {
    let id = entry.target.getAttribute('id')
    // Lazyload leaflet map when intersecting
    if (entry.isIntersecting && !entryMaps[id]) {
      let mapData = JSON.parse(entry.target.getAttribute('data-map-field'));

      // Init leaflet entry map
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

// observe entry map and add an listId if necessary
function addMapObserver() {
  const observer = new IntersectionObserver(lazyloadMaps, observerOptions);
  document.querySelectorAll('.map-entry').forEach(function (map) {
    observer.observe(map);
  });
}

// on load, init the map statically generated
window.addEventListener('load', function() {
  addMapObserver()
});

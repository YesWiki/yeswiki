import { drawGeometries } from '../leaflet-draw.helper.js'

export function initEntryMap(newMap) {
  if (newMap.classList.contains('initialized')) return

  const mapData = JSON.parse(newMap.getAttribute('data-map-field'))
  // Init leaflet entry map
  const map = new L.Map(newMap, {
    scrollWheelZoom: mapData.bazWheelZoom,
    zoomControl: mapData.bazShowNav,
  })
  const provider = L.tileLayer.provider(
    mapData.mapProvider,
    mapData.mapProviderCredentials,
  )
  map.addLayer(provider)

  var drawnFeatures = new L.FeatureGroup()
  map.addLayer(drawnFeatures)

  const point = new L.LatLng(mapData.latitude, mapData.longitude)
  var marker = L.marker(point)
  drawnFeatures.addLayer(marker)
  map.setView(point, mapData.bazMapZoom || 13)

  drawnFeatures = drawGeometries(drawnFeatures, mapData.geometries.features)

  map.whenReady(function () {
    var bounds = drawnFeatures.getBounds()
    if (bounds.isValid()) {
      map.fitBounds(bounds, {
        padding: [30, 30],
      })
    }
  })

  newMap.classList.add('initialized')
}

export function initEntryMaps(entryDom) {
  entryDom.querySelectorAll('.map-entry:not(.initialized)').forEach((map) => {
    initEntryMap(map)
  })
}

// on first time seen, load the leaflet entry map
function lazyloadMaps(maps) {
  maps.forEach((map) => {
    // Lazyload leaflet map when intersecting
    if (map.isIntersecting) initEntryMap(map.target)
  })
}

// observe entry map and add an listId if necessary
function addMapObserver() {
  const observer = new IntersectionObserver(lazyloadMaps, {
    root: document.body,
    rootMargin: '20px',
    threshold: 0,
  })
  document.querySelectorAll('.map-entry:not(.initialized)').forEach((map) => {
    observer.observe(map)
  })
}

// on load, init the map statically generated
window.addEventListener('load', () => {
  addMapObserver()
})

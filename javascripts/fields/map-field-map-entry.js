import { drawGeometries } from '../leaflet-draw.helper.js'

export function initEntryMap(newMap) {
  if (newMap.classList.contains('initialized')) return

  const mapData = JSON.parse(newMap.getAttribute('data-map-field'))
  const map = new L.Map(newMap, {
    scrollWheelZoom: mapData.bazWheelZoom,
    zoomControl: mapData.bazShowNav,
  })
  const provider = L.tileLayer.provider(
    mapData.mapProvider,
    mapData.mapProviderCredentials,
  )
  map.addLayer(provider)
  map.setView(
    [mapData.bazMapCenterLat, mapData.bazMapCenterLon],
    mapData.bazMapZoom || 13,
  )

  let drawnFeatures = new L.FeatureGroup()
  map.addLayer(drawnFeatures)

  if (mapData.latitude && mapData.longitude) {
    const point = new L.LatLng(mapData.latitude, mapData.longitude)
    const marker = L.marker(point)
    drawnFeatures.addLayer(marker)
    map.setView(point, mapData.bazMapZoom || 13)
  }

  if (mapData.geometries) {
    drawnFeatures = drawGeometries(drawnFeatures, mapData.geometries.features)
    map.whenReady(() => {
      const bounds = drawnFeatures.getBounds()
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [30, 30] })
      }
    })
  }

  newMap.classList.add('initialized')
}

export function initEntryMaps(entryDom) {
  entryDom.querySelectorAll('.map-entry:not(.initialized)').forEach((map) => {
    initEntryMap(map)
  })
}

function lazyloadMaps(maps) {
  maps.forEach((map) => {
    if (map.isIntersecting) initEntryMap(map.target)
  })
}

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

window.addEventListener('load', () => {
  addMapObserver()
})

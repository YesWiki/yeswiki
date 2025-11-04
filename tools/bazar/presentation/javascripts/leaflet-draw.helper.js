export function drawGeometries(drawnItems, features) {
  features.forEach(function (feature) {
    if (feature.properties && feature.properties.type === 'circle') {
      var latlng = L.latLng(
        feature.geometry.coordinates[1],
        feature.geometry.coordinates[0],
      )
      var radius = feature.properties.radius

      var circleOptions = { ...feature.properties }
      delete circleOptions.type
      delete circleOptions.radius
      // Clean up any default marker properties
      if (circleOptions.icon) delete circleOptions.icon
      if (circleOptions.title) delete circleOptions.title

      var circle = L.circle(latlng, { radius: radius, ...circleOptions })
      drawnItems.addLayer(circle)
    } else {
      L.geoJSON(feature, {
        onEachFeature: function (f, layer) {
          drawnItems.addLayer(layer)
        },
      })
    }
  })
  return drawnItems
}

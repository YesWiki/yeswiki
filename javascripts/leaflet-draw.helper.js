export function drawGeometries(
  drawnItems,
  features,
  popup = '',
  id = 'unknown',
) {
  if (features && features.length > 0) {
    features.forEach((feature) => {
      let layer
      if (feature.properties && feature.properties.type === 'circle') {
        const latlng = L.latLng(
          feature.geometry.coordinates[1],
          feature.geometry.coordinates[0],
        )
        const { radius } = feature.properties

        const circleOptions = { ...feature.properties }
        delete circleOptions.type
        delete circleOptions.radius
        if (circleOptions.icon) delete circleOptions.icon
        if (circleOptions.title) delete circleOptions.title
        circleOptions.className = `bazar-entry-geometry ${feature.properties.className || ''}`
        const circle = L.circle(latlng, { radius, ...circleOptions })
        if (popup && popup.length > 0) {
          circle.bindPopup(() => popup)
        }

        circle.on('add', function () {
          const pathElement = this.getElement()
          if (pathElement) {
            pathElement.setAttribute('stroke', 'blue')
            pathElement.setAttribute('stroke-opacity', '1')
            pathElement.setAttribute('data-id', id)
            pathElement.setAttribute('data-type', 'circle')
          }
        })

        circle.feature = feature
        layer = circle
      } else {
        layer = L.geoJSON(feature, {
          style: {
            color: 'blue',
            className: `bazar-entry-geometry ${feature.properties.className || ''}`,
          },
          pointToLayer(feature, latlng) {
            const customIcon = L.Icon.Default.extend({
              options: {
                className: `bazar-entry-geometry ${feature.properties.className || ''}`,
              },
            })

            return L.marker(latlng, { icon: new customIcon() })
          },
          onEachFeature(f, layer) {
            if (popup && popup.length > 0) {
              layer.bindPopup(() => popup)
            }
            if (layer.getElement) {
              layer.on('add', function () {
                const elem = this.getElement()
                if (elem) {
                  elem.setAttribute('data-id', id)
                  elem.setAttribute('data-type', f.geometry.type)
                }
              })
            }
            drawnItems.addLayer(layer)
          },
        })
      }
      layer.tag = id
      drawnItems.addLayer(layer)
    })
  }
  return drawnItems
}

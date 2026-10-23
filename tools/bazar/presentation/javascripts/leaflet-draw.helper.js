export function drawGeometries(
  drawnItems,
  features,
  popup = '',
  id = 'unknown',
) {
  if (features && features.length > 0) {
    features.forEach((feature) => {
      const properties = feature.properties || {}
      const className = `bazar-entry-geometry ${properties.className || ''}`
      if (properties.type === 'circle') {
        const latlng = L.latLng(
          feature.geometry.coordinates[1],
          feature.geometry.coordinates[0],
        )
        const { radius } = properties

        const circleOptions = { ...properties }
        delete circleOptions.type
        delete circleOptions.radius
        if (circleOptions.icon) delete circleOptions.icon
        if (circleOptions.title) delete circleOptions.title
        circleOptions.className = className
        const circle = L.circle(latlng, { radius, ...circleOptions })
        if (popup && popup.length > 0) {
          circle.bindPopup((_layer) => popup)
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
        circle.id_fiche = id
        drawnItems.addLayer(circle)
      } else {
        L.geoJSON(feature, {
          style: /* function (feature) {
			    return */ {
            color: 'blue',
            className,
          }, // ;
          pointToLayer(_f, latlng) {
            const customIcon = L.Icon.Default.extend({
              options: { className },
            })

            return L.marker(latlng, { icon: new customIcon() })
          },
          onEachFeature(f, subLayer) {
            if (popup && popup.length > 0) {
              subLayer.bindPopup((_l) => popup)
            }
            if (subLayer.getElement) {
              subLayer.on('add', function () {
                const elem = this.getElement()
                if (elem) {
                  elem.setAttribute('data-id', id)
                  elem.setAttribute('data-type', f.geometry.type)
                }
              })
            }
            subLayer.id_fiche = id
            drawnItems.addLayer(subLayer)
          },
        })
      }
    })
  }
  return drawnItems
}

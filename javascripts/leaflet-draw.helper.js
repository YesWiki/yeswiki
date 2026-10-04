function drawCircle(drawnItems, feature, properties, className, popup, id) {
  const latlng = L.latLng(
    feature.geometry.coordinates[1],
    feature.geometry.coordinates[0],
  )
  const { radius } = properties

  const circleOptions = { ...properties }
  delete circleOptions.type
  delete circleOptions.radius
  delete circleOptions.icon
  delete circleOptions.title
  circleOptions.className = className
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
  circle.tag = id
  drawnItems.addLayer(circle)
}

function drawFeature(drawnItems, feature, className, popup, id) {
  L.geoJSON(feature, {
    style: { color: 'blue', className },
    pointToLayer(_f, latlng) {
      const customIcon = L.Icon.Default.extend({ options: { className } })
      return L.marker(latlng, { icon: new customIcon() })
    },
    onEachFeature(f, subLayer) {
      if (popup && popup.length > 0) {
        subLayer.bindPopup(() => popup)
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
      subLayer.tag = id
      drawnItems.addLayer(subLayer)
    },
  })
}

/** Adds each stored feature to drawnItems as its own layer, skipping the ones that fail. */
export function drawGeometries(
  drawnItems,
  features,
  popup = '',
  id = 'unknown',
) {
  if (!Array.isArray(features)) return drawnItems
  features.forEach((feature) => {
    try {
      const properties = feature.properties || {}
      const className = `bazar-entry-geometry ${properties.className || ''}`
      if (properties.type === 'circle') {
        drawCircle(drawnItems, feature, properties, className, popup, id)
      } else {
        drawFeature(drawnItems, feature, className, popup, id)
      }
    } catch (e) {
      console.error(`Error drawing geometry for ${id}`, e)
    }
  })
  return drawnItems
}

/** Serialises drawnItems into a flat FeatureCollection, circles as points with a radius. */
export function drawnItemsToGeoJSON(drawnItems) {
  const data = { type: 'FeatureCollection', features: [] }

  drawnItems.eachLayer((layer) => {
    if (layer instanceof L.Circle) {
      const latLng = layer.getLatLng()
      data.features.push({
        type: 'Feature',
        properties: {
          ...(layer.feature?.properties ?? layer.options),
          type: 'circle',
          radius: layer.getRadius(),
        },
        geometry: { type: 'Point', coordinates: [latLng.lng, latLng.lat] },
      })
      return
    }
    const geoJSON = layer.toGeoJSON()
    if (geoJSON.type === 'FeatureCollection') {
      data.features.push(...geoJSON.features)
    } else {
      data.features.push(geoJSON)
    }
  })

  return data
}

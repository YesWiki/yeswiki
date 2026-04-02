export function drawGeometries(drawnItems, features, popup = '', id = 'unknown') {
  if (features && features.length > 0) {
    features.forEach(function(feature) {
      let layer;
      if (feature.properties && feature.properties.type === 'circle') {
        var latlng = L.latLng(
          feature.geometry.coordinates[1],
          feature.geometry.coordinates[0]
        )
        var radius = feature.properties.radius

        var circleOptions = { ...feature.properties }
        delete circleOptions.type
        delete circleOptions.radius
        if (circleOptions.icon) delete circleOptions.icon
        if (circleOptions.title) delete circleOptions.title
        circleOptions.className = 'bazar-entry-geometry ' + (feature.properties.className || '');
        var circle = L.circle(latlng, { radius: radius, ...circleOptions })
        if (popup && popup.length > 0) {
          circle.bindPopup(function(layer) {
            return popup;
          });
        }

        circle.on('add', function() {
          var pathElement = this.getElement();
          if (pathElement) {
            pathElement.setAttribute('stroke', 'blue');
            pathElement.setAttribute('stroke-opacity', '1');
            pathElement.setAttribute('data-id', id);
            pathElement.setAttribute('data-type', 'circle');
          }
        });

        circle.feature = feature;
        layer = circle;
      } else {
        layer = L.geoJSON(feature, {
          style:/* function (feature) {
			    return */{
            color: 'blue',
            className: 'bazar-entry-geometry ' + (feature.properties.className || '')
          }//;
          ,
          pointToLayer: function(feature, latlng) {
            var customIcon = L.Icon.Default.extend({
              options: {
                className: 'bazar-entry-geometry ' + (feature.properties.className || '')
              }
            });

            return L.marker(latlng, { icon: new customIcon() });
          },
          onEachFeature: function(f, layer) {
            if (popup && popup.length > 0) {
              layer.bindPopup(function(l) {
                return popup;
              });
            }
            if (layer.getElement) {
              layer.on('add', function() {
                var elem = this.getElement();
                if (elem) {
                  elem.setAttribute('data-id', id);
                  elem.setAttribute('data-type', f.geometry.type);
                }
              });
            }
            drawnItems.addLayer(layer);
          }
        });
      }
      layer.id_fiche = id;
      drawnItems.addLayer(layer);
    })
  }
  return drawnItems
}

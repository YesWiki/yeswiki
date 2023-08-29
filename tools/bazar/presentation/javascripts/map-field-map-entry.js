window.addEventListener('load', function(event) {
  let entryMaps = Array();
  $('.map-entry').each(function( index, value) {
    let $this = $(this);
    let mapFieldData = $this.data('map-field');
    // Init leaflet map
    entryMaps[index] = new L.Map($this.attr('id'), {
      scrollWheelZoom: mapFieldData.bazWheelZoom,
      zoomControl: mapFieldData.bazShowNav
    });
    var provider = L.tileLayer.provider(mapFieldData.mapProvider, mapFieldData.mapProviderCredentials);
    entryMaps[index].addLayer(provider);

    entryMaps[index].setView(new L.LatLng(mapFieldData.latitude, mapFieldData.longitude), mapFieldData.bazMapZoom);
    L.marker([mapFieldData.latitude, mapFieldData.longitude]).addTo(entryMaps[index] );
  })
});
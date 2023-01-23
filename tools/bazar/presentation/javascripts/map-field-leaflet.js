$(document).ready(function() {
  if (typeof mapFieldData == 'object' && 
    'bazWheelZoom' in mapFieldData &&
    'bazShowNav' in mapFieldData &&
    'mapProvider' in mapFieldData &&
    'mapProviderCredentials' in mapFieldData &&
    'bazMapCenterLat' in mapFieldData &&
    'bazMapCenterLon' in mapFieldData &&
    'bazMapZoom' in mapFieldData
    ){
    // Init leaflet map
    var map = new L.Map('osmmapform', {
        scrollWheelZoom: mapFieldData.bazWheelZoom,
        zoomControl: mapFieldData.bazShowNav
    });
    var geocodedmarker;
    var provider = L.tileLayer.provider(mapFieldData.mapProvider, mapFieldData.mapProviderCredentials);
    map.addLayer(provider);
    
    map.setView(new L.LatLng(mapFieldData.bazMapCenterLat, mapFieldData.bazMapCenterLon), mapFieldData.bazMapZoom);
    
    $("body").on("keyup keypress", "#bf_latitude, #bf_longitude", function(){
      var pattern = /^-?[\d]{1,3}[.][\d]+$/;
      var thisVal = $(this).val();
      if(!thisVal.match(pattern)) $(this).val($(this).val().replace(/[^\d.]/g,''));
    });
    $("body").on("blur", "#bf_latitude, #bf_longitude", function() {
        var point = L.latLng($("#bf_latitude").val(), $("#bf_longitude").val());
        geocodedmarker.setLatLng(point);
        map.panTo(point, {animate:true}).zoomIn();
    });
    var fields = ["#bf_adresse", "#bf_adresse1", "#bf_adresse2", "#bf_ville", "#bf_code_postal", "#bf_pays"]
    fields = fields.map((id) => $(id)).filter((field) => field.length > 0)

    function showAddress(map) {
        var address = "";
        fields.forEach((field) => address += field.val() + " ")
        console.log("geocode address", address);
        address = address.replace(/\\("|'|\\)/g, " ").trim();
        if (!address) {
            geocodedmarkerRefresh( map.getCenter() );
            return
        }
        geocodage( address, showAddressOk, showAddressError );
        return false;
    }
    function showAddressOk( lon, lat )
    {
        //console.log("showAddressOk: "+lon+", "+lat);
        geocodedmarkerRefresh( L.latLng( lat, lon ) );
    }

    function showAddressError( msg )
    {
        //console.log("showAddressError: "+msg);
        if ( msg == "not found" ) {
            alert(_t("BAZ_GEOLOC_NOT_FOUND"));
            geocodedmarkerRefresh( map.getCenter() );
        } else {
            alert(_t('BAZ_MAP_ERROR',{msg:msg}));
        }
    }
    function popupHtml( point ) {
        return `
            <div class="input-group" style="margin-bottom: 10px">
                <span class="input-group-addon">Lat</span>
                <input type="text" class="form-control bf_latitude" pattern="-?\\\d{1,3}\\\.\\\d+" value="${point.lat}" />
                <span class="input-group-addon">Lon</span>
                <input type="text" class="form-control bf_longitude" pattern="-?\\\d{1,3}\\\.\\\d+" value="${point.lng}" />
            </div>
            <div class="text-center">${_t('BAZ_ADJUST_MARKER_POSITION')}</div>
        `
    }

    function geocodedmarkerRefresh( point )
    {
        if (geocodedmarker) map.removeLayer(geocodedmarker);
        geocodedmarker = L.marker(point, {draggable:true}).addTo(map);
        geocodedmarker.bindPopup(popupHtml( geocodedmarker.getLatLng() ), {
            closeButton: false, 
            closeOnClick: false,
            minWidth: 300
        }).openPopup();
        map.setView(point, 18);
        // map.panTo( geocodedmarker.getLatLng(), {animate:true});
        $('#bf_latitude').val(point.lat);
        $('#bf_longitude').val(point.lng);

        geocodedmarker.on("dragend",function(ev){
            this.openPopup();
            var changedPos = ev.target.getLatLng();
            $('#bf_latitude').val(changedPos.lat);
            $('#bf_longitude').val(changedPos.lng);
            $('.bf_latitude').val(changedPos.lat);
            $('.bf_longitude').val(changedPos.lng);
        });
    }
    $('.btn-geolocate').on('click', function(){
        function onLocationFound(e) {
            $('#bf_latitude').val(e.latitude);
            $('#bf_longitude').val(e.longitude);
            geocodedmarkerRefresh(e.latlng);
            map.panTo( e.latlng, {animate:true});
        }
    
        function onLocationError(e) {
            $('#bf_latitude').val('');
            $('#bf_longitude').val('');
            console.log(e.message);
        }
    
        map.on('locationfound', onLocationFound);
        map.on('locationerror', onLocationError);
    
        map.locate({setView: true, maxZoom: 16});
    });
    $('.btn-geolocate-address').on('click', function(){showAddress(map);});
    $('body').on('change', '.bf_latitude, .bf_longitude', function(e) {
        if ($(this).is(":invalid")) {
            $('#bf_latitude').val('');
            $('#bf_longitude').val('');
            alert(_t('BAZ_NOT_VALID_GEOLOC_FORMAT'));
        } else {
            $('#bf_latitude').val($('.bf_latitude').val());
            $('#bf_longitude').val($('.bf_longitude').val());
            geocodedmarker.setLatLng([$('.bf_latitude').val(), $('.bf_longitude').val()]);
            map.panTo( geocodedmarker.getLatLng(), {animate:true});
        }
    })
    if ('latitude' in mapFieldData && typeof mapFieldData.latitude === 'string' && mapFieldData.latitude !== '' &&
        'longitude' in mapFieldData && typeof mapFieldData.longitude === 'string' && mapFieldData.longitude !== ''  ){
        showAddressOk(mapFieldData.longitude, mapFieldData.latitude)
    }
  }
});
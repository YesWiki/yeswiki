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

    function getParentGeocode({element}){
        if (!element){
            return null
        }
        const parent = $(element).closest('div.geocode-input')
        return (parent && parent.length > 0) ? parent : null
    }

    function getFieldNames({element,parent=null}){
        if (!element){
            return null
        }
        let foundParent = parent
        if (foundParent === null){
            foundParent = getParentGeocode({element})
        }
        if (foundParent === null){
            return null
        }
        const fieldNames = $(foundParent).data('fieldNames')
        if (typeof fieldNames === 'object' && fieldNames !== null){
            const fields = ['street','street1','street2','town','postalCode','county','state'].map((name)=>{
                return name in fieldNames ? fieldNames[name] : null
            }).filter((e)=>(typeof e === 'string' && e.length > 0))
            .map((name)=>$(`#${name}`))
            .filter((field) => field.length > 0)
            return {...fieldNames,fields}
        } else {
            return null
        }
    }

    function getLatLon({element}){
        const fieldNames = getFieldNames({element})
        const latitude = (fieldNames !== null && 'latitude' in fieldNames) ? fieldNames.latitude : 'bf_latitude'
        const longitude = (fieldNames !== null && 'longitude' in fieldNames) ? fieldNames.longitude : 'bf_longitude'
        return {latitude,longitude}
    }

    const fieldsToFollow = {
        latitude: [],
        longitude: []
    }
    $('.geocode-input').each(function(){
        const fieldNames = getFieldNames({element:$(this)})
        if (fieldNames !== null){
            let latitude = null
            let longitude = null
            if ('latitude' in fieldNames && !fieldsToFollow.latitude.includes(fieldNames.latitude)){
                fieldsToFollow.latitude.push(fieldNames.latitude)
                const latitudeField = $(`#${fieldNames.latitude}`)
                if (latitudeField && latitudeField.length > 0){
                    latitude = latitudeField.val()
                }
            }
            if ('longitude' in fieldNames && !fieldsToFollow.longitude.includes(fieldNames.longitude)){
                fieldsToFollow.longitude.push(fieldNames.longitude)
                const longitudeField = $(`#${fieldNames.longitude}`)
                if (longitudeField && longitudeField.length > 0){
                    longitude = longitudeField.val()
                }
            }
            if (longitude !== null && longitude != 0 && latitude !==null && latitude != 0){
                showAddressOk(longitude, latitude)
            }
        }
    })
    const cssToFollow = [...fieldsToFollow.latitude,...fieldsToFollow.longitude].map((e)=>`#${e}`).join(', ')
    if (cssToFollow.length > 0){
        $("body").on("keyup keypress", cssToFollow, function(){
          var pattern = /^-?[\d]{1,3}[.][\d]+$/;
          var thisVal = $(this).val();
          if(!thisVal.match(pattern)) $(this).val($(this).val().replace(/[^\d.]/g,''));
        });
        $("body").on("blur", cssToFollow, function() {
            const names = getLatLon({element:$(this)})
            showAddressOk( $(`#${names.longitude}`).val(), $(`#${names.latitude}`).val() )
        });
    }

    function showAddress(map,element) {
        var address = "";
        const fieldsNames = getFieldNames({element})
        fieldsNames.fields.forEach((field) => address += field.val() + " ")
        address = address.replace(/\\("|'|\\)/g, " ").trim();
        if (!address) {
            geocodedmarkerRefresh( map.getCenter() );
            return
        }
        geolocationHelper.geolocateRetryWithoutNumberAtBeginningIfNeeded(address)
            .then((data)=>{
                if (data.length > 0 && data[0].latitude.length > 0 && data[0].longitude.length > 0){
                    showAddressOk(data[0].longitude, data[0].latitude )
                } else {
                    showAddressError('bad format')
                }
            })
            .catch((error)=>{
                showAddressError(error instanceof Error ? Error.message : String(error))
            })
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
        
        const names = getLatLon({element:$(this)})
        function onLocationFound(e) {
            $(`#${names.latitude}`).val(e.latitude);
            $(`#${names.longitude}`).val(e.longitude);
            geocodedmarkerRefresh(e.latlng);
            map.panTo( e.latlng, {animate:true});
        }
    
        function onLocationError(e) {
            $(`#${names.latitude}`).val('');
            $(`#${names.longitude}`).val('');
            console.log(e.message);
        }
    
        map.on('locationfound', onLocationFound);
        map.on('locationerror', onLocationError);
    
        map.locate({setView: true, maxZoom: 16});
    });
    $('.btn-geolocate-address').on('click', function(){showAddress(map,$(this));});
    $('body').on('change', '.bf_latitude, .bf_longitude', function(e) {
        const names = getLatLon({element:$(this)})
        if ($(this).is(":invalid")) {
            $(`#${names.latitude}`).val('');
            $(`#${names.longitude}`).val('');
            alert(_t('BAZ_NOT_VALID_GEOLOC_FORMAT'));
        } else {
            $(`#${names.latitude}`).val($(this).parent().find('.bf_latitude').first().val());
            $(`#${names.longitude}`).val($(this).parent().find('.bf_longitude').first().val());
            geocodedmarker.setLatLng([$(`#${names.latitude}`).val(), $(`#${names.longitude}`).val()]);
            map.panTo( geocodedmarker.getLatLng(), {animate:true});
        }
    })
  }
});
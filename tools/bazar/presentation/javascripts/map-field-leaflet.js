$(document).ready(() => {
  if (typeof mapFieldData == 'object'
    && 'bazWheelZoom' in mapFieldData
    && 'bazShowNav' in mapFieldData
    && 'mapProvider' in mapFieldData
    && 'mapProviderCredentials' in mapFieldData
    && 'bazMapCenterLat' in mapFieldData
    && 'bazMapCenterLon' in mapFieldData
    && 'bazMapZoom' in mapFieldData
  ) {
    // Init leaflet map
    const map = new L.Map('osmmapform', {
      scrollWheelZoom: mapFieldData.bazWheelZoom,
      zoomControl: mapFieldData.bazShowNav
    })
    let geocodedmarker
    const provider = L.tileLayer.provider(mapFieldData.mapProvider, mapFieldData.mapProviderCredentials)
    map.addLayer(provider)

    map.setView(new L.LatLng(mapFieldData.bazMapCenterLat, mapFieldData.bazMapCenterLon), mapFieldData.bazMapZoom)

    function getParentGeocode({ element }) {
      if (!element) {
        return null
      }
      const parent = $(element).closest('div.geocode-input')
      return (parent && parent.length > 0) ? parent : null
    }

    function getFieldNames({ element, parent = null }) {
      if (!element) {
        return null
      }
      let foundParent = parent
      if (foundParent === null) {
        foundParent = getParentGeocode({ element })
      }
      if (foundParent === null) {
        return null
      }
      const fieldNames = $(foundParent).data('fieldNames')
      if (typeof fieldNames === 'object' && fieldNames !== null) {
        const fields = ['street', 'street1', 'street2', 'town', 'postalCode', 'county', 'state'].map((name) => (name in fieldNames ? fieldNames[name] : null)).filter((e) => (typeof e === 'string' && e.length > 0))
          .map((name) => $(`#${name}`))
          .filter((field) => field.length > 0)
        return { ...fieldNames, fields }
      }
      return null
    }

    function getLatLon({ element }) {
      const fieldNames = getFieldNames({ element })
      const latitude = (fieldNames !== null && 'latitude' in fieldNames) ? fieldNames.latitude : 'bf_latitude'
      const longitude = (fieldNames !== null && 'longitude' in fieldNames) ? fieldNames.longitude : 'bf_longitude'
      return { latitude, longitude }
    }

    const fieldsToFollow = {
      latitude: [],
      longitude: []
    }
    $('.geocode-input').each(function() {
      const fieldNames = getFieldNames({ element: $(this) })
      if (fieldNames !== null) {
        let latitude = null
        let longitude = null
        if ('latitude' in fieldNames && !fieldsToFollow.latitude.includes(fieldNames.latitude)) {
          fieldsToFollow.latitude.push(fieldNames.latitude)
          const latitudeField = $(`#${fieldNames.latitude}`)
          if (latitudeField && latitudeField.length > 0) {
            latitude = latitudeField.val()
          }
        }
        if ('longitude' in fieldNames && !fieldsToFollow.longitude.includes(fieldNames.longitude)) {
          fieldsToFollow.longitude.push(fieldNames.longitude)
          const longitudeField = $(`#${fieldNames.longitude}`)
          if (longitudeField && longitudeField.length > 0) {
            longitude = longitudeField.val()
          }
        }
        if (longitude !== null && longitude != 0 && latitude !== null && latitude != 0) {
          showAddressOk(longitude, latitude)
        }
      }
    })
    const cssToFollow = [...fieldsToFollow.latitude, ...fieldsToFollow.longitude].map((e) => `#${e}`).join(', ')
    if (cssToFollow.length > 0) {
      $('body').on('keyup keypress', cssToFollow, function() {
        const pattern = /^-?[\d]{1,3}[.][\d]+$/
        const thisVal = $(this).val()
        if (!thisVal.match(pattern)) $(this).val($(this).val().replace(/[^\d.]/g, ''))
      })
      $('body').on('blur', cssToFollow, function() {
        const names = getLatLon({ element: $(this) })
        showAddressOk($(`#${names.longitude}`).val(), $(`#${names.latitude}`).val())
      })
    }

    function showAddress(map, element) {
      let address = ''
      const fieldsNames = getFieldNames({ element })
      address = fieldsNames.fields.map((field) => field.val()).join(' ')
      address = address.replace(/\\("|'|\\)/g, ' ').trim()
      if (!address) {
        geocodedmarkerRefresh(map.getCenter())
        return
      }
      const formattedFields = {}
      fieldsNames.fields.forEach((field) => {
        const id = field.prop('id');
        ['street', 'street1', 'street2', 'town', 'postalCode', 'county', 'state'].forEach((key) => {
          if (key in fieldsNames && typeof fieldsNames[key] === 'string' && fieldsNames[key] === id) {
            const val = field.val()
            if (val.length > 0) {
              formattedFields[key] = val
            }
          }
        })
      })
      let setToTry = []
      if ('street' in formattedFields && 'street1' in formattedFields && 'street2' in formattedFields) {
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street} ${formattedFields.street1} ${formattedFields.street2}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street} ${formattedFields.street1}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street} ${formattedFields.street2}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street1} ${formattedFields.street2}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street1}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street2}` } } })
        const withoutStreet = { ...formattedFields }
        delete withoutStreet.street
        setToTry.push({ method: 'geolocate', fields: withoutStreet })
      } else if ('street' in formattedFields && 'street1' in formattedFields) {
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street} ${formattedFields.street1}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street1}` } } })
        const withoutStreet = { ...formattedFields }
        delete withoutStreet.street
        setToTry.push({ method: 'geolocate', fields: withoutStreet })
      } else if ('street' in formattedFields && 'street2' in formattedFields) {
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street} ${formattedFields.street2}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street}` } } })
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street2}` } } })
        const withoutStreet = { ...formattedFields }
        delete withoutStreet.street
        setToTry.push({ method: 'geolocate', fields: withoutStreet })
      } else if ('street' in formattedFields) {
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields, ...{ street: `${formattedFields.street}` } } })
        const withoutStreet = { ...formattedFields }
        delete withoutStreet.street
        setToTry.push({ method: 'geolocate', fields: withoutStreet })
      } else {
        setToTry.push({ method: 'geolocate', fields: { ...formattedFields } })
      }
      setToTry.push({ method: 'geolocateRetryWithoutNumberAtBeginningIfNeeded', fields: address })

      let manageData = null
      const processNextSet = async() => {
        if (setToTry.length == 0) {
          throw new Error(_t('GEOLOCATER_NOT_FOUND', { addr: address }))
        } else {
          const newSet = setToTry[0]
          setToTry = setToTry.slice(1)
          return await geolocationHelper[newSet.method](newSet.fields).then(manageData)
        }
      }
      manageData = async(data) => {
        if (data.length > 0 && data[0].latitude.length > 0 && data[0].longitude.length > 0) {
          return data
        }
        return await processNextSet().then((data) => data)
      }
      processNextSet()
        .then((data) => {
          showAddressOk(data[0].longitude, data[0].latitude)
        })
        .catch((error) => {
          showAddressError(error instanceof Error ? error.message : String(error))
        })

      return false
    }
    function showAddressOk(lon, lat) {
      // console.log("showAddressOk: "+lon+", "+lat);
      geocodedmarkerRefresh(L.latLng(lat, lon))
    }

    function showAddressError(msg) {
      // console.log("showAddressError: "+msg);
      if (msg == 'not found') {
        alert(_t('BAZ_GEOLOC_NOT_FOUND'))
        geocodedmarkerRefresh(map.getCenter())
      } else {
        alert(_t('BAZ_MAP_ERROR', { msg }))
      }
    }
    function popupHtml(point) {
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

    function geocodedmarkerRefresh(point) {
      if (geocodedmarker) map.removeLayer(geocodedmarker)
      geocodedmarker = L.marker(point, { draggable: true }).addTo(map)
      geocodedmarker.bindPopup(popupHtml(geocodedmarker.getLatLng()), {
        closeButton: false,
        closeOnClick: false,
        minWidth: 300
      }).openPopup()
      map.setView(point, 18)
      // map.panTo( geocodedmarker.getLatLng(), {animate:true});
      $('#bf_latitude').val(point.lat)
      $('#bf_longitude').val(point.lng)

      geocodedmarker.on('dragend', function(ev) {
        this.openPopup()
        const changedPos = ev.target.getLatLng()
        $('#bf_latitude').val(changedPos.lat)
        $('#bf_longitude').val(changedPos.lng)
        $('.bf_latitude').val(changedPos.lat)
        $('.bf_longitude').val(changedPos.lng)
      })
    }
    $('.btn-geolocate').on('click', function() {
      const names = getLatLon({ element: $(this) })
      function onLocationFound(e) {
        $(`#${names.latitude}`).val(e.latitude)
        $(`#${names.longitude}`).val(e.longitude)
        geocodedmarkerRefresh(e.latlng)
        map.panTo(e.latlng, { animate: true })
      }

      function onLocationError(e) {
        $(`#${names.latitude}`).val('')
        $(`#${names.longitude}`).val('')
        console.log(e.message)
      }

      map.on('locationfound', onLocationFound)
      map.on('locationerror', onLocationError)

      map.locate({ setView: true, maxZoom: 16 })
    })
    $('.btn-geolocate-address').on('click', function() { showAddress(map, $(this)) })
    $('body').on('change', '.bf_latitude, .bf_longitude', function(e) {
      const names = getLatLon({ element: $(this) })
      if ($(this).is(':invalid')) {
        $(`#${names.latitude}`).val('')
        $(`#${names.longitude}`).val('')
        alert(_t('BAZ_NOT_VALID_GEOLOC_FORMAT'))
      } else {
        $(`#${names.latitude}`).val($(this).parent().find('.bf_latitude').first()
          .val())
        $(`#${names.longitude}`).val($(this).parent().find('.bf_longitude').first()
          .val())
        geocodedmarker.setLatLng([$(`#${names.latitude}`).val(), $(`#${names.longitude}`).val()])
        map.panTo(geocodedmarker.getLatLng(), { animate: true })
      }
    })
  }
})

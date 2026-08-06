// map-leaflet.js — geolocation map field (ticket 16: vanilla JS around the same
// Leaflet/Leaflet.draw logic). Two pre-existing bugs fixed along the way:
// showAddressError referenced an undefined `msg` variable, and the popup
// validity check ran :invalid against the wrapper <div> (never matching) instead
// of its inputs.
import { drawGeometries } from '../leaflet-draw.helper.js'

// Ticket 14: ywInit rather than DOMContentLoaded, so a map field arriving in a fragment --
// the form designer's field preview, and every htmx-swapped entry form after it -- becomes a
// map. The immediate sweep matters here specifically: this file is loaded *by* the fragment
// that needs it, so it is still downloading when htmx fires htmx:load for that content.
ywInit((root) => {
  function drawnItemsToGeoJSON(pDrawnItems) {
    const vData = {
      type: 'FeatureCollection',
      features: [],
    }

    pDrawnItems.eachLayer((pLayer) => {
      if (pLayer instanceof L.Circle) {
        const cLatLng = pLayer.getLatLng()

        // For circles, store center and radius as custom properties
        vData.features.push({
          type: 'Feature',
          properties: {
            type: 'circle',
            radius: pLayer.getRadius(),
            ...pLayer.options,
          },
          geometry: {
            type: 'Point', // GeoJSON still sees it as a point
            coordinates: [cLatLng.lng, cLatLng.lat],
          },
        })
      } else {
        vData.features.push(pLayer.toGeoJSON())
      }
    })

    return vData
  }

  function parseJsonAttribute(element, attribute) {
    const raw = element.getAttribute(attribute)
    if (!raw) return null
    try {
      return JSON.parse(raw)
    } catch {
      return null
    }
  }

  const scope = root && root.querySelectorAll ? root : document
  const maps = [
    ...scope.querySelectorAll('.geocode-input:not(.yw-initialized)'),
  ]
  if (scope.matches && scope.matches('.geocode-input:not(.yw-initialized)'))
    maps.unshift(scope)

  maps.forEach((cMe) => {
    cMe.classList.add('yw-initialized')

    const cMapFieldData = parseJsonAttribute(cMe, 'data-map-field-data')

    if (
      typeof cMapFieldData === 'object' &&
      cMapFieldData !== null &&
      'bazWheelZoom' in cMapFieldData &&
      'bazShowNav' in cMapFieldData &&
      'mapProvider' in cMapFieldData &&
      'mapProviderCredentials' in cMapFieldData &&
      'bazMapCenterLat' in cMapFieldData &&
      'bazMapCenterLon' in cMapFieldData &&
      'bazMapZoom' in cMapFieldData
    ) {
      let vGeocodedMarker

      const cName = cMe.getAttribute('name')

      // Scoped to this field rather than document.getElementById: a map may now be rendered
      // as a fragment (a designer preview card) where element ids are rewritten to stay
      // unique within the page, and where several maps can coexist. The three hidden inputs
      // already carry distinct classes for exactly this. Ids that legitimately refer to
      // *other* fields of the entry form (the address autocomplete below, the popup inputs
      // leaflet injects) stay document-wide -- they are not part of this element's subtree.
      const byId = (id) => document.getElementById(id)
      const setById = (id, value) => {
        const el = byId(id)
        if (el) el.value = value
      }

      const cLatitude = cMe.querySelector('.yw-latitude-input')
      const cLongitude = cMe.querySelector('.yw-longitude-input')
      const cGeometries = cMe.querySelector('.yw-geometries-input')

      const cGeolocateButton = cMe.querySelector('.btn-geolocate')
      const cGeolocateAddressButton = cMe.querySelector(
        '.btn-geolocate-address',
      )
      const cMoveToAddressButton = cMe.querySelector('.btn-move-to-address')

      const cFieldNames = parseJsonAttribute(cMe, 'data-field-names') || {}

      const cFields = [
        'street',
        'street1',
        'street2',
        'town',
        'postalCode',
        'county',
        'state',
      ].reduce((pAcc, pName) => {
        if (pName in cFieldNames && cFieldNames[pName].trim() !== '') {
          const cField = byId(cFieldNames[pName])

          if (cField) pAcc[pName] = cField // eslint-disable-line no-param-reassign
        }

        return pAcc
      }, {})

      // Init leaflet map
      const cMap = new L.Map(cMe.querySelector('.yw-geolocation-map'), {
        scrollWheelZoom: cMapFieldData.bazWheelZoom,
        zoomControl: cMapFieldData.bazShowNav,
      })
      const cProvider = L.tileLayer.provider(
        cMapFieldData.mapProvider,
        cMapFieldData.mapProviderCredentials,
      )

      cMap.addLayer(cProvider)

      cMap.setView(
        new L.LatLng(
          cMapFieldData.bazMapCenterLat,
          cMapFieldData.bazMapCenterLon,
        ),
        cMapFieldData.bazMapZoom,
      )

      if (cMapFieldData.hasGeometries) {
        let vDrawnItems = L.featureGroup().addTo(cMap)

        L.drawLocal.edit.toolbar.actions.save.title = _t('SAVE_CHANGES_TITLE')
        L.drawLocal.edit.toolbar.actions.save.text = _t('SAVE_BUTTON_TEXT')
        L.drawLocal.edit.toolbar.actions.cancel.title = _t(
          'CANCEL_EDITING_TITLE',
        )
        L.drawLocal.edit.toolbar.actions.cancel.text = _t('CANCEL_BUTTON_TEXT')
        L.drawLocal.edit.toolbar.actions.clearAll.title = _t(
          'CLEAR_ALL_LAYERS_TITLE',
        )
        L.drawLocal.edit.toolbar.actions.clearAll.text = _t(
          'CLEAR_ALL_BUTTON_TEXT',
        )
        L.drawLocal.edit.toolbar.buttons.edit = _t('EDIT_LAYERS_BUTTON')
        L.drawLocal.edit.toolbar.buttons.editDisabled = _t(
          'EDIT_DISABLED_BUTTON',
        )
        L.drawLocal.edit.toolbar.buttons.remove = _t('DELETE_LAYERS_BUTTON')
        L.drawLocal.edit.toolbar.buttons.removeDisabled = _t(
          'DELETE_DISABLED_BUTTON',
        )
        L.drawLocal.edit.handlers.edit.tooltip.text = _t('EDIT_TOOLTIP_TEXT')
        L.drawLocal.edit.handlers.edit.tooltip.subtext = _t(
          'EDIT_TOOLTIP_SUBTEXT',
        )
        L.drawLocal.edit.handlers.remove.tooltip.text = _t(
          'REMOVE_TOOLTIP_TEXT',
        )
        L.drawLocal.draw.toolbar.actions.title = _t('CANCEL_DRAWING_TITLE')
        L.drawLocal.draw.toolbar.actions.text = _t('CANCEL_BUTTON_TEXT')
        L.drawLocal.draw.toolbar.finish.title = _t('FINISH_DRAWING_TITLE')
        L.drawLocal.draw.toolbar.finish.text = _t('FINISH_BUTTON_TEXT')
        L.drawLocal.draw.toolbar.undo.title = _t('DELETE_LAST_POINT_TITLE')
        L.drawLocal.draw.toolbar.undo.text = _t('DELETE_LAST_POINT_TEXT')
        L.drawLocal.draw.toolbar.buttons.polyline = _t('DRAW_POLYLINE_BUTTON')
        L.drawLocal.draw.toolbar.buttons.polygon = _t('DRAW_POLYGON_BUTTON')
        L.drawLocal.draw.toolbar.buttons.rectangle = _t('DRAW_RECTANGLE_BUTTON')
        L.drawLocal.draw.toolbar.buttons.circle = _t('DRAW_CIRCLE_BUTTON')
        L.drawLocal.draw.toolbar.buttons.marker = _t('DRAW_MARKER_BUTTON')
        L.drawLocal.draw.toolbar.buttons.circlemarker = _t(
          'DRAW_CIRCLE_MARKER_BUTTON',
        )
        L.drawLocal.draw.handlers.circle.tooltip.start = _t(
          'CIRCLE_TOOLTIP_START',
        )
        L.drawLocal.draw.handlers.circle.radius = _t('CIRCLE_RADIUS_LABEL')
        L.drawLocal.draw.handlers.circlemarker.tooltip.start = _t(
          'CIRCLE_MARKER_TOOLTIP_START',
        )
        L.drawLocal.draw.handlers.marker.tooltip.start = _t(
          'MARKER_TOOLTIP_START',
        )
        L.drawLocal.draw.handlers.polygon.tooltip.start = _t(
          'POLYGON_TOOLTIP_START',
        )
        L.drawLocal.draw.handlers.polygon.tooltip.cont = _t(
          'POLYGON_TOOLTIP_CONT',
        )
        L.drawLocal.draw.handlers.polygon.tooltip.end = _t(
          'POLYGON_TOOLTIP_END',
        )
        L.drawLocal.draw.handlers.polyline.error = _t('POLYLINE_ERROR')
        L.drawLocal.draw.handlers.polyline.tooltip.start = _t(
          'POLYLINE_TOOLTIP_START',
        )
        L.drawLocal.draw.handlers.polyline.tooltip.cont = _t(
          'POLYLINE_TOOLTIP_CONT',
        )
        L.drawLocal.draw.handlers.polyline.tooltip.end = _t(
          'POLYLINE_TOOLTIP_END',
        )
        L.drawLocal.draw.handlers.rectangle.tooltip.start = _t(
          'RECTANGLE_TOOLTIP_START',
        )
        L.drawLocal.draw.handlers.simpleshape.tooltip.end = _t(
          'SIMPLE_SHAPE_TOOLTIP_END',
        )

        if (cMapFieldData.geometries) {
          vDrawnItems = drawGeometries(
            vDrawnItems,
            cMapFieldData.geometries.features,
          )
        }

        cMap.addControl(
          new L.Control.Draw({
            edit: {
              featureGroup: vDrawnItems,
              remove: true,
              poly: { allowIntersection: false },
            },
            draw: {
              position: 'topleft',
              polyline: cMapFieldData.chosenGeometries.includes('line'),
              polygon: cMapFieldData.chosenGeometries.includes('polygon'),
              rectangle: cMapFieldData.chosenGeometries.includes('rectangle'),
              circle: cMapFieldData.chosenGeometries.includes('circle'),
              circlemarker: false,
              marker: false,
            },
          }),
        )

        const syncGeometries = () => {
          if (cGeometries) {
            cGeometries.value = JSON.stringify(drawnItemsToGeoJSON(vDrawnItems))
          }
        }

        cMap.on(L.Draw.Event.CREATED, (e) => {
          vDrawnItems.addLayer(e.layer)
          syncGeometries()
        })

        cMap.on('draw:edited', syncGeometries)

        cMap.on(L.Draw.Event.DELETED, syncGeometries)

        cMap.whenReady(() => {
          const bounds = vDrawnItems.getBounds()

          if (bounds.isValid()) {
            cMap.fitBounds(bounds, { padding: [50, 50] })
          }
        })
      }

      function popupHtml(pPoint) {
        return `
          <div id="${cName}_geolocation_popup" class="input-group" style="margin-bottom: 10px">
            <span class="input-group-addon">Lat</span>
            <input id="${cName}_latitude_popup" type="text" class="yw-input"
              pattern="-?\\d{1,3}\\.\\d+" value="${pPoint.lat}" />
            <span class="input-group-addon">Lon</span>
            <input id="${cName}_longitude_popup" type="text" class="yw-input"
              pattern="-?\\d{1,3}\\.\\d+" value="${pPoint.lng}" />
          </div>
          <div class="text-center">${_t('BAZ_ADJUST_MARKER_POSITION')}</div>
        `
      }

      function geocodedmarkerRefresh(pPoint) {
        if (vGeocodedMarker) cMap.removeLayer(vGeocodedMarker)

        if (!pPoint) {
          if (cLatitude) cLatitude.value = ''
          if (cLongitude) cLongitude.value = ''
          setById(`${cName}_latitude_popup`, '')
          setById(`${cName}_longitude_popup`, '')
          return
        }

        if (cMapFieldData.chosenGeometries.includes('marker')) {
          vGeocodedMarker = L.marker(pPoint, { draggable: true }).addTo(cMap)

          cMap.setView(pPoint, 18)

          vGeocodedMarker
            .bindPopup(popupHtml(vGeocodedMarker.getLatLng()), {
              closeButton: false,
              closeOnClick: false,
              minWidth: 300,
            })
            .openPopup()

          if (cLatitude) cLatitude.value = pPoint.lat
          if (cLongitude) cLongitude.value = pPoint.lng

          vGeocodedMarker.on('dragend', function (ev) {
            this.openPopup()
            const changedPos = ev.target.getLatLng()
            if (cLatitude) cLatitude.value = changedPos.lat
            if (cLongitude) cLongitude.value = changedPos.lng
            setById(`${cName}_latitude_popup`, changedPos.lat)
            setById(`${cName}_longitude_popup`, changedPos.lng)
          })
        } else {
          // remove formerly encoded marker position
          if (cLatitude) cLatitude.value = ''
          if (cLongitude) cLongitude.value = ''
          setById(`${cName}_latitude_popup`, '')
          setById(`${cName}_longitude_popup`, '')
        }
      }

      function showAddressOk(pLatitude, pLongitude, pMove) {
        if (pMove) {
          cMap.flyTo([pLatitude, pLongitude], cMap.getMaxZoom())
        } else {
          geocodedmarkerRefresh(L.latLng(pLatitude, pLongitude))
        }
      }

      function showAddressError(pMessage) {
        if (pMessage === 'not found') {
          alert(_t('BAZ_GEOLOC_NOT_FOUND'))
          geocodedmarkerRefresh()
        } else {
          alert(_t('BAZ_MAP_ERROR', { pMessage }))
        }
      }

      function showAddress(pMove = false) {
        const lAddress = Object.values(cFields)
          .map((pField) => pField.value)
          .join(' ')
          .replace(/\\("|'|\\)/g, ' ')
          .trim()

        if (!lAddress) {
          geocodedmarkerRefresh()
          return
        }

        const formattedFields = {}

        Object.keys(cFields).forEach((pName) => {
          formattedFields[pName] = cFields[pName].value
        })

        let setToTry = []
        const pushStreetVariant = (street) => {
          setToTry.push({
            method: 'geolocate',
            fields: { ...formattedFields, ...{ street } },
          })
        }
        const pushWithoutStreet = () => {
          const withoutStreet = { ...formattedFields }
          delete withoutStreet.street
          setToTry.push({ method: 'geolocate', fields: withoutStreet })
        }

        if (
          'street' in formattedFields &&
          'street1' in formattedFields &&
          'street2' in formattedFields
        ) {
          pushStreetVariant(
            `${formattedFields.street} ${formattedFields.street1} ${formattedFields.street2}`,
          )
          pushStreetVariant(
            `${formattedFields.street} ${formattedFields.street1}`,
          )
          pushStreetVariant(
            `${formattedFields.street} ${formattedFields.street2}`,
          )
          pushStreetVariant(`${formattedFields.street}`)
          pushStreetVariant(
            `${formattedFields.street1} ${formattedFields.street2}`,
          )
          pushStreetVariant(`${formattedFields.street1}`)
          pushStreetVariant(`${formattedFields.street2}`)
          pushWithoutStreet()
        } else if (
          'street' in formattedFields &&
          'street1' in formattedFields
        ) {
          pushStreetVariant(
            `${formattedFields.street} ${formattedFields.street1}`,
          )
          pushStreetVariant(`${formattedFields.street}`)
          pushStreetVariant(`${formattedFields.street1}`)
          pushWithoutStreet()
        } else if (
          'street' in formattedFields &&
          'street2' in formattedFields
        ) {
          pushStreetVariant(
            `${formattedFields.street} ${formattedFields.street2}`,
          )
          pushStreetVariant(`${formattedFields.street}`)
          pushStreetVariant(`${formattedFields.street2}`)
          pushWithoutStreet()
        } else if ('street' in formattedFields) {
          pushStreetVariant(`${formattedFields.street}`)
          pushWithoutStreet()
        } else {
          setToTry.push({ method: 'geolocate', fields: { ...formattedFields } })
        }

        setToTry.push({
          method: 'geolocateRetryWithoutNumberAtBeginningIfNeeded',
          fields: lAddress,
        })

        let manageData = null

        const processNextSet = () => {
          if (setToTry.length === 0) {
            throw new Error(_t('GEOLOCATER_NOT_FOUND', { addr: lAddress }))
          }
          const newSet = setToTry[0]
          setToTry = setToTry.slice(1)
          return geolocationHelper[newSet.method](newSet.fields).then(
            manageData,
          )
        }

        manageData = (pData) => {
          if (
            pData.length > 0 &&
            pData[0].latitude.length > 0 &&
            pData[0].longitude.length > 0
          ) {
            return pData
          }

          return processNextSet()
        }

        processNextSet()
          .then((pData) => {
            showAddressOk(pData[0].latitude, pData[0].longitude, pMove)
          })
          .catch((error) => {
            showAddressError(
              error instanceof Error ? error.message : String(error),
            )
          })
      }

      const vLatitude = cLatitude ? cLatitude.value : null
      const vLongitude = cLongitude ? cLongitude.value : null

      if (
        vLatitude !== null &&
        vLatitude != 0 && // eslint-disable-line eqeqeq
        vLongitude !== null &&
        vLongitude != 0 // eslint-disable-line eqeqeq
      ) {
        showAddressOk(vLatitude, vLongitude)
      }

      const isCoordinateInput = (target) =>
        target.id === `${cName}_latitude` || target.id === `${cName}_longitude`

      const sanitizeCoordinate = (e) => {
        if (!isCoordinateInput(e.target)) return
        const input = e.target
        const pattern = /^-?[\d]{1,3}[.][\d]+$/

        if (!input.value.match(pattern)) {
          input.value = input.value.replace(/[^\d.]/g, '')
        }
      }
      document.body.addEventListener('keyup', sanitizeCoordinate)
      document.body.addEventListener('keypress', sanitizeCoordinate)

      document.body.addEventListener(
        'blur',
        (e) => {
          if (!isCoordinateInput(e.target)) return
          showAddressOk(
            cLatitude ? cLatitude.value : '',
            cLongitude ? cLongitude.value : '',
          )
        },
        true,
      )

      if (cGeolocateButton) {
        cGeolocateButton.addEventListener('click', () => {
          function onLocationFound(e) {
            if (cLatitude) cLatitude.value = e.latitude
            if (cLongitude) cLongitude.value = e.longitude
            geocodedmarkerRefresh(e.latlng)
            cMap.panTo(e.latlng, { animate: true })
          }

          function onLocationError(e) {
            if (cLatitude) cLatitude.value = ''
            if (cLongitude) cLongitude.value = ''
            console.log(e.message)
          }

          cMap.on('locationfound', onLocationFound)
          cMap.on('locationerror', onLocationError)

          cMap.locate({ setView: true, maxZoom: 16 })
        })
      }

      if (cMoveToAddressButton) {
        cMoveToAddressButton.addEventListener('click', () => {
          showAddress(true)
        })
      }

      if (cGeolocateAddressButton) {
        cGeolocateAddressButton.addEventListener('click', () => {
          showAddress()
        })
      }

      document.body.addEventListener('change', (e) => {
        const popup = e.target.closest(`#${cName}_geolocation_popup`)
        if (!popup) return
        const latitudePopup = byId(`${cName}_latitude_popup`)
        const longitudePopup = byId(`${cName}_longitude_popup`)
        const invalid =
          (latitudePopup && !latitudePopup.checkValidity()) ||
          (longitudePopup && !longitudePopup.checkValidity())
        if (invalid) {
          if (cLatitude) cLatitude.value = ''
          if (cLongitude) cLongitude.value = ''
          alert(_t('BAZ_NOT_VALID_GEOLOC_FORMAT'))
        } else {
          const cLatitudePopup = latitudePopup ? latitudePopup.value : ''
          const cLongitudePopup = longitudePopup ? longitudePopup.value : ''

          if (cLatitude) cLatitude.value = cLatitudePopup
          if (cLongitude) cLongitude.value = cLongitudePopup

          vGeocodedMarker.setLatLng([cLatitudePopup, cLongitudePopup])

          cMap.panTo(vGeocodedMarker.getLatLng(), { animate: true })
        }
      })
    }
  })
})

import { drawGeometries } from '../leaflet-draw.helper.js'

$(document).ready(() => {

	function drawnItemsToGeoJSON(pDrawnItems) {
		var vData = {
			type: 'FeatureCollection',
			features: [],
		}
		
		pDrawnItems.eachLayer(function (pLayer) {
			if (pLayer instanceof L.Circle) {
			
				const cLatLng = pLayer.getLatLng()
			
				// For circles, store center and radius as custom properties
				vData.features.push ({
					type: 'Feature',
					properties: {
					  type: 'circle',
					  radius: pLayer.getRadius(),
					  ...pLayer.options,
					},
					geometry: {
					  type: 'Point', // GeoJSON still sees it as a point
					  coordinates: [cLatLng.lng, cLatLng.lat],
					}
				})
			} else {
			  vData.features.push(pLayer.toGeoJSON())
			}
		})
		
		return vData
	}

	$(".geocode-input:not(.yw-initialized)").each (function ()	
	{
		const cMe = $(this);
	
		cMe.addClass ("yw-initialized");

		const cMapFieldData = cMe.data ("map-field-data");
		
		if (
			typeof cMapFieldData == 'object' &&
			'bazWheelZoom' in cMapFieldData &&
			'bazShowNav' in cMapFieldData &&
			'mapProvider' in cMapFieldData &&
			'mapProviderCredentials' in cMapFieldData &&
			'bazMapCenterLat' in cMapFieldData &&
			'bazMapCenterLon' in cMapFieldData &&
			'bazMapZoom' in cMapFieldData
		) {		  
			var vGeocodedMarker
		
			const cName = cMe.attr ("name");
			
			const cLatitude = cMe.find (`#${cName}_latitude`);

			const cLongitude = cMe.find (`#${cName}_longitude`);
			
			const cGeometries = cMe.find (`#${cName}_geometries`);
			
			const cGeolocateButton = cMe.find (`.btn-geolocate`);
			const cGeolocateAddressButton = cMe.find (`.btn-geolocate-address`);
			const cMoveToAddressButton = cMe.find (`.btn-move-to-address`);			
		
			const cFieldNames = cMe.data('fieldNames')
			
			const cFields = [
				  'street',
				  'street1',
				  'street2',
				  'town',
				  'postalCode',
				  'county',
				  'state'
			]
			.reduce((pAcc, pName) => {
				if (pName in cFieldNames && cFieldNames[pName].trim() !== '') 
				{
					const cField = $(`#${cFieldNames[pName]}`);
					
					if (cField.length > 0) pAcc[pName] = cField;
				}
				
			    return pAcc;
			}, {});
			
			// Init leaflet map
			const cMap = new L.Map(cMe.find (".yw-geolocation-map")[0], {
				scrollWheelZoom: cMapFieldData.bazWheelZoom,
				zoomControl: cMapFieldData.bazShowNav,
			})
			const cProvider = L.tileLayer.provider(
				cMapFieldData.mapProvider,
				cMapFieldData.mapProviderCredentials,
			)			
			
			cMap.addLayer(cProvider)

			cMap.setView(
				new L.LatLng(cMapFieldData.bazMapCenterLat, cMapFieldData.bazMapCenterLon),
				cMapFieldData.bazMapZoom,
			)

			if (cMapFieldData.hasGeometries) {
				var vDrawnItems = L.featureGroup().addTo(cMap)
				
				L.drawLocal.edit.toolbar.actions.save.title = _t('SAVE_CHANGES_TITLE')
				L.drawLocal.edit.toolbar.actions.save.text = _t('SAVE_BUTTON_TEXT')
				L.drawLocal.edit.toolbar.actions.cancel.title = _t('CANCEL_EDITING_TITLE')
				L.drawLocal.edit.toolbar.actions.cancel.text = _t('CANCEL_BUTTON_TEXT')
				L.drawLocal.edit.toolbar.actions.clearAll.title = _t('CLEAR_ALL_LAYERS_TITLE')
				L.drawLocal.edit.toolbar.actions.clearAll.text = _t('CLEAR_ALL_BUTTON_TEXT')
				L.drawLocal.edit.toolbar.buttons.edit = _t('EDIT_LAYERS_BUTTON')
				L.drawLocal.edit.toolbar.buttons.editDisabled = _t('EDIT_DISABLED_BUTTON')
				L.drawLocal.edit.toolbar.buttons.remove = _t('DELETE_LAYERS_BUTTON')
				L.drawLocal.edit.toolbar.buttons.removeDisabled = _t('DELETE_DISABLED_BUTTON')
				L.drawLocal.edit.handlers.edit.tooltip.text = _t('EDIT_TOOLTIP_TEXT')
				L.drawLocal.edit.handlers.edit.tooltip.subtext = _t('EDIT_TOOLTIP_SUBTEXT')
				L.drawLocal.edit.handlers.remove.tooltip.text = _t('REMOVE_TOOLTIP_TEXT')
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
				L.drawLocal.draw.toolbar.buttons.circlemarker = _t('DRAW_CIRCLE_MARKER_BUTTON')
				L.drawLocal.draw.handlers.circle.tooltip.start = _t('CIRCLE_TOOLTIP_START')
				L.drawLocal.draw.handlers.circle.radius = _t('CIRCLE_RADIUS_LABEL')
				L.drawLocal.draw.handlers.circlemarker.tooltip.start = _t('CIRCLE_MARKER_TOOLTIP_START')
				L.drawLocal.draw.handlers.marker.tooltip.start = _t('MARKER_TOOLTIP_START')
				L.drawLocal.draw.handlers.polygon.tooltip.start = _t('POLYGON_TOOLTIP_START')
				L.drawLocal.draw.handlers.polygon.tooltip.cont = _t('POLYGON_TOOLTIP_CONT')
				L.drawLocal.draw.handlers.polygon.tooltip.end = _t('POLYGON_TOOLTIP_END')
				L.drawLocal.draw.handlers.polyline.error = _t('POLYLINE_ERROR')
				L.drawLocal.draw.handlers.polyline.tooltip.start = _t('POLYLINE_TOOLTIP_START')
				L.drawLocal.draw.handlers.polyline.tooltip.cont = _t('POLYLINE_TOOLTIP_CONT')
				L.drawLocal.draw.handlers.polyline.tooltip.end = _t('POLYLINE_TOOLTIP_END')
				L.drawLocal.draw.handlers.rectangle.tooltip.start = _t('RECTANGLE_TOOLTIP_START')
				L.drawLocal.draw.handlers.simpleshape.tooltip.end = _t('SIMPLE_SHAPE_TOOLTIP_END')

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
							poly: {
							  allowIntersection: false,
							}
						},
						draw: {
							position: 'topleft',
							polyline: cMapFieldData.chosenGeometries.includes('line'),
							polygon: cMapFieldData.chosenGeometries.includes('polygon'),
							rectangle: cMapFieldData.chosenGeometries.includes('rectangle'),
							circle: cMapFieldData.chosenGeometries.includes('circle'),
							circlemarker: false,
							marker: false
						}
					})
				)

				cMap.on(L.Draw.Event.CREATED, function (e) {
					var vLayer = e.layer

					vDrawnItems.addLayer(vLayer)
					
					cGeometries.val(JSON.stringify(drawnItemsToGeoJSON(vDrawnItems)))
				})
				
				cMap.on('draw:edited', function (e) {
					cGeometries.val(JSON.stringify(drawnItemsToGeoJSON(vDrawnItems)))
				})
				
				cMap.on(L.Draw.Event.DELETED, function (e) {
					cGeometries.val(JSON.stringify(drawnItemsToGeoJSON(vDrawnItems)))
				})

				cMap.whenReady(function () {
					var bounds = vDrawnItems.getBounds()
					
					if (bounds.isValid()) {
						cMap.fitBounds(bounds, { padding : [50, 50] })
					}
				})
      }
      
      function showAddress(pMove = false) {
        let lAddress = ''
        
        lAddress = Object.values (cFields)
        .map((pField) => { return pField.val()})
        .join(' ')
        .replace(/\\("|'|\\)/g, ' ')
        .trim()

        if (!lAddress) {
          geocodedmarkerRefresh()
          return
        }

        const formattedFields = {}

        Object.keys (cFields)
        .forEach((pName) => {					
          formattedFields[pName] = cFields[pName].val()
        })

        let setToTry = []

        if (
        'street' in formattedFields &&
        'street1' in formattedFields &&
        'street2' in formattedFields
        ) {
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{
                street: `${formattedFields.street} ${formattedFields.street1} ${formattedFields.street2}`,
              }
            }
          })
          
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street} ${formattedFields.street1}` }
            }
          })
          
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street} ${formattedFields.street2}` }
            }
          })
        
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street}` },
            }
          })
          
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street1} ${formattedFields.street2}`}
            },
          })
          
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street1}` }
            }
          })
          
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street2}` }
            }
          })
        
          const withoutStreet = { ...formattedFields }
          delete withoutStreet.street
          setToTry.push({ method: 'geolocate', fields: withoutStreet })
          
        } else if ('street' in formattedFields && 'street1' in formattedFields) {
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street} ${formattedFields.street1}` }
            }
          })
        
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street}` }
            }
          })
          
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street1}` }
            }
          })
          
          const withoutStreet = { ...formattedFields }
          delete withoutStreet.street
          setToTry.push({ method: 'geolocate', fields: withoutStreet })
        } else if ('street' in formattedFields && 'street2' in formattedFields) {
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{
                street: `${formattedFields.street} ${formattedFields.street2}`
              }
            }
          })
          
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street}` }
            }
          })
          
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street2}` }
            }
          })
          
          const withoutStreet = { ...formattedFields }
          delete withoutStreet.street
          setToTry.push({ method: 'geolocate', fields: withoutStreet })
          
        } else if ('street' in formattedFields) {
          setToTry.push({
            method: 'geolocate',
            fields: {
              ...formattedFields,
              ...{ street: `${formattedFields.street}` },
            }
          })
          
          const withoutStreet = { ...formattedFields }
          delete withoutStreet.street
          setToTry.push({ method: 'geolocate', fields: withoutStreet })
        } else {
          setToTry.push({ method: 'geolocate', fields: { ...formattedFields } })
        }
        
        setToTry.push({
          method: 'geolocateRetryWithoutNumberAtBeginningIfNeeded',
          fields: lAddress
        })

        let manageData = null

        const processNextSet = async () => {
          if (setToTry.length == 0) {
            throw new Error(_t('GEOLOCATER_NOT_FOUND', { addr: lAddress }))
          } else {
            const newSet = setToTry[0]
            setToTry = setToTry.slice(1)
            return await geolocationHelper[newSet.method](newSet.fields).then(manageData)
          }
        }

        manageData = async (pData) => {
          if (
            pData.length > 0 &&
            pData[0].latitude.length > 0 &&
            pData[0].longitude.length > 0
          ) {
            return pData
          }
          
          return await processNextSet().then((pData) => pData)
        }

        processNextSet()
        .then((pData) => {
          showAddressOk(pData[0].latitude, pData[0].longitude, pMove)
        })
        .catch((error) => {
          showAddressError (error instanceof Error ? error.message : String(error))
        })

        return false
      }
      
      function showAddressOk(pLatitude, pLongitude, pMove) {
        if (pMove) {
          cMap.flyTo([pLatitude, pLongitude], cMap.getMaxZoom())
        } else {
          geocodedmarkerRefresh(L.latLng(pLatitude, pLongitude))
        }
      }

      function showAddressError(pMessage) {
        // console.log("showAddressError: " + pMessage);
        if (msg == 'not found') {
          alert(_t('BAZ_GEOLOC_NOT_FOUND'))
          geocodedmarkerRefresh()
        } else {
          alert(_t('BAZ_MAP_ERROR', { pMessage }))
        }
      }
      
      function popupHtml(pPoint) {
        return `
          <div id="${cName}_geolocation_popup" class="input-group" style="margin-bottom: 10px">
            <span class="input-group-addon">Lat</span>
            <input id="${cName}_latitude_popup" type="text" class="form-control" pattern="-?\\\d{1,3}\\\.\\\d+" value="${pPoint.lat}" />
            <span class="input-group-addon">Lon</span>
            <input id="${cName}_longitude_popup" type="text" class="form-control" pattern="-?\\\d{1,3}\\\.\\\d+" value="${pPoint.lng}" />
          </div>
          <div class="text-center">${_t('BAZ_ADJUST_MARKER_POSITION')}</div>
        `
      }

      function geocodedmarkerRefresh(pPoint) {
        if (vGeocodedMarker) cMap.removeLayer(vGeocodedMarker)
        
        if (!pPoint)
        {
          cLatitude.val("")
          cLongitude.val("")
          $(`#${cName}_latitude_popup`).val("")
          $(`#${cName}_longitude_popup`).val("")
          return;
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
            
          cLatitude.val(pPoint.lat)
          cLongitude.val(pPoint.lng)

          vGeocodedMarker.on('dragend', function (ev) {
              this.openPopup()
              const changedPos = ev.target.getLatLng()
              cLatitude.val(changedPos.lat)
              cLongitude.val(changedPos.lng)
              $(`#${cName}_latitude_popup`).val(changedPos.lat)
              $(`#${cName}_longitude_popup`).val(changedPos.lng)
          })
        } else {
          // remove formerly encoded marker position
          $(`#${cName}_latitude,#${cName}_longitude,#${cName}_latitude_popup,#${cName}_longitude_popup`).val('')
        }
      }
      
      var vLatitude = cLatitude.val();
      var vLongitude = cLongitude.val();
      
      if (
          vLatitude !== null &&
          vLatitude != 0 &&
          vLongitude !== null &&
          vLongitude != 0
        ) {

        showAddressOk(vLatitude, vLongitude)
      }

      $('body').on('keyup keypress', `#${cName}_latitude, #${cName}_longitude`, function () {
        const pattern = /^-?[\d]{1,3}[.][\d]+$/
        const thisVal = $(this).val()
        
        if (!thisVal.match(pattern))
          $(this).val(
            $(this)
              .val()
              .replace(/[^\d.]/g, ''),
          )
      })
        
      $('body').on('blur', `#${cName}_latitude, #${cName}_longitude`, function () {
        showAddressOk(cLatitude.val(), cLongitude.val())
      })
      
      cGeolocateButton.on('click', function () {
        function onLocationFound(e) {
          cLatitude.val(e.latitude)
          cLongitude.val(e.longitude)
          geocodedmarkerRefresh(e.latlng)
          cMap.panTo(e.latlng, { animate: true })
        }

        function onLocationError(e) {
          cLatitude.val('')
          cLongitude.val('')
          console.log(e.message)
        }

        cMap.on('locationfound', onLocationFound)
        cMap.on('locationerror', onLocationError)

        cMap.locate({ setView: true, maxZoom: 16 })
      })
      
      cMoveToAddressButton.on('click', function () {
        showAddress(true)
      })
      
      cGeolocateAddressButton.on('click', function () {
        showAddress()
      })
      
      $('body').on('change', `#${cName}_geolocation_popup`, function (e) {
        
        if ($(this).is(':invalid')) {
          cLatitude.val('')
          cLongitude.val('')
          alert(_t('BAZ_NOT_VALID_GEOLOC_FORMAT'))
        } else {
          const cLatitudePopup = $(this).find (`#${cName}_latitude_popup`).val();
          const cLongitudePopup = $(this).find (`#${cName}_longitude_popup`).val();
        
          cLatitude.val(cLatitudePopup)
          cLongitude.val(cLongitudePopup)

          vGeocodedMarker.setLatLng([
            cLatitudePopup,
            cLongitudePopup
          ])
        
          cMap.panTo(vGeocodedMarker.getLatLng(), { animate: true })
        }		
      });
		}
	})
})

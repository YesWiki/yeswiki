import renderHelper from './commons/render-helper.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_GEO_LABEL'),
    name: 'map',
    attrs: { type: 'map' },
    icon: '<i class="fas fa-map-marked-alt"></i>'
  },
  attributes: {
    name_latitude: { label: _t('BAZ_FORM_EDIT_MAP_LATITUDE'), value: 'bf_latitude' },
    name_longitude: { label: _t('BAZ_FORM_EDIT_MAP_LONGITUDE'), value: 'bf_longitude' },
    autocomplete_street: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET_PLACEHOLDER') },
    autocomplete_postalcode: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_POSTALCODE'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_POSTALCODE_PLACEHOLDER') },
    autocomplete_town: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_TOWN'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_TOWN_PLACEHOLDER') },
    autocomplete_county: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_COUNTY'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_COUNTY_PLACEHOLDER') },
    autocomplete_state: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STATE'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STATE_PLACEHOLDER') },
    autocomplete_other: { label: '', value: '' },
    autocomplete_street1: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET1'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET1_PLACEHOLDER') },
    autocomplete_street2: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET2'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET2_PLACEHOLDER') },
    geolocate: {
      label: _t('BAZ_FORM_EDIT_GEOLOCATE'),
      options: { 0: _t('NO'), 1: _t('YES') }
    },
    show_map_in_entry_view: {
      label: _t('BAZ_FORM_EDIT_SHOW_MAP_IN_ENTRY_VIEW'),
      options: { 0: _t('NO'), 1: _t('YES') }
    }
  },
  advancedAttributes: ['read', 'write', 'semantic', 'geolocate', 'autocomplete_other', 'autocomplete_street1', 'autocomplete_street2', 'show_map_in_entry_view'],
  // disabledAttributes: [],
  attributesMapping: {
    0: 'type',
    1: 'name_latitude',
    2: 'name_longitude',
    3: '',
    4: 'autocomplete_postalcode',
    5: 'autocomplete_town',
    6: 'autocomplete_other',
    7: 'show_map_in_entry_view',
    8: 'required'
  },
  renderInput(fieldData) {
    return {
      field: _t('BAZ_FORM_EDIT_MAP_FIELD'),
      onRender() {
        const toggleState = function(name, state) {
          const formGroup = renderHelper.getFormGroup(fieldData, name)
          if (formGroup !== null) {
            if (state === 'show') {
              formGroup.show()
            } else {
              formGroup.hide()
            }
          }
        }
        const toggleStates = function(state) {
          ['autocomplete_street1', 'autocomplete_street2'].forEach((name) => toggleState(name, state))
        }
        // initMapAutocompleteUpdate()
        $('.map-field.form-field')
          .find('input[type=text][name=autocomplete_street]:not(.initialized)'
              + ',input[type=text][name=autocomplete_street1]:not(.initialized)'
              + ',input[type=text][name=autocomplete_street2]:not(.initialized)'
              + ',input[type=text][name=autocomplete_county]:not(.initialized)'
              + ',input[type=text][name=autocomplete_state]:not(.initialized)'
              + ',select[name=geolocate]:not(.initialized)')
          .on('change', (event) => {
            // mapAutocompleteUpdate(event.target)
            const element = event.target
            const base = $(element).closest('.map-field.form-field')
            if (!$(element).hasClass('initialized')) {
              $(element).addClass('initialized')
              if ($(element).val().length == 0 || $(element).prop('tagName') === 'SELECT') {
                // mapAutocompleteUpdateExtractFromOther(base)
                const other = {
                  geolocate: '',
                  street: '',
                  street1: '',
                  street2: '',
                  county: '',
                  state: ''
                }
                const autoCompleteOther = $(base)
                  .find('input[type=text][name=autocomplete_other]')
                  .first()
                if (autoCompleteOther && autoCompleteOther.length > 0) {
                  const value = autoCompleteOther.val().split('|')
                  other.geolocate = ['1', 1, true].includes(value[0]) ? '1' : '0'
                  other.street = value[1] || ''
                  other.street1 = value[2] || ''
                  other.street2 = value[3] || ''
                  other.county = value[4] || ''
                  other.state = value[5] || ''
                }
                switch (element.getAttribute('name')) {
                  case 'autocomplete_street':
                    $(element).val(other.street)
                    break
                  case 'autocomplete_street1':
                    $(element).val(other.street1)
                    break
                  case 'autocomplete_street2':
                    $(element).val(other.street2)
                    break
                  case 'autocomplete_county':
                    $(element).val(other.county)
                    break
                  case 'autocomplete_state':
                    $(element).val(other.state)
                    break
                  case 'geolocate':
                    $(element).val(other.geolocate === '1' ? '1' : '0')
                    break
                  default:
                    break
                }
              }
            } else {
              // autocompleteUpdateSaveToOther(base)
              const autoCompleteOther = $(base)
                .find('input[type=text][name=autocomplete_other]')
                .first()
              if (autoCompleteOther && autoCompleteOther.length > 0) {
                const results = {
                  geolocate: '',
                  street: '',
                  street1: '',
                  street2: '',
                  county: '',
                  state: ''
                }
                const associations = {
                  street: 'autocomplete_street',
                  street1: 'autocomplete_street1',
                  street2: 'autocomplete_street2',
                  county: 'autocomplete_county',
                  state: 'autocomplete_state'
                }
                for (const key in associations) {
                  const autoCompleteField = $(base)
                    .find(`input[type=text][name=${associations[key]}]`)
                    .first()
                  if (autoCompleteField && autoCompleteField.length > 0) {
                    results[key] = autoCompleteField.val() || ''
                  }
                }
                // geolocate
                const geolocateField = $(base)
                  .find('select[name=geolocate]')
                  .first()
                if (geolocateField && geolocateField.length > 0) {
                  results.geolocate = geolocateField.val() || ''
                }
                autoCompleteOther.val(
                  `${results.geolocate
                  }|${results.street}`
                        + `|${results.street1}`
                        + `|${results.street2}`
                        + `|${results.county}`
                        + `|${results.state}`
                )
              }
            }
          })
          .trigger('change')

        renderHelper.prependHTMLBeforeGroup(fieldData, 'autocomplete_street', `
            <div class="form-group text-center">
              <b>${_t('GEOLOCATER_GROUP_GEOLOCATIZATION')}</b>
              <div class="small text-muted">${_t('GEOLOCATER_GROUP_GEOLOCATIZATION_HINT')}</div>
            </div>
          `)
      }
    }
  }
}

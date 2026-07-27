import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_GEO_LABEL'),
    name: 'map',
    attrs: { type: 'map' },
    icon: '<i class="fas fa-map-marked-alt"></i>'
  },
  defaultIdentifier: 'bf_geolocation',
  attributes: {
    autocomplete_street: { transient: true, label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET_PLACEHOLDER'), description: _t('GEOLOCATER_GROUP_GEOLOCATIZATION_HINT') },
    autocomplete_postalcode: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_POSTALCODE'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_POSTALCODE_PLACEHOLDER') },
    autocomplete_town: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_TOWN'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_TOWN_PLACEHOLDER') },
    autocomplete_county: { transient: true, label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_COUNTY'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_COUNTY_PLACEHOLDER') },
    autocomplete_state: { transient: true, label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STATE'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STATE_PLACEHOLDER') },
    autocomplete_others: { label: '', value: '' },
    autocomplete_street1: { transient: true, label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET1'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET1_PLACEHOLDER') },
    autocomplete_street2: { transient: true, label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET2'), value: '', placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_STREET2_PLACEHOLDER') },
    geolocate: {
      transient: true,
      label: _t('BAZ_FORM_EDIT_GEOLOCATE'),
      options: { 0: _t('NO'), 1: _t('YES') }
    },
    show_map_in_entry_view: {
      label: _t('BAZ_FORM_EDIT_SHOW_MAP_IN_ENTRY_VIEW'),
      options: { 0: _t('NO'), 1: _t('YES') }
    },
    geometries: { label: _t('BAZ_FORM_EDIT_MAP_GEOMETRIES'), value: 'marker' },
    max_geometries: { label: _t('BAZ_FORM_EDIT_MAP_MAX_GEOMETRIES'), value: '' },
    read_access: readConf,
    write_access: writeConf
  },
  advancedAttributes: ['read_access', 'write_access', 'geolocate', 'autocomplete_others', 'autocomplete_street1', 'autocomplete_street2', 'show_map_in_entry_view', 'geometries', 'max_geometries'],
  // disabledAttributes: [],
  editorHint: `<b>${_t('GEOLOCATER_GROUP_GEOLOCATIZATION')}</b> ${_t('GEOLOCATER_GROUP_GEOLOCATIZATION_HINT')}`,
  editorSetup(api) {
    // `autocomplete_other` is the stored attribute packing
    // geolocate|street|street1|street2|county|state; the visible inputs edit its
    // parts, it stays hidden itself
    api.hide('autocomplete_others')
    const partNames = ['geolocate', 'autocomplete_street', 'autocomplete_street1',
      'autocomplete_street2', 'autocomplete_county', 'autocomplete_state']
    const packed = (api.getValue('autocomplete_others') || '').split('|')
    if (packed.length > 1) {
      partNames.forEach((attr, index) => {
        if (!api.getValue(attr)) {
          let value = packed[index] || ''
          if (attr === 'geolocate') {
            value = ['1', 1, true].includes(value) ? '1' : '0'
          }
          api.setValue(attr, value, { silent: true })
        }
      })
    }
    const pack = () => {
      api.setValue(
        'autocomplete_others',
        partNames.map((attr) => api.getValue(attr) || '').join('|')
      )
    }
    partNames.forEach((attr) => api.onChange(attr, pack))
  },
  renderInput() {
    return { field: _t('BAZ_FORM_EDIT_MAP_FIELD') }
  }
}

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_METADATA_LABEL'),
    name: 'metadatas',
    attrs: { type: 'metadatas' },
    icon: '<i class="fas fa-palette"></i>'
  },
  attributes: {
    theme: {
      label: _t('BAZ_FORM_EDIT_METADATA_THEME_LABEL'),
      value: '',
      placeholder: 'margot, interface, colibris'
    },
    squelette: { label: _t('BAZ_FORM_EDIT_METADATA_SQUELETON_LABEL'), value: '1col.tpl.html' },
    style: {
      label: _t('BAZ_FORM_EDIT_METADATA_STYLE_LABEL'),
      value: '',
      placeholder: 'bootstrap.css...'
    },
    preset: {
      label: _t('BAZ_FORM_EDIT_METADATA_PRESET_LABEL'),
      value: '',
      placeholder: `blue.css (${_t('BAZ_FORM_EDIT_METADATA_PRESET_PLACEHOLDER')})`
    },
    image: {
      label: _t('BAZ_FORM_EDIT_METADATA_BACKGROUND_IMAGE_LABEL'),
      value: '',
      placeholder: 'foret.jpg...'
    }
  },
  // disabledAttributes: [],
  attributesMapping: { 0: 'type', 1: 'theme', 2: 'squelette', 3: 'style', 4: 'image', 5: 'preset' },
  renderInput(field) {
    return { field: '' }
  }
}

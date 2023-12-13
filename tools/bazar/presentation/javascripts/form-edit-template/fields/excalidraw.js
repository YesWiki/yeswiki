import renderHelper from './commons/render-helper.js'

export default {
  field: {
    label: 'Excalidraw',
    name: 'excalidraw',
    attrs: { type: 'excalidraw' },
    icon: '<i class="fas fa-chalkboard"></i>'
  },
  attributes: {
    urlField: { label: _t('BAZ_FORM_EDIT_EXCALIDRAW_URLFIELD_LABEL'), value: 'bf_url' },
  },
  disabledAttributes: [
    'required', 'value'
  ],
  attributesMapping: {
    0: 'type',
    1: 'name',
    2: 'label',
    3: 'urlField'
  },
  renderInput(field) {
    return {
      field: '',
      onRender() {
        renderHelper.prependHint(field, _t('BAZ_EXCALIDRAW_HINT', { '\\n': '<br>' }))
      }
    }
  },
}

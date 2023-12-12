import renderHelper from './commons/render-helper.js'

export default {
  field: {
    label: 'Whiteboard',
    name: 'whiteboard',
    attrs: { type: 'whiteboard' },
    icon: '<i class="fas fa-chalkboard"></i>'
  },
  attributes: {
    urlField: { label: _t('BAZ_FORM_EDIT_WHITEBOARD_URLFIELD_LABEL'), value: 'bf_url' },
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
        renderHelper.prependHint(field, _t('BAZ_WHITEBOARD_HINT', { '\\n': '<br>' }))
      }
    }
  },
}

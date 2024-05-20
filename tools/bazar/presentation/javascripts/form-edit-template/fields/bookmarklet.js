import renderHelper from './commons/render-helper.js'

export default {
  field: {
    label: 'Bookmarklet',
    name: 'bookmarklet',
    attrs: { type: 'bookmarklet' },
    icon: '<i class="fas fa-bookmark"></i>'
  },
  attributes: {
    urlField: { label: _t('BAZ_FORM_EDIT_BOOKMARKLET_URLFIELD_LABEL'), value: 'bf_url' },
    descriptionField: { label: _t('BAZ_FORM_EDIT_BOOKMARKLET_DESCRIPTIONFIELD_LABEL'), value: 'bf_description' },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: _t('BAZ_FORM_EDIT_BOOKMARKLET_HINT_DEFAULT_VALUE') },
    text: { label: _t('BAZ_FORM_EDIT_BOOKMARKLET_TEXT_LABEL'), value: _t('BAZ_FORM_EDIT_BOOKMARKLET_TEXT_VALUE') }
  },
  disabledAttributes: [
    'required', 'value'
  ],
  attributesMapping: {
    0: 'type',
    1: 'name',
    2: 'label',
    3: 'urlField',
    4: 'descriptionField',
    5: 'text',
    6: '',
    7: '',
    8: '',
    9: '',
    10: 'hint'
  },
  renderInput(field) {
    return {
      field: '',
      onRender() {
        renderHelper.prependHint(field, _t('BAZ_BOOKMARKLET_HINT', { '\\n': '<br>' }))
      }
    }
  }
}

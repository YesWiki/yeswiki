import { readConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CALC_LABEL'),
    name: 'calc',
    attrs: { type: 'calc' },
    icon: '<i class="fas fa-calculator"></i>'
  },
  attributes: {
    display_text: {
      label: _t('BAZ_FORM_EDIT_DISPLAYTEXT_LABEL'),
      value: '',
      placeholder: '{value}',
      description: _t('BAZ_FORM_EDIT_DISPLAYTEXT_HELP')
    },
    calcformula: {
      label: _t('BAZ_FORM_EDIT_FORMULA_LABEL'),
      value: ''
    },
    read_access: readConf
    // write: writeconf
  },
  disabledAttributes: [
    'required', 'default'
  ],
  editorHint: _t('BAZ_FORM_CALC_HINT', { br: '<BR>' }),
  renderInput() {
    return { field: '' }
  }
}

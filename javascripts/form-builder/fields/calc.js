import { readConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CALC_LABEL'),
    name: 'calc',
    attrs: { type: 'calc' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#calculator"/></svg>'
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
    // write: writeConf
  },
  disabledAttributes: [
    'required', 'default'
  ],
  editorHint: _t('BAZ_FORM_CALC_HINT', { br: '<BR>' })
}

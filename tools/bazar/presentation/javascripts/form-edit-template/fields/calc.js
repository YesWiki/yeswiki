import { readConf, defaultMapping } from './commons/attributes.js'
import renderHelper from './commons/render-helper.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CALC_LABEL'),
    name: 'calc',
    attrs: { type: 'calc' },
    icon: '<i class="fas fa-calculator"></i>'
  },
  attributes: {
    displaytext: {
      label: _t('BAZ_FORM_EDIT_DISPLAYTEXT_LABEL'),
      value: '',
      placeholder: '{value}'
    },
    formula: {
      label: _t('BAZ_FORM_EDIT_FORMULA_LABEL'),
      value: ''
    },
    read: readConf
    // write: writeconf
  },
  disabledAttributes: [
    'required', 'value', 'default'
  ],
  attributesMapping: {
    ...defaultMapping,
    ...{
      4: 'displaytext',
      5: 'formula',
      8: '',
      9: ''
    }
  },
  renderInput(field) {
    return {
      field: '',
      onRender() {
        renderHelper.prependHint(field, _t('BAZ_FORM_CALC_HINT', { '\\n': '<BR>' }))
        renderHelper.defineLabelHintForGroup(field, 'displaytext', _t('BAZ_FORM_EDIT_DISPLAYTEXT_HELP'))
      }
    }
  }
}

import renderHelper from './commons/render-helper.js'
import { defaultMapping } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_LABEL'),
    name: 'conditionschecking',
    attrs: { type: 'conditionschecking' },
    icon: '<i class="fas fa-project-diagram"></i>'
  },
  // Define an entire group of fields to be added to the stage at a time.
  set: {
    label: _t('BAZ_FORM_EDIT_CONDITIONCHECKING_LABEL'),
    name: 'conditionschecking',
    icon: '<i class="fas fa-project-diagram"></i>',
    fields: [
      {
        type: 'conditionschecking',
        label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_LABEL')
      },
      {
        type: 'labelhtml',
        label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_END'),
        content_saisie: `</div><!-- ${_t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_END')}-->`
      }
    ]
  },
  attributes: {
    condition: {
      label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_LABEL'),
      value: ''
    },
    clean: {
      label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_CLEAN_LABEL'),
      options: {
        ' ': _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_CLEAN_OPTION'),
        noclean: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_NOCLEAN_OPTION')
      }
    }
  },
  disabledAttributes: [
    'required', 'value', 'name', 'label'
  ],
  attributesMapping: {
    ...defaultMapping,
    ...{
      1: 'condition',
      2: 'clean',
      5: '',
      8: '',
      9: ''
    }
  },
  renderInput(data) {
    return {
      field: '',
      onRender() {
        renderHelper.prependHint(data, _t('BAZ_FORM_CONDITIONSCHEKING_HINT', { '\\n': '<BR>' }))
        renderHelper.defineLabelHintForGroup(data, 'noclean', _t('BAZ_FORM_CONDITIONSCHEKING_NOCLEAN_HINT'))
      }
    }
  }
}

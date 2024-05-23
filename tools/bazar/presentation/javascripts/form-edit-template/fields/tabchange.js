import renderHelper from './commons/render-helper.js'
import { defaultMapping } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TABCHANGE'),
    name: 'tabchange',
    attrs: { type: 'tabchange' },
    icon: '<i class="fas fa-stop"></i>'
  },
  attributes: {
    formChange: {
      label: _t('BAZ_FORM_EDIT_TABS_FOR_FORM'),
      options: { formChange: _t('YES'), noformchange: _t('NO') },
      description: `${_t('BAZ_FORM_EDIT_TABCHANGE_CHANGE_LABEL')} ${_t('BAZ_FORM_EDIT_TABS_FOR_FORM')}`
    },
    viewChange: {
      label: _t('BAZ_FORM_EDIT_TABS_FOR_ENTRY'),
      options: { '': _t('NO'), viewChange: _t('YES') },
      description: `${_t('BAZ_FORM_EDIT_TABCHANGE_CHANGE_LABEL')} ${_t('BAZ_FORM_EDIT_TABS_FOR_ENTRY')}`
    }
  },
  disabledAttributes: [
    'required', 'value', 'name', 'label'
  ],
  attributesMapping: {
    ...defaultMapping,
    ...{
      1: 'formChange',
      2: '',
      3: 'viewChange'
    }
  },
  renderInput(field) {
    return {
      field: '',
      onRender() {
        renderHelper.prependHint(field, _t('BAZ_FORM_TABS_HINT', {
          '\\n': '<BR>',
          'tabs-field-label': _t('BAZ_FORM_EDIT_TABS'),
          'tabchange-field-label': _t('BAZ_FORM_EDIT_TABCHANGE')
        }))
        renderHelper.prependHTMLBeforeGroup(field, 'formChange', $('<div/>').addClass('form-group').append($('<b/>').append(_t('BAZ_FORM_EDIT_TABCHANGE_CHANGE_LABEL'))))
      }
    }
  }
}

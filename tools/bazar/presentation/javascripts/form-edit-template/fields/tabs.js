import renderHelper from './commons/render-helper.js'
import { defaultMapping } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TABS'),
    name: 'tabs',
    attrs: { type: 'tabs' },
    icon: '<i class="fas fa-layer-group"></i>'
  },
  // Define an entire group of fields to be added to the stage at a time.
  set: {
    label: _t('BAZ_FORM_EDIT_TABS'),
    name: 'tabs',
    icon: '<i class="fas fa-layer-group"></i>',
    fields: [
      {
        type: 'tabs',
        label: _t('BAZ_FORM_EDIT_TABS')
      },
      {
        type: 'tabchange',
        label: _t('BAZ_FORM_EDIT_TABCHANGE')
      },
      {
        type: 'tabchange',
        label: _t('BAZ_FORM_EDIT_TABCHANGE')
      },
      {
        type: 'tabchange',
        label: _t('BAZ_FORM_EDIT_TABCHANGE')
      }
    ]
  },
  attributes: {
    formTitles: {
      label: _t('BAZ_FORM_EDIT_TABS_FOR_FORM'),
      value: _t('BAZ_FORM_EDIT_TABS_FORMTITLES_VALUE'),
      placeholder: _t('BAZ_FORM_EDIT_TABS_FORMTITLES_DESCRIPTION'),
      description: _t('BAZ_FORM_EDIT_TABS_FORMTITLES_DESCRIPTION')
    },
    viewTitles: {
      label: _t('BAZ_FORM_EDIT_TABS_FOR_ENTRY'),
      value: '',
      placeholder: _t('BAZ_FORM_EDIT_TABS_VIEWTITLES_DESCRIPTION'),
      description: _t('BAZ_FORM_EDIT_TABS_VIEWTITLES_DESCRIPTION')
    },
    moveSubmitButtonToLastTab: {
      label: _t('BAZ_FORM_EDIT_TABS_MOVESUBMITBUTTONTOLASTTAB_LABEL'),
      options: { '': _t('NO'), moveSubmit: _t('YES') },
      description: _t('BAZ_FORM_EDIT_TABS_MOVESUBMITBUTTONTOLASTTAB_DESCRIPTION')
    },
    btnColor: {
      label: _t('BAZ_FORM_EDIT_TABS_BTNCOLOR_LABEL'),
      options: { 'btn-primary': _t('PRIMARY'), 'btn-secondary-1': `${_t('SECONDARY')} 1`, 'btn-secondary-2': `${_t('SECONDARY')} 2` }
    },
    btnSize: {
      label: _t('BAZ_FORM_EDIT_TABS_BTNSIZE_LABEL'),
      options: { '': _t('NORMAL_F'), 'btn-xs': _t('SMALL_F') }
    }
  },
  disabledAttributes: [
    'required', 'value', 'name', 'label'
  ],
  attributesMapping: {
    ...defaultMapping,
    ...{
      1: 'formTitles',
      2: '',
      3: 'viewTitles',
      5: 'moveSubmitButtonToLastTab',
      6: '',
      7: 'btnColor',
      9: 'btnSize'
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
        renderHelper.prependHTMLBeforeGroup(field, 'formTitles', $('<div/>').addClass('form-group').append($('<b/>').append(_t('BAZ_FORM_EDIT_TABS_TITLES_LABEL'))))
        renderHelper.defineLabelHintForGroup(field, 'formTitles', _t('BAZ_FORM_EDIT_TABS_FORMTITLES_DESCRIPTION'))
        renderHelper.defineLabelHintForGroup(field, 'viewTitles', _t('BAZ_FORM_EDIT_TABS_VIEWTITLES_DESCRIPTION'))
        renderHelper.prependHTMLBeforeGroup(field, 'moveSubmitButtonToLastTab', $('<hr/>').addClass('form-group'))

        const holder = renderHelper.getHolder(field)
        if (holder) {
          const formGroup = holder.find('.formTitles-wrap')
          if (typeof formGroup !== undefined && formGroup.length > 0) {
            const input = formGroup.find('input').first()
            if (typeof input !== undefined && input.length > 0) {
              $(input).val($(input).val().replace(/\|/g, ','))
            }
          }
        }
      }
    }
  }
}

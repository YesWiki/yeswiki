export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TABCHANGE'),
    name: 'tabchange',
    attrs: { type: 'tabchange' },
    icon: '<i class="fas fa-stop"></i>'
  },
  attributes: {
    form_change: {
      label: _t('BAZ_FORM_EDIT_TABS_FOR_FORM'),
      options: { formChange: _t('YES'), noformchange: _t('NO') },
      description: `${_t('BAZ_FORM_EDIT_TABCHANGE_CHANGE_LABEL')} ${_t('BAZ_FORM_EDIT_TABS_FOR_FORM')}`
    },
    view_change: {
      label: _t('BAZ_FORM_EDIT_TABS_FOR_ENTRY'),
      options: { '': _t('NO'), viewChange: _t('YES') },
      description: `${_t('BAZ_FORM_EDIT_TABCHANGE_CHANGE_LABEL')} ${_t('BAZ_FORM_EDIT_TABS_FOR_ENTRY')}`
    }
  },
  disabledAttributes: [
    'required', 'default', 'name', 'label'
  ],
  editorHint: _t('BAZ_FORM_TABS_HINT', {
    br: '<BR>',
    'tabs-field-label': _t('BAZ_FORM_EDIT_TABS'),
    'tabchange-field-label': _t('BAZ_FORM_EDIT_TABCHANGE')
  })
}

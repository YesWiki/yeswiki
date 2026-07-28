export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_LABEL'),
    name: 'conditionschecking',
    attrs: { type: 'conditionschecking' },
    icon: '<i class="fas fa-project-diagram"></i>'
  },
  // Define an entire group of fields to be added to the stage at a time.
  set: {
    label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_MAIN_LABEL'),
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
        form_text: `</div><!-- ${_t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_END')}-->`
      }
    ]
  },
  attributes: {
    condition: {
      label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_LABEL'),
      value: ''
    },
    options: {
      label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_CLEAN_LABEL'),
      options: {
        ' ': _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_CLEAN_OPTION'),
        noclean: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_NOCLEAN_OPTION')
      },
      description: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_NOCLEAN_HINT')
    }
  },
  disabledAttributes: [
    'required', 'default', 'name', 'label'
  ],
  editorHint: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_HINT', { br: '<BR>' })
}

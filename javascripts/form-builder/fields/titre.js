export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TITLE_LABEL'),
    name: 'titre',
    attrs: { type: 'titre' },
    icon: '<i class="fas fa-heading"></i>'
  },
  attributes: { title_template: { label: _t('BAZ_FORM_EDIT_TITLE_LABEL'), value: '' } },
  disabledAttributes: ['name', 'required', 'default'],
  // disabledAttributes: [],
  renderInput(field) {
    return { field: field.title_template }
  }
}

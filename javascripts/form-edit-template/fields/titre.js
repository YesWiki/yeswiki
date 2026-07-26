export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TITLE_LABEL'),
    name: 'titre',
    attrs: { type: 'titre' },
    icon: '<i class="fas fa-heading"></i>'
  },
  attributes: {},
  // disabledAttributes: [],
  attributesMapping: { 0: 'type', 1: 'value', 2: 'label' },
  renderInput(field) {
    return { field: field.value }
  }
}

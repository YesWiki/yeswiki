export default {
  field: {
    name: 'collaborative_doc',
    attrs: { type: 'collaborative_doc' }
  },
  attributes: {},
  // disabledAttributes: [],
  renderInput(field) {
    return { field: _t('BAZ_FORM_EDIT_COLLABORATIVE_DOC_FIELD') }
  }
}

export default {
  field: {
    label: 'Bookmarklet',
    name: 'bookmarklet',
    attrs: { type: 'bookmarklet' },
    icon: '<i class="fas fa-bookmark"></i>'
  },
  attributes: {
    url_field: { label: _t('BAZ_FORM_EDIT_BOOKMARKLET_URLFIELD_LABEL'), value: 'bf_url' },
    description_field: { label: _t('BAZ_FORM_EDIT_BOOKMARKLET_DESCRIPTIONFIELD_LABEL'), value: 'bf_description' },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: _t('BAZ_FORM_EDIT_BOOKMARKLET_HINT_DEFAULT_VALUE') },
    text_field: { label: _t('BAZ_FORM_EDIT_BOOKMARKLET_TEXT_LABEL'), value: _t('BAZ_FORM_EDIT_BOOKMARKLET_TEXT_VALUE') }
  },
  disabledAttributes: [
    'required', 'default'
  ],
  editorHint: _t('BAZ_BOOKMARKLET_HINT', { '\\n': '<br>' }),
  renderInput() {
    return { field: '' }
  }
}

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CUSTOM_HTML_LABEL'),
    name: 'labelhtml',
    attrs: { type: 'labelhtml' },
    icon: '<i class="fas fa-code"></i>'
  },
  attributes: {
    label: {
      label: _t('BAZ_FORM_EDIT_CUSTOM_HTML_LABEL'),
      value: ''
    },
    content_saisie: {
      label: _t('BAZ_FORM_EDIT_EDIT_CONTENT_LABEL'),
      type: 'textarea',
      rows: '4',
      value: ''
    },
    content_display: {
      label: _t('BAZ_FORM_EDIT_VIEW_CONTENT_LABEL'),
      type: 'textarea',
      rows: '4',
      value: ''
    },
    useWikiSyntax: {
      label: _t('BAZ_FORM_EDIT_USE_WIKI_SYNTAX_DETAILS'),
      options: { true: _t('YES'), false: _t('NO') },
      value: 'false'
    }
  },
  // disabledAttributes: [],
  attributesMapping: {
    0: 'type',
    1: 'content_saisie',
    2: '',
    3: 'content_display',
    4: 'useWikiSyntax'
  },
  renderInput(field) {
    return {
      field:
        `${field.content_saisie && field.content_saisie.trim() !== '' ? `<div>${_t('BAZ_FORM_EDIT_EDIT_CONTENT_LABEL')}: <pre>${field.content_saisie}</pre></div>` : ''}`
        + `${field.content_display && field.content_display.trim() !== '' ? `<div>${_t('BAZ_FORM_EDIT_VIEW_CONTENT_LABEL')}: <pre>${field.content_display}</pre></div>` : ''}`
    }
  }
}

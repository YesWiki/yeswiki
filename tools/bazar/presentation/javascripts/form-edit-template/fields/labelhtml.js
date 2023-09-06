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
    }
  },
  // disabledAttributes: [],
  attributesMapping: {
    0: 'type',
    1: 'content_saisie',
    2: '',
    3: 'content_display'
  },
  renderInput(field) {
    return {
      field:
        `<div>${field.content_saisie || ''}</div>
         <div>${field.content_display || ''}</div>`
    }
  }
}

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_CUSTOM_HTML_LABEL'),
    name: 'labelhtml',
    attrs: { type: 'labelhtml' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#code"/></svg>',
  },
  attributes: {
    label: {
      label: _t('BAZ_FORM_EDIT_CUSTOM_HTML_LABEL'),
      value: '',
    },
    form_text: {
      label: _t('BAZ_FORM_EDIT_EDIT_CONTENT_LABEL'),
      type: 'textarea',
      rows: '4',
      value: '',
    },
    view_text: {
      label: _t('BAZ_FORM_EDIT_VIEW_CONTENT_LABEL'),
      type: 'textarea',
      rows: '4',
      value: '',
    },
    use_wiki_syntax: {
      label: _t('BAZ_FORM_EDIT_USE_WIKI_SYNTAX_DETAILS'),
      options: { true: _t('YES'), false: _t('NO') },
      value: 'false',
    },
  },
}

import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TEXTAREA_LABEL'),
    name: 'textarea',
    attrs: { type: 'textarea' },
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-text" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/><path d="M3 5.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zM3 8a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9A.5.5 0 0 1 3 8zm0 2.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5z"/></svg>',
  },
  defaultIdentifier: 'bf_description',
  attributes: {
    syntax: {
      label: _t('BAZ_FORM_EDIT_TEXTAREA_SYNTAX_LABEL'),
      options: {
        wiki: 'Wiki',
        html: _t('BAZ_FORM_EDIT_TEXTAREA_SYNTAX_HTML'),
        nohtml: _t('BAZ_FORM_EDIT_TEXTAREA_SYNTAX_NOHTML'),
      },
    },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    num_rows: {
      label: _t('BAZ_FORM_EDIT_TEXTAREA_ROWS_LABEL'),
      type: 'number',
      placeholder: _t('BAZ_FORM_EDIT_TEXTAREA_ROWS_PLACEHOLDER'),
    },
    placeholder: { label: _t('BAZ_FORM_EDIT_PLACEHOLDER'), value: '' },
    read_access: readConf,
    write_access: writeConf,
  },
  advancedAttributes: [
    'placeholder',
    'read_access',
    'write_access',
    'syntax',
    'num_rows',
  ],
}

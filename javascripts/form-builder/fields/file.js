import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('FORM_BUILDER_FILE_LABEL'),
    name: 'file',
    attrs: { type: 'file' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#paperclip"/></svg>'
  },
  defaultIdentifier: 'fichier',
  attributes: {
    read_label: {
      label: _t('BAZ_FORM_EDIT_FILE_READLABEL_LABEL'),
      value: '',
      placeholder: _t('BAZ_FILEFIELD_FILE')
    },
    authorized_exts_label: {
      label: _t('BAZ_FORM_EDIT_FILE_AUTHEXTS_LABEL'),
      value: '',
      placeholder: _t('BAZ_FORM_EDIT_FILE_AUTHEXTS_PLACEHOLDER')
    },
    max_size: { label: _t('BAZ_FORM_EDIT_FILE_MAXSIZE_LABEL'), value: '' },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read_access: readConf,
    write_access: writeConf
  },
  advancedAttributes: ['read_access', 'write_access', 'max_size', 'authorized_exts_label']
  // disabledAttributes: [],
}

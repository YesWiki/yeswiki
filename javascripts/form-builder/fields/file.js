import { readConf, writeconf } from './commons/attributes.js'

export default {
  field: {
    label: _t('FORM_BUILDER_FILE_LABEL'),
    name: 'file',
    attrs: { type: 'file' },
    icon: '<i class="fas fa-paperclip"></i>'
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
    write_access: writeconf
  },
  advancedAttributes: ['read_access', 'write_access', 'max_size', 'authorized_exts_label']
  // disabledAttributes: [],
  // renderInput(field) {
  //   return {
  //     field: `<input type="file"/>`,
  //   }
  // }
}

import { readConf, writeconf, defaultMapping } from './commons/attributes.js'

export default {
  attributes: {
    readlabel: {
      label: _t('BAZ_FORM_EDIT_FILE_READLABEL_LABEL'),
      value: '',
      placeholder: _t('BAZ_FILEFIELD_FILE'),
    },
    authorizedExts: {
      label: _t('BAZ_FORM_EDIT_FILE_AUTHEXTS_LABEL'),
      value: '',
      placeholder: _t('BAZ_FORM_EDIT_FILE_AUTHEXTS_PLACEHOLDER'),
    },
    maxsize: { label: _t('BAZ_FORM_EDIT_FILE_MAXSIZE_LABEL'), value: '' },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read: readConf,
    write: writeconf,
  },
  advancedAttributes: ['read', 'write', 'maxsize', 'authorizedExts'],
  // disabledAttributes: [],
  attributesMapping: {
    ...defaultMapping,
    ...{ 14: 'maxsize', 6: 'readlabel', 7: 'authorizedExts' },
  },
  // renderInput(field) {
  //   return {
  //     field: `<input type="file"/>`,
  //   }
  // }
}

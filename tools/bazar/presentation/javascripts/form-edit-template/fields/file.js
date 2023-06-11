import { readConf, writeconf, semanticConf, defaultMapping } from './commons/attributes.js'

export default {
  attributes: {
    readlabel: {
      label: _t('BAZ_FORM_EDIT_FILE_READLABEL_LABEL'),
      value: '',
      placeholder: _t('BAZ_FILEFIELD_FILE')
    },
    maxsize: { label: _t('BAZ_FORM_EDIT_FILE_MAXSIZE_LABEL'), value: '' },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  advancedAttributes: ['read', 'write', 'semantic', 'maxsize'],
  // disabledAttributes: [],
  attributesMapping: { ...defaultMapping, ...{ 3: 'maxsize', 6: 'readlabel' } },
  // renderInput(fieldData) {},
}

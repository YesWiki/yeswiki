import { readConf, writeconf, semanticConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TAGS_LABEL'),
    name: 'tags',
    attrs: { type: 'tags' },
    icon: '<i class="fas fa-tags"></i>'
  },
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  // disabledAttributes: [],
  renderInput(fieldData) {
    return { field: '<input/>' }
  }
}

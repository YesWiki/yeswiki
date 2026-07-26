import { readConf, writeconf } from './commons/attributes.js'

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
  },
  // disabledAttributes: [],
  renderInput(fieldData) {
    return { field: '<input/>' }
  }
}

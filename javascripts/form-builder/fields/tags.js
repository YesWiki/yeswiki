import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TAGS_LABEL'),
    name: 'tags',
    attrs: { type: 'tags' },
    icon: '<i class="fas fa-tags"></i>'
  },
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read_access: readConf,
    write_access: writeConf
  },
  // disabledAttributes: [],
  renderInput() {
    return { field: '<input/>' }
  }
}

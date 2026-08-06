import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_TAGS_LABEL'),
    name: 'tags',
    attrs: { type: 'tags' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#tags"/></svg>',
  },
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read_access: readConf,
    write_access: writeConf,
  },
  // disabledAttributes: [],
}

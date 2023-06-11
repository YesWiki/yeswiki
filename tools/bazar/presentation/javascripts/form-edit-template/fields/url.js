import { readConf, writeconf, semanticConf, defaultMapping } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_URL_LABEL'),
    name: 'url',
    attrs: { type: 'url' },
    icon: '<i class="fas fa-link"></i>'
  },
  attributes: {
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  advancedAttributes: ['read', 'write', 'semantic'],
  // disabledAttributes: [],
  renderInput(fieldData) {
    return { field: `<input type="url" placeholder="${fieldData.value || ''}"/>` }
  }
}

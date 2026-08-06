import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: "Horaires d'ouverture",
    name: 'openinghours',
    attrs: { type: 'openinghours' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#calendar-event"/></svg>',
  },
  defaultIdentifier: 'horaires_ouverture',
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read_access: readConf,
    write_access: writeConf,
  },
  advancedAttributes: ['read_access', 'write_access'],
}

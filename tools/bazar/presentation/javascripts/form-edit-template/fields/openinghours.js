import { readConf, writeconf, defaultMapping } from './commons/attributes.js'

export default {
  field: {
    label: "Horaires d'ouverture",
    name: 'openinghours',
    attrs: { type: 'openinghours' },
    icon: '<i class="far fa-calendar-alt"></i>',
  },
  defaultIdentifier: 'horaires_ouverture',
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read: readConf,
    write: writeconf,
  },
  advancedAttributes: ['read', 'write'],
  renderInput(fieldData) {
    return { field: '<input type="date"/>' }
  },
}

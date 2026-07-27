import { readConf, writeconf } from './commons/attributes.js'

export default {
  field: {
    label: "Horaires d'ouverture",
    name: 'openinghours',
    attrs: { type: 'openinghours' },
    icon: '<i class="far fa-calendar-alt"></i>'
  },
  defaultIdentifier: 'horaires_ouverture',
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read_access: readConf,
    write_access: writeconf
  },
  advancedAttributes: ['read_access', 'write_access'],
  renderInput() {
    return { field: '<input type="date"/>' }
  }
}

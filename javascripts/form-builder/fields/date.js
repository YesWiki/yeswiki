import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('FORM_BUILDER_DATE_LABEL'),
    name: 'date',
    attrs: { type: 'date' },
    icon: '<i class="far fa-calendar-alt"></i>'
  },
  defaultIdentifier: 'bf_date_debut_evenement',
  attributes: {
    default: {
      label: _t('BAZ_FORM_EDIT_DATE_TODAY_BUTTON'),
      options: { ' ': _t('NO'), today: _t('YES') }
    },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read_access: readConf,
    write_access: writeConf
  },
  advancedAttributes: ['read_access', 'write_access', 'default']
  // disabledAttributes: [],
}

import { readConf, writeconf, semanticConf, defaultMapping } from './commons/attributes.js'

export default {
  // field: {
  //   label: "Sélecteur de date",
  //   name: "jour",
  //   attrs: { type: "date" },
  //   icon: '<i class="far fa-calendar-alt"></i>',
  // },
  defaultIdentifier: 'bf_date_debut_evenement',
  attributes: {
    today_button: {
      label: _t('BAZ_FORM_EDIT_DATE_TODAY_BUTTON'),
      options: { ' ': _t('NO'), today: _t('YES') }
    },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  advancedAttributes: ['read', 'write', 'semantic', 'today_button'],
  // disabledAttributes: [],
  attributesMapping: { ...defaultMapping, ...{ 5: 'today_button' } }
  // renderInput(fieldData) {},
}

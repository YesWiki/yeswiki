import { readConf, writeconf, defaultMapping } from './commons/attributes.js'

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
      options: { ' ': _t('NO'), today: _t('YES') },
    },
    entry_mode: {
      label: _t('BAZ_FORM_EDIT_DATE_ENTRY_MODE'),
      options: {
        ' ': _t('BAZ_FORM_EDIT_DATE_ENTRY_MODE_AUTO'),
        time: _t('BAZ_FORM_EDIT_DATE_ENTRY_MODE_TIME'),
        allday: _t('BAZ_FORM_EDIT_DATE_ENTRY_MODE_ALL_DAY'),
      },
    },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    read: readConf,
    write: writeconf,
  },
  advancedAttributes: ['read', 'write', 'today_button'],
  // disabledAttributes: [],
  attributesMapping: { ...defaultMapping, ...{ 5: 'today_button', 6: 'entry_mode' } },
  // renderInput(fieldData) {},
}

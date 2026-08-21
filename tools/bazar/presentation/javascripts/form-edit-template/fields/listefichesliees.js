import { defaultMapping, readConf, writeconf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_LINKEDENTRIES_LABEL'),
    name: 'listefichesliees',
    attrs: { type: 'listefichesliees' },
    icon: '<i class="fas fa-th-list"></i>',
  },
  attributes: {
    id: { label: _t('BAZ_FORM_EDIT_LISTEFICHES_FORMID_LABEL'), value: '' },
    query: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_QUERY_LABEL'),
      value: '',
      placeholder: _t('BAZ_FORM_EDIT_LISTEFICHES_QUERY_PLACEHOLDER', {
        url: 'https://yeswiki.net/?DocQuery/iframe',
      }),
    },
    param: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_PARAMS_LABEL'),
      value: '',
      placeholder: 'Ex: champs="bf_nom" ordre="desc"',
    },
    addEntryBtnLabel: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_PARAMS_ADD_ENTRY_BTN_LABEL'),
      value: '',
      placeholder: 'Ex: "Ajouter une fiche"',
    },
    number: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_NUMBER_LABEL'),
      value: '',
      placeholder: '',
    },
    template: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_TEMPLATE_LABEL'),
      value: '',
      placeholder: _t('BAZ_FORM_EDIT_LISTEFICHES_TEMPLATE_PLACEHOLDER'),
    },
    type_link: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_LISTTYPE_LABEL'),
      value: '',
      placeholder: _t('BAZ_FORM_EDIT_LISTEFICHES_LISTTYPE_PLACEHOLDER'),
    },
    read: readConf,
    write: writeconf,
  },
  advancedAttributes: [
    'read',
    'write',
    'template',
    'type_link',
    'param',
    'query',
    'addEntryBtnLabel',
  ],
  disabledAttributes: ['required', 'value', 'name'],
  attributesMapping: {
    ...defaultMapping,
    ...{
      0: 'type',
      1: 'id',
      2: 'query',
      3: 'param',
      4: 'number',
      5: 'template',
      6: 'type_link',
      7: 'label',
      10: 'addEntryBtnLabel',
    },
  },
  renderInput(field) {
    return { field: '' }
  },
}

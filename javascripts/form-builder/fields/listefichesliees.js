import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_LINKEDENTRIES_LABEL'),
    name: 'listefichesliees',
    attrs: { type: 'listefichesliees' },
    icon: '<i class="fas fa-th-list"></i>'
  },
  attributes: {
    name: { label: _t('BAZ_FORM_EDIT_LISTEFICHES_FORMID_LABEL'), value: '' },
    query: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_QUERY_LABEL'),
      value: '',
      placeholder: _t('BAZ_FORM_EDIT_LISTEFICHES_QUERY_PLACEHOLDER', { url: 'https://yeswiki.net/?DocQuery/iframe' })
    },
    other_params: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_PARAMS_LABEL'),
      value: '',
      placeholder: 'Ex: champs="bf_nom" ordre="desc"'
    },
    add_entry_btn_label: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_PARAMS_ADD_ENTRY_BTN_LABEL'),
      value: '',
      placeholder: 'Ex: "Ajouter une fiche"'
    },
    limit: { label: _t('BAZ_FORM_EDIT_LISTEFICHES_NUMBER_LABEL'), value: '', placeholder: '' },
    template: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_TEMPLATE_LABEL'),
      value: '',
      placeholder:
        _t('BAZ_FORM_EDIT_LISTEFICHES_TEMPLATE_PLACEHOLDER')
    },
    link_type: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_LISTTYPE_LABEL'),
      value: '',
      placeholder:
        _t('BAZ_FORM_EDIT_LISTEFICHES_LISTTYPE_PLACEHOLDER')
    },
    read_access: readConf,
    write_access: writeConf
  },
  advancedAttributes: ['read_access', 'write_access', 'template', 'link_type', 'other_params', 'query', 'add_entry_btn_label'],
  disabledAttributes: ['required', 'default']
}

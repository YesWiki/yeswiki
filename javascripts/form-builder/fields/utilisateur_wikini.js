export default {
  field: {
    label: _t('BAZ_FORM_EDIT_USERS_WIKINI_LABEL'),
    name: 'utilisateur_wikini',
    attrs: { type: 'utilisateur_wikini' },
    icon: '<i class="fas fa-user"></i>'
  },
  attributes: {
    name_field: { label: _t('BAZ_FORM_EDIT_USERS_WIKINI_NAME_FIELD_LABEL'), value: 'bf_titre' },
    email_field: {
      label: _t('BAZ_FORM_EDIT_USERS_WIKINI_EMAIL_FIELD_LABEL'),
      value: 'bf_mail'
    },
    // mailing_list: {
    //   label: "Inscrite à une liste de diffusion"
    // },
    auto_update_mail: {
      label: _t('BAZ_FORM_EDIT_USERS_WIKINI_AUTOUPDATE_MAIL'),
      options: { 0: _t('NO'), 1: _t('YES') }
    },
    auto_add_to_group: {
      label: _t('BAZ_FORM_EDIT_ADD_TO_GROUP_LABEL'),
      value: '',
      placeholder: _t('BAZ_FORM_EDIT_ADD_TO_GROUP_DESCRIPTION'),
      description: _t('BAZ_FORM_EDIT_ADD_TO_GROUP_DESCRIPTION')
    }
  },
  advancedAttributes: ['auto_update_mail', 'auto_add_to_group'],
  // disabledAttributes: [],
  renderInput() {
    return { field: '' }
  }
}

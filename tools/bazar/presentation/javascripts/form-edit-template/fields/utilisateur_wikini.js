import renderHelper from './commons/render-helper.js'
import { defaultMapping } from './commons/attributes.js'

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
    autoupdate_email: {
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
  advancedAttributes: ['autoupdate_email', 'auto_add_to_group'],
  // disabledAttributes: [],
  attributesMapping: {
    ...defaultMapping,
    ...{
      0: 'type',
      1: 'name_field',
      2: 'email_field',
      5: '', /* 5:"mailing_list", */
      6: 'auto_add_to_group',
      8: '',
      9: 'autoupdate_email'
    }
  },
  renderInput(field) {
    return {
      field: '',
      onRender() {
        renderHelper.defineLabelHintForGroup(field, 'auto_add_to_group', _t('BAZ_FORM_EDIT_ADD_TO_GROUP_HELP'))
      }
    }
  }
}

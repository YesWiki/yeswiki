export default {
  field: {
    label: _t('BAZ_FORM_EDIT_SUBSCRIBE_LIST_LABEL'),
    name: 'inscriptionliste',
    attrs: { type: 'inscriptionliste' },
    icon: '<i class="fas fa-mail-bulk"></i>'
  },
  attributes: {
    subscription_email: { label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_LABEL'), value: '' },
    email_field_id: {
      label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_FIELDID'),
      value: 'bf_mail'
    },
    mailing_list_tool: {
      label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_MAILINGLIST'),
      value: ''
    }
  },
  // disabledAttributes: [],
  attributesMapping: {
    0: 'type',
    1: 'subscription_email',
    2: 'label',
    3: 'email_field_id',
    4: 'mailing_list_tool'
  },
  renderInput(field) {
    return { field: '<input type="checkbox"/>' }
  }
}

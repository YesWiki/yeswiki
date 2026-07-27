export default {
  field: {
    label: _t('BAZ_FORM_EDIT_SUBSCRIBE_LIST_LABEL'),
    name: 'inscriptionliste',
    attrs: { type: 'inscriptionliste' },
    icon: '<i class="fas fa-mail-bulk"></i>'
  },
  attributes: {
    mailer_email: { label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_LABEL'), value: '' },
    email_field: {
      label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_FIELDID'),
      value: 'bf_mail'
    },
    mailer_tool: {
      label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_MAILINGLIST'),
      value: ''
    }
  },
  // disabledAttributes: [],
  renderInput() {
    return { field: '<input type="checkbox"/>' }
  }
}

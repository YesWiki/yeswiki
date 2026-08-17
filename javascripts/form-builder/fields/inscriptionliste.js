export default {
  field: {
    label: _t('BAZ_FORM_EDIT_SUBSCRIBE_LIST_LABEL'),
    name: 'inscriptionliste',
    attrs: { type: 'inscriptionliste' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#mail-forward"/></svg>',
  },
  attributes: {
    mailer_email: {
      label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_LABEL'),
      value: '',
    },
    email_field: {
      label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_FIELDID'),
      value: 'bf_mail',
    },
    mailer_tool: {
      label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_MAILINGLIST'),
      value: '',
    },
  },
}

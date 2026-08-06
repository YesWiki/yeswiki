import { readConf, writeConf } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_EMAIL_LABEL'),
    name: 'champs_mail',
    attrs: { type: 'champs_mail' },
    icon: '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#mail"/></svg>',
  },
  defaultIdentifier: 'bf_mail',
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    separator: { label: '' }, // separate important attrs from others
    send_email: {
      label: _t('BAZ_FORM_EDIT_EMAIL_SEND_FORM_CONTENT_LABEL'),
      options: { 0: _t('NO'), 1: _t('YES') },
    },
    show_contact_form: {
      label: _t('BAZ_FORM_EDIT_EMAIL_REPLACE_BY_BUTTON_LABEL'),
      options: { '': _t('NO'), form: _t('YES') },
      value: 'form',
    },
    see_mail_acls: {
      ...readConf,
      ...{ label: _t('BAZ_FORM_EDIT_EMAIL_SEE_MAIL_ACLS') },
    },
    readWhenForm: {
      transient: true,
      ...readConf,
      ...{ label: _t('BAZ_FORM_EDIT_EMAIL_SEND_ACLS') },
    },
    // searchable: searchableConf, -> 10/19 Florian say that this conf is not working for now
    read_access: readConf,
    write_access: writeConf,
  },
  advancedAttributes: [
    'read_access',
    'write_access',
    'name',
    'see_mail_acls',
    'readWhenForm',
  ],
  // disabledAttributes: [],
  editorSetup(api) {
    const arrayEquals = (a, b) =>
      Array.isArray(a) &&
      Array.isArray(b) &&
      a.length === b.length &&
      a.every((e) => b.includes(e))
    // read and readWhenForm mirror each other
    api.onChange('read_access', () => {
      if (
        !arrayEquals(api.getValue('readWhenForm'), api.getValue('read_access'))
      ) {
        api.setValue('readWhenForm', api.getValue('read_access'), {
          silent: true,
        })
      }
    })
    api.onChange('readWhenForm', () => {
      if (
        !arrayEquals(api.getValue('read_access'), api.getValue('readWhenForm'))
      ) {
        api.setValue('read_access', api.getValue('readWhenForm'), {
          silent: true,
        })
      }
    })
    const applyButtonMode = () => {
      if (api.getValue('show_contact_form') === 'form') {
        // when chosing 'form' (or at init), if readAcl is '%', prefer '*'
        // to show the button to everyone
        if (arrayEquals(api.getValue('read_access'), ['%'])) {
          api.setValue('read_access', ['*'])
        }
        api.show('readWhenForm')
        api.show('see_mail_acls')
        api.hide('read_access')
      } else {
        // when chosing 'text' (or at init), if readAcl is '*', prefer '%'
        // to force the email not to be shown
        const write = api.getValue('write_access') || []
        if (
          arrayEquals(api.getValue('read_access'), ['*']) &&
          !write.includes('*')
        ) {
          api.setValue('read_access', ['%'])
        }
        api.hide('readWhenForm')
        api.hide('see_mail_acls')
        api.show('read_access')
      }
    }
    api.onChange('show_contact_form', applyButtonMode)
    applyButtonMode()
  },
}

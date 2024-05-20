import renderHelper from './commons/render-helper.js'
import { readConf, writeconf, semanticConf, defaultMapping } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_EMAIL_LABEL'),
    name: 'champs_mail',
    attrs: { type: 'champs_mail' },
    icon: '<i class="fas fa-envelope"></i>'
  },
  defaultIdentifier: 'bf_mail',
  attributes: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
    separator: { label: '' }, // separate important attrs from others
    send_form_content_to_this_email: {
      label: _t('BAZ_FORM_EDIT_EMAIL_SEND_FORM_CONTENT_LABEL'),
      options: { 0: _t('NO'), 1: _t('YES') }
    },
    replace_email_by_button: {
      label: _t('BAZ_FORM_EDIT_EMAIL_REPLACE_BY_BUTTON_LABEL'),
      options: { '': _t('NO'), form: _t('YES') },
      value: 'form'
    },
    seeEmailAcls: { ...readConf, ...{ label: _t('BAZ_FORM_EDIT_EMAIL_SEE_MAIL_ACLS') } },
    readWhenForm: { ...readConf, ...{ label: _t('BAZ_FORM_EDIT_EMAIL_SEND_ACLS') } },
    // searchable: searchableConf, -> 10/19 Florian say that this conf is not working for now
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  advancedAttributes: ['read', 'write', 'semantic', 'pattern', 'defaultIdentifier', 'name', 'seeEmailAcls', 'readWhenForm'],
  // disabledAttributes: [],
  attributesMapping: {
    ...defaultMapping,
    ...{ 4: 'seeEmailAcls', 6: 'replace_email_by_button', 9: 'send_form_content_to_this_email' }
  },
  renderInput(fieldData) {
    return {
      field: `<input id="${fieldData.name}" type="email" value="" />`,
      onRender() {
        const currentField = renderHelper.getHolder(fieldData).parent()
        renderHelper.initializeField(currentField)
        const arrayEquals = (a, b) => {
          if (a.length != b.length) {
            return false
          }
          return (a.every((e) => b.includes(e)) && b.every((e) => a.includes(e)))
        }
        currentField.find('select[name=read]:not(.initialized)')
          .on('change', (event) => {
            const element = event.target
            const base = $(element).closest('.champs_mail-field.form-field')
            $(element).addClass('initialized')

            const readWhenFormInput = $(base).find('select[name=readWhenForm]')
            if (readWhenFormInput && readWhenFormInput.length > 0 && !arrayEquals(readWhenFormInput.val(), $(element).val())) {
              readWhenFormInput.val($(element).val())
            }
          }).trigger('change')
        currentField.find('select[name=readWhenForm]:not(.initialized)')
          .on('change', (event) => {
            const element = event.target
            const base = $(element).closest('.champs_mail-field.form-field')
            $(element).addClass('initialized')

            const readInput = $(base).find('select[name=read]')
            if (readInput && readInput.length > 0 && !arrayEquals(readInput.val(), $(element).val())) {
              readInput.val($(element).val())
            }
          }).trigger('change')
        currentField
          .find('select[name=replace_email_by_button]:not(.initialized)')
          .on('change', (event) => {
            const element = event.target

            const base = $(element).closest('.champs_mail-field.form-field')
            $(element).addClass('initialized')

            const setDisplay = (base, name, newValue) => {
              const wrapper = $(base).find(`div.form-group.${name}-wrap`)
              if (wrapper && wrapper.length > 0) {
                if (newValue) {
                  wrapper.show()
                } else {
                  wrapper.hide()
                }
              }
            }
            if ($(element).val() == 'form') {
              // when chosing 'form' (or at init), if readAcl is ' % ', prefer ' * '
              // to show button to everyone
              const field = currentField.find('select[name=read]')
              if (arrayEquals(field.val(), [' % '])) {
                field.val([' * '])
                field.trigger('change')
              }
              setDisplay(base, 'readWhenForm', 1)
              setDisplay(base, 'seeEmailAcls', 1)
              setDisplay(base, 'read', 0)
            } else {
              // when chosing 'text' (or at init), if readAcl is ' * ', prefer ' % '
              // to force email not to be shown
              const field = currentField.find('select[name=read]')
              if (arrayEquals(field.val(), [' * ']) && !currentField.find('select[name=write]').val().includes(' * ')) {
                field.val([' % '])
                field.trigger('change')
              }
              setDisplay(base, 'readWhenForm', 0)
              setDisplay(base, 'seeEmailAcls', 0)
              setDisplay(base, 'read', 1)
            }
          })
          .trigger('change')
      }
    }
  }
}

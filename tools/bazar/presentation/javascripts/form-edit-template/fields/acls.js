import { aclsOptions, aclsCommentOptions } from './commons/attributes.js'
import renderHelper from './commons/render-helper.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_ACL_LABEL'),
    name: 'acls',
    attrs: { type: 'acls' },
    icon: '<i class="fas fa-user-lock"></i>'
  },
  attributes: {
    read: {
      label: _t('BAZ_FORM_EDIT_ACL_READ_LABEL'),
      options: aclsOptions,
      multiple: true
    },
    write: {
      label: _t('BAZ_FORM_EDIT_ACL_WRITE_LABEL'),
      options: aclsOptions,
      multiple: true
    },
    comment: {
      label: _t('BAZ_FORM_EDIT_ACL_COMMENT_LABEL'),
      options: aclsCommentOptions,
      multiple: true
    },
    askIfActivateComments: {
      label: _t('BAZ_FORM_EDIT_ACL_ASK_IF_ACTIVATE_COMMENT_LABEL'),
      options: { 0: _t('NO'), 1: _t('YES') }
    },
    fieldLabel: {
      label: _t('BAZ_FORM_EDIT_COMMENTS_FIELD_ACTIVATE_LABEL'),
      value: '',
      placeholder: _t('BAZ_ACTIVATE_COMMENTS')
    },
    hint: {
      label: _t('BAZ_FORM_EDIT_HELP'),
      value: '',
      placeholder: _t('BAZ_ACTIVATE_COMMENTS_HINT')
    },
    value: {
      label: _t('BAZ_FORM_EDIT_COMMENTS_FIELD_DEFAULT_ACTIVATION_LABEL'),
      options: { non: _t('NO'), oui: _t('YES'), ' ': '' }
    }
  },
  disabledAttributes: [
    'label', 'required'
  ],
  attributesMapping: {
    0: 'type',
    1: 'read',
    2: 'write',
    3: 'comment',
    4: 'fieldLabel',
    5: 'value',
    6: 'name',
    7: 'askIfActivateComments',
    8: '',
    9: '',
    10: 'hint'
  },
  renderInput(field) {
    return {
      field: field.askIfActivateComments == 1 ? `<i class="far fa-comment-dots"></i> ${field.fieldlabel || _t('BAZ_ACTIVATE_COMMENTS')}` : '',
      onRender() {
        const currentField = renderHelper.getHolder(field).parent()
        renderHelper.initializeField(currentField)
        $(currentField)
          .find('select[name=askIfActivateComments]:not(.initialized)')
          .change((event) => {
            const element = event.target

            const base = $(element).closest('.acls-field.form-field')
            $(element).addClass('initialized')

            const nameInput = $(base).find('input[type=text][name=name]')
            if (nameInput.val().trim().length == 0
              || nameInput.val().trim() == 'bf_acls') {
              nameInput.val('bf_commentaires')
            }

            const visibleSelect = $(base).find('select[name=askIfActivateComments]')
            const selectedValue = visibleSelect.val()

            const subElements = $(base)
              .find('.form-group.fieldLabel-wrap,.form-group.hint-wrap,.form-group.name-wrap,.form-group.value-wrap')
            if ([1, '1'].includes(selectedValue)) {
              subElements.show()
              const commentInput = $(base).find('select[name=comment]')
              const currentValue = commentInput.val()
              if (Array.isArray(currentValue)
                && (
                  currentValue.length == 0
                  || (currentValue.length == 1 && currentValue.includes('comments-closed'))
                )) {
                commentInput.val([' + '])
              }
            } else {
              subElements.hide()
            }
          })
          .trigger('change')
        renderHelper.defineLabelHintForGroup(field, 'fieldlabel', _t('BAZ_FORM_EDIT_COMMENTS_FIELD_ACTIVATE_HINT'))
        renderHelper.defineLabelHintForGroup(field, 'hint', _t('BAZ_FORM_EDIT_COMMENTS_FIELD_ACTIVATE_HINT'))
      }
    }
  }
}

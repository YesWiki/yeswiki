import { aclsOptions, aclsCommentOptions } from './commons/attributes.js'

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_ACL_LABEL'),
    name: 'acls',
    attrs: { type: 'acls' },
    icon: '<i class="fas fa-user-lock"></i>'
  },
  attributes: {
    entry_read_right: {
      label: _t('BAZ_FORM_EDIT_ACL_READ_LABEL'),
      options: aclsOptions,
      multiple: true
    },
    entry_write_right: {
      label: _t('BAZ_FORM_EDIT_ACL_WRITE_LABEL'),
      options: aclsOptions,
      multiple: true
    },
    entry_comment_right: {
      label: _t('BAZ_FORM_EDIT_ACL_COMMENT_LABEL'),
      options: aclsCommentOptions,
      multiple: true
    },
    ask_if_activate_comments: {
      label: _t('BAZ_FORM_EDIT_ACL_ASK_IF_ACTIVATE_COMMENT_LABEL'),
      options: { 0: _t('NO'), 1: _t('YES') }
    },
    label: {
      label: _t('BAZ_FORM_EDIT_COMMENTS_FIELD_ACTIVATE_LABEL'),
      value: '',
      placeholder: _t('BAZ_ACTIVATE_COMMENTS'),
      description: _t('BAZ_FORM_EDIT_COMMENTS_FIELD_ACTIVATE_HINT')
    },
    hint: {
      label: _t('BAZ_FORM_EDIT_HELP'),
      value: '',
      placeholder: _t('BAZ_ACTIVATE_COMMENTS_HINT')
    },
    default: {
      label: _t('BAZ_FORM_EDIT_COMMENTS_FIELD_DEFAULT_ACTIVATION_LABEL'),
      options: { non: _t('NO'), oui: _t('YES'), ' ': '' }
    }
  },
  disabledAttributes: [
    'required'
  ],
  editorSetup(api) {
    const applyActivateComments = () => {
      const activated = [1, '1'].includes(api.getValue('ask_if_activate_comments'))
      const toggled = ['label', 'hint', 'name', 'default']
      toggled.forEach((attr) => (activated ? api.show(attr) : api.hide(attr)))
      if (activated) {
        const name = (api.getValue('name') || '').trim()
        if (name.length === 0 || name === 'bf_acls') {
          api.setValue('name', 'bf_commentaires')
        }
        const comment = api.getValue('entry_comment_right')
        if (Array.isArray(comment)
          && (comment.length === 0
            || (comment.length === 1 && comment.includes('comments-closed')))) {
          api.setValue('entry_comment_right', ['+'])
        }
      }
    }
    api.onChange('ask_if_activate_comments', applyActivateComments)
    applyActivateComments()
  },
  renderInput(field) {
    return { field: field.ask_if_activate_comments === 1 || field.ask_if_activate_comments === '1' ? `<i class="far fa-comment-dots"></i> ${field.label || _t('BAZ_ACTIVATE_COMMENTS')}` : '' }
  }
}

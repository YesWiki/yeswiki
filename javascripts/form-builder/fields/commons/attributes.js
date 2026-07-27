// groupsList and formAndListIds are defined in forms_form.twig

// When the user adds, via the code tab, a list or a formId that does not exist, keep the
// value so it can be offered in the select option list. The template is the stored JSON
// array of field objects; enum fields carry their list/form id as `linked_object`.
const _listAndFormUserValues = {} // eslint-disable-line no-underscore-dangle
try {
  const template = JSON.parse(document.getElementById('form-builder-text')?.value ?? '[]')
  if (Array.isArray(template)) {
    template.forEach((field) => {
      const value = field?.linked_object
      if (value && !(value in formAndListIds.forms) && !(value in formAndListIds.lists)) {
        _listAndFormUserValues[value] = value
      }
    })
  }
} catch {
  // legacy/invalid template text: nothing to collect
}
export const listAndFormUserValues = _listAndFormUserValues

// Some attributes configuration used in multiple fields.
// ACL tokens are stored trimmed ('*', '+', '%') in the JSON template.
export const visibilityOptions = {
  '*': _t('EVERYONE'),
  '+': _t('IDENTIFIED_USERS'),
  '%': _t('BAZ_FORM_EDIT_OWNER_AND_ADMINS'),
  '@admins': _t('MEMBER_OF_GROUP', { groupName: 'admin' })
}

// create list of user groups
// groupsList variable is defined in forms_form.twig
const _formattedGroupList = {} // eslint-disable-line no-underscore-dangle
groupsList.forEach((group) => {
  _formattedGroupList[`@${group}`] = _t('MEMBER_OF_GROUP', { groupName: group })
})
export const formattedGroupList = _formattedGroupList

export const aclsOptions = {
  ...visibilityOptions,
  ...{
    user:
    _t('BAZ_FORM_EDIT_USER')
  },
  ...formattedGroupList
}

export const aclsCommentOptions = {
  ...{ 'comments-closed': _t('BAZ_FORM_EDIT_COMMENTS_CLOSED') },
  ...Object.fromEntries(Object.entries(visibilityOptions).filter(([key]) => key !== '*')),
  ...{ user: _t('BAZ_FORM_EDIT_USER') },
  ...formattedGroupList
}

export const readConf = {
  label: _t('BAZ_FORM_EDIT_CAN_BE_READ_BY'),
  options: { ...visibilityOptions, ...formattedGroupList },
  multiple: true
}

export const writeconf = {
  label: _t('BAZ_FORM_EDIT_CAN_BE_WRITTEN_BY'),
  options: { ...visibilityOptions, ...formattedGroupList },
  multiple: true
}

export const searchableConf = {
  label: _t('BAZ_FORM_EDIT_SEARCH_LABEL'),
  options: { '': _t('NO'), 1: _t('YES') }
}

export const selectConf = {
  subtype2: {
    transient: true,
    label: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_LABEL'),
    options: {
      list: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_LIST'),
      form: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_FORM')
    }
  },
  linked_object: {
    label: _t('BAZ_FORM_EDIT_SELECT_LIST_FORM_ID'),
    options: {
      ...{ '': '' },
      ...formAndListIds.lists,
      ...formAndListIds.forms,
      ...listAndFormUserValues
    }
  },
  default: {
    label: _t('BAZ_FORM_EDIT_SELECT_DEFAULT'),
    value: ''
  },
  hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
  read_access: readConf,
  write_access: writeconf
}

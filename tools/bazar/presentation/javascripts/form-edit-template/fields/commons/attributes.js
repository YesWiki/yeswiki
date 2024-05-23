// groupsList and formAndListIds are defined in forms_form.twig

// When user add manuall via wikiCode a list or a formId that does not exist, keep the value
// so it can be added in the select option list
const _listAndFormUserValues = {}
$('#form-builder-text').val().trim().split('\n')
  .forEach((textField) => {
    const fieldValues = textField.split('***')
    if (fieldValues.length > 1) {
      const [field, value] = fieldValues
      if (['checkboxfiche', 'checkbox', 'liste', 'radio', 'listefiche', 'radiofiche'].includes(field)
        && value && value != ' ' && !(value in formAndListIds.forms) && !(value in formAndListIds.lists)) {
        _listAndFormUserValues[value] = value
      }
    }
  })
export const listAndFormUserValues = _listAndFormUserValues

// Some attributes configuration used in multiple fields
export const visibilityOptions = {
  ' * ': _t('EVERYONE'),
  ' + ': _t('IDENTIFIED_USERS'),
  ' % ': _t('BAZ_FORM_EDIT_OWNER_AND_ADMINS'),
  '@admins': _t('MEMBER_OF_GROUP', { groupName: 'admin' })
}

// create list of user groups
// groupsList variable is defined in forms_form.twig
const _formattedGroupList = []
groupsList.map((group) => {
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
  ...Object.fromEntries(Object.entries(visibilityOptions).filter(([key]) => key != ' * ')),
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

export const semanticConf = {
  label: _t('BAZ_FORM_EDIT_SEMANTIC_LABEL'),
  value: '',
  placeholder: 'Ex: https://schema.org/name'
}

export const selectConf = {
  subtype2: {
    label: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_LABEL'),
    options: {
      list: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_LIST'),
      form: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_FORM')
    }
  },
  listeOrFormId: {
    label: _t('BAZ_FORM_EDIT_SELECT_LIST_FORM_ID'),
    options: {
      ...{ '': '' },
      ...formAndListIds.lists,
      ...formAndListIds.forms,
      ...listAndFormUserValues
    }
  },
  defaultValue: {
    label: _t('BAZ_FORM_EDIT_SELECT_DEFAULT'),
    value: ''
  },
  hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
  read: readConf,
  write: writeconf,
  semantic: semanticConf
}

// Mapping betwwen yes wiki syntax and FormBuilder json syntax
export const defaultMapping = {
  0: 'type',
  1: 'name',
  2: 'label',
  3: 'size',
  4: 'maxlength',
  5: 'value',
  6: 'pattern',
  7: 'subtype',
  8: 'required',
  9: 'searchable',
  10: 'hint',
  11: 'read',
  12: 'write',
  14: 'semantic',
  15: 'queries'
}

export const listsMapping = {
  ...defaultMapping,
  ...{ 1: 'listeOrFormId', 5: 'defaultValue', 6: 'name' }
}

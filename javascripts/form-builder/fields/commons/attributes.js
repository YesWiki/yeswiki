// groupsList and formAndListIds are defined in forms_form.twig
import openRemoteModal from '../../../helpers/remote-modal.js'

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

export const writeConf = {
  label: _t('BAZ_FORM_EDIT_CAN_BE_WRITTEN_BY'),
  options: { ...visibilityOptions, ...formattedGroupList },
  multiple: true
}

export const searchableConf = {
  label: _t('BAZ_FORM_EDIT_SEARCH_LABEL'),
  options: { '': _t('NO'), 1: _t('YES') }
}

// Opens the iframed list editor in a remote modal and resolves its postmessage
// answer (same contract as the old builder: the editor posts list_created /
// list_updated back). Listens via addEventListener so concurrent handlers on
// window.onmessage are left alone, and unhooks itself when the modal closes.
function openListEditorModal(title, url, expectedMsg, onDone) {
  const modal = openRemoteModal(title, url)
  const onMessage = (event) => {
    if (event.data?.msg === expectedMsg) {
      onDone(event.data)
      toastMessage(_t(expectedMsg === 'list_created' ? 'LIST_CREATED' : 'LIST_UPDATED'), 3000, 'yw-alert yw-alert--success')
      window.removeEventListener('message', onMessage)
      modal.close()
    }
  }
  window.addEventListener('message', onMessage)
}

function listActionButton(iconClass, onClick) {
  const button = document.createElement('button')
  button.type = 'button'
  button.className = 'yw-btn yw-btn--sm yw-btn--primary'
  button.innerHTML = `<i class="fa ${iconClass}"></i>`
  button.addEventListener('click', onClick)
  return button
}

// Shared editorSetup for the enum family (select / checkbox-group / radio-group):
// the data source choice (list vs form) repopulates the linked_object options, and
// list sources get create/edit buttons opening the list editor in a remote modal.
export function enumEditorSetup(api) {
  const updateOptions = () => {
    const source = api.getValue('subtype2') === 'form' ? 'forms' : 'lists'
    api.setOptions('linked_object', { ...{ '': '' }, ...formAndListIds[source], ...listAndFormUserValues })
  }
  api.onChange('subtype2', updateOptions)

  const row = api.getRow('linked_object')
  if (!row) return

  const editButton = listActionButton('fa-pen', () => {
    const listId = api.getValue('linked_object')
    openListEditorModal(
      _t('LIST_UPDATE_TITLE'),
      wiki.url(`?BazaR/iframe&vue=listes&action=modif_liste&voirmenu=0&onsubmit=postmessage&idliste=${listId}`),
      'list_updated',
      (data) => {
        // update the options (list name might have changed)
        formAndListIds.lists[data.id] = data.title
        updateOptions()
      }
    )
  })

  const createButton = listActionButton('fa-plus', () => {
    openListEditorModal(
      _t('LIST_CREATE_TITLE'),
      wiki.url('?BazaR/iframe&vue=listes&action=saisir_liste&voirmenu=0&onsubmit=postmessage'),
      'list_created',
      (data) => {
        formAndListIds.lists[data.id] = data.title
        updateOptions()
        // select the newly created list
        api.setValue('linked_object', data.id)
      }
    )
  })

  const actions = document.createElement('div')
  actions.className = 'yw-fb__list-actions'
  actions.append(editButton, createButton)
  row.append(actions)

  const applyVisibility = () => {
    const isList = api.getValue('subtype2') !== 'form'
    actions.classList.toggle('hide', !isList)
    editButton.classList.toggle('hide', !api.getValue('linked_object'))
  }
  api.onChange('subtype2', applyVisibility)
  api.onChange('linked_object', applyVisibility)
  applyVisibility()
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
  write_access: writeConf
}

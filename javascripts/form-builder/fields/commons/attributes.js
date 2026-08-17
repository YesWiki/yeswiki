import openRemoteModal from '../../../helpers/remote-modal.js'

const _listAndFormUserValues = {}
try {
  const template = JSON.parse(
    document.getElementById('form-builder-text')?.value ?? '[]',
  )
  if (Array.isArray(template)) {
    template.forEach((field) => {
      const value = field?.linked_object
      if (
        value &&
        !(value in formAndListIds.forms) &&
        !(value in formAndListIds.lists)
      ) {
        _listAndFormUserValues[value] = value
      }
    })
  }
} catch {}
export const listAndFormUserValues = _listAndFormUserValues

export const visibilityOptions = {
  '*': _t('EVERYONE'),
  '+': _t('IDENTIFIED_USERS'),
  '%': _t('BAZ_FORM_EDIT_OWNER_AND_ADMINS'),
  '@admins': _t('MEMBER_OF_GROUP', { groupName: 'admin' }),
}

const _formattedGroupList = {}
groupsList.forEach((group) => {
  _formattedGroupList[`@${group}`] = _t('MEMBER_OF_GROUP', { groupName: group })
})
export const formattedGroupList = _formattedGroupList

export const aclsOptions = {
  ...visibilityOptions,
  ...{
    user: _t('BAZ_FORM_EDIT_USER'),
  },
  ...formattedGroupList,
}

export const aclsCommentOptions = {
  ...{ 'comments-closed': _t('BAZ_FORM_EDIT_COMMENTS_CLOSED') },
  ...Object.fromEntries(
    Object.entries(visibilityOptions).filter(([key]) => key !== '*'),
  ),
  ...{ user: _t('BAZ_FORM_EDIT_USER') },
  ...formattedGroupList,
}

export const readConf = {
  label: _t('BAZ_FORM_EDIT_CAN_BE_READ_BY'),
  options: { ...visibilityOptions, ...formattedGroupList },
  multiple: true,
}

export const writeConf = {
  label: _t('BAZ_FORM_EDIT_CAN_BE_WRITTEN_BY'),
  options: { ...visibilityOptions, ...formattedGroupList },
  multiple: true,
}

export const searchableConf = {
  label: _t('BAZ_FORM_EDIT_SEARCH_LABEL'),
  options: { '': _t('NO'), 1: _t('YES') },
}

function openListEditorModal(title, url, expectedMsg, onDone) {
  const modal = openRemoteModal(title, url)
  const onMessage = (event) => {
    if (event.data?.msg === expectedMsg) {
      onDone(event.data)
      toastMessage(
        _t(expectedMsg === 'list_created' ? 'LIST_CREATED' : 'LIST_UPDATED'),
        3000,
        'yw-alert yw-alert--success',
      )
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

export function enumEditorSetup(api) {
  const updateOptions = () => {
    const source = api.getValue('subtype2') === 'form' ? 'forms' : 'lists'
    api.setOptions('linked_object', {
      ...{ '': '' },
      ...formAndListIds[source],
      ...listAndFormUserValues,
    })
  }
  api.onChange('subtype2', updateOptions)

  const row = api.getRow('linked_object')
  if (!row) return

  const editButton = listActionButton('fa-pen', () => {
    const listId = api.getValue('linked_object')
    openListEditorModal(
      _t('LIST_UPDATE_TITLE'),
      wiki.url(
        `?BazaR/iframe&view=listes&action=modif_liste&showmenu=0&onsubmit=postmessage&listid=${listId}`,
      ),
      'list_updated',
      (data) => {
        formAndListIds.lists[data.id] = data.title
        updateOptions()
      },
    )
  })

  const createButton = listActionButton('fa-plus', () => {
    openListEditorModal(
      _t('LIST_CREATE_TITLE'),
      wiki.url(
        '?BazaR/iframe&view=listes&action=saisir_liste&showmenu=0&onsubmit=postmessage',
      ),
      'list_created',
      (data) => {
        formAndListIds.lists[data.id] = data.title
        updateOptions()
        api.setValue('linked_object', data.id)
      },
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
      form: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_FORM'),
    },
  },
  linked_object: {
    label: _t('BAZ_FORM_EDIT_SELECT_LIST_FORM_ID'),
    options: {
      ...{ '': '' },
      ...formAndListIds.lists,
      ...formAndListIds.forms,
      ...listAndFormUserValues,
    },
  },
  default: {
    label: _t('BAZ_FORM_EDIT_SELECT_DEFAULT'),
    value: '',
  },
  hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
  read_access: readConf,
  write_access: writeConf,
}

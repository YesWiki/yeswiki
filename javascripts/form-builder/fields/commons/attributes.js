import openRemoteModal from '../../../helpers/remote-modal.js'

const visibilityOptions = {
  '*': _t('EVERYONE'),
  '+': _t('IDENTIFIED_USERS'),
  '%': _t('BAZ_FORM_EDIT_OWNER_AND_ADMINS'),
  '@admins': _t('MEMBER_OF_GROUP', { groupName: 'admin' }),
}

let formAndListIds = { forms: {}, lists: {} }
let listAndFormUserValues = {}

function parseJson(text, fallback) {
  try {
    const parsed = JSON.parse(text || '')
    return parsed && typeof parsed === 'object' ? parsed : fallback
  } catch {
    return fallback
  }
}

function replaceEntries(target, source) {
  Object.keys(target).forEach((key) => delete target[key])
  Object.assign(target, source)
}

/** Fills the page-dependent option lists in place at boot, since field configs spread these objects at import time. */
export function refreshDesignerData(container) {
  const groups = parseJson(container?.dataset.groups, [])
  formAndListIds = {
    forms: {},
    lists: {},
    ...parseJson(container?.dataset.formAndListIds, {}),
  }
  const groupOptions = {}
  ;(Array.isArray(groups) ? groups : []).forEach((group) => {
    groupOptions[`@${group}`] = _t('MEMBER_OF_GROUP', { groupName: group })
  })
  listAndFormUserValues = {}
  const template = parseJson(
    document.getElementById('form-builder-text')?.value,
    [],
  )
  ;(Array.isArray(template) ? template : []).forEach((field) => {
    const value = field?.linked_object
    if (
      value &&
      !(value in formAndListIds.forms) &&
      !(value in formAndListIds.lists)
    ) {
      listAndFormUserValues[value] = value
    }
  })
  replaceEntries(readConf.options, { ...visibilityOptions, ...groupOptions })
  replaceEntries(writeConf.options, { ...visibilityOptions, ...groupOptions })
  replaceEntries(selectConf.linked_object.options, {
    '': '',
    ...formAndListIds.lists,
    ...formAndListIds.forms,
    ...listAndFormUserValues,
  })
}

export const readConf = {
  label: _t('BAZ_FORM_EDIT_CAN_BE_READ_BY'),
  options: { ...visibilityOptions },
  multiple: true,
}

export const writeConf = {
  label: _t('BAZ_FORM_EDIT_CAN_BE_WRITTEN_BY'),
  options: { ...visibilityOptions },
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
      '': '',
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
    options: { '': '' },
  },
  default: {
    label: _t('BAZ_FORM_EDIT_SELECT_DEFAULT'),
    value: '',
  },
  hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: '' },
  read_access: readConf,
  write_access: writeConf,
}

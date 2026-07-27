// duplicate.js — duplicate a page/entry to a remote wiki (ticket 16: vanilla JS,
// fetch instead of $.ajax)
let shortUrl = ''

function isValidUrl(string) {
  try {
    const url = new URL(string)
    return url
  } catch {
    return false
  }
}

function arrayIncludesAllRequiredFields(arr, fields) {
  return fields.every((v) => arr.some((i) => i.id === v.id && i.type === v.type))
}

function setMessageState(element, state) {
  const group = element ? element.closest('.yw-form-group') : null
  if (!group) return
  group.classList.remove('has-error', 'has-success')
  group.classList.add(state === 'success' ? 'has-success' : 'has-error')
}

function setHidden(selector, hidden) {
  document.querySelectorAll(selector).forEach((el) => {
    el.classList.toggle('hide', hidden)
  })
}

function blockDuplicationName(tag) {
  document.querySelectorAll('[name=duplicate-action]').forEach((button) => {
    button.setAttribute('disabled', 'disabled')
    button.classList.add('disabled')
  })
  setMessageState(document.getElementById('newTag'), 'error')
  const message = document.getElementById('pagetag-message')
  if (message) message.innerHTML = _t('PAGE_NOT_AVAILABLE', { tag })
}

function validateDuplicationName(tag) {
  document.querySelectorAll('[name=duplicate-action]').forEach((button) => {
    button.removeAttribute('disabled')
    button.classList.remove('disabled')
  })
  setMessageState(document.getElementById('newTag'), 'success')
  const message = document.getElementById('pagetag-message')
  if (message) message.innerHTML = _t('PAGE_AVAILABLE', { tag })
}

function checkPageExistence(url) {
  const tag = url.replace(`${shortUrl}/?api/pages/`, '')
  fetch(url)
    .then((response) => {
      if (response.ok) {
        blockDuplicationName(tag)
      } else if (response.status === 404) {
        validateDuplicationName(tag)
      } else {
        blockDuplicationName(tag)
      }
    })
    .catch(() => {
      blockDuplicationName(tag)
    })
}

function handleLoginResponse(data) {
  const loginMessage = document.getElementById('login-message')
  if (data.isAdmin === true) {
    if (loginMessage) {
      loginMessage.innerHTML = _t('CONNECTED_AS_ADMIN', { user: data.user })
      setMessageState(loginMessage, 'success')
    }
    setHidden('.login-fields', true)
    setHidden('.duplication-fields', false)
    const newTag = document.getElementById('newTag')
    checkPageExistence(`${shortUrl}/?api/pages/${newTag ? newTag.value : ''}`)
  } else {
    if (loginMessage) {
      loginMessage.innerHTML = _t('CONNECTED_BUT_NOT_ADMIN', { user: data.user })
      setMessageState(loginMessage, 'error')
    }
    setHidden('.duplication-fields', true)
    setHidden('.login-fields', false)
  }
}

function setFormMessage(state, text) {
  const formMessage = document.getElementById('form-message')
  if (!formMessage) return
  formMessage.classList.remove('has-error', 'has-success')
  formMessage.classList.add(state === 'success' ? 'has-success' : 'has-error')
  const help = formMessage.querySelector('.help-block')
  if (help) help.innerHTML = text
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll(
    '.duplication-wiki-form, .duplication-login-form, #form-duplication'
  ).forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault()
      e.stopPropagation()
    })
  })

  const urlWiki = document.getElementById('url-wiki')
  if (urlWiki) {
    urlWiki.addEventListener('change', () => {
      setHidden('.login-fields, .duplication-fields', true)
      const loginMessage = document.getElementById('login-message')
      if (loginMessage) loginMessage.innerHTML = ''
    })
  }

  document.querySelectorAll('.btn-distant-login').forEach((button) => {
    button.addEventListener('click', (e) => {
      e.preventDefault()
      const username = document.getElementById('username')
      const password = document.getElementById('password')
      fetch(`${shortUrl}/?api/login`, {
        method: 'POST',
        body: new URLSearchParams({
          username: username ? username.value : '',
          password: password ? password.value : ''
        })
      })
        .then((response) => response.json()
          .then((data) => (response.ok
            ? data
            : Promise.reject(Object.assign(new Error(), { status: response.status, data })))))
        .then(handleLoginResponse)
        .catch((error) => {
          if (error.data && error.data.error) {
            toastMessage(error.data.error, 3000, 'alert alert-danger')
          }
          if (error.status === 401) {
            const loginMessage = document.getElementById('login-message')
            if (loginMessage) {
              loginMessage.replaceChildren()
              const notConnected = document.createElement('div')
              notConnected.className = 'text-danger'
              notConnected.textContent = _t('NOT_CONNECTED')
              loginMessage.appendChild(notConnected)
            }
            setHidden('.login-fields', false)
          }
        })
    })
  })

  document.querySelectorAll('[name="duplicate-action"]').forEach((button) => {
    button.addEventListener('click', (e) => {
      e.preventDefault()
      const btnAction = e.currentTarget.value
      const newTag = document.getElementById('newTag')
      const form = document.getElementById('form-duplication')
      fetch(`${shortUrl}/?api/pages/${newTag ? newTag.value : ''}/duplicate`, {
        method: 'POST',
        headers: { accept: 'application/json' },
        body: new URLSearchParams(new FormData(form))
      })
        .then((response) => (response.ok
          ? response.json()
          : Promise.reject(response.status)))
        .then((d) => {
          if (btnAction === 'open') {
            document.location = `${shortUrl}/?${d.newTag}`
          } else if (btnAction === 'edit') {
            document.location = `${shortUrl}/?${d.newTag}/edit`
          } else {
            const url = document.location.href.replace(/\/duplicate.*/, '')
            document.location = url
          }
        })
        .catch((status) => {
          toastMessage(
            `${_t('ERROR')} ${status}`,
            3000,
            'alert alert-danger'
          )
        })
    })
  })

  document.querySelectorAll('.btn-verify-tag').forEach((button) => {
    button.addEventListener('click', () => {
      const newTag = document.getElementById('newTag')
      checkPageExistence(`${shortUrl}/?api/pages/${newTag ? newTag.value : ''}`)
    })
  })

  document.querySelectorAll('.btn-verify-wiki').forEach((button) => {
    button.addEventListener('click', () => {
      const urlInput = document.querySelector('.duplication-wiki-form #url-wiki')
      let url = urlInput ? urlInput.value : ''

      if (!isValidUrl(url)) {
        toastMessage(_t('NOT_VALID_URL', { url }), 3000, 'alert alert-danger')
        return
      }
      const taburl = url.search('wakka.php') > -1 ? url.split('wakka.php') : url.split('?')
      shortUrl = taburl[0].replace(/\/+$/g, '')
      const baseUrlHolder = document.getElementById('base-url')
      if (baseUrlHolder) baseUrlHolder.textContent = `${shortUrl}/?`
      url = `${shortUrl}/?api/auth/me`
      fetch(url)
        .then((response) => (response.ok
          ? response.json()
          : Promise.reject(response.status)))
        .then((data) => {
          handleLoginResponse(data)

          // if case of entry, we need to check if form id is available and compatible
          // or propose another id
          const formIdInput = document.getElementById('form-id')
          const formId = formIdInput ? formIdInput.value : undefined
          if (typeof formId !== 'undefined') {
            fetch(`${shortUrl}/?api/forms/${formId}`)
              .then((response) => (response.ok
                ? response.json()
                : Promise.reject(response.status)))
              .then((form) => {
                const requiredFields = form.prepared.filter(
                  (field) => field.required === true
                )
                // we check if the found formId is compatible
                if (
                  arrayIncludesAllRequiredFields(
                    window.sourceForm.prepared,
                    requiredFields
                  )
                ) {
                  setFormMessage('success', _t('FORM_ID_IS_COMPATIBLE', { id: formId }))
                } else {
                  setFormMessage('error', _t('FORM_ID_NOT_AVAILABLE', { id: formId }))
                }
              })
              .catch((status) => {
                if (status === 404) {
                  // the formId is available
                  setFormMessage('success', _t('FORM_ID_AVAILABLE', { id: formId }))
                }
              })
          }
        })
        .catch((status) => {
          if (status === 401) {
            const loginMessage = document.getElementById('login-message')
            if (loginMessage) {
              loginMessage.innerHTML = `<div class="text-danger">${_t('NOT_CONNECTED')}</div>`
            }
            setHidden('.login-fields', false)
          } else {
            toastMessage(
              _t('NOT_WIKI_OR_OLD_WIKI', { url }),
              3000,
              'alert alert-danger'
            )
          }
        })
    })
  })
})

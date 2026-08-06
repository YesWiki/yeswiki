// users-table.js — admin user creation/deletion on the users table page
// (ticket 16: vanilla JS; the table itself is a yw-datatable now)
const usersTableService = {
  isRunning: false,
  postForm(url, data) {
    return fetch(url, { method: 'POST', body: new URLSearchParams(data) }).then(
      (response) =>
        response
          .json()
          .then((payload) => (response.ok ? payload : Promise.reject(payload))),
    )
  },
  createUser(elem) {
    const form = elem.closest('form')
    if (!form) {
      usersTableService.isRunning = false
      elem.removeAttribute('disabled')
      return
    }
    const inputName = form.querySelector('[name=name]')
    const inputEmail = form.querySelector('[name=email]')
    const name = inputName ? inputName.value : ''
    const email = inputEmail ? inputEmail.value : ''
    usersTableService
      .postForm(wiki.url('api/users'), { name, email })
      .then((data) => {
        const userName = data.user.name
        const userEmail = data.user.email
        const userLink = data.user.link
        const { signuptime } = data.user
        if (inputName) inputName.value = ''
        if (inputEmail) inputEmail.value = ''
        // append a row to the users table (admin view column layout)
        const table = document.querySelector('#users-table-action table')
        const tbody = table ? table.tBodies[0] : null
        if (tbody) {
          const row = tbody.insertRow()
          const cells = ['', userName, '', userEmail, signuptime, '']
          cells.forEach((text) => {
            row.insertCell().textContent = text
          })
        }
        if (userLink !== '') {
          const holder = document.getElementById(
            'users-table-link-change-password',
          )
          if (holder) {
            holder.innerHTML =
              `<br/><label>${_t('LINK_TO_CHANGE_PASSWORD')}</label><br/>` +
              `<a href='${userLink}' target='_blank'>${userLink}</a>`
          }
        }
        toastMessage(
          _t('USERSTABLE_USER_CREATED', { name: userName }),
          1100,
          'alert alert-success',
        )
      })
      .catch((payload) => {
        toastMessage(
          _t('USERSTABLE_USER_NOT_CREATED', {
            name,
            error: payload && payload.error ? payload.error : '',
          }),
          3000,
          'alert alert-danger',
        )
      })
      .finally(() => {
        elem.removeAttribute('disabled')
        usersTableService.isRunning = false
      })
  },
  deleteUser(event) {
    event.preventDefault()
    if (usersTableService.isRunning) return
    usersTableService.isRunning = true
    const elem = event.target
    if (!elem) return
    elem.setAttribute('disabled', 'disabled')
    const { name } = elem.dataset
    const csrfToken = wiki.antiCsrfToken || 'error wiki has not antiCsrfToken'
    const targetNode = elem.userTargetNode
    const modal = elem.userModal

    usersTableService
      .postForm(wiki.url(`api/users/${encodeURIComponent(name)}/delete`), {
        csrfToken,
      })
      .then(() => {
        if (targetNode) {
          const row = targetNode.closest('tr')
          if (row) row.remove()
        }
        const results = modal
          ? modal.querySelector('.yw-modal__body .multi-delete-results')
          : null
        if (results) {
          const done = document.createElement('div')
          done.textContent = _t('USERSTABLE_USER_DELETED', { username: name })
          results.appendChild(done)
        }
      })
      .catch((payload) => {
        multiDeleteService.addErrorMessage(
          modal,
          `${_t('USERSTABLE_USER_NOT_DELETED', { username: name })} : ${
            payload && payload.error ? payload.error : ''
          }`,
        )
      })
      .finally(() => {
        multiDeleteService.updateProgressBar(modal, ['test'], 0)
        usersTableService.isRunning = false
      })
  },
}

document.addEventListener('click', (event) => {
  const button = event.target.closest('form.form-inline button.create-user')
  if (!button) return
  event.preventDefault()
  if (!usersTableService.isRunning) {
    usersTableService.isRunning = true
    button.setAttribute('disabled', 'disabled')
    usersTableService.createUser(button)
  }
})

const userTableDeleteModal = document.getElementById('userTableDeleteModal')
if (userTableDeleteModal) {
  userTableDeleteModal.addEventListener('yw-modal-shown', (event) => {
    const modal = userTableDeleteModal
    multiDeleteService.initProgressBar(modal)
    modal
      .querySelectorAll('.yw-modal__body .multi-delete-results')
      .forEach((resultsParam) => {
        const results = resultsParam
        results.innerHTML = ''
      })
    const deleteButton = modal.querySelector('button.start-btn-delete-user')
    if (!deleteButton) return
    deleteButton.removeAttribute('disabled')
    const opener = event.detail.relatedTarget // Button that triggered the modal
    const name = opener ? opener.dataset.name : ''
    const nameHolder = modal.querySelector('#userNameToDelete')
    if (nameHolder) nameHolder.textContent = name
    deleteButton.dataset.name = name
    deleteButton.userTargetNode = opener
    deleteButton.userModal = modal
    if (!deleteButton.classList.contains('eventSet')) {
      deleteButton.classList.add('eventSet')
      deleteButton.addEventListener('click', usersTableService.deleteUser)
    }
  })
}

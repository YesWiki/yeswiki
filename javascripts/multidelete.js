// multidelete.js — bulk deletion of pages/users/comments from admin tables
// (ticket 16: vanilla JS, yw-modal events instead of Bootstrap modal events)

// eslint-disable-next-line no-unused-vars
function checkAllFirstCol(elem) {
  const newState = elem.checked
  // DataTables-driven tables live in a .dataTables_wrapper; yw-datatable ones
  // don't, the table element itself is the scope there
  const scope = elem.closest('.dataTables_wrapper') || elem.closest('table')
  if (!scope) return
  scope
    .querySelectorAll('tr > td:first-child input.selectline[type=checkbox]')
    .forEach((checkboxParam) => {
      const checkbox = checkboxParam
      if (checkbox.offsetParent === null) return // hidden
      checkbox.checked = newState
      checkbox.dispatchEvent(new Event('change', { bubbles: true }))
    })

  scope
    .querySelectorAll(
      'tr > th:first-child label.check-all-container input[type=checkbox]',
    )
    .forEach((checkboxParam) => {
      const checkbox = checkboxParam
      checkbox.checked = newState
    })
}

const multiDeleteService = {
  isRunning: false,
  refreshOnModalClosing: {},
  modalClosing(modalContainer) {
    const id = modalContainer && modalContainer.id
    if (id && this.refreshOnModalClosing[id] === true) {
      window.location.reload()
    }
  },
  initProgressBar(modal) {
    this.updateProgressBar(modal, ['test'], -1)
  },
  updateProgressBar(modal, items, currentIndex) {
    if (!modal) return
    const value =
      items.length === 0
        ? 100
        : Math.min(100, Math.round(((currentIndex + 1) / items.length) * 100))
    modal
      .querySelectorAll(
        '.modal-footer .progress-bar, .yw-modal__footer .yw-progressbar__bar',
      )
      .forEach((barParam) => {
        const bar = barParam
        bar.style.width = `${value}%`
        bar.setAttribute('aria-valuenow', value)
      })
  },
  resultsContainer(modal) {
    return modal
      ? modal.querySelector(
          '.modal-body .multi-delete-results, .yw-modal__body .multi-delete-results',
        )
      : null
  },
  addErrorMessage(modal, message) {
    const results = this.resultsContainer(modal)
    if (!results) return
    const error = document.createElement('div')
    error.className = 'yw-alert yw-alert--danger'
    error.textContent = message
    results.appendChild(error)
  },
  removeLine(target, itemId) {
    const row = document.querySelector(`#${target} [data-itemid="${itemId}"]`)
    const line = row ? row.closest('tr') : null
    if (!line) {
      return false
    }
    line.remove()
    return true
  },
  deleteNextItem(modal, items, type, currentIndex, target) {
    this.updateProgressBar(modal, items, currentIndex)
    if (currentIndex + 1 < items.length) {
      this.deleteOneItem(modal, items, type, currentIndex + 1, target)
    } else {
      this.isRunning = false
      const results = this.resultsContainer(modal)
      if (results) {
        const done = document.createElement('div')
        done.textContent = _t('MULTIDELETE_END')
        results.appendChild(done)
      }
    }
  },
  deleteOneItem(modal, items, type, currentIndex, target) {
    if (['pages', 'comments', 'users'].indexOf(type) === -1) {
      this.addErrorMessage(
        modal,
        "Unknown type ! Should be 'pages' or 'users' or 'comments'!",
      )
      return
    }
    const item = items[currentIndex] ?? {}
    const itemId = item.id !== undefined ? item.id : ''
    let csrfToken = item.token !== undefined ? item.token : ''
    if ('antiCsrfToken' in wiki) {
      csrfToken = wiki.antiCsrfToken
    }
    if (itemId.length === 0 || csrfToken.length === 0) {
      this.deleteNextItem(modal, items, type, currentIndex, target)
      return
    }
    this.localFetchJson(
      wiki.url(`?api/${type}/${encodeURIComponent(itemId)}/delete`),
      {
        method: 'POST',
        timeout: 30000, // 30 seconds,
        data: { csrfToken },
      },
    )
      .then(() => {
        if (!this.removeLine(target, itemId)) {
          this.refreshOnModalClosing[modal.id] = true
        }
      })
      .catch((error) => {
        this.addErrorMessage(
          modal,
          _t('MULTIDELETE_ERROR')
            .replace('{itemId}', itemId)
            .replace('{error}', error),
        )
        // if error force reload
        this.refreshOnModalClosing[modal.id] = true
      })
      .finally(() => {
        setTimeout(() => {
          this.deleteNextItem(modal, items, type, currentIndex, target)
        }, 0)
      })
  },
  deleteItems(elem) {
    const { target, type } = elem.dataset
    if (!target || target.length === 0) return
    const container = document.getElementById(target)
    const modal = elem.closest('.yw-modal, .modal')
    if (!container || !modal) return
    const inputs = Array.from(
      container.querySelectorAll(
        'tr > td:first-child input.selectline[type=checkbox]',
      ),
    ).filter((input) => input.offsetParent !== null && input.checked)

    const items = []
    inputs.forEach((input) => {
      const itemId = input.dataset.itemid
      const csrfToken = input.dataset.csrftoken
      if (
        itemId &&
        itemId.length > 0 &&
        (csrfToken === undefined || csrfToken.length === 0)
      ) {
        items.push({ id: itemId })
      } else if (itemId && itemId.length > 0 && csrfToken.length > 0) {
        items.push({ id: itemId, token: csrfToken })
      }
    })
    if (items.length > 0) {
      setTimeout(() => {
        this.deleteOneItem(modal, items, type, 0, target)
      }, 0)
    }
  },
  async localFetchJson(url, options) {
    const internalOptions = {}
    let resetTimeoutId = null
    if ('timeout' in options && Number(options.timeout) > 0) {
      const abortController = new AbortController()
      resetTimeoutId = setTimeout(
        () => abortController.abort(),
        options.timeout,
      )
      internalOptions.signal = abortController.signal
    }
    if ('method' in options && options.method === 'POST') {
      internalOptions.method = 'POST'
      internalOptions.body = new URLSearchParams(
        this.prepareFormData(options.data ?? {}),
      )
      internalOptions.headers = new Headers().append(
        'Content-Type',
        'application/x-www-form-urlencoded',
      )
    }
    return fetch(url, internalOptions)
      .then(async (response) => {
        if (response.ok) {
          return response.json()
        }
        let errorDetail = ''
        try {
          const body = await response.json()
          if (body && body.error) {
            errorDetail = `: ${body.error}`
          }
        } catch {
          /* ignore parse errors */
        }
        throw new Error(
          `Response is not ok (code ${response.status})${errorDetail}`,
        )
      })
      .finally(() => {
        if (resetTimeoutId !== null) {
          clearTimeout(resetTimeoutId)
        }
      })
  },
  prepareFormData(thing) {
    const formData = new FormData()
    if (typeof thing === 'object') {
      Object.keys(thing).forEach((key) => {
        formData.append(key, String(thing[key]))
      })
    }
    return formData
  },
  updateNbSelected(modalId) {
    const modal = document.getElementById(modalId)
    if (!modal) return
    const button = modal.querySelector('button.start-btn-delete-all')
    const text = modal.querySelector('.nb-elem-selected')
    const target = button ? button.dataset.target : null
    if (text && target && target.length > 0) {
      const container = document.getElementById(target)
      const nb = container
        ? Array.from(
            container.querySelectorAll(
              'tr > td:first-child input.selectline[type=checkbox]',
            ),
          ).filter((input) => input.offsetParent !== null && input.checked)
            .length
        : 0
      text.textContent = nb
    } else if (text) {
      text.textContent = 'error'
    }
  },
}

document.addEventListener('click', (event) => {
  const button = event.target.closest('button.start-btn-delete-all')
  if (!button) return
  if (!multiDeleteService.isRunning) {
    multiDeleteService.isRunning = true
    button.setAttribute('disabled', 'disabled')
    multiDeleteService.deleteItems(button)
  }
})

document.addEventListener('yw-modal-shown', (event) => {
  const modal = event.target.closest('.yw-modal.multidelete')
  if (!modal) return
  multiDeleteService.initProgressBar(modal)
  const results = multiDeleteService.resultsContainer(modal)
  if (results) results.innerHTML = ''
  const button = modal.querySelector('button.start-btn-delete-all')
  if (button) button.removeAttribute('disabled')
})

document.addEventListener('yw-modal-hidden', (event) => {
  const modal = event.target.closest('.yw-modal.multidelete')
  if (!modal) return
  multiDeleteService.modalClosing(modal)
})

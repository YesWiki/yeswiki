// The wiki's own confirmation dialog, in its colours and language, for scripts, `data-yw-confirm` attributes and `hx-confirm`.
;(() => {
  let dialog = null
  let answer = null

  /** The one dialog every confirmation reuses, made on first use. */
  const theDialog = () => {
    if (dialog?.isConnected) return dialog
    dialog = document.createElement('dialog')
    dialog.className = 'yw-dialog'
    dialog.innerHTML = `
      <form method="dialog" class="yw-dialog__body">
        <p class="yw-dialog__message"></p>
        <div class="yw-dialog__actions">
          <button type="submit" value="cancel" class="yw-btn" data-yw-dialog-cancel></button>
          <button type="submit" value="confirm" class="yw-btn yw-btn--primary" data-yw-dialog-confirm></button>
        </div>
      </form>`
    dialog.addEventListener('close', () => {
      const resolve = answer
      answer = null
      resolve?.(dialog.returnValue === 'confirm')
    })
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close('cancel')
    })
    document.body.append(dialog)
    return dialog
  }

  /** Asks the question; resolves true when the visitor confirms, false on Cancel, Escape or a click outside. */
  const ywConfirm = (
    message,
    { confirmLabel = '', cancelLabel = '', destructive = false } = {},
  ) => {
    const box = theDialog()
    if (box.open) return Promise.resolve(false)
    box.returnValue = ''
    box.querySelector('.yw-dialog__message').textContent = message
    const cancel = box.querySelector('[data-yw-dialog-cancel]')
    const accept = box.querySelector('[data-yw-dialog-confirm]')
    cancel.textContent = cancelLabel || _t('BAZ_ANNULER')
    accept.textContent = confirmLabel || _t('OK')
    accept.classList.toggle('yw-btn--danger', destructive)
    accept.classList.toggle('yw-btn--primary', !destructive)
    return new Promise((resolve) => {
      answer = resolve
      box.showModal()
      ;(destructive ? cancel : accept).focus()
    })
  }

  /** For a submit listener: false after cancelling the submit to ask, true when the submit is the confirmed one being sent again. */
  const ywConfirmSubmit = (event, message, options = {}) => {
    const form = event.target
    if (form.dataset.ywConfirmed !== undefined) {
      delete form.dataset.ywConfirmed
      return true
    }
    event.preventDefault()
    const submitter = event.submitter
    ywConfirm(message, options).then((confirmed) => {
      if (!confirmed) return
      form.dataset.ywConfirmed = ''
      form.requestSubmit(submitter ?? undefined)
    })
    return false
  }

  const optionsOf = (element) => ({
    destructive: element.hasAttribute('data-yw-confirm-destructive'),
  })

  document.addEventListener(
    'click',
    (event) => {
      const element = event.target.closest?.(
        'a[data-yw-confirm], button[data-yw-confirm]',
      )
      if (!element) return
      if (element.dataset.ywConfirmed !== undefined) {
        delete element.dataset.ywConfirmed
        return
      }
      event.preventDefault()
      event.stopImmediatePropagation()
      ywConfirm(element.dataset.ywConfirm, optionsOf(element)).then(
        (confirmed) => {
          if (!confirmed) return
          element.dataset.ywConfirmed = ''
          element.click()
        },
      )
    },
    true,
  )

  document.addEventListener(
    'submit',
    (event) => {
      const form = event.target
      if (!(form instanceof HTMLFormElement) || !form.dataset.ywConfirm) return
      if (!ywConfirmSubmit(event, form.dataset.ywConfirm, optionsOf(form))) {
        event.stopImmediatePropagation()
      }
    },
    true,
  )

  document.addEventListener('htmx:confirm', (event) => {
    const { question, elt } = event.detail
    if (!question) return
    event.preventDefault()
    ywConfirm(question, optionsOf(elt)).then(
      (confirmed) => confirmed && event.detail.issueRequest(true),
    )
  })

  window.ywConfirm = ywConfirm
  window.ywConfirmSubmit = ywConfirmSubmit
})()

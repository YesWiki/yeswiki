// A form that changes something is sent once: a second submit while the first is on its way is ignored, and its button says it is busy.
;(() => {
  const BUSY = 'yw-btn--busy'
  const RELEASE_AFTER_MS = 30000
  const held = new Map()

  /** Whether this submit may be sent any number of times: a search, a filter, a dialog's buttons, or a form that says so. */
  const resubmittable = (form) =>
    form.hasAttribute('data-yw-allow-resubmit') ||
    ['get', 'dialog'].includes(String(form.method).toLowerCase()) ||
    (form.target !== '' && form.target !== '_self')

  /** Marks the element as on its way, and its button as busy; a submit that leaves the page open (a download) is released after a while. */
  const hold = (element, button) => {
    const timer = setTimeout(() => release(element), RELEASE_AFTER_MS)
    held.set(element, { button, timer })
    element.dataset.ywSubmitting = ''
    if (button) {
      button.classList.add(BUSY)
      button.setAttribute('aria-disabled', 'true')
    }
  }

  /** Gives the element and its button back. */
  const release = (element) => {
    const holding = held.get(element)
    delete element.dataset.ywSubmitting
    if (!holding) return
    clearTimeout(holding.timer)
    holding.button?.classList.remove(BUSY)
    holding.button?.removeAttribute('aria-disabled')
    held.delete(element)
  }

  document.addEventListener('submit', (event) => {
    const form = event.target
    if (!(form instanceof HTMLFormElement) || resubmittable(form)) return
    if (held.has(form)) {
      event.preventDefault()
      return
    }
    if (event.defaultPrevented) return
    hold(form, event.submitter)
  })

  document.addEventListener('htmx:beforeRequest', (event) => {
    const { elt, requestConfig } = event.detail
    if (String(requestConfig?.verb ?? 'get').toLowerCase() === 'get') return
    if (elt instanceof HTMLFormElement && resubmittable(elt)) return
    if (held.has(elt)) {
      event.preventDefault()
      return
    }
    const submitter = requestConfig?.triggeringEvent?.submitter
    hold(elt, submitter ?? (elt instanceof HTMLButtonElement ? elt : null))
  })

  document.addEventListener('htmx:afterRequest', (event) =>
    release(event.detail.elt),
  )

  window.addEventListener('pageshow', () =>
    [...held.keys()].forEach((element) => release(element)),
  )

  window.ywSubmitGuard = { release }
})()

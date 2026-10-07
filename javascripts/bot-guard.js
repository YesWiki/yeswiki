// BotGuard in the browser: solves ALTCHA and refreshes spent fields.
;(() => {
  if (window.ywBotGuard) return

  const widgetOf = (form) => form?.querySelector('altcha-widget') || null

  const isSolved = (widget) =>
    !widget || typeof widget.verify !== 'function'
      ? true
      : widget.getState?.() === 'verified'

  /** Solves the form's ALTCHA if it is not solved yet. */
  async function verify(form) {
    const widget = widgetOf(form)
    if (!isSolved(widget)) await widget.verify()
  }

  /** Whether the form still has an ALTCHA to solve. */
  function isPending(form) {
    return !isSolved(widgetOf(form))
  }

  /** Replaces the form's spent guard fields with those in `source`. */
  function refresh(form, source) {
    if (!form || !source) return
    let fresh = null
    if (typeof source === 'string') {
      const holder = document.createElement('div')
      holder.innerHTML = source
      fresh = holder.querySelector('.yw-bot-guard-fields')
    } else {
      fresh = source.querySelector?.('.yw-bot-guard-fields') || null
    }
    const spent = form.querySelector('.yw-bot-guard-fields')
    if (fresh && spent) spent.replaceWith(fresh)
  }

  const pending = new WeakMap()
  const lastSubmitter = new WeakMap()

  /** Sends a form held back for its ALTCHA once it is solved. */
  function resume(form) {
    if (!form || !pending.has(form) || !isSolved(widgetOf(form))) return
    const submitter = pending.get(form)
    pending.delete(form)
    form.requestSubmit(submitter || undefined)
  }

  /** Holds the form back and solves its ALTCHA, then sends it. */
  function holdUntilSolved(form, submitter) {
    const widget = widgetOf(form)
    pending.set(form, submitter)
    if (form.dataset.ywBotGuardSolving) return
    form.dataset.ywBotGuardSolving = '1'
    const solving =
      widget.getState?.() === 'verifying' ? Promise.resolve() : widget.verify()
    solving
      .catch(() => {})
      .finally(() => {
        delete form.dataset.ywBotGuardSolving
        resume(form)
      })
  }

  const nativeRequestSubmit = HTMLFormElement.prototype.requestSubmit
  HTMLFormElement.prototype.requestSubmit = function requestSubmit(submitter) {
    lastSubmitter.set(this, submitter)
    return nativeRequestSubmit.call(this, submitter)
  }

  document.addEventListener(
    'click',
    (event) => {
      const button = event.target.closest?.(
        'button[type="submit"], button:not([type]), input[type="submit"]',
      )
      if (button?.form) lastSubmitter.set(button.form, button)
    },
    true,
  )

  document.addEventListener(
    'invalid',
    (event) => {
      const widget = event.target.closest?.('altcha-widget')
      const form = widget?.closest('form')
      if (!form || isSolved(widget)) return
      event.preventDefault()
      holdUntilSolved(form, lastSubmitter.get(form))
    },
    true,
  )

  document.addEventListener(
    'submit',
    (event) => {
      const form = event.target
      if (!(form instanceof HTMLFormElement)) return
      if (isSolved(widgetOf(form))) return
      event.preventDefault()
      event.stopImmediatePropagation()
      holdUntilSolved(form, event.submitter)
    },
    true,
  )

  document.addEventListener(
    'statechange',
    (event) => {
      const widget = event.target
      if (
        !(widget instanceof HTMLElement) ||
        widget.tagName !== 'ALTCHA-WIDGET'
      )
        return
      const fields = widget.closest('.yw-bot-guard-fields')
      if (!fields) return
      const verified = event.detail?.state === 'verified'
      widget.hidden = verified
      const active = fields.querySelector('.yw-bot-guard-active')
      if (active) active.hidden = !verified
      if (verified) resume(widget.closest('form'))
    },
    true,
  )

  window.ywBotGuard = { verify, refresh, isPending }
})()

// yw-core.js — core's vanilla-JS behavior for the yw-* design system (ADR-0004/0005).
// No jQuery, no Bootstrap JS. Delegated listeners so it works for content swapped in
// later by htmx, not just what's in the DOM at load time.
(function() {
  function closeModal(modal) {
    if (modal) {
      modal.classList.remove('yw-modal--open')
    }
  }

  function closeDropdowns(except) {
    document.querySelectorAll('.yw-dropdown--open').forEach((dropdown) => {
      if (dropdown !== except) dropdown.classList.remove('yw-dropdown--open')
    })
  }

  // Open: click on any [data-yw-modal-target="#id"]
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-yw-modal-target]')
    if (opener) {
      const modal = document.querySelector(opener.getAttribute('data-yw-modal-target'))
      if (modal) {
        modal.classList.add('yw-modal--open')
      }
      return
    }

    // Dismiss: click on [data-yw-dismiss="modal"] (hide) or "alert" (remove)
    const dismisser = e.target.closest('[data-yw-dismiss]')
    if (dismisser) {
      const kind = dismisser.getAttribute('data-yw-dismiss')
      if (kind === 'modal') {
        closeModal(dismisser.closest('.yw-modal'))
      } else if (kind === 'alert') {
        const alertEl = dismisser.closest('.yw-alert')
        if (alertEl) {
          alertEl.remove()
        }
      }
      return
    }

    // Toggle: click on [data-yw-dropdown-toggle]
    const toggle = e.target.closest('[data-yw-dropdown-toggle]')
    if (toggle) {
      const dropdown = toggle.closest('.yw-dropdown')
      if (dropdown) {
        const willOpen = !dropdown.classList.contains('yw-dropdown--open')
        closeDropdowns()
        dropdown.classList.toggle('yw-dropdown--open', willOpen)
      }
      return
    }

    // Picking an item inside an open dropdown's menu closes it (its own click
    // action, e.g. a link's href or an onclick handler, still runs normally)
    if (e.target.closest('.yw-dropdown__menu')) {
      closeDropdowns()
      return
    }

    // Click on the backdrop itself (not its dialog) closes the modal
    if (e.target.classList.contains('yw-modal') && e.target.classList.contains('yw-modal--open')) {
      closeModal(e.target)
      return
    }

    // Click anywhere else closes any open dropdown
    closeDropdowns()
  })

  // Escape closes the top-most open modal, or any open dropdown
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const open = document.querySelectorAll('.yw-modal--open')
      if (open.length) {
        closeModal(open[open.length - 1])
      }
      closeDropdowns()
    }
  })
}())

// yw-core.js — core's vanilla-JS behavior for the yw-* design system (ADR-0004/0005).
// No jQuery, no Bootstrap JS. Delegated listeners so it works for content swapped in
// later by htmx, not just what's in the DOM at load time.
(function() {
  function closeModal(modal) {
    if (modal) {
      modal.classList.remove('yw-modal--open')
    }
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

    // Click on the backdrop itself (not its dialog) closes the modal
    if (e.target.classList.contains('yw-modal') && e.target.classList.contains('yw-modal--open')) {
      closeModal(e.target)
    }
  })

  // Escape closes the top-most open modal
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const open = document.querySelectorAll('.yw-modal--open')
      if (open.length) {
        closeModal(open[open.length - 1])
      }
    }
  })
}())

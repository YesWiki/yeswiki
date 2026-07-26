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

  // ---- Remote-load modal (ticket 16: replaces jQuery `.load()` + `.modal('show')`) ----
  // Any element with [data-yw-modal-remote="<url>"] opens a single shared modal shell,
  // fetches the URL, and injects its content. Optional attributes on the opener:
  //   title="..."                     modal header title
  //   data-yw-modal-size="lg"         appends a `yw-modal__dialog--lg`-style modifier
  //   data-yw-modal-no-header         suppresses the header entirely
  //   data-yw-modal-iframe            loads the url in an <iframe> instead of fetching it
  //   data-yw-modal-fragment=".page"  extract only this selector's innerHTML from the
  //                                   fetched response (default: the whole response body)
  function remoteModalShell() {
    let modal = document.getElementById('yw-modal-remote')
    if (!modal) {
      modal = document.createElement('div')
      modal.className = 'yw-modal'
      modal.id = 'yw-modal-remote'
      modal.innerHTML = `
        <div class="yw-modal__dialog">
          <div class="yw-modal__content">
            <div class="yw-modal__header">
              <h3 class="yw-modal__title"></h3>
              <button type="button" class="yw-close" data-yw-dismiss="modal"
                aria-label="close">&times;</button>
            </div>
            <div class="yw-modal__body"></div>
          </div>
        </div>`
      document.body.appendChild(modal)
    }
    return modal
  }

  // Loads any <script src> / <link rel=stylesheet> from a fetched fragment that isn't
  // already present in the main document, so modal content can bring its own assets along.
  function loadMissingAssets(doc) {
    doc.querySelectorAll('script[src]').forEach((script) => {
      const src = script.getAttribute('src')
      if (src && !document.querySelector(`script[src="${src}"]`)) {
        const newScript = document.createElement('script')
        newScript.src = src
        if (script.type === 'module') newScript.type = 'module'
        document.body.appendChild(newScript)
      }
    })
    doc.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
      const href = link.getAttribute('href')
      if (href && !document.querySelector(`link[href="${href}"]`)) {
        const newLink = document.createElement('link')
        newLink.rel = 'stylesheet'
        newLink.href = href
        document.head.appendChild(newLink)
      }
    })
  }

  function openRemoteModal(opener) {
    const url = opener.getAttribute('data-yw-modal-remote')
    if (!url) return

    const modal = remoteModalShell()
    const dialog = modal.querySelector('.yw-modal__dialog')
    const body = modal.querySelector('.yw-modal__body')
    const title = modal.querySelector('.yw-modal__title')
    const header = modal.querySelector('.yw-modal__header')

    dialog.className = 'yw-modal__dialog'
    const size = opener.getAttribute('data-yw-modal-size')
    if (size) dialog.classList.add(`yw-modal__dialog--${size}`)
    header.hidden = opener.hasAttribute('data-yw-modal-no-header')
    title.textContent = opener.getAttribute('title') || ''

    if (/\.(gif|jpe?g|png|svg|webp)$/i.test(url)) {
      body.replaceChildren()
      const img = document.createElement('img')
      img.loading = 'lazy'
      img.src = url
      img.alt = ''
      body.appendChild(img)
    } else if (opener.hasAttribute('data-yw-modal-iframe')) {
      body.innerHTML = `<span class="yw-modal__loading"></span>
        <iframe class="yw-modal__iframe" src="${url}" referrerpolicy="no-referrer"></iframe>`
    } else {
      body.innerHTML = '<span class="yw-modal__loading"></span>'
      const fragmentSelector = opener.getAttribute('data-yw-modal-fragment')
      fetch(url)
        .then((response) => {
          if (!response.ok) throw new Error(`HTTP ${response.status}`)
          return response.text()
        })
        .then((html) => {
          const doc = new DOMParser().parseFromString(html, 'text/html')
          loadMissingAssets(doc)
          const target = fragmentSelector ? doc.querySelector(fragmentSelector) : doc.body
          body.innerHTML = target ? target.innerHTML : ''
          document.dispatchEvent(new CustomEvent('yw-modal-open'))
        })
        .catch(() => {
          body.innerHTML = '<div class="yw-alert yw-alert--danger" role="alert"></div>'
        })
    }

    modal.classList.add('yw-modal--open')
  }

  // Open: click on any [data-yw-modal-target="#id"], or [data-yw-modal-remote="<url>"]
  document.addEventListener('click', (e) => {
    const remoteOpener = e.target.closest('[data-yw-modal-remote]')
    if (remoteOpener) {
      e.preventDefault()
      openRemoteModal(remoteOpener)
      return
    }

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

    // Toggle: click on [data-yw-collapse-toggle="#id"] (accordion panels etc.)
    // Optional [data-yw-accordion="#id"] closes every other panel within that
    // container first, mirroring Bootstrap's data-parent exclusive-open behavior.
    const collapseToggle = e.target.closest('[data-yw-collapse-toggle]')
    if (collapseToggle) {
      const target = document.querySelector(collapseToggle.getAttribute('data-yw-collapse-toggle'))
      if (target) {
        const willOpen = !target.classList.contains('yw-collapse--open')
        const accordionSelector = collapseToggle.getAttribute('data-yw-accordion')
        if (accordionSelector) {
          const accordion = document.querySelector(accordionSelector)
          if (accordion) {
            accordion.querySelectorAll('.yw-collapse--open').forEach((el) => {
              if (el !== target) el.classList.remove('yw-collapse--open')
            })
            const openToggleSelector = '[data-yw-collapse-toggle][aria-expanded="true"]'
            accordion.querySelectorAll(openToggleSelector).forEach((btn) => {
              if (btn !== collapseToggle) btn.setAttribute('aria-expanded', 'false')
            })
          }
        }
        target.classList.toggle('yw-collapse--open', willOpen)
        collapseToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false')
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

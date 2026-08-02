// yw-core.js — core's vanilla-JS behavior for the yw-* design system (ADR-0004/0005).
// No jQuery, no Bootstrap JS. Delegated listeners so it works for content swapped in
// later by htmx, not just what's in the DOM at load time.
(function() {
  function closeModal(modal) {
    if (modal && modal.classList.contains('yw-modal--open')) {
      modal.classList.remove('yw-modal--open')
      // replaces Bootstrap's hidden.bs.modal for close-time cleanup hooks
      modal.dispatchEvent(new CustomEvent('yw-modal-hidden', { bubbles: true }))
    }
  }

  function closeDropdowns(except) {
    document.querySelectorAll('.yw-dropdown--open').forEach((dropdown) => {
      if (dropdown !== except) dropdown.classList.remove('yw-dropdown--open')
    })
    // legacy Bootstrap markup: parent of a .dropdown-menu gets .open
    document.querySelectorAll('.open > .dropdown-menu').forEach((menu) => {
      if (menu.parentElement !== except) menu.parentElement.classList.remove('open')
    })
  }

  // ---- Remote-load modal (ticket 16: replaces jQuery `.load()` + `.modal('show')`) ----
  // Matches the EXACT pre-existing markup contract every "open in a modal" link across
  // the codebase already uses (`class="modalbox"`/`href`/`data-size`/`data-iframe`/
  // `data-header`/`title`) rather than inventing a new attribute scheme -- this lets every
  // existing modalbox link keep working unconverted. Optional attributes on the opener:
  //   href="<url>"           required: what to load into the modal
  //   title="..."            modal header title
  //   data-size="modal-lg"   appends a `yw-modal__dialog--lg`-style modifier (a legacy
  //                          "modal-" prefix, if present, is stripped before appending)
  //   data-header="false"    suppresses the header entirely
  //   data-iframe="1"        loads the url in an <iframe> instead of fetching it
  //   data-yw-modal-fragment=".foo"  extract only this selector's innerHTML from the
  //                          fetched response (default: ".page", matching the legacy
  //                          behavior of always extracting the fetched page's own content)
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

  // A url already routed through an edit/iframe handler is left alone; a same-domain url
  // gets `/iframe` appended so it renders without this wiki's own surrounding chrome
  function addIframeHandlerTo(url) {
    if (/\??.*\/(edit)?iframe(&.*)?/.test(url)) return url
    if (new RegExp(`^${wiki.baseUrl}`).test(url)) return `${url}/iframe`
    return url
  }

  function openRemoteModal(opener) {
    const url = opener.getAttribute('href')
    if (!url) return

    const modal = remoteModalShell()
    const dialog = modal.querySelector('.yw-modal__dialog')
    const body = modal.querySelector('.yw-modal__body')
    const title = modal.querySelector('.yw-modal__title')
    const header = modal.querySelector('.yw-modal__header')

    dialog.className = 'yw-modal__dialog'
    const size = (opener.getAttribute('data-size') || '').replace(/^modal-/, '')
    if (size) dialog.classList.add(`yw-modal__dialog--${size}`)
    header.hidden = opener.getAttribute('data-header') === 'false'
    title.textContent = opener.getAttribute('title') || ''

    if (/\.(gif|jpe?g|png|svg|webp|tiff)$/i.test(url)) {
      body.replaceChildren()
      const img = document.createElement('img')
      img.loading = 'lazy'
      img.src = url
      img.alt = ''
      body.appendChild(img)
    } else if (opener.getAttribute('data-iframe') === '1') {
      const iframeUrl = addIframeHandlerTo(url)
      if (!title.textContent) title.textContent = url.substring(0, 128)
      body.innerHTML = `<span class="yw-modal__loading"></span>
        <iframe class="yw-modal__iframe" src="${iframeUrl}" referrerpolicy="no-referrer"></iframe>`
      const loading = body.querySelector('.yw-modal__loading')
      body.querySelector('.yw-modal__iframe').addEventListener('load', () => {
        if (loading) loading.remove()
      })
    } else {
      body.innerHTML = '<span class="yw-modal__loading"></span>'
      const fragmentSelector = opener.getAttribute('data-yw-modal-fragment') || '.page'
      fetch(url)
        .then((response) => {
          if (!response.ok) throw new Error(`HTTP ${response.status}`)
          return response.text()
        })
        .then((html) => {
          const doc = new DOMParser().parseFromString(html, 'text/html')
          loadMissingAssets(doc)
          const target = doc.querySelector(fragmentSelector) || doc.body
          body.innerHTML = target ? target.innerHTML : ''
          document.dispatchEvent(new CustomEvent('yw-modal-open'))
        })
        .catch(() => {
          body.innerHTML = '<div class="yw-alert yw-alert--danger" role="alert"></div>'
        })
    }

    modal.classList.add('yw-modal--open')
  }

  // Resolves the element a toggle points at, reading whichever attribute the
  // markup uses: the yw-* one, legacy Bootstrap's data-target, or a "#id" href.
  function toggleTarget(toggle, ywAttribute) {
    const selector = toggle.getAttribute(ywAttribute)
      || toggle.getAttribute('data-target')
      || (/^#./.test(toggle.getAttribute('href') || '') ? toggle.getAttribute('href') : null)
    return selector ? document.querySelector(selector) : null
  }

  // ---- Tabs (ticket 16: replaces Bootstrap's tab plugin + jQuery historyTabs) ----
  // Markup contract (legacy attributes honored, no template churn):
  //   <ul class="yw-tabs"><li class="active"><a href="#pane1" data-toggle="tab">...</a></li>...</ul>
  //   <div class="yw-tabs__content"><div class="yw-tabs__pane active" id="pane1">...</div>...</div>
  // A trigger can also live inside the pane container itself (next/previous
  // buttons) — the nav is then found as the container's preceding tab list.
  function tabNavFor(link, pane) {
    const ownNav = link.closest('ul')
    if (ownNav && ownNav.querySelector('a[data-toggle="tab"], a[data-yw-tab]')) return ownNav
    let el = pane.parentElement
    while (el && el.previousElementSibling) {
      el = el.previousElementSibling
      if (el.matches('ul') && el.querySelector('a[data-toggle="tab"], a[data-yw-tab]')) return el
    }
    return null
  }

  function activateTab(link) {
    const href = link.getAttribute('href')
    if (!href || !/^#./.test(href)) return
    const pane = document.querySelector(href)
    if (!pane) return

    Array.from(pane.parentElement.children).forEach((sibling) => {
      sibling.classList.toggle('active', sibling === pane)
    })
    const nav = tabNavFor(link, pane)
    if (nav) {
      nav.querySelectorAll('li').forEach((item) => {
        const itemLink = item.querySelector('a[href]')
        item.classList.toggle('active', !!itemLink && itemLink.getAttribute('href') === href)
      })
    }
    // A trigger inside the pane container (next/previous button) scrolls back up
    // to the tab strip, matching the legacy behavior for long tabbed forms
    if (nav && !nav.contains(link)) {
      nav.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }

  // Remember the visited tab in the browser history (legacy historyTabs behavior)
  function recordTabInHistory(href) {
    const state = { url: href }
    const url = window.location.pathname + window.location.search + href
    if (window.location.hash && href !== window.location.hash) {
      window.history.pushState(state, document.title, url)
    } else {
      window.history.replaceState(state, document.title, url)
    }
  }

  function tabLinkFor(href) {
    return document.querySelector(
      `a[data-toggle="tab"][href="${href}"], a[data-yw-tab][href="${href}"]`
    )
  }

  window.addEventListener('popstate', (event) => {
    if (event.state && event.state.url) {
      const link = tabLinkFor(event.state.url)
      if (link) activateTab(link)
    }
  })

  // ticket 14: one initialiser convention (the hash is read once, when the page arrives)
  ywInitEach('body', () => {
    if (window.location.hash) {
      const link = tabLinkFor(window.location.hash)
      if (link) activateTab(link)
    }
  })

  // A field asking for focus on arrival -- the /search page's search box, today.
  //
  // The `autofocus` attribute alone is not enough and the reason is ticket 16: an internal
  // link is htmx-boosted, so reaching /search from the top bar SWAPS the body rather than
  // parsing a new document, and `autofocus` only fires on parse. The button that exists to
  // reach the search page is exactly the path where the attribute does nothing.
  //
  // Marked with an attribute rather than by selecting the search box directly, so that
  // "focus me on arrival" stays a property a template can ask for rather than a list of
  // selectors kept in step here. ywInitEach marks each element once, so a later swap
  // elsewhere on the page does not yank focus back out of whatever the visitor moved to.
  ywInitEach('[data-yw-autofocus]', (field) => {
    // never steal focus from something the visitor is already using: an embedded {{search}}
    // lower down a page can arrive while they are typing somewhere else
    const active = document.activeElement
    if (active && active !== document.body && active !== field) return
    field.focus({ preventScroll: true })
    // caret after any prefilled phrase rather than selecting it, so typing extends the
    // query someone arrived with instead of wiping it
    if (typeof field.setSelectionRange === 'function' && field.value) {
      field.setSelectionRange(field.value.length, field.value.length)
    }
  })

  // Open: click on any [data-yw-modal-target="#id"] / legacy [data-toggle="modal"],
  // or a remote-loading "a.modalbox"-style link
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-yw-modal-target], [data-toggle="modal"]')
    if (opener) {
      const modal = toggleTarget(opener, 'data-yw-modal-target')
      // only claim converted .yw-modal targets — a legacy Bootstrap-markup modal
      // (not yet converted) is still Bootstrap JS's to open, not ours
      if (modal && modal.classList.contains('yw-modal')) {
        e.preventDefault()
        modal.classList.add('yw-modal--open')
        // Replaces Bootstrap's shown.bs.modal + event.relatedTarget contract
        modal.dispatchEvent(
          new CustomEvent('yw-modal-shown', { bubbles: true, detail: { relatedTarget: opener } })
        )
      }
      return
    }

    const remoteOpener = e.target.closest('a.modalbox, a.modal, .modalbox a')
    if (remoteOpener) {
      e.stopPropagation()
      e.preventDefault()
      openRemoteModal(remoteOpener)
      return
    }

    // Dismiss: click on [data-yw-dismiss="modal"] (hide) or "alert" (remove);
    // legacy [data-dismiss] markup means the same thing
    const dismisser = e.target.closest('[data-yw-dismiss], [data-dismiss]')
    if (dismisser) {
      const kind = dismisser.getAttribute('data-yw-dismiss') || dismisser.getAttribute('data-dismiss')
      if (kind === 'modal') {
        closeModal(dismisser.closest('.yw-modal'))
      } else if (kind === 'alert') {
        const alertEl = dismisser.closest('.yw-alert, .alert')
        if (alertEl) {
          alertEl.remove()
        }
      }
      return
    }

    // Toggle: click on [data-yw-dropdown-toggle] or legacy [data-toggle="dropdown"]
    const toggle = e.target.closest('[data-yw-dropdown-toggle], [data-toggle="dropdown"]')
    if (toggle) {
      const dropdown = toggle.closest('.yw-dropdown')
      if (dropdown) {
        e.preventDefault()
        const willOpen = !dropdown.classList.contains('yw-dropdown--open')
        closeDropdowns()
        dropdown.classList.toggle('yw-dropdown--open', willOpen)
      } else if (toggle.parentElement && toggle.parentElement.querySelector('.dropdown-menu')) {
        // legacy Bootstrap markup: toggle .open on the .dropdown-menu's parent
        e.preventDefault()
        const parent = toggle.parentElement
        const willOpen = !parent.classList.contains('open')
        closeDropdowns()
        parent.classList.toggle('open', willOpen)
      }
      return
    }

    // Switch tab: click on [data-yw-tab] or legacy [data-toggle="tab"]
    const tabLink = e.target.closest('a[data-yw-tab], a[data-toggle="tab"]')
    if (tabLink) {
      e.preventDefault()
      activateTab(tabLink)
      recordTabInHistory(tabLink.getAttribute('href'))
      return
    }

    // Toggle: click on [data-yw-collapse-toggle="#id"] (accordion panels etc.)
    // Optional [data-yw-accordion="#id"] closes every other panel within that
    // container first, mirroring Bootstrap's data-parent exclusive-open behavior.
    // Legacy [data-toggle="collapse"] markup (data-target/href + data-parent) is
    // honored too, driving the same .yw-collapse--open class.
    const collapseToggle = e.target.closest('[data-yw-collapse-toggle], [data-toggle="collapse"]')
    if (collapseToggle) {
      const target = toggleTarget(collapseToggle, 'data-yw-collapse-toggle')
      // legacy Bootstrap .collapse markup toggles its .in class instead
      if (target && !target.classList.contains('yw-collapse')
        && target.classList.contains('collapse')) {
        e.preventDefault()
        const willOpen = !target.classList.contains('in')
        const accordionSelector = collapseToggle.getAttribute('data-yw-accordion')
          || collapseToggle.getAttribute('data-parent')
        if (accordionSelector) {
          const accordion = document.querySelector(accordionSelector)
          if (accordion) {
            accordion.querySelectorAll('.collapse.in').forEach((el) => {
              if (el !== target) el.classList.remove('in')
            })
            accordion.querySelectorAll('[data-toggle="collapse"][aria-expanded="true"]')
              .forEach((btn) => {
                if (btn !== collapseToggle) {
                  btn.setAttribute('aria-expanded', 'false')
                  btn.classList.add('collapsed')
                }
              })
          }
        }
        target.classList.toggle('in', willOpen)
        collapseToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false')
        collapseToggle.classList.toggle('collapsed', !willOpen)
        return
      }
      if (target && target.classList.contains('yw-collapse')) {
        e.preventDefault()
        const willOpen = !target.classList.contains('yw-collapse--open')
        const accordionSelector = collapseToggle.getAttribute('data-yw-accordion')
          || collapseToggle.getAttribute('data-parent')
        if (accordionSelector) {
          const accordion = document.querySelector(accordionSelector)
          if (accordion) {
            accordion.querySelectorAll('.yw-collapse--open').forEach((el) => {
              if (el !== target) el.classList.remove('yw-collapse--open')
            })
            const openToggleSelector = '[data-yw-collapse-toggle][aria-expanded="true"],'
              + ' [data-toggle="collapse"][aria-expanded="true"]'
            accordion.querySelectorAll(openToggleSelector).forEach((btn) => {
              if (btn !== collapseToggle) {
                btn.setAttribute('aria-expanded', 'false')
                btn.classList.add('collapsed')
              }
            })
          }
        }
        target.classList.toggle('yw-collapse--open', willOpen)
        collapseToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false')
        // Legacy Bootstrap markup styles the trigger via a .collapsed class
        collapseToggle.classList.toggle('collapsed', !willOpen)
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

  // ---------------------------------------------------------------- viewport width

  // The width a full-width block may actually use, which is NOT 100vw: `vw` includes the
  // vertical scrollbar's gutter, so a 100vw block on a scrolling page overhangs the visible
  // area by the scrollbar's width and gives the document a horizontal scrollbar of its own.
  // documentElement.clientWidth is the same measurement with the gutter taken off. See
  // .full-width in yw-core.css, which falls back to 100vw when this never runs.
  function publishViewportWidth() {
    document.documentElement.style.setProperty(
      '--yw-viewport-width',
      document.documentElement.clientWidth + 'px'
    )
  }

  publishViewportWidth()
  window.addEventListener('resize', publishViewportWidth)
  // a swap can add or remove enough content to gain or lose the scrollbar, which changes the
  // answer without the window ever being resized
  document.addEventListener('htmx:afterSettle', publishViewportWidth)

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

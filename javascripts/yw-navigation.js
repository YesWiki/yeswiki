;(function () {
  if (window.htmx && window.htmx.config) {
    window.htmx.config.historyCacheSize = 0
  }

  const NON_PAGE = /\/(raw|iframe|editiframe|render)(\/|$|\?)/i
  const FILE_PATH = /\/files\//i

  document.addEventListener('htmx:confirm', (event) => {
    const link = event.detail.elt
    if (!link || link.tagName !== 'A') return
    const href = link.getAttribute('href') || ''
    if (NON_PAGE.test(href) || FILE_PATH.test(href)) {
      event.preventDefault()
      window.location.href = link.href
    }
  })

  function announce(title) {
    const announcer = document.getElementById('yw-navigation-announcer')
    if (!announcer) return
    announcer.textContent = ''
    window.setTimeout(() => {
      announcer.textContent = title
    }, 50)
  }

  function focusNewContent() {
    const main = document.getElementById('yw-main')
    if (!main) return

    const requested = main.querySelector('[data-yw-autofocus]')
    if (requested) {
      requested.focus({ preventScroll: true })
      return
    }

    const target = main.querySelector('h1') || main
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1')
    target.focus({ preventScroll: true })
  }

  function scrollToHashOrTop() {
    const { hash } = window.location
    if (!hash || hash === '#') return
    let anchor = null
    try {
      anchor = document.querySelector(hash)
    } catch {
      return
    }
    if (anchor) anchor.scrollIntoView()
  }

  document.addEventListener('htmx:afterSettle', (event) => {
    if (!event.detail || !event.detail.boosted) return

    scrollToHashOrTop()
    focusNewContent()
    announce(document.title)
  })

  ywInitEach('[data-yw-flash]', (element) => {
    const message = element.getAttribute('data-yw-flash')
    if (!message) return
    if (typeof toastMessage === 'function') {
      toastMessage(message, 3000, 'alert alert-secondary-1')
    }
  })
})()

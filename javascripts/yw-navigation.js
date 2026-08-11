// yw-navigation.js — what a boosted navigation has to do that a real page load did for free
// (ticket 16).
//
// htmx swaps the squelette's body block in place of a page load. Four things the browser
// stopped doing on its own, and one thing it must not start doing:
//
//   1. Keyboard focus. The clicked link is destroyed by the swap, so focus falls back to
//      <body> and a keyboard user's tab order restarts at the top of the document — before
//      the skip link, the nav and the header — on every single navigation.
//   2. Announcement. A screen reader is told nothing at all: the page silently became a
//      different page. Focus alone usually announces, but *usually* varies by screen
//      reader/browser pair, which is why there is also a live region.
//   3. Anchors. htmx suppresses its own scroll-to-top when the href has a hash, but an
//      innerHTML swap does not make the browser jump to the anchor either, so `Page#section`
//      lands wherever you happened to be.
//   4. Flash messages. They used to arrive as `<body onload="toastMessage(…)">`; a swap
//      replaces the body's *contents*, so the attribute neither changed nor re-fired.
//
// And the thing it must not do: boost a link to something that is not a page. The server is
// the authority there (it answers HX-Redirect for anything that did not go through
// renderPage), but round-tripping a 200 MB download only to throw it away is worth avoiding,
// so the obvious cases are skipped here. This list is allowed to be incomplete — correctness
// never depends on it.
;(function () {
  // No page snapshots in sessionStorage.
  //
  // htmx caches the last 10 navigations so the back button can restore instantly. That was
  // the plan (ticket 16, decision 5) and it was wrong for this wiki: a page carrying
  // {{admincontent}} renders ~2.4 MB, ten of those is ~24 MB, and sessionStorage allows about
  // 5 MB per origin -- so the very first navigation away from an admin page throws
  // QuotaExceededError, which htmx reports as htmx:historyCacheError.
  //
  // With the cache off and htmx.config.refreshOnHistoryMiss left at false, going back is an
  // ordinary full page load: one round-trip, always fresh, nothing stored. It also removes
  // two things that were only ever downsides here -- rendered private pages sitting in
  // sessionStorage for the tab's lifetime, and a restored snapshot whose widgets are dead
  // because its initialisers never re-ran.
  if (window.htmx && window.htmx.config) {
    window.htmx.config.historyCacheSize = 0
  }

  // handler suffixes that never render a page, and the upload path.
  // Only the handlers that still exist. qrcodetroc became an action and xml/rss/tagrss became API
  // routes (ticket 35) -- an /api/ url is not a page link, so it never reaches this list.
  const NON_PAGE = /\/(raw|iframe|editiframe|render)(\/|$|\?)/i
  const FILE_PATH = /\/files\//i

  document.addEventListener('htmx:confirm', (event) => {
    const link = event.detail.elt
    if (!link || link.tagName !== 'A') return
    const href = link.getAttribute('href') || ''
    if (NON_PAGE.test(href) || FILE_PATH.test(href)) {
      // let the browser handle it: cancel the htmx request and follow the link normally
      event.preventDefault()
      window.location.href = link.href
    }
  })

  // ---------------------------------------------------------------- after a navigation

  function announce(title) {
    const announcer = document.getElementById('yw-navigation-announcer')
    if (!announcer) return
    // re-setting identical text does not re-announce; clearing first makes repeat
    // navigations to the same title (a refresh, a back) speak
    announcer.textContent = ''
    window.setTimeout(() => {
      announcer.textContent = title
    }, 50)
  }

  function focusNewContent() {
    const main = document.getElementById('yw-main')
    if (!main) return

    // A page that names the element it wants focused wins over the rule below. /search is
    // the case: it exists to be typed into, and announcing its heading instead would leave a
    // keyboard user tabbing past the one control the whole page is for. `autofocus` cannot
    // do this job here -- it only fires when a document is parsed, and a boosted navigation
    // swaps markup into a document that already was.
    const requested = main.querySelector('[data-yw-autofocus]')
    if (requested) {
      requested.focus({ preventScroll: true })
      return
    }

    // the heading, not the container: focusing the container makes some screen readers read
    // the whole region, while the heading announces the page name and stops
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
      return // a hash that is not a valid selector
    }
    if (anchor) anchor.scrollIntoView()
  }

  document.addEventListener('htmx:afterSettle', (event) => {
    // only whole-page navigations; an inline swap (the tags autocomplete, the admin table)
    // must not steal focus or announce anything
    if (!event.detail || !event.detail.boosted) return

    scrollToHashOrTop()
    focusNewContent()
    announce(document.title)
  })

  // ---------------------------------------------------------------- flash messages

  // rendered by {{ page_state() }} inside the body block, so it survives a swap
  ywInitEach('[data-yw-flash]', (element) => {
    const message = element.getAttribute('data-yw-flash')
    if (!message) return
    if (typeof toastMessage === 'function') {
      toastMessage(message, 3000, 'alert alert-secondary-1')
    }
  })
})()

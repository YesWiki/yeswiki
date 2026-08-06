// yw-assets.js — the browser half of ticket 14's declared assets.
//
// A fragment declares everything it needs, every time: the server states needs, and it has
// no idea what this particular page already holds. This file is what turns that into "loaded
// exactly once" — a registry of asset URLs, seeded from the document at startup and consulted
// before every htmx swap, dropping the <link>/<script> tags that are already present.
//
// It is the form designer's own `previewStyles` Set (which deduplicated preview stylesheets
// by href on one admin page) promoted to core, so every fragment gets the same guarantee.
//
// Stripping happens before the browser sees the tags, not after. A duplicate <link> is inert,
// but a duplicate classic <script src> re-executes, and re-parsing leaflet once per map field
// on a designer canvas is exactly what this exists to prevent.
//
// See docs/adr/0014-assets-are-declared-by-a-render-not-accumulated-by-a-request.md
;(function () {
  const loaded = new Set()

  // Normalised so the same file registered as `styles/x.css?v=4.5` and as
  // `/wiki/styles/x.css?v=4.5` counts once: the version query is part of the identity (a new
  // release *is* a different file), the origin is not.
  function key(url) {
    if (!url) return null
    try {
      const parsed = new URL(url, document.baseURI)
      return parsed.pathname + parsed.search
    } catch {
      return url
    }
  }

  function remember(url) {
    const k = key(url)
    if (k) loaded.add(k)
  }

  function has(url) {
    const k = key(url)
    return k !== null && loaded.has(k)
  }

  // Everything the server already put in the document on this page load.
  function seed() {
    document
      .querySelectorAll('link[rel="stylesheet"][href], script[src]')
      .forEach((node) =>
        remember(node.getAttribute('href') || node.getAttribute('src')),
      )
  }

  seed()

  /**
   * Take the fragment's scripts out of htmx's hands and load them ourselves, in order.
   *
   * Leaving them to htmx meant two vendor libraries racing their own dependents inside a
   * single navigation: `vditor-textarea.js` ran before `vendor/vditor/index.min.js`
   * ("Vditor is not defined"), and `vue-select` before Vue ("can't access property
   * createElementVNode"). Both are the same shape -- a script executing before the global it
   * needs -- and no amount of retrying afterwards makes the first attempt not fail.
   *
   * Loading them here makes the order deterministic: one at a time, each awaited, in exactly
   * the order the server declared them. Stylesheets are left in the fragment, because CSS has
   * no execution order to get wrong.
   *
   * This is the "inert declaration plus loader" option that ticket 16's grilling considered
   * and rejected as too much machinery. It was the right option; the machinery is 30 lines and
   * it removes a whole class of failure rather than reporting it.
   */
  const loadInOrder = async (scripts) => {
    for (const entry of scripts) {
      if (entry.code === null) {
        // eslint-disable-next-line no-await-in-loop
        await new Promise((resolve) => {
          const script = document.createElement('script')
          script.src = entry.url
          if (entry.module) script.type = 'module'
          script.addEventListener('load', resolve, { once: true })
          script.addEventListener('error', resolve, { once: true })
          document.head.append(script)
        })
      } else {
        // Inline code runs synchronously, but only once everything declared before it has
        // loaded. This is the half the first version missed: an action that declares a
        // library *and* the inline code driving it -- BazarListeAction building a leaflet map
        // is the case -- had its inline code execute immediately while leaflet was still
        // downloading, so the map was never built and nothing threw.
        const script = document.createElement('script')
        if (entry.module) script.type = 'module'
        script.textContent = entry.code
        document.head.append(script)
      }
    }
    document.dispatchEvent(new CustomEvent('yw:assets-ready'))
  }

  document.addEventListener('htmx:oobBeforeSwap', (event) => {
    const fragment = event.detail && event.detail.fragment
    if (!fragment || !fragment.querySelectorAll) return

    let dropped = 0
    const scripts = []

    // document order matters: an inline block usually drives the library declared just above it
    fragment
      .querySelectorAll('link[rel="stylesheet"][href], script')
      .forEach((node) => {
        const isScript = node.tagName === 'SCRIPT'
        const url = node.getAttribute('href') || node.getAttribute('src')

        // an inline block has no URL to deduplicate on, and is always this page's own code
        if (isScript && !url) {
          scripts.push({
            url: null,
            code: node.textContent,
            module: node.getAttribute('type') === 'module',
          })
          node.remove()

          return
        }

        if (has(url)) {
          node.remove()
          dropped += 1

          return
        }
        remember(url)

        if (isScript) {
          // out of the fragment, into the queue below
          scripts.push({
            url,
            code: null,
            module: node.getAttribute('type') === 'module',
          })
          node.remove()
        }
      })

    if (scripts.length > 0) loadInOrder(scripts)

    if (dropped > 0 && typeof wiki !== 'undefined' && wiki.isDebugEnabled) {
      console.debug(`yw-assets: dropped ${dropped} already-loaded asset tag(s)`)
    }
  })

  // Anything the page gained by other means (an inline swap that carried its own tags) is
  // recorded too, so a later fragment does not re-request it.
  document.addEventListener('htmx:afterSettle', () => seed())

  // Deliberately global: an initialiser that loads an asset by hand (a lazy vendor library,
  // say) can declare it here so a later fragment carrying the same file is not re-fetched.
  window.ywAssets = { has, remember }
})()

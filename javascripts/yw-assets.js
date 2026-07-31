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
// Stripping happens at htmx:beforeSwap, i.e. before the browser sees the tags — not after.
// A duplicate <link> is inert, but a duplicate classic <script src> re-executes, and re-parsing
// leaflet once per map field on a designer canvas is exactly what this exists to prevent.
//
// See docs/adr/0014-assets-are-declared-by-a-render-not-accumulated-by-a-request.md
(function() {
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
      .forEach((node) => remember(node.getAttribute('href') || node.getAttribute('src')))
  }

  // Drop tags for assets we already have, and record the ones we are about to gain.
  // Returns the number of tags removed, which is only used for debug logging.
  function dedupe(root) {
    let dropped = 0
    root.querySelectorAll('link[rel="stylesheet"][href], script[src]').forEach((node) => {
      const url = node.getAttribute('href') || node.getAttribute('src')
      if (has(url)) {
        node.remove()
        dropped += 1
        return
      }
      remember(url)
    })

    return dropped
  }

  seed()

  document.addEventListener('htmx:beforeSwap', (event) => {
    const xhr = event.detail && event.detail.xhr
    if (!xhr || typeof xhr.responseText !== 'string' || xhr.responseText === '') return
    if (!/<(link|script)\b/i.test(xhr.responseText)) return

    // Parse, strip, re-serialise. htmx swaps from detail.serverResponse, so rewriting that
    // string is what actually keeps the duplicate tags out of the document.
    const holder = document.createElement('template')
    holder.innerHTML = event.detail.serverResponse
    const dropped = dedupe(holder.content)
    if (dropped > 0) {
      event.detail.serverResponse = holder.innerHTML // eslint-disable-line no-param-reassign
      if (typeof wiki !== 'undefined' && wiki.isDebugEnabled) {
        console.debug(`yw-assets: dropped ${dropped} already-loaded asset tag(s)`)
      }
    }
  })

  // Deliberately global: an initialiser that loads an asset by hand (a lazy vendor library,
  // say) can declare it here so a later fragment carrying the same file is not re-fetched.
  window.ywAssets = { has, remember }
}())

;(function () {
  const loaded = new Set()

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
        await new Promise((resolve) => {
          const script = document.createElement('script')
          script.src = entry.url
          if (entry.module) script.type = 'module'
          script.addEventListener('load', resolve, { once: true })
          script.addEventListener('error', resolve, { once: true })
          document.head.append(script)
        })
      } else {
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

    fragment
      .querySelectorAll('link[rel="stylesheet"][href], script')
      .forEach((node) => {
        const isScript = node.tagName === 'SCRIPT'
        const url = node.getAttribute('href') || node.getAttribute('src')

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

  document.addEventListener('htmx:afterSettle', () => seed())

  window.ywAssets = { has, remember }
})()

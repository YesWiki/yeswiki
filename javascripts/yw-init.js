// contents change: adding a new global to an existing file means every browser holding that
function ywInit(init) {
  /** `settled` says whether the page's declared assets have finished loading. */
  const run = (root, settled) => {
    try {
      init(root || document)
    } catch (error) {
      if (settled) {
        console.error('yw-init: initialiser failed', error)
      } else if (typeof wiki !== 'undefined' && wiki.isDebugEnabled) {
        console.debug('yw-init: initialiser deferred, waiting on assets', error)
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => run(document, true))
  } else {
    run(document, true)
  }

  document.addEventListener('htmx:load', (event) => run(event.target, false))

  document.addEventListener('yw:assets-ready', () => run(document, true))
}

// This is the file's public surface: a global other scripts call, declared in
// eslint-disable-next-line no-unused-vars
function ywInitEach(selector, init) {
  const initialised = new WeakSet()

  const setUp = (element) => {
    if (initialised.has(element)) return
    init(element)
    initialised.add(element)
  }

  ywInit((root) => {
    const scope = root && root.querySelectorAll ? root : document
    if (scope.matches && scope.matches(selector)) setUp(scope)
    scope.querySelectorAll(selector).forEach(setUp)
  })
}

// yw-init.js -- the one initialisation convention (ticket 14).
//
// Deliberately its own file rather than a few more lines in yeswiki-base-no-defer.js. Asset
// URLs are cache-busted with `?v=<yeswiki_release>`, which does not change when a file's
// contents change: adding a new global to an existing file means every browser holding that
// file keeps serving the version without it, and every one of the ~25 initialisers that now
// depend on it dies with a ReferenceError. A new file has a URL nobody can have cached.
//
// Loaded first and *not* deferred, so it is defined before any deferred, module or
// fragment-loaded caller runs.
//
// One convention for "run this over content that appears", replacing three that
// coexisted — DOMContentLoaded, a MutationObserver with a readiness attribute, and an
// htmx:afterSwap re-init.
//
// The immediate sweep is not redundant with the htmx:load listener, and this is the subtle
// part: htmx fires htmx:load on swapped content as soon as it settles, while the <script src>
// that fragment carried is still loading. A script that a fragment just pulled in therefore
// *misses* the htmx:load for the very content that pulled it in. Sweeping once on load and
// listening for the rest is what covers both — at the cost of running twice on a normal page
// load, which is why every initialiser passed here has to be idempotent.
//
// Lives in the one non-deferred script so it is defined before any deferred or
// fragment-loaded caller runs, and listens on document rather than calling htmx.onLoad so it
// does not depend on htmx having loaded yet (htmx events bubble).
function ywInit(init) {
  const run = (root) => {
    try {
      init(root || document)
    } catch (error) {
      console.error('yw-init: initialiser failed', error)
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => run(document))
  } else {
    run(document)
  }

  document.addEventListener('htmx:load', (event) => run(event.target))
}

// The common shape: "for every element matching `selector`, set it up once". The marker
// attribute is what makes the double call above harmless, so callers get idempotency by
// construction instead of each inventing its own guard.
function ywInitEach(selector, init) {
  ywInit((root) => {
    const scope = root && root.querySelectorAll ? root : document
    // an inserted element can itself be the match, and querySelectorAll never returns it
    if (scope.matches && scope.matches(selector) && !scope.hasAttribute('data-yw-ready')) {
      scope.setAttribute('data-yw-ready', '')
      init(scope)
    }
    scope.querySelectorAll(`${selector}:not([data-yw-ready])`).forEach((element) => {
      element.setAttribute('data-yw-ready', '')
      init(element)
    })
  })
}

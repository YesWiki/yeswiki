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
  /**
   * `settled` says whether the page's declared assets have finished loading.
   *
   * A sweep triggered by htmx:load runs the moment the new content is in the DOM, which can be
   * before the scripts that content declared have loaded. An initialiser failing *then* is
   * expected, not an error: the element is left unmarked (see ywInitEach) and yw:assets-ready
   * sweeps again once the assets are in. Only a failure after that is worth reporting -- and
   * reporting the transient one as an error is how a recovered race ends up looking like a
   * broken page in the console.
   */
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
    // a fragment-loaded script is loaded *after* its declared dependencies (yw-assets.js
    // sequences them), so by the time this runs the assets it needs are in
    run(document, true)
  }

  document.addEventListener('htmx:load', (event) => run(event.target, false))

  // Fired by yw-assets.js when a script a fragment carried has finished loading. This is what
  // makes the ordering above recoverable rather than merely reported: `vditor-textarea.js`
  // sweeping before `vditor/index.min.js` has executed is a race nobody can win by ordering
  // alone, so the answer is to try again when the dependency lands.
  document.addEventListener('yw:assets-ready', () => run(document, true))
}

// The common shape: "for every element matching `selector`, set it up once".
//
// "Once" is tracked per initialiser, in a WeakSet this call closes over -- NOT with a shared
// marker attribute on the element. That distinction is the whole correctness of this
// function: fourteen files legitimately initialise <body> (page-level setup that used to hang
// off DOMContentLoaded), and with a shared attribute the first one to run marked <body> and
// silently disabled all thirteen others -- including the editor and the dynamic bazar
// templates. A WeakSet also needs no cleanup: an element removed from the document becomes
// unreachable and takes its entry with it.
//
// Elements arriving in a swap are new objects, so they are initialised; <body> survives a
// boosted navigation, so body-level setup correctly runs once per document rather than once
// per navigation.
// This is the file's public surface: a global other scripts call, declared in
// eslint.config.mjs. ESLint sees each file alone, so the definition reads as unused.
// eslint-disable-next-line no-unused-vars
function ywInitEach(selector, init) {
  const initialised = new WeakSet()

  // Marked only *after* init succeeds. Marking first looks equivalent and is not: an
  // initialiser that throws because its vendor library has not loaded yet would be recorded as
  // done and never retried, which is exactly how a page ended up with a permanently dead
  // editor after one failed attempt.
  const setUp = (element) => {
    if (initialised.has(element)) return
    init(element)
    initialised.add(element)
  }

  ywInit((root) => {
    const scope = root && root.querySelectorAll ? root : document
    // an inserted element can itself be the match, and querySelectorAll never returns it
    if (scope.matches && scope.matches(selector)) setUp(scope)
    scope.querySelectorAll(selector).forEach(setUp)
  })
}

// javascripts/unsaved-changes.js -- "you have unsaved changes, leave this page?"
//
// Opt in by putting `data-yw-unsaved-guard` on a <form>. Anything typed inside it makes the
// page dirty; submitting it makes the page clean again, because submitting IS saving.
//
// Two halves, and both are needed. `beforeunload` covers a real navigation -- a typed URL,
// the back button, closing the tab. It does **not** cover an internal link, because since
// ticket 16 those load through htmx and an htmx navigation never unloads the document. So
// `htmx:confirm`, which htmx fires before every request and which can be cancelled, is the
// same guard for the other half of the navigations. The page editor learned this the hard
// way and carries the same pair (EditHandler); this module is that idea, reusable.
//
// **The exemption is the subtle part.** A screen may post its own form over htmx without
// navigating anywhere -- /admin/layout posts it on every keystroke to render the live
// preview. Guarding those would ask "are you sure you want to leave?" while you type, which
// is worse than not guarding at all. So a request issued from *inside* a guarded form is
// never a departure from it.

const GUARDED = '[data-yw-unsaved-guard]'

/** The guarded form holding changes nobody has saved, or null. */
let dirty = null

function guardedFormOf(node) {
  if (!node || typeof node.closest !== 'function') return null
  return node.closest(GUARDED)
}

const markDirty = (event) => {
  const form = guardedFormOf(event.target)
  if (form) dirty = form
}

// Delegated rather than bound to the form: rows are added to these screens after load, and a
// listener attached at startup would not know about them. It also survives an htmx swap,
// which is the thing every initialiser in this codebase has had to relearn.
document.addEventListener('input', markDirty)
document.addEventListener('change', markDirty)

// Capture, so this runs before anything that might cancel the submit: what matters is that
// a form on its way to the server stops being unsaved work.
const markSaved = (event) => {
  if (guardedFormOf(event.target)) dirty = null
}
document.addEventListener('submit', markSaved, true)

window.addEventListener('beforeunload', (event) => {
  if (!dirty) return
  // The browser shows its own wording here; ours is only reachable on the htmx path below.
  // Both calls: the current spec says preventDefault() is what raises the dialog, and
  // browsers older than Chrome 119 / Safari 17.4 only honour `returnValue`.
  event.preventDefault()
  // eslint-disable-next-line no-param-reassign -- mutating the event IS this legacy API
  event.returnValue = ''
})

document.addEventListener('htmx:confirm', (event) => {
  if (!dirty) return
  // a request the guarded form issued itself is not someone leaving it -- see the note at
  // the top about the live preview
  if (guardedFormOf(event.detail.elt)) return

  event.preventDefault()
  // eslint-disable-next-line no-alert -- the same prompt the page editor raises, by design
  if (window.confirm(_t('EDIT_LEAVE_WITHOUT_SAVING'))) {
    dirty = null
    event.detail.issueRequest(true)
  }
})

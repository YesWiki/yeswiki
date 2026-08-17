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

document.addEventListener('input', markDirty)
document.addEventListener('change', markDirty)

const markSaved = (event) => {
  if (guardedFormOf(event.target)) dirty = null
}
document.addEventListener('submit', markSaved, true)

window.addEventListener('beforeunload', (event) => {
  if (!dirty) return
  event.preventDefault()

  event.returnValue = ''
})

document.addEventListener('htmx:confirm', (event) => {
  if (!dirty) return
  if (guardedFormOf(event.detail.elt)) return

  event.preventDefault()
  if (window.confirm(_t('EDIT_LEAVE_WITHOUT_SAVING'))) {
    dirty = null
    event.detail.issueRequest(true)
  }
})

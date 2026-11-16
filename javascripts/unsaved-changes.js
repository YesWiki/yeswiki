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
  if (!dirty || event.detail.question) return
  if (guardedFormOf(event.detail.elt)) return

  event.preventDefault()
  window
    .ywConfirm(_t('EDIT_LEAVE_WITHOUT_SAVING'), { destructive: true })
    .then((confirmed) => {
      if (!confirmed) return
      dirty = null
      event.detail.issueRequest(true)
    })
})

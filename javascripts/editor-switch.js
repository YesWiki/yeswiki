/**
 * PROTOTYPE -- swapping the wiki-syntax editor for the other one, mid-edit.
 *
 * The two editors are different enough in markup and in assets that shipping both on every
 * edit screen to toggle between them would cost every writer the weight of the one they are
 * not using. So the switch is a reload: it says which editor it wants in a cookie the
 * server reads (AceditorAction), and the page comes back rendered as the other one.
 *
 * What is being written must survive that, and the only thing that knows it is the browser
 * -- the text has not been posted anywhere. It goes into sessionStorage on the way out and
 * is put back into the textarea on the way in, before either editor reads it. So a switch
 * keeps unsaved work, and the reload is the whole mechanism.
 */
const COOKIE = 'yw_editor'
const STASH = 'yw-editor-stash'

/** Which editor the reader last asked for, if they have asked. */
export const preferredEditor = () =>
  document.cookie.match(new RegExp(`(?:^|;\\s*)${COOKIE}=([^;]*)`))?.[1] || ''

/**
 * Leave for the other editor, carrying what is written.
 *
 * @param textarea the field being edited -- its `name` is what the stash is keyed on, so
 *   a form with several editors on it puts each one's text back where it came from.
 */
export function switchEditorTo(name, textarea) {
  const stash = readStash()
  stash[textarea.name] = textarea.value
  sessionStorage.setItem(STASH, JSON.stringify(stash))
  // path=/ so the choice holds for the next page edited, not only for this one
  document.cookie = `${COOKIE}=${name}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`
  window.location.reload()
}

/**
 * Put back what the other editor was holding, if this field is coming back from a switch.
 * Consumed rather than read: a later reload of the same page is a fresh start, and putting
 * stale text back over what the server sent would undo whatever happened in between.
 */
export function restoreStashedValue(textareaParam) {
  const textarea = textareaParam
  const stash = readStash()
  if (!Object.prototype.hasOwnProperty.call(stash, textarea.name)) return

  textarea.value = stash[textarea.name]
  delete stash[textarea.name]
  if (Object.keys(stash).length === 0) {
    sessionStorage.removeItem(STASH)
  } else {
    sessionStorage.setItem(STASH, JSON.stringify(stash))
  }
}

function readStash() {
  try {
    return JSON.parse(sessionStorage.getItem(STASH)) || {}
  } catch {
    // a stash that cannot be read is one nobody wrote: start over rather than throw on
    // every editor the page carries
    return {}
  }
}

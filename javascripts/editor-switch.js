/** PROTOTYPE -- swapping the wiki-syntax editor for the other one, mid-edit. */
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
  document.cookie = `${COOKIE}=${name}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`
  window.location.reload()
}

/** Put back what the other editor was holding, if this field is coming back from a switch. */
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
    return {}
  }
}

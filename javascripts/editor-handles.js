/**
 * Every wiki-syntax editor on the page, by the name of the field it edits.
 *
 * Two editors write into the same `textarea` -- the ACeditor and the Vditor one -- and the
 * page around them has to read and set what one of them holds without knowing which is on
 * screen: posting a comment empties the field, and a required entry field is validated by
 * reading it. Both of those were written as `window['aceditor-body'].editor`, which is one
 * editor's own instance, so with the wysiwyg editor on they threw instead.
 *
 * What a handle promises is only what those callers need: the wiki text, in and out. The
 * ACeditor's instance stays where it was (`window['aceditor-<name>']`) for anything that
 * really does mean Ace.
 *
 * Kept on `window` because not every caller is a module -- `yeswiki-base.js` is a classic
 * script -- and because a browser test has no other way in.
 *
 * @typedef {{getValue: () => string, setValue: (text: string) => void}} EditorHandle
 */

/** @param {EditorHandle} handle */
export function registerEditor(name, handle) {
  if (!name) return
  window.ywEditors = window.ywEditors || {}
  window.ywEditors[name] = handle
}

/** @returns {EditorHandle | undefined} */
export function editorFor(name) {
  return window.ywEditors?.[name]
}

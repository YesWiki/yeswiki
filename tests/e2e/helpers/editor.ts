import { Page, expect } from '@playwright/test'

/**
 * The editor these helpers talk to is whichever one the wiki draws.
 *
 * Since the wysiwyg editor became the default there are two, and a test that means "the
 * body of this page" must not have to know which. Both publish the same handle -- the wiki
 * text, in and out, keyed on the field's name (javascripts/editor-handles.js) -- so that is
 * what these use. Reading the textarea instead would work for one of them and silently not
 * for the other: the wysiwyg editor writes into it and never reads it back.
 */

const BODY = 'body'

/** Wait until the field's editor has published its handle. */
export const editorReady = async (page: Page, name: string = BODY) => {
  await page.waitForFunction(
    (field) => window['ywEditors']?.[field] !== undefined,
    name,
    { timeout: 15000 },
  )
}

export const editorText = (page: Page, name: string = BODY): Promise<string> =>
  page.evaluate((field) => window['ywEditors'][field].getValue(), name)

/**
 * Replace the text in the editor using a callback function.
 * The callback function does not have access to the page context.
 * So only use the value passed as argument and the browser api.
 */
export const replaceEditorTextCallback = async (
  page: Page,
  callback: Function,
  additionalProperties: object = null,
) => {
  await page.waitForLoadState()

  // Since ticket 16 an internal link is htmx-boosted, so reaching the editor by clicking
  // "Éditer la page" swaps the body rather than loading a document: waitForLoadState()
  // resolves before ywInitEach has built the editor, and the handle is still undefined.
  // Wait for the handle itself rather than for the navigation.
  await editorReady(page)

  await page.evaluate(
    ({ callbackStr, additionalPropertiesStr, field }) => {
      const additionalProperties = JSON.parse(additionalPropertiesStr)
      const callback = new Function('return ' + callbackStr)()
      const editor = window['ywEditors'][field]
      editor.setValue(callback(editor.getValue(), additionalProperties))
    },
    {
      callbackStr: callback.toString(),
      additionalPropertiesStr: JSON.stringify(additionalProperties),
      field: BODY,
    },
  )
}

export const replaceEditorTextNewContent = async (
  page: Page,
  newContent: string,
) => {
  await replaceEditorTextCallback(
    page,
    (value, additionalProperties) => additionalProperties.content,
    { content: newContent },
  )
}

/** Open a page's editor on exactly this text, whichever editor the wiki draws. */
export const openEditorWith = async (
  page: Page,
  content: string,
  tag: string = 'PagePrincipale',
) => {
  await page.goto(`/?${tag}/edit`)
  await editorReady(page)
  await replaceEditorTextNewContent(page, content)
}

/**
 * Ask for the source editor for the rest of this test.
 *
 * For the few behaviours that are its own -- its toolbar writes a link over the selection,
 * and the wysiwyg one has no such button -- rather than for testing the editor a wiki
 * actually opens with. Read by AceditorAction::chosenEditorTemplate() before any script
 * runs, so it must be set before the editor page is loaded.
 */
export const useSourceEditor = async (page: Page) => {
  await page.context().addCookies([
    {
      name: 'yw_editor',
      value: 'aceditor',
      url: process.env.YESWIKI_BASE_URL || 'https://yeswiki.test',
    },
  ])
}

/**
 * Click "Sauver" and wait for the save to actually land.
 *
 * This is ticket 25's defect 9, root-caused. The editor form carries hx-boost="false"
 * (ticket 25 defect 5), so saving is a real form POST and a full navigation -- and
 * Playwright's `click()` does not wait for a navigation it triggers. Asserting straight
 * after the click therefore starts against the OLD document and survives only because
 * `expect`'s 5s retry usually outlasts the round trip.
 *
 * Usually. On a cold opcache the whole edit/save path is compiled during that request:
 * `example.spec.ts` runs in ~4s warm and was measured at 9.6s cold, so the assertion window
 * expires while the browser is still showing the pre-save page. That is exactly the reported
 * symptom -- "it receives the pre-save content" -- and it is why the failure only ever
 * appeared in full-suite runs after code changes, never in isolation.
 *
 * The fix is to wait for the round trip rather than to raise the timeout: what the test is
 * about is whether the save worked, never how fast the server was.
 */
export const saveEditor = async (page: Page) => {
  const saved = page.waitForResponse(
    (response) => response.request().method() === 'POST',
    { timeout: 30000 },
  )
  // the wysiwyg editor's Save is a toolbar item that submits through the form's own
  // button, which is in the document and hidden -- so "the Save button" is two elements
  // and only one of them can be clicked
  await page
    .getByRole('button', { name: 'Sauver' })
    .filter({ visible: true })
    .first()
    .click()
  await saved
  await page.waitForLoadState()
}

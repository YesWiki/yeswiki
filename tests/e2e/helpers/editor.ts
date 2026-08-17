import { Page, expect } from '@playwright/test'

/** The editor these helpers talk to is whichever one the wiki draws. */

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

/** Replace the text in the editor using a callback function. */
export const replaceEditorTextCallback = async (
  page: Page,
  callback: Function,
  additionalProperties: object = null,
) => {
  await page.waitForLoadState()

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

/** Ask for the source editor for the rest of this test. */
export const useSourceEditor = async (page: Page) => {
  await page.context().addCookies([
    {
      name: 'yw_editor',
      value: 'aceditor',
      url: process.env.YESWIKI_BASE_URL || 'https://yeswiki.test',
    },
  ])
}

/** Click "Sauver" and wait for the save to actually land. */
export const saveEditor = async (page: Page) => {
  const saved = page.waitForResponse(
    (response) => response.request().method() === 'POST',
    { timeout: 30000 },
  )
  await page
    .getByRole('button', { name: 'Sauver' })
    .filter({ visible: true })
    .first()
    .click()
  await saved
  await page.waitForLoadState()
}

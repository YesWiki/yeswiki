import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { replaceEditorTextNewContent } from '../helpers/editor'

test.beforeEach(async() => {
  resetEnv()
})

/**
 * The link editor is a rail beside the editor, on the same rules as the actions builder:
 * it opens on the link the cursor is in, closes when the cursor leaves, and writes into
 * the document only when asked. There is no button to press first -- the pencil that used
 * to float over the line is gone, along with the modal it opened.
 */

const PANEL = '#YesWikiLinkPanel'
const ACTIONS = '#actions-builder-panel'
const MARKER = '.ace-body .ace_marker-layer .yw-active-group'

const editorContent = (page: Page) => page.evaluate(() => window['aceditor-body'].editor.getValue())

const openEditor = async(page: Page, content: string) => {
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(() => window['aceditor-body']?.editor !== undefined, null, {timeout: 15000})
  await replaceEditorTextNewContent(page, content)
}

const putCursorAt = async(page: Page, row: number, column: number) => {
  await page.locator('.ace-body').click()
  await page.evaluate(([r, c]) => {
    window['aceditor-body'].editor.ace.selection.moveCursorTo(r, c)
  }, [row, column])
}

test('putting the cursor in a link opens it, with no button in between', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page, 'avant [Le lien](PagePrincipale "un titre") après\ndu texte')

  await expect(page.locator(PANEL)).toBeHidden()
  await expect(page.locator('.flying-edit-button'), 'the flying pencil is gone').toHaveCount(0)

  await putCursorAt(page, 0, 12)

  await expect(page.locator(PANEL)).toBeVisible()
  await expect(page.locator(`${PANEL} input[name=url]`)).toHaveValue('PagePrincipale')
  await expect(page.locator(`${PANEL} input[name=text]`)).toHaveValue('Le lien')
  await expect(page.locator(`${PANEL} input[name=title]`)).toHaveValue('un titre')
  await expect(page.locator(MARKER), 'and the link is marked in the text').toHaveCount(1)
})

test('leaving the link closes the rail and writes nothing', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const text = 'avant [Le lien](PagePrincipale) après\ndu texte'
  await openEditor(page, text)

  await putCursorAt(page, 0, 12)
  await page.locator(`${PANEL} input[name=text]`).fill('jamais demandé')
  await putCursorAt(page, 1, 3)

  await expect(page.locator(PANEL)).toBeHidden()
  await expect(page.locator(MARKER)).toHaveCount(0)
  expect(await editorContent(page), 'the change went with it').toBe(text)
})

test('the rail rewrites the link it is on when asked', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page, 'avant [Le lien](PagePrincipale) après')

  await putCursorAt(page, 0, 12)
  await page.locator(`${PANEL} input[name=text]`).fill('Un autre texte')
  await page.locator(`${PANEL} .btn-insert`).click()

  await expect.poll(() => editorContent(page)).toBe('avant [Un autre texte](PagePrincipale) après')
})

/** They share one slot on the right, so only one of them can be showing. */
test('a component and a link never share the rail', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page, [
    'avant [Le lien](PagePrincipale) après',
    '{{button text="Hello" link="https://yeswiki.net"}}'
  ].join('\n'))

  await putCursorAt(page, 0, 12)
  await expect(page.locator(PANEL)).toBeVisible()
  await expect(page.locator(ACTIONS)).toBeHidden()

  await putCursorAt(page, 1, 12)
  await expect(page.locator(ACTIONS)).toBeVisible()
  await expect(page.locator(PANEL)).toBeHidden()
})

/**
 * The toolbar writes a NEW link, over what is selected. The selection is captured when the
 * button is pressed: the rail is not a modal, so nothing stops the caret moving on before
 * the link is filled in.
 */
test('the toolbar button writes a new link over the selection', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page, 'hello world')
  await page.locator('.ace-body').click()
  await page.evaluate(() => {
    window['aceditor-body'].editor.ace.selection.setRange(new window['ace'].Range(0, 6, 0, 11))
  })

  await page.locator('.aceditor-btn-link').first().click()

  await expect(page.locator(PANEL)).toBeVisible()
  await expect(page.locator(`${PANEL} input[name=text]`), 'the selection is the link text').toHaveValue('world')
  await page.locator(`${PANEL} input[name=url]`).fill('PagePrincipale')
  await page.locator(`${PANEL} .btn-insert`).click()

  await expect.poll(() => editorContent(page)).toBe('hello [world](PagePrincipale)')
})

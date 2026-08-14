import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import {
  editorText,
  openEditorWith,
  replaceEditorTextNewContent,
  useSourceEditor,
} from '../helpers/editor'
import { clickText, components, link } from '../helpers/wysiwyg'

test.beforeEach(async () => {
  resetEnv()
})

/**
 * The link editor is a rail beside the editor, on the same rules as the actions builder:
 * it opens on the link you point at, closes when you leave it, and writes into the
 * document only when asked. There is no button to press first -- the pencil that used to
 * float over the line is gone, along with the modal it opened.
 *
 * Pointing at one means clicking it, in the editor a wiki opens with: a link in the page
 * being written is not a link to follow -- following it would leave the editor -- it is
 * the thing to edit.
 */

const PANEL = '#YesWikiLinkPanel'
const ACTIONS = '#actions-builder-panel'

test('clicking a link opens it, with no button in between', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    'avant [Le lien](PagePrincipale "un titre") après\n\ndu texte',
  )

  await expect(page.locator(PANEL)).toBeHidden()
  await expect(
    page.locator('.flying-edit-button'),
    'the flying pencil is gone',
  ).toHaveCount(0)

  await link(page, 'Le lien').click()

  await expect(page.locator(PANEL)).toBeVisible()
  await expect(page.locator(`${PANEL} input[name=url]`)).toHaveValue(
    'PagePrincipale',
  )
  await expect(page.locator(`${PANEL} input[name=text]`)).toHaveValue('Le lien')
  await expect(page.locator(`${PANEL} input[name=title]`)).toHaveValue(
    'un titre',
  )
})

test('leaving the link closes the rail and writes nothing', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const text = 'avant [Le lien](PagePrincipale) après\n\ndu texte'
  await openEditorWith(page, text)

  await link(page, 'Le lien').click()
  await page.locator(`${PANEL} input[name=text]`).fill('jamais demandé')
  await clickText(page, 'du texte')

  await expect(page.locator(PANEL)).toBeHidden()
  expect((await editorText(page)).trim(), 'the change went with it').toBe(text)
})

test('the rail rewrites the link it is on when asked', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(page, 'avant [Le lien](PagePrincipale) après')

  await link(page, 'Le lien').click()
  await page.locator(`${PANEL} input[name=text]`).fill('Un autre texte')
  await page.locator(`${PANEL} .btn-insert`).click()

  await expect
    .poll(async () => (await editorText(page)).trim())
    .toBe('avant [Un autre texte](PagePrincipale) après')
})

/** They share one slot on the right, so only one of them can be showing. */
test('a component and a link never share the rail', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    [
      'avant [Le lien](PagePrincipale) après',
      '',
      '{{button text="Hello" link="https://yeswiki.net"}}',
    ].join('\n'),
  )

  await link(page, 'Le lien').click()
  await expect(page.locator(PANEL)).toBeVisible()
  await expect(page.locator(ACTIONS)).toBeHidden()

  await components(page).first().click()
  await expect(page.locator(ACTIONS)).toBeVisible()
  await expect(page.locator(PANEL)).toBeHidden()
})

/**
 * The page suggestions are a dropdown, and a dropdown needs a way out. This one had none:
 * it was hidden when a suggestion was picked or when the field went empty, and by nothing
 * else -- so Escape did nothing and clicking away left the list sitting over the panel.
 */
test('the page suggestions close on Escape and on clicking away', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(page, 'avant [Le lien](PagePrincipale) après')

  const url = page.locator(`${PANEL} input[name=url]`)
  const suggestions = page.locator(`${PANEL} [data-link-panel-suggestions]`)

  await link(page, 'Le lien').click()
  await expect(page.locator(PANEL)).toBeVisible()
  await url.click()
  await url.fill('Page')
  await expect(suggestions).toBeVisible()

  await url.press('Escape')
  await expect(suggestions, 'Escape closes the list').toBeHidden()
  await expect(
    page.locator(PANEL),
    'and closes only the list -- the rail stays put',
  ).toBeVisible()

  // typing again brings them back, so Escape hid them rather than switching them off
  await url.fill('PagePr')
  await expect(suggestions).toBeVisible()

  await page.locator(`${PANEL} input[name=text]`).click()
  await expect(suggestions, 'clicking away closes the list').toBeHidden()
})

/**
 * The source editor's own way in, which the wysiwyg one has no equivalent of: there, a
 * link is written by selecting words and pressing a toolbar button, and the selection is
 * captured when the button is pressed -- the rail is not a modal, so nothing stops the
 * caret moving on before the link is filled in. In the wysiwyg editor the link button is
 * Vditor's, and what the rail edits is a link that already exists.
 */
test('the source editor writes a new link over the selection', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await useSourceEditor(page)
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(
    () => window['aceditor-body']?.editor !== undefined,
    null,
    { timeout: 15000 },
  )
  await replaceEditorTextNewContent(page, 'hello world')

  await page.locator('.ace-body').click()
  await page.evaluate(() => {
    window['aceditor-body'].editor.ace.selection.setRange(
      new window['ace'].Range(0, 6, 0, 11),
    )
  })

  await page.locator('.aceditor-btn-link').first().click()

  await expect(page.locator(PANEL)).toBeVisible()
  await expect(
    page.locator(`${PANEL} input[name=text]`),
    'the selection is the link text',
  ).toHaveValue('world')
  await page.locator(`${PANEL} input[name=url]`).fill('PagePrincipale')
  await page.locator(`${PANEL} .btn-insert`).click()

  await expect
    .poll(() => editorText(page))
    .toBe('hello [world](PagePrincipale)')
})

/**
 * ...and there, the rail still opens on whatever the caret lands in. The gesture is the
 * only thing that differs between the two editors; the rail is the same rail.
 */
test('the source editor opens the rail on the link the caret is in', async ({
  page,
}: {
  page: Page
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await useSourceEditor(page)
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(
    () => window['aceditor-body']?.editor !== undefined,
    null,
    { timeout: 15000 },
  )
  await replaceEditorTextNewContent(
    page,
    'avant [Le lien](PagePrincipale) après\ndu texte',
  )

  await page.locator('.ace-body').click()
  await page.evaluate(() =>
    window['aceditor-body'].editor.ace.selection.moveCursorTo(0, 12),
  )

  await expect(page.locator(PANEL)).toBeVisible()
  await expect(page.locator(`${PANEL} input[name=url]`)).toHaveValue(
    'PagePrincipale',
  )
  await expect(
    page.locator('.ace-body .ace_marker-layer .yw-active-group'),
    'and the link is marked in the text',
  ).toHaveCount(1)
})

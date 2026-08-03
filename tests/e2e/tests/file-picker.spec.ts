import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { attachConsole, watchConsole } from '../helpers/console'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { replaceEditorTextNewContent } from '../helpers/editor'

test.beforeEach(async() => {
  resetEnv()
})

/**
 * The editor's file picker, in a browser.
 *
 * Everything here is structurally invisible to phpunit: the list of already-uploaded
 * files is fetched by the rail's own script, so a URL that does not reach the route
 * looks exactly like "this wiki has no files" -- which is what it looked like, for as
 * long as the fetch asked for `/?/api/files` and got the home page back. A test that
 * only asserts the route's JSON would still have passed.
 */

// 1x1 transparent PNG
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  'base64'
)

const openPicker = async(page: Page) => {
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(() => window['aceditor-body']?.editor !== undefined, null, {timeout: 15000})
  await page.locator('.aceditor-btn-file').first().click()
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()
}

const uploadThroughPicker = async(page: Page, name: string, mimeType: string, buffer: Buffer) => {
  await page.locator('[data-yw-file-picker-tab="upload"]').click()
  await page.locator('#YesWikiFilePickerPanel input[name="upFile"]').setInputFiles({name, mimeType, buffer})
  await page.locator('#YesWikiFilePickerPanel .btn-do-upload').click()
  // the upload lands the file in the list and selects it
  await expect(page.locator('[data-yw-file-picker-selected-name]')).toHaveText(name)
}

const resultNames = (page: Page) => page.locator('#YesWikiFilePickerPanel .file-picker__name')

test('the picker lists the files already uploaded, and filters them', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await uploadThroughPicker(page, 'minutes.txt', 'text/plain', Buffer.from('some notes'))

  // both are in the list without reopening anything -- the regression this covers is an
  // empty list, so the count matters more than the order
  await expect(resultNames(page)).toHaveText(['minutes.txt', 'holiday.png'])

  // each family says how many files it holds
  const families = page.locator('#YesWikiFilePickerPanel .file-picker__family')
  await expect(families).toHaveText(['Tous (2)', 'Images (1)', 'Documents (1)'])

  await families.filter({hasText: 'Images'}).click()
  await expect(resultNames(page)).toHaveText(['holiday.png'])

  // the extension list is built from what the family left, and .txt is not in it
  const extensions = page.locator('#YesWikiFilePickerPanel [data-yw-file-picker-extensions] option')
  await expect(extensions).toHaveText(['Toutes les extensions', '.png'])

  await families.filter({hasText: 'Tous'}).click()
  // searching the extension finds a kind of file, not just a name
  await page.locator('#YesWikiFilePickerPanel input[name="search"]').fill('txt')
  await expect(resultNames(page)).toHaveText(['minutes.txt'])

  await page.locator('#YesWikiFilePickerPanel input[name="search"]').fill('nothing matches this')
  await expect(resultNames(page)).toHaveCount(0)
  await expect(page.locator('[data-yw-file-picker-empty]')).toBeVisible()
})

test('picking a file inserts an attach action for it', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await page.locator('#YesWikiFilePickerPanel .file-picker__result').first().click()
  await page.locator('#YesWikiFilePickerPanel .btn-insert-upload').click()

  const content = await page.evaluate(() => window['aceditor-body'].editor.getValue())
  expect(content).toMatch(/\{\{attach file="[^"]+" desc="holiday\.png"/)
})

/**
 * The same rail from the Vditor toolbar. What differs is what gets inserted: a Vditor
 * field stores HTML and never meets the wiki parser, so a {{attach}} action would sit
 * there as literal text. The options that only exist as parameters of that action are
 * not offered either.
 */
const openVditorPicker = async(page: Page) => {
  // form 1's "Ma présentation" is the seeded wiki's only html-syntax textarea
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  await page.waitForSelector('.vditor-toolbar__item > button[data-type="yw-file"]', {timeout: 15000})
  await page.locator('.vditor-toolbar__item > button[data-type="yw-file"]').click()
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()
}

test('the Vditor toolbar opens the same picker and inserts Markdown', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openVditorPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await page.locator('#YesWikiFilePickerPanel .file-picker__result').first().click()

  // an alignment or a PDF display mode has nowhere to go in Markdown, so it is not offered
  await expect(page.locator('#YesWikiFilePickerPanel .image-option').first()).toBeHidden()
  await expect(page.locator('#YesWikiFilePickerPanel [data-yw-collapse-toggle]')).toBeHidden()

  await page.locator('#YesWikiFilePickerPanel .btn-insert-upload').click()

  // the textarea, not the editor surface, is what the form submits
  await expect(page.locator('textarea.vditor-html').first())
    .toHaveValue(/<img src="[^"]*api\/files\/[^"]+\/download" alt="holiday\.png"/)
})

test('the Vditor toolbar buttons are styled like the ACeditor ones', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  await page.waitForSelector('.vditor-toolbar__item > button.yw-btn', {timeout: 15000})

  // .yw-btn is added by vditor-textarea.js after Vditor has built its own toolbar; every
  // top-level button gets it, and none of the rows inside a dropdown panel does
  await expect(page.locator('.vditor-toolbar > .vditor-toolbar__item > button:not(.yw-btn)')).toHaveCount(0)
  await expect(page.locator('.vditor-hint button.yw-btn')).toHaveCount(0)

  // and the class actually paints them: Vditor's own rule makes them transparent
  const background = await page.locator('.vditor-toolbar__item > button.yw-btn').first()
    .evaluate((el) => getComputedStyle(el).backgroundColor)
  expect(background).toBe('rgb(255, 255, 255)')
})

test('both editor toolbars stay on screen, on the same line, when the content is long', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  await page.waitForSelector('.vditor-toolbar--pin', {timeout: 15000})

  // a long entry: without a sticky toolbar, formatting the bottom of it means scrolling
  // back up for every button
  await page.evaluate(() => {
    document.querySelector('.vditor-wysiwyg .vditor-reset').innerHTML =
      Array.from({length: 80}, (unused, i) => `<p>line ${i}</p>`).join('')
  })
  const toolbar = page.locator('.vditor-toolbar').first()
  const before = await toolbar.boundingBox()

  await page.mouse.wheel(0, 600)
  await expect.poll(async() => (await toolbar.boundingBox()).y).toBeLessThan(before.y)
  const parked = (await toolbar.boundingBox()).y

  // still fully on screen, and clear of the sticky top bar
  expect(parked).toBeGreaterThan(0)
  await page.mouse.wheel(0, 600)
  await expect.poll(async() => (await toolbar.boundingBox()).y).toBe(parked)

  // the ACeditor parks on exactly the same line -- a form can show both at once, and
  // yw-core.css's --sticky-toolbar-top is what keeps them agreeing
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(() => window['aceditor-body']?.editor !== undefined, null, {timeout: 15000})
  await page.mouse.wheel(0, 600)
  await expect.poll(async() => (await page.locator('.scroll-container-toolbar').boundingBox()).y).toBe(parked)
})

/**
 * Vditor's per-block controls: move the block up, move it down, remove it.
 *
 * They were invisible because Vditor 3.11 declares `customWysiwygToolbar` optional,
 * provides no default, and calls it unconditionally while building that popover -- the
 * TypeError lands between the buttons being created and the panel being positioned, so
 * the buttons existed in the DOM at `display: none` forever. Nothing server-side knew,
 * and the only symptom was in the browser console, which is why this test watches it.
 */
test('a block can be moved and removed from the Vditor popover', async({page}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  await page.waitForSelector('.vditor-toolbar', {timeout: 15000})

  const surface = page.locator('.vditor-wysiwyg .vditor-reset')
  await surface.click()
  await page.keyboard.type('alpha')
  await page.keyboard.press('Enter')
  await page.keyboard.type('beta')

  const popover = page.locator('.vditor-wysiwyg .vditor-panel--none').first()
  await expect(popover, 'the block popover must actually be shown').toBeVisible()

  // the cursor is in the second paragraph, so it can go up but not down
  await popover.locator('[data-type="up"]').click()
  await expect(page.locator('textarea.vditor-html').first()).toHaveValue(/beta[\s\S]*alpha/)

  // the first block has no previous sibling, so an absent "up" is how this test knows the
  // popover has been rebuilt for it -- clicking a button the popover has not rebuilt yet
  // acts on the block it was last bound to
  await page.locator('.vditor-wysiwyg .vditor-reset > p').first().click()
  await expect(popover.locator('[data-type="up"]')).toHaveCount(0)
  await popover.locator('[data-type="remove"]').click()
  await expect(page.locator('textarea.vditor-html').first()).not.toHaveValue(/beta/)

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/**
 * The picker is a rail beside the editor, in the same slot as the actions builder and the
 * link editor -- so opening it closes whichever of those was showing, and it holds the
 * slot while a file is being chosen even though the cursor keeps moving behind it.
 */
test('the picker takes the rail slot, and keeps it until it is done', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(() => window['aceditor-body']?.editor !== undefined, null, {timeout: 15000})
  await replaceEditorTextNewContent(page, '{{button text="Hello" link="https://yeswiki.net"}}\nplain text')

  // the cursor opens the actions builder on the component
  await page.locator('.ace-body').click()
  await page.evaluate(() => window['aceditor-body'].editor.ace.selection.moveCursorTo(0, 12))
  await expect(page.locator('#actions-builder-panel')).toBeVisible()

  await page.locator('.aceditor-btn-file').first().click()

  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()
  await expect(page.locator('#actions-builder-panel'), 'one rail at a time').toBeHidden()

  // the caret moving out of the component must not close a picker mid-choice
  await page.evaluate(() => window['aceditor-body'].editor.ace.selection.moveCursorTo(1, 3))
  await page.waitForTimeout(400)
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()

  // and the link rail displaces it in turn
  await page.locator('.aceditor-btn-link').first().click()
  await expect(page.locator('#YesWikiLinkPanel')).toBeVisible()
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeHidden()
})

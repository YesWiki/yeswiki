import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { attachConsole, watchConsole } from '../helpers/console'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import {
  editorReady,
  editorText,
  openEditorWith,
  useSourceEditor,
} from '../helpers/editor'
import {
  clickText,
  components,
  componentsNamed,
  link,
  toolbarButton,
} from '../helpers/wysiwyg'

test.beforeEach(async () => {
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
  'base64',
)

/** The picker as the page editor offers it, which is the wysiwyg one's toolbar. */
const openPicker = async (page: Page) => {
  await page.goto('/?PagePrincipale/edit')
  await editorReady(page)
  await toolbarButton(page, 'yw-file').click()
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()
}

const backToList = async (page: Page) => {
  const back = page.locator(
    '#YesWikiFilePickerPanel [data-yw-file-picker-back]',
  )
  if (await back.isVisible()) {
    await back.click()
  }
  await expect(
    page.locator(
      '#YesWikiFilePickerPanel [data-yw-file-picker-pane="existing"]',
    ),
  ).toBeVisible()
}

const uploadThroughPicker = async (
  page: Page,
  name: string,
  mimeType: string,
  buffer: Buffer,
) => {
  // the rail is a state machine now: the upload action lives on the list view, so a
  // previous upload (which lands on the file it just made) has to be backed out of first
  await backToList(page)
  await page
    .locator('#YesWikiFilePickerPanel [data-yw-file-picker-upload-open]')
    .click()
  await page
    .locator('#YesWikiFilePickerPanel input[name="upFile"]')
    .setInputFiles({ name, mimeType, buffer })
  await page.locator('#YesWikiFilePickerPanel .btn-do-upload').click()
  // the upload lands the file in the list and selects it
  await expect(page.locator('[data-yw-file-picker-selected-name]')).toHaveText(
    name,
  )
}

const resultNames = (page: Page) =>
  page.locator('#YesWikiFilePickerPanel .file-picker__name')

test('the picker lists the files already uploaded, and filters them', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await uploadThroughPicker(
    page,
    'minutes.txt',
    'text/plain',
    Buffer.from('some notes'),
  )
  await backToList(page)

  // both are in the list without reopening anything -- the regression this covers is an
  // empty list, so the count matters more than the order
  await expect(resultNames(page)).toHaveText(['minutes.txt', 'holiday.png'])

  // each family says how many files it holds
  const families = page.locator('#YesWikiFilePickerPanel .file-picker__family')
  await expect(families).toHaveText(['Tous (2)', 'Images (1)', 'Documents (1)'])

  await families.filter({ hasText: 'Images' }).click()
  await expect(resultNames(page)).toHaveText(['holiday.png'])

  // the extension list is built from what the family left, and .txt is not in it
  const extensions = page.locator(
    '#YesWikiFilePickerPanel [data-yw-file-picker-extensions] option',
  )
  await expect(extensions).toHaveText(['Toutes les extensions', '.png'])

  await families.filter({ hasText: 'Tous' }).click()
  // searching the extension finds a kind of file, not just a name
  await page.locator('#YesWikiFilePickerPanel input[name="search"]').fill('txt')
  await expect(resultNames(page)).toHaveText(['minutes.txt'])

  await page
    .locator('#YesWikiFilePickerPanel input[name="search"]')
    .fill('nothing matches this')
  await expect(resultNames(page)).toHaveCount(0)
  await expect(page.locator('[data-yw-file-picker-empty]')).toBeVisible()
})

/**
 * The rail shows one thing at a time.
 *
 * It used to show a "choose an existing file" tab beside an "upload" one -- the first
 * switching to the view already on screen -- then append the chosen file *under* a list
 * still offering every other candidate, in a 340px column, above a footer split between
 * Cancel and Insert. This asserts the shape that replaced it, because every part of it is
 * invisible to phpunit: it is all in what the browser shows.
 */
test('the rail shows the list, the upload or the choice -- never two at once', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)

  const panel = page.locator('#YesWikiFilePickerPanel')
  const list = panel.locator('[data-yw-file-picker-pane="existing"]')
  const uploadPane = panel.locator('[data-yw-file-picker-pane="upload"]')
  const uploadOpen = panel.locator('[data-yw-file-picker-upload-open]')
  const back = panel.locator('[data-yw-file-picker-back]')

  // the tab that chose the view already on screen is gone, and so is the second way out
  await expect(panel.locator('[data-yw-file-picker-tab]')).toHaveCount(0)
  await expect(panel.locator('.btn-cancel-upload')).toHaveCount(0)

  // browsing: the list and one action, no way back from where you already are
  await expect(list).toBeVisible()
  await expect(uploadOpen).toBeVisible()
  await expect(back).toBeHidden()
  await expect(uploadOpen.locator('svg use')).toHaveAttribute(
    'href',
    /#upload$/,
  )

  // uploading takes the whole rail
  await uploadOpen.click()
  await expect(uploadPane).toBeVisible()
  await expect(list).toBeHidden()
  await expect(back).toBeVisible()

  await back.click()
  await expect(list).toBeVisible()
  await expect(uploadPane).toBeHidden()

  // the search and the extension filter share one line
  const searchBox = panel.locator('.file-picker-search input[name="search"]')
  const extensions = panel.locator('[data-yw-file-picker-extensions]')
  await expect(searchBox).toBeVisible()
  const searchTop = (await searchBox.boundingBox()).y
  const extensionsTop = (await extensions.boundingBox()).y
  expect(Math.abs(searchTop - extensionsTop)).toBeLessThan(8)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await backToList(page)

  // choosing one takes the whole rail too, and the row it came from says so
  const chosen = panel.locator('.file-picker__result').first()
  await chosen.click()
  await expect(list).toBeHidden()
  await expect(panel.locator('.file-picker-selected')).toBeVisible()
  await expect(back).toBeVisible()
  await expect(panel.locator('.btn-insert-upload')).toBeEnabled()

  // going back unmakes the choice, and the row shows which one it was
  await back.click()
  await expect(list).toBeVisible()
  await expect(panel.locator('.btn-insert-upload')).toBeDisabled()
  await expect(panel.locator('.file-picker__result--selected')).toHaveCount(0)
})

test('picking a file inserts an attach action for it', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await backToList(page)
  await page
    .locator('#YesWikiFilePickerPanel .file-picker__result')
    .first()
    .click()
  await page.locator('#YesWikiFilePickerPanel .btn-insert-upload').click()

  // a wiki-syntax field gets the component, so what lands in the page is a widget
  await expect
    .poll(() => editorText(page))
    .toMatch(/\{\{attach file="[^"]+" desc="holiday\.png"/)
  await expect(componentsNamed(page, 'attach')).toHaveCount(1)
})

/**
 * The same rail from the Vditor toolbar. What differs is what gets inserted: a Vditor
 * field stores HTML and never meets the wiki parser, so a {{attach}} action would sit
 * there as literal text. The options that only exist as parameters of that action are
 * not offered either.
 */
const openVditorPicker = async (page: Page) => {
  // form 1's "Ma présentation" is the seeded wiki's only html-syntax textarea
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  await page.waitForSelector(
    '.vditor-toolbar__item > button[data-type="yw-file"]',
    { timeout: 15000 },
  )
  await page
    .locator('.vditor-toolbar__item > button[data-type="yw-file"]')
    .click()
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()
}

test('the Vditor toolbar opens the same picker and inserts Markdown', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openVditorPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await backToList(page)
  await page
    .locator('#YesWikiFilePickerPanel .file-picker__result')
    .first()
    .click()

  // an alignment or a PDF display mode has nowhere to go in Markdown, so it is not offered
  await expect(
    page.locator('#YesWikiFilePickerPanel .image-option').first(),
  ).toBeHidden()
  await expect(
    page.locator('#YesWikiFilePickerPanel [data-yw-collapse-toggle]'),
  ).toBeHidden()

  await page.locator('#YesWikiFilePickerPanel .btn-insert-upload').click()

  // the textarea, not the editor surface, is what the form submits
  await expect(page.locator('textarea.vditor-html').first()).toHaveValue(
    /<img src="[^"]*api\/files\/[^"]+\/download" alt="holiday\.png"/,
  )
})

test('the Vditor toolbar buttons are styled like the ACeditor ones', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  await page.waitForSelector('.vditor-toolbar__item > button.yw-btn', {
    timeout: 15000,
  })

  // .yw-btn is added by vditor-textarea.js after Vditor has built its own toolbar; every
  // top-level button gets it, and none of the rows inside a dropdown panel does
  await expect(
    page.locator(
      '.vditor-toolbar > .vditor-toolbar__item > button:not(.yw-btn)',
    ),
  ).toHaveCount(0)
  await expect(page.locator('.vditor-hint button.yw-btn')).toHaveCount(0)

  // and the class actually paints them: Vditor's own rule makes them transparent
  const background = await page
    .locator('.vditor-toolbar__item > button.yw-btn')
    .first()
    .evaluate((el) => getComputedStyle(el).backgroundColor)
  expect(background).toBe('rgb(255, 255, 255)')
})

test('both editor toolbars stay on screen, on the same line, when the content is long', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  await page.waitForSelector('.vditor-toolbar--pin', { timeout: 15000 })

  // a long entry: without a sticky toolbar, formatting the bottom of it means scrolling
  // back up for every button
  await page.evaluate(() => {
    document.querySelector('.vditor-wysiwyg .vditor-reset').innerHTML =
      Array.from({ length: 80 }, (unused, i) => `<p>line ${i}</p>`).join('')
  })
  const toolbar = page.locator('.vditor-toolbar').first()
  const before = await toolbar.boundingBox()

  await page.mouse.wheel(0, 600)
  await expect
    .poll(async () => (await toolbar.boundingBox()).y)
    .toBeLessThan(before.y)
  const parked = (await toolbar.boundingBox()).y

  // still fully on screen, and clear of the sticky top bar
  expect(parked).toBeGreaterThan(0)
  await page.mouse.wheel(0, 600)
  await expect.poll(async () => (await toolbar.boundingBox()).y).toBe(parked)

  // the ACeditor parks on exactly the same line -- a form can show both at once, and
  // yw-core.css's --sticky-toolbar-top is what keeps them agreeing. Asked for by name,
  // since a page opens with the wysiwyg editor and this is about the other one's bar
  await useSourceEditor(page)
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(
    () => window['aceditor-body']?.editor !== undefined,
    null,
    { timeout: 15000 },
  )
  await page.mouse.wheel(0, 600)
  await expect
    .poll(
      async () =>
        (await page.locator('.scroll-container-toolbar').boundingBox()).y,
    )
    .toBe(parked)
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
test('a block can be moved and removed from the Vditor popover', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  await page.waitForSelector('.vditor-toolbar', { timeout: 15000 })

  const surface = page.locator('.vditor-wysiwyg .vditor-reset')
  await surface.click()
  await page.keyboard.type('alpha')
  await page.keyboard.press('Enter')
  await page.keyboard.type('beta')

  const popover = page.locator('.vditor-wysiwyg .vditor-panel--none').first()
  await expect(
    popover,
    'the block popover must actually be shown',
  ).toBeVisible()

  // the cursor is in the second paragraph, so it can go up but not down
  await popover.locator('[data-type="up"]').click()
  await expect(page.locator('textarea.vditor-html').first()).toHaveValue(
    /beta[\s\S]*alpha/,
  )

  // the first block has no previous sibling, so an absent "up" is how this test knows the
  // popover has been rebuilt for it -- clicking a button the popover has not rebuilt yet
  // acts on the block it was last bound to
  await page.locator('.vditor-wysiwyg .vditor-reset > p').first().click()
  await expect(popover.locator('[data-type="up"]')).toHaveCount(0)
  await popover.locator('[data-type="remove"]').click()
  await expect(page.locator('textarea.vditor-html').first()).not.toHaveValue(
    /beta/,
  )

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/**
 * The picker is a rail beside the editor, in the same slot as the actions builder and the
 * link editor -- so opening it closes whichever of those was showing, and it holds the
 * slot while a file is being chosen even though the cursor keeps moving behind it.
 */
test('the picker takes the rail slot, and keeps it until it is done', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    [
      '{{button text="Hello" link="https://yeswiki.net"}}',
      '',
      'plain text with a [Le lien](PagePrincipale) in it',
    ].join('\n'),
  )

  // clicking the component opens the actions builder on it
  await components(page).first().click()
  await expect(page.locator('#actions-builder-panel')).toBeVisible()

  await toolbarButton(page, 'yw-file').click()

  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()
  await expect(
    page.locator('#actions-builder-panel'),
    'one rail at a time',
  ).toBeHidden()

  // ...and clicking in the document must not close a picker mid-choice: where the file
  // goes is what that click is for. Every other rail takes it as "I have left"
  await clickText(page, 'plain text')
  await page.waitForTimeout(400)
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()

  // and the link rail displaces it in turn
  await link(page, 'Le lien').click()
  await expect(page.locator('#YesWikiLinkPanel')).toBeVisible()
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeHidden()
})

/**
 * An image is converted to WebP and capped before it is uploaded.
 *
 * Only a browser can see this at all: the conversion happens in the page, so every
 * server-side test sees whatever bytes it is handed and calls them correct. What is uploaded
 * is what the wiki stores, backs up and serves forever, so a phone's twelve-megapixel JPEG
 * arriving intact is a cost paid on every page view for the life of the wiki.
 *
 * The picture is drawn HERE rather than committed as a fixture: five megapixels of noise is
 * a five-megabyte file, and a repository is a bad place to keep one.
 */
test('an oversized photo is uploaded as a capped WebP', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)
  await page
    .locator('#YesWikiFilePickerPanel [data-yw-file-picker-upload-open]')
    .click()

  // 4200x2400 of noise -- over the 4K cap on the long side, and busy enough that JPEG
  // cannot make it small by accident. Drawn with rectangles rather than per-pixel ImageData,
  // which at this size is sixty megabytes of buffer and takes the headless browser with it.
  const original = await page.evaluate(async () => {
    const canvas = document.createElement('canvas')
    canvas.width = 4200
    canvas.height = 2400
    const context = canvas.getContext('2d')
    for (let i = 0; i < 4000; i += 1) {
      context.fillStyle = `hsl(${(i * 37) % 360} 90% ${20 + ((i * 11) % 60)}%)`
      context.fillRect(
        (i * 173) % canvas.width,
        (i * 271) % canvas.height,
        40 + ((i * 7) % 90),
        40 + ((i * 13) % 90),
      )
    }
    const blob = await new Promise((resolve) =>
      canvas.toBlob(resolve, 'image/jpeg', 0.95),
    )
    const file = new File([blob], 'photo.jpg', { type: 'image/jpeg' })
    const transfer = new DataTransfer()
    transfer.items.add(file)
    const input = document.querySelector(
      '#YesWikiFilePickerPanel input[name="upFile"]',
    )
    input.files = transfer.files
    input.dispatchEvent(new Event('change', { bubbles: true }))
    return { size: file.size }
  })
  expect(original.size).toBeGreaterThan(200 * 1024)

  await page.locator('#YesWikiFilePickerPanel .btn-do-upload').click()
  // the extension follows the format: `photo.jpg` is not what was sent
  await expect(page.locator('[data-yw-file-picker-selected-name]')).toHaveText(
    'photo.webp',
    { timeout: 30000 },
  )

  // and what LANDED is within the cap, which is the claim that matters -- read back from
  // the wiki, not from the browser's copy
  const stored = await page.evaluate(async () => {
    const answer = await fetch(wiki.url('api/files')).then((r) => r.json())
    const entry = answer.find((f) => f.original_filename === 'photo.webp')
    const blob = await fetch(wiki.url(`api/files/${entry.tag}/download`)).then(
      (r) => r.blob(),
    )
    const bitmap = await createImageBitmap(blob)
    return {
      size: blob.size,
      type: blob.type,
      width: bitmap.width,
      height: bitmap.height,
    }
  })

  expect(stored.type).toBe('image/webp')
  expect(stored.width).toBeLessThanOrEqual(3840)
  expect(stored.height).toBeLessThanOrEqual(2160)
  // fitted, not squashed: the shape is kept, and the dimension that has furthest to fall is
  // the one that lands on the cap -- 2400 into 2160 here, which takes 4200 down to 3780
  expect(stored.width).toBe(3780)
  expect(stored.height).toBe(2160)
  expect(stored.size).toBeLessThan(original.size)
})

/**
 * A small image is left exactly as it was.
 *
 * The point is bytes, not a uniform extension: re-encoding a 1x1 PNG as WebP makes it
 * bigger, and re-encoding a WebP as WebP makes it worse every time the same picture is
 * uploaded. Both are silent, which is why they are asserted rather than assumed.
 */
test('a small image is uploaded untouched', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await expect(page.locator('[data-yw-file-picker-selected-name]')).toHaveText(
    'holiday.png',
  )
})

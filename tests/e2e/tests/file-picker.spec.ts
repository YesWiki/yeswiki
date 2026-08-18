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

/** The name a chosen file lands under: a raster image is converted, so it changes. */
const storedName = (name: string, mimeType: string) =>
  mimeType.startsWith('image/') && mimeType !== 'image/svg+xml'
    ? name.replace(/\.[^.]+$/, '.webp')
    : name

const uploadThroughPicker = async (
  page: Page,
  name: string,
  mimeType: string,
  buffer: Buffer,
) => {
  await backToList(page)
  await page
    .locator('#YesWikiFilePickerPanel [data-yw-file-picker-upload-open]')
    .click()
  await page
    .locator('#YesWikiFilePickerPanel input[name="upFile"]')
    .setInputFiles({ name, mimeType, buffer })
  await page.locator('#YesWikiFilePickerPanel .btn-do-upload').click()
  await expect(page.locator('[data-yw-file-picker-selected-name]')).toHaveText(
    storedName(name, mimeType),
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

  await expect(resultNames(page)).toHaveText(['minutes.txt', 'holiday.webp'])

  const families = page.locator('#YesWikiFilePickerPanel .file-picker__family')
  await expect(families).toHaveText(['Tous (2)', 'Images (1)', 'Documents (1)'])

  await families.filter({ hasText: 'Images' }).click()
  await expect(resultNames(page)).toHaveText(['holiday.webp'])

  const extensions = page.locator(
    '#YesWikiFilePickerPanel [data-yw-file-picker-extensions] option',
  )
  await expect(extensions).toHaveText(['Toutes les extensions', '.webp'])

  await families.filter({ hasText: 'Tous' }).click()
  await page.locator('#YesWikiFilePickerPanel input[name="search"]').fill('txt')
  await expect(resultNames(page)).toHaveText(['minutes.txt'])

  await page
    .locator('#YesWikiFilePickerPanel input[name="search"]')
    .fill('nothing matches this')
  await expect(resultNames(page)).toHaveCount(0)
  await expect(page.locator('[data-yw-file-picker-empty]')).toBeVisible()
})

/** The rail shows one thing at a time. */
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

  await expect(panel.locator('[data-yw-file-picker-tab]')).toHaveCount(0)
  await expect(panel.locator('.btn-cancel-upload')).toHaveCount(0)

  await expect(list).toBeVisible()
  await expect(uploadOpen).toBeVisible()
  await expect(back).toBeHidden()
  await expect(uploadOpen.locator('svg use')).toHaveAttribute(
    'href',
    /#upload$/,
  )

  await uploadOpen.click()
  await expect(uploadPane).toBeVisible()
  await expect(list).toBeHidden()
  await expect(back).toBeVisible()

  await back.click()
  await expect(list).toBeVisible()
  await expect(uploadPane).toBeHidden()

  const searchBox = panel.locator('.file-picker-search input[name="search"]')
  const extensions = panel.locator('[data-yw-file-picker-extensions]')
  await expect(searchBox).toBeVisible()
  const searchTop = (await searchBox.boundingBox()).y
  const extensionsTop = (await extensions.boundingBox()).y
  expect(Math.abs(searchTop - extensionsTop)).toBeLessThan(8)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await backToList(page)

  const chosen = panel.locator('.file-picker__result').first()
  await chosen.click()
  await expect(list).toBeHidden()
  await expect(panel.locator('.file-picker-selected')).toBeVisible()
  await expect(back).toBeVisible()
  await expect(panel.locator('.btn-insert-upload')).toBeEnabled()

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

  await expect
    .poll(() => editorText(page))
    .toMatch(/\{\{attach file="[^"]+" desc="holiday\.webp"/)
  await expect(componentsNamed(page, 'attach')).toHaveCount(1)
})

/** The same rail from the Vditor toolbar. */
const openVditorPicker = async (page: Page) => {
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

  await expect(
    page.locator('#YesWikiFilePickerPanel .image-option').first(),
  ).toBeHidden()
  await expect(
    page.locator('#YesWikiFilePickerPanel [data-yw-collapse-toggle]'),
  ).toBeHidden()

  await page.locator('#YesWikiFilePickerPanel .btn-insert-upload').click()

  await expect(page.locator('textarea.vditor-html').first()).toHaveValue(
    /<img src="[^"]*api\/files\/[^"]+\/download" alt="holiday\.webp"/,
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

  await expect(
    page.locator(
      '.vditor-toolbar > .vditor-toolbar__item > button:not(.yw-btn)',
    ),
  ).toHaveCount(0)
  await expect(page.locator('.vditor-hint button.yw-btn')).toHaveCount(0)

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

  expect(parked).toBeGreaterThan(0)
  await page.mouse.wheel(0, 600)
  await expect.poll(async () => (await toolbar.boundingBox()).y).toBe(parked)

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

/** Vditor's per-block controls: move the block up, move it down, remove it. */
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

  await popover.locator('[data-type="up"]').click()
  await expect(page.locator('textarea.vditor-html').first()).toHaveValue(
    /beta[\s\S]*alpha/,
  )

  await page.locator('.vditor-wysiwyg .vditor-reset > p').first().click()
  await expect(popover.locator('[data-type="up"]')).toHaveCount(0)
  await popover.locator('[data-type="remove"]').click()
  await expect(page.locator('textarea.vditor-html').first()).not.toHaveValue(
    /beta/,
  )

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/** The picker is a rail beside the editor, in the same slot as the actions builder and the link editor -- so opening it closes whichever of those was showing, and it holds the slot while a file is being chosen even though the cursor keeps moving behind it. */
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

  await components(page).first().click()
  await expect(page.locator('#actions-builder-panel')).toBeVisible()

  await toolbarButton(page, 'yw-file').click()

  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()
  await expect(
    page.locator('#actions-builder-panel'),
    'one rail at a time',
  ).toBeHidden()

  await clickText(page, 'plain text')
  await page.waitForTimeout(400)
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeVisible()

  await link(page, 'Le lien').click()
  await expect(page.locator('#YesWikiLinkPanel')).toBeVisible()
  await expect(page.locator('#YesWikiFilePickerPanel')).toBeHidden()
})

/** An image is converted to WebP and capped before it is uploaded. */
test('an oversized photo is uploaded as a capped WebP', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)
  await page
    .locator('#YesWikiFilePickerPanel [data-yw-file-picker-upload-open]')
    .click()

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
  await expect(page.locator('[data-yw-file-picker-selected-name]')).toHaveText(
    'photo.webp',
    { timeout: 30000 },
  )

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
  expect(stored.width).toBeLessThanOrEqual(1920)
  expect(stored.height).toBeLessThanOrEqual(1920)
  expect(stored.width).toBe(1920)
  expect(stored.height).toBe(1097)
  expect(stored.size).toBeLessThan(original.size)
})

/** A small image is left exactly as it was. */
test('every raster image is stored as WebP, however small', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await expect(page.locator('[data-yw-file-picker-selected-name]')).toHaveText(
    'holiday.webp',
  )
})

test('an animated GIF keeps its frames, and its format', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)
  await page
    .locator('#YesWikiFilePickerPanel [data-yw-file-picker-upload-open]')
    .click()

  await page.evaluate(() => {
    const gif = Uint8Array.from(
      atob(
        'R0lGODlhAQABAIAAAAAAAP///yH/C05FVFNDQVBFMi4wAwEAAAAh+QQJAAAAACwAAAAAAQABAAACAkQBACH5BAkAAAAALAAAAAABAAEAAAICRAEAOw==',
      ),
      (c) => c.charCodeAt(0),
    )
    const transfer = new DataTransfer()
    transfer.items.add(new File([gif], 'wave.gif', { type: 'image/gif' }))
    const input = document.querySelector(
      '#YesWikiFilePickerPanel input[name="upFile"]',
    ) as HTMLInputElement
    input.files = transfer.files
    input.dispatchEvent(new Event('change', { bubbles: true }))
  })
  await page.locator('#YesWikiFilePickerPanel .btn-do-upload').click()

  await expect(page.locator('[data-yw-file-picker-selected-name]')).toHaveText(
    'wave.gif',
  )
})

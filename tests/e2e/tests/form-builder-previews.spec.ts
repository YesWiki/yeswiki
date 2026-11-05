import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { attachConsole, watchConsole } from '../helpers/console'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

/** The form designer's live previews, each asset arriving once with the preview that needs it. */

test.beforeEach(async () => {
  resetEnv()
})

const openDesigner = async (page: Page) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=formulaire&action=new')
  await expect(page.locator('.yw-fb__palette-grid')).toBeVisible()
}

/** Adding a field opens its settings over the palette, so the next add starts by going back. */
const addField = async (page: Page, type: string) => {
  const back = page.locator('.yw-fb__back')
  if (await back.isVisible()) {
    await back.click()
  }
  await page.locator(`.yw-fb__palette-item[data-fb-type="${type}"]`).click()
}

const cards = (page: Page) => page.locator('.yw-fb__canvas [data-fb-id]')

/** Every preview has answered: none is still showing its pending state. */
const previewsSettled = async (page: Page) => {
  await expect(page.locator('.yw-fb__card-preview--pending')).toHaveCount(0)
}

/** How many times the document asks the browser for a URL containing this name. */
const assetCount = (page: Page, needle: string) =>
  page
    .locator(`script[src*="${needle}"], link[href*="${needle}"]`)
    .evaluateAll((els) => els.length)

test('a map field previews as a map you could pan', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await openDesigner(page)

  await addField(page, 'map')

  const map = page.locator('.yw-fb__card-preview .leaflet-container')
  await expect(map).toBeVisible()
  await expect(map.locator('.leaflet-pane')).not.toHaveCount(0)
  await expect
    .poll(() => map.evaluate((el) => getComputedStyle(el).overflow))
    .toBe('hidden')

  await attachConsole(watcher, testInfo)
  expect(watcher.errors()).toEqual([])
})

test('a second map field does not fetch leaflet a second time', async ({
  page,
}) => {
  await openDesigner(page)

  await addField(page, 'map')
  await expect(page.locator('.leaflet-container')).toHaveCount(1)

  await addField(page, 'map')
  await expect(page.locator('.leaflet-container')).toHaveCount(2)
  await previewsSettled(page)

  expect(await assetCount(page, 'leaflet/leaflet.min.js')).toBe(1)
  expect(await assetCount(page, 'leaflet/leaflet.css')).toBe(1)
  expect(await assetCount(page, 'leaflet-providers')).toBe(1)
})

test('deleting one map leaves the other mounted and styled', async ({
  page,
}) => {
  await openDesigner(page)

  await addField(page, 'map')
  await addField(page, 'map')
  await expect(page.locator('.leaflet-container')).toHaveCount(2)
  await previewsSettled(page)

  await cards(page)
    .filter({ has: page.locator('.leaflet-container') })
    .first()
    .locator('[data-fb-action="delete"]')
    .click()

  await expect(page.locator('.leaflet-container')).toHaveCount(1)
  const survivor = page.locator('.leaflet-container')
  await expect(survivor.locator('.leaflet-pane')).not.toHaveCount(0)
  expect(await assetCount(page, 'leaflet/leaflet.css')).toBe(1)
  await expect
    .poll(() => survivor.evaluate((el) => getComputedStyle(el).overflow))
    .toBe('hidden')
})

test('the file picker previews as the picker, not as a bare input', async ({
  page,
}) => {
  await openDesigner(page)

  await addField(page, 'file')
  await previewsSettled(page)

  await expect(page.locator('.yw-fb__card-preview .file-or-url')).toBeVisible()
})

test('the tags field previews as the tag input', async ({ page }) => {
  await openDesigner(page)

  await addField(page, 'tags')
  await previewsSettled(page)

  await expect(
    page.locator('.yw-fb__card-preview [data-yw-tag-input]'),
  ).toBeVisible()
})

test('two tag inputs load their script once and survive each other', async ({
  page,
}) => {
  await openDesigner(page)

  await addField(page, 'tags')
  await addField(page, 'tags')
  await previewsSettled(page)
  await expect(page.locator('[data-yw-tag-input]')).toHaveCount(2)
  expect(await assetCount(page, 'yw-tags-input')).toBe(1)

  await cards(page).nth(1).locator('[data-fb-action="delete"]').click()

  await expect(page.locator('[data-yw-tag-input]')).toHaveCount(1)
  await expect(
    page.locator('.yw-fb__card-preview [data-yw-tag-input-search]'),
  ).toBeVisible()
})

test('a field with nothing to show says so instead of spinning', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await openDesigner(page)

  await addField(page, 'conditionschecking')
  await previewsSettled(page)

  await expect(page.locator('.yw-fb__card-nopreview').first()).toBeVisible()

  await attachConsole(watcher, testInfo)
  expect(watcher.errors()).toEqual([])
})

/** A required field's preview is not a field of the form being designed, so it cannot hold up saving it. */
test('a form with required checkbox, radio and map fields saves', async ({
  page,
}) => {
  const alerts: string[] = []
  page.on('dialog', async (dialog) => {
    alerts.push(dialog.message())
    await dialog.dismiss()
  })
  await openDesigner(page)
  await page.locator('#bazar-form-editor input[name="label"]').fill('Requis')

  for (const type of ['checkbox-group', 'radio-group', 'map']) {
    await addField(page, type)
    await page
      .locator('[data-fb-attribute="required"] select')
      .selectOption('1')
  }
  await previewsSettled(page)

  await page.locator('#bazar-form-editor button[name="valider"]').click()

  await expect(page.locator('#bazar-form-editor')).toHaveCount(0)
  expect(alerts).toEqual([])
})

test('a geolocation field brings its address field, once', async ({ page }) => {
  await openDesigner(page)
  const before = await cards(page).count()

  await addField(page, 'map')
  await expect(cards(page)).toHaveCount(before + 2)
  await expect(cards(page).filter({ hasText: 'Adresse' })).not.toHaveCount(0)
  const withAddress = await cards(page).count()

  await addField(page, 'map')
  await expect(
    cards(page),
    'the second map finds the address already there',
  ).toHaveCount(withAddress + 1)
})

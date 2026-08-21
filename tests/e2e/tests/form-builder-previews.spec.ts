import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { attachConsole, watchConsole } from '../helpers/console'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

/**
 * The form designer's live previews, which are what ticket 14 was really about.
 *
 * Ticket 14 replaced the request-wide asset globals with a registry a render declares into,
 * and `POST /api/forms/preview` is the hardest case it has: a preview arrives by htmx into a
 * page that is already built, so whatever it needs has to arrive with it, once, and has to
 * keep working when a sibling preview is thrown away. None of that is visible to phpunit or
 * to eslint, so the ticket carried a manual checklist instead. This is that checklist.
 */

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
  // `.leaflet-container` is on the div before leaflet touches it; the panes only exist once
  // leaflet has run, which is the difference between a preview and a grey box.
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

  // The registry deduplicates by resolved URL, so the second preview declares the same
  // assets and the page ends up with one of each. Loading leaflet twice would re-enter its
  // module and is how the old accumulate-into-a-global mechanism failed.
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

  // The card added first, which is the one after the locked title field.
  await cards(page).nth(1).locator('[data-fb-action="delete"]').click()

  await expect(page.locator('.leaflet-container')).toHaveCount(1)
  const survivor = page.locator('.leaflet-container')
  await expect(survivor.locator('.leaflet-pane')).not.toHaveCount(0)
  // Removing a preview must not take the stylesheet its sibling is still using with it.
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

  // Conditional display is logic rather than an input, so it draws nothing in a form and
  // nothing is the right preview. What matters is that the card resolves and says why.
  await addField(page, 'conditionschecking')
  await previewsSettled(page)

  await expect(page.locator('.yw-fb__card-nopreview').first()).toBeVisible()

  await attachConsole(watcher, testInfo)
  expect(watcher.errors()).toEqual([])
})

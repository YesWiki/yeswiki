import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async() => {
  resetEnv()
})

/**
 * The Personnalisation screen (ticket 30).
 *
 * Three of the things this screen does write outside the database -- `favorite_preset` into
 * the configuration file, and stylesheets into custom/css-presets/ -- so PresetServiceTest
 * deliberately does not exercise them against the working tree, and this is where they are
 * covered. It is also the only place that can see the two things that matter most: that
 * trying a preset on really does **not** change the wiki, and that making one the default
 * really does reach the head of an ordinary page.
 */

/** The card for a preset, found by the id it carries. */
function card(page: Page, id: string) {
  return page.locator(`[data-yw-preset-card="${id}"]`)
}

/**
 * Press one of a card's buttons.
 *
 * They live on the card's one line and are hidden until it is under the pointer, so every
 * one of them is reached through a hover -- Playwright refuses to click what is not visible.
 * The filled star of the preset in use is the exception and needs no hover, but hovering
 * first costs nothing and keeps every call here the same shape.
 */
async function clickTool(page: Page, id: string, tool: string) {
  const target = card(page, id)
  await target.hover()
  await target.locator(tool).click()
}

const STAR = '.yw-preset-card__star'
const COPY = '[data-yw-preset-duplicate-form] button'
const DELETE = '[data-yw-preset-delete-form] button'

/** What an ordinary page is wearing, as seen from the browser. */
async function primaryColourOfHomePage(page: Page) {
  await page.goto('/?PagePrincipale')
  return page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim())
}

test('trying a preset on changes this page and leaves the wiki alone', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const before = await primaryColourOfHomePage(page)

  await page.goto('/?admin/preset')

  // Every preset the theme ships is listed, each as its colours, plus the way back to
  // none. Not a count of the cards: an instance can carry presets of its own, and this
  // suite runs against a container that has one.
  for (const preset of ['default.css', 'fun.css', 'landes.css', 'red.css', 'yellow.css']) {
    await expect(card(page, preset)).toBeVisible()
  }
  await expect(card(page, '')).toBeVisible()
  await expect(card(page, 'fun.css').locator('.yw-preset-card__swatch')).toHaveCount(6)

  // clicking the card wears it here...
  await card(page, 'fun.css').locator('[data-yw-preset-try]').click()
  await expect(card(page, 'fun.css')).toHaveClass(/yw-preset-card--trying/)
  await expect.poll(async() => page.evaluate(
    () => getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim()
  )).not.toBe(before)

  // ...and nowhere else. This is the whole distinction the screen is built on.
  expect(await primaryColourOfHomePage(page)).toBe(before)
})

test('the starred button makes a preset the wiki default', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  await clickTool(page, 'fun.css', STAR)
  await expect(card(page, 'fun.css')).toHaveClass(/yw-preset-card--default/)
  // the star of the preset in use is the filled one, and it is the only one
  await expect(card(page, 'fun.css').locator('.yw-preset-card__star--on')).toHaveCount(1)
  await expect(page.locator('.yw-preset-card__star--on')).toHaveCount(1)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="presets/fun.css"]')).toHaveCount(1)

  // the star is a toggle: clicking the filled one takes the preset off, which is the theme's
  // own colours -- and that is what the "no preset" card then wears the filled star for
  await page.goto('/?admin/preset')
  await clickTool(page, 'fun.css', STAR)
  await expect(card(page, '')).toHaveClass(/yw-preset-card--default/)
  await expect(card(page, '').locator('.yw-preset-card__star--on')).toHaveCount(1)
  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="presets/fun.css"]')).toHaveCount(0)

  // the other way back: the "no preset" card's own star, while a preset is in use
  await page.goto('/?admin/preset')
  await clickTool(page, 'fun.css', STAR)
  await expect(card(page, 'fun.css')).toHaveClass(/yw-preset-card--default/)
  await clickTool(page, '', STAR)
  await expect(card(page, '')).toHaveClass(/yw-preset-card--default/)
  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="presets/fun.css"]')).toHaveCount(0)
})

test('a theme preset cannot be edited, only copied into the wiki', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  page.on('dialog', (dialog) => dialog.accept())
  await page.goto('/?admin/preset')

  // themes/ is code: there is no pencil on a preset that lives there
  await expect(card(page, 'red.css').locator('[data-yw-preset-edit]')).toHaveCount(0)

  // the copy button is the way in
  await clickTool(page, 'red.css', COPY)
  await expect(card(page, 'custom/red.css')).toBeVisible()
  await expect(card(page, 'custom/red.css').locator('[data-yw-preset-edit]')).toHaveCount(1)

  // copying twice gives two presets rather than overwriting the first
  await clickTool(page, 'red.css', COPY)
  await expect(card(page, 'custom/red-2.css')).toBeVisible()

  // copying does not change what the wiki wears
  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="red"]')).toHaveCount(0)

  // clean up: this suite's container keeps custom/css-presets between runs
  await page.goto('/?admin/preset')
  for (const id of ['custom/red.css', 'custom/red-2.css']) {
    await clickTool(page, id, DELETE)
    await expect(card(page, id)).toHaveCount(0)
  }
})

test('editing a preset replaces it, renaming included', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  page.on('dialog', (dialog) => dialog.accept())
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  await expect(rail).toBeHidden()

  // start from a preset of the wiki's own
  await clickTool(page, 'yellow.css', COPY)
  await expect(card(page, 'custom/yellow.css')).toBeVisible()

  await clickTool(page, 'custom/yellow.css', '[data-yw-preset-edit]')
  await expect(rail).toBeVisible()
  await expect(rail.locator('#yw-preset-name')).toHaveValue('yellow')

  // live preview: the variable is written onto the document, so the gallery below repaints
  const primary = rail.locator('[data-yw-preset-field="primary-color"]')
  await primary.fill('#010203')
  await expect.poll(async() => page.evaluate(
    () => getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim()
  )).toBe('#010203')

  // renamed on save -- and that must RENAME it, not leave a second copy behind
  await rail.locator('#yw-preset-name').fill('Essai e2e')
  // generous: saving installs the preset's webfonts locally, which is a network round trip
  await rail.locator('button[type="submit"]').click()
  await expect(card(page, 'custom/essai-e2e.css')).toBeVisible({timeout: 30000})
  await expect(card(page, 'custom/yellow.css')).toHaveCount(0)

  // saving does not make it the wiki's -- that is the starred button and nothing else
  await expect(card(page, 'custom/essai-e2e.css')).not.toHaveClass(/yw-preset-card--default/)

  // it really is the edited preset: make it the default and the rule reaches an ordinary page
  await clickTool(page, 'custom/essai-e2e.css', STAR)
  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="custom/css-presets/essai-e2e.css"]')).toHaveCount(1)
  await expect.poll(async() => page.evaluate(
    () => getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim()
  )).toBe('#010203')

  // deleting removes the file, so the wiki stops wearing it rather than linking nothing
  await page.goto('/?admin/preset')
  await clickTool(page, 'custom/essai-e2e.css', DELETE)
  await expect(card(page, 'custom/essai-e2e.css')).toHaveCount(0)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="essai-e2e.css"]')).toHaveCount(0)
})

test('the screen is refused to someone who is not an admin', async({page}) => {
  await page.goto('/?admin/preset')
  await expect(page.locator('.yw-preset-cards')).toHaveCount(0)
})

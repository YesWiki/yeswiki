import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

/** The card for a preset, found by the id it carries. */
function card(page: Page, id: string) {
  return page.locator(`[data-yw-preset-card="${id}"]`)
}

/** Press one of a card's buttons. */
async function clickTool(page: Page, id: string, tool: string) {
  const target = card(page, id)
  await target.hover()
  await target.locator(tool).click()
}

const STAR = '.yw-preset-card__star'
const COPY = '[data-yw-preset-duplicate-form] button'
const DELETE = '[data-yw-preset-delete-form] button'

/** The value a Design token resolves to right now, in this document. */
function token(page: Page, name: string) {
  return page.evaluate(
    (property) =>
      getComputedStyle(document.documentElement)
        .getPropertyValue(property)
        .trim(),
    name,
  )
}

/** What an ordinary page is wearing, as seen from the browser. */
async function primaryColourOfHomePage(page: Page) {
  await page.goto('/?PagePrincipale')
  return token(page, '--yw-primary')
}

test('trying a preset on changes this page and leaves the wiki alone', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const before = await primaryColourOfHomePage(page)

  await page.goto('/?admin/preset')

  for (const preset of [
    'default.css',
    'fun.css',
    'landes.css',
    'red.css',
    'yellow.css',
  ]) {
    await expect(card(page, preset)).toBeVisible()
  }
  await expect(card(page, '')).toBeVisible()
  await expect(
    card(page, 'fun.css').locator('.yw-preset-card__swatch'),
  ).toHaveCount(6)

  await expect(page.locator('.yw-item--card')).toHaveCount(6)
  await expect(page.locator('.yw-items--list')).toHaveCount(1)
  await expect(page.locator('.yw-items--table')).toHaveCount(1)

  await card(page, 'fun.css').locator('[data-yw-preset-try]').click()
  await expect(card(page, 'fun.css')).toHaveClass(/yw-preset-card--trying/)
  await expect.poll(async () => token(page, '--yw-primary')).not.toBe(before)

  expect(await primaryColourOfHomePage(page)).toBe(before)
})

test('the starred button makes a preset the wiki default', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  await clickTool(page, 'fun.css', STAR)
  await expect(card(page, 'fun.css')).toHaveClass(/yw-preset-card--default/)
  await expect(
    card(page, 'fun.css').locator('.yw-preset-card__star--on'),
  ).toHaveCount(1)
  await expect(page.locator('.yw-preset-card__star--on')).toHaveCount(1)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="presets/fun.css"]')).toHaveCount(1)

  await page.goto('/?admin/preset')
  await clickTool(page, 'fun.css', STAR)
  await expect(card(page, '')).toHaveClass(/yw-preset-card--default/)
  await expect(card(page, '').locator('.yw-preset-card__star--on')).toHaveCount(
    1,
  )
  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="presets/fun.css"]')).toHaveCount(0)

  await page.goto('/?admin/preset')
  await clickTool(page, 'fun.css', STAR)
  await expect(card(page, 'fun.css')).toHaveClass(/yw-preset-card--default/)
  await clickTool(page, '', STAR)
  await expect(card(page, '')).toHaveClass(/yw-preset-card--default/)
  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="presets/fun.css"]')).toHaveCount(0)
})

test('a theme preset cannot be edited, only copied into the wiki', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  page.on('dialog', (dialog) => dialog.accept())
  await page.goto('/?admin/preset')

  await expect(
    card(page, 'red.css').locator('[data-yw-preset-edit]'),
  ).toHaveCount(0)

  await clickTool(page, 'red.css', COPY)
  await expect(card(page, 'custom/red.css')).toBeVisible()
  await expect(
    card(page, 'custom/red.css').locator('[data-yw-preset-edit]'),
  ).toHaveCount(1)

  await clickTool(page, 'red.css', COPY)
  await expect(card(page, 'custom/red-2.css')).toBeVisible()

  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="red"]')).toHaveCount(0)

  await page.goto('/?admin/preset')
  for (const id of ['custom/red.css', 'custom/red-2.css']) {
    await clickTool(page, id, DELETE)
    await expect(card(page, id)).toHaveCount(0)
  }
})

test('editing a preset replaces it, renaming included', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  page.on('dialog', (dialog) => dialog.accept())
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  const list = rail.locator('[data-yw-preset-screen="list"]')
  const editor = rail.locator('[data-yw-preset-screen="edit"]')
  await expect(rail).toBeVisible()
  await expect(list).toBeVisible()
  await expect(editor).toBeHidden()

  await clickTool(page, 'yellow.css', COPY)
  await expect(card(page, 'custom/yellow.css')).toBeVisible()

  await clickTool(page, 'custom/yellow.css', '[data-yw-preset-edit]')
  await expect(editor).toBeVisible()
  await expect(list).toBeHidden()
  await expect(rail.locator('#yw-preset-name')).toHaveValue('yellow')

  const primary = rail.locator('[data-yw-preset-field="light.yw-primary"]')
  await primary.fill('#010203')
  await expect.poll(async () => token(page, '--yw-primary')).toBe('#010203')

  const roundness = rail.locator(
    '[data-yw-preset-slider="light.yw-radius-scale"]',
  )
  await roundness.fill('3')
  await expect(
    rail.locator('[data-yw-preset-readout="light.yw-radius-scale"]'),
  ).toHaveText('3×')
  await expect(
    rail.locator('[data-yw-preset-field="light.yw-radius-scale"]'),
  ).toHaveValue('3')
  await expect
    .poll(async () =>
      page.evaluate(
        () =>
          getComputedStyle(document.querySelector('.yw-btn--primary'))
            .borderRadius,
      ),
    )
    .not.toBe('0px')

  await rail
    .locator('[data-yw-preset-field="light.yw-success"]')
    .fill('#00ff00')
  await expect
    .poll(async () =>
      page.evaluate(
        () =>
          getComputedStyle(document.querySelector('.yw-alert--success'))
            .backgroundColor,
      ),
    )
    .not.toBe('')

  await rail.locator('#yw-preset-name').fill('Essai e2e')
  await editor.locator('.yw-rail__footer button[type="submit"]').click()
  await expect(card(page, 'custom/essai-e2e.css')).toBeVisible({
    timeout: 30000,
  })
  await expect(card(page, 'custom/yellow.css')).toHaveCount(0)

  await expect(card(page, 'custom/essai-e2e.css')).not.toHaveClass(
    /yw-preset-card--default/,
  )

  await clickTool(page, 'custom/essai-e2e.css', STAR)
  await page.goto('/?PagePrincipale')
  await expect(
    page.locator('link[href*="custom/css-presets/essai-e2e.css"]'),
  ).toHaveCount(1)
  await expect.poll(async () => token(page, '--yw-primary')).toBe('#010203')
  await expect.poll(async () => token(page, '--yw-radius-scale')).toBe('3')

  await page.goto('/?admin/preset')
  await clickTool(page, 'custom/essai-e2e.css', DELETE)
  await expect(card(page, 'custom/essai-e2e.css')).toHaveCount(0)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="essai-e2e.css"]')).toHaveCount(0)
})

test('the drawer goes back to the list, shuts, and comes back', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  const list = rail.locator('[data-yw-preset-screen="list"]')
  const editor = rail.locator('[data-yw-preset-screen="edit"]')
  const openButton = page.locator('[data-yw-preset-open]')

  await expect(openButton).toBeHidden()

  await rail.locator('[data-yw-preset-new]').click()
  await expect(editor).toBeVisible()
  const previewed = await token(page, '--yw-primary')
  await rail
    .locator('[data-yw-preset-field="light.yw-primary"]')
    .fill('#0a0b0c')
  await expect.poll(async () => token(page, '--yw-primary')).toBe('#0a0b0c')

  await rail.locator('[data-yw-preset-back]').click()
  await expect(list).toBeVisible()
  await expect(editor).toBeHidden()
  await expect.poll(async () => token(page, '--yw-primary')).toBe(previewed)

  await rail.locator('[data-yw-preset-close-rail]').click()
  await expect(rail).toBeHidden()
  await expect(openButton).toBeVisible()

  await openButton.click()
  await expect(rail).toBeVisible()
  await expect(list).toBeVisible()
  await expect(editor).toBeHidden()
})

test('a colour is scored against the one it sits on, per scheme', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  await rail.locator('[data-yw-preset-new]').click()

  const badge = rail.locator(
    '[data-yw-preset-contrast="light.yw-ink-on-light"]',
  )
  const ink = rail.locator('[data-yw-preset-field="light.yw-ink-on-light"]')
  const ground = rail.locator('[data-yw-preset-field="light.yw-surface"]')

  await ground.fill('#ffffff')
  await ink.fill('#000000')
  await expect(badge).toHaveText('21.0 AAA')
  await expect(badge).toHaveAttribute('data-grade', 'AAA')

  await ink.fill('#767676')
  await expect(badge).toHaveText('4.5 AA')

  await ink.fill('#ffffff')
  await expect(badge).toHaveAttribute('data-grade', 'fail')

  await ground.fill('#000000')
  await expect(badge).toHaveText('21.0 AAA')

  await expect(rail.locator('[data-scheme="light"]').first()).toBeVisible()
  await expect(rail.locator('[data-scheme="dark"]').first()).toBeHidden()

  await page.evaluate(() => {
    document.documentElement.dataset.theme = 'dark'
  })
  await expect(rail.locator('[data-scheme="dark"]').first()).toBeVisible()
  await expect(rail.locator('[data-scheme="light"]').first()).toBeHidden()

  await expect.poll(async () => token(page, '--yw-surface')).not.toBe('#000000')

  await rail
    .locator('[data-yw-preset-field="light.yw-ink-on-dark"]')
    .fill('#111111')
  await rail.locator('[data-yw-preset-field="dark.yw-surface"]').fill('#000000')
  await expect(
    rail.locator('[data-yw-preset-contrast="light.yw-ink-on-dark"]'),
  ).toHaveAttribute('data-grade', 'fail')

  await page.evaluate(() => {
    document.documentElement.dataset.theme = 'light'
  })
  await expect(badge).toHaveText('21.0 AAA')
})

test('a colour can be pointed at another, and follows it', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  await rail.locator('[data-yw-preset-new]').click()

  const heading = rail.locator('[data-yw-preset-field="light.yw-heading-1"]')
  const brand = rail.locator('[data-yw-preset-field="light.yw-primary"]')
  const swatch = rail.locator('[data-yw-preset-picker="light.yw-heading-1"]')

  await brand.fill('#123456')
  await rail
    .locator('[data-yw-preset-palette-open="light.yw-heading-1"]')
    .click()

  await rail.locator('[data-yw-preset-palette-open="light.yw-primary"]').click()
  await expect(
    page.locator('[data-yw-preset-palette-pick="yw-primary"]'),
  ).toBeHidden()

  await rail
    .locator('[data-yw-preset-palette-open="light.yw-heading-1"]')
    .click()
  await page.locator('[data-yw-preset-palette-pick="yw-primary"]').click()
  await expect(heading).toHaveValue('var(--yw-primary)')

  await expect.poll(async () => token(page, '--yw-heading-1')).toBe('#123456')

  await brand.fill('#cc0088')
  await expect.poll(async () => token(page, '--yw-heading-1')).toBe('#cc0088')
  await expect(swatch).toHaveValue('#cc0088')
  await expect
    .poll(async () =>
      page.evaluate(
        () =>
          getComputedStyle(document.querySelector('.yw-preset-preview h1'))
            .color,
      ),
    )
    .toBe('rgb(204, 0, 136)')

  await rail
    .locator('[data-yw-preset-palette-open="light.yw-heading-1"]')
    .click()
  await page.locator('[data-yw-preset-palette-pick=""]').click()
  await expect(heading).toHaveValue('#cc0088')
  await brand.fill('#00aa44')
  await expect(heading).toHaveValue('#cc0088')
})

test('the screen is refused to someone who is not an admin', async ({
  page,
}) => {
  await page.goto('/?admin/preset')
  await expect(page.locator('.yw-preset-cards')).toHaveCount(0)
})

/** Has the browser actually got this typeface -- rules AND bytes? */
async function loadedFace(page: Page, family: string) {
  return page.evaluate(async (name) => {
    await document.fonts.load(`16px '${name}'`)
    return [...document.fonts].some(
      (face) =>
        face.family.replace(/['"]/g, '') === name && face.status === 'loaded',
    )
  }, family)
}

/** Downloading a webfont must not cost the preset you were designing. */
test('adding a webfont keeps the preset being edited', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  await rail.locator('[data-yw-preset-new]').click()

  await rail.locator('#yw-preset-name').fill('EditsThatMustSurvive')
  await rail
    .locator('[data-yw-preset-field="light.yw-primary"]')
    .fill('#ff00aa')
  await expect.poll(async () => token(page, '--yw-primary')).toBe('#ff00aa')

  await rail.locator('[data-yw-modal-target="#yw-preset-font-modal"]').click()
  const modal = page.locator('#yw-preset-font-modal')
  await expect(modal).toHaveClass(/yw-modal--open/)

  await modal.locator('#yw-preset-font-family').fill('Lobster')
  await modal.locator('[data-yw-tag-input-suggestion]').first().click()
  await expect(modal.locator('[name="font_family"]')).toHaveValue('Lobster')

  await modal.locator('button[type="submit"]').click()
  await expect(modal.locator('[data-yw-preset-font-result]')).toContainText(
    'Lobster',
    { timeout: 30000 },
  )

  await expect(rail.locator('#yw-preset-name')).toHaveValue(
    'EditsThatMustSurvive',
  )
  await expect(
    rail.locator('[data-yw-preset-field="light.yw-primary"]'),
  ).toHaveValue('#ff00aa')
  expect(await token(page, '--yw-primary')).toBe('#ff00aa')

  const body = rail.locator('[data-yw-preset-field="light.yw-font-body"]')
  await expect(body.locator('option', { hasText: 'Lobster' })).toHaveCount(1)

  await body.selectOption({ label: 'Lobster' })
  await expect
    .poll(async () => loadedFace(page, 'Lobster'), { timeout: 20000 })
    .toBe(true)

  await body.selectOption({ label: 'Playfair Display' })
  await expect
    .poll(async () => loadedFace(page, 'Playfair Display'), { timeout: 20000 })
    .toBe(true)
})

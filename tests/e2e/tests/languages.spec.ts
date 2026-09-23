import { expect, test } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

const CONFIG = '/?admin/config'
const OTHERS = 'input[name="other_languages[]"]'

/** Open the screen's `core` group. */
async function openConfig(page: import('@playwright/test').Page) {
  await page.goto(CONFIG)
  await page.locator('#head_core').click()
  await expect(page.locator(OTHERS).first()).toBeVisible()
}

/** Save the configuration form, and wait for the screen it redirects to. */
async function save(page: import('@playwright/test').Page) {
  await page.locator('#edit-config button[type="submit"]').click()
  await page.waitForURL(/saved_config/)
}

test('the languages a wiki offers are a choice, not a typed value', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto(CONFIG)

  const main = page.locator('select[name="default_language"]')
  await expect(main).toHaveValue('fr')
  expect(
    await main.locator('option').count(),
    'every installed language, and auto',
  ).toBeGreaterThan(2)

  await expect(page.locator(OTHERS)).not.toHaveCount(0)
  await expect(page.locator(`${OTHERS}:checked`)).toHaveCount(2)
  await expect(page.locator(`${OTHERS}[value="en"]`)).toBeChecked()
  await expect(page.locator(`${OTHERS}[value="es"]`)).toBeChecked()
})

test('choosing a main language takes it out of the others, before anything is saved', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openConfig(page)

  const main = page.locator('select[name="default_language"]')
  const option = (code: string) =>
    page.locator(`[data-yw-other-languages] [data-language="${code}"]`)

  await expect(option('fr')).toBeHidden()
  await expect(option('en')).toBeVisible()
  await expect(page.locator(`${OTHERS}[value="en"]`)).toBeChecked()

  await main.selectOption('en')
  await expect(option('en')).toBeHidden()
  await expect(page.locator(`${OTHERS}[value="en"]`)).not.toBeChecked()
  await expect(option('fr')).toBeVisible()

  await expect(main.locator('option[value="auto"]')).toHaveCount(0)

  await main.selectOption('fr')
  await expect(option('en')).toBeVisible()
  await expect(option('fr')).toBeHidden()
})

test('turning a language off takes it out of the switcher, and back on puts it back', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('.yw-corner-tools a[hreflang]')).toHaveCount(3)

  await openConfig(page)
  await page.locator(`${OTHERS}[value="es"]`).uncheck()
  await save(page)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('.yw-corner-tools a[hreflang]')).toHaveCount(2)
  await expect(page.locator('.yw-corner-tools a[hreflang="es"]')).toHaveCount(0)
  await page.goto('/?PagePrincipale&lang=es')
  await expect(page.locator('html')).toHaveAttribute('lang', 'fr')

  await openConfig(page)
  await page.locator(`${OTHERS}[value="es"]`).check()
  await save(page)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('.yw-corner-tools a[hreflang]')).toHaveCount(3)
})

test('a wiki in one language shows no switcher at all', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  await openConfig(page)
  for (const value of ['en', 'es']) {
    await page.locator(`${OTHERS}[value="${value}"]`).uncheck()
  }
  await save(page)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('.yw-corner-tools a[hreflang]')).toHaveCount(0)
  await expect(page.locator('.yw-corner-tools .yw-switcher__code')).toHaveCount(
    0,
  )
  await expect(page.locator('[data-yw-scheme-set]')).toHaveCount(3)

  await openConfig(page)
  for (const value of ['en', 'es']) {
    await page.locator(`${OTHERS}[value="${value}"]`).check()
  }
  await save(page)
})

test('the switcher links back to the screen it is on, routed screens included', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  for (const route of ['admin/content', 'admin/menus', 'dashboard/lists']) {
    await page.goto(`/?${route}`)
    const href =
      (await page
        .locator('.yw-corner-tools a[hreflang="en"]')
        .getAttribute('href')) ?? ''

    expect(href, `${route} is not what the switcher links to`).toContain(
      `?${route}&`,
    )
    expect(
      href,
      'the tag already holds the whole address, so the route guessed before routing is repeated',
    ).not.toContain(`${route}/`)
  }
})

test('the switcher keeps the url clean, and does not pile the page address into it', async ({
  page,
}) => {
  await page.goto('/?PagePrincipale')

  const href = async () =>
    (await page
      .locator('.yw-corner-tools a[hreflang="en"]')
      .getAttribute('href')) ?? ''

  expect(await href(), 'no /show, and no page address as a parameter').toMatch(
    /\?PagePrincipale&lang=en$/,
  )

  await page.goto(await href())
  expect(await href(), 'and it stays that way after a switch').toMatch(
    /\?PagePrincipale&lang=en$/,
  )
})

test('the switcher keeps a filtered list filtered', async ({ page }) => {
  await page.goto('/?PagePrincipale&facette=bf_ville%3DLyon')

  const href =
    (await page
      .locator('.yw-corner-tools a[hreflang="en"]')
      .getAttribute('href')) ?? ''

  expect(href).toContain('facette=bf_ville')
  expect(href).toContain('lang=en')
})

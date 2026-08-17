import { expect, test } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

/**
 * Which languages a wiki offers its readers.
 *
 * `default_language` is the wiki's own; `other_languages` is what a reader may switch it to,
 * and its absence means "this wiki is in one language" -- which is the ordinary case and the
 * one where no language switcher appears at all.
 *
 * Both are chosen at install time and on the configuration screen. The installer's half is
 * covered by `tests/e2e/reset.sh`, which seeds this wiki with `en` and `es`: every assertion
 * here about there being three languages is also an assertion that the installer wrote them.
 *
 * This spec puts the wiki back the way it found it, rather than leaving a changed
 * configuration for whatever runs next.
 */

test.beforeEach(async () => {
  resetEnv()
})

const CONFIG = '/?GererConfig'
const OTHERS = 'input[name="other_languages[]"]'

/**
 * Open the screen's `core` group.
 *
 * Every group on this screen is a collapsed panel, so its fields are in the document and not
 * on screen -- which is fine for reading an attribute and not for ticking a box.
 */
async function openConfig(page: import('@playwright/test').Page) {
  await page.goto(CONFIG)
  await page.locator('#head_core').click()
  await expect(page.locator(OTHERS).first()).toBeVisible()
}

/**
 * Save the configuration form, and wait for the screen it redirects to.
 *
 * The URL rather than a load state: saving answers with a redirect carrying `saved_config`,
 * and a `waitForLoadState` resolves against the document that is on its way out -- the next
 * navigation then races the redirect and Playwright aborts one of them.
 */
async function save(page: import('@playwright/test').Page) {
  await page.locator('#edit-config button[type="submit"]').click()
  await page.waitForURL(/saved_config/)
}

test('the languages a wiki offers are a choice, not a typed value', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto(CONFIG)

  // the main language is a select over what YesWiki is translated into here, plus `auto`
  const main = page.locator('select[name="default_language"]')
  await expect(main).toHaveValue('fr')
  expect(
    await main.locator('option').count(),
    'every installed language, and auto',
  ).toBeGreaterThan(2)

  // ...and the others are a list of them, with the wiki's own excluded from the answer
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

  // the wiki is in French, so French is not on offer as an "other" and English is
  await expect(option('fr')).toBeHidden()
  await expect(option('en')).toBeVisible()
  await expect(page.locator(`${OTHERS}[value="en"]`)).toBeChecked()

  // ...and the moment English becomes the main language, it leaves the list -- and stops
  // being ticked, so the form cannot post the same language as both
  await main.selectOption('en')
  await expect(option('en')).toBeHidden()
  await expect(page.locator(`${OTHERS}[value="en"]`)).not.toBeChecked()
  // the one it replaced comes back, which a list rendered as "everything but the main one"
  // could not do
  await expect(option('fr')).toBeVisible()

  // there is no `auto` to choose: a wiki says what language it is written in, and "let the
  // browser decide" is what a first visit already does, ahead of this
  await expect(main.locator('option[value="auto"]')).toHaveCount(0)

  // ...and going back puts the list back
  await main.selectOption('fr')
  await expect(option('en')).toBeVisible()
  await expect(option('fr')).toBeHidden()
})

test('turning a language off takes it out of the switcher, and back on puts it back', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  // three to start with: the wiki's own and the two the installer was given
  await page.goto('/?PagePrincipale')
  await expect(page.locator('.yw-topnav-tools a[hreflang]')).toHaveCount(3)

  await openConfig(page)
  await page.locator(`${OTHERS}[value="es"]`).uncheck()
  await save(page)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('.yw-topnav-tools a[hreflang]')).toHaveCount(2)
  await expect(page.locator('.yw-topnav-tools a[hreflang="es"]')).toHaveCount(0)
  // and the wiki stops answering to a language it no longer offers
  await page.goto('/?PagePrincipale&lang=es')
  await expect(page.locator('html')).toHaveAttribute('lang', 'fr')

  // put it back, so the next spec finds the wiki it expects
  await openConfig(page)
  await page.locator(`${OTHERS}[value="es"]`).check()
  await save(page)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('.yw-topnav-tools a[hreflang]')).toHaveCount(3)
})

test('a wiki in one language shows no switcher at all', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  await openConfig(page)
  for (const value of ['en', 'es']) {
    await page.locator(`${OTHERS}[value="${value}"]`).uncheck()
  }
  await save(page)

  await page.goto('/?PagePrincipale')
  // no options, and no readout of a language nobody can change either
  await expect(page.locator('.yw-topnav-tools a[hreflang]')).toHaveCount(0)
  await expect(page.locator('.yw-topnav-tools .yw-switcher__code')).toHaveCount(
    0,
  )
  // ...while the Colour scheme, which is nobody's to remove, is still there
  await expect(page.locator('[data-yw-scheme-set]')).toHaveCount(3)

  await openConfig(page)
  for (const value of ['en', 'es']) {
    await page.locator(`${OTHERS}[value="${value}"]`).check()
  }
  await save(page)
})

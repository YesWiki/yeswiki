import { expect, test } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

/** A menu is Content with labels in it, so it translates like the rest of the wiki. */

const SCREEN = '/?admin/menus'
const MENU = 'MenuNavigation'

test.beforeEach(async () => {
  resetEnv()
})

test('the menu editor offers the languages, and says when it is translating', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto(`${SCREEN}&menu=${MENU}`)

  await expect(
    page.locator('.yw-corner-tools a[hreflang]'),
    'the reader switch, because nothing is being written yet',
  ).toHaveCount(3)

  await page
    .locator('.yw-corner-tools [data-yw-dropdown-toggle]')
    .last()
    .click()
  const target = page.locator('.yw-corner-tools a[hreflang="en"]')
  await expect(target).toBeVisible()
  await target.click()

  await expect(page).toHaveURL(/menu=MenuNavigation&editlang=en/)
  await expect(
    page.locator('.yw-corner-tools a[hreflang="en"]'),
    'the switch says which translation is open',
  ).toHaveClass(/yw-switcher__option--on/)
  await expect(page.locator('.yw-menu-rows--translating')).toBeVisible()
})

test('a translator types labels and nothing else', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto(`${SCREEN}&menu=${MENU}&editlang=en`)

  const rows = page.locator('.yw-menu-row')
  await expect(rows.first()).toBeVisible()

  await expect(
    page.locator('.yw-menu-row input[name*="[link]"]'),
    'a link is not a translation',
  ).toHaveCount(0)
  await expect(
    page.locator('[data-yw-menu-add]'),
    'a translator may not add an entry',
  ).toHaveCount(0)
  await expect(
    page.locator('.yw-menu-row [data-yw-menu-remove]'),
    'nor remove one',
  ).toHaveCount(0)

  await expect(
    rows.first().locator('input[name*="[label]"]'),
    'the input would save the source as its own translation',
  ).toHaveValue('')
  await expect(
    rows.first().locator('.yw-translate-from__text'),
    'the translator is not shown what they are retyping',
  ).not.toBeEmpty()
})

test('what a translator saves reaches a reader of that language, and leaves the source alone', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto(`${SCREEN}&menu=${MENU}&editlang=en`)

  const first = page.locator('.yw-menu-row input[name*="[label]"]').first()
  const source = await page
    .locator('.yw-menu-row .yw-translate-from__text')
    .first()
    .innerText()
  await first.fill('Translated entry')
  await page.locator('button[type="submit"]').click()

  await expect(page).toHaveURL(/editlang=en/)
  await expect(
    page.locator('.yw-menu-row input[name*="[label]"]').first(),
    'the translation did not come back',
  ).toHaveValue('Translated entry')

  await page.goto('/?PagePrincipale&lang=en')
  await expect(page.locator('#yw-topnav')).toContainText('Translated entry')

  await page.goto('/?PagePrincipale&lang=fr')
  await expect(
    page.locator('#yw-topnav'),
    'the source wording was overwritten',
  ).toContainText(source)
})

test('reshaping the menu in its own language keeps the translations', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto(`${SCREEN}&menu=${MENU}&editlang=en`)
  await page.locator('.yw-menu-row input[name*="[label]"]').first().fill('Kept')
  await page.locator('button[type="submit"]').click()

  await page.goto(`${SCREEN}&menu=${MENU}`)
  const label = page.locator('.yw-menu-row input[name*="[label]"]').first()
  await label.fill((await label.inputValue()) + ' (revu)')
  await page.locator('button[type="submit"]').click()

  await page.goto(`${SCREEN}&menu=${MENU}&editlang=en`)
  await expect(
    page.locator('.yw-menu-row input[name*="[label]"]').first(),
    'saving the structure rebuilt the body and dropped the translations',
  ).toHaveValue('Kept')
})

test('the translations screen counts menus and links to their editor', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/translations&group=menu')

  const cell = page.locator('.yw-translations__state[href*="editlang=en"]')
  await expect(cell.first()).toHaveAttribute('href', /admin\/menus/)
  await cell.first().click()

  await expect(page.locator('.yw-menu-rows--translating')).toBeVisible()
})

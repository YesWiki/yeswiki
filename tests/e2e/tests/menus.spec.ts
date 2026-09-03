import { expect, test } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

/** Menus are Content, and one editor edits them wherever they are edited (ticket 64). */

test('the menus screen lists the wiki menus and edits one', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/menus')

  await expect(page.locator('.yw-dashboard__sidebar')).toContainText('Menus')
  await expect(page.locator('.yw-table')).toContainText('MenuNavigation')

  await page.goto('/?admin/menus&menu=MenuNavigation')
  const rows = page.locator('[data-yw-menu-rows="entries"] [data-yw-menu-row]')
  await expect(rows.first()).toBeVisible()

  const before = await rows.count()
  await page.locator('[data-yw-menu-add="entries"]').click()
  await expect(rows).toHaveCount(before + 1)

  await rows.last().locator('.yw-menu-row__label').fill('Le forum')
  await rows.last().locator('.yw-menu-row__link').fill('https://forum.example')
  await page.locator('button[type="submit"]').first().click()

  await page.goto('/?PagePrincipale')
  await expect(
    page.locator('.yw-topnav a[href="https://forum.example"]'),
  ).toBeVisible()
})

/** A page draws a menu by name, and the same renderer draws it. */
test('a nav call names a menu and the page draws it', async ({ page }) => {
  await page.goto('/?GererSite')

  const nav = page.locator('nav.yw-menu')
  await expect(nav).toBeVisible()
  await expect(nav).toContainText('Gestion du site')
  await expect(nav.locator('.active-link')).toContainText('Gestion du site')
})

/** Every entry carries its own icon, so a glyph works in a page as well as in the bar. */
test('the quick access bar draws the icons its menu carries', async ({
  page,
}) => {
  await page.goto('/?PagePrincipale')

  const quick = page.locator('.yw-topnav-fast-access')
  await expect(quick.locator('svg').first()).toBeVisible()
  await expect(quick.locator('a[href$="?search"]')).toBeVisible()
})

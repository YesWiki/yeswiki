import { expect, test } from '@playwright/test'
import { ADMIN_PASSWORD, ADMIN_USERNAME, logout } from '../helpers/login'

test.describe('the edit bar', () => {
  test.beforeEach(async ({ page }) => {
    await logout(page)
    await page.goto('/?user')
    await page.fill(
      '.yw-account-guest__card .login-form input[name="name"]',
      ADMIN_USERNAME,
    )
    await page.fill(
      '.yw-account-guest__card .login-form input[name="password"]',
      ADMIN_PASSWORD,
    )
    await page.click(
      '.yw-account-guest__card .login-form button[type="submit"]',
    )
  })

  test('the comments dropdown opens, and nothing was reparented out of it', async ({
    page,
  }) => {
    await page.goto('/?PagePrincipale')

    const menu = page.locator('.yw-page-info .dropup > ul.dropdown-menu')
    await expect(
      menu,
      'the menu belongs inside the .dropup the toggle searches',
    ).toHaveCount(1)
    await expect(
      page.locator('.yw-page-info p'),
      'no paragraph here -- see the file comment',
    ).toHaveCount(0)

    await page.locator('.yw-page-info .link-comments').first().click()
    await expect(menu).toBeVisible()
  })

  test('the actions float in the corner and the facts stay at the foot', async ({
    page,
  }) => {
    await page.goto('/?PagePrincipale')

    const cluster = page.locator('.yw-page-actions')
    await expect(cluster.locator('.yw-page-actions__edit')).toBeVisible()
    await expect(cluster.locator('.link-deletepage')).toBeHidden()

    await cluster.hover()
    await expect(cluster.locator('.link-deletepage')).toBeVisible()

    const before = await cluster.boundingBox()
    await page.mouse.wheel(0, 600)
    await page.waitForTimeout(200)
    expect((await cluster.boundingBox())?.y).toBe(before?.y)
  })

  test('editing replaces the reader actions with the editor ones', async ({
    page,
  }) => {
    await page.goto('/?PagePrincipale/edit')

    const cluster = page.locator('.yw-page-actions--editing')
    await expect(cluster).toHaveCount(1)
    await expect(cluster.locator('button[value="Sauver"]')).toBeVisible()
    await expect(page.locator('.yw-page-actions .link-edit')).toHaveCount(0)

    await cluster.hover()
    await expect(cluster.locator('.link-cancel')).toBeVisible()
    await expect(cluster.locator('.link-cancel')).toHaveAttribute('href', /./)
  })
})

import { expect, test } from '@playwright/test'
import { attachConsole, watchConsole } from '../helpers/console'
import { ADMIN_PASSWORD, ADMIN_USERNAME, logout } from '../helpers/login'

const SIGN_IN_FORM = '.yw-account-guest__card .login-form'
const RAIL_SECTION = '.yw-dashboard__section'

test.describe('the account routes', () => {
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('a signed-out visitor gets the sign-in form, not a refusal', async ({
    page,
  }) => {
    for (const route of [
      'user',
      'user/pages',
      'user/entries',
      'user/reactions',
    ]) {
      await page.goto(`/?${route}`)
      await expect(
        page.locator(SIGN_IN_FORM),
        `/?${route} must offer the sign-in form`,
      ).toBeVisible()
    }

    await expect(page.locator('.yw-dashboard__sidebar')).toHaveCount(0)
    await expect(page.locator('.yw-account-guest__card')).toBeVisible()
  })

  test('signing up and recovering a password are their own screens', async ({
    page,
  }) => {
    await page.goto('/?user/signup')
    await expect(
      page.locator('input[name="usersettings_action"][value="signup"]'),
    ).toHaveCount(1)
    await expect(page.locator(SIGN_IN_FORM)).toHaveCount(0)

    await page.goto('/?user/lost-password')
    await expect(page.locator(SIGN_IN_FORM)).toHaveCount(0)
  })

  test('signing in from /user lands on the account, not on an error', async ({
    page,
  }, testInfo) => {
    const watcher = watchConsole(page)

    await page.goto('/?user')
    await page.fill(`${SIGN_IN_FORM} input[name="name"]`, ADMIN_USERNAME)
    await page.fill(`${SIGN_IN_FORM} input[name="password"]`, ADMIN_PASSWORD)

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.request().method() === 'POST' && r.url().includes('?user'),
      ),
      page.click(`${SIGN_IN_FORM} button[type="submit"]`),
    ])
    expect(response.status(), 'the sign-in POST must redirect, not fail').toBe(
      302,
    )

    await expect(
      page.locator(SIGN_IN_FORM),
      'signing in must end the sign-in form',
    ).toHaveCount(0)
    await expect(page.locator(RAIL_SECTION)).toHaveCount(1)
    await expect(
      page.locator('.yw-dashboard__sidebar a[href*="user/pages"]'),
    ).toHaveCount(1)
    await expect(
      page.locator('.yw-dashboard__sidebar a[href*="admin/"]'),
    ).toHaveCount(0)
    await expect(page.locator('.yw-dashboard__link--current')).toBeVisible()

    await page.goto('/?user/logout')
    await expect(
      page.locator(SIGN_IN_FORM),
      'signing out must bring the form back',
    ).toBeVisible()
    await expect(page.locator('.yw-dashboard__sidebar')).toHaveCount(0)

    await attachConsole(watcher, testInfo)
    expect(watcher.errors(), 'the browser reported errors').toEqual([])
  })

  test('the navbar links to the account instead of opening a modal', async ({
    page,
  }) => {
    await page.goto('/?PagePrincipale')

    await expect(
      page.locator('#LoginModal'),
      'the login modal is gone',
    ).toHaveCount(0)
    await expect(
      page.locator('#yw-topnav a.account-link'),
      'the navbar must offer a way to the account',
    ).toHaveCount(1)
    await expect(page.locator('#yw-topnav input[name="password"]')).toHaveCount(
      0,
    )
  })

  /** The one thing a PHP test cannot show: the round trip. */
  test('signing in from the navbar comes back to the page you were on', async ({
    page,
  }) => {
    await page.goto('/?ReglesDeFormatage')

    const accountLink = page.locator('#yw-topnav a.account-link')
    await expect(accountLink).toHaveAttribute('href', /return=/)
    await accountLink.click()

    await expect(page.locator(SIGN_IN_FORM)).toBeVisible()
    await page.fill(`${SIGN_IN_FORM} input[name="name"]`, ADMIN_USERNAME)
    await page.fill(`${SIGN_IN_FORM} input[name="password"]`, ADMIN_PASSWORD)
    await page.click(`${SIGN_IN_FORM} button[type="submit"]`)

    await expect(page).toHaveURL(/ReglesDeFormatage$/)
    await expect(
      page.locator('#yw-topnav a.account-link .yw-avatar'),
    ).toBeVisible()
    await expect(
      page.locator('#yw-topnav a.account-link'),
      'signed in, the button goes straight to the account',
    ).toHaveAttribute('href', /\?user$/)
  })
})

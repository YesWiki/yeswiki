import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'

test.beforeEach(async () => {
  resetEnv()
})

/** A boosted click whose request fails says so, in a toast and to screen readers, and leaves the page where it was (end-users 03). */
test('a boosted link that answers 500 shows a toast and keeps the URL', async ({
  page,
}) => {
  await page.goto('/?PagePrincipale')
  const before = page.url()
  await page.route(/BacASable/, (route) =>
    route.fulfill({
      status: 500,
      contentType: 'application/json',
      body: JSON.stringify({
        error: 'Une erreur est survenue. Référence : a1b2c3d4.',
        incident: 'a1b2c3d4',
      }),
    }),
  )

  await page.locator('a[href*="BacASable"]').first().click()

  await expect(page.locator('.toast-message')).toContainText('a1b2c3d4')
  await expect(page.locator('#yw-navigation-announcer')).toContainText(
    'a1b2c3d4',
  )
  expect(page.url()).toBe(before)
})

test('a boosted link with the network cut shows the offline message', async ({
  page,
}) => {
  await page.goto('/?PagePrincipale')
  const before = page.url()
  await page.route(/BacASable/, (route) => route.abort('internetdisconnected'))

  await page.locator('a[href*="BacASable"]').first().click()

  await expect(page.locator('.toast-message')).toContainText('connexion')
  await expect(page.locator('#yw-navigation-announcer')).toContainText(
    'connexion',
  )
  expect(page.url()).toBe(before)
})

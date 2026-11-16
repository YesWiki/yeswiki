// Double-click opens the editor on a desktop, and a pencil does it on a touch screen (end-users 27).
import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

test('a double-click on the page opens its editor, and none is written inline', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale')

  expect(await page.locator('[ondblclick]').count()).toBe(0)
  await expect(page.locator('#yw-main .yw-edit-zone__button')).toBeHidden()

  await page.locator('#yw-main h1, #yw-main p').first().dblclick()
  await page.waitForURL(/PagePrincipale\/edit/)
})

test.describe('on a touch screen', () => {
  test.use({
    hasTouch: true,
    isMobile: true,
    viewport: { width: 390, height: 844 },
  })

  test('a writer gets a pencil that opens the editor', async ({ page }) => {
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
    await page.goto('/?PagePrincipale')
    const pencil = page.locator('#yw-main > .yw-edit-zone__button')
    await expect(pencil).toBeVisible()
    await expect(pencil).toHaveAttribute(
      'aria-label',
      'Modifier PagePrincipale',
    )
    await pencil.tap()
    await page.waitForURL(/PagePrincipale\/edit/)
  })
})

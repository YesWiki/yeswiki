// A pipette beside a colour input picks a colour from the screen, where the browser has EyeDropper (end-users 36).
import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

const stubEyeDropper = (page: Page) =>
  page.addInitScript(() => {
    window['EyeDropper'] = class {
      open() {
        return Promise.resolve({ sRGBHex: '#123456' })
      }
    }
  })

test('the preset editor takes the picked colour in its picker and its text field', async ({
  page,
}) => {
  await stubEyeDropper(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')
  await page.locator('[data-yw-preset-new]').click()

  const field = page.locator('[data-yw-preset-field="light.yw-primary"]')
  await page
    .locator(
      '[data-yw-eyedropper=\'[data-yw-preset-picker="light.yw-primary"]\']',
    )
    .click()
  await expect(field).toHaveValue('#123456')
  await expect(
    page.locator('[data-yw-preset-picker="light.yw-primary"]'),
  ).toHaveValue('#123456')
})

test('without EyeDropper no pipette is shown', async ({ page }) => {
  await page.addInitScript(() => {
    delete window['EyeDropper']
  })
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')
  await page.locator('[data-yw-preset-new]').click()

  await expect(page.locator('[data-yw-eyedropper]').first()).toBeHidden()
})

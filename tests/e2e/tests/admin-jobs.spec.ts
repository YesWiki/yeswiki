import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

/** The htmx wiring between the page, its tabs and the API is only exercised in a browser; the logic is PHPUnit's (JobsControllerTest). */
test('the jobs page shows the mode and swaps its tabs without leaving the page', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/jobs')

  await expect(page.locator('#yw-jobs-status .yw-jobs__mode')).toBeVisible()
  await expect(page.locator('#yw-jobs-tabs [role="tab"][aria-selected="true"]')).toHaveCount(1)

  const failed = page.locator('#yw-jobs-tabs [role="tab"]').nth(1)
  const swapped = page.waitForResponse((response) =>
    response.url().includes('api/admin/jobs/tab/failed'),
  )
  await failed.click()
  expect((await swapped).ok()).toBeTruthy()

  await expect(
    page.locator('#yw-jobs-tabs [role="tab"][aria-selected="true"]'),
  ).toHaveAttribute('hx-get', /tab\/failed/)
  await expect(page).toHaveURL(/admin\/jobs/)
})

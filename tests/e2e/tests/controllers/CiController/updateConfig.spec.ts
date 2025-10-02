import { expect, test } from '@playwright/test'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../../../helpers/login'

const TARGET = '?api/ci/update_config'
test('Access should no be granted to anonymous', async({ page }) => {
  const res = await page.request.post(TARGET, { data: {} })
  expect(res.status()).toBe(401)
  expect(await res.body()).toBe(401)
})

// TODO: restore test
// test('Admins should modify config', async({ page }) => {
//   await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
//   const res = await page.request.post(TARGET, { data: { wakka_name: 'New site name' } })
//   expect(res.status()).toBe(200)
//   await page.goto('/')
//   await expect(page.locator('#yw-topnav .navbar-header .navbar-brand')).toContainText('New site name')
// })

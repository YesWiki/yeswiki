import { expect, test } from '@playwright/test'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../../../helpers/login'

const TARGET = '?api/ci/update_config'
test('Access should no be granted to anonymous', async({ page }) => {
  const res = await page.request.post(TARGET, { data: {} })
  expect(res.status()).toBe(401)
})

test('Admins should modify config', async({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  // `wakka_name` became `yeswiki_name` in this rewrite; this endpoint writes whatever
  // key it is given verbatim, so the old name wrote a key nothing reads
  const res = await page.request.post(TARGET, { data: { yeswiki_name: 'New site name' } })
  expect(res.status()).toBe(200)
  await page.goto('/')
  // the site name shows in the document title; the navbar brand includes the PageTitre
  // page, so it never reflected this setting
  await expect(page).toHaveTitle(/New site name/)
})

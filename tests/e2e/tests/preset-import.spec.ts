// A preset is downloaded from its card and imported into another wiki from the list (end-users 26).
import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

const importCss = async (page: Page, name: string, css: string) => {
  await page.goto('/?admin/preset')
  const form = page.locator('form.yw-preset-import')
  await form
    .locator('input[type="file"]')
    .setInputFiles({ name, mimeType: 'text/css', buffer: Buffer.from(css) })
  await form.locator('button[type="submit"]').click()
  await page.waitForLoadState()
}

const downloadedPreset = async (page: Page): Promise<string> => {
  await page.goto('/?admin/preset')
  const link = page.locator('.yw-preset-card a[download]').first()
  const href = (await link.getAttribute('href')) || ''
  const res = await page.request.get(href)
  expect(res.ok()).toBeTruthy()

  return res.text()
}

test('a downloaded preset imports under its own name, then under a free one', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const css = await downloadedPreset(page)

  const name = `ocean${Date.now()}`
  await importCss(page, `${name}.css`, css)
  await expect(page.locator('body')).toContainText(`« ${name} » a été importé`)
  await importCss(page, `${name}.css`, css)
  await expect(page.locator('body')).toContainText(
    `« ${name}-2 » a été importé`,
  )
})

test('an incomplete preset or one carrying an address is refused', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const css = await downloadedPreset(page)

  await importCss(page, 'partiel.css', ':root { --yw-primary: #123456; }')
  await expect(page.locator('body')).toContainText('n’a pas pu être enregistré')
  await expect(page.locator('body')).toContainText('yw-surface')

  await importCss(
    page,
    'adresse.css',
    css.replace(
      /--yw-primary:[^;]*;/,
      '--yw-primary: url(https://example.org/x.png);',
    ),
  )
  await expect(page.locator('body')).toContainText('yw-primary')
  await expect(page.locator('body')).toContainText(
    'une adresse ou une règle CSS',
  )
})

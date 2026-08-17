import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

/** The wiki's own stylesheet, as a screen (ticket 30). */
test('an admin writes the wiki stylesheet and it reaches an ordinary page', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  await page.goto('/?admin/custom-css')

  const editor = page.locator('.yw-custom-css__ace')
  await expect(editor).toBeVisible()
  const box = page.locator('#custom_css')
  await expect(box).toBeHidden()

  await page.evaluate(() => {
    window['ace']
      .edit(document.querySelector('.yw-custom-css__ace'))
      .setValue('')
  })
  await expect(box).toHaveValue('')

  await expect(page.locator('.yw-dashboard__sidebar')).toContainText(
    'Apparence',
  )
  await expect(page.locator('.yw-dashboard__link--current')).toContainText(
    'CSS',
  )

  await page.locator('.yw-custom-css__ace .ace_content').click()
  await page.keyboard.type('.e2e-probe { color: rgb(1, 2, 3); }')
  await expect(box).toHaveValue(/e2e-probe/, { timeout: 5000 })

  await expect(
    page.locator('.yw-custom-css__ace .ace_support.ace_type').first(),
  ).toBeVisible()

  await page.locator('button[type="submit"]').click()

  await expect(page.locator('#custom_css')).toHaveValue(/e2e-probe/)

  await page.goto('/?PagePrincipale')
  const link = page.locator(
    'link[rel="stylesheet"][href*="custom/styles/custom.css"]',
  )
  await expect(link).toHaveCount(1)

  await page.evaluate(() => {
    const probe = document.createElement('div')
    probe.className = 'e2e-probe'
    document.body.appendChild(probe)
  })
  await expect
    .poll(async () =>
      page.locator('.e2e-probe').evaluate((el) => getComputedStyle(el).color),
    )
    .toBe('rgb(1, 2, 3)')

  await page.goto('/?admin/custom-css')
  await page.evaluate(() => {
    const host = document.querySelector('.yw-custom-css__ace')
    window['ace'].edit(host).setValue('')
  })
  await page.locator('button[type="submit"]').click()
  await page.goto('/?PagePrincipale')
  await expect(
    page.locator('link[href*="custom/styles/custom.css"]'),
  ).toHaveCount(0)
})

/** The stylesheet screen, twice over: it has to be dark when the reader is, and it has to still be there after you leave it and come back. */
test('the stylesheet editor is dark when the reader is, and survives leaving the screen', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  await page.goto('/?PagePrincipale')
  await page.locator('.yw-topnav-tools').hover()
  await page.locator('[data-yw-scheme-set="dark"]').click()

  await page.goto('/?admin/custom-css')
  const editor = page.locator('.yw-custom-css__ace')
  await expect(editor).toBeVisible()

  /** A token as the browser resolves it here, in the same units a computed style comes back in. */
  const token = (property: string) =>
    page.evaluate((name) => {
      const probe = document.createElement('span')
      probe.style.color = `var(${name})`
      document.body.appendChild(probe)
      const value = getComputedStyle(probe).color
      probe.remove()
      return value
    }, property)

  await expect
    .poll(() => editor.evaluate((el) => getComputedStyle(el).backgroundColor))
    .toBe(await token('--yw-surface-raised'))

  await page.evaluate(() => {
    window['ace']
      .edit(document.querySelector('.yw-custom-css__ace'))
      .setValue('.probe { color: red; }')
  })
  await expect
    .poll(() =>
      page
        .locator('.yw-custom-css__ace .ace_support.ace_type')
        .first()
        .evaluate((el) => getComputedStyle(el).color),
    )
    .toBe(await token('--yw-primary'))

  await page.locator('.yw-dashboard__sidebar a[href*="admin/layout"]').click()
  await expect(page.locator('#custom_css')).toHaveCount(0)
  await page
    .locator('.yw-dashboard__sidebar a[href*="admin/custom-css"]')
    .click()

  await expect(
    page.locator('.yw-custom-css__ace'),
    'the editor did not come back: the module ran once for the whole document',
  ).toBeVisible()
  await expect(page.locator('#custom_css')).toBeHidden()

  await page.locator('.yw-custom-css__ace .ace_content').click()
  await page.keyboard.type('/* back */')
  await expect(page.locator('#custom_css')).toHaveValue(/back/, {
    timeout: 5000,
  })
})

test('the stylesheet screen is refused to someone who is not an admin', async ({
  page,
}) => {
  await page.goto('/?admin/custom-css')
  await expect(page.locator('#custom_css')).toHaveCount(0)
})

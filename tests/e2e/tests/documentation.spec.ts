import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'

test.beforeEach(async () => {
  resetEnv()
})

/** /doc, the documentation drawn by Docsify and its search and copy-code plugins. */

test('/doc renders the documentation home with its menus', async ({ page }) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))

  await page.goto('/?doc')

  await expect(page).toHaveURL(/#\/docs\/fr\/README/)
  await expect(page.locator('.markdown-section h1')).toHaveText(
    'Documentation YesWiki',
  )
  await expect(
    page.locator('.app-nav a', { hasText: 'Prise en main' }),
  ).toBeAttached()
  await expect(
    page.locator('.sidebar-nav a', { hasText: 'Où suis-je' }),
  ).toBeVisible()
  expect(errors).toEqual([])
})

test('a link in the top menu opens its page', async ({ page }) => {
  await page.goto('/?doc')
  await page
    .locator('.app-nav a', { hasText: 'Formulaires' })
    .dispatchEvent('click')

  await expect(page).toHaveURL(/#\/docs\/fr\/bazar/)
  await expect(page.locator('.markdown-section h1').first()).toBeVisible()
  await expect(page.locator('.markdown-section h1').first()).not.toHaveText(
    'Documentation YesWiki',
  )
})

test('the search box finds pages', async ({ page }) => {
  await page.goto('/?doc')
  await page.locator('.search input').fill('bazar')

  await expect(
    page.locator('.results-panel .matching-post').first(),
  ).toBeVisible()
})

test('a code block gets a French copy button that copies its code', async ({
  page,
  context,
}) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write'])
  await page.goto('/?doc#/docs/fr/dev')

  const block = page.locator('.markdown-section pre').first()
  const button = block.locator('.docsify-copy-code-button')
  await expect(button.locator('.label')).toHaveText('Copier le code')

  await block.hover()
  await button.click()

  await expect(button).toHaveClass(/success/)
  const copied = await page.evaluate(() => navigator.clipboard.readText())
  expect(copied.trim()).toBe(
    (await block.locator('code').textContent())?.trim(),
  )
})

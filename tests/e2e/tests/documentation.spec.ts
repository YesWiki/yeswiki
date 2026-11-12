import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'

test.beforeEach(async () => {
  resetEnv()
})

/** /doc, the documentation's Markdown rendered by the wiki, searched by the wiki, with copy buttons on its code. */

test('/doc renders the documentation home with its menu and contents', async ({
  page,
}) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))

  await page.goto('/?doc')

  await expect(page.locator('.yw-doc__content h1')).toHaveText(
    /^Documentation YesWiki/,
  )
  await expect(
    page.locator('.yw-doc__menu a', { hasText: 'Prise en main' }),
  ).toBeVisible()
  await expect(
    page.locator('.yw-doc__aside .yw-toc a', { hasText: 'Où suis-je' }),
  ).toBeVisible()
  expect(errors).toEqual([])
})

test('a link in the menu opens its page', async ({ page }) => {
  await page.goto('/?doc')
  await page.locator('.yw-doc__menu a', { hasText: 'Formulaires' }).click()

  await expect(page).toHaveURL(/doc\/bazar/)
  await expect(page.locator('.yw-doc__content h1').first()).not.toHaveText(
    /^Documentation YesWiki/,
  )
  await expect(
    page.locator('.yw-doc__menu a.active', { hasText: 'Formulaires' }),
  ).toBeVisible()
})

test('the search box finds pages as you type', async ({ page }) => {
  await page.goto('/?doc')
  await page.locator('.yw-doc__search input').fill('bazar')

  const first = page.locator('.yw-doc__result').first()
  await expect(first).toBeVisible()
  await expect(first.locator('mark').first()).toHaveText(/bazar/i)

  await page.locator('.yw-doc__search input').fill('zzqqxxwordnowhere')
  await expect(page.locator('.yw-doc__no-result')).toBeVisible()
})

test('an old Docsify address opens its page at its heading', async ({
  page,
}) => {
  await page.goto('/?doc#/docs/fr/admin?id=droit-d39accès')

  await expect
    .poll(() => decodeURIComponent(page.url()))
    .toMatch(/doc\/admin#droit-daccès$/)
  await expect(
    page.locator('h2#droit-daccès, h3#droit-daccès, h4#droit-daccès'),
  ).toBeInViewport()
})

test('a code block gets a French copy button that copies its code', async ({
  page,
  context,
}) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write'])
  await page.goto('/?doc/dev')

  const block = page.locator('.yw-doc__content pre').first()
  const button = block.locator('.yw-doc__copy')
  await expect(button).toHaveText('Copier le code')

  await block.hover()
  await button.click()

  await expect(button).toHaveClass(/success/)
  const copied = await page.evaluate(() => navigator.clipboard.readText())
  expect(copied.trim()).toBe(
    (await block.locator('code').textContent())?.trim(),
  )
})

test('a long page folds its contents and opens the section being read', async ({
  page,
}) => {
  const toc = page.locator('.yw-doc__aside .yw-toc')
  const subsections = toc.locator('li.toc3:visible')

  await page.goto('/?doc/admin')
  await expect(page.locator('.yw-toc--foldable.yw-toc--ready')).toBeVisible()
  await expect(subsections).toHaveCount(0)

  await page.goto('/?doc/admin#droit-daccès')
  const current = toc.locator('a[aria-current]')
  await expect(current).toHaveText("Droit d'accès")
  await expect(current).toBeVisible()
  const opened = await subsections.count()
  expect(opened).toBeGreaterThan(0)

  await toc.locator('.yw-toc__toggle[aria-expanded="false"]').first().click()
  await expect.poll(() => subsections.count()).toBeGreaterThan(opened)
})

import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { setPageContent } from '../helpers/page'
import { watchConsole } from '../helpers/console'
import { login, logout, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'
import { seedResources } from '../helpers/bazar'

/** A server-rendered list searches, sorts and pages over htmx, keeping the facets checked. */

const PAGE = 'ListeAvecOutils'

const titles = (page) => page.locator('.yw-item__title').allInnerTexts()

test.beforeEach(async ({ page }) => {
  resetEnv()
  await seedResources(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(
    page,
    PAGE,
    '{{entrylist id="4" template="card" search="true" groups="bf_type" sortfields="bf_titre" sortfieldstitles="Titre"}}',
  )
  await logout(page)
  await page.goto(`/?${PAGE}`)
})

test('typing narrows the list without leaving the page or the search box', async ({
  page,
}) => {
  const watcher = watchConsole(page)
  await expect(page.locator('.yw-item')).toHaveCount(2)
  await page.evaluate(() => {
    ;(window as unknown as { __stillHere: boolean }).__stillHere = true
  })

  const search = page.getByRole('searchbox')
  await search.pressSequentially('frama', { delay: 30 })

  await expect(page.locator('.yw-item')).toHaveCount(1)
  await expect(page.locator('.yw-item__title')).toHaveText('Framasoft')
  await expect(page).toHaveURL(/keywords=frama/)
  await expect(search).toBeFocused()
  await expect(search).toHaveValue('frama')
  expect(
    await page.evaluate(
      () => (window as unknown as { __stillHere?: boolean }).__stillHere,
    ),
  ).toBe(true)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

test('choosing a sort reorders the list and lands in the URL', async ({
  page,
}) => {
  expect(await titles(page)).toEqual(['Framasoft', 'Yeswiki : le site officiel'])

  await page.getByLabel('Trier').selectOption({ label: 'Titre ↓' })

  await expect
    .poll(() => titles(page))
    .toEqual(['Yeswiki : le site officiel', 'Framasoft'])
  await expect(page).toHaveURL(/sort=bf_titre%3Adesc|sort=bf_titre:desc/)
})

test('a facet keeps the search, and the search keeps the facet', async ({
  page,
}) => {
  await page.getByRole('checkbox', { name: /Partenaire/ }).check()
  await expect(page.locator('.yw-item')).toHaveCount(1)
  const kept = await page.locator('.yw-item__title').innerText()

  await page.getByRole('searchbox').fill(kept.slice(0, 5))
  await expect(page).toHaveURL(/keywords=/)
  await expect(page.getByRole('checkbox', { name: /Partenaire/ })).toBeChecked()
  await expect(page.locator('.yw-item')).toHaveCount(1)

  await page.getByRole('searchbox').fill('aucune fiche ne contient ceci')
  await expect(page.locator('.yw-item')).toHaveCount(0)
  await page.getByRole('searchbox').fill('')
  await expect(page.locator('.yw-item')).toHaveCount(1)
  await expect(page).toHaveURL(/facet/)
})

test('the filtered, sorted and searched list survives a reload', async ({
  page,
}) => {
  await page.getByLabel('Trier').selectOption({ label: 'Titre ↓' })
  await page.getByRole('searchbox').fill('yeswiki')
  await expect(page.locator('.yw-item')).toHaveCount(1)
  await expect(page).toHaveURL(/keywords=yeswiki/)

  await page.goto(page.url())
  await expect(page.locator('.yw-item')).toHaveCount(1)
  await expect(page.getByRole('searchbox')).toHaveValue('yeswiki')
  await expect(page.getByLabel('Trier')).toHaveValue('bf_titre:desc')
})

test('the pager swaps the results and keeps the toolbar', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(
    page,
    PAGE,
    '{{entrylist id="4" template="card" search="true" pagination="1" sortfields="bf_titre" sortfieldstitles="Titre"}}',
  )
  await logout(page)
  await page.goto(`/?${PAGE}`)
  await expect(page.locator('.yw-item__title')).toHaveText('Framasoft')

  await page.evaluate(() => {
    ;(window as unknown as { __stillHere: boolean }).__stillHere = true
  })
  await page.locator('.yw-pagination__link').filter({ hasText: '2' }).click()

  await expect(page.locator('.yw-item__title')).toHaveText(
    'Yeswiki : le site officiel',
  )
  await expect(page.getByRole('searchbox')).toBeVisible()
  expect(
    await page.evaluate(
      () => (window as unknown as { __stillHere?: boolean }).__stillHere,
    ),
  ).toBe(true)
})

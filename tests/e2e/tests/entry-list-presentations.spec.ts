import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { setPageContent } from '../helpers/page'
import { watchConsole } from '../helpers/console'
import { login, logout, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'
import { nestListValues, seedResources } from '../helpers/bazar'

/** What the server-rendered presentations took over from the browser-drawn ones: the list that unfolds an entry, and the facets of a hierarchical list. */

test.beforeEach(async ({ page }) => {
  resetEnv()
  await seedResources(page)
})

test('a list in accordion mode loads an entry only when it is opened', async ({
  page,
}) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(
    page,
    'ListeDepliable',
    '{{entrylist id="4" template="list" entrydisplay="accordion"}}',
  )
  await logout(page)

  const fragments: string[] = []
  page.on('request', (request) => {
    if (request.url().includes('/fragment')) fragments.push(request.url())
  })
  await page.goto('/?ListeDepliable')
  const entry = page.locator('details.yw-item').filter({ hasText: 'Framasoft' })
  await expect(entry).toHaveCount(1)
  expect(fragments, 'nothing is fetched before an entry is opened').toEqual([])

  await entry.locator('summary').click()
  await expect(entry.locator('.yw-item__entry')).toContainText('framasoft.org')
  expect(fragments).toHaveLength(1)

  await entry.locator('summary').click()
  await entry.locator('summary').click()
  await expect(entry.locator('.yw-item__entry')).toContainText('framasoft.org')
  expect(fragments, 'an entry opened twice is fetched once').toHaveLength(1)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

test('a hierarchical facet nests its values and a parent keeps its children', async ({
  page,
}) => {
  nestListValues('ListeType', '1', '3')
  await page.goto('/?FacetteRessource&template=card')
  await expect(page.locator('.yw-item')).toHaveCount(2)

  const parent = page.getByRole('checkbox', { name: /Site web/ })
  const child = page.getByRole('checkbox', { name: /Partenaire/ })
  await expect(parent).toBeVisible()
  await expect(child, 'a child waits inside its closed parent').toBeHidden()
  await expect(
    page.locator('.facet-node').filter({ has: parent }).locator('.nb').first(),
  ).toHaveText('2')

  await page.locator('.facet-node__toggle').first().click()
  await child.check()
  await expect(page.locator('.yw-item')).toHaveCount(1)
  await expect(page.locator('.yw-item__title')).toHaveText('Framasoft')
  await expect(child, 'the checked child keeps its parent open').toBeVisible()

  await page.getByRole('checkbox', { name: /Site web/ }).check()
  await expect(page.locator('.yw-item')).toHaveCount(2)
  await expect(
    page.locator('input.filter-checkbox[value="3"]'),
    'checking the parent drops the child, which it already covers',
  ).not.toBeChecked()
  await expect(page).not.toHaveURL(/bf_type%5D%5B%5D=3|bf_type\]\[\]=3/)
})

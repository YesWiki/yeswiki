import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'

test.beforeEach(async() => {
  resetEnv()
})

/**
 * The search surface (ticket 26), in a browser and on MySQL.
 *
 * These cover the two things phpunit structurally cannot: the htmx round trip -- the results
 * fragment is fetched, not rendered with the page, so a broken hx-get looks exactly like an
 * empty result set to a PHP test -- and the MySQL FULLTEXT path, since the suite runs SQLite
 * only. That gap is what let ticket 25's seven defects accumulate behind a green suite.
 */

test('the top bar search button opens /search', async({ page }) => {
  await page.goto('/')

  // PageRapideHaut ships {{button icon="loupe" link="search"}} since ticket 26 -- the old
  // reveal-panel {{searchform}} is gone, and `search` is a routed name, not a page
  await page.getByRole('link', { name: /Rechercher/i }).first().click()

  await expect(page).toHaveURL(/[?/]search/)
  await expect(page.locator('#yw-search-form')).toBeVisible()
})

test('typing a phrase fetches results over htmx', async({ page }) => {
  await page.goto('/?search')

  await expect(page.locator('#yw-search-form')).toBeVisible()

  const search = page.waitForResponse((response) =>
    response.url().includes('api/search') && response.status() === 200)
  await page.locator('#yw-search-phrase').fill('wiki')
  await search

  // the seeded wiki has pages mentioning "wiki"; what is asserted is that the fragment was
  // swapped in at all, which is the htmx contract
  await expect(page.locator('#yw-search-results .yw-search-result').first()).toBeVisible()
})

test('a phrase matching nothing says so', async({ page }) => {
  await page.goto('/?search')

  const search = page.waitForResponse((response) => response.url().includes('api/search'))
  await page.locator('#yw-search-phrase').fill('zzzzriennezcorrespondzzzz')
  await search

  await expect(page.locator('#yw-search-results')).toContainText(/aucun/i)
  await expect(page.locator('#yw-search-results .yw-search-result')).toHaveCount(0)
})

test('the content type filter narrows results', async({ page }) => {
  await page.goto('/?search')

  const results = page.locator('#yw-search-results .yw-search-result')

  // waitForResponse is not enough on its own here: the container also fires an hx-get on
  // `load`, so a waiter registered afterwards can resolve on THAT response and the count
  // below would read the container before the debounced search has swapped in. Every step
  // therefore ends on an auto-retrying assertion; count() is a snapshot and does not retry.
  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(results.first()).toBeVisible()
  const all = await results.count()
  expect(all).toBeGreaterThan(0)

  await page.locator('#yw-search-form select[name="type"]').selectOption('form')

  // "no result is anything but a form" as a single auto-retrying assertion, which is also
  // the signal that the filtered response has swapped in. Checking the first result instead
  // would pass on the UNFILTERED list whenever a form happened to sort first.
  await expect(page.locator('#yw-search-results .yw-search-result:not(.yw-search-result--form)')).toHaveCount(0)
  await expect(results.first()).toBeVisible()

  // and the filter narrows rather than re-querying something unrelated
  expect(await results.count()).toBeLessThanOrEqual(all)
})

test('the retired search actions are gone from the seeded wiki', async({ page }) => {
  await page.goto('/?PageRapideHaut')

  const body = await page.locator('body').innerText()
  expect(body).not.toContain('newtextsearch')
  expect(body).not.toContain('searchform')
})

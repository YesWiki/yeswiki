import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'

test.beforeEach(async () => {
  resetEnv()
})

/** The search surface (ticket 26), in a browser and on MySQL. */

test('the top bar search button opens /search', async ({ page }) => {
  await page.goto('/')

  await page
    .getByRole('link', { name: /Rechercher/i })
    .first()
    .click()

  await expect(page).toHaveURL(/[?/]search/)
  await expect(page.locator('#yw-search-form')).toBeVisible()
})

test('the search box takes focus on arrival, however you arrive', async ({
  page,
}) => {
  await page.goto('/?search')
  await expect(page.locator('#yw-search-phrase')).toBeFocused()

  await page.goto('/')
  await page
    .getByRole('link', { name: /Rechercher/i })
    .first()
    .click()
  await expect(page.locator('#yw-search-phrase')).toBeFocused()
})

test('the loading indicator does not move the results', async ({ page }) => {
  await page.goto('/?search')
  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(page.locator('.yw-search-result').first()).toBeVisible()

  const idle = await page.locator('#yw-search-results').boundingBox()
  await page.evaluate(() =>
    document.querySelector('.yw-search')?.classList.add('htmx-request'),
  )
  const busy = await page.locator('#yw-search-results').boundingBox()

  expect(busy?.y).toBe(idle?.y)
})

test('typing a phrase fetches results over htmx', async ({ page }) => {
  await page.goto('/?search')

  await expect(page.locator('#yw-search-form')).toBeVisible()

  const search = page.waitForResponse(
    (response) =>
      response.url().includes('api/search') && response.status() === 200,
  )
  await page.locator('#yw-search-phrase').fill('wiki')
  await search

  await expect(
    page.locator('#yw-search-results .yw-search-result').first(),
  ).toBeVisible()
})

test('a phrase matching nothing says so', async ({ page }) => {
  await page.goto('/?search')

  const search = page.waitForResponse((response) =>
    response.url().includes('api/search'),
  )
  await page.locator('#yw-search-phrase').fill('zzzzriennezcorrespondzzzz')
  await search

  await expect(page.locator('#yw-search-results')).toContainText(/aucun/i)
  await expect(
    page.locator('#yw-search-results .yw-search-result'),
  ).toHaveCount(0)
})

test('the facet row appears only once a search has results', async ({
  page,
}) => {
  await page.goto('/?search')

  await expect(page.locator('#yw-search-results')).toContainText(/Saisissez/i)
  await expect(page.locator('#yw-search-facets')).toHaveCount(0)

  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(
    page.locator('#yw-search-results .yw-search-result').first(),
  ).toBeVisible()

  await expect(page.locator('#yw-search-facets')).toBeVisible()
  await expect(
    page.locator('#yw-search-facets .yw-facet__count').first(),
  ).toBeVisible()
})

test('choosing a facet narrows results and stays chosen', async ({ page }) => {
  await page.goto('/?search')

  const results = page.locator('#yw-search-results .yw-search-result')

  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(results.first()).toBeVisible()
  const all = await results.count()
  expect(all).toBeGreaterThan(0)

  await page.locator('#yw-search-facets label[for="yw-facet-form"]').click()

  await expect(
    page.locator(
      '#yw-search-results .yw-search-result:not(.yw-search-result--form)',
    ),
  ).toHaveCount(0)
  await expect(results.first()).toBeVisible()
  expect(await results.count()).toBeLessThanOrEqual(all)

  await expect(
    page.locator('#yw-search-facets input[value="form"]'),
  ).toBeChecked()
})

test('the display switcher changes the layout and stays chosen', async ({
  page,
}) => {
  await page.goto('/?search')
  await expect(page.locator('#yw-search-form #yw-search-display')).toBeVisible()
  await page.locator('#yw-search-phrase').fill('wiki')
  await expect(page.locator('.yw-search-result').first()).toBeVisible()

  await expect(page.locator('.yw-search-results--accordion')).toHaveCount(0)

  await page.locator('label[for="yw-display-accordion"]').click()
  await expect(page.locator('.yw-search-results--accordion')).toBeVisible()
  await page.locator('.yw-search-results--accordion summary').first().click()
  await expect(
    page.locator('.yw-search-results--accordion details[open]').first(),
  ).toBeVisible()

  await page.locator('label[for="yw-display-cards"]').click()
  await expect(page.locator('.yw-search-results--cards')).toBeVisible()

  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(page.locator('.yw-search-results--cards')).toBeVisible()
  await expect(
    page.locator('#yw-search-display input[value="cards"]'),
  ).toBeChecked()
})

test('a search is shareable: the URL carries it, and reloading restores it', async ({
  page,
}) => {
  await page.goto('/?search')
  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(page.locator('.yw-search-result').first()).toBeVisible()

  await expect(page).toHaveURL(/[?&]q=annuaire/)

  await page.locator('label[for="yw-display-cards"]').click()
  await expect(page.locator('.yw-search-results--cards')).toBeVisible()
  await expect(page).toHaveURL(/display=cards/)

  const shared = page.url()
  await page.goto(shared)
  await expect(page.locator('#yw-search-phrase')).toHaveValue('annuaire')
  await expect(page.locator('.yw-search-results--cards')).toBeVisible()
})

test('the search page offers no page actions in its footer', async ({
  page,
}) => {
  await page.goto('/?search')

  await expect(page.locator('.footer')).toHaveCount(0)
  await expect(page.getByRole('link', { name: /Éditer la page/i })).toHaveCount(
    0,
  )

  await page.goto('/?PagePrincipale')
  await expect(
    page.getByRole('link', { name: /Éditer la page/i }).first(),
  ).toBeVisible()
})

test('the retired search actions are gone from the seeded wiki', async ({
  page,
}) => {
  await page.goto('/')

  const body = await page.locator('body').innerText()
  expect(body).not.toContain('newtextsearch')
  expect(body).not.toContain('searchform')
})

test('leaving a result behind leaves the search behind with it', async ({
  page,
}) => {
  await page.goto('/?search&q=accueil')
  await expect(page.locator('.yw-search-result a').first()).toBeVisible({
    timeout: 15000,
  })

  const target = await page
    .locator('.yw-search-result a')
    .first()
    .getAttribute('href')
  await page.locator('.yw-search-result a').first().click()
  await page.waitForFunction((href) => window.location.href === href, target, {
    timeout: 10000,
  })

  expect(
    page.url(),
    'the search rode along to the page the visitor opened',
  ).toBe(target)
})

test('the cards display gets the wiki card layout', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/?search&q=a&display=cards')

  const container = page.locator('.bazar-cards-container')
  await expect(container).toBeVisible({ timeout: 15000 })

  const layout = await page.evaluate(() => {
    const box = document.querySelector('.bazar-cards-container') as HTMLElement
    const card = document.querySelector('.bazar-card') as HTMLElement

    return {
      display: getComputedStyle(box).display,
      boxWidth: Math.round(box.getBoundingClientRect().width),
      cardWidth: Math.round(card.getBoundingClientRect().width),
      titleOverflows: Array.from(
        document.querySelectorAll('.yw-search-result-title'),
      ).some((t) => t.scrollWidth > t.clientWidth + 1),
    }
  })

  expect(
    layout.display,
    'card.css did not reach the page: the cards are not a grid',
  ).toBe('grid')
  expect(
    layout.cardWidth,
    'a card fills the whole row, so the grid never took effect',
  ).toBeLessThan(layout.boxWidth / 2)
  expect(layout.titleOverflows, 'a long page tag ran out of its card').toBe(
    false,
  )
})

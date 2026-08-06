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

  // the quick menu ships a search button (`layout_quick_menu`, ticket 30, which is where
  // ticket 26 put it back when it was still the PageRapideHaut page) -- the old
  // reveal-panel {{searchform}} is gone, and `search` is a routed name, not a page
  await page.getByRole('link', { name: /Rechercher/i }).first().click()

  await expect(page).toHaveURL(/[?/]search/)
  await expect(page.locator('#yw-search-form')).toBeVisible()
})

test('the search box takes focus on arrival, however you arrive', async({ page }) => {
  // a directly loaded document: the native `autofocus` attribute covers this one
  await page.goto('/?search')
  await expect(page.locator('#yw-search-phrase')).toBeFocused()

  // and a BOOSTED arrival, which is how the top-bar button gets here -- the attribute never
  // fires there, because nothing was parsed. Two mechanisms, one behaviour.
  await page.goto('/')
  await page.getByRole('link', { name: /Rechercher/i }).first().click()
  await expect(page.locator('#yw-search-phrase')).toBeFocused()
})

test('the loading indicator does not move the results', async({ page }) => {
  await page.goto('/?search')
  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(page.locator('.yw-search-result').first()).toBeVisible()

  const idle = await page.locator('#yw-search-results').boundingBox()
  // force the indicator on rather than racing a real request
  await page.evaluate(() => document.querySelector('.yw-search')?.classList.add('htmx-request'))
  const busy = await page.locator('#yw-search-results').boundingBox()

  // an indicator in normal flow pushes the list down when a request starts and pulls it back
  // when it ends, so the results jitter on every keystroke
  expect(busy?.y).toBe(idle?.y)
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

test('the facet row appears only once a search has results', async({ page }) => {
  await page.goto('/?search')

  // nothing to narrow before a search, so the row is not rendered at all
  await expect(page.locator('#yw-search-results')).toContainText(/Saisissez/i)
  await expect(page.locator('#yw-search-facets')).toHaveCount(0)

  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(page.locator('#yw-search-results .yw-search-result').first()).toBeVisible()

  await expect(page.locator('#yw-search-facets')).toBeVisible()
  // facets carry counts -- that is what makes them a proposal rather than a menu
  await expect(page.locator('#yw-search-facets .yw-facet__count').first()).toBeVisible()
})

test('choosing a facet narrows results and stays chosen', async({ page }) => {
  await page.goto('/?search')

  const results = page.locator('#yw-search-results .yw-search-result')

  // every step ends on an auto-retrying assertion: the container also fires an hx-get on
  // `load`, so a waitForResponse registered afterwards can resolve on THAT response, and
  // count() is a snapshot that does not retry
  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(results.first()).toBeVisible()
  const all = await results.count()
  expect(all).toBeGreaterThan(0)

  // click the label, not the input: the radio is visually hidden (kept focusable for the
  // keyboard, styled through `input:checked + label`), which is what a user clicks too
  await page.locator('#yw-search-facets label[for="yw-facet-form"]').click()

  // "no result is anything but a form" as one auto-retrying assertion, which is also the
  // signal that the filtered response has swapped in. Checking the first result instead
  // would pass on the UNFILTERED list whenever a form happened to sort first.
  await expect(page.locator('#yw-search-results .yw-search-result:not(.yw-search-result--form)')).toHaveCount(0)
  await expect(results.first()).toBeVisible()
  expect(await results.count()).toBeLessThanOrEqual(all)

  // the choice survives the swap that rendered it -- it is form state, not a JS toggle
  await expect(page.locator('#yw-search-facets input[value="form"]')).toBeChecked()
})

test('the display switcher changes the layout and stays chosen', async({ page }) => {
  await page.goto('/?search')
  // the switcher sits in the form beside the box, not in the results
  await expect(page.locator('#yw-search-form #yw-search-display')).toBeVisible()
  await page.locator('#yw-search-phrase').fill('wiki')
  await expect(page.locator('.yw-search-result').first()).toBeVisible()

  // list is the default, so no layout modifier at all
  await expect(page.locator('.yw-search-results--accordion')).toHaveCount(0)

  await page.locator('label[for="yw-display-accordion"]').click()
  await expect(page.locator('.yw-search-results--accordion')).toBeVisible()
  // the browser's own disclosure widget, so it opens with no JS of ours
  await page.locator('.yw-search-results--accordion summary').first().click()
  await expect(page.locator('.yw-search-results--accordion details[open]').first()).toBeVisible()

  await page.locator('label[for="yw-display-cards"]').click()
  await expect(page.locator('.yw-search-results--cards')).toBeVisible()

  // the choice is form state: it survives the next search rather than resetting
  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(page.locator('.yw-search-results--cards')).toBeVisible()
  await expect(page.locator('#yw-search-display input[value="cards"]')).toBeChecked()
})

test('a search is shareable: the URL carries it, and reloading restores it', async({ page }) => {
  await page.goto('/?search')
  await page.locator('#yw-search-phrase').fill('annuaire')
  await expect(page.locator('.yw-search-result').first()).toBeVisible()

  // the server tells the browser which address this result set is, via HX-Replace-Url --
  // hx-push-url would have put the /api/search path in the address bar
  await expect(page).toHaveURL(/[?&]q=annuaire/)

  await page.locator('label[for="yw-display-cards"]').click()
  await expect(page.locator('.yw-search-results--cards')).toBeVisible()
  await expect(page).toHaveURL(/display=cards/)

  // and the URL is enough on its own: a fresh load reproduces the same screen
  const shared = page.url()
  await page.goto(shared)
  await expect(page.locator('#yw-search-phrase')).toHaveValue('annuaire')
  await expect(page.locator('.yw-search-results--cards')).toBeVisible()
})

test('the search page offers no page actions in its footer', async({ page }) => {
  await page.goto('/?search')

  // /search is a routed name with no Content behind it: "edit this page", the file manager,
  // duplicate and share all point at a tag no page may occupy
  await expect(page.locator('.footer')).toHaveCount(0)
  await expect(page.getByRole('link', { name: /Éditer la page/i })).toHaveCount(0)

  // ... while an ordinary page still has them
  await page.goto('/?PagePrincipale')
  await expect(page.getByRole('link', { name: /Éditer la page/i }).first()).toBeVisible()
})

test('the retired search actions are gone from the seeded wiki', async({ page }) => {
  // the quick menu, which is chrome on every page since ticket 30 -- so any page will do,
  // and PageRapideHaut, where this used to look, is no longer seeded
  await page.goto('/')

  const body = await page.locator('body').innerText()
  expect(body).not.toContain('newtextsearch')
  expect(body).not.toContain('searchform')
})

test('leaving a result behind leaves the search behind with it', async({ page }) => {
  // htmx attributes are inherited, and #yw-search-results carries the hx-include that gathers
  // the search form. Every result link sits inside it, so a (boosted) click was sent with the
  // whole form attached and the address bar ended up reading `?ThePage&q=…&display=list&
  // limit=20` -- a finished search following the visitor from page to page.
  await page.goto('/?search&q=accueil')
  await expect(page.locator('.yw-search-result a').first()).toBeVisible({ timeout: 15000 })

  const target = await page.locator('.yw-search-result a').first().getAttribute('href')
  await page.locator('.yw-search-result a').first().click()
  await page.waitForFunction((href) => window.location.href === href, target, { timeout: 10000 })

  expect(page.url(), 'the search rode along to the page the visitor opened').toBe(target)
})

test('the cards display gets the wiki card layout', async({ page }) => {
  // styles/bazar/card.css is what turns the cards into a grid, and it used to be declared
  // from search-results.twig -- which is served as an htmx fragment, and a fragment response
  // has no <head> for include_css to write into. The stylesheet never loaded and every card
  // came out as a full-width line. Declared from search-action.twig now, which also has to be
  // where it lives because the display switcher changes mode without reloading the page.
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
      // a page title is one unbroken word: it must wrap inside its card rather than run over
      // the card beside it
      titleOverflows: Array.from(document.querySelectorAll('.yw-search-result-title'))
        .some((t) => t.scrollWidth > t.clientWidth + 1),
    }
  })

  expect(layout.display, 'card.css did not reach the page: the cards are not a grid').toBe('grid')
  expect(layout.cardWidth, 'a card fills the whole row, so the grid never took effect')
    .toBeLessThan(layout.boxWidth / 2)
  expect(layout.titleOverflows, 'a long page tag ran out of its card').toBe(false)
})

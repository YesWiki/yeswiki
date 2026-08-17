import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { setPageContent } from '../helpers/page'
import { editorText, openEditorWith } from '../helpers/editor'
import { components } from '../helpers/wysiwyg'
import { watchConsole } from '../helpers/console'
import { login, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

/** The facets, over htmx (ticket 37). */

const CARD_LIST = '/?FacetteRessource&template=card'

test('checking a facet filters the list without leaving the page', async ({
  page,
}) => {
  await page.goto(CARD_LIST)
  const cards = page.locator('.yw-item')
  const before = await cards.count()
  expect(before).toBeGreaterThan(1)

  await page.evaluate(() => {
    ;(window as unknown as { __stillHere: boolean }).__stillHere = true
  })

  await page.getByRole('checkbox').first().check()
  await expect(cards).toHaveCount(1)

  expect(
    await page.evaluate(
      () => (window as unknown as { __stillHere?: boolean }).__stillHere,
    ),
  ).toBe(true)

  await expect(page).toHaveURL(/facet/)
  await expect(page.getByRole('checkbox').first()).toBeChecked()
})

test('the filtered list survives a reload', async ({ page }) => {
  await page.goto(CARD_LIST)
  await page.getByRole('checkbox').first().check()
  await expect(page.locator('.yw-item')).toHaveCount(1)

  const filtered = page.url()
  await page.goto(filtered)
  await expect(page.locator('.yw-item')).toHaveCount(1)
  await expect(page.getByRole('checkbox').first()).toBeChecked()
})

test('the reset button drops the selection', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(
    page,
    'FacettesRemiseAZero',
    '{{entrylist id="4" template="card" groups="bf_type" resetfiltersbutton="1"}}',
  )

  await page.getByRole('checkbox').first().check()
  await expect(page.locator('.yw-item')).toHaveCount(1)

  await page.getByRole('link', { name: /Réinitialiser/i }).click()
  await expect(page.getByRole('checkbox').first()).not.toBeChecked()
  await expect(page.locator('.yw-item')).toHaveCount(2)
  await expect(page).not.toHaveURL(/facet/)
})

test('the counts stay whole, so a second value of the same box is offered', async ({
  page,
}) => {
  await page.goto(CARD_LIST)
  const offered = await page.getByRole('checkbox').count()
  expect(offered).toBeGreaterThan(1)

  await page.getByRole('checkbox').first().check()
  await expect(page.getByRole('checkbox')).toHaveCount(offered)
})

/** ...and a webmaster can ask for them without knowing the parameter names. */
test('the rail offers the facets, and what they can be told', async ({
  page,
}) => {
  const PANEL = '#actions-builder-panel'
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(page, '{{entrylist id="4" template="card"}}')
  await components(page).first().click()
  await expect(
    page.locator(`${PANEL} .action-parameters-container`),
  ).toBeVisible()

  const position = page.locator(`${PANEL} .yw-form-group.list`).filter({
    hasText: 'Position des facettes',
  })
  const width = page.locator(`${PANEL} .yw-form-group.range`)
  await expect(
    position,
    'nothing to lay out until there is a facet',
  ).toBeHidden()

  await page
    .locator(`${PANEL} .multi-input-container.facets .btn`)
    .first()
    .click()
  await page
    .locator(`${PANEL} .multi-input-container.facets select`)
    .first()
    .selectOption({ label: 'Type de ressource - bf_type' })

  await expect
    .poll(() => editorText(page), { message: 'the facet is written' })
    .toContain('groups="bf_type"')
  await expect(position).toBeVisible()
  await expect(width, 'a column has a width').toBeVisible()

  await position
    .locator('select')
    .selectOption({ label: 'Au-dessus (horizontal)' })
  await expect
    .poll(() => editorText(page), { message: 'the layout is written' })
    .toContain('filterposition="top"')
  await expect(width, '...and a row does not').toBeHidden()

  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

test('facets can be laid out above the list rather than beside it', async ({
  page,
}) => {
  await page.goto(`${CARD_LIST}&filterposition=top`)

  const filters = page.locator('.filters-col')
  const results = page.locator('.results-col')
  const filterBox = await filters.boundingBox()
  const resultBox = await results.boundingBox()
  if (!filterBox || !resultBox) throw new Error('the list did not render')

  expect(filterBox.y + filterBox.height).toBeLessThanOrEqual(resultBox.y + 1)
  expect(Math.abs(filterBox.width - resultBox.width)).toBeLessThan(2)

  const tops = await page
    .locator('.yw-facet-select')
    .evaluateAll((nodes) =>
      nodes.map((node) => Math.round(node.getBoundingClientRect().top)),
    )
  expect(new Set(tops).size, 'the facets do not share a top edge').toBe(1)

  await expect(
    page.locator('.facette-container [class*="col-sm-"]'),
  ).toHaveCount(0)

  await expect(page.locator('.yw-facet-select .yw-tag-input')).not.toHaveCount(
    0,
  )
  expect(
    filterBox.height,
    'the row of facets is a row, not a stack of every value there is',
  ).toBeLessThan(140)

  const row = await page.locator('.results-container').boundingBox()
  const count = await page.locator('.results-info').boundingBox()
  if (!row || !count) throw new Error('no facets')
  expect(count.y).toBeGreaterThan(row.y)

  expect(await page.evaluate(() => typeof window['Vue'])).toBe('undefined')
})

/** The tag input is a way of *saying* the same thing: it writes the same parameter into the same form, and the server does the filtering either way. */
test('the facet chips filter, survive the swap, and come off again', async ({
  page,
}) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(String(error)))
  await page.goto(`${CARD_LIST}&filterposition=top`)

  const chips = page.locator('.yw-tag-input__chip')
  const cards = page.locator('.yw-item')
  await expect(cards).toHaveCount(2)

  const pick = async (label: string) => {
    await page.locator('.yw-tag-input__search').first().click()
    await page
      .locator('[data-yw-tag-input-suggestion]', { hasText: label })
      .first()
      .click()
  }

  await pick('Site web')
  await expect(cards).toHaveCount(1)
  await expect(chips, 'the selection survives the swap it caused').toHaveCount(
    1,
  )

  await pick('Partenaire')
  await expect(cards).toHaveCount(2)
  await expect(chips).toHaveCount(2)
  await expect(page).toHaveURL(/facet%5Bbf_type%5D=1%2C3/)

  await page.locator('[data-yw-tag-input-remove]').first().click()
  await expect(cards).toHaveCount(1)
  await expect(chips).toHaveCount(1)

  expect(errors, 'the browser reported errors').toEqual([])
})

/** ...with the keyboard, which is the half of a picker that is easy to ship without: the mouse path works from the first line of markup and nothing reports the arrows missing. */
test('the arrows walk the values and Enter takes one', async ({ page }) => {
  await page.goto(`${CARD_LIST}&filterposition=top`)

  const active = page.locator('[data-yw-tag-input-suggestion][data-active]')
  await page.locator('.yw-tag-input__search').first().click()

  await page.keyboard.press('ArrowDown')
  await expect(active).toHaveText(/Site web/)
  await page.keyboard.press('ArrowDown')
  await expect(active).toHaveText(/Partenaire/)
  await page.keyboard.press('ArrowUp')
  await expect(active).toHaveText(/Site web/)

  await page.keyboard.press('Enter')
  await expect(page.locator('.yw-tag-input__chip')).toHaveText([/Site web/])
  await expect(page.locator('.yw-item')).toHaveCount(1)
})

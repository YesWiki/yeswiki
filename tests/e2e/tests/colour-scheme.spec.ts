import { expect, Page, test } from '@playwright/test'

/** What the VIEWER chooses: light or dark, and which language (ADR-0020). */

const PAGE = process.env.YESWIKI_PAGE_WITH_LIST || 'CartoAnnuaire'
const OTHER = process.env.YESWIKI_PAGE_WITH_EDITOR || 'SaisirAnnuaire'

const TOOLS = '.yw-corner-tools .yw-dropdown:has([data-yw-scheme])'
const LANGUAGES = '.yw-corner-tools .yw-dropdown:has(a[hreflang])'
const READOUT = '[data-yw-scheme]'
const scheme = (state: string) => `[data-yw-scheme-set="${state}"]`

/** Pick a scheme the way a reader does: open the corner menu, then click a mark. */
async function choose(page: Page, state: string) {
  await page.locator(`${TOOLS} [data-yw-dropdown-toggle]`).click()
  await page.locator(scheme(state)).click()
}

/** What the document says its scheme is: the attribute the inline head script writes. */
const forcedScheme = (page: Page) =>
  page.evaluate(() => document.documentElement.getAttribute('data-theme'))

/** The colour a token resolves to right now -- i.e. */
const token = (page: Page, name: string) =>
  page.evaluate(
    (property) =>
      getComputedStyle(document.documentElement)
        .getPropertyValue(property)
        .trim(),
    name,
  )

test('the three states are offered as three marks, and the one in force is marked', async ({
  page,
}) => {
  await page.goto(`/?${PAGE}`)

  expect(await forcedScheme(page)).toBeNull()
  await expect(page.locator(scheme('system'))).toHaveAttribute(
    'aria-pressed',
    'true',
  )

  await choose(page, 'dark')
  expect(await forcedScheme(page)).toBe('dark')
  await expect(page.locator(scheme('dark'))).toHaveAttribute(
    'aria-pressed',
    'true',
  )
  await expect(page.locator(scheme('system'))).toHaveAttribute(
    'aria-pressed',
    'false',
  )

  await choose(page, 'system')
  expect(
    await forcedScheme(page),
    'the third state is the absence of a choice, not a third value',
  ).toBeNull()
})

test('the two menus are docked in the bottom-left corner, and stay there', async ({
  page,
}) => {
  await page.goto(`/?${PAGE}`)

  const dock = page.locator('.yw-corner-tools')
  await expect(dock).toBeVisible()

  const where = await dock.evaluate((element) => {
    const box = element.getBoundingClientRect()
    return {
      position: getComputedStyle(element).position,
      left: box.left,
      fromBottom: window.innerHeight - box.bottom,
    }
  })

  expect(where.position).toBe('fixed')
  expect(where.left, 'it is not against the left edge').toBeLessThan(40)
  expect(where.fromBottom, 'it is not against the bottom edge').toBeLessThan(40)

  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
  const afterScrolling = await dock.evaluate(
    (element) => window.innerHeight - element.getBoundingClientRect().bottom,
  )
  expect(afterScrolling, 'it scrolled away with the page').toBeCloseTo(
    where.fromBottom,
    0,
  )
})

test('each menu opens on a click, on its own, as a column of choices', async ({
  page,
}) => {
  await page.goto(`/?${PAGE}`)

  const menus = page.locator('.yw-corner-tools .yw-dropdown')
  await expect(menus).toHaveCount(2)

  const scheme = menus.filter({ has: page.locator('[data-yw-scheme]') })
  const language = menus.filter({ hasNot: page.locator('[data-yw-scheme]') })
  const panelOf = (menu: ReturnType<typeof page.locator>) =>
    menu.locator('.yw-corner-tools__menu')
  const open = (menu: ReturnType<typeof page.locator>) =>
    menu.locator('[data-yw-dropdown-toggle]').click()

  await expect(
    panelOf(scheme),
    'the options are showing before anyone asked',
  ).toBeHidden()
  await expect(panelOf(language)).toBeHidden()

  await open(scheme)
  await expect(panelOf(scheme)).toBeVisible()
  await expect(panelOf(language), 'the other menu opened too').toBeHidden()

  await open(language)
  await expect(panelOf(language)).toBeVisible()
  await expect(panelOf(scheme), 'the first menu stayed open').toBeHidden()

  await page.keyboard.press('Escape')
  await expect(panelOf(language), 'Escape left it open').toBeHidden()

  await open(scheme)
  await expect(panelOf(scheme)).toBeVisible()
  const shape = await panelOf(scheme).evaluate((element) => {
    const options = [...element.querySelectorAll('.yw-switcher__option')].map(
      (option) => option.getBoundingClientRect(),
    )

    return {
      options: options.length,
      switchers: element.querySelectorAll('.yw-switcher').length,
      rows: new Set(options.map((box) => Math.round(box.top + box.height / 2)))
        .size,
      lefts: new Set(options.map((box) => Math.round(box.left))).size,
      widths: new Set(options.map((box) => Math.round(box.width))).size,
    }
  })

  expect(shape.switchers, 'a menu holds one switcher, not both').toBe(1)
  expect(shape.options, 'system, light and dark').toBe(3)
  expect(shape.rows, 'the options are stacked, one per row').toBe(shape.options)
  expect(shape.lefts, 'the options are aligned down one edge').toBe(1)
  expect(shape.widths, 'the options are the same width').toBe(1)
})

test('the wiki can be read in another language, from the same cluster', async ({
  page,
}) => {
  await page.goto(`/?${PAGE}`)

  const languages = page.locator('.yw-corner-tools a[hreflang]')
  const count = await languages.count()
  test.skip(count < 2, 'this wiki has one language installed')

  await expect(
    page.locator('.yw-corner-tools a[aria-current="true"]'),
  ).toHaveCount(1)

  await page.locator(`${LANGUAGES} [data-yw-dropdown-toggle]`).click()
  const target = page.locator('.yw-corner-tools a[hreflang="en"]')
  await target.click()

  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  expect(page.url()).toContain('lang=en')

  const cookie = (await page.context().cookies()).find(
    (c) => c.name === 'yw-lang',
  )
  expect(cookie?.value, 'the choice was not remembered').toBe('en')

  await page.goto(`/?${OTHER}`)
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  expect(page.url(), 'the language is not in the URL any more').not.toContain(
    'lang=',
  )

  await expect(page.locator('#yw-main a[href*="lang="]')).toHaveCount(0)
})

test('the dark scheme really repaints the page', async ({ page }) => {
  await page.goto(`/?${PAGE}`)

  const light = await token(page, '--yw-surface')

  await choose(page, 'dark')

  const dark = await token(page, '--yw-surface')
  expect(dark, 'the dark token block did not win').not.toBe(light)

  expect(
    await page.evaluate(
      () => getComputedStyle(document.documentElement).colorScheme,
    ),
  ).toBe('dark')
})

test('the choice survives a full load and a boosted navigation, without a flash', async ({
  page,
}) => {
  await page.goto(`/?${PAGE}`)
  await choose(page, 'dark')
  const dark = await token(page, '--yw-surface')

  await page.goto(`/?${OTHER}`)
  expect(await forcedScheme(page)).toBe('dark')
  expect(await token(page, '--yw-surface')).toBe(dark)

  const headOrder = await page.evaluate(() => {
    const nodes = [...document.head.children]
    const script = nodes.findIndex(
      (node) =>
        node.tagName === 'SCRIPT' &&
        !node.hasAttribute('src') &&
        node.textContent?.includes('yw-scheme'),
    )
    const sheet = nodes.findIndex(
      (node) =>
        node.tagName === 'LINK' && node.getAttribute('rel') === 'stylesheet',
    )
    return { script, sheet }
  })
  expect(
    headOrder.script,
    'the scheme script is not inline in the head',
  ).toBeGreaterThanOrEqual(0)
  expect(
    headOrder.script,
    'the scheme script must run before the first stylesheet is applied',
  ).toBeLessThan(headOrder.sheet)

  const link = page.locator(`a[href*="${PAGE}"]`).first()
  if (await link.count()) {
    await link.click()
    await page.waitForFunction(
      (expected) => window.location.href.includes(expected),
      PAGE,
    )
    expect(await forcedScheme(page)).toBe('dark')
    expect(await token(page, '--yw-surface')).toBe(dark)
    await expect(page.locator(READOUT)).toHaveAttribute('title', /sombre|dark/i)
  }
})

/** The editors are the one part of a wiki page that brings its own colours. */
test('the editor follows the scheme, on arrival and while it is open', async ({
  page,
}) => {
  await page.goto(`/?${PAGE}`)
  await choose(page, 'dark')

  await page.goto(`/?${OTHER}/edit`)
  const editor = page.locator('.vditor')
  await expect(editor).toBeVisible({ timeout: 20000 })
  await expect(editor, 'the editor opened light on a dark page').toHaveClass(
    /vditor--dark/,
  )

  await choose(page, 'light')
  await expect(editor).not.toHaveClass(/vditor--dark/)
  await choose(page, 'dark')
  await expect(editor).toHaveClass(/vditor--dark/)
})

test('a page pins the Preset and the viewer picks the scheme, independently', async ({
  page,
}) => {
  await page.goto(`/?${PAGE}`)
  const lightPrimary = await token(page, '--yw-primary')
  const styleSheets = () =>
    page.evaluate(() =>
      [...document.querySelectorAll('link[rel="stylesheet"]')]
        .map((link) => (link as HTMLLinkElement).href)
        .join(' '),
    )
  const before = await styleSheets()

  await choose(page, 'dark')

  expect(await token(page, '--yw-primary')).not.toBe(lightPrimary)
  expect(await styleSheets()).toBe(before)
})

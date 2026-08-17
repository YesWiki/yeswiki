import { expect, Page, test } from '@playwright/test'

/**
 * What the VIEWER chooses: light or dark, and which language (ADR-0020).
 *
 * Three states for the scheme, not two: follow the system, forced light, forced dark. Both
 * controls live in one cluster at the end of the navbar, apart from the quick menu an admin
 * configures -- an admin does not get to remove them.
 *
 * Everything worth asserting here only exists in a browser: what `<html>` is wearing before
 * the first paint, whether the options really are on one line, what survives a navigation
 * that replaces the body rather than the document, and whether the page's own background
 * actually flipped. No PHP test can see any of it.
 */

const PAGE = process.env.YESWIKI_PAGE_WITH_LIST || 'CartoAnnuaire'
const OTHER = process.env.YESWIKI_PAGE_WITH_EDITOR || 'SaisirAnnuaire'

/** The cluster, its readout, and one of the three marks inside it. */
// The scheme's own menu, not the cluster: the cluster holds two dropdowns now, and hovering
// it opens whichever one the pointer happens to land on.
const TOOLS = '.yw-topnav-tools__menu:has([data-yw-scheme])'
const LANGUAGES = '.yw-topnav-tools__menu:has(a[hreflang])'
const READOUT = '[data-yw-scheme]'
const scheme = (state: string) => `[data-yw-scheme-set="${state}"]`

/**
 * Pick a scheme the way a reader does: reach for the cluster, then click a mark.
 *
 * The hover is not decoration -- until the cluster is hovered the line is `pointer-events:
 * none`, so a click that skipped it would be a click on the navbar behind it.
 */
async function choose(page: Page, state: string) {
  await page.locator(TOOLS).hover()
  await page.locator(scheme(state)).click()
}

/** What the document says its scheme is: the attribute the inline head script writes. */
const forcedScheme = (page: Page) =>
  page.evaluate(() => document.documentElement.getAttribute('data-theme'))

/** The colour a token resolves to right now -- i.e. which of the two blocks won. */
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

  // nothing stored: the viewer follows their machine, and no attribute is written at all --
  // which is what leaves `prefers-color-scheme` in charge
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

  // ...and back to following the machine, which is a state you can reach in one click rather
  // than by clicking until it comes round again
  await choose(page, 'system')
  expect(
    await forcedScheme(page),
    'the third state is the absence of a choice, not a third value',
  ).toBeNull()
})

test('each menu opens on its own, as a column of choices', async ({ page }) => {
  await page.goto(`/?${PAGE}`)

  // TWO dropdowns, not one panel holding both: they answer different questions, and a reader
  // opening one has no business being shown the other.
  const menus = page.locator('.yw-topnav-tools__menu')
  await expect(menus).toHaveCount(2)

  const scheme = menus.filter({ has: page.locator('[data-yw-scheme]') })
  const language = menus.filter({ hasNot: page.locator('[data-yw-scheme]') })
  const opacityOf = (menu: ReturnType<typeof page.locator>) =>
    menu
      .locator('.yw-topnav-tools__panel')
      .evaluate((element) => getComputedStyle(element).opacity)

  expect(
    await opacityOf(scheme),
    'the options are showing before anyone asked',
  ).toBe('0')
  expect(await opacityOf(language)).toBe('0')

  // hovering one opens THAT one, and leaves the other shut
  await scheme.hover()
  await expect.poll(() => opacityOf(scheme)).toBe('1')
  expect(await opacityOf(language), 'the other menu opened too').toBe('0')

  await language.hover()
  await expect.poll(() => opacityOf(language)).toBe('1')
  expect(await opacityOf(scheme), 'the other menu stayed open').toBe('0')

  // A COLUMN. It used to be one nowrap line of six marks, where "is this third one the last
  // scheme or the first language?" was a question the panel asked rather than answered.
  await scheme.hover()
  await expect.poll(() => opacityOf(scheme)).toBe('1')
  const shape = await scheme
    .locator('.yw-topnav-tools__panel')
    .evaluate((element) => {
      const options = [...element.querySelectorAll('.yw-switcher__option')].map(
        (option) => option.getBoundingClientRect(),
      )

      return {
        options: options.length,
        switchers: element.querySelectorAll('.yw-switcher').length,
        rows: new Set(
          options.map((box) => Math.round(box.top + box.height / 2)),
        ).size,
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

  const languages = page.locator('.yw-topnav-tools a[hreflang]')
  const count = await languages.count()
  test.skip(count < 2, 'this wiki has one language installed')

  // the one being read is marked, like the scheme in force is
  await expect(
    page.locator('.yw-topnav-tools a[aria-current="true"]'),
  ).toHaveCount(1)

  // the LANGUAGE menu, which is the other dropdown -- TOOLS is the scheme's
  await page.locator(LANGUAGES).hover()
  const target = page.locator('.yw-topnav-tools a[hreflang="en"]')
  await target.click()

  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  expect(page.url()).toContain('lang=en')

  // ...and it stays, through a cookie rather than through the URL. The old answer was to
  // append `&lang=` to every link YesWiki generated, which reached exactly those links: a
  // link somebody wrote by hand dropped the reader back into the wiki's own language
  // halfway through reading it, and a shared URL handed your language to whoever opened it.
  const cookie = (await page.context().cookies()).find(
    (c) => c.name === 'yw-lang',
  )
  expect(cookie?.value, 'the choice was not remembered').toBe('en')

  await page.goto(`/?${OTHER}`)
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  expect(page.url(), 'the language is not in the URL any more').not.toContain(
    'lang=',
  )

  // no link on the page carries it either
  await expect(page.locator('#yw-main a[href*="lang="]')).toHaveCount(0)
})

test('the dark scheme really repaints the page', async ({ page }) => {
  await page.goto(`/?${PAGE}`)

  const light = await token(page, '--yw-surface')

  await choose(page, 'dark')

  const dark = await token(page, '--yw-surface')
  expect(dark, 'the dark token block did not win').not.toBe(light)

  // the ground the browser paints behind everything, not just the boxes on it: `color-scheme`
  // is what makes the scrollbars and the space past the end of the document follow
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

  // a full load: the inline script in the head has to have run before anything was painted,
  // so the attribute is already there when the first stylesheet applies
  await page.goto(`/?${OTHER}`)
  expect(await forcedScheme(page)).toBe('dark')
  expect(await token(page, '--yw-surface')).toBe(dark)

  // ...and there was never a moment when it was not. A flicker is not a thing a test can
  // watch for reliably, so what is asserted is the structural property that prevents it: the
  // script is INLINE and in the <head>, ahead of the first stylesheet. A deferred script, or
  // one at the foot of the body, would leave the light set painted first.
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

  // a boosted navigation replaces the BODY, so `<html>`'s attribute is untouched by
  // construction -- verified rather than assumed, because "the attribute is on the element
  // that survives" is the whole reason it is on `<html>`
  const link = page.locator(`a[href*="${PAGE}"]`).first()
  if (await link.count()) {
    await link.click()
    await page.waitForFunction(
      (expected) => window.location.href.includes(expected),
      PAGE,
    )
    expect(await forcedScheme(page)).toBe('dark')
    expect(await token(page, '--yw-surface')).toBe(dark)
    // and the readout that arrived with the new body knows which state it is in
    await expect(page.locator(READOUT)).toHaveAttribute('title', /sombre|dark/i)
  }
})

/**
 * The editors are the one part of a wiki page that brings its own colours.
 *
 * Vditor ships themes and is told which to wear; ACE has none vendored at all and is painted
 * from the same tokens as everything else. Both have to be right on arrival *and* while open:
 * an editor is where somebody stays for twenty minutes, which is exactly when the sun goes
 * down and a machine following its system flips underneath them.
 */
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

  // ...and it turns round without being reopened
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

  // the same Preset, its other set of colours: the scheme is a second block inside the file
  // the page already had, so nothing is fetched and nothing about which Preset the page
  // wears has changed. A "dark preset" would show up here as a different list of links.
  expect(await token(page, '--yw-primary')).not.toBe(lightPrimary)
  expect(await styleSheets()).toBe(before)
})

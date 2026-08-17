import { expect, Page, test } from '@playwright/test'
import { attachConsole, watchConsole } from '../helpers/console'

/** Ticket 16: internal links load through htmx, so a navigation swaps the squelette's body block instead of reloading the page. */

const PAGE_WITH_LIST = process.env.YESWIKI_PAGE_WITH_LIST || 'CartoAnnuaire'
const PAGE_WITH_EDITOR =
  process.env.YESWIKI_PAGE_WITH_EDITOR || 'SaisirAnnuaire'

/** A *mounted* editor, not merely present markup: vditor hides the original textarea and builds its own container beside it, so `textarea[data-vditor-ready]` is correctly invisible and asserting on it fails for the wrong reason. */
const EDITOR = '.vditor, .aceditor-container .ace_editor'

/** A widget that only exists once JavaScript has run — a Vue list, or leaflet having built its map. */
const MOUNTED_WIDGET =
  process.env.YESWIKI_WIDGET_SELECTOR ||
  '.entry-list-dynamic-container > *, .leaflet-container'

/** Mark the current document, then navigate by clicking. */
const markDocument = (page: Page) =>
  page.evaluate(() => {
    // @ts-expect-error test-only marker
    window.__ywNavigationProbe = true
  })

const documentWasKept = (page: Page) =>
  page.evaluate(
    // @ts-expect-error test-only marker
    () => window.__ywNavigationProbe === true,
  )

/** Follow a link to `tag` the way a user would, and wait for htmx to finish swapping. */
const navigateByLink = async (page: Page, tag: string) => {
  const link = page.locator(`a[href*="${tag}"]`).first()
  await expect(link, `no link to ${tag} on this page`).toBeVisible()
  await link.click()
  await page.waitForFunction(
    (expected) => window.location.href.includes(expected),
    tag,
    { timeout: 10000 },
  )
  await page.waitForTimeout(500)
}

test('the navbar keeps its height from page to page', async ({ page }) => {
  await page.setViewportSize({ width: 1000, height: 500 })

  const heights: number[] = []
  for (const url of ['/', '/?BacASable', '/?search', '/?PageInexistante']) {
    await page.goto(url)
    heights.push(
      await page.evaluate(
        () =>
          (
            document.querySelector('#yw-topnav') as HTMLElement
          ).getBoundingClientRect().height,
      ),
    )
  }

  await page.goto('/')
  heights.push(
    await page.evaluate(() => {
      const nav = document.querySelector('#yw-topnav') as HTMLElement
      const link = document.querySelector(
        '#yw-topnav .topnavpage a',
      ) as HTMLElement
      link.classList.add('active-link')
      link.parentElement?.classList.add('active-list', 'active')
      return nav.getBoundingClientRect().height
    }),
  )

  expect(
    new Set(heights).size,
    `navbar heights differed: ${heights.join(', ')}`,
  ).toBe(1)
})

test.describe('boosted navigation', () => {
  test('the skeleton opts in to htmx navigation', async ({ page }) => {
    await page.goto(`/?${PAGE_WITH_LIST}`)

    const body = page.locator('body')
    await expect(body).toHaveAttribute('hx-boost', 'true')
    await expect(body).toHaveAttribute('hx-headers', /HX-YesWiki-Layout/)
  })

  test('a link click swaps the page instead of reloading it', async ({
    page,
  }) => {
    await page.goto(`/?${PAGE_WITH_LIST}`)
    await markDocument(page)

    await navigateByLink(page, PAGE_WITH_EDITOR)

    expect(
      await documentWasKept(page),
      'the document was replaced, so navigation was not boosted',
    ).toBe(true)
  })

  /** The regression that started this: `ywInitEach` was keyed on <body>, which survives a swap, so the editor was only ever built on the first page of a session. */
  test('the editor initialises on a page reached by a boosted navigation', async ({
    page,
  }, testInfo) => {
    const watcher = watchConsole(page)

    await page.goto(`/?${PAGE_WITH_EDITOR}`)
    await expect(
      page.locator(EDITOR).first(),
      'the editor must work on a direct load',
    ).toBeVisible({ timeout: 15000 })

    await navigateByLink(page, PAGE_WITH_LIST)
    await navigateByLink(page, PAGE_WITH_EDITOR)

    await expect(
      page.locator(EDITOR).first(),
      'the editor was not initialised after a boosted navigation',
    ).toBeVisible({ timeout: 15000 })

    await attachConsole(watcher, testInfo)
    expect(watcher.errors(), 'the browser reported errors').toEqual([])
  })

  /** The same fault seen from the other side: the Vue list never mounted on the second visit. */
  test('a dynamic bazar list mounts on a page reached by a boosted navigation', async ({
    page,
  }, testInfo) => {
    const watcher = watchConsole(page)

    await page.goto(`/?${PAGE_WITH_LIST}`)
    const widget = page.locator(MOUNTED_WIDGET).first()
    if ((await widget.count()) === 0) {
      test.skip(
        true,
        `no JS-mounted widget on ${PAGE_WITH_LIST}; set YESWIKI_WIDGET_SELECTOR`,
      )
    }
    await expect(
      widget,
      'the widget did not mount on a direct load',
    ).toBeVisible({ timeout: 15000 })

    await navigateByLink(page, PAGE_WITH_EDITOR)
    await navigateByLink(page, PAGE_WITH_LIST)

    const diagnostic = await page.evaluate(() => {
      const holders = [
        ...document.querySelectorAll('.entry-list-dynamic-container'),
      ]

      const main = document.querySelector('#yw-main')

      return {
        url: window.location.href,
        title: document.title,
        containers: holders.length,
        leafletContainers:
          document.querySelectorAll('.leaflet-container').length,
        mainContentChars: (main?.innerHTML || '').length,
        mainExcerpt: (main?.textContent || '')
          .replace(/\s+/g, ' ')
          .trim()
          .slice(0, 300),
      }
    })
    await testInfo.attach('dom-after-navigation', {
      body: JSON.stringify(diagnostic, null, 2),
      contentType: 'application/json',
    })

    await expect(
      page.locator(MOUNTED_WIDGET).first(),
      `the widget did not mount after a boosted navigation -- ${JSON.stringify(diagnostic)}`,
    ).toBeVisible({ timeout: 15000 })

    await attachConsole(watcher, testInfo)
    expect(watcher.errors(), 'the browser reported errors').toEqual([])
  })

  /** Does the *server* answer a boosted request with the same page? */
  test('a boosted request returns the same content as a direct one', async ({
    page,
  }, testInfo) => {
    await page.goto(`/?${PAGE_WITH_LIST}`)
    const fingerprint = JSON.parse(
      (await page.locator('body').getAttribute('hx-headers')) || '{}',
    )

    const direct = await page.request.get(`/?${PAGE_WITH_LIST}`)
    const boosted = await page.request.get(`/?${PAGE_WITH_LIST}`, {
      headers: { 'HX-Request': 'true', 'HX-Boosted': 'true', ...fingerprint },
    })

    const directBody = await direct.text()
    const boostedBody = await boosted.text()

    const report = {
      directStatus: direct.status(),
      boostedStatus: boosted.status(),
      directChars: directBody.length,
      boostedChars: boostedBody.length,
      ratio: Number(
        (boostedBody.length / Math.max(directBody.length, 1)).toFixed(3),
      ),
      boostedRedirect: boosted.headers()['hx-redirect'] ?? null,
      boostedHasHead: /<head[\s>]/i.test(boostedBody),
      boostedStartsWith: boostedBody.slice(0, 160),
    }
    await testInfo.attach('server-comparison', {
      body: JSON.stringify(report, null, 2),
      contentType: 'application/json',
    })

    expect(report.boostedStatus, JSON.stringify(report)).toBe(200)
    expect(
      report.boostedHasHead,
      `a fragment must carry no literal <head>; htmx strips it -- ${JSON.stringify(report)}`,
    ).toBe(false)
    expect(
      report.ratio,
      `the boosted response is far smaller than the direct one, so content was lost -- ${JSON.stringify(report)}`,
    ).toBeGreaterThan(0.8)
  })

  /** The catch-all. */
  test('a round trip produces no console errors', async ({
    page,
  }, testInfo) => {
    const watcher = watchConsole(page)

    await page.goto(`/?${PAGE_WITH_LIST}`)
    await navigateByLink(page, PAGE_WITH_EDITOR)
    await navigateByLink(page, PAGE_WITH_LIST)

    await attachConsole(watcher, testInfo)
    expect(
      watcher.errors(),
      'the browser reported errors during navigation',
    ).toEqual([])
  })

  /**
   * /doc and /api are dashboard routes, so moving between them is a boosted swap that
   * re-runs every <script> the swapped body carries -- documentation.js among them.
   *
   * It declared `const docRoot` at top level, and a second run of the same file in the same
   * global scope is a SyntaxError: the whole script died before its first statement. That
   * statement is the empty <nav> it puts at the top of the body for docsify to claim, so
   * docsify's `find('nav')` took the WIKI's navbar instead, emptied it into `_navbar.md`,
   * and the site navigation vanished until a full reload. Nothing server-side can see this.
   */
  test('leaving the documentation and coming back keeps the wiki navbar', async ({
    page,
  }, testInfo) => {
    const watcher = watchConsole(page)
    const railLink = (route: string) =>
      page.locator(`.yw-dashboard__sidebar a[href*="${route}"]`).first()
    const wikiMenuLinks = () => page.locator('#yw-topnav .topnavpage a').count()

    await page.goto('/?doc')
    await expect(
      page.locator('.yw-dashboard__canvas main .markdown-section'),
      'docsify did not render on a direct load',
    ).toBeVisible({ timeout: 15000 })
    const linksBefore = await wikiMenuLinks()
    expect(
      linksBefore,
      'this wiki has no navbar menu, so there is nothing to lose',
    ).toBeGreaterThan(0)

    for (let round = 0; round < 2; round += 1) {
      await railLink('dashboard/export').click()
      await page.waitForFunction(
        () => window.location.href.includes('export'),
        null,
        { timeout: 10000 },
      )
      await railLink('doc').click()
      await page.waitForFunction(
        () => window.location.href.includes('doc'),
        null,
        { timeout: 10000 },
      )
      await page.waitForTimeout(1500)

      await expect(
        page.locator('.yw-dashboard__canvas main .markdown-section'),
        `docsify did not render after round trip ${round + 1}`,
      ).toBeVisible({ timeout: 15000 })
      expect(
        await wikiMenuLinks(),
        `the wiki navbar was emptied after round trip ${round + 1}`,
      ).toBe(linksBefore)
      expect(
        await page.locator('#yw-topnav.app-nav').count(),
        `docsify claimed the wiki navbar after round trip ${round + 1}`,
      ).toBe(0)
    }

    await attachConsole(watcher, testInfo)
    expect(watcher.errors(), 'the browser reported errors').toEqual([])
  })

  /** With the cache disabled, going back is an ordinary load -- and must still work. */
  test('the back button restores a working page', async ({
    page,
  }, testInfo) => {
    const watcher = watchConsole(page)

    await page.goto(`/?${PAGE_WITH_LIST}`)
    await navigateByLink(page, PAGE_WITH_EDITOR)
    await page.goBack()
    await page.waitForLoadState('load')
    await page.waitForTimeout(500)

    const widget = page.locator(MOUNTED_WIDGET).first()
    if ((await widget.count()) > 0) {
      await expect(
        widget,
        'the widget did not mount after going back',
      ).toBeVisible({ timeout: 15000 })
    }

    await attachConsole(watcher, testInfo)
    expect(
      watcher.errors(),
      'the browser reported errors after going back',
    ).toEqual([])
  })
})

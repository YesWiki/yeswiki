import { expect, test } from '@playwright/test'
import { login, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'
import { setPageContent } from '../helpers/page'

/**
 * A page must never be wider than the window.
 *
 * Reported as "the search form flickers a lot, going from block to inline" on a page carrying
 * {{admincontent}}. A document that overflows horizontally has a horizontal scrollbar, and a
 * scrollbar takes room away from the layout that produced it -- so the width the page is laid
 * out in stops being a constant. The admin screen swaps its table on every keystroke, each
 * table a different width, and its filter bar is a wrapping flex row: the search box lands on
 * a line of its own or shares one depending on a few pixels that keep changing.
 *
 * Four separate causes, all of them small and all of them real:
 *   - a CSS tooltip was hidden with `visibility: hidden`, which still takes part in layout --
 *     so the "Se connecter" bubble on the rightmost navbar icon made *every page in the wiki*
 *     13px wider than its window;
 *   - `.container` was `width: 100%` plus 30px of padding under content-box sizing, so the
 *     navbar stuck 30px out of every window narrower than its 1170px cap;
 *   - `.full-width` sized itself with `100vw`, which counts the vertical scrollbar's gutter;
 *   - the admin table pushed the document sideways instead of scrolling in its own box.
 *
 * A headless browser draws overlay scrollbars and so never shows the flicker itself. The
 * overflow that drives it measures the same either way, which is what this asserts.
 */
const WIDTHS = [1600, 1280, 1024, 900, 768]

test.describe('no page is wider than the window', () => {
  test('an ordinary page', async ({ page }) => {
    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto('/?PagePrincipale')
      await page.waitForTimeout(300)
      const overflow = await page.evaluate(() =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth)
      expect(overflow, `the page overflows by ${overflow}px at ${width}px wide`).toBe(0)
    }
  })

  /** The widest thing the wiki draws, and the one the flicker was reported on. */
  test('a page carrying the admin content screen', async ({ page }) => {
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
    await setPageContent(page, 'LayoutOverflowProbe', '{{admincontent}}')

    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto('/?LayoutOverflowProbe')
      // the table arrives by htmx after the shell, and it is the table that is wide
      await page.waitForTimeout(1200)
      const overflow = await page.evaluate(() =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth)
      expect(overflow, `the admin screen overflows by ${overflow}px at ${width}px wide`).toBe(0)
    }
  })
})

import { expect, test } from '@playwright/test'
import { login, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'
import { setPageContent } from '../helpers/page'

/** A page must never be wider than the window. */
const WIDTHS = [1600, 1280, 1024, 900, 768]

test.describe('no page is wider than the window', () => {
  test('an ordinary page', async ({ page }) => {
    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto('/?PagePrincipale')
      await page.waitForTimeout(300)
      const overflow = await page.evaluate(
        () =>
          document.documentElement.scrollWidth -
          document.documentElement.clientWidth,
      )
      expect(
        overflow,
        `the page overflows by ${overflow}px at ${width}px wide`,
      ).toBe(0)
    }
  })

  /** The widest thing the wiki draws, and the one the flicker was reported on. */
  test('a page carrying the admin content screen', async ({ page }) => {
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
    await setPageContent(page, 'LayoutOverflowProbe', '{{admincontent}}')

    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto('/?LayoutOverflowProbe')
      await page.waitForTimeout(1200)
      const overflow = await page.evaluate(
        () =>
          document.documentElement.scrollWidth -
          document.documentElement.clientWidth,
      )
      expect(
        overflow,
        `the admin screen overflows by ${overflow}px at ${width}px wide`,
      ).toBe(0)
    }
  })
})

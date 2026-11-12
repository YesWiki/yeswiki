import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { createPageWithContent } from '../helpers/page'

test.beforeEach(async () => {
  resetEnv()
})

/** `{{toc display="rail"}}` on a long page: the same table of contents as the documentation's, folded to its sections. */

const filler = Array(12)
  .fill('Un paragraphe pour donner de la hauteur à la section.')
  .join('\n\n')
const body = [
  '{{toc display="rail" title="Sur cette page"}}',
  ...Array.from({ length: 7 }, (_, i) =>
    [`## Section ${i + 1}`, filler, `### Détail ${i + 1}`, filler].join('\n\n'),
  ),
].join('\n\n')

test('a long rail shows its sections folded and opens the one being read', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1400, height: 800 })
  await createPageWithContent(page, 'TocRailFolding', body)
  await page.goto('/?TocRailFolding')

  const rail = page.locator('.yw-toc--rail')
  const details = rail.locator('li.toc3:visible')
  await expect(rail).toHaveClass(/yw-toc--ready/)
  await expect(rail.locator('li.toc2')).toHaveCount(7)
  await expect(details).toHaveText(['Détail 1'])

  await page.evaluate(() =>
    document
      .getElementById('toc-détail-4')
      ?.scrollIntoView({ behavior: 'instant' }),
  )
  await expect(rail.locator('a[aria-current]')).toHaveText('Détail 4')
  await expect(details).toHaveText(['Détail 4'])

  await rail.locator('.yw-toc__toggle[aria-expanded="false"]').first().click()
  await expect(details).toHaveCount(2)
})

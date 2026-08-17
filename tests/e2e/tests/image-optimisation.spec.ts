import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { setPageContent } from '../helpers/page'

test.beforeEach(async () => {
  resetEnv()
})

/** Upload a picture of this size through the API, and return its tag. */
const uploadPicture = async (page: Page, width: number, height: number) => {
  return page.evaluate(
    async ({ width, height }) => {
      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height
      const context = canvas.getContext('2d')
      for (let i = 0; i < 1500; i += 1) {
        context.fillStyle = `hsl(${(i * 41) % 360} 90% ${20 + ((i * 17) % 60)}%)`
        context.fillRect((i * 131) % width, (i * 197) % height, 60, 60)
      }
      const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, 'image/webp', 0.9),
      )

      const body = new FormData()
      body.append(
        'upFile',
        new File([blob], 'banner.webp', { type: 'image/webp' }),
      )
      body.append('pageTag', wiki.pageTag)
      const entry = await fetch(wiki.url('api/files'), {
        method: 'POST',
        body,
      }).then((r) => r.json())
      return entry.tag
    },
    { width, height },
  )
}

/** What the wiki actually sends for this URL. */
const served = (page: Page, url: string) =>
  page.evaluate(async (address) => {
    const blob = await fetch(address).then((r) => r.blob())
    const bitmap = await createImageBitmap(blob)
    return { size: blob.size, width: bitmap.width, height: bitmap.height }
  }, url)

test('an attached image is served at the size it is drawn', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale')
  const tag = await uploadPicture(page, 3000, 2000)

  await setPageContent(
    page,
    'TestImageAttach',
    `{{attach file="${tag}" size="small"}}`,
  )

  const src = await page.locator('.page img').first().getAttribute('src')
  expect(src).toContain('width=140')

  const small = await served(page, src)
  expect(small.width).toBeLessThanOrEqual(146)
  expect(small.height).toBeLessThanOrEqual(97)

  const whole = await served(page, src.replace(/[?&]width=\d+&height=\d+/, ''))
  expect(whole.width).toBe(3000)
  expect(small.size).toBeLessThan(whole.size / 10)
})

test('an image with no size asked for is still capped', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale')
  const tag = await uploadPicture(page, 3000, 2000)

  await setPageContent(page, 'TestImageCap', `{{attach file="${tag}"}}`)

  const src = await page.locator('.page img').first().getAttribute('src')
  const image = await served(page, src)
  expect(image.width).toBe(1920)
})

/** A picture smaller than the cap comes back untouched, rather than blown up to it. */
test('a small image is not enlarged to the cap', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale')
  const tag = await uploadPicture(page, 300, 200)

  await setPageContent(page, 'TestImageSmall', `{{attach file="${tag}"}}`)

  const src = await page.locator('.page img').first().getAttribute('src')
  const image = await served(page, src)
  expect(image.width).toBe(300)
  expect(image.height).toBe(200)
})

test("a section's background picture is capped too", async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale')
  const tag = await uploadPicture(page, 3000, 2000)

  await setPageContent(
    page,
    'TestImageSection',
    `{{section file="${tag}"}}\nHello\n{{end elem="section"}}`,
  )

  const style = await page
    .locator('[style*="--yw-section-image"]')
    .first()
    .getAttribute('style')
  const url = /--yw-section-image:url\(([^)]+)\)/.exec(style)[1]
  expect(url).toContain('width=1920')

  const image = await served(page, url)
  expect(image.width).toBe(1920)
})

import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import {
  ADMIN_PASSWORD,
  ADMIN_USERNAME,
  login,
  signedPost,
} from '../helpers/login'
import { attachConsole, watchConsole } from '../helpers/console'

test.beforeEach(async () => {
  resetEnv()
})

/** {{qrcodetroc}} draws its accounts on a canvas of its own, with no library and no error (ticket 31). */
test('the qrcodetroc visualisation draws on its canvas', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)

  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const saved = await signedPost(page, '/?api/pages/QrcodeTrocVisualisation', {
    form: {
      body: '{{qrcodetroc formuser="6" relation="echange" refresh="60000"}}',
    },
  })
  expect(saved.ok()).toBeTruthy()

  await page.goto('/?QrcodeTrocVisualisation')

  const canvas = page.locator('#canvas-qrcodetroc canvas')
  await expect(canvas).toBeVisible()
  await expect
    .poll(() =>
      canvas.evaluate((element: HTMLCanvasElement) => {
        const pixels = element
          .getContext('2d')!
          .getImageData(0, 0, element.width, element.height).data
        let drawn = 0
        for (let i = 0; i < pixels.length; i += 4) {
          if (pixels[i] !== 20 || pixels[i + 1] !== 20 || pixels[i + 2] !== 20)
            drawn++
        }
        return drawn
      }),
    )
    .toBeGreaterThan(0)
  expect(await page.locator('script[src*="p5"]').count()).toBe(0)

  await attachConsole(watcher, testInfo)
  expect(watcher.errors()).toEqual([])
})

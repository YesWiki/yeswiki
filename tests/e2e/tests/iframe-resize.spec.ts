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

/** An iframe.auto-resize showing a wiki page through /iframe takes the height of that page, with no library (ticket 30). */
test('an embedded page sizes its iframe', async ({ page }, testInfo) => {
  const watcher = watchConsole(page)

  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const lines = Array.from(
    { length: 60 },
    (_, i) => `Ligne ${i + 1} de la page embarquée.`,
  ).join('\n\n')
  const inner = await signedPost(page, '/?api/pages/PageEmbarquee', {
    form: { body: lines },
  })
  expect(inner.ok()).toBeTruthy()
  const host = await signedPost(page, '/?api/pages/PageHote', {
    form: {
      body: '<iframe class="auto-resize" src="?PageEmbarquee/iframe" width="100%" frameborder="0"></iframe>',
    },
  })
  expect(host.ok()).toBeTruthy()

  await page.goto('/?PageHote')

  const frame = page.locator('iframe.auto-resize')
  await expect(frame).toBeVisible()
  await expect
    .poll(async () => (await frame.boundingBox())?.height ?? 0, {
      timeout: 15000,
    })
    .toBeGreaterThan(1000)
  const inside = page.frameLocator('iframe.auto-resize')
  await expect(inside.getByText('Ligne 60 de la page embarquée.')).toBeVisible()
  expect(await page.locator('script[src*="iframe-resizer"]').count()).toBe(0)

  await attachConsole(watcher, testInfo)
  expect(watcher.errors()).toEqual([])
})

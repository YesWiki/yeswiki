import { Page } from '@playwright/test'

const MIN_AGE_MS = 3500

/** Waits until the page's BotGuard token passes the minimum age. */
export const waitPastBotGuardMinimumAge = async (page: Page) => {
  const token = await page
    .locator('.yw-bot-guard-fields input[type="hidden"]')
    .first()
    .getAttribute('value', { timeout: 1000 })
    .catch(() => null)
  if (!token) return
  const issuedAt = Number(token.split('.')[0]) * 1000
  const wait = issuedAt + MIN_AGE_MS - Date.now()
  if (wait > 0) await page.waitForTimeout(wait)
}

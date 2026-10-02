import { Page } from '@playwright/test'

/** Waits past the minimum age BotGuard asks of an anonymous submission. */
export const waitPastBotGuardMinimumAge = (page: Page) =>
  page.waitForTimeout(3500)

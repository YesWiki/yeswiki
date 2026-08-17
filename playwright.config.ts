import { defineConfig, devices } from '@playwright/test'

/** See https://playwright.dev/docs/test-configuration. */
export default defineConfig({
  testDir: '.',
  testMatch: [
    /\/tests\/e2e\/.*\.spec.ts/,
    /\/tools\/.*\/tests\/e2e\/.*\.spec.ts/,
  ],
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: 'html',
  use: {
    baseURL: process.env.YESWIKI_BASE_URL || 'https://yeswiki.test',

    ignoreHTTPSErrors: true,

    locale: 'fr-FR',

    trace: 'on-first-retry',

    video: 'on-first-retry',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
})

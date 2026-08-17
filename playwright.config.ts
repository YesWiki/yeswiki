import { defineConfig, devices } from '@playwright/test'

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// import dotenv from 'dotenv';
// import path from 'path';
// dotenv.config({ path: path.resolve(__dirname, '.env') });

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: '.',
  testMatch: [
    /\/tests\/e2e\/.*\.spec.ts/,
    /\/tools\/.*\/tests\/e2e\/.*\.spec.ts/,
  ],
  /* Run tests in files in parallel */
  fullyParallel: false,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 1 : 0,
  /* Opt out of parallel tests on CI. */
  workers: 1,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: 'html',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`.
     *
     * Defaults to the local dev instance so `make test-e2e` works on a developer machine with
     * no arguments; override for the docker image or anywhere else:
     *   YESWIKI_BASE_URL=http://yeswiki-web yarn test-e2e
     */
    baseURL: process.env.YESWIKI_BASE_URL || 'https://yeswiki.test',

    /* local instances are served with a self-signed certificate */
    ignoreHTTPSErrors: true,

    /* The browser's language, and it is load-bearing.
     *
     * A wiki answers a first-time visitor in their own language when it offers it, ahead of
     * its own `default_language` -- so a headless Chromium asking for `en-US` would read this
     * French test wiki (which offers `en`) in English, and every assertion on a French label
     * would fail for a reason that has nothing to do with what it is testing. Saying `fr-FR`
     * here is the suite being a French reader, which is what it has always assumed it was. */
    locale: 'fr-FR',

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',

    video: 'on-first-retry',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    // {
    //   name: 'firefox',
    //   use: { ...devices['Desktop Firefox'] },
    // },
    //
    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },

    /* Test against mobile viewports. */
    // {
    //   name: 'Mobile Chrome',
    //   use: { ...devices['Pixel 5'] },
    // },
    // {
    //   name: 'Mobile Safari',
    //   use: { ...devices['iPhone 12'] },
    // },

    /* Test against branded browsers. */
    // {
    //   name: 'Microsoft Edge',
    //   use: { ...devices['Desktop Edge'], channel: 'msedge' },
    // },
    // {
    //   name: 'Google Chrome',
    //   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
    // },
  ],

  /* Run your local dev server before starting the tests */
  // webServer: {
  //   command: 'npm run start',
  //   url: 'http://127.0.0.1:3000',
  //   reuseExistingServer: !process.env.CI,
  // },
})

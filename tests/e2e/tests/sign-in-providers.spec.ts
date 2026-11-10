import { expect, Page, test } from '@playwright/test'
import {
  addToGroup,
  forgetAccounts,
  groupsOf,
  journal,
  PASSWORD,
  PEOPLE,
  personDn,
  PROVIDER_IDS,
  PROVIDERS,
  ProviderType,
  sectionFor,
  setDirectoryValue,
  setProviders,
  signInAtPortal,
} from '../helpers/sso'

/**
 * Every provider type against the real servers of the development stack (Step 0 of the SSO plan).
 * The task adding a type adds it here; the wiki is reset once for the file, so each test starts from the accounts it needs.
 */
const TYPES: ProviderType[] = ['oidc']

const GUEST_CARD = '.yw-account-guest__card'
const MARIE_ACCOUNT = 'marie-dupont'
const JEAN_ACCOUNT = 'jean-martin'

/** From the account page, out to the provider and back. */
const signInWith = async (page: Page, type: ProviderType, login: string) => {
  await page.goto('/?user')
  await page
    .locator(`${GUEST_CARD} .yw-sign-in__provider`, {
      hasText: String(PROVIDERS[type].label),
    })
    .click()
  await signInAtPortal(page, login, PASSWORD)
}

/** The settings form of the signed-in account. */
const accountField = (page: Page, field: string) =>
  page.locator(`form [name="${field}"]`).first()

for (const type of TYPES) {
  const id = PROVIDER_IDS[type]
  /** The sign-in rows of this provider written after the row `since`; a diagnostic that collapsed into an older one is not among them. */
  const loginRows = (since = 0) =>
    journal('login.%').filter(
      (row) =>
        Number(row.id) > since &&
        (row.action.endsWith(`.${id}`) ||
          (row.action === 'login.failed' && row.context.provider === id)),
    )
  const latestRow = () =>
    Math.max(0, ...journal('%').map((row) => Number(row.id)))

  test.describe(`${type} against the real server`, () => {
    test.beforeEach(() => {
      forgetAccounts(PEOPLE.marie.email, PEOPLE.jean.email)
      setProviders(sectionFor(type))
    })

    test(`${type}: a first sign-in creates the account`, async ({ page }) => {
      await signInWith(page, type, PEOPLE.marie.login)

      await page.goto('/?user')
      await expect(page.locator(GUEST_CARD)).toHaveCount(0)
      await expect(accountField(page, 'first_name')).toHaveValue('Marie')
      await expect(accountField(page, 'last_name')).toHaveValue('Dupont')
      await expect(page.locator('body')).toContainText(MARIE_ACCOUNT)
    })

    test(`${type}: a return visit refreshes the profile`, async ({
      browser,
    }) => {
      const first = await browser.newPage()
      await signInWith(first, type, PEOPLE.jean.login)
      await first.goto('/?user')
      await expect(accountField(first, 'last_name')).toHaveValue('Martin')
      await first.context().close()

      setDirectoryValue(personDn('jean'), 'sn', 'Martin-Durand')
      try {
        const again = await browser.newPage()
        await signInWith(again, type, PEOPLE.jean.login)
        await again.goto('/?user')
        await expect(accountField(again, 'last_name')).toHaveValue(
          'Martin-Durand',
        )
        await expect(again.locator('body')).toContainText(JEAN_ACCOUNT)
        await again.context().close()
      } finally {
        setDirectoryValue(personDn('jean'), 'sn', PEOPLE.jean.lastName)
      }
    })

    test(`${type}: mapped groups are added and removed`, async ({
      browser,
    }) => {
      setProviders(
        sectionFor(type, {
          group_mapping: [{ provider: 'wiki-editors', wiki: 'Editors' }],
        }),
      )
      const first = await browser.newPage()
      await signInWith(first, type, PEOPLE.marie.login)
      await first.context().close()
      expect(groupsOf(MARIE_ACCOUNT)).toContain('Editors')

      addToGroup('Handmade', MARIE_ACCOUNT)
      setProviders(
        sectionFor(type, {
          group_mapping: [{ provider: 'no-such-group', wiki: 'Editors' }],
        }),
      )
      const again = await browser.newPage()
      await signInWith(again, type, PEOPLE.marie.login)
      await again.context().close()

      const groups = groupsOf(MARIE_ACCOUNT)
      expect(groups, 'the mapping no longer applies').not.toContain('Editors')
      expect(groups, 'a group made by hand is left alone').toContain('Handmade')
    })

    test(`${type}: a refusal explains itself`, async ({ page }) => {
      setProviders(sectionFor(type, { create_accounts: false }))
      const before = latestRow()

      await signInWith(page, type, PEOPLE.marie.login)

      await expect(page.locator('h1')).toBeVisible()
      await expect(page.locator('body')).toContainText(
        String(PROVIDERS[type].label),
      )
      await expect(page.locator('body')).toContainText(PEOPLE.marie.email)

      const rows = loginRows(before)
      const audit = rows.find((row) => row.action === 'login.failed')
      expect(audit?.target).toBe(PEOPLE.marie.email)
      expect(audit?.context).toMatchObject({
        provider: id,
        reason: 'no_account',
      })
      const diagnostic = rows.find(
        (row) => row.action === `login.refused.no_account.${id}`,
      )
      expect(diagnostic, 'a diagnostic beside the audit row').toBeTruthy()
      expect(diagnostic?.context.attributes).toHaveProperty('mail')
      expect(JSON.stringify(diagnostic?.context)).not.toContain(
        'wiki-oidc-secret',
      )
    })

    test(`${type}: a success writes nothing to the journal`, async ({
      page,
    }) => {
      const before = latestRow()

      await signInWith(page, type, PEOPLE.marie.login)
      await page.goto('/?user')
      await expect(page.locator(GUEST_CARD)).toHaveCount(0)

      expect(loginRows(before)).toEqual([])
    })

    test.fixme(`${type}: test mode signs nobody in`, async () => {
      // Test mode arrives with Task 13.
    })

    test(`${type}: nomail is refused naming the attribute`, async ({
      page,
    }) => {
      const before = latestRow()

      await signInWith(page, type, PEOPLE.nomail.login)

      await expect(page.locator('body')).toContainText(/["«]\s?mail\s?["»]/)
      const diagnostic = loginRows(before).find(
        (row) => row.action === `login.refused.missing_attribute.${id}`,
      )
      expect(diagnostic, 'a diagnostic for the missing attribute').toBeTruthy()
      expect(String(diagnostic?.context.error)).toContain('"mail"')
    })

    if (type === 'oidc') {
      test('oidc: explicit endpoints and the legacy callback complete on /?api/auth_sso/callback=', async ({
        page,
      }) => {
        const legacy = 'http://wiki.yeswiki.test/?api/auth_sso/callback='
        setProviders(
          sectionFor('oidc', {
            connection: {
              authorize_url: 'http://auth.yeswiki.test/oauth2/authorize',
              token_url: 'http://auth.yeswiki.test/oauth2/token',
              userinfo_url: 'http://auth.yeswiki.test/oauth2/userinfo',
              client_id: 'wiki-oidc',
              client_secret: 'wiki-oidc-secret',
              legacy_callback: legacy,
            },
          }),
        )
        const callbacks: string[] = []
        page.on('request', (request) => {
          const url = decodeURIComponent(request.url())
          if (
            url.startsWith('http://wiki.yeswiki.test/') &&
            url.includes('callback')
          ) {
            callbacks.push(url)
          }
        })

        await signInWith(page, 'oidc', PEOPLE.marie.login)

        expect(
          callbacks,
          'the portal sent the browser back to the old address, and only there',
        ).toEqual([expect.stringMatching(/\?api\/auth_sso\/callback=&.*code=/)])
        await page.goto('/?user')
        await expect(page.locator(GUEST_CARD)).toHaveCount(0)
        await expect(accountField(page, 'last_name')).toHaveValue('Dupont')
      })
    }
  })
}

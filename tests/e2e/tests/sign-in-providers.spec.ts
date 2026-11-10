import { expect, Page, test } from '@playwright/test'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import {
  accountWithEmail,
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
const TYPES: ProviderType[] = ['oidc', 'saml']

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

/** A field of the signed-in account's settings that its provider fills, shown as text since it cannot be edited there. */
const accountField = (page: Page, field: string) =>
  page.locator(`[data-id="${field}"] .BAZ_texte`).first()

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
      await expect(accountField(page, 'first_name')).toHaveText('Marie')
      await expect(accountField(page, 'last_name')).toHaveText('Dupont')
      await expect(page.locator('body')).toContainText(MARIE_ACCOUNT)
    })

    test(`${type}: a return visit refreshes the profile`, async ({
      browser,
    }) => {
      const first = await browser.newPage()
      await signInWith(first, type, PEOPLE.jean.login)
      await first.goto('/?user')
      await expect(accountField(first, 'last_name')).toHaveText('Martin')
      await first.context().close()

      setDirectoryValue(personDn('jean'), 'sn', 'Martin-Durand')
      try {
        const again = await browser.newPage()
        await signInWith(again, type, PEOPLE.jean.login)
        await again.goto('/?user')
        await expect(accountField(again, 'last_name')).toHaveText(
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
      expect(
        JSON.stringify(rows),
        'what was sent is never recorded, only what it said',
      ).not.toMatch(/SAMLResponse|saml2?p?:Assertion|BEGIN [A-Z ]*KEY/)

      // and the screen reads it back: filter on the target, then open the detail
      await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
      await page.goto('/?admin/logs')
      const filtered = page.waitForResponse(
        (response) =>
          response.url().includes('api/admin/logs') &&
          response.url().includes('target='),
      )
      await page.locator('#yw-logs-target').fill(PEOPLE.marie.email)
      await filtered
      const table = page.locator('#yw-logs-table')
      await expect(table).toContainText('login.refused.no_account.' + id)
      await expect(table).toContainText(PEOPLE.marie.email)
      // the types tested before this one were refused for the same person
      const editLink = `a[href*="admin/sign-in/${id}/edit"]`
      const detail = table
        .locator('.yw-logs__sign-in')
        .filter({ has: page.locator(editLink) })
      await expect(detail).toHaveCount(1)
      const edit = detail.locator(editLink)
      // the table is fetched again as the filter settles, which closes a detail opened too early
      await expect(async () => {
        if (!(await edit.isVisible())) await detail.locator('summary').click()
        await expect(edit).toBeVisible({ timeout: 500 })
      }).toPass()
      await expect(detail).toContainText('mail')
      await expect(detail).toContainText(PEOPLE.marie.email)
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

    test(`${type}: test mode signs nobody in`, async ({ page }) => {
      // switched off: an administrator tries a provider out before anyone can use it.
      setProviders(sectionFor(type, { enabled: false }))
      await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
      const before = latestRow()

      await page.goto('/?admin/sign-in')
      await page.locator(`a[href*="admin/sign-in/${id}/test"]`).click()
      await signInAtPortal(page, PEOPLE.marie.login, PASSWORD)

      await expect(page.locator('.yw-sign-in-test__banner')).toBeVisible()
      const attributes = page.locator('.yw-sign-in-test__attributes')
      await expect(attributes).toContainText('mail')
      await expect(attributes).toContainText(PEOPLE.marie.email)
      await expect(attributes).toContainText(PEOPLE.marie.lastName)
      await expect(
        page.locator('.yw-sign-in-test__would'),
        'what a real sign-in would do',
      ).toContainText(MARIE_ACCOUNT)
      expect(
        accountWithEmail(PEOPLE.marie.email),
        'no account was created',
      ).toBeNull()
      expect(loginRows(before), 'nothing was written to the journal').toEqual(
        [],
      )

      await page.goto('/?user')
      await expect(page.locator(GUEST_CARD)).toHaveCount(0)
      await expect(
        page.locator('body'),
        'the administrator is still the one signed in',
      ).toContainText(ADMIN_USERNAME)
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
        await expect(accountField(page, 'last_name')).toHaveText('Dupont')
      })
    }

    if (type === 'saml') {
      test('saml: the metadata names the callback, at the address the portal knows as entity ID', async ({
        request,
      }) => {
        const entityId =
          'http://wiki.yeswiki.test/?user/sign-in/saml-a/metadata'
        const response = await request.get(entityId)

        expect(response.status()).toBe(200)
        expect(response.headers()['content-type']).toContain('xml')
        const xml = await response.text()
        expect(xml).toContain(`entityID="${entityId}"`)
        expect(xml).toContain(
          'Location="http://wiki.yeswiki.test/?user/sign-in/saml-a/callback"',
        )
      })

      test('saml: two providers at once each link their own account', async ({
        browser,
      }) => {
        const labels = { 'saml-a': 'Acme SAML A', 'saml-b': 'Acme SAML B' }
        setProviders({
          local: { type: 'local' },
          'saml-a': { ...PROVIDERS.saml, label: labels['saml-a'] },
          'saml-b': { ...PROVIDERS.saml, label: labels['saml-b'] },
        })
        // a new browser each time: the portal would otherwise remember who signed in last
        const through = async (
          provider: 'saml-a' | 'saml-b',
          login: string,
        ) => {
          const page = await browser.newPage()
          await page.goto('/?user')
          await page
            .locator(`${GUEST_CARD} .yw-sign-in__provider`, {
              hasText: labels[provider],
            })
            .click()
          await signInAtPortal(page, login, PASSWORD)

          return page
        }

        for (const [provider, person, account] of [
          ['saml-a', PEOPLE.marie, MARIE_ACCOUNT],
          ['saml-b', PEOPLE.jean, JEAN_ACCOUNT],
        ] as const) {
          const page = await through(provider, person.login)
          await page.goto('/?user')
          await expect(page.locator(GUEST_CARD)).toHaveCount(0)
          await expect(page.locator('body')).toContainText(account)
          await expect(
            page.locator('.alert-info', { hasText: labels[provider] }),
            'the account says which provider it belongs to',
          ).toHaveCount(1)
          await page.context().close()
        }

        // marie's account is saml-a's: saml-b cannot take it over
        const before = latestRow()
        const refused = await through('saml-b', PEOPLE.marie.login)
        await expect(refused.locator('body')).toContainText(labels['saml-b'])
        const audit = journal('login.failed').find(
          (row) => Number(row.id) > before && row.context.provider === 'saml-b',
        )
        expect(audit?.context.reason).toBe('linked_elsewhere')
        await refused.context().close()
      })
    }
  })
}

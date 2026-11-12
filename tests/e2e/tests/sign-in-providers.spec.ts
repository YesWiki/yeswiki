import { expect, Page, test } from '@playwright/test'
import {
  idpSignsIn,
  IDP,
  Person,
  RedirectType,
  samlSignsIn,
  WIKI,
} from '../helpers/idp'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { mockReady, mockReset } from '../helpers/mockserver'
import {
  accountWithEmail,
  addToGroup,
  forgetAccounts,
  groupsOf,
  journal,
  PASSWORD,
  PASSWORD_TYPES,
  PEOPLE,
  personDn,
  PROVIDER_IDS,
  PROVIDERS,
  ProviderType,
  sectionFor,
  SENT_OR_SECRET,
  setDirectoryValue,
  setProviders,
  signInAtWikiForm,
  testAtWikiForm,
  throughProvider,
} from '../helpers/sso'

/**
 * Every provider type: OpenID Connect, SAML and CAS against the mock identity provider, LDAP against the directory of the
 * development stack. The wiki is reset once for the file, so each test starts from the accounts it needs.
 */
const TYPES: ProviderType[] = ['oidc', 'saml', 'cas', 'ldap']

test.beforeAll(mockReady)

const GUEST_CARD = '.yw-account-guest__card'
const MARIE_ACCOUNT = 'marie-dupont'
const JEAN_ACCOUNT = 'jean-martin'

/**
 * From the account page, out to the provider and back as `person`, whom the mock identity provider signs in; or, for a
 * directory, through the wiki's own form under its tab.
 */
const signInWith = async (page: Page, type: ProviderType, person: Person) => {
  await page.goto('/?user')
  const label = String(PROVIDERS[type].label)
  if (PASSWORD_TYPES.includes(type)) {
    await signInAtWikiForm(page, PROVIDER_IDS[type], person.login, PASSWORD)

    return
  }
  await idpSignsIn(type as RedirectType, person)
  await throughProvider(page, () =>
    page
      .locator(`${GUEST_CARD} .yw-sign-in__provider`, { hasText: label })
      .click(),
  )
}

/**
 * An administrator's test of the provider, from the sign-in screen, as `person`: out to the mock identity provider and back,
 * or through the test page's own form for a password.
 */
const testAs = async (page: Page, type: ProviderType, person: Person) => {
  const link = page.locator(
    `a[href*="admin/sign-in/${PROVIDER_IDS[type]}/test"]`,
  )
  if (PASSWORD_TYPES.includes(type)) {
    await link.click()
    await testAtWikiForm(page, person.login, PASSWORD)

    return
  }
  await idpSignsIn(type as RedirectType, person)
  await throughProvider(page, () => link.click())
}

/**
 * What the wiki tells the person of a refusal: the refused page, or for a password provider the message the sign-in form
 * comes back with (shown as a toast, from the page's flash).
 */
const refusalSays = async (
  page: Page,
  type: ProviderType,
  expected: string | RegExp,
) => {
  if (!PASSWORD_TYPES.includes(type)) {
    await expect(page.locator('body')).toContainText(expected)

    return
  }
  const pattern =
    typeof expected === 'string'
      ? new RegExp(expected.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
      : expected
  await expect(page.locator('[data-yw-flash]')).toHaveAttribute(
    'data-yw-flash',
    pattern,
  )
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

  test.describe(`${type} against the mock identity provider`, () => {
    test.beforeEach(async () => {
      await mockReset()
      forgetAccounts(PEOPLE.marie.email, PEOPLE.jean.email)
      setProviders(sectionFor(type))
    })

    test(`${type}: a first sign-in creates the account`, async ({ page }) => {
      await signInWith(page, type, PEOPLE.marie)

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
      await signInWith(first, type, PEOPLE.jean)
      await first.goto('/?user')
      await expect(accountField(first, 'last_name')).toHaveText('Martin')
      await first.context().close()

      // the directory changes for LDAP; the mock identity provider is told the new name for the others
      const renamed = { ...PEOPLE.jean, lastName: 'Martin-Durand' }
      if (type === 'ldap') {
        setDirectoryValue(personDn('jean'), 'sn', renamed.lastName)
      }
      try {
        const again = await browser.newPage()
        await signInWith(again, type, renamed)
        await again.goto('/?user')
        await expect(accountField(again, 'last_name')).toHaveText(
          'Martin-Durand',
        )
        await expect(again.locator('body')).toContainText(JEAN_ACCOUNT)
        await again.context().close()
      } finally {
        if (type === 'ldap') {
          setDirectoryValue(personDn('jean'), 'sn', PEOPLE.jean.lastName)
        }
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
      await signInWith(first, type, PEOPLE.marie)
      await first.context().close()
      expect(groupsOf(MARIE_ACCOUNT)).toContain('Editors')

      addToGroup('Handmade', MARIE_ACCOUNT)
      setProviders(
        sectionFor(type, {
          group_mapping: [{ provider: 'no-such-group', wiki: 'Editors' }],
        }),
      )
      const again = await browser.newPage()
      await signInWith(again, type, PEOPLE.marie)
      await again.context().close()

      const groups = groupsOf(MARIE_ACCOUNT)
      expect(groups, 'the mapping no longer applies').not.toContain('Editors')
      expect(groups, 'a group made by hand is left alone').toContain('Handmade')
    })

    test(`${type}: a refusal explains itself`, async ({ page }) => {
      setProviders(sectionFor(type, { create_accounts: false }))
      const before = latestRow()

      // a password provider records the login typed; the others the person's email
      const who = PASSWORD_TYPES.includes(type)
        ? PEOPLE.marie.login
        : PEOPLE.marie.email

      await signInWith(page, type, PEOPLE.marie)

      await expect(page.locator('h1')).toBeVisible()
      await refusalSays(page, type, String(PROVIDERS[type].label))
      await refusalSays(page, type, who)

      const rows = loginRows(before)
      const audit = rows.find((row) => row.action === 'login.failed')
      expect(audit?.target).toBe(who)
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
      ).not.toMatch(SENT_OR_SECRET)
      expect(
        JSON.stringify(rows),
        'nor the password, which a directory lets a person read of their own entry',
      ).not.toContain(PASSWORD)
      await expect(page.locator('body')).not.toContainText(PASSWORD)

      // and the screen reads it back: filter on the target, then open the detail
      await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
      await page.goto('/?admin/logs')
      const filtered = page.waitForResponse(
        (response) =>
          response.url().includes('api/admin/logs') &&
          response.url().includes('target='),
      )
      await page.locator('#yw-logs-target').fill(who)
      await filtered
      const table = page.locator('#yw-logs-table')
      await expect(table).toContainText('login.refused.no_account.' + id)
      await expect(table).toContainText(who)
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

      await signInWith(page, type, PEOPLE.marie)
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
      await testAs(page, type, PEOPLE.marie)

      await expect(page.locator('.yw-sign-in-test__banner')).toBeVisible()
      const attributes = page.locator('.yw-sign-in-test__attributes')
      await expect(attributes).toContainText('mail')
      await expect(attributes).toContainText(PEOPLE.marie.email)
      await expect(attributes).toContainText(PEOPLE.marie.lastName)
      expect(
        await page.content(),
        'the password is on no page, not even among the attributes read',
      ).not.toContain(PASSWORD)
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

      await signInWith(page, type, PEOPLE.nomail)

      await refusalSays(page, type, /["«]\s?mail\s?["»]/)
      const diagnostic = loginRows(before).find(
        (row) => row.action === `login.refused.missing_attribute.${id}`,
      )
      expect(diagnostic, 'a diagnostic for the missing attribute').toBeTruthy()
      expect(String(diagnostic?.context.error)).toContain('"mail"')
    })

    if (type === 'oidc') {
      test('oidc: explicit endpoints complete on the provider callback', async ({
        page,
      }) => {
        setProviders(
          sectionFor('oidc', {
            connection: {
              authorize_url: `${IDP}/authorize`,
              token_url: `${IDP}/token`,
              userinfo_url: `${IDP}/userinfo`,
              client_id: 'wiki-oidc',
              client_secret: 'wiki-oidc-secret',
            },
          }),
        )
        const callbacks: string[] = []
        page.on('request', (request) => {
          const url = decodeURIComponent(request.url())
          if (url.startsWith(`${WIKI}/`) && url.includes('callback')) {
            callbacks.push(url)
          }
        })

        await signInWith(page, 'oidc', PEOPLE.marie)

        expect(
          callbacks,
          'the provider sent the browser back to the provider callback, and only there',
        ).toEqual([
          expect.stringMatching(/\?user\/sign-in\/oidc\/callback&.*code=/),
        ])
        await page.goto('/?user')
        await expect(page.locator(GUEST_CARD)).toHaveCount(0)
        await expect(accountField(page, 'last_name')).toHaveText('Dupont')
      })
    }

    if (type === 'saml') {
      test('saml: the metadata names the callback, at the address the identity provider knows as entity ID', async ({
        request,
      }) => {
        const entityId = `${WIKI}/?user/sign-in/saml-a/metadata`
        const response = await request.get(entityId)

        expect(response.status()).toBe(200)
        expect(response.headers()['content-type']).toContain('xml')
        const xml = await response.text()
        expect(xml).toContain(`entityID="${entityId}"`)
        expect(xml).toContain(
          `Location="${WIKI}/?user/sign-in/saml-a/callback"`,
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
        // the mock identity provider serves one SAML provider at a time: it is told which before each sign-in
        const through = async (
          provider: 'saml-a' | 'saml-b',
          person: Person,
        ) => {
          await samlSignsIn(person, { providerId: provider })
          const page = await browser.newPage()
          await page.goto('/?user')
          await throughProvider(page, () =>
            page
              .locator(`${GUEST_CARD} .yw-sign-in__provider`, {
                hasText: labels[provider],
              })
              .click(),
          )

          return page
        }

        for (const [provider, person, account] of [
          ['saml-a', PEOPLE.marie, MARIE_ACCOUNT],
          ['saml-b', PEOPLE.jean, JEAN_ACCOUNT],
        ] as const) {
          const page = await through(provider, person)
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
        const refused = await through('saml-b', PEOPLE.marie)
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

/**
 * Accounts of the wiki stay a way to sign in beside a provider (spec section 8.1): the form offers a tab for each, and what
 * belongs to local accounts alone -- lost password, remember me, sign up -- shows under its tab only.
 */
test.describe('local accounts beside a provider', () => {
  test.beforeEach(() => {
    setProviders(sectionFor('ldap'))
  })

  test('lost password and sign-up show under the local tab only', async ({
    page,
  }) => {
    await page.goto('/?user')
    const form = page.locator('form.login-form')
    const tab = (id: string) =>
      form
        .locator('.yw-sign-in__tab')
        .filter({ has: page.locator(`input[name="provider"][value="${id}"]`) })
    const localOnly = form.locator(
      'a[href*="lost-password"], a[href*="user/signup"], #remember-default',
    )
    expect(await localOnly.count(), 'local has its extras').toBeGreaterThan(0)

    await tab('local').click()
    for (const extra of await localOnly.all()) {
      await expect(extra).toBeVisible()
    }

    await tab(PROVIDER_IDS.ldap).click()
    await expect(
      form.locator(`input[name="provider"][value="${PROVIDER_IDS.ldap}"]`),
    ).toBeChecked()
    for (const extra of await localOnly.all()) {
      await expect(extra).toBeHidden()
    }
  })

  test('a local account still signs in through its tab', async ({ page }) => {
    await page.goto('/?user')
    await signInAtWikiForm(page, 'local', ADMIN_USERNAME, ADMIN_PASSWORD)

    await page.goto('/?user')
    await expect(page.locator(GUEST_CARD)).toHaveCount(0)
    await expect(page.locator('body')).toContainText(ADMIN_USERNAME)
  })
})

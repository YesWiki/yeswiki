import { expect, Page, test } from '@playwright/test'
import {
  CAS_VALIDATE,
  casSignsIn,
  idpDenies,
  idpEndpointFails,
  idpSignsIn,
  oidcSignsIn,
  RedirectType,
  samlSignsIn,
  WIKI,
} from '../helpers/idp'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { mockReady, mockReset } from '../helpers/mockserver'
import {
  accountWithEmail,
  forgetAccounts,
  forgetDiagnostics,
  journal,
  SENT_OR_SECRET,
  PEOPLE,
  PROVIDER_IDS,
  PROVIDERS,
  sectionFor,
  setProviders,
  throughProvider,
} from '../helpers/sso'

/**
 * What a person and an administrator get when an identity provider fails (spec section 7.2 of the MockServer design): the mock
 * identity provider is made to fail in one way, Marie tries to sign in, and the wiki refuses her for the reason that fits,
 * with a diagnostic that says what went wrong.
 */
type Case = {
  type: RedirectType
  name: string
  /** Configures the mock identity provider, for Marie. */
  arrange: () => Promise<void>
  /** The FailureReason the wiki records. */
  reason: string
  /** The stable part of the diagnostic's error. */
  detail: string
}

const marie = PEOPLE.marie

/**
 * One row per failure. A regression seen on a real server is one more row, with a comment naming the server and its version,
 * and an `arrange` that makes the mock answer as that server did.
 */
const CASES: Case[] = [
  {
    type: 'oidc',
    name: 'tampered ID token signature',
    arrange: () => oidcSignsIn(marie, { flags: ['tamperedSignature'] }),
    reason: 'invalid_response',
    detail: 'Signature verification failed',
  },
  {
    type: 'oidc',
    name: 'expired ID token',
    arrange: () => oidcSignsIn(marie, { flags: ['issueExpiredToken'] }),
    reason: 'invalid_response',
    detail: 'the ID token has expired',
  },
  {
    type: 'oidc',
    name: 'wrong issuer',
    arrange: () => oidcSignsIn(marie, { flags: ['wrongIssuer'] }),
    reason: 'invalid_response',
    detail: 'the ID token has the issuer',
  },
  {
    type: 'oidc',
    name: 'the person refuses',
    arrange: async () => {
      await oidcSignsIn(marie)
      await idpDenies('oidc')
    },
    reason: 'provider_rejected',
    detail: 'access_denied',
  },
  {
    type: 'oidc',
    name: 'token endpoint fails',
    arrange: async () => {
      await oidcSignsIn(marie)
      await idpEndpointFails('/token', { status: 500, body: 'boom' })
    },
    reason: 'invalid_response',
    detail: 'the token endpoint answered something unreadable',
  },
  {
    type: 'oidc',
    name: 'token endpoint drops the connection',
    arrange: async () => {
      await oidcSignsIn(marie)
      await idpEndpointFails('/token', 'drop')
    },
    reason: 'provider_unreachable',
    detail: 'the token endpoint could not be reached',
  },
  {
    type: 'oidc',
    name: 'secret refused',
    arrange: () => oidcSignsIn(marie, { clientSecret: 'another-secret' }),
    reason: 'provider_rejected',
    detail: 'invalid_client',
  },
  {
    type: 'oidc',
    name: 'discovery unreachable',
    arrange: async () => {
      await oidcSignsIn(marie)
      await idpEndpointFails('/.well-known/openid-configuration', 'drop')
    },
    reason: 'provider_unreachable',
    detail: 'the discovery document at',
  },
  {
    type: 'saml',
    name: 'tampered signature',
    arrange: () => samlSignsIn(marie, { flags: ['tamperedSignature'] }),
    reason: 'invalid_response',
    detail: 'Signature validation failed',
  },
  {
    type: 'saml',
    name: 'expired assertion',
    arrange: () => samlSignsIn(marie, { flags: ['expiredAssertion'] }),
    reason: 'invalid_response',
    detail: 'Could not validate timestamp: expired',
  },
  {
    type: 'saml',
    name: 'wrong audience',
    arrange: () => samlSignsIn(marie, { flags: ['wrongAudience'] }),
    reason: 'invalid_response',
    detail: 'Invalid audience',
  },
  {
    type: 'saml',
    name: 'status failure',
    arrange: async () => {
      await samlSignsIn(marie)
      await idpDenies('saml')
    },
    reason: 'provider_rejected',
    detail: 'was not Success',
  },
  {
    type: 'saml',
    name: 'metadata unreachable',
    arrange: async () => {
      await samlSignsIn(marie)
      await idpEndpointFails('/saml/metadata', 'drop')
    },
    reason: 'provider_unreachable',
    detail: 'could not be fetched',
  },
  {
    type: 'cas',
    name: 'ticket refused',
    arrange: async () => {
      await casSignsIn(marie)
      await idpDenies('cas')
    },
    reason: 'provider_rejected',
    detail: 'the CAS server refused the ticket: INVALID_TICKET',
  },
  {
    type: 'cas',
    name: 'ticket validation drops the connection',
    arrange: async () => {
      await casSignsIn(marie)
      await idpEndpointFails(CAS_VALIDATE, 'drop')
    },
    reason: 'provider_unreachable',
    detail: 'could not be reached to validate the ticket',
  },
]

const GUEST_CARD = '.yw-account-guest__card'

/** The SAMLResponse values the browser posts to the wiki, as posted (base64). */
const postedSamlResponses = (page: Page): string[] => {
  const posted: string[] = []
  page.on('request', (request) => {
    const value = new URLSearchParams(request.postData() ?? '').get(
      'SAMLResponse',
    )
    if (request.method() === 'POST' && value) {
      posted.push(value)
    }
  })

  return posted
}

const latestRow = () =>
  Math.max(0, ...journal('%').map((row) => Number(row.id)))

/** From the account page, out through the provider's button, and back to the page the wiki answers with, or straight to it. */
const tryToSignIn = async (page: Page, type: RedirectType) => {
  await page.goto('/?user')
  await page
    .locator(`${GUEST_CARD} .yw-sign-in__provider`, {
      hasText: String(PROVIDERS[type].label),
    })
    .click()
  // a failure before the browser leaves the wiki is shown at the sign-in's own address
  await page.waitForURL(
    (url) =>
      url.origin === new URL(WIKI).origin &&
      url.search.startsWith('?user/sign-in/'),
    { waitUntil: 'domcontentloaded' },
  )
}

test.beforeAll(mockReady)

test.describe('a provider that fails', () => {
  test.beforeEach(async () => {
    await mockReset()
    forgetAccounts(marie.email)
    // a diagnostic collapses into the day's row of its provider and reason: an earlier one would answer for this test's
    forgetDiagnostics()
    expect(journal('login.refused.%')).toEqual([])
  })

  test.afterAll(() => {
    forgetAccounts(marie.email)
    setProviders({ local: { type: 'local' } })
  })

  for (const { type, name, arrange, reason, detail } of CASES) {
    test(`${type}: ${name}`, async ({ page }) => {
      const id = PROVIDER_IDS[type]
      setProviders(sectionFor(type))
      const before = latestRow()
      await arrange()
      const posted = postedSamlResponses(page)

      await tryToSignIn(page, type)

      await expect(page.locator('h1')).toBeVisible()
      await expect(page.locator('body')).toContainText(
        String(PROVIDERS[type].label),
      )
      const rows = journal('login.%').filter((row) => Number(row.id) > before)
      expect(
        rows.find((row) => row.action === 'login.failed')?.context,
      ).toMatchObject({ provider: id, reason })
      const diagnostic = rows.find(
        (row) => row.action === `login.refused.${reason}.${id}`,
      )
      expect(diagnostic, 'a diagnostic beside the audit row').toBeTruthy()
      expect(String(diagnostic?.context.error)).toContain(detail)
      expect(
        JSON.stringify(rows),
        'neither a secret, nor what was sent, nor a key is recorded',
      ).not.toMatch(SENT_OR_SECRET)
      // the check above would see what was sent, had it been recorded
      for (const response of posted) {
        expect(response).toMatch(SENT_OR_SECRET)
        expect(Buffer.from(response, 'base64').toString()).toMatch(
          SENT_OR_SECRET,
        )
      }
      await page.goto('/?user')
      await expect(
        page.locator(GUEST_CARD),
        'nobody is signed in',
      ).toBeVisible()

      // the mock answers as it should again: the failure was this sign-in's alone
      await mockReset()
      await idpSignsIn(type, marie)
      await throughProvider(page, () =>
        page
          .locator(`${GUEST_CARD} .yw-sign-in__provider`, {
            hasText: String(PROVIDERS[type].label),
          })
          .click(),
      )
      await page.goto('/?user')
      await expect(page.locator(GUEST_CARD)).toHaveCount(0)
    })
  }

  test("oidc: an administrator's test shows what failed", async ({ page }) => {
    // switched off: an administrator tries a provider out before anyone can use it
    setProviders(sectionFor('oidc', { enabled: false }))
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
    await oidcSignsIn(marie, { flags: ['tamperedSignature'] })

    await page.goto('/?admin/sign-in')
    await throughProvider(page, () =>
      page
        .locator(`a[href*="admin/sign-in/${PROVIDER_IDS.oidc}/test"]`)
        .click(),
    )

    await expect(page.locator('body')).toContainText(
      'Signature verification failed',
    )
    expect(accountWithEmail(marie.email), 'no account was created').toBeNull()
    await page.goto('/?user')
    await expect(page.locator(GUEST_CARD)).toHaveCount(0)
    await expect(
      page.locator('body'),
      'the administrator is still the one signed in',
    ).toContainText(ADMIN_USERNAME)
  })
})

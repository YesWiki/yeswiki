import { execFileSync } from 'child_process'
import { createHash } from 'crypto'
import { fileURLToPath } from 'url'
import { Browser, expect, Page, test } from '@playwright/test'
import { instanceDir } from '../helpers/instance'
import {
  accountWithEmail,
  forgetAttempts,
  groupsOf,
  journal,
  LDAP_URI,
  PASSWORD,
  PEOPLE,
  PEOPLE_BASE,
  PORTAL,
  PORTAL_HOST,
  setProviders,
  signInAtPortal,
  signInAtWikiForm,
} from '../helpers/sso'

/**
 * The upgrade from Doryphore, for each login extension (spec section 13): a wiki that signed in through loginsso, loginldap or
 * logincas is put back as Doryphore left it, upgraded again, and its people sign in against the real servers of the development
 * stack. Marie reaches the account she had, nobody gets a second one, and loginsso's provider keeps the callback registered at it.
 */

/** The wiki as the portal knows it: CI serves it on a port, the development stack on the bare name. */
const WIKI = (
  process.env.YESWIKI_BASE_URL || 'http://wiki.yeswiki.test'
).replace(/\/$/, '')

/** The address loginsso registered at its provider, which the converted provider keeps sending. */
const LEGACY_CALLBACK = `${WIKI}/?api/auth_sso/callback=`

const HANDMADE = 'DoryphoreTeam'
const GUEST_CARD = '.yw-account-guest__card'
const md5 = (text: string): string =>
  createHash('md5').update(text).digest('hex')

type Account = {
  body: Record<string, unknown>
  password: 'none' | 'md5' | 'other'
  revisions: number
  md5_revisions: number
}
type Provider = Record<string, any>

const fixturePhp = fileURLToPath(
  new URL('../helpers/doryphore-fixture.php', import.meta.url),
)
const fixture = (...args: string[]): string =>
  execFileSync('php', [fixturePhp, ...args], {
    cwd: instanceDir(),
    encoding: 'utf8',
  })

/** How long the web server's opcode cache may keep serving the previous configuration file, as in sso.ts. */
const waitForTheConfiguration = (): void => {
  Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 3100)
}

/** Puts the wiki back as Doryphore left it with this extension, then runs the upgrade's migrations; returns what they said. */
const loadDoryphore = (dory: {
  tool: string
  config: Record<string, unknown>
  accounts: Record<string, string | null>[]
  pages?: Record<string, string>
  groups?: Record<string, string[]>
}): string[] => {
  const said = JSON.parse(
    fixture(
      'load',
      JSON.stringify({
        tools: [dory.tool],
        config: dory.config,
        accounts: dory.accounts,
        pages: dory.pages ?? {},
        groups: dory.groups ?? {},
      }),
    ),
  ) as string[]
  waitForTheConfiguration()

  return said
}

const account = (tag: string): Account | null =>
  JSON.parse(fixture('account', tag))
const accounts = (): string[] => JSON.parse(fixture('accounts'))
const converted = (): {
  providers: Record<string, Provider>
  doryphore_keys: string[]
} => JSON.parse(fixture('providers'))

/** The highest Journal id: the rows a sign-in writes come after it. */
const latestRow = (): number =>
  Math.max(0, ...journal('%').map((row) => Number(row.id)))

/** Days from today to a `Y-m-d` date. */
const daysUntil = (date: string): number =>
  Math.round(
    (new Date(`${date}T00:00:00Z`).getTime() - Date.now()) / 86_400_000,
  )

/** What the upgrade must have done to the section, whichever the extension: one provider on, local off, the old keys gone. */
const expectInCharge = (id: string, type: string): Provider => {
  const { providers, doryphore_keys } = converted()
  expect(doryphore_keys, "the extension's keys are removed").toEqual([])
  expect(
    providers.local,
    'local accounts are off, as on Doryphore',
  ).toMatchObject({ enabled: false })
  expect(providers[id]).toMatchObject({
    type,
    enabled: true,
    match_by_email: true,
  })

  return providers[id]
}

/** Marie's account after her sign-in: the one she had, linked, with what it held and a group made by hand kept. */
const expectHerAccount = (
  tag: string,
  before: Account,
  everyone: string[],
  providerId: string,
) => {
  expect(accounts(), 'nobody got a second account').toEqual(everyone)
  expect(accountWithEmail(PEOPLE.marie.email)).toBe(tag)
  const after = account(tag)
  expect(after?.body).toMatchObject({
    motto: before.body.motto,
    signuptime: before.body.signuptime,
    sso_provider: providerId,
    sso_subject: PEOPLE.marie.login,
  })
  expect(groupsOf(tag), 'a group made by hand on Doryphore is kept').toContain(
    HANDMADE,
  )
}

/** The account page shows Marie signed in, under the account she had. */
const expectSignedInAs = async (page: Page, tag: string) => {
  await page.goto('/?user')
  await expect(page.locator(GUEST_CARD)).toHaveCount(0)
  await expect(page.locator('body')).toContainText(tag)
}

/** From the account page, out to the portal through the provider's button and back, in a browser of its own. */
const signInThroughPortal = async (
  browser: Browser,
  label: string,
): Promise<Page> => {
  const page = await (await browser.newContext()).newPage()
  await page.goto('/?user')
  await page
    .locator(`${GUEST_CARD} .yw-sign-in__provider`, { hasText: label })
    .click()
  await signInAtPortal(page, PEOPLE.marie.login, PASSWORD)

  return page
}

test.describe('the upgrade from Doryphore', () => {
  test.beforeEach(() => {
    forgetAttempts(PEOPLE.marie.login)
  })

  test.afterAll(() => {
    fixture('reset')
    waitForTheConfiguration()
  })

  test('loginsso: Marie signs in to her account through the callback registered at the provider', async ({
    browser,
  }) => {
    const tag = 'MarieDupont'
    const said = loadDoryphore({
      tool: 'loginsso',
      config: {
        sso_config: {
          providers: [
            {
              auth_type: 'oauth2',
              auth_options: {
                clientId: 'wiki-oidc',
                clientSecret: 'wiki-oidc-secret',
                urlAuthorize: `${PORTAL}/oauth2/authorize`,
                urlAccessToken: `${PORTAL}/oauth2/token`,
                urlResourceOwnerDetails: `${PORTAL}/oauth2/userinfo`,
                scopes: ['openid'],
                addFinalEqual: true,
              },
              id_sso_field: 'sub',
              email_sso_field: 'email',
              groups_sso_field: 'groups',
              groups_sso_mapping: { 'wiki-editors': 'Editors' },
              create_user_from: '#[given_name] #[family_name]',
              button_style: {
                button_label: 'Acme SSO',
                button_class: 'btn btn-default',
                button_icon: 'glyphicon glyphicon-log-in',
              },
            },
          ],
          bazar_mapping: {
            fields: { bf_prenom: 'given_name', bf_nom: 'family_name' },
          },
        },
      },
      accounts: [
        // an email of her own on Doryphore: only the user ID loginsso stored can find her account
        {
          name: tag,
          email: 'marie@doryphore.example',
          password: md5('random-loginsso-password'),
          motto: 'Marie, since Doryphore',
          loginsso_id: PEOPLE.marie.login,
        },
        {
          name: 'JeanMartin',
          email: PEOPLE.jean.email,
          password: md5('JeansOldPassword1'),
        },
      ],
      pages: {
        PageMenuUser:
          '[Mes paramètres](ParametresUtilisateur)\n{{linktouserprofil dash="1"}}',
        UserEntries: '{{userentries}}',
      },
      groups: { [HANDMADE]: [tag] },
    })
    expect(said.join('\n')).toContain('LoginssoActionsBecomeLinks')

    const provider = expectInCharge('acme-sso', 'oidc')
    expect(provider.connection).toMatchObject({
      authorize_url: `${PORTAL}/oauth2/authorize`,
      legacy_callback: LEGACY_CALLBACK,
    })
    const before = account(tag) as Account
    expect(before.body, "loginsso_id became the account's link").toMatchObject({
      sso_provider: 'acme-sso',
      sso_subject: PEOPLE.marie.login,
    })
    expect(before.body).not.toHaveProperty('loginsso_id')
    expect(
      account('JeanMartin')?.password,
      'md5 is kept where LDAP was not in charge',
    ).toBe('md5')
    const everyone = accounts()

    const page = await (await browser.newContext()).newPage()
    const sentBack: string[] = []
    page.on('request', (request) => {
      // the portal escapes the slashes of the query it was given: `?api%2Fauth_sso%2Fcallback=&code=…`
      const url = new URL(request.url())
      if (
        url.origin === new URL(WIKI).origin &&
        [...url.searchParams.keys()][0] === 'api/auth_sso/callback'
      ) {
        sentBack.push(request.url())
      }
    })
    const authorize = page.waitForRequest((request) =>
      request.url().startsWith(`${PORTAL}/oauth2/authorize`),
    )
    await page.goto('/?user')
    await page
      .locator(`${GUEST_CARD} .yw-sign-in__provider`, { hasText: 'Acme SSO' })
      .click()
    expect(
      new URL((await authorize).url()).searchParams.get('redirect_uri'),
    ).toBe(LEGACY_CALLBACK)
    await signInAtPortal(page, PEOPLE.marie.login, PASSWORD)

    expect(
      sentBack,
      'the portal sends the browser back to the legacy callback',
    ).toHaveLength(1)
    expect(new URL(sentBack[0]).searchParams.get('code')).toBeTruthy()
    await expectSignedInAs(page, tag)
    expectHerAccount(tag, before, everyone, 'acme-sso')
    expect(groupsOf(tag), 'the group mapping was carried over').toContain(
      'Editors',
    )

    // the pages loginsso seeded link to the account screens now
    await page.goto('/?PageMenuUser')
    const profile = page.locator('.page a[href$="?user"]', {
      hasText: 'Consulter mon profil',
    })
    await expect(profile).toBeVisible()
    await expect(page.locator('.page')).not.toContainText('linktouserprofil')
    await profile.click()
    await expect(page).toHaveURL(/\?user$/)
    await expect(page.locator('body')).toContainText(tag)
    await page.goto('/?UserEntries')
    await expect(
      page.locator('.page a[href$="?user/entries"]', { hasText: 'Mes fiches' }),
    ).toBeVisible()
    await page.context().close()
  })

  test('loginldap: Marie signs in to her account, and the md5 hashes are gone', async ({
    page,
  }) => {
    const tag = PEOPLE.marie.login
    const directory = new URL(LDAP_URI)
    loadDoryphore({
      tool: 'loginldap',
      config: {
        ldap_host: directory.hostname,
        ldap_port: directory.port,
        ldap_base: PEOPLE_BASE,
      },
      accounts: [
        // loginldap kept an md5 of the directory password in the account
        {
          name: tag,
          email: PEOPLE.marie.email,
          password: md5(PASSWORD),
          motto: 'Marie, since Doryphore',
        },
        {
          name: PEOPLE.jean.login,
          email: PEOPLE.jean.email,
          password: md5('JeansOldPassword1'),
        },
      ],
      groups: { [HANDMADE]: [tag] },
    })

    const provider = expectInCharge('ldap', 'ldap')
    expect(provider.connection).toEqual({
      uri: LDAP_URI,
      bind_dn_template: `uid={login},${PEOPLE_BASE}`,
    })
    expect(daysUntil(provider.match_by_name_until)).toBeGreaterThan(360)
    expect(daysUntil(provider.match_by_name_until)).toBeLessThan(370)
    const before = account(tag) as Account
    for (const login of [tag, PEOPLE.jean.login]) {
      expect(
        account(login),
        'no md5 hash is left, in any revision',
      ).toMatchObject({
        password: 'none',
        md5_revisions: 0,
      })
    }
    const everyone = accounts()

    await page.goto('/?user')
    await signInAtWikiForm(page, 'ldap', PEOPLE.marie.login, PASSWORD)

    await expectSignedInAs(page, tag)
    expectHerAccount(tag, before, everyone, 'ldap')
  })

  test('logincas: Marie signs in to her account through the CAS server', async ({
    browser,
  }) => {
    const tag = PEOPLE.marie.login
    loadDoryphore({
      tool: 'logincas',
      config: {
        cas_host: PORTAL_HOST,
        cas_port: '443',
        cas_context: '/cas',
        cas_server_ca_cert_path: '',
      },
      accounts: [
        // logincas named the account after the CAS `name` attribute, and gave it a random md5 password
        {
          name: tag,
          email: PEOPLE.marie.email,
          password: md5('cas_6541f0c1a2b3c'),
          motto: 'Marie, since Doryphore',
        },
      ],
      groups: { [HANDMADE]: [tag] },
    })

    const provider = expectInCharge('cas', 'cas')
    expect(provider.connection).toEqual({
      server_url: `https://${PORTAL_HOST}:443/cas`,
      version: '2.0',
    })
    expect(provider.attributes).toEqual({ id: 'name', email: 'mail' })
    expect(daysUntil(provider.match_by_name_until)).toBeGreaterThan(360)
    expect(daysUntil(provider.match_by_name_until)).toBeLessThan(370)
    const before = account(tag) as Account
    expect(before.password, 'md5 is kept where LDAP was not in charge').toBe(
      'md5',
    )
    const everyone = accounts()

    // phpCAS only spoke https, and the development portal only http: the one setting this stack cannot keep
    const section = converted().providers
    const overPlainHttp = {
      ...provider,
      connection: { ...provider.connection, server_url: `${PORTAL}/cas` },
    }
    setProviders({ ...section, cas: overPlainHttp })

    // The development portal does not release `name`, the attribute logincas named accounts after: the converted ID is refused.
    const since = latestRow()
    const refused = await signInThroughPortal(browser, 'CAS')
    await expect(refused.locator('h1')).toBeVisible()
    await refused.context().close()
    const diagnostic = journal('login.refused.missing_attribute.cas').find(
      (row) => Number(row.id) > since,
    )
    expect(diagnostic, 'the portal released no `name`').toBeTruthy()
    const received = Object.keys(
      (diagnostic?.context.attributes as Record<string, unknown>) ?? {},
    )
    test
      .info()
      .annotations.push({
        type: 'portal CAS attributes',
        description: received.join(', '),
      })
    expect(received).not.toContain('name')
    expect(received).toContain('uid')
    expect(accounts(), 'a refusal creates nothing').toEqual(everyone)

    // With the user ID the portal does release, the rest of the conversion finds her account by its name.
    setProviders({
      ...section,
      cas: {
        ...overPlainHttp,
        attributes: { ...provider.attributes, id: 'uid' },
      },
    })
    const page = await signInThroughPortal(browser, 'CAS')

    await expectSignedInAs(page, tag)
    expectHerAccount(tag, before, everyone, 'cas')
    await page.context().close()
  })
})

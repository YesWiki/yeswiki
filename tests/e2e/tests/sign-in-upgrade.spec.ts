import { execFileSync } from 'child_process'
import { createHash } from 'crypto'
import { fileURLToPath } from 'url'
import { Browser, expect, Page, test } from '@playwright/test'
import {
  IDP,
  idpSignsIn,
  oidcSignsIn,
  RedirectType,
  WIKI,
} from '../helpers/idp'
import { instanceDir } from '../helpers/instance'
import { mockReady, mockReset } from '../helpers/mockserver'
import {
  accountWithEmail,
  groupsOf,
  LDAP_URI,
  PASSWORD,
  PEOPLE,
  PEOPLE_BASE,
  returnFromProvider,
  setProviders,
  signInAtWikiForm,
} from '../helpers/sso'

/**
 * The upgrade from Doryphore, for each login extension (spec section 13): a wiki that signed in through loginsso, loginldap or
 * logincas is put back as Doryphore left it, upgraded again, and its people sign in against the mock identity provider and the
 * directory of the development stack. Marie reaches the account she had, nobody gets a second one, and loginsso's provider keeps the callback registered at it.
 */

/** The address the converted provider sends, which UPGRADE.md asks to register at the provider before upgrading. */
const PROVIDER_CALLBACK = `${WIKI}/?user/sign-in/acme-sso/callback`

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

/** From the account page, out to the provider through its button and back as Marie, in a browser of its own. */
const signInThroughProvider = async (
  browser: Browser,
  label: string,
  type: RedirectType,
): Promise<Page> => {
  await idpSignsIn(type, PEOPLE.marie)
  const page = await (await browser.newContext()).newPage()
  await page.goto('/?user')
  await page
    .locator(`${GUEST_CARD} .yw-sign-in__provider`, { hasText: label })
    .click()
  await returnFromProvider(page)

  return page
}

test.beforeAll(mockReady)

test.describe('the upgrade from Doryphore', () => {
  test.beforeEach(mockReset)

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
                urlAuthorize: `${IDP}/authorize`,
                urlAccessToken: `${IDP}/token`,
                urlResourceOwnerDetails: `${IDP}/userinfo`,
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
      authorize_url: `${IDP}/authorize`,
    })
    expect(provider.connection).not.toHaveProperty('legacy_callback')
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
      // the first key of the query, whether a provider sends its slashes as they are or escaped (`?user%2Fsign-in%2F…`)
      const url = new URL(request.url())
      if (
        url.origin === new URL(WIKI).origin &&
        [...url.searchParams.keys()][0] === 'user/sign-in/acme-sso/callback'
      ) {
        sentBack.push(request.url())
      }
    })
    await oidcSignsIn(PEOPLE.marie)
    const authorize = page.waitForRequest((request) =>
      request.url().startsWith(`${IDP}/authorize`),
    )
    await page.goto('/?user')
    await page
      .locator(`${GUEST_CARD} .yw-sign-in__provider`, { hasText: 'Acme SSO' })
      .click()
    expect(
      new URL((await authorize).url()).searchParams.get('redirect_uri'),
    ).toBe(PROVIDER_CALLBACK)
    await returnFromProvider(page)

    expect(
      sentBack,
      'the provider sends the browser back to the converted provider callback',
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
        cas_host: new URL(IDP).host,
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
      server_url: `https://${new URL(IDP).host}:443/cas`,
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

    // NOT EXERCISED: the converted server URL. logincas's phpCAS only spoke https, so the conversion writes
    // https://<cas_host>:<cas_port>/<cas_context> (checked above), but the mock serves CAS on plain http only.
    // That one value is replaced by the mock's http address; every other converted setting, the `name` user ID included, is used unchanged.
    const section = converted().providers
    setProviders({
      ...section,
      cas: {
        ...provider,
        connection: { ...provider.connection, server_url: `${IDP}/cas` },
      },
    })
    const page = await signInThroughProvider(browser, 'CAS', 'cas')

    await expectSignedInAs(page, tag)
    expectHerAccount(tag, before, everyone, 'cas')
    await page.context().close()
  })
})

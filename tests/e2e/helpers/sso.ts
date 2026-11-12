import { execFileSync } from 'child_process'
import { fileURLToPath } from 'url'
import { expect, Page, Request } from '@playwright/test'
import { IDP, WIKI } from './idp'
import { instanceDir } from './instance'

/**
 * What the sign-in specs sign in against: a mock identity provider for OpenID Connect, SAML and CAS (MockServer, configured
 * by idp.ts before each sign-in), and the OpenLDAP directory of the development stack for LDAP (docker/README.md).
 */
export { IDP, WIKI }

export const LDAP_URI = 'ldap://yeswiki-ldap:1389'
export const PEOPLE_BASE = 'ou=people,dc=acme,dc=example'
export const GROUPS_BASE = 'ou=groups,dc=acme,dc=example'
const ADMIN_DN = 'cn=admin,dc=acme,dc=example'
const ADMIN_PASSWORD = 'AdminPassword1'

export const PASSWORD = 'TestPassword1'
export const PEOPLE = {
  marie: {
    login: 'marie',
    firstName: 'Marie',
    lastName: 'Dupont',
    email: 'marie.dupont@acme.example',
    groups: ['wiki-editors', 'staff'],
  },
  jean: {
    login: 'jean',
    firstName: 'Jean',
    lastName: 'Martin',
    email: 'jean.martin@acme.example',
    groups: ['staff'],
  },
  nomail: { login: 'nomail' },
}

export const personDn = (login: string): string => `uid=${login},${PEOPLE_BASE}`

/** What every provider releases, under the directory's names. */
const ATTRIBUTES = {
  id: 'uid',
  email: 'mail',
  first_name: 'givenName',
  last_name: 'sn',
  groups: 'groups',
}

export type ProviderType = 'oidc' | 'saml' | 'cas' | 'ldap'

/** The types whose login and password are typed into the wiki's own form, not at the provider's. */
export const PASSWORD_TYPES: ProviderType[] = ['ldap']

/** The identifier of each type's provider in `sso_providers`: it is part of the wiki's addresses for it. */
export const PROVIDER_IDS: Record<ProviderType, string> = {
  oidc: 'oidc',
  saml: 'saml-a',
  cas: 'cas',
  ldap: 'ldap',
}

/** One entry of `sso_providers` per type, against the mock identity provider and the directory. */
export const PROVIDERS: Record<ProviderType, Record<string, unknown>> = {
  oidc: {
    type: 'oidc',
    label: 'Acme OIDC',
    connection: {
      discovery_url: `${IDP}/.well-known/openid-configuration`,
      client_id: 'wiki-oidc',
      client_secret: 'wiki-oidc-secret',
    },
    attributes: ATTRIBUTES,
  },
  saml: {
    type: 'saml',
    label: 'Acme SAML',
    connection: { metadata_url: `${IDP}/saml/metadata` },
    attributes: ATTRIBUTES,
  },
  cas: {
    type: 'cas',
    label: 'Acme CAS',
    connection: { server_url: `${IDP}/cas`, version: '3.0' },
    attributes: ATTRIBUTES,
  },
  ldap: {
    type: 'ldap',
    label: 'Acme directory',
    connection: {
      uri: LDAP_URI,
      bind_dn_template: `uid={login},${PEOPLE_BASE}`,
      // the directory has no memberOf: groups come from a search, by their cn, under the `groups` name the others use
      group_base: GROUPS_BASE,
    },
    attributes: ATTRIBUTES,
  },
}

/** A section with local accounts and the type's provider, its entry changed by `overrides`. */
export const sectionFor = (
  type: ProviderType,
  overrides: Record<string, unknown> = {},
): Record<string, unknown> => ({
  local: { type: 'local' },
  [PROVIDER_IDS[type]]: { ...PROVIDERS[type], ...overrides },
})

const helper = (name: string): string =>
  fileURLToPath(new URL(`./${name}`, import.meta.url))

const php = (script: string, args: string[]): string =>
  execFileSync('php', [helper(script), ...args], {
    cwd: instanceDir(),
    encoding: 'utf8',
  })

/** How long the web server's opcode cache may keep serving the previous configuration file (opcache.revalidate_freq). */
const OPCACHE_REVALIDATE_MS = 3100

/** Writes `sso_providers` into the wiki's configuration file, and waits until the web server reads the new one. */
export const setProviders = (section: object): void => {
  php('set-sso-providers.php', [JSON.stringify(section)])
  Atomics.wait(
    new Int32Array(new SharedArrayBuffer(4)),
    0,
    0,
    OPCACHE_REVALIDATE_MS,
  )
}

/**
 * What the journal must never hold: the test client secret, a key, or what a SAML provider sent, decoded or as posted (base64
 * of `<samlp:Response` or of `<?xml `).
 */
export const SENT_OR_SECRET =
  /wiki-oidc-secret|SAMLResponse|saml2?p?:(Response|Assertion)|PHNhbWxwOlJlc3BvbnNl|PD94bWwg|BEGIN [A-Z ]*KEY/

export type JournalRow = {
  id: number | string
  action: string
  target: string
  channel: string
  context: Record<string, unknown>
}

/** The Journal rows whose action is LIKE `pattern`, oldest first. */
export const journal = (pattern: string): JournalRow[] =>
  JSON.parse(php('sso-wiki.php', ['journal', pattern]))

/** Deletes the Journal's diagnostics of refused sign-ins, which collapse per provider and reason into one row a day. */
export const forgetDiagnostics = (): void => {
  php('sso-wiki.php', ['forget-diagnostics'])
}

/** Deletes the wiki accounts that have these emails, so a sign-in meets the wiki for the first time. */
export const forgetAccounts = (...emails: string[]): void => {
  php('sso-wiki.php', ['forget', ...emails])
}

/** The name of the account that has this email, null when none has. */
export const accountWithEmail = (email: string): string | null =>
  JSON.parse(php('sso-wiki.php', ['account', email]))

export const groupsOf = (account: string): string[] =>
  JSON.parse(php('sso-wiki.php', ['groups', account]))

export const addToGroup = (group: string, account: string): void => {
  php('sso-wiki.php', ['add-to-group', group, account])
}

/**
 * Does what sends the browser out to the identity provider (`leave`, a click), then waits until it has been there and is back
 * on a page of the wiki: the mock answers with a redirect or a form that posts itself, so the browser goes through it alone.
 * Watching starts before `leave`, since a redirect may be over by the time the click returns.
 */
export const throughProvider = async (
  page: Page,
  leave: () => Promise<unknown>,
): Promise<void> => {
  const idp = new URL(IDP).origin
  const wiki = new URL(WIKI).origin
  let wentThere = false
  const watch = (request: Request) => {
    if (
      request.isNavigationRequest() &&
      request.frame() === page.mainFrame() &&
      new URL(request.url()).origin === idp
    ) {
      wentThere = true
    }
  }
  page.on('request', watch)
  try {
    const back = page.waitForEvent('framenavigated', {
      predicate: (frame) =>
        wentThere &&
        frame === page.mainFrame() &&
        new URL(frame.url()).origin === wiki,
    })
    await leave()
    await back
    await page.waitForLoadState('domcontentloaded')
  } finally {
    page.off('request', watch)
  }
}

/**
 * Types the login and password into the wiki's own sign-in form, on the page the browser is at, under the tab of the provider
 * `providerId` when the form has several; then waits for the page the wiki answers with.
 */
export const signInAtWikiForm = async (
  page: Page,
  providerId: string,
  login: string,
  password: string,
): Promise<void> => {
  const form = page.locator('form.login-form')
  if ((await form.locator('.yw-sign-in__tab').count()) > 0) {
    // the radio is drawn under its label: a person clicks the label
    const choice = `input[name="provider"][value="${providerId}"]`
    const tab = form
      .locator('.yw-sign-in__tab')
      .filter({ has: page.locator(choice) })
    await tab.click()
    await expect(tab.locator(choice)).toBeChecked()
  }
  await form.locator('#inputWikiName').fill(login)
  await form.locator('#inputPassword').fill(password)
  await form.locator('button[type="submit"]').click()
  await page.waitForLoadState('domcontentloaded')
}

/** Types the login and password into an administrator's test of a password provider, then waits for the result. */
export const testAtWikiForm = async (
  page: Page,
  login: string,
  password: string,
): Promise<void> => {
  await page.locator('#sign-in-test-login').fill(login)
  await page.locator('#sign-in-test-password').fill(password)
  await page.locator('#sign-in-test-password').press('Enter')
  await page.waitForLoadState('domcontentloaded')
}

/** Whether the directory accepts this DN and password. */
export const bindAs = (dn: string, password: string): boolean => {
  try {
    execFileSync(
      'ldapwhoami',
      ['-x', '-H', LDAP_URI, '-D', dn, '-w', password],
      {
        stdio: 'ignore',
      },
    )

    return true
  } catch {
    return false
  }
}

/** Replaces one value of a directory entry, as its administrator. */
export const setDirectoryValue = (
  dn: string,
  attribute: string,
  value: string,
): void => {
  execFileSync(
    'ldapmodify',
    ['-x', '-H', LDAP_URI, '-D', ADMIN_DN, '-w', ADMIN_PASSWORD],
    {
      input: `dn: ${dn}\nchangetype: modify\nreplace: ${attribute}\n${attribute}: ${value}\n`,
      stdio: ['pipe', 'ignore', 'inherit'],
    },
  )
}
